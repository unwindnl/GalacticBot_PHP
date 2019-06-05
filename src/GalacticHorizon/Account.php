<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;
use Base32\Base32;

class Account implements XDRInputInterface, XDROutputInterface {

const KEY_TYPE_ED25519 = 0;

// Encoded stellar addresses (base-32 encodes to 'G...')
const VERSION_BYTE_ACCOUNT_ID = 6 << 3;

// Encoded stellar seeds (base-32 encodes to 'S...')
const VERSION_BYTE_SEED = 18 << 3;
	
private $keypair = null;
private $accountIDBytes = null;
private $sequence = null;

private $balances = [];
private $signers = [];

private $thresholds = null;

private $data = [];

function __construct(Keypair $keypair = null) {
	$this->sequence = new BigInteger(0);
	$this->keypair = $keypair;
	$this->accountIDBytes = $this->keypair ? self::getRawBytesFromBase32AccountId($this->keypair->getPublicKey()) : null;
}

public static function createFromKeyPair(Keypair $keypair) {
	return new self($keypair);
}

public static function createFromSecretKey(string $publicKey) {
	return new self(Keypair::createFromSecretKey($publicKey));
}

public static function createFromPublicKey(string $publicKey) {
	return new self(Keypair::createFromPublicKey($publicKey));
}

private static function getRawBytesFromBase32AccountId($base32AccountId) {
	$decoded = Base32::decode($base32AccountId);

	// Unpack version byte
	$unpacked = unpack('Cversion', substr($decoded, 0, 1));
	$version = $unpacked['version'];
	$payload = substr($decoded, 1, strlen($decoded) - 3);
	$checksum = substr($decoded, -2);

	// Verify version
	if ($version != self::VERSION_BYTE_ACCOUNT_ID) {
		$msg  = 'Invalid account ID version.';

		if ($version == self::VERSION_BYTE_SEED)
			$msg .= ' Got a private key and expected a public account ID';
	
		throw new \InvalidArgumentException($msg);
	}

	// Verify checksum of version + payload
	if (!Checksum::verify($checksum, substr($decoded, 0, -2)))
		throw new \InvalidArgumentException('Invalid checksum');

	return $payload;
}

public function getMinimumBalance() {
	// Base reserve
	$minimumBalance = 1;

	foreach($this->balances AS $balance)
		$minimumBalance += 0.5;

	$signers = count($this->signers)-1;

	$minimumBalance += 0.5 * $signers;

	foreach($this->data AS $dataEntry)
		$minimumBalance += 0.5;

	return $minimumBalance;
}

public function getBalances() {
	return $this->balances;
}

public function getSigners() {
	return $this->signers;
}

public function getData() {
	return $this->data;
}

public function getKeypair() {
	return $this->keypair;
}

public function getPublicKey() {
	return $this->keypair->getPublicKey();
}

public function fetch() {
	Client::getInstance()->get(
		sprintf("accounts/%s", $this->keypair->getPublicKey()),
		[],
		function($data) {
			$this->sequence = new BigInteger($data->sequence);
			$this->balances = [];
			
			foreach($data->balances AS $balance)
				$this->balances[] = AccountBalance::fromJSON($balance);

			$this->signers = [];
			
			foreach($data->signers AS $signer)
				$this->signers[] = AccountSigner::fromJSON($signer);
		
			$this->data = (Array)$data->data;

			if (isset($data->low_threshold)) {
				$this->thresholds = (object)Array(
					"low" => $data->low_threshold,
					"medium" => $data->med_threshold,
					"high" => $data->high_threshold
				);
			} else {
				$this->thresholds = null;
			}
		}
	);

	return true;
}

public function getNextSequenceNumber() {
	$this->fetch();

	$this->sequence = $this->sequence->add(new BigInteger(1));

	return $this->sequence;
}

public function hasValue() { return $this->keypair !== null; }

public function toXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeUnsignedInteger(self::KEY_TYPE_ED25519);
	$buffer->writeOpaqueFixed($this->accountIDBytes);
}

static public function fromXDRBuffer(XDRBuffer &$buffer) {
	$keyType = $buffer->readUnsignedInteger();
	$key = $buffer->readOpaqueFixed(32);

	$accountID = AddressableKey::addressFromRawBytes($key);

	$keypair = Keypair::createFromPublicKey($accountID);

	$o = new self($keypair);
	return $o;
}

public function getTrades($cursor = "now", $order = "asc") {
	$trades = null;

	Client::getInstance()->get(
		sprintf("accounts/%s/trades", $this->keypair->getPublicKey()),
		[
			"cursor" => $cursor,
			"order" => $order
		],
		function($data) use (&$trades) {
			$trades = [];

			foreach($data->_embedded->records AS $record) {
				$trades[] = Trade::createFromJSON($record);
			}
		}
	);

	return $trades;
}

public function getTradesStreaming($cursor, \Closure $callback, $order = "asc") {
	$trades = null;

	Client::getInstance()->stream(
		sprintf("accounts/%s/trades", $this->keypair->getPublicKey()),
		[
			"cursor" => $cursor,
			"order" => $order,
		],
		function($ID, $data) use (&$callback) {
			$callback($ID, Trade::createFromJSON($data));
		}
	);

	return $trades;
}

public function getOffers($cursor = "now", $order = "asc") {
	$offers = null;
	
	Client::getInstance()->get(
		sprintf("accounts/%s/offers", $this->keypair->getPublicKey()),
		[
			"cursor" => $cursor,
			"order" => $order
		],
		function($data) use (&$offers) {
			$offers = [];
			
			foreach($data->_embedded->records AS $record) {
				$offers[] = Offer::createFromJSON($record);
			}
		}
	);

	return $offers;
}

public function getOffersStreaming($cursor, \Closure $callback) {
	$trades = null;

	Client::getInstance()->stream(
		sprintf("accounts/%s/offers", $this->keypair->getPublicKey()),
		[
			"cursor" => $cursor
		],
		function($data) use (&$callback) {
	var_dump("createFromJSON: ", $data);
			$callback(Offer::createFromJSON($data));
		}
	);

	return $trades;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ") " . ($this->keypair === null ? "null" : $this->keypair->getPublicKey());
	$str[] = " - Balances (count: " . count($this->balances) . "):";

	foreach($this->balances AS $balance)
		$str[] = IncreaseDepth((string)$balance);

	$str[] = " - Signers (count: " . count($this->signers) . "):";

	foreach($this->signers AS $signer)
		$str[] = IncreaseDepth((string)$signer);

	$str[] = " - Data entries (count: " . count($this->data) . "):";

	foreach($this->data AS $k => $v)
		$str[] = "{$k} = {$v}";

	return implode("\n", $str);
}

}

class Offer
{

private $ID;

private $seller, $sellingAsset;

private $buyingAsset;

private $buyingAmount;

private $price;

public function getID() { return $this->ID; }

static function createFromJSON(object $data) {
	$o = new self();
	$o->ID = $data->id;

	$o->seller = Account::createFromPublicKey($data->seller);

	if ($data->selling->asset_type == "native")
		$o->sellingAsset = Asset::createNative();
	else
		$o->sellingAsset = Asset::createFromCodeAndIssuer($data->selling->asset_code, Account::createFromPublicKey($data->selling->asset_issuer));

	if ($data->buying->asset_type == "native")
		$o->buyingAsset = Asset::createNative();
	else
		$o->buyingAsset = Asset::createFromCodeAndIssuer($data->buying->asset_code, Account::createFromPublicKey($data->buying->asset_issuer));

	$o->buyingAmount = Amount::createFromFloat($data->amount);

	$o->price = Price::createFromFloat($data->price);

	return $o;
}

}

class Trade
{

private $ID, $offerID, $operationID;

private $ledgerCloseTime;

private $baseAccount, $baseAmount, $baseAsset;

private $counterOfferID, $counterAccount, $counterAmount, $counterAsset;

private $baseIsSeller;

private $price;

public function getID() { return $this->ID; }
public function getOfferID() { return $this->offerID; }
public function getCounterOfferID() { return $this->counterOfferID; }

public function getLedgerCloseTime() { return $this->ledgerCloseTime; }

public function getBaseAccount() { return $this->baseAccount; }
public function getBaseAsset() { return $this->baseAsset; }
public function getBaseIsSeller() { return $this->baseIsSeller; }

public function getCounterAccount() { return $this->counterAccount; }
public function getCounterAsset() { return $this->counterAsset; }
public function getCounterAmount() { return $this->counterAmount; }

public function getPrice() { return $this->price; }

public function getOperationID() { return $this->operationID; }

static function createFromJSON(object $data) {
	$o = new self();
	$o->ID = $data->id;
	$o->offerID = $data->offer_id;

	$date = date_parse($data->ledger_close_time);

	$o->ledgerCloseTime = new \DateTime($date["year"] . "-" . $date["month"] . "-" . $date["day"] . " " . $date["hour"] . ":" . $date["minute"] . ":" . $date["second"], null);

	$o->baseAccount = Account::createFromPublicKey($data->base_account);
	$o->baseAmount = Amount::createFromFloat($data->base_amount);

	if ($data->base_asset_type == "native")
		$o->baseAsset = Asset::createNative();
	else
		$o->baseAsset = Asset::createFromCodeAndIssuer($data->base_asset_code, Account::createFromPublicKey($data->base_asset_issuer));

	$o->counterOfferID = $data->counter_offer_id;
	$o->counterAccount = Account::createFromPublicKey($data->counter_account);
	$o->counterAmount = Amount::createFromFloat($data->counter_amount);

	if ($data->counter_asset_type == "native")
		$o->counterAsset = Asset::createNative();
	else
		$o->counterAsset = Asset::createFromCodeAndIssuer($data->counter_asset_code, Account::createFromPublicKey($data->counter_asset_issuer));

	$o->baseIsSeller = $data->base_is_seller ? true : false;

	$o->price = new Price($data->price->n, $data->price->d);

	$parts = explode("/", $data->_links->operation->href);
	$o->operationID = array_pop($parts);

	return $o;

/*
  ["base_account"]=>
  string(56) ""
  [""]=>
  string(9) "9.8728614"
  [""]=>
  string(6) "native"
  [""]=>
  string(19) "4710550934311165953"
  [""]=>
  string(56) "GCILEORWFS6PKGCXUVC73TKTPPHXADNQFLVGKVWLAW4FJJTCXNE6DGB7"
  [""]=>
  string(9) "0.2070182"
  [""]=>
  string(16) "credit_alphanum4"
  [""]=>
  string(3) "SLT"
  [""]=>
  string(56) "GCKA6K5PCQ6PNF5RQBF7PQDJWRHO6UOGFMRLK3DYHDOI244V47XKQ4GP"
  [""]=>
  bool(true)
  ["price"]=>
  object(stdClass)#1237 (2) {
    ["n"]=>
    int(152)
    ["d"]=>
    int(7249)
*/
}

}


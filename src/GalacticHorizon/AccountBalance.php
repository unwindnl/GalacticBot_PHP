<?php

namespace GalacticHorizon;

class AccountBalance {

private $balance;
private $limit;

private $buyingLiabilities;
private $sellingLiabilities;

private $lastModifiedLedger;

private $asset;

public function getBalance() { return $this->balance; }

public function getLimit() { return $this->limit; }

public function getBuyingLiabilities() { return $this->buyingLiabilities; }

public function getSellingLiabilities() { return $this->sellingLiabilities; }

public function getLastModifiedLedger() { return $this->lastModifiedLedger; }

public function getAsset() { return $this->asset; }

public static function fromJSON(object $json) {
	$o = new self();

	$o->balance = Amount::createFromFloat($json->balance);
	$o->limit = isset($json->limit) ? Amount::createFromFloat($json->limit) : null;
	
	$o->lastModifiedLedger = isset($json->last_modified_ledger) ? $json->last_modified_ledger : null;

	$o->buyingLiabilities = Amount::createFromFloat($json->buying_liabilities);
	$o->sellingLiabilities = Amount::createFromFloat($json->selling_liabilities);

	if ($json->asset_type == "native") {
		$o->asset = Asset::createNative();
	} else {
		$o->asset = Asset::createFromCodeAndIssuer($json->asset_code, Account::createFromPublicKey($json->asset_issuer));
	}

	return $o;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = " - Asset = " . $this->asset;
	$str[] = " - Balance = " . $this->balance;
	$str[] = " - Limit = " . $this->limit;
	$str[] = " - Buying liabilities = " . $this->buyingLiabilities;
	$str[] = " - Selling liabilities = " . $this->sellingLiabilities;
	$str[] = " - Last modified ledger = " . $this->lastModifiedLedger;
	return implode("\n", $str);
}

}


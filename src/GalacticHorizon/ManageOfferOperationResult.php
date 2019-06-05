<?php

namespace GalacticHorizon;

class ClaimOfferAtom implements XDRInputInterface {

private $seller;
private $offerID;

// amount and asset taken from the owner
private $assetSold;
private $amountSold;

// amount and asset sent to the owner
private $assetBought;
private $amountBought;

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$result = new self();

	$result->seller = Account::fromXDRBuffer($buffer);
	$result->offerID = $buffer->readUnsignedInteger64();

	$result->assetSold = Asset::fromXDRBuffer($buffer);
	$result->amountSold = Amount::fromXDRBuffer($buffer);

	$result->assetBought = Asset::fromXDRBuffer($buffer);
	$result->amountBought = Amount::fromXDRBuffer($buffer);

	return $result;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = "- Seller = " . $this->seller;
	$str[] = "- OfferID = " . $this->offerID;
	$str[] = "- Asset sold = " . $this->assetSold;
	$str[] = "- Amount sold = " . $this->amountSold;
	$str[] = "- Asset bought = " . $this->assetBought;
	$str[] = "- Amount bought = " . $this->amountBought;

	return implode("\n", $str);
}

}


class ManageOfferOperationResult extends OperationResult implements XDRInputInterface {

// codes considered as "success" for the operation
const MANAGE_BUY_OFFER_SUCCESS = 0;	

// codes considered as "failure" for the operation
const MANAGE_BUY_OFFER_MALFORMED = -1;					// generated offer would be invalid
const MANAGE_BUY_OFFER_SELL_NO_TRUST = -2;				// no trust line for what we're selling
const MANAGE_BUY_OFFER_BUY_NO_TRUST = -3;				// no trust line for what we're buying
const MANAGE_BUY_OFFER_SELL_NOT_AUTHORIZED = -4;		// not authorized to sell
const MANAGE_BUY_OFFER_BUY_NOT_AUTHORIZED = -5;			// not authorized to buy
const MANAGE_BUY_OFFER_LINE_FULL = -6;					// can't receive more of what it's buying
const MANAGE_BUY_OFFER_UNDERFUNDED = -7;				// doesn't hold what it's trying to sell
const MANAGE_BUY_OFFER_CROSS_SELF = -8;					// would cross an offer from the same user
const MANAGE_BUY_OFFER_SELL_NO_ISSUER = -9;				// no issuer for what we're selling
const MANAGE_BUY_OFFER_BUY_NO_ISSUER = -10;				// no issuer for what we're buying

// update errors
const MANAGE_BUY_OFFER_NOT_FOUND = -11;					// offerID does not match an existing offer

const MANAGE_BUY_OFFER_LOW_RESERVE = -12;				// not enough funds to create a new Offer

const EFFECT_MANAGE_OFFER_CREATED = 0;
const EFFECT_MANAGE_OFFER_UPDATED = 1;
const EFFECT_MANAGE_OFFER_DELETED = 2;

private $errorCode;

private $claimedOffers = [];

private $effect = null;

private $offer = null;

public function getClaimedOfferCount() { return count($this->claimedOffers); }
public function getClaimedOffer(int $index) { return $this->claimedOffers[$index]; }

public function getOffer() { return $this->offer; }

public function getErrorCode() { return $this->errorCode; }

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$result = new self();
	$result->errorCode = $buffer->readInteger();

	if ($result->errorCode == self::MANAGE_BUY_OFFER_SUCCESS) {
		$claimedOfferCount = $buffer->readUnsignedInteger();

		for($i=0; $i<$claimedOfferCount; $i++)
			$result->claimedOffers[] = ClaimOfferAtom::fromXDRBuffer($buffer);

		$result->effect = $buffer->readUnsignedInteger();

		switch($result->effect) {
			case self::EFFECT_MANAGE_OFFER_CREATED:
			case self::EFFECT_MANAGE_OFFER_UPDATED:
					$result->offer = OfferEntry::fromXDRBuffer($buffer);
				break;
		}
	}

	return $result;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = "- Error code = " . $this->errorCode;
	$str[] = "* Claimed offers (" . count($this->claimedOffers) . ") = ";

	foreach($this->claimedOffers AS $offer)
		$str[] = IncreaseDepth((string)$offer);
	
	$str[] = "- Effect = " . ($this->effect === null ? "null" : $this->effect);
	$str[] = "* Offer\n" . IncreaseDepth((string)$this->offer);

	return implode("\n", $str);
}

}


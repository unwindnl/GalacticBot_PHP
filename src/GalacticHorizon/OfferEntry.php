<?php

namespace GalacticHorizon;

class OfferEntry implements XDRInputInterface {

private $seller = null;

private $sellingAsset = null;
private $buyingAsset = null;

private $amount = null;

private $price = null;

private $offerID = null;

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();

	$o->seller = Account::fromXDRBuffer($buffer);
	$o->offerID = $buffer->readUnsignedInteger64();

	$o->sellingAsset = Asset::fromXDRBuffer($buffer);
	$o->buyingAsset = Asset::fromXDRBuffer($buffer);

	$o->sellingAmount = Amount::fromXDRBuffer($buffer);

	$o->price = Price::fromXDRBuffer($buffer);

	return $o;
}

public function getOfferID() { return $this->offerID; }

public function __toString() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = "- Seller = " . $this->seller;
	$str[] = "- Selling = " . $this->sellingAsset;
	$str[] = "- Buying = " . $this->buyingAsset;
	$str[] = "- Selling amount = " . $this->sellingAmount;
	$str[] = "- Price = " . $this->price;
	$str[] = "- OfferID = " . ($this->offerID === null ? "null" : $this->offerID);
		
	return implode("\n", $str);
}

}



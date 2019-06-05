<?php

namespace GalacticHorizon;

class ManageOfferOperation extends Operation {

private $sellingAsset = null;
private $buyingAsset = null;

private $amount = null;

private $price = null;

private $offerID = null;

public function getType() { return static::TYPE_MANAGE_OFFER; }

public function setSellingAsset(Asset $sellingAsset) {
	$this->sellingAsset = $sellingAsset;
}	

public function getSellingAsset() { return $this->sellingAsset; }

public function setBuyingAsset(Asset $buyingAsset) {
	$this->buyingAsset = $buyingAsset;
}	

public function getBuyingAsset() { return $this->buyingAsset; }

public function getAmount() { return $this->amount; }

public function setAmount(Amount $amount) {
	$this->amount = $amount;
}	

public function getPrice() { return $this->price; }

public function setPrice(Price $price) {
	$this->price = $price;
}	

public function setOfferID(string $offerID = null) {
	$this->offerID = $offerID;
}	

protected function extendXDRBuffer(XDRBuffer &$buffer) {
	$this->sellingAsset->toXDRBuffer($buffer);
	$this->buyingAsset->toXDRBuffer($buffer);

	$this->amount->toXDRBuffer($buffer);

	$this->price->toXDRBuffer($buffer);

	$buffer->writeUnsignedInteger64($this->offerID);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();

	$o->sellingAsset = Asset::fromXDRBuffer($buffer);
	$o->buyingAsset = Asset::fromXDRBuffer($buffer);

	$o->amount = Amount::fromXDRBuffer($buffer);

	$o->price = Price::fromXDRBuffer($buffer);

	$o->offerID = $buffer->readUnsignedInteger64();

	return $o;
}

public function toString($depth) {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = $depth . "- Selling = " . $this->sellingAsset;
	$str[] = $depth . "- Buying = " . $this->buyingAsset;
	$str[] = $depth . "- Amount = " . $this->amount;
	$str[] = $depth . "- Price = " . $this->price;
	$str[] = $depth . "- OfferID = " . ($this->offerID === null ? "null" : $this->offerID);
		
	return implode("\n", $str);
}

}


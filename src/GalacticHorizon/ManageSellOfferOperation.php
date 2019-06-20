<?php

namespace GalacticHorizon;

class ManageSellOfferOperation extends Operation {

private $sellingAsset = null;
private $buyingAsset = null;

private $sellAmount = null;

private $price = null;

private $offerID = null;

public function getType() { return static::TYPE_MANAGE_SELL_OFFER; }

public function setSellingAsset(Asset $sellingAsset) {
	$this->sellingAsset = $sellingAsset;
}	

public function getSellingAsset() { return $this->sellingAsset; }

public function setBuyingAsset(Asset $buyingAsset) {
	$this->buyingAsset = $buyingAsset;
}	

public function getBuyingAsset() { return $this->buyingAsset; }

public function getSellAmount() { return $this->sellAmount; }

public function setSellAmount(Amount $sellAmount) {
	$this->sellAmount = $sellAmount;
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

	$this->sellAmount->toXDRBuffer($buffer);

	$this->price->toXDRBuffer($buffer);

	$buffer->writeUnsignedInteger64($this->offerID);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();

	$o->sellingAsset = Asset::fromXDRBuffer($buffer);
	$o->buyingAsset = Asset::fromXDRBuffer($buffer);

	$o->sellAmount = Amount::fromXDRBuffer($buffer);

	$o->price = Price::fromXDRBuffer($buffer);

	$o->offerID = $buffer->readUnsignedInteger64();

	return $o;
}

public function toString($depth) {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = $depth . "- Selling = " . $this->sellingAsset;
	$str[] = $depth . "- Buying = " . $this->buyingAsset;
	$str[] = $depth . "- Selling amount = " . $this->sellAmount;
	$str[] = $depth . "- Price = " . $this->price;
	$str[] = $depth . "- OfferID = " . ($this->offerID === null ? "null" : $this->offerID);
		
	return implode("\n", $str);
}

}


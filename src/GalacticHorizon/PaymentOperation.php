<?php

namespace GalacticHorizon;

class PaymentOperation extends Operation {

private $destinationAccount = null;
private $asset = null;
private $amount = null;

public function getType() { return static::TYPE_PAYMENT; }

public function setDestinationAccount(Account $destinationAccount) {
	$this->destinationAccount = $destinationAccount;
}	

public function setAsset(Asset $asset) {
	$this->asset = $asset;
}	

public function setAmount(Amount $amount) {
	$this->amount = $amount;
}	

protected function extendXDRBuffer(XDRBuffer &$buffer) {
	$this->destinationAccount->toXDRBuffer($buffer);
	$this->asset->toXDRBuffer($buffer);
	$this->amount->toXDRBuffer($buffer);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();
	$o->destinationAccount = Account::fromXDRBuffer($buffer);
	$o->asset = Asset::fromXDRBuffer($buffer);
	$o->amount = Amount::fromXDRBuffer($buffer);

	return $o;
}

public function toString($depth) {
	$str = [];
	$str[] = $depth . "- Destination account = " . $this->destinationAccount;
	$str[] = $depth . "- Asset = " . $this->asset;
	$str[] = $depth . "- Amount = " . $this->amount;
		
	return implode("\n", $str);
}

}


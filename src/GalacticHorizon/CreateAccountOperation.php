<?php

namespace GalacticHorizon;

class CreateAccountOperation extends Operation {

private $destinationAccount = null;
private $startingBalance = null;

public function getType() { return static::TYPE_CREATE_ACCOUNT; }

public function setDestinationAccount(Account $destinationAccount) {
	$this->destinationAccount = $destinationAccount;
}	

public function setStartingBalance(Amount $startingBalance) {
	$this->startingBalance = $startingBalance;
}	

protected function extendXDRBuffer(XDRBuffer &$buffer) {
	$this->destinationAccount->toXDRBuffer($buffer);
	$this->startingBalance->toXDRBuffer($buffer);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();
	$o->destinationAccount = Account::fromXDRBuffer($buffer);
	$o->startingBalance = Amount::fromXDRBuffer($buffer);

	return $o;
}

public function toString($depth) {
	$str = [];
	$str[] = $depth . "- Destination account = " . $this->destinationAccount;
	$str[] = $depth . "- Starting balance = " . $this->startingBalance;
		
	return implode("\n", $str);
}

}


<?php

namespace GalacticHorizon;

abstract class Operation implements XDRInputInterface, XDROutputInterface {

const TYPE_CREATE_ACCOUNT       = 0;
const TYPE_PAYMENT              = 1;
const TYPE_PATH_PAYMENT         = 2;
const TYPE_MANAGE_SELL_OFFER    = 3;
const TYPE_CREATE_PASSIVE_OFFER = 4;
const TYPE_SET_OPTIONS          = 5;
const TYPE_CHANGE_TRUST         = 6;
const TYPE_ALLOW_TRUST          = 7;
const TYPE_ACCOUNT_MERGE        = 8;
const TYPE_INFLATION            = 9;
const TYPE_MANAGE_DATA          = 10;
const TYPE_BUMP_SEQUENCE        = 11;
const TYPE_MANAGE_BUY_OFFER     = 12;

private $sourceAccount = null;

public function __construct(Account $sourceAccount = null) {
	$this->sourceAccount = $sourceAccount ? $sourceAccount : new Account();
}

public function setSourceAccount(Account $source) {
	$this->sourceAccount = $source;
}

public function hasValue() { return true; }

final public function toXDRBuffer(XDRBuffer &$buffer) {
	// Source Account
	$buffer->writeOptional($this->sourceAccount);

	// Type
	$buffer->writeUnsignedInteger($this->getType());

	$this->extendXDRBuffer($buffer);
}

abstract public function getType();

abstract protected function extendXDRBuffer(XDRBuffer &$buffer);

abstract public function toString($depth);

public function getTypeName() {
	switch($this->getType()) {
		case self::TYPE_CREATE_ACCOUNT:				return "TYPE_CREATE_ACCOUNT";
		case self::TYPE_PAYMENT:					return "TYPE_PAYMENT";
		case self::TYPE_PATH_PAYMENT:				return "TYPE_PATH_PAYMENT";
		case self::TYPE_MANAGE_SELL_OFFER:			return "TYPE_MANAGE_SELL_OFFER";
		case self::TYPE_CREATE_PASSIVE_OFFER:		return "TYPE_CREATE_PASSIVE_OFFER";
		case self::TYPE_SET_OPTIONS:				return "TYPE_SET_OPTIONS";
		case self::TYPE_CHANGE_TRUST:				return "TYPE_CHANGE_TRUST";
		case self::TYPE_ALLOW_TRUST:				return "TYPE_ALLOW_TRUST";
		case self::TYPE_ACCOUNT_MERGE:				return "TYPE_ACCOUNT_MERGE";
		case self::TYPE_INFLATION:					return "TYPE_INFLATION";
		case self::TYPE_MANAGE_DATA:				return "TYPE_MANAGE_DATA";
		case self::TYPE_BUMP_SEQUENCE:				return "TYPE_BUMP_SEQUENCE";
		case self::TYPE_MANAGE_BUY_OFFER:			return "TYPE_MANAGE_BUY_OFFER";
	}

	return "Unknown (" . $this->getType() . ")";
}

public function __tostring() {
	$str = [];
	$str[] = "* (Operation)";
	$str[] = " - Source account = " . $this->sourceAccount;
	$str[] = " - Type = " . $this->getTypeName();
	$str[] = $this->toString("     ");
		
	return implode("\n", $str);
}

}

class OperationFactory extends Operation {

public function getType() { return null; }
protected function extendXDRBuffer(XDRBuffer &$buffer) { }
public function toString($depth) {}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$sourceAccount = null;

	if ($buffer->readBoolean())
		$sourceAccount = Account::fromXDRBuffer($buffer);

	$type = $buffer->readUnsignedInteger();

	switch($type) {
		case self::TYPE_PAYMENT:			$operation = PaymentOperation::fromXDRBuffer($buffer); break;
		case self::TYPE_MANAGE_SELL_OFFER:	$operation = ManageOfferOperation::fromXDRBuffer($buffer); break;
		case self::TYPE_ALLOW_TRUST:		$operation = AllowTrustOperation::fromXDRBuffer($buffer); break;
		case self::TYPE_CHANGE_TRUST:		$operation = ChangeTrustOperation::fromXDRBuffer($buffer); break;
		case self::TYPE_MANAGE_BUY_OFFER:	$operation = ManageOfferOperation::fromXDRBuffer($buffer); break;

		default:
			throw \GalacticHorizon\Exception::create(
				\GalacticHorizon\Exception::TYPE_UNIMPLEMENTED_FEATURE,
				"Operation type with code '{$type}' isn't implemented yet in " . __FILE__ . "."
			);
			break;
	}

	if ($sourceAccount)
		$operation->setSourceAccount($sourceAccount);

	return $operation;
}

}



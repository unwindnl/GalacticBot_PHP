<?php

namespace GalacticHorizon;

class OperationResult implements XDRInputInterface {


protected function __construct() {}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$errorCode = $buffer->readInteger();

	if ($errorCode != 0) {
		return FailedOperationResult::fromErrorCode($errorCode);
	}

	$type = $buffer->readInteger();

	switch($type) {
		case Operation::TYPE_PAYMENT:					return PaymentOperationResult::fromXDRBuffer($buffer); break;
		case Operation::TYPE_MANAGE_OFFER:				return ManageOfferOperationResult::fromXDRBuffer($buffer); break;
		case Operation::TYPE_ALLOW_TRUST:				return AllowTrustOperationResult::fromXDRBuffer($buffer); break;
		case Operation::TYPE_CHANGE_TRUST:				return ChangeTrustOperationResult::fromXDRBuffer($buffer); break;

		default:
			throw \GalacticHorizon\Exception::create(
				\GalacticHorizon\Exception::TYPE_UNIMPLEMENTED_FEATURE,
				"OperationResult type with code '{$type}' isn't implemented yet in " . __FILE__ . "."
			);
			break;
	}

	return NULL;
}

}

class FailedOperationResult extends OperationResult {
const BAD_AUTH						= -1; // too few signatures or wrong network
const NO_ACCOUNT					= -2; // source account doesn't exist

private $errorCode;

static function fromResultCode($resultCode) {
	$o = new self();
	$o->errorCode = $errorCode;
	return $o;
}

}


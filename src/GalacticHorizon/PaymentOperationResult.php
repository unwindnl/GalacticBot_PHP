<?php

namespace GalacticHorizon;

class PaymentOperationResult extends OperationResult implements XDRInputInterface {

const PAYMENT_SUCCESS = 0;					// payment successfuly completed

// codes considered as "failure" for the operation
const PAYMENT_MALFORMED = -1;				// bad input
const PAYMENT_UNDERFUNDED = -2;				// not enough funds in source account
const PAYMENT_SRC_NO_TRUST = -3;			// no trust line on source account
const PAYMENT_SRC_NOT_AUTHORIZED = -4;		// source not authorized to transfer
const PAYMENT_NO_DESTINATION = -5;			// destination account does not exist
const PAYMENT_NO_TRUST = -6;				// destination missing a trust line for asset
const PAYMENT_NOT_AUTHORIZED = -7;			// destination not authorized to hold asset
const PAYMENT_LINE_FULL = -8;				// destination would go above their limit
const PAYMENT_NO_ISSUER = -9;				// missing issuer on asset

private $code = null;

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$result = new self();
	$result->errorCode = $buffer->readInteger();
	return $result;
}

public function __tostring() {
	$str = [];
	$str[] = "(PaymentResult) Error code = " . $this->errorCode;

	return implode("\n", $str);
}

}


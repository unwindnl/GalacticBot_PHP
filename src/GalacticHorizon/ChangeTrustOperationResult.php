<?php

namespace GalacticHorizon;

class ChangeTrustOperationResult extends OperationResult implements XDRInputInterface {

// codes considered as "success" for the operation
const CHANGE_TRUST_SUCCESS = 0;

// codes considered as "failure" for the operation
const CHANGE_TRUST_MALFORMED = -1;			// bad input
const CHANGE_TRUST_NO_ISSUER = -2;			// could not find issuer
const CHANGE_TRUST_INVALID_LIMIT = -3;		// cannot drop limit below balance

// cannot create with a limit of 0
const CHANGE_TRUST_LOW_RESERVE = -4;		// not enough funds to create a new trust line,
const CHANGE_TRUST_SELF_NOT_ALLOWED = -5;	// trusting self is not allowed

private $errorCode;

public function getErrorCode() { return $this->errorCode; }

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$result = new self();
	$result->errorCode = $buffer->readInteger();

	return $result;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = "- Error code = " . $this->errorCode;

	return implode("\n", $str);
}

}


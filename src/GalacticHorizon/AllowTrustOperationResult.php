<?php

namespace GalacticHorizon;

class AllowTrustOperationResult extends OperationResult implements XDRInputInterface {

// codes considered as "success" for the operation
const ALLOW_TRUST_SUCCESS = 0;

// codes considered as "failure" for the operation
const ALLOW_TRUST_MALFORMED = -1;				// asset is not ASSET_TYPE_ALPHANUM
const ALLOW_TRUST_NO_TRUST_LINE = -2;			// trustor does not have a trustline

// source account does not require trust
const ALLOW_TRUST_TRUST_NOT_REQUIRED = -3;
const ALLOW_TRUST_CANT_REVOKE = -4;				// source account can't revoke trust,
const ALLOW_TRUST_SELF_NOT_ALLOWED = -5;		// trusting self is not allowed

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


<?php

namespace GalacticHorizon;

class CreateAccountOperationResult extends OperationResult implements XDRInputInterface {

// codes considered as "success" for the operation
const CREATE_ACCOUNT_SUCCESS = 0;			// account was created

    // codes considered as "failure" for the operation
const CREATE_ACCOUNT_MALFORMED = -1;		// invalid destination
const CREATE_ACCOUNT_UNDERFUNDED = -2;		// not enough funds in source account
const CREATE_ACCOUNT_LOW_RESERVE = -3;		// would create an account below the min reserve
const CREATE_ACCOUNT_ALREADY_EXIST = -4;	// account already exists

private $code = null;

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$result = new self();
	$result->errorCode = $buffer->readInteger();
	return $result;
}

public function __tostring() {
	$str = [];
	$str[] = "(CreateAccountOperationResult) Error code = " . $this->errorCode;

	return implode("\n", $str);
}

}


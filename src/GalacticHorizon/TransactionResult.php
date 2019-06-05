<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;

class TransactionResult implements XDRInputInterface {

const TX_SUCCESS				= 0; // all operations succeeded

const TX_FAILED					= -1; // one of the operations failed (none were applied)

const TX_TOO_EARLY				= -2; // ledger closeTime before minTime
const TX_TOO_LATE				= -3; // ledger closeTime after maxTime
const TX_MISSING_OPERATION		= -4; // no operation was specified
const TX_BAD_SEQ				= -5; // sequence number does not match source account

const TX_BAD_AUTH				= -6; // too few valid signatures / wrong network
const TX_INSUFFICIENT_BALANCE	= -7; // fee would bring account below reserve
const TX_NO_ACCOUNT				= -8; // source account not found
const TX_INSUFFICIENT_FEE		= -9; // fee is too small
const TX_BAD_AUTH_EXTRA			= -10; // unused signatures attached to transaction
const TX_INTERNAL_ERROR			= -11; // an unknown error occured

private $hash = null;
private $ledger = null;
private $envelopeXDRString = null;

private $feeCharged = null;
private $errorCode = self::TX_FAILED;
private $results = [];

public function setHash($hash) {
	$this->hash = $hash;
}

public function setLedger($ledger) {
	$this->ledger = $ledger;
}

public function setEnvelopeXDRString($envelopeXDRString) {
	$this->envelopeXDRString = $envelopeXDRString;
}

public function getHash() { return $this->hash; }

public function getErrorCode() { return $this->errorCode; }

public function getFeeCharged() { return $this->feeCharged; }

public function getResultCount() { return count($this->results); }
public function getResult(int $index) { return $this->results[$index]; }

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$result = new self();
	
	$result->feeCharged = Amount::fromXDRBuffer($buffer);
	$result->errorCode = $buffer->readInteger();

	if ($result->errorCode == self::TX_SUCCESS || $result->errorCode == self::TX_FAILED) {
		$resultCount = $buffer->readUnsignedInteger();

		for($i=0; $i<$resultCount; $i++)
			$result->results[] = OperationResult::fromXDRBuffer($buffer);
	}
	
	return $result;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = "- Hash = " . $this->hash;
	$str[] = "- Free charged = " . $this->feeCharged->toString();
	$str[] = "- Error code = " . $this->errorCode;
	$str[] = "* Results (" . count($this->results) . ")";
	
	foreach($this->results AS $result)
		$str[] = IncreaseDepth($result);

	return implode("\n", $str);
}

}


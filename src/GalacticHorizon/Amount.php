<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;
use Base32\Base32;

class Amount implements XDRInputInterface, XDROutputInterface {

const STROOP_SCALE = 10000000; // 10 million, 7 zeroes

private $value = null;

function __construct() {
	$this->value = new BigInteger(0);
}

static public function createFromString(string $value) {
	$scale = new BigInteger(static::STROOP_SCALE);

	$o = new self();
	$o->value = new BigInteger($value);
	$o->value->divide($scale);

	return $o;
}

static public function createFromFloat(float $value) {
	$value = (float)number_format($value, 7, '.', '');
	$parts = explode('.', $value);
	$unscaledAmount = new BigInteger(0);

	// Everything to the left of the decimal point
	if ($parts[0]) {
		$unscaledAmountLeft = (new BigInteger($parts[0]))->multiply(new BigInteger(static::STROOP_SCALE));
		$unscaledAmount = $unscaledAmount->add($unscaledAmountLeft);
	}

	// Add everything to the right of the decimal point
	if (count($parts) == 2 && str_replace('0', '', $parts[1]) != '') {
		// Should be a total of 7 decimal digits to the right of the decimal
		$unscaledAmountRight = str_pad($parts[1], 7, '0',STR_PAD_RIGHT);
		$unscaledAmount = $unscaledAmount->add(new BigInteger($unscaledAmountRight));
	}	

	$scale = new BigInteger(static::STROOP_SCALE);

	$o = new self();
	$o->value = $unscaledAmount;

	return $o;
}

public function toBigInteger() {
	return $this->value;
}

public function toString() {
	return $this->value->toString();
}

public function toFloat() {
	$scale = new BigInteger(static::STROOP_SCALE);

	list($quotient, $remainder) = $this->value->divide($scale);

	$number = intval($quotient->toString()) + (intval($remainder->toString()) / intval($scale->toString()));

	return (float)number_format($number, 7, '.', '');
}

public function toXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeSignedBigInteger64($this->value);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();
	$o->value = new BigInteger($buffer->readUnsignedInteger64($buffer));
	return $o;
}

public function hasValue() { return true; }

public function __tostring() {
	return "(Amount) " . $this->toFloat();
}

}


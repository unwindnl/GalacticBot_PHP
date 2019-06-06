<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;
use Base32\Base32;

class Price implements XDRInputInterface, XDROutputInterface {

private $numerator = null;
private $denominator = null;

function __construct($numerator, $denominator = 1) {
	$this->numerator = $numerator;
	$this->denominator = $denominator;
}

static public function createFromFloat(float $value) {
	$price = self::float2Rat($value);

	$o = new self($price[0], $price[1]);
	return $o;
}

public function isValid() {
	return !(is_nan($this->numerator) || is_nan($this->denominator) || is_infinite($this->numerator) || is_infinite($this->denominator));
}

public function toFloat() {
	return $this->numerator / $this->denominator;
}

public function getNumerator() {
	return $this->numerator;
}

public function getDenominator() {
	return $this->denominator;
}

public function toXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeUnsignedInteger($this->numerator);
	$buffer->writeUnsignedInteger($this->denominator);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self($buffer->readUnsignedInteger(), $buffer->readUnsignedInteger());
	return $o;
}

public function hasValue() { return true; }

public function __toString() {
	return "(Price) {$this->numerator} / {$this->denominator} (" . $this->toFloat() . ")";
}

// Source: http://jonisalonen.com/2012/converting-decimal-numbers-to-ratios/
static function float2Rat($n, $tolerance = 1.e-6) {
	if ($n <= 0)
		return [0, 0];

	$h1=1; $h2=0;
	$k1=0; $k2=1;
	$b = 1/$n;

	do {
		$b = 1/$b;
		$a = floor($b);
		$aux = $h1; $h1 = $a*$h1+$h2; $h2 = $aux;
		$aux = $k1; $k1 = $a*$k1+$k2; $k2 = $aux;
		$b = $b-$a;
	} while (abs($n-$h1/$k1) > $n*$tolerance);

	return [$h1, $k1];
}

}


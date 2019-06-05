<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;
use Base32\Base32;

class TimeBounds implements XDRInputInterface, XDROutputInterface {

private $minTimestamp = null;
private $maxTimestamp = null;

public function toXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeUnsignedInteger64($this->minTimestamp);
	$buffer->writeUnsignedInteger64($this->maxTimestamp);
}

public function __tostring() {
	$min = $this->minTimestamp ? \DateTime::createFromFormat("U", $this->minTimestamp) : null;
	$max = $this->maxTimestamp ? \DateTime::createFromFormat("U", $this->maxTimestamp) : null;

	$min = $min ? $min->format("Y-m-d H:i:s") : "null";
	$max = $max ? $max->format("Y-m-d H:i:s") : "null";

	return $min . " - " . $max;
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();
	$o->minTimestamp = $buffer->readUnsignedInteger64();
	$o->maxTimestamp = $buffer->readUnsignedInteger64();
	return $o;
}

public function hasValue() {
	return $this->minTimestamp !== null || $this->maxTimestamp !== null;
}

}


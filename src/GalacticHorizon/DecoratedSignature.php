<?php

namespace GalacticHorizon;

use Base32\Base32;

class DecoratedSignature implements XDRInputInterface, XDROutputInterface {

private $hint;
private $signature;

public function __construct($hint, $signature) {
	$this->hint = $hint;
	$this->signature = $signature;
}

public function toXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeOpaqueFixed($this->hint, 4);
	$buffer->writeOpaqueVariable($this->signature);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$hint = $buffer->readOpaqueFixed(4);
	$signature = $buffer->readOpaqueVariable();

	$o = new self($hint, $signature);
	return $o;
}

public function hasValue() { return true; }

public function __tostring() {
	return "(Signature) Hint: " . Base32::encode($this->hint);
}

}


<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;
use Base32\Base32;

class Memo implements XDRInputInterface, XDROutputInterface {

const MEMO_TYPE_NONE = 0;
const MEMO_TYPE_TEXT = 1;
const MEMO_TYPE_ID = 2;
const MEMO_TYPE_HASH = 3;
const MEMO_TYPE_RETURN = 4;

// Text memos can be up to 28 characters
const VALUE_TEXT_MAX_SIZE = 28;

private $type;

private $value;

public function __construct($type = self::MEMO_TYPE_NONE, $value = "") {
	$this->type = $type;
	$this->value = $value;
}

public function validate() {
	if ($this->type == static::MEMO_TYPE_NONE)
		return;
	
	if ($this->type == static::MEMO_TYPE_TEXT) {
		// Verify length does not exceed max
		if (strlen($this->value) > static::VALUE_TEXT_MAX_SIZE) {
			throw new \ErrorException(sprintf('memo text is greater than the maximum of %s bytes', static::VALUE_TEXT_MAX_SIZE));
		}
	} else if ($this->type == static::MEMO_TYPE_ID) {
		if ($this->value < 0)
			throw new \ErrorException('value cannot be negative');
		
		if ($this->value > PHP_INT_MAX)
			throw new \ErrorException(sprintf('value cannot be larger than %s', PHP_INT_MAX));
	} else if ($this->type == static::MEMO_TYPE_HASH || $this->type == static::MEMO_TYPE_RETURN) {
		if (strlen($this->value) != 32)
			throw new \InvalidArgumentException(sprintf('hash values must be 32 bytes, got %s bytes', strlen($this->value)));
	}
}

public function __tostring() {
	if ($this->type == static::MEMO_TYPE_NONE) {
		return "(none)";
	} else if ($this->type == static::MEMO_TYPE_TEXT) {
		return "(string) " . $this->value;
	} else if ($this->type == static::MEMO_TYPE_ID) {
		return "(ID) " . $this->value;
	} else if ($this->type == static::MEMO_TYPE_HASH) {
		return "(hash) " . $this->value;
	} else if ($this->type == static::MEMO_TYPE_RETURN) {
		return "(return) " . $this->value;
	}
}

public function hasValue() { return true; }

public function toXDRBuffer(XDRBuffer &$buffer) {
	$this->validate();

	$buffer->writeUnsignedInteger($this->type);

	if ($this->type == static::MEMO_TYPE_NONE) {
	} else if ($this->type == static::MEMO_TYPE_TEXT) {
		$buffer->writeString($this->value, static::VALUE_TEXT_MAX_SIZE);
	} else if ($this->type == static::MEMO_TYPE_ID) {
		$buffer->writeUnsignedInteger64($this->value);
	} else if ($this->type == static::MEMO_TYPE_HASH) {
		$buffer->writeOpaqueFixed($this->value, 32);
	} else if ($this->type == static::MEMO_TYPE_RETURN) {
		$buffer->writeOpaqueFixed($this->value, 32);
	}
}

public static function fromXDRBuffer(XDRBuffer &$xdr) {
	$type = $xdr->readUnsignedInteger();
	$value = null;

	if ($type == static::MEMO_TYPE_TEXT) {
		$value = $xdr->readString(static::VALUE_TEXT_MAX_SIZE);
	} else if ($type == static::MEMO_TYPE_ID) {
		$value = $xdr->readBigInteger()->toString();
	} else if ($type == static::MEMO_TYPE_HASH || $type == static::MEMO_TYPE_RETURN) {
		$value = $xdr->readOpaqueFixed(32);
	}
	
	$memo = new Memo($type, $value);
	return $memo;
}

public static function fromText($string) {
	return new self(self::MEMO_TYPE_TEXT, $string);
}

}


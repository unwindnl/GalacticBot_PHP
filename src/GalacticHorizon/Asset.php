<?php

namespace GalacticHorizon;

class Asset implements XDRInputInterface, XDROutputInterface {

const TYPE_NATIVE       = 0;
const TYPE_ALPHANUM_4   = 1;
const TYPE_ALPHANUM_12  = 2;

private $type;
private $code;
private $issuer = null;

public function __construct($type, $code = null, Account $issuer = null) {
	$this->type = $type;
	$this->code = $code;
	$this->issuer = $issuer;
}

public function setIssuer(Account $issuer) {
	$this->issuer = $issuer;
}

public function getIssuer() { return $this->issuer; }

public function getCode() { return $this->code; }

public function getType() { return $this->type; }

public function isNative() { return $this->type == self::TYPE_NATIVE; }

public static function createNative() {
	return new self(self::TYPE_NATIVE);
}

public static function createFromCodeAndIssuer(string $code, Account $issuer) {
	return new self(
		strlen($code) <= 4 ? self::TYPE_ALPHANUM_4 : self::TYPE_ALPHANUM_12,
		$code,
		$issuer
	);
}

public function toXDRBuffer(XDRBuffer &$buffer, $includeIssuer = true) {
	$buffer->writeUnsignedInteger($this->type);
	
	if ($this->type == self::TYPE_NATIVE) {
		// no additional content for native types
	} else if ($this->type == self::TYPE_ALPHANUM_4) {
		$buffer->writeOpaqueFixed($this->code, 4, true);

		if ($includeIssuer)
			$this->issuer->toXDRBuffer($buffer);
	} elseif ($this->type == self::TYPE_ALPHANUM_12) {
		$buffer->writeOpaqueFixed($this->code, 12, true);

		if ($includeIssuer)
			$this->issuer->toXDRBuffer($buffer);
	}
}

public static function fromXDRBuffer(XDRBuffer &$buffer, $includeIssuer = true) {
	$type = $buffer->readUnsignedInteger($buffer);

	$o = new self($type);

	switch($o->type) {
		case self::TYPE_NATIVE:
			break;
		case self::TYPE_ALPHANUM_4:
				$o->code = $buffer->readOpaqueFixed(4, true);

				if ($includeIssuer)
					$o->issuer = Account::fromXDRBuffer($buffer);
			break;
		case self::TYPE_ALPHANUM_12:
				$o->code = $buffer->readOpaqueFixed(12, true);

				if ($includeIssuer)
					$o->issuer = Account::fromXDRBuffer($buffer);
			break;
	}

	return $o;
}

public function hasValue() { return true; }

public function __tostring() {
	if ($this->type == self::TYPE_NATIVE)
		return "(Asset) XLM";
	else
		return "(Asset) " . $this->code . " (Issuer: " . ($this->issuer && $this->issuer->getKeypair() ? $this->issuer->getKeypair()->getPublicKey() : "null") .")";
}

}


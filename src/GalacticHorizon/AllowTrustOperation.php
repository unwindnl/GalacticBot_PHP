<?php

namespace GalacticHorizon;

class AllowTrustOperation extends Operation {

private $asset;
private $authorize;

public function getType() { return static::TYPE_ALLOW_TRUST; }

public function setAsset(Asset $asset) {
	$this->asset = $asset;
}	

public function setAuthorized(bool $authorize) {
	$this->authorize = $authorize;
}	

protected function extendXDRBuffer(XDRBuffer &$buffer) {
	$this->asset->getIssuer()->toXDRBuffer($buffer);
	$this->asset->toXDRBuffer($buffer, false);
	$buffer->writeUnsignedInteger($this->authorize ? 1 : 0);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();

	$issuer = Account::fromXDRBuffer($buffer);

	$o->asset = Asset::fromXDRBuffer($buffer, false);
	$o->asset->setIssuer($issuer);

	$o->authorize = $buffer->readUnsignedInteger() == 1;

	return $o;
}

public function toString($depth) {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = $depth . "- Asset = " . $this->asset;
	$str[] = $depth . "- Authorize = " . ($this->authorize ? "true" : "false");
		
	return implode("\n", $str);
}

}


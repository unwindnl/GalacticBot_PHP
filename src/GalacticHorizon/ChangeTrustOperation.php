<?php

namespace GalacticHorizon;

class ChangeTrustOperation extends Operation {

private $asset;
private $limit;

public function getType() { return static::TYPE_CHANGE_TRUST; }

public function setAsset(Asset $asset) {
	$this->asset = $asset;
}	

public function setLimit(Amount $limit) {
	$this->limit = $limit;
}	

protected function extendXDRBuffer(XDRBuffer &$buffer) {
	$this->asset->toXDRBuffer($buffer);
	$this->limit->toXDRBuffer($buffer);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$o = new self();

	$o->asset = Asset::fromXDRBuffer($buffer);
	$o->limit = Amount::fromXDRBuffer($buffer);

	return $o;
}

public function toString($depth) {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = $depth . "- Asset = " . $this->asset;
	$str[] = $depth . "- Limit = " . $this->limit;
		
	return implode("\n", $str);
}

}


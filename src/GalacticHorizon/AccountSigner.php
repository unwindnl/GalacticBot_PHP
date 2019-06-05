<?php

namespace GalacticHorizon;

class AccountSigner {

private $weight;
private $signer;

public static function fromJSON(object $json) {
	$o = new self();

	$o->weight = $json->weight;

	$o->signer = Account::createFromPublicKey($json->key);
	
	return $o;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = " - Weight = " . $this->weight;
	$str[] = " - Signer = ";
	$str[] = IncreaseDepth($this->signer);
	return implode("\n", $str);
}

}



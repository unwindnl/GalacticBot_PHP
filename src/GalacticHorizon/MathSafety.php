<?php

namespace GalacticHorizon;

class MathSafety {

public static function require64Bit() {
if (PHP_INT_SIZE < 8) throw new \ErrorException('A 64-bit operating system is required');
}

}


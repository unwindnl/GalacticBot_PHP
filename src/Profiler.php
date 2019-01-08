<?php

namespace GalacticBot;

class Profiler {

	static $times = [];

	static function start($name)
	{
		self::$times[$name] = microtime(true);
	}

	static function stop($name)
	{
		 $delta = microtime(true) - self::$times[$name];

		 $delta = number_format($delta, 8);

		 echo " -- Time $name = $delta\n";
	}

}


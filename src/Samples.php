<?php

namespace GalacticBot;

class Samples
{

	private $maxLength = null;

	private $samples = Array();

	function __construct($maxLength, $samples = Array())
	{
		$this->maxLength = $maxLength;
		$this->samples = $samples;
	}

	function add($value) {
		$this->samples[] = $value;

		$this->samples = array_slice($this->samples, -$this->maxLength);
	}

	function getMaxLength()
	{
		return $this->maxLength;
	}

	function getLength()
	{
		return count($this->samples);
	}

	function setMaxLength($maxLength)
	{
		$this->maxLength = $maxLength;
	}

	function getArray()
	{
		return $this->samples;
	}

	function clear()
	{
		$this->samples = [];
	}

	function getIsBufferFull()
	{
		return count($this->samples) == $this->maxLength;
	}

	function getAverage() {
		if (!count($this->samples))
			return false;

		$sum = 0;
	
		foreach($this->samples AS $s)
			$sum += $s;

		return $sum / count($this->samples);
	}

	/**
	* Source: https://github.com/markrogoyski/math-php/blob/master/src/Statistics/Average.php
	*
	* Exponential moving average (EMA)
	*
	* The start of the EPA is seeded with the first data point.
	* Then each day after that:
	*  EMAtoday = α⋅xtoday + (1-α)EMAyesterday
	*
	*   where
	*    α: coefficient that represents the degree of weighting decrease, a constant smoothing factor between 0 and 1.
	*
	* @return array of exponential moving averages
	*/
	function getExponentialMovingAverage() {
		$n = count($this->samples)/2;
		$m   = count($this->samples);
		$α   = 2 / ($n + 1);
		$EMA = [];

		// Start off by seeding with the first data point
		$EMA[] = $this->samples[0];
	
		// Each day after: EMAtoday = α⋅xtoday + (1-α)EMAyesterday
		for ($i = 1; $i < $m; $i++) {
			$EMA[] = ($α * $this->samples[$i]) + ((1 - $α) * $EMA[$i - 1]);
		}
		return array_pop($EMA);
	}
	
}


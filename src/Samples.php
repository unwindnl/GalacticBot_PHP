<?php

namespace GalacticBot;

/*
* Sample buffer class, holds an array of collected samples (prices) and can do calculations on the values.
*/
class Samples
{

	private $maxLength = null;

	private $samples = Array();

	/*
	* @param int $maxLength maximum number of samples you want to keep
	* @param Array of samples
	*/
	function __construct($maxLength, $samples = Array())
	{
		$this->maxLength = $maxLength;
		$this->samples = $samples;
	}

	/*
	* Adds an sample to the buffer and caps the buffer if it's getting too long
	*/
	function add($value) {
		$this->samples[] = $value;

		$this->samples = array_slice($this->samples, -$this->maxLength);
	}

	/*
	* Returns the maximum length of this buffer
	*/
	function getMaxLength()
	{
		return $this->maxLength;
	}

	/*
	* Returns the current length of this buffer
	*/
	function getLength()
	{
		return count($this->samples);
	}

	/*
	* Changes the maximum length of this buffer
	*/
	function setMaxLength($maxLength)
	{
		$this->maxLength = $maxLength;
	}

	/*
	* Returns the sample buffer array
	* @return Array
	*/
	function getArray()
	{
		return $this->samples;
	}

	/*
	* Clears the sample buffer
	*/
	function clear()
	{
		$this->samples = [];
	}

	/*
	* Checks to see if the buffer is full or not
	* @return bool
	*/
	function getIsBufferFull()
	{
		return count($this->samples) == $this->maxLength;
	}

	/*
	* Returns the average value of all values in the buffer
	* @return float or false when no data is present
	*/
	function getAverage() {
		if (!count($this->samples))
			return false;

		$sum = 0;
	
		foreach($this->samples AS $s)
			$sum += $s;

		return $sum / count($this->samples);
	}

	/**
	* Exponential moving average (EMA) - Source: https://github.com/markrogoyski/math-php/blob/master/src/Statistics/Average.php
	*
	* The start of the EPA is seeded with the first data point.
	* Then each day after that:
	*  EMAtoday = α⋅xtoday + (1-α)EMAyesterday
	*
	*   where
	*    α: coefficient that represents the degree of weighting decrease, a constant smoothing factor between 0 and 1.
	*
	* @return Array of exponential moving averages
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


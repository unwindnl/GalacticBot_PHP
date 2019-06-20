<?php

namespace GalacticBot;

/*
* Time class used for the Bot processing, currently the time is in units of minutes. So one timeframe for the bot is one minute.
*/
class Time
{

	private $dateTime = null;

	function __construct(Time $other = null)
	{
		if ($other)
			$this->dateTime = clone $other->dateTime;
	}

	function setDate(\DateTime $dateTime, $includeSeconds = false)
	{
		$this->dateTime = clone $dateTime;
		$this->dateTime->setTime($this->dateTime->format("H"), $this->dateTime->format("i"), $includeSeconds ? $this->dateTime->format("s") : 0);
	}

	function add($count, $units = "minutes") {
		if (!$count)
			return;

		$this->dateTime->modify("+{$count} {$units}");
	}

	function subtract($count, $units = "minutes") {
		if (!$count)
			return;

		$this->dateTime->modify("-{$count} {$units}");
	}

	function subtractWeeks($weeks)
	{
		if (!$weeks)
			return;

		$this->dateTime->modify("-{$weeks} weeks");
	}

	function toString()
	{
		return $this->dateTime->format("Y-m-d H:i:s");
	}

	function getTimestamp()
	{
		return $this->dateTime->format("U");
	}

	function isBefore(Time $other)
	{
		return $this->dateTime < $other->dateTime;
	}

	function isEqual(Time $other)
	{
		return $this->dateTime == $other->dateTime;
	}

	function isAfter(Time $other)
	{
		return $this->dateTime > $other->dateTime;
	}

	function isNow() {
		$now = self::now();
		return $this->dateTime->format("Y-m-d H:i:00") == $now->dateTime->format("Y-m-d H:i:00");
	}

	function getDateTime() {
		return $this->dateTime;
	}

	function getAgeInMinutes(Time $now = null) {
		return $this->getAgeInSeconds($now) / 60;
	}

	function getAgeInSeconds(Time $now = null) {
		if (!$now)
			$now = self::now();

		return (float)$now->dateTime->format("U") - (float)$this->dateTime->format("U");
	}

	function getSeconds() {
		return (float)$this->dateTime->format("s");
	}

	static function getDurationDescription(Time $from, Time $to) {
	//	var_dump("from = ", $from->toString());
	//	var_dump("to = ", $to->toString());

		$minutes = $from->getAgeInMinutes($to);
		$hours = floor($minutes / (60));
		$minutes -= $hours * 60;
		$days = floor($hours / 24);
		$hours -= $days * 24;

		if ($days > 0 && $days <= 1) {
			if ($days == 1)
				$days = "1 day";
			else
				$days = $days . " days";

			if ($hours == 1)
				return $days . " and 1 hour";
			else
				return $days . " and {$hours} hours";
		} else if ($hours > 0 && $hours <= 24) {
			if ($hours == 1)
				$hours = "1 hour";
			else
				$hours = $hours . " hours";

			if ($minutes == 1)
				return $hours . " and 1 minute";
			else
				return $hours . " and {$minutes} minutes";
		} else if ($minutes == 0)
			return null;
		else if ($minutes == 1)
			return "1 minute";
		else if ($minutes <= 59)
			return $minutes . " minutes";
		else 

		return $days == 1 ? "1 day" : $days . " days";
	}
	
	static function fromString($string)
	{
		$o = new self();
		$o->dateTime = new \DateTime($string, new \DateTimeZone("UTC"));
		return $o;
	}

	static function fromDateTime(\DateTime $date)
	{
		$o = new self();
		$o->setDate($date);
		return $o;
	}

	static function now($includeSeconds = false)
	{
		$o = new self();
		$o->setDate(new \DateTime(null, new \DateTimeZone("UTC")), $includeSeconds);
		return $o;
	}

	static function fromTimestamp($stamp)
	{
		$d = new \DateTime(null, new \DateTimeZone("UTC"));
		$d->setTimestamp($stamp);

		$o = new self();
		$o->setDate($d);
		return $o;
	}

	static function getRange(Time $from, Time $to)
	{
		$date = new self($from);
		$list = [];

		while(!$date->isAfter($to))
		{
			$list[] = new self($date);

			$date->add(1);
		}

		return $list;
	}

}


<?php

namespace GalacticBot;

class Time
{

	private $dateTime = null;

	function __construct(Time $other = null)
	{
		if ($other)
			$this->dateTime = clone $other->dateTime;
	}

	function setDate(\DateTime $dateTime)
	{
		$this->dateTime = clone $dateTime;
		$this->dateTime->setTime($this->dateTime->format("H"), $this->dateTime->format("i"), 0);
	}

	function add($units)
	{
		$this->dateTime->modify("+{$units} minutes");
	}

	function subtract($units)
	{
		$this->dateTime->modify("-{$units} minutes");
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

	function getAgeInMinutes(Time $now = null)
	{
		if (!$now)
			$now = self::now();

		$seconds = $now->dateTime->format("U") - $this->dateTime->format("U");

		return $seconds / 60;
	}

	static function fromString($string)
	{
		$o = new self();
		$o->dateTime = new \DateTime($string, null);
		return $o;
	}

	static function fromDateTime(\DateTime $date)
	{
		$o = new self();
		$o->setDate($date);
		return $o;
	}

	static function now()
	{
		$o = new self();
		$o->setDate(new \DateTime(null, null));
		return $o;
	}

	static function fromTimestamp($stamp)
	{
		$d = new \DateTime();
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


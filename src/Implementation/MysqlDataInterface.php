<?php

namespace GalacticBot\Implementation;

class MysqlDataInterface implements \GalacticBot\DataInterface
{

	private $bot = null;

	private $data = [];
	private $changedData = [];

	private $sampleBuffers = [];

	function __construct($server, $user, $password, $database) {
		$this->mysqli = new \mysqli($server, $user, $password, $database);

		if ($this->mysqli->connect_errno) {
			throw Exception("Mysql error #{$this->mysqli->connect_errno} {$this->mysqli->connect_error}");
		}
	}

	function isSetting($name)
	{
		$value = $this->get("setting_" . $name);

		return $value !== null;
	}

	function getSetting($name, $defaultValue = null)
	{
		$value = $this->get("setting_" . $name);

		if ($value === null)
		{
			$this->setSetting($name, $defaultValue);

			$value = $defaultValue;
		}
		
		return $value;
	}

	function setSetting($name, $value)
	{
		$this->directSet("setting_" . $name, $value);
	}

	private function excludeLastTradeFromOffers(Array $offers)
	{
		$lastTrade = $this->getLastTrade();

		$lastTradePrice = 0;
		$lastTradeAmount = 0;

		if ($lastTrade)
		{
			$lastTradePrice = number_format(1/$lastTrade->getPrice(), 7);
			$lastTradeAmount = number_format($lastTrade->getSellAmount(), 7);
		}
		
		foreach($offers AS $bid)
		{
			$price = number_format($bid["price"], 7);
			$amount = $bid["amount"];

			if ($price == $lastTradePrice)
			{
				$amount -= (float)$lastTradeAmount;
			}

			if ($amount > 0)
			{
				// Still have left when our offer is excluded
				// We can assume this is valid offer from someone else
				return $bid["price"];
			}
		}

		return null;
	}

	function getAssetValueForTime(\GalacticBot\Time $time)
	{
		$baseAsset = $this->bot->getSettings()->getBaseAsset();
		$counterAsset = $this->bot->getSettings()->getCounterAsset();

		$sample = $this->getT($time, "value");

		if ($sample !== null)
			return $sample;

		if ($time->isNow()) {
			$orderbook = \GalacticBot\StellarAPI::getPublicOrderBook($this->bot, $baseAsset, $counterAsset, 10);

			$samples = new \GalacticBot\Samples(2);

			if ($orderbook && isset($orderbook["bids"]))
			{
				$price = $this->excludeLastTradeFromOffers($orderbook["bids"]);

				if ($price !== null)
					$samples->add($price);
			}

			if ($orderbook && isset($orderbook["asks"]))
			{
				$price = $this->excludeLastTradeFromOffers($orderbook["asks"]);

				if ($price !== null)
					$samples->add($price);
			}

		//	var_dump($samples);
		//	exit();

			if ($samples->getLength() > 0) {
				$price = (float)number_format($samples->getAverage(), 7, '.', '');

				$this->setT($time, "value", $price);

				return $price;
			}

			return null;
		}

		// Fetch older data which we didn't have collected
		$start = new \GalacticBot\Time($time);

		$end = new \GalacticBot\Time($time);
		$end->add(120);

		$now = \GalacticBot\Time::now();

		if ($end->isAfter($now))
		{
			$end = $now;
			$end->subtract(1);
		}

		$list = \GalacticBot\StellarAPI::getPublicTradeAggregations($this->bot, $baseAsset, $counterAsset, $start, $end, \GalacticBot\StellarAPI::INTERVAL_MINUTE);

		if ($list)
		{
			$lastDate = new \GalacticBot\Time($start);

			foreach($list AS $i => $record)
			{
				$date = \GalacticBot\Time::fromTimestamp($record->getTimestamp() / 1000);

				$isLastRecord = $i == count($list);

				$range = \GalacticBot\Time::getRange($lastDate, $isLastRecord ? $end : $date);

				foreach($range AS $rangeDate) {
					$price = (float)number_format(($record->getLow()+$record->getHigh())/2, 7, '.', '');
					$this->setT($rangeDate, "value", $price);
				}

				$lastDate = $date;
			}
		}

		$price = $this->getT($time, "value");

		if (!$price) {
			// This happens when we're requesting the price of an asset
			// without any transactions in the requested timefrime
			// We'll have to get the last known price as that is still valid
			$sql = "
				SELECT	value
				FROM	BotData
				WHERE	botID = " . $this->escapeSQLValue($this->bot->getSettings()->getID()) . "
					AND	name = 'value'
					AND	date <= " . $this->escapeSQLValue($time->getDateTime()->format("Y-m-d H:i:s")) . "
					AND	date IS NOT NULL
					AND	date <> '0000-00-00 00:00:00'
				ORDER BY
						date DESC
				LIMIT	1
			";

			if (!$result = $this->mysqli->query($sql))
			{
				throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");
			}

			$row = $result->fetch_assoc();

			if ($row) {
				$time = clone $start;

				$price = $row["value"];

				while($time->isBefore($end))
				{
					$this->setT($time, "value", $price);
					$time->add(1);
				}
			}
		}

		return $price ? (float)number_format($price, 7, '.', '') : null;
	}
	
	function clearAllExceptSampleDataAndSettings()
	{
		// Make sure to clean our cache
		$this->save();

		$sql = "
			DELETE FROM
					BotData
			WHERE	botID = '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
				AND	name <> 'value'
				AND	name NOT LIKE 'setting_%'
		";
		
		if (!$result = $this->mysqli->query($sql))
		{
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");
		}

		$this->sampleBuffers = [];

		// Reload (empty) data from database
		$this->saveAndReload();
	}

	function getLastTrade()
	{
		$sql = "
			SELECT	*
			FROM	BotTrade
			WHERE	state NOT IN ('" . \GalacticBot\Trade::STATE_CANCELLED . "', '" . \GalacticBot\Trade::STATE_REPLACED . "')
				AND	botID = '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
			ORDER BY
					createdAt DESC
			LIMIT	1
		";
	
		if (!$result = $this->mysqli->query($sql))
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");

		$row = $result->fetch_assoc();

		if ($row && count($row)) {
			$trade = new \GalacticBot\Trade();
			$trade->setData($row);
			return $trade;
		}
	}

	function getLastCompletedTrade()
	{
		$last = $this->getLastTrade();

		if ($last && $last->getIsFilledCompletely())
			return $last;

		$sql = "
			SELECT	*
			FROM	BotTrade
			WHERE	state = '" . \GalacticBot\Trade::STATE_FILLED . "'
				AND	botID = '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
			ORDER BY
					createdAt DESC
			LIMIT	1
		";
	
		if (!$result = $this->mysqli->query($sql))
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");

		$row = $result->fetch_assoc();

		if ($row && count($row)) {
			$trade = new \GalacticBot\Trade();
			$trade->setData($row);
			return $trade;
		}

		return null;
	}

	function getTrades($limit, $orderDesc)
	{
		$list = [];

		$limit = (int)$limit;
		$order = "createdAt " . ($orderDesc ? "DESC" : "ASC");

		$sql = "
			SELECT	*
			FROM	BotTrade
			WHERE	botID = '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
			ORDER BY
					$order
			LIMIT
					$limit
		";

		if (!$result = $this->mysqli->query($sql))
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");

		while(($row = $result->fetch_assoc())) {
			$trade = new \GalacticBot\Trade();
			$trade->setData($row);

			$list[] = $trade;
		}

		return $list;
	}
	
	function getTradeByID($ID)
	{
		if (!$ID)
			return null;

		$sql = "
			SELECT	*
			FROM	BotTrade
			WHERE	ID = " . $this->escapeSQLValue($ID) . "
				AND	botID = '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
		";
	
		if (!$result = $this->mysqli->query($sql))
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");

		$row = $result->fetch_assoc();

		if ($row && count($row)) {
			$trade = new \GalacticBot\Trade();
			$trade->setData($row);
			return $trade;
		}

		return null;
	}

	function saveTrade(\GalacticBot\Trade $trade)
	{
		$set = [];
		$data = $trade->getData();
		
		foreach($data AS $k => $v)
		{
			$set[] = "$k = " . $this->escapeSQLValue($v);
		}

		$set[] = "updatedAt = UTC_TIMESTAMP()";

		$set = implode(",\n", $set);

		$sql = "
			UPDATE	BotTrade
			SET		$set
			WHERE	ID = " . $this->escapeSQLValue($trade->getID()) . "
		";
	
		if (!$result = $this->mysqli->query($sql))
		{
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");
		}
	}

	function addTrade(\GalacticBot\Trade $trade)
	{
		$names = [];
		$values = [];

		$names[] = "botID";
		$values[] = $this->escapeSQLValue($this->bot->getSettings()->getID());

		$data = $trade->getData();
		
		foreach($data AS $k => $v)
		{
			$names[] = $k;
			$values[] = $this->escapeSQLValue($v);
		}

		$names = implode(",\n", $names);
		$values = implode(",\n", $values);

		$sql = "
			INSERT INTO
				BotTrade
			(
				$names
			)
			VALUES
			(
				$values
			)
		";
	
		if (!$result = $this->mysqli->query($sql))
		{
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");
		}

		$trade->setID($this->mysqli->insert_id);
	}

	function escapeSQLValue($value)
	{
		if ($value === NULL)
			return "NULL";

		return "'" . $this->mysqli->real_escape_string($value) . "'";
	}

	function get($name, $defaultValue = null)
	{
		return isset($this->data[$name]) ? $this->data[$name] : $defaultValue;
	}

	function set($name, $value)
	{
		if (!isset($this->data[$name]) || $this->data[$name] != $value)
		{
			$this->data[$name] = $value;
			$this->changedData[$name] = $name;
		}
	}

	function directSet($name, $value)
	{
		$this->data[$name] = $value;

		$sql = "
			REPLACE INTO BotData
			(
				botID,
				name,
				date,
				value
			)
			VALUES
			(
				'" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "',
				'" . $this->mysqli->real_escape_string($name) . "',
				'0000-00-00 00:00:00',
				'" . $this->mysqli->real_escape_string($value) . "'
			)
		";

		$this->mysqli->query($sql);
	}

	function getT(\GalacticBot\Time $time, $name)
	{
		$sql = "
			SELECT	value
			FROM	BotData
			WHERE
					botID	= '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
				AND	name	= '" . $this->mysqli->real_escape_string($name) . "'
				AND	date	= '" . $this->mysqli->real_escape_string($time->toString()) . "'
		";
	
		if (!$result = $this->mysqli->query($sql))
		{
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");
		}

		$row = $result->fetch_assoc();

		if ($row && count($row))
			return $row["value"];

		return null;
	}

	function setT(\GalacticBot\Time $time, $name, $value)
	{
		$sql = "
			REPLACE INTO BotData
			(
				botID,
				name,
				date,
				value
			)
			VALUES
			(
				'" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "',
				'" . $this->mysqli->real_escape_string($name) . "',
				'" . $this->mysqli->real_escape_string($time->toString()) . "',
				'" . $this->mysqli->real_escape_string($value) . "'
			)
		";

		$this->mysqli->query($sql);
	}

	function getS($name, $maxLength)
	{
		if (!isset($this->sampleBuffers[$name]))
			$this->sampleBuffers[$name] = new \GalacticBot\Samples($maxLength);
		else
			$this->sampleBuffers[$name]->setMaxLength($maxLength);

		return $this->sampleBuffers[$name];
	}

	function setS($name, \GalacticBot\Samples $value)
	{
	}

	function loadForBot(\GalacticBot\Bot $bot, $force = false)
	{
		if ($this->bot && $this->bot->getSettings()->getID() == $bot->getSettings()->getID() && !$force)
			return;

		$this->data = [];
		$this->changedData = [];
		$this->sampleBuffers = [];
		$this->bot = $bot;

		$sql = "
			SELECT	name,
					value
			FROM	BotData
			WHERE	botID = '" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "'
				AND	date = '0000-00-00 00:00:00'
		";
		
		if (!$result = $this->mysqli->query($sql))
		{
			throw new \Exception("Mysql error #{$this->mysqli->errno}: {$this->mysqli->error}.");
		}

		while($row = $result->fetch_assoc())
		{
			if (preg_match("/^SB_(.+?)$/", $row["name"], $matches))
			{
				$data = json_decode($row["value"]);

				if (is_object($data))
					$this->sampleBuffers[$matches[1]] = new \GalacticBot\Samples($data->maxLength, $data->samples);
			}
			else
			{
				$this->data[$row["name"]] = $row["value"];
			}
		}
		
		$this->bot->getSettings()->loadFromDataInterface();
	}

	function saveAndReload()
	{
		$this->save();
		
		$this->loadForBot($this->bot, true);
	}

	function save()
	{
		foreach($this->changedData AS $k => $v)
		{
			$v = $this->data[$k];

			$sql = "
				REPLACE INTO BotData
				(
					botID,
					name,
					date,
					value
				)
				VALUES
				(
					'" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "',
					'" . $this->mysqli->real_escape_string($k) . "',
					'0000-00-00 00:00:00',
					'" . $this->mysqli->real_escape_string($v) . "'
				)
			";

			//echo " --- updating '$k' to '$v'\n";
			$this->mysqli->query($sql);
		}

		foreach($this->sampleBuffers AS $k => $v)
		{
			$jv = (object)[];
			$jv->maxLength = $v->getMaxLength();
			$jv->samples = $v->getArray();

			$sql = "
				REPLACE INTO BotData
				(
					botID,
					name,
					date,
					value
				)
				VALUES
				(
					'" . $this->mysqli->real_escape_string($this->bot->getSettings()->getID()) . "',
					'SB_" . $this->mysqli->real_escape_string($k) . "',
					'0000-00-00 00:00:00',
					'" . $this->mysqli->real_escape_string(json_encode($jv)) . "'
				)
			";

			$this->mysqli->query($sql);
		}
		
		$this->changedData = [];
	}

	function logVerbose($what) {
		echo "[VERBOSE] " . date("Y-m-d H:i:s") . " [Bot #" . $this->bot->getSettings()->getID() . " - " . $this->bot->getSettings()->getName() . "]: {$what}\n";
	}

	function logWarning($what) {
		echo "[WARNING] " . date("Y-m-d H:i:s") . " [Bot #" . $this->bot->getSettings()->getID() . " - " . $this->bot->getSettings()->getName() . "]: {$what}\n";
	}

	function logError($what) {
		echo "[ERROR]   " . date("Y-m-d H:i:s") . " [Bot #" . $this->bot->getSettings()->getID() . " - " . $this->bot->getSettings()->getName() . "]: {$what}\n";
	}

}


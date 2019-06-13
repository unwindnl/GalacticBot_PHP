<?php

namespace GalacticBot;

include_once "GalacticHorizon/lib.php";

/*
* Abstract bot class.
*
* This is the class you will need to implement to create your own Bot algorithm. See the Implemention/ folder of this library for an example EMA bot.
*/
abstract class Bot
{

	/*
	* Simulated bots do not trade.
	*/
	const SETTING_TYPE_SIMULATION				= "SIMULATION";

	/*
	* Live bots trade on both the test net as on the public net, you can choose on which in the settings class instance you provide to construct this class
	*/
	const SETTING_TYPE_LIVE						= "LIVE";

	const STATE_NONE							= "";
	const STATE_RUNNING							= "RUNNING";
	const STATE_PAUSED							= "PAUSED";
	const STATE_STOPPED							= "STOPPED";

	/*
	* Default trade state, you will have to define your own trade states in your bot implementation
	*/
	const TRADE_STATE_NONE						= "";

	protected $settingDefaults = [];

	protected $settings;
	protected $data;

	/**
	* Is false when the bot is paused (but will still process data)
	*/
	private $shouldTrade = false;

	/**
	* Time we're processing, see work() method
	*/
	private $currentTime = null;

	/**
	* Information about the bot's account
	*/
	private $accountInfo = null;

	/**
	* Time at which the information about the bot's account was last updated
	*/
	private $lastAccountInfoUpdate = null;

	/**
	* Constructs a new bot instance.
	*
	* @param Settings $settings An instance of the Settings class, please make sure to give each bot instance it's own settings class instance and not a copy/the same
	*/
	public function __construct(
		Settings $settings
    )
	{
		$this->settings = $settings;
		$this->data = $settings->getDataInterface();
		$this->data->loadForBot($this);

		$this->lastProcessingTime = null;

		if ($this->data->get("lastProcessingTime"))
			$this->lastProcessingTime = Time::fromString($this->data->get("lastProcessingTime"));
		else
		{
			$this->lastProcessingTime = Time::now();
			$this->lastProcessingTime->subtract(1);
		}

		$this->initialize();
	}

	/**
	* Initialize your bot here.
	*
	* For example the EMABot loads all sample arrays from the database to a local SampleBuffer instance.
	*
	* @return void
	*/
	abstract protected function initialize();

	/**
	* Return a description for any custom tradeStates you define.
	*
	* @return string or null
	*/
	abstract public function getTradeStateLabel($forState);

	/**
	* This is where the real bot processing happens. This is where you implement your trading algorithm.
	*
	* @return void
	*/
	abstract protected function process(\GalacticBot\Time $time, $sample);

	public function getSettingDefaults()
	{
		return $this->settingDefaults;
	}

	/**
	* Returns last fully processed date & time.
	*
	* @return DateTime
	*/
	public function getLastProcessingTime()
	{
		return new \DateTime($this->data->get("lastProcessingTime"));
	}

	/**
	* Returns the settings instance used for this bot instance.
	*
	* @return Settings
	*/
	public function getSettings()
	{
		return $this->settings;
	}

	/**
	* Returns the data(base) interface instance used for this bot instance.
	*
	* @return Settings
	*/
	public function getDataInterface()
	{
		return $this->data;
	}

	/**
	* Sets the bot state to 'should be running'. For this to take affect the bot should be running (see the demo for a worker script).
	*
	* @return void
	*/
	public function start()
	{
		// reset first, will start automaticly after a full reset
		$this->data->directSet("resetNeeded", 1);
	}

	/**
	* Sets the bot state to 'should pause'. For this to take affect the bot should be running (see the demo for a worker script).
	*
	* @return void
	*/
	public function pause()
	{
		$this->data->directSet("state", self::STATE_PAUSED);
	}

	/**
	* Sets the bot state to 'should stop'.
	*
	* @return void
	*/
	public function stop()
	{
		$this->data->directSet("state", self::STATE_STOPPED);
	}

	/**
	* Enable trading; use this to enable calling the buy, cancel and sell methods from outside the work method
	*
	* @return void
	*/
	public function enableTrades()
	{
		$this->shouldTrade = true;
	}

	/**
	* Sets the bot state to 'should reset'. For this to take affect the bot should be running (see the demo for a worker script). See the performFullReset method for more information.
	*
	* @return void
	*/
	public function simulationReset()
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$this->data->directSet("resetNeeded", 1);
		}
	}

	/**
	* Returns the current state the Bot is in. Value must be one of defined STATE_ constants.
	*
	* @return string
	*/
	public function getState() { return $this->data->get("state"); }

	/**
	* Returns the current state as an array with "state" and "label" (which is a description of the state).
	*
	* @return Array
	*/
	function getStateInfo() {
		$state = $this->getState();
		$label = "Unknown state ($state)";

		switch($state)
		{
			case "":
			case self::STATE_NONE:								$label = "Not started yet"; break;

			case self::STATE_RUNNING:							$label = "Running"; break;
			case self::STATE_PAUSED:							$label = "Paused"; break;
			case self::STATE_STOPPED:							$label = "Stopped"; break;
		}

		if ($this->data->get("resetNeeded") == 1)
			$label = "Waiting for reset to complete";

		return Array(
			"state" => $state,
			"label" => $label
		);
	}

	function getStateDescription()
	{
		return $this->getStateInfo()["label"];
	}

	/**
	* Returns the current trade state the Bot is in. Value must be one of defined TRADE_STATE_ constants in this class or from the implemented Bot instance.
	*
	* @return string
	*/
	public function getTradeState() { return $this->data->get("tradeState"); }

	/**
	* Returns the current trade state as an array with "state" and "label" (which is a description of the trade state).
	*
	* @return Array
	*/
	function getTradeStateInfo() {
		$state = $this->getTradeState();
		$label = "Unknown state ($state)";

		$label = $this->getTradeStateLabel($state);

		return Array(
			"state" => $state,
			"label" => $label
		);
	}

	function getTradeStateDescription()
	{
		return $this->getTradeStateInfo()["label"];
	}

	/**
	* Determines if the Bot should run.
	*
	* @return bool
	*/
	public function getShouldNotProcess()
	{
		$state = $this->data->get("state");
		return !$state || $state == self::STATE_NONE || $state == self::STATE_STOPPED;
	}

	/**
	* Resets the bot data for a simulated bot.
	*
	* This method will ask the data interface instance to erase all data from the database for this bot, except for collected sample data (historical prices - which are needed for a simulation to be run).
	*
	* @return void
	*/
	private function performFullReset()
	{
		$this->getDataInterface()->clearAllExceptSampleDataAndSettings();
		
		$this->data->logVerbose("Performing full reset.");

		// Go back to the last date with buffered samples until x days back
		$lastBufferDate = Time::now();
		$days = 0;
		$value = null;
		
		if ($this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
			$maxDaysBack = 1;
		else
			$maxDaysBack = 7;

		do {
			$days++;
			$lastBufferDate->subtract(1, "day");

			$value = $this->getDataInterface()->getAssetValueForTime($lastBufferDate);
		} while ($value !== null && $days < $maxDaysBack); 

		if ($value === null) {
			$lastBufferDate = Time::now();
		}

		$this->lastProcessingTime = new Time($lastBufferDate);
		$this->data->set("lastProcessingTime", $lastBufferDate->toString());
		$this->data->set("firstProcessingTime", null);
		$this->data->directSet("firstProcessingTime", null);

		$this->currentTime = new Time($lastBufferDate);

		$this->data->set("state", self::STATE_RUNNING);
		$this->data->set("tradeState", "");
		$this->data->save();
		
		$this->data->directSet("state", self::STATE_RUNNING);
		$this->data->directSet("tradeState", "");

		$this->onFullReset();
		
		$this->data->save();
	}

	protected function onFullReset() {}

	public function preLaunch() {
		date_default_timezone_set("UTC");

		$timezone = date_default_timezone_get();

		if ($timezone != "UTC")
			exit("Cannot set timezone to UTC");
	
		if ($this->settings->getIsOnTestNet())
			\GalacticHorizon\Client::createTestNetClient();
		else
			\GalacticHorizon\Client::createPublicClient();
	}

	public function stream() {
		$this->preLaunch();

		$account = \GalacticHorizon\Account::createFromPublicKey($this->getSettings()->getAccountPublicKey());
			
		if ($account->fetch()) {
			$cursor = $this->data->get("last-trades-update-cursor", "now");
		
			$bot = $this;
			
			$account->getTradesStreaming(
				$cursor,
				function($ID, $trade) use ($bot) {
					$this->data->set("last-trades-update-cursor", $ID);
					$this->data->save();

					$lastTrade = $this->data->getLastTrade();

					if ($lastTrade) {
						$lastTradeDate = clone $lastTrade->getCreatedAt();
						$lastTradeDate->modify("-1 minute");

						if ($trade->getLedgerCloseTime() >= $lastTradeDate) {
							$isBuy = $lastTrade->getType() == \GalacticBot\Trade::TYPE_BUY;
							$isSell = !$isBuy;

							$lastTradeBuyingAssetCode = $isBuy ? $this->settings->getCounterAsset()->getCode() : $this->settings->getBaseAsset()->getCode();

							if ($trade->getBaseIsSeller())
							{
								if ($trade->getBaseAccount() == $this->getSettings()->getAccountPublicKey())
								{
									$buyingAssetCode = $trade->getCounterAsset()->getCode();
									$buyingAssetType = $trade->getCounterAsset()->getType();
								}
								else
								{
									$buyingAssetCode = $trade->getBaseAsset()->getCode();
									$buyingAssetType = $trade->getBaseAsset()->getType();
								}
							}
							else
							{
								if ($trade->getBaseAccount() == $this->getSettings()->getAccountPublicKey())
								{
									$buyingAssetCode = $trade->getBaseAsset()->getCode();
									$buyingAssetType = $trade->getBaseAsset()->getType();
								}
								else
								{
									$buyingAssetCode = $trade->getCounterAsset()->getCode();
									$buyingAssetType = $trade->getCounterAsset()->getType();
								}
							}

							if ($buyingAssetCode == $lastTradeBuyingAssetCode) {
								$lastTrade->addCompletedHorizonTradeForBot($trade, $bot);
							}
						}
					}
				}
			);
		}
	}

	protected $profilingEnabled = false;
	protected $profiling = [];
	protected $profileLastID = null;
	protected $profileCount = 0;

	function profileStart()
	{
		if (!$this->profilingEnabled)
			return;

		$this->profileLastID = null;
	}

	function profile($what, $file, $line)
	{
		if (!$this->profilingEnabled)
			return;

		$ID = $file . $line;

		$tick = microtime(true);

		if ($this->profileLastID)
			$this->profiling[$this->profileLastID]["stop"] = $tick;

		if (!isset($this->profiling[$ID])) {
			$this->profiling[$ID] = Array(
				"start" => $tick,
				"what" => $what
			);
		}
		else
		{
			$this->profiling[$ID]["start"] = $tick;
		}
		
		$this->profileLastID = $ID;
	}

	function profileEndTask()
	{
		if (!$this->profilingEnabled)
			return;

		$ID = $file . $line;

		$tick = microtime(true);

		if ($this->profileLastID)
			$this->profiling[$this->profileLastID]["stop"] = $tick;

		$this->profileLastID = null;
	}

	function profileStop()
	{
		if (!$this->profilingEnabled)
			return;

		$this->profileEndTask();

		foreach($this->profiling AS $ID => $info) {
			$info["duration"] = $info["stop"] - $info["start"];
			$info["total"] += $info["duration"];

			$this->profiling[$ID] = $info;
		}

		if ($this->profileCount++ >= 20)
		{
			uasort(
				$this->profiling,
				function($a, $b)
				{
					if ($a["total"] == $b["total"])
						return 0;

					return $a["total"] > $b["total"] ? -1 : 1;
				}
			);

			echo "[PROFILING]\n";

			foreach($this->profiling AS $info)
			{
				$intValue = (int)$info["total"];
				$decimals = $info["total"] - $intValue;

				$time = sprintf("%04d.%05d", $intValue, abs(round($decimals*100000)));

				for($i=0; $i<strlen($time); $i++)
				{
					if ($time[$i] == '0' && $time[$i+1] != '.')
						$time[$i] = ' ';
					else if ($time[$i] == '.')
						$i = strlen($time);
				}

				$what = $info["what"];

				$echt = $info["total"];

				echo "\t[$time] $echt $what\n";
			}

			echo "\n\n";

			exit();
		}
	}

	/**
	* The work loop which will loop forever when called.
	*
	* This will take care of getting the current/historical price and calls the bots _process method when needed.
	*
	* @return void
	*/
	public function work() {
		$this->preLaunch();

		$this->currentTime = new Time($this->lastProcessingTime);

		$ticks = 0;

		while(1) {
			$this->profileStart();

			$this->profile("Getting real-time sample", __FILE__, __LINE__);

			// always get the realtime value
			$this->data->getAssetValueForTime(Time::now());

			$this->profile("Getting sample for running time", __FILE__, __LINE__);

			// get the value we need right now
			$sample = $this->data->getAssetValueForTime($this->currentTime);

			$this->profileEndTask();

			$hasRun = $this->_process($this->currentTime, $sample);

			$this->profileStop();

			if ($this->currentTime->isNow() || $this->getShouldNotProcess()) {
				// Sleep for 1 second
				sleep(1);

				$ticks += 1;
			} else {
				// Sleep for 0.01 seconds
				usleep(1000000 * 0.01);

				$this->currentTime->add(1);

				$ticks += 0.5;
			}

			if ($ticks >= 15)
			{
				// This will first save our changes and then load any changed settings or state
				$this->data->saveAndReload();

				$ticks = 0;
			}
		}
	}

	/**
	* Calls the bots implemented method process() method after checking the timeframe hasnt been processed before.
	*
	* @return boolean
	*/
	public function _process(\GalacticBot\Time $time, $sample)
	{
		$lastTrade = $this->data->getLastTrade();

		// Update the last trade
		if ($lastTrade && !$lastTrade->getIsFilledCompletely()) {
			$lastTradeUpdateTime = $this->data->get("lastTradeUpdateTime");
			$lastTradeUpdateID = $this->data->get("lastTradeUpdateID");
			
			$now = \GalacticBot\Time::now(true);

			if ($lastTradeUpdateID != $lastTrade->getID()) {
				$this->data->set("lastTradeUpdateTime", $now->toString());
				$this->data->set("lastTradeUpdateID", $lastTrade->getID());
			} else {
				$lastTradeUpdateTime = $lastTradeUpdateTime ? \GalacticBot\Time::fromString($lastTradeUpdateTime) : null;

				if ($lastTradeUpdateTime->getAgeInSeconds($now) >= 60*2) {
					$this->data->set("lastTradeUpdateTime", $now->toString());
				
					$this->getDataInterface()->logVerbose("We've got a trade open for a long time, making sure it isn't cancelled by checking open trades for this bot's account.");

					$account = \GalacticHorizon\Account::createFromPublicKey($this->getSettings()->getAccountPublicKey());
						
					if ($account->fetch()) {
						$offers = $account->getOffers("");

						if (count($offers) == 0) {
							$this->getDataInterface()->logVerbose("There aren't any open trades, marking our open trade as cancelled.");
		
							$this->cancel($time, $lastTrade);
						}
					}
				}
			}
		}

		$state = $this->data->get("state");
		$tradeState = $this->data->get("tradeState");

		if ($this->data->get("resetNeeded") == 1)
		{
			$this->data->directSet("resetNeeded", 0);

			$this->performFullReset();

			// return and start in the next iteration
			return;
		}

		if ($this->getShouldNotProcess())
		{
			//$this->data->logVerbose("Stopped or not running, doing nothing.");
			// Do nothing
			return false;
		}

		$this->shouldTrade = true;

		// Do all we have to do when paused, except for trading
		if ($state == self::STATE_PAUSED)
			$this->shouldTrade = false;


		if (!$time->isAfter($this->lastProcessingTime)) {
			return false;
		}

		$this->data->logVerbose("Processing timeframe (" . $time->toString() . ")");
		
		if ($lastTrade && !$lastTrade->getIsFilledCompletely())
			$this->getDataInterface()->logVerbose("Trade #" . $lastTrade->getID() . " is not fulfilled yet (fill: " . $lastTrade->getFillPercentage() . "%).");

		// Do not trade based up off old data
		if (!$time->isNow()) {
			$this->data->logVerbose("Trading is disabled, processing old data.");
			$this->shouldTrade = false;
		}

		$lastTrade = $this->data->getLastTrade();

		$this->process($time, $sample);
	
		$this->lastProcessingTime = new Time($time);
		$this->data->set("lastProcessingTime", $this->lastProcessingTime->toString());
	
		if (!$this->data->get("firstProcessingTime"))
			$this->data->set("firstProcessingTime", (Time::now())->toString());
	
		$this->data->setT($time, "baseAssetAmount", $this->getCurrentBaseAssetBudget());
		$this->data->setT($time, "counterAssetAmount", $this->getCurrentCounterAssetBudget());
		$this->data->setT($time, "totalHoldings", $this->getTotalHoldings());
		$this->data->setT($time, "profitPercentage", $this->getProfitPercentage());

		$this->data->set("tradeStateDescription", $this->getTradeStateLabel($this->data->get("tradeState")));

		// save, excluding the samples buffers (we don't want to save them every run when simulating)
		$this->data->save(false);

		return true;
	}

	/**
	* Loads, caches and returns balances for the bot's Stellar account.
	*
	* @return float
	*/
	function getAccountBalances() {
		$balances = $this->data->get("acountInfoBalances_Data");
		$date = $this->data->get("acountInfoBalances_Date");

		if ($balances && $date) {
			$date = Time::fromString($date);

			if ($date->isNow()) {
				return json_decode($balances);
			}
		}

		$date = Time::now();
		$account = $this->getAccountInfo();
		$balances = [];
		$balancesArray = [];
		
		if ($account) {
			$balances = $account->getBalances();

			foreach($balances AS $balance) {
				$balancesArray[] = (object)Array(
					"balanceInStroops" => $balance->getBalance()->toString(),
					"assetCode" => $balance->getAsset()->getCode(),
					"assetIssuer" => !$balance->getAsset()->isNative() ? $balance->getAsset()->getIssuer()->getPublicKey() : null,
					"assetIsNative" => $balance->getAsset()->isNative() ? true : false
				);
			}
		}

		$this->data->set("acountInfoBalances_Data", json_encode($balancesArray));
		$this->data->set("acountInfoBalances_Date", $date->toString());
		$this->data->save();

		return $balancesArray;
	}

	/**
	* Loads information (balance and minimum requirement) for the bot's Stellar account.
	*
	* @return float
	*/
	function getAccountInfo() {
		if (!$this->accountInfo || !$this->lastAccountInfoUpdate->isNow()) {
			$account = \GalacticHorizon\Account::createFromPublicKey($this->getSettings()->getAccountPublicKey());
			
			if ($account->fetch()) {
				$this->accountInfo = $account;
				$this->lastAccountInfoUpdate = Time::now();
			} else if (!$this->accountInfo) {
				$this->data->logError("Cannot load account info from the Stellar network.");
			} else {
				$this->data->logWarning("Cannot load account info from the Stellar network. Using previously loaded data for now.");
			}
		}

		return $this->accountInfo;
	}

	function getMinimumXLMRequirement()
	{
		$minimum = $this->data->get("acountInfoMinimum_Value");
		$date = $this->data->get("acountInfoMinimum_Date");

		if ($minimum && $date)
		{
			$date = Time::fromString($date);

			if ($date->isNow())
				return $minimum;
		}

		$date = Time::now();
		$account = $this->getAccountInfo();
		$minimum = null;
		
		if ($account) {
			$minimum = $account->getMinimumBalance();
		}

		$minimum += 0.5; // For one outstanding offer

		$this->data->set("acountInfoMinimum_Value", $minimum);
		$this->data->set("acountInfoMinimum_Date", $date->toString());
		$this->data->save();

		return $minimum;
	}

	function baseAssetIsNative()
	{
		return $this->getSettings()->getBaseAsset()->getCode() == null;
	}

	/**
	* Returns total amount this Bot has in the base asset.
	*
	* @return float
	*/
	function getCurrentBaseAssetBudget($subtractMinimumXLMReserve = false)
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$lastTrade = $this->data->getLastCompletedTrade();

			$type = $this->baseAssetIsNative() ? Trade::TYPE_SELL : Trade::TYPE_BUY;

			if ($lastTrade && $lastTrade->getType() == $type)
				return $lastTrade->getBoughtAmount();
			else if ($lastTrade)
				return 0; // we currently have the counter asset

			// Start a simulation with a fixed amount
			return $this->baseAssetIsNative() ? 100 : 0;
		}

		$balances = $this->getAccountBalances();

		if ($balances) {
			$botBaseAssetCode = $this->settings->getBaseAsset()->getCode();
			$botBaseAssetIssuer = $this->settings->getBaseAsset()->getIssuer() ? $this->settings->getBaseAsset()->getIssuer()->getPublicKey() : null;

			foreach($balances AS $balance) {
				if ($balance->assetIsNative) {
					$assetCode = null;
					$assetIssuer = null;
				} else {
					$assetCode = $balance->assetCode;
					$assetIssuer = $balance->assetIssuer;
				}
			
				if (
					$botBaseAssetCode == $assetCode
				&&	$botBaseAssetIssuer == $assetIssuer
				)
				{
					if (!$assetCode && $subtractMinimumXLMReserve)
						return self::stroopsToInt($balance->balanceInStroops) - $this->getMinimumXLMRequirement() - $this->settings->getBaseAssetReservationAmount();
					else
						return self::stroopsToInt($balance->balanceInStroops) - $this->settings->getBaseAssetReservationAmount();
				}
			}
		}

		return null;
	}

	// TODO: Temporary, should work with BigInteger's or Amount objects only!
	static function stroopsToInt($stroopsString) {
		if (!$stroopsString)
			return 0;

		$amount = \GalacticHorizon\Amount::createFromString($stroopsString);

		return $amount->toFloat();
	}

	/**
	* Returns total amount this Bot has in the counter asset.
	*
	* @return float
	*/
	function getCurrentCounterAssetBudget($subtractMinimumXLMReserve = false) {
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$lastTrade = $this->data->getLastCompletedTrade();

			$type = $this->baseAssetIsNative() ? Trade::TYPE_BUY : Trade::TYPE_SELL;

			if ($lastTrade && $lastTrade->getType() == $type)
				return $lastTrade->getBoughtAmount();
			else if ($lastTrade)
				return 0;

			// Start a simulation with a fixed amount
			return $this->baseAssetIsNative() ? 0 : 100;
		}

		$balances = $this->getAccountBalances();

		if ($balances) {
			$botCounterAssetCode = $this->settings->getCounterAsset()->getCode();
			$botCounterAssetIssuer = $this->settings->getCounterAsset()->getIssuer() ? $this->settings->getCounterAsset()->getIssuer()->getPublicKey() : null;

			foreach($balances as $balance) {

				if ($balance->assetIsNative) {
					$assetCode = null;
					$assetIssuer = null;
				} else {
					$assetCode = $balance->assetCode;
					$assetIssuer = $balance->assetIssuer;
				}

				if (
					$botCounterAssetCode == $assetCode
				&&	$botCounterAssetIssuer == $assetIssuer
				) {
					if (!$assetCode && $subtractMinimumXLMReserve)
						return self::stroopsToInt($balance->balanceInStroops) - $this->getMinimumXLMRequirement();
					else
						return self::stroopsToInt($balance->balanceInStroops);
				}
			}
		}

		return 0;
	}

	/**
	* Returns a sum of total holdings (converted to the base asset when needed).
	*
	* @return float
	*/
	function getTotalHoldings()
	{
		$lastTrade = $this->data->getLastCompletedTrade();
		$previousTrade = $lastTrade && $lastTrade->getPreviousBotTradeID() ? $this->data->getTradeByID($lastTrade->getPreviousBotTradeID()) : null;

		if ($this->baseAssetIsNative())
		{
			$sum = $this->getCurrentBaseAssetBudget();
			$otherAssetAmmount = $this->getCurrentCounterAssetBudget();
		}
		else
		{
			$sum = $this->getCurrentCounterAssetBudget();
			$otherAssetAmmount = $this->getCurrentBaseAssetBudget();
		}

		if ($lastTrade && $lastTrade->getType() == Trade::TYPE_BUY)
		{
			$price = $this->data->getAssetValueForTime(Time::now());

			if ($price)
				$price = 1/$price;

			$sum += $otherAssetAmmount * $price;
		}
		else if ($lastTrade && $previousTrade && $previousTrade->getType() == Trade::TYPE_BUY)
		{
			$price = $this->data->getAssetValueForTime(Time::now());
			
			if ($price)
				$price = 1/$price;

			$sum += $otherAssetAmmount * $price;
		}

		return $sum;
	}

	/**
	* Calculates how much profit this bot has made since it started.
	*
	* @return float
	*/
	function getProfitPercentage()
	{
		$firstTrade = $this->data->getFirstCompletedTrade();

		if ($firstTrade)
			$start = $this->data->getT(Time::fromDateTime($firstTrade->getProcessedAt()), "amount");

		$start = $firstTrade ? $firstTrade->getSellAmount() : 0;

		if (!$start)
			return 0;

		$current = $this->getTotalHoldings();

		$percentage = round((($current / $start) - 1) * 10000)/100;

		return $percentage;
	}

	/**
	* Tries to trade the base asset for the counter asset at the current price.
	*
	* @return Trade or null
	*/
	function buy(Time $processingTime, Trade $updateExistingTrade = null, $cancelOffer = false, $price = null)
	{
		if (!$this->shouldTrade && !$cancelOffer) // But allow to cancel an order
			return null;

		if ($cancelOffer)
			$price = $updateExistingTrade->getPrice();

		$budget = $this->getCurrentBaseAssetBudget(true);
		$fromAsset = $this->settings->getBaseAsset();
		$toAsset = $this->settings->getCounterAsset();
		
		$offerIDToUpdate = $updateExistingTrade ? $updateExistingTrade->getOfferID() : null;

		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$trade = new Trade();
			$trade->simulate(Trade::TYPE_BUY, $this, $processingTime, $fromAsset, $budget, $toAsset);
		}
		else
		{
			// make sure to get the latest account info (and thus sequence number)
			$this->getAccountInfo();

			$trade = $this->manageOffer(true, $processingTime, $fromAsset, $budget, $toAsset, $offerIDToUpdate, $cancelOffer, $price);
		}

		if ($cancelOffer)
		{
			return $trade;
		}

		if (!$trade)
			return false;

		if ($updateExistingTrade)
		{
			$updateExistingTrade->setState(Trade::STATE_REPLACED);
			$this->data->saveTrade($updateExistingTrade);

			$trade->setPreviousBotTradeID($updateExistingTrade->getPreviousBotTradeID());
		}
		else
		{
			$lastTrade = $this->data->getLastTrade();

			if ($lastTrade)
				$trade->setPreviousBotTradeID($lastTrade->getID());
		}

		$trade->setProcessedAt($processingTime->getDateTime());
		$this->data->addTrade($trade);

		return $trade;
	}

	/**
	* Cancels any open Trade (buy or sell).
	*
	* @return void
	*/
	function cancel(Time $processingTime, Trade $trade, $newState = Trade::STATE_CANCELLED)
	{
		// only try to cancel orders which are open
		if ($trade->getState() != Trade::STATE_CREATED)
			return;

		if ($this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
		{
			if ($trade->getType() == Trade::TYPE_SELL)
				$this->sell($processingTime, $trade, true);
			else
				$this->buy($processingTime, $trade, true);
		}
		
		if ($newState === null)
			return;

		$trade->setState($newState);
		$this->data->saveTrade($trade);
	}

	/**
	* Tries to trade the counter asset for the base asset at the current price.
	*
	* @return Trade or null
	*/
	function sell(Time $processingTime, Trade $updateExistingTrade = null, $cancelOffer = false, $price = null)
	{
		if (!$this->shouldTrade && !$cancelOffer) // But allow to cancel an order
			return null;

		if ($cancelOffer)
			$price = $updateExistingTrade->getPrice();

		$budget = $this->getCurrentCounterAssetBudget(true);
		$fromAsset = $this->settings->getCounterAsset();
		$toAsset = $this->settings->getBaseAsset();
		
		$offerIDToUpdate = $updateExistingTrade ? $updateExistingTrade->getOfferID() : null;

		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$trade = new Trade();
			$trade->simulate(Trade::TYPE_SELL, $this, $processingTime, $fromAsset, $budget, $toAsset);
		}
		else
		{
			// make sure to get the latest account info (and thus sequence number)
			$this->getAccountInfo();

			$trade = $this->manageOffer(false, $processingTime, $fromAsset, $budget, $toAsset, $offerIDToUpdate, $cancelOffer, $price);
		}

		if ($cancelOffer)
		{
			return $trade;
		}

		if (!$trade)
			return false;

		if ($updateExistingTrade)
		{
			$updateExistingTrade->setState(Trade::STATE_REPLACED);
			$this->data->saveTrade($updateExistingTrade);

			$trade->setPreviousBotTradeID($updateExistingTrade->getPreviousBotTradeID());
		}
		else
		{
			$lastTrade = $this->data->getLastTrade();

			if ($lastTrade)
				$trade->setPreviousBotTradeID($lastTrade->getID());
		}

		$trade->setProcessedAt($processingTime->getDateTime());

		$this->data->addTrade($trade);

		return $trade;
	}

	private function manageOffer($isBuyOffer, Time $time, \GalacticHorizon\Asset $sellingAsset, $sellingAmount, \GalacticHorizon\Asset $buyingAsset, $offerIDToUpdate = null, $cancelOffer = false, $price = null)
	{
		global $_BASETIMEZONE;
		
		// reset last trade update time
		$now = \GalacticBot\Time::now();
		$this->data->set("lastTradeUpdateTime", $now->toString());
		$this->data->set("lastTradeUpdateID", null);
		
		$this->data->logVerbose("Manage offer called; isBuyOffer = " . ($isBuyOffer ? "true" : "false") . ", sellingAsset = " . $sellingAsset . ", sellingAmount = " . $sellingAmount . ", buyingAsset = " . $buyingAsset . ", offerIDToUpdate = " . $offerIDToUpdate . ", cancelOffer = " . ($cancelOffer ? "true" : "false") . ", price = " . ($price === null ? "null" : $price));
	
		if ($price === null) {
			$this->data->logVerbose("Manage offer called with zero price. Fetching the current price by ourselfs.");

			$price = $this->getDataInterface()->getAssetValueForTime($time);
		}

		if ((float)$price <= 0) {
			$this->data->logError("Manage offer failed, price is (still) zero.");
			return false;
		}

		if ((float)$sellingAmount <= 0) {
			$this->data->logError("Manage offer failed, selling amount is zero.");
			return false;
		}

		$sellingIsCounterAsset = false;

		if ($sellingAsset->getCode() == $this->settings->getCounterAsset()->getCode() && $sellingAsset->getIssuer()->getPublicKey() == $this->settings->getCounterAsset()->getIssuer())
		{
			$sellingIsCounterAsset = true;
		}

		if ($sellingIsCounterAsset)
			$price = 1/$price;

	//	var_dump("price = ", $price, ", sellingIsCounterAsset = ", $sellingIsCounterAsset);
	//	exit();

		$buyingAmount = $price * $sellingAmount;

		if ($isBuyOffer)
			$price = \GalacticHorizon\Price::createFromFloat($buyingAmount / $sellingAmount);
		else
			$price = \GalacticHorizon\Price::createFromFloat($sellingAmount / $buyingAmount);

		$buyingAmount = (float)number_format($buyingAmount, 7, '.', '');
		$sellingAmount = (float)number_format($sellingAmount, 7, '.', '');

		$buyingAmount = \GalacticHorizon\Amount::createFromFloat($buyingAmount);
		$sellingAmount = \GalacticHorizon\Amount::createFromFloat($sellingAmount);

		$manageOffer = new \GalacticHorizon\ManageOfferOperation();
		$manageOffer->setSellingAsset($sellingAsset);
		$manageOffer->setBuyingAsset($buyingAsset);
		$manageOffer->setAmount($cancelOffer ? \GalacticHorizon\Amount::createFromFloat(0) : $sellingAmount);
		$manageOffer->setPrice($price);
		$manageOffer->setOfferID($offerIDToUpdate ? $offerIDToUpdate : null);

		try {
			$transaction = new \GalacticHorizon\Transaction($this->settings->getAccountKeypair());
			$transaction->addOperation($manageOffer);
			$transaction->sign([$this->settings->getAccountKeypair()]);
			
			$buffer = new \GalacticHorizon\XDRBuffer();
			$transaction->toXDRBuffer($buffer);

			$automaticlyFixTrustLineWithAmount = \GalacticHorizon\Amount::createFromFloat(200);

			$transactionResult = $transaction->submit($automaticlyFixTrustLineWithAmount);

			if ($transactionResult->getErrorCode() == \GalacticHorizon\TransactionResult::TX_SUCCESS) {
				// Return when an offer is cancelled
				if ($cancelOffer)
					return true;

				$trade = Trade::fromGalacticHorizonOperationResponseAndResultForBot(
					$manageOffer,
					$transactionResult,
					$transactionResult->getResult(0),
					$buffer->toBase64String(),
					$transactionResult->getFeeCharged()->toString(),
					$this
				);

				return $trade;
			} else {
				$this->getDataInterface()->logError("Manage offer operation failed, error code = " . $transactionResult->getErrorCode());

				if ($transactionResult->getResultCount() > 0) {
					$this->getDataInterface()->logError("Manage offer result, error code = " . $transactionResult->getResult(0)->getErrorCode());
				}

				$this->getDataInterface()->logError("Transaction envelope = " . $buffer->toBase64String());
			}
		} catch (\GalacticHorizon\Exception $e) {
			$this->getDataInterface()->logError("Manage offer operation failed, exception = " . (string)$e);
			$this->getDataInterface()->logError("Response = " . $e->getHttpResponseBody());
		}

		return false;
	}

}


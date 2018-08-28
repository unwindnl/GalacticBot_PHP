<?php

namespace GalacticBot;

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
	const STATE_NEEDS_RESET						= "NEEDS_RESET";

	/*
	* Default trade state, you will have to define your own trade states in your bot implementation
	*/
	const TRADE_STATE_NONE						= "";

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
		$this->data->directSet("state", self::STATE_RUNNING);
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
	* Sets the bot state to 'should reset'. For this to take affect the bot should be running (see the demo for a worker script). See the performFullReset method for more information.
	*
	* @return void
	*/
	public function simulationReset()
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$this->data->directSet("state", self::STATE_NEEDS_RESET);
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

			case self::STATE_NEEDS_RESET:						$label = "Waiting for reset to complete"; break;
		}

		return Array(
			"state" => $state,
			"label" => $label
		);
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
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$this->getDataInterface()->clearAllExceptSampleDataAndSettings();
		
			$aWeekAgo = Time::now();
			$aWeekAgo->subtractWeeks(1);

			$this->lastProcessingTime = new Time($aWeekAgo);
			$this->data->set("lastProcessingTime", $aWeekAgo->toString());

			$this->currentTime = new Time($aWeekAgo);
		}

		$this->data->set("state", self::STATE_RUNNING);
		$this->data->save();
	}

	/**
	* The work loop which will loop forever when called.
	*
	* This will take care of getting the current/historical price and calls the bots _process method when needed.
	*
	* @return void
	*/
	public function work()
	{
		date_default_timezone_set("UTC");

		$timezone = date_default_timezone_get();

		if ($timezone != "UTC")
			exit("Cannot set timezone to UTC");

		$this->currentTime = new Time($this->lastProcessingTime);

		$ticks = 0;

		while(1) {
			// always get the realtime value
			$this->data->getAssetValueForTime(Time::now());

			// get the value we need right now
			$sample = $this->data->getAssetValueForTime($this->currentTime);

			$hasRun = $this->_process($this->currentTime, $sample);

			if ($this->currentTime->isNow() || $this->getShouldNotProcess()) {
				sleep(1);

				$ticks += 1/3;
			} else {
				$this->currentTime->add(1);

				$ticks += 1/100;
			}

			if ($ticks >= 1)
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
		$state = $this->data->get("state");
		$tradeState = $this->data->get("tradeState");

		if ($state == self::STATE_NEEDS_RESET)
		{
			$this->performFullReset();

			// return and start in the next iteration
			return;
		}

		if ($this->getShouldNotProcess())
		{
			$this->data->logVerbose("Stopped or not running, doing nothing.");
			// Do nothing
			return false;
		}

		$this->shouldTrade = true;

		// Do all we have to do when paused, except for trading
		if ($state == self::STATE_PAUSED)
			$this->shouldTrade = false;

		if (!$time->isAfter($this->lastProcessingTime)) {
			$this->data->logVerbose("Already processed this timeframe (" . $time->toString() . ")");
			return false;
		}

		$this->data->logVerbose("Processing timeframe (" . $time->toString() . ")");

		$lastTrade = $this->data->getLastTrade();

		// Update the last trade
		if ($lastTrade && !$lastTrade->getIsFilledCompletely())
			$lastTrade->updateFromAPIForBot($this->settings->getAPI(), $this);

		$this->process($time, $sample);
		
		$this->lastProcessingTime = new Time($time);
		$this->data->set("lastProcessingTime", $this->lastProcessingTime->toString());

		$this->data->save();
			
		return true;
	}

	/**
	* Loads information (balance and minimum requirement) for the bot's Stellar account.
	*
	* @return float
	*/
	function getAccountInfo()
	{
		if (!$this->accountInfo || !$this->lastAccountInfoUpdate->isNow())
		{
			$info = $this->settings->getAPI()->getAccount($this);

			if ($info)
			{
				$this->accountInfo = $info;
				$this->lastAccountInfoUpdate = Time::now();
			}
			else if (!$this->accountInfo)
			{
				$this->data->logError("Cannot load account info from the Stellar network.");
			}
			else
			{
				$this->data->logWarning("Cannot load account info from the Stellar network. Using previously loaded data for now.");
			}
		}

		return $this->accountInfo;
	}

	/**
	* Returns total amount this Bot has in the base asset.
	*
	* @return float
	*/
	function getCurrentBaseAssetBudget()
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$lastTrade = $this->data->getLastCompletedTrade();

			if ($lastTrade && $lastTrade->getType() == Trade::TYPE_SELL)
				return $lastTrade->getBoughtAmount();
			else if ($lastTrade)
				return 0; // we currently have the counter asset

			// Start a simulation with a fixed amount
			return 100;
		}

		$account = $this->getAccountInfo();

		foreach($account->getBalances() as $balance)
		{
			$assetCode = $balance->getAssetCode();

			if ($assetCode == "XLM")
				$assetCode = null;

			if (
				$this->settings->getBaseAsset()->getAssetCode() == $assetCode
			)
			{
				return $balance->getBalance() - $account->getMinimumRequirement();
			}
		}

		return null;
	}

	/**
	* Returns total amount this Bot has in the counter asset.
	*
	* @return float
	*/
	function getCurrentCounterAssetBudget()
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$lastTrade = $this->data->getLastCompletedTrade();

			if ($lastTrade && $lastTrade->getType() == Trade::TYPE_BUY)
				return $lastTrade->getBoughtAmount();

			return 0;
		}

		$account = $this->getAccountInfo();

		foreach($account->getBalances() as $balance)
		{
			$assetCode = $balance->getAssetCode();

			if ($assetCode == "XLM")
				$assetCode = null;

			if (
				$this->settings->getCounterAsset()->getAssetCode() == $assetCode
			)
			{
				return $balance->getBalance();
			}
		}

		return 0;
	}

	/**
	* Returns a sum of total holdings converted to the base asset.
	*
	* @return float
	*/
	function getTotalHoldings()
	{
		$sum = $this->getCurrentBaseAssetBudget();
		$counterAssetAmmount = $this->getCurrentCounterAssetBudget();

		$lastTrade = $this->data->getLastCompletedTrade();
		$previousTrade = $lastTrade && $lastTrade->getPreviousBotTradeID() ? $this->data->getTradeByID($lastTrade->getPreviousBotTradeID()) : null;

		if ($lastTrade && $lastTrade->getType() == Trade::TYPE_BUY)
		{
			$sum += $counterAssetAmmount * $lastTrade->getPaidPrice();
		}
		else if ($lastTrade && $previousTrade->getType() == Trade::TYPE_BUY)
		{
			$sum += $counterAssetAmmount * $previousTrade->getPaidPrice();
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

		$start = $firstTrade ? $firstTrade->getSellAmount() : 0;

		if (!$start)
			return 0;

		$current = $this->getTotalHoldings();

		return round((($current / $start) - 1) * 10000)/100;
	}

	/**
	* Tries to trade the base asset for the counter asset at the current price.
	*
	* @return Trade or null
	*/
	function buy(Time $processingTime)
	{
		if (!$this->shouldTrade)
			return null;

		$budget = $this->getCurrentBaseAssetBudget();

		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$trade = new Trade();
			$trade->simulate(Trade::TYPE_BUY, $this, $processingTime, $this->settings->getBaseAsset(), $budget, $this->settings->getCounterAsset());
		}
		else
		{
			$trade = $this->settings->getAPI()->manageOffer($this, $processingTime, $this->settings->getBaseAsset(), $budget, $this->settings->getCounterAsset());
		}

		$lastTrade = $this->data->getLastTrade();

		if ($lastTrade)
			$trade->setPreviousBotTradeID($lastTrade->getID());

		$trade->setProcessedAt($processingTime->getDateTime());
		$this->data->addTrade($trade);

		return $trade;
	}

	/**
	* Cancels any open Trade (buy or sell).
	*
	* @return void
	*/
	function cancel(Time $processingTime, Trade $trade)
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
		{
			$this->sell($processingTime, $trade, true);
		}

		$trade->setState(Trade::STATE_CANCELLED);
		$this->data->saveTrade($trade);
	}

	/**
	* Tries to trade the counter asset for the base asset at the current price.
	*
	* @return Trade or null
	*/
	function sell(Time $processingTime, Trade $updateExistingTrade = null, $cancelOffer = false)
	{
		if (!$this->shouldTrade)
			return null;

		$budget = $this->getCurrentCounterAssetBudget();

		$offerIDToUpdate = $updateExistingTrade ? $updateExistingTrade->getOfferID() : null;

		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$trade = new Trade();
			$trade->simulate(Trade::TYPE_SELL, $this, $processingTime, $this->settings->getBaseAsset(), $budget, $this->settings->getCounterAsset());
		}
		else
		{
			$trade = $this->settings->getAPI()->manageOffer($this, $processingTime, $this->settings->getCounterAsset(), $budget, $this->settings->getBaseAsset(), $offerIDToUpdate, $cancelOffer);
		}

		if ($cancelOffer)
		{
			return $trade;
		}

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

}


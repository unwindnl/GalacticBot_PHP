<?php

namespace GalacticBot;

abstract class Bot
{

	const SETTING_TYPE_SIMULATION				= "SIMULATION";
	const SETTING_TYPE_LIVE						= "LIVE";

	const STATE_NONE							= "";
	const STATE_RUNNING							= "RUNNING";
	const STATE_PAUSED							= "PAUSED";
	const STATE_STOPPED							= "STOPPED";
	const STATE_NEEDS_RESET						= "NEEDS_RESET";

	const TRADE_STATE_NONE						= "";

	protected $settings = null;
	protected $data = null;

	private $shouldTrade = false;

	// Time we're processing, see work() method
	private $currentTime = null;

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

	abstract protected function initialize();

	abstract public function getTradeStateLabel($forState);

	abstract protected function process(\GalacticBot\Time $time, $sample);

	public function getLastProcessingTime()
	{
		return new \DateTime($this->data->get("lastProcessingTime"));
	}

	public function getSettings()
	{
		return $this->settings;
	}

	public function getDataInterface()
	{
		return $this->data;
	}

	public function start()
	{
		$this->data->directSet("state", self::STATE_RUNNING);
	}

	public function pause()
	{
		$this->data->directSet("state", self::STATE_PAUSED);
	}

	public function stop()
	{
		$this->data->directSet("state", self::STATE_STOPPED);
	}

	public function simulationReset()
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_SIMULATION)
		{
			$this->data->directSet("state", self::STATE_NEEDS_RESET);
		}
	}

	public function getState() { return $this->data->get("state"); }

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

	public function getTradeState() { return $this->data->get("tradeState"); }

	function getTradeStateInfo() {
		$state = $this->getTradeState();
		$label = "Unknown state ($state)";

		$label = $this->getTradeStateLabel($state);

		return Array(
			"state" => $state,
			"label" => $label
		);
	}

	public function getIsNotRunning()
	{
		$state = $this->data->get("state");
		return !$state || $state == self::STATE_NONE || $state == self::STATE_STOPPED;
	}

	public function performFullReset()
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

	public function work()
	{
		$this->currentTime = new Time($this->lastProcessingTime);

		$ticks = 0;

		while(1) {
			// always get the realtime value
			$this->data->getAssetValueForTime(Time::now());

			// get the value we need right now
			$sample = $this->data->getAssetValueForTime($this->currentTime);

			$hasRun = $this->_process($this->currentTime, $sample);

			if ($this->currentTime->isNow() || $this->getIsNotRunning()) {
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

		if ($this->getIsNotRunning())
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

		$this->process($time, $sample);
		
		$this->lastProcessingTime = new Time($time);
		$this->data->set("lastProcessingTime", $this->lastProcessingTime->toString());

		$this->data->save();
			
		return true;
	}

	function getCurrentBaseAssetBudget()
	{
		$lastTrade = $this->data->getLastCompletedTrade();

		if (!$lastTrade)
			return $this->settings->getBaseAssetInitialBudget();

		$sum = 0;

		if ($lastTrade->getType() == Trade::TYPE_SELL)
			$sum += $this->getAvailableBudgetForAsset($this->settings->getBaseAsset(), false);
		else
			$sum += $lastTrade->getAmountRemaining() * $lastTrade->getPaidPrice();

		return $sum;
	}

	function getCurrentCounterAssetBudget()
	{
		$lastTrade = $this->data->getLastCompletedTrade();

		if (!$lastTrade)
			return 0;

		$sum = 0;

		if ($lastTrade->getType() == Trade::TYPE_BUY)
			$sum += $this->getAvailableBudgetForAsset($this->settings->getCounterAsset(), false);
		else
			$sum += $lastTrade->getAmountRemaining();

		return $sum;
	}

	function getTotalHoldings()
	{
		$lastTrade = $this->data->getLastCompletedTrade();

		if (!$lastTrade)
			return $this->settings->getBaseAssetInitialBudget();
		
		$previousTrade = $lastTrade->getPreviousBotTradeID() ? $this->data->getTradeByID($lastTrade->getPreviousBotTradeID()) : null;

		$sum = 0;

		if ($lastTrade->getType() == Trade::TYPE_BUY)
		{
			$sum = $lastTrade->getBoughtAmount() * $lastTrade->getPaidPrice();

			if ($previousTrade)
			{
				$sum += $previousTrade->getAmountRemaining();
			}
		}
		else
		{
			$sum = $lastTrade->getBoughtAmount();

			if ($previousTrade)
			{
				$sum += $previousTrade->getAmountRemaining() * $lastTrade->getPaidPrice();
			}
		}

		return $sum;
	}

	function getProfitPercentage()
	{
		$start = $this->settings->getBaseAssetInitialBudget();
		$current = $this->getTotalHoldings();

		return (($current / $start) - 1) * 100;
	}

	function getAvailableBudgetForAsset($asset, $onlyFromLastTrade = true)
	{
		$lastTrade = $this->data->getLastCompletedTrade();

		if ($lastTrade)
		{
			if ($onlyFromLastTrade)
			{
				if (
					$asset->getType() == $this->settings->getCounterAsset()->getType()
				&&	$lastTrade->getType() != Trade::TYPE_BUY
				)
				{
					$caller = debug_backtrace(0);
					$caller = array_shift($caller);
					$caller = $caller["file"] . " on line: #" . $caller["line"];

					$this->data->save();

					exit("TODO: How did this happen? Last trade type is invalid " . __FILE__ . " on line #" . __LINE__ . "\nCalled from: $caller\n");
				}
				else if (
					$asset->getType() == $this->settings->getBaseAsset()->getType()
				&&	$lastTrade->getType() != Trade::TYPE_SELL
				)
				{
					$caller = debug_backtrace(0);
					$caller = array_shift($caller);
					$caller = $caller["file"] . " on line: #" . $caller["line"];

					$this->data->save();

					exit("TODO: How did this happen? Last trade type is invalid " . __FILE__ . " on line #" . __LINE__ . "\nCalled from: $caller\n");
				}
			}
			else
			{
				if (
					$asset->getType() == $this->settings->getBaseAsset()->getType()
				&&	$lastTrade->getType() == Trade::TYPE_BUY
				)
				{
					$lastTrade = $this->data->getTradeByID($lastTrade->getPreviousBotTradeID());
				}
				else if (
					$asset->getType() == $this->settings->getCounterAsset()->getType()
				&&	$lastTrade->getType() == Trade::TYPE_SELL
				)
				{
					$lastTrade = $this->data->getTradeByID($lastTrade->getPreviousBotTradeID());
				}
			}

			if ($lastTrade)
			{
				$previousTrade = $this->data->getTradeByID($lastTrade->getPreviousBotTradeID());

				$budget = $lastTrade->getBoughtAmount();

				if ($previousTrade)
					$budget += $previousTrade->getAmountRemaining();
			
				return $budget;
			}
		}
		else if ($asset->getType() == $this->settings->getBaseAsset()->getType())
		{
			// initial budget
			return $this->settings->getBaseAssetInitialBudget();
		}

		return null;
	}

	function buy(Time $processingTime)
	{
		if (!$this->shouldTrade)
			return null;

		$budget = $this->getAvailableBudgetForAsset($this->settings->getBaseAsset());

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

	function cancel(Time $processingTime, Trade $trade)
	{
		if ($this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
		{
			$this->sell($processingTime, $trade, true);
		}

		$trade->setState(Trade::STATE_CANCELLED);
		$this->data->saveTrade($trade);
	}

	function sell(Time $processingTime, Trade $updateExistingTrade = null, $cancelOffer = false)
	{
		if (!$this->shouldTrade)
			return null;

		$budget = $this->getAvailableBudgetForAsset($this->settings->getCounterAsset());

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


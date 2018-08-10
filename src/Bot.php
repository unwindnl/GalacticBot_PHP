<?php

namespace GalacticBot;

include_once "HelperFunctions.php";

class Bot
{

	const SETTING_TYPE_SIMULATION				= "SIMULATION";
	const SETTING_TYPE_LIVE						= "LIVE";

	const STATE_NONE							= "";
	const STATE_RUNNING							= "RUNNING";
	const STATE_PAUSED							= "PAUSED";
	const STATE_DONE							= "DONE";

	const TRADE_STATE_NONE						= "";
	const TRADE_STATE_BUFFERING					= "BUFFERING";
	const TRADE_STATE_BUY_DELAY					= "BUY_DELAY";
	const TRADE_STATE_BUY_WAIT_NEGATIVE_TREND	= "BUY_DELAY_NEG";

	const TRADE_STATE_SELL_WAIT					= "SELL_WAIT";
	const TRADE_STATE_SELL_DELAY				= "SELL_DELAY";
	const TRADE_STATE_SELL_WAIT_POSITIVE		= "SELL_WAIT_POSITIVE";
	const TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT	= "SELL_DELAY_MINP";
	const TRADE_STATE_SELL_WAIT_FOR_TRADES		= "SELL_TRADES_WAIT";

	const TRADE_STATE_DIP_WAIT					= "DIP_WAIT";

	private $settings = null;
	
	private $data = null;

	private $shortTermSamples = null;
	private $shortTermSaleSamples = null;
	private $mediumTermSamples = null;
	private $longTermSamples = null;

	private $shortTermValue = null;
	private $shortTermSaleValue = null;
	private $mediumTermValue = null;
	private $longTermValue = null;

	private $predictionBuffer = null;
	private $predictionDirection = null;
		
	private $shortAboveMedium = null;
	private $shortAboveLong = null;
		
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

		$this->shortTermSamples = $this->data->getS("shortTerm", $this->settings->getShortTermSampleCount());
		$this->shortTermSaleSamples = $this->data->getS("shortTermSale", $this->settings->getShortTermSaleSampleCount());
		$this->mediumTermSamples = $this->data->getS("mediumTerm", $this->settings->getMediumTermSampleCount());
		$this->longTermSamples = $this->data->getS("longTerm", $this->settings->getLongTermSampleCount());

		$this->predictionBuffer = $this->data->getS("prediction", $this->settings->getPrognosisWindowMinutes());

		$this->shortAboveMedium = $this->data->get("shortAboveMedium") == 1;
		$this->shortAboveLong = $this->data->get("shortAboveLong") == 1;
	}

	public function getSettings()
	{
		return $this->settings;
	}

	public function getDataInterface()
	{
		return $this->data;
	}

	public function work()
	{
		$time = new Time($this->lastProcessingTime);

		while(1) {
			$sample = $this->data->getAssetValueForTime($time);

			$hasRun = $this->process($time, $sample);

			if ($time->isNow()) {
				sleep(1);
			} else {
				$time->add(1);
			}
		}
	}

	public function getState() { return $this->data->get("state"); }
	public function getTradeState() { return $this->data->get("tradeState"); }

	function getTradeStateInfo() {
		$state = $this->getTradeState();
		$label = "Unknown state";

		switch($state)
		{
			case self::TRADE_STATE_BUFFERING:					$label = "Waiting for enough data"; break;
			case self::TRADE_STATE_NONE:						$label = "Waiting for rise to buy"; break;

			case self::TRADE_STATE_BUY_DELAY:					$label = "Delaying to buy"; break;
			case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:		$label = "Negative trend, wait for bottom"; break;

			case self::TRADE_STATE_SELL_WAIT:					$label = "Waiting for rise to sell"; break;
			case self::TRADE_STATE_SELL_DELAY:					$label = "Delaying to sell"; break;
			case self::TRADE_STATE_SELL_WAIT_POSITIVE:			$label = "Holding, waiting for short above med"; break;
			case self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT:	$label = "Delaying to sell (min profit%)"; break;
			case self::TRADE_STATE_SELL_WAIT_FOR_TRADES:		$label = "Waiting for offer to fulfill"; break;

			case self::TRADE_STATE_DIP_WAIT:					$label = "Waiting for short to fall below long"; break;
		}

		return Array(
			"state" => $state,
			"label" => $label
		);
	}

	public function process(\GalacticBot\Time $time, $sample)
	{
		if (!$time->isAfter($this->lastProcessingTime)) {
			$this->data->logVerbose("Already processed this timeframe (" . $time->toString() . ", lastProcessingTime = " . $this->lastProcessingTime->toString() . ")");
			return false;
		}

		$this->data->logVerbose("Processing timeframe (" . $time->toString() . ")");

		$this->shortTermSamples->add($sample);
		$this->shortTermValue = $this->shortTermSamples->getExponentialMovingAverage();
		$this->data->setT($time, "shortTermValue", $this->shortTermValue);

		$this->shortTermSaleSamples->add($sample);
		$this->shortTermSaleValue = $this->shortTermSaleSamples->getExponentialMovingAverage();
		$this->data->setT($time, "shortTermSaleValue", $this->shortTermSaleValue);

		$this->mediumTermSamples->add($sample);
		$this->mediumTermValue = $this->mediumTermSamples->getExponentialMovingAverage();
		$this->data->setT($time, "mediumTermValue", $this->mediumTermValue);

		$this->longTermSamples->add($sample);
		$this->longTermValue = $this->longTermSamples->getExponentialMovingAverage();
		$this->data->setT($time, "longTermValue", $this->longTermValue);

		// Predict next values and determine the direction of the prediction
		$this->predict($time);

		// Signs
		$shortAboveMedium = $this->shortTermValue > $this->mediumTermValue;
		$shortAboveLong = $this->shortTermValue > $this->longTermValue;

		$changed_sign_shortAboveMedium = $this->shortAboveMedium != $shortAboveMedium;
		$changed_sign_shortAboveLong = $this->shortAboveLong != $shortAboveLong;

		$this->shortAboveMedium = $shortAboveMedium;
		$this->data->set("shortAboveMedium", $shortAboveMedium ? 1 : 0);
		$this->shortAboveLong = $shortAboveLong;
		$this->data->set("shortAboveLong", $shortAboveLong ? 1 : 0);

		$gotFullBuffers = $this->shortTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->shortTermSaleSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->mediumTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->longTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->predictionBuffer->getIsBufferFull();

		$state = $this->data->get("state");
		$tradeState = $this->data->get("tradeState");
		$startOfBuyDelayDate = $this->data->get("startOfBuyDelayDate") ? Time::fromString($this->data->get("startOfBuyDelayDate")) : null;

		$lastTrade = $this->data->getLastTrade();

		if ($lastTrade && !$lastTrade->getIsFilledCompletely())
			$lastTrade->updateFromAPIForBot($this->settings->getAPI(), $this);

		$lastCompletedTrade = $this->data->getLastCompletedTrade();

		$this->data->logVerbose("- lastTrade = " . ($lastTrade ? "#" . $lastTrade->getID() : "none"));
		$this->data->logVerbose("- state = {$state}");
		$this->data->logVerbose("- tradeState = {$tradeState}");

		if ($gotFullBuffers && $tradeState == self::TRADE_STATE_BUFFERING)
			$tradeState = self::TRADE_STATE_NONE;
		
		// Waiting for trade to complete and is completed? Then wait for a new dip to buy in again
		if ($tradeState == self::TRADE_STATE_SELL_WAIT_FOR_TRADES && $lastTrade->getIsFilledCompletely())
			$tradeState = self::TRADE_STATE_DIP_WAIT;

		if (!$gotFullBuffers)
		{
			$tradeState = self::TRADE_STATE_BUFFERING;
		}
		else if ($sample === null)
		{
			$this->data->logWarning("No sample data received, not processing this timeframe.");
			$this->data->save();
			exit();
		}
		else
		{
			switch($tradeState)
			{
				case self::TRADE_STATE_DIP_WAIT:
				case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:
						if ($this->shortTermValue < $this->longTermValue) {
							$tradeState = self::TRADE_STATE_NONE;

							$this->data->logVerbose("- tradeState = {$tradeState}");
						}
					break;
			}
					
			switch($tradeState)
			{
				case self::TRADE_STATE_DIP_WAIT:
				case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:
						// Handled above
					break;

				case self::TRADE_STATE_NONE:
				case self::TRADE_STATE_BUY_DELAY:
				case "":
						// Should buy?
						if ($this->shortTermValue > $this->longTermValue && $this->shortTermSaleValue > $this->longTermValue)
						{
							if (!$startOfBuyDelayDate)
								$startOfBuyDelayDate = clone $time;

							// Buy
							if ($tradeState == self::TRADE_STATE_NONE) {
								$tradeState = self::TRADE_STATE_BUY_DELAY;
							}
								
							if ($tradeState == self::TRADE_STATE_BUY_DELAY) {
								if ($startOfBuyDelayDate->getAgeInMinutes($time) >= $this->settings->getBuyDelayMinutes()) {
									if ($this->predictionDirection >= 0) {
										$tradeState = self::TRADE_STATE_NONE;
										$startOfBuyDelayDate = null;
										
										$this->buy($time);

										$tradeState = self::TRADE_STATE_SELL_WAIT;
									} else {
										$tradeState = self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND;
									}
								}
							}

						}
						else
						{
							$startOfBuyDelayDate = null;

							$tradeState = self::TRADE_STATE_NONE;
						}
					break;
				
				case self::TRADE_STATE_SELL_WAIT:
				case self::TRADE_STATE_SELL_DELAY:
				case self::TRADE_STATE_SELL_WAIT_POSITIVE:
				case self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT:
				case self::TRADE_STATE_SELL_WAIT_FOR_TRADES:
						if (!$lastTrade)
						{
							// Something went wrong, waiting to sell but we don't have anything to sell - can't be good
							// A well, reset to normal state and see what happens :)
							$tradeState = self::TRADE_STATE_NONE;
						}
						else if ($tradeState == self::TRADE_STATE_SELL_WAIT && !$lastTrade->getIsFilledCompletely())
						{
							// Bought in but order is not complete yet
						}
						else
						{
							$currentProfitPercentage = (1/$sample) / $lastCompletedTrade->getPaidPrice();
							$currentProfitPercentage -= 1;
							$currentProfitPercentage *= 100;

							// Short crossed long
							$shortBelowLong = ($this->shortTermSaleValue <= $this->longTermValue);

							$holdLongEnough = $lastCompletedTrade->getAgeInMinutes($time) >= $this->settings->getMinimumHoldMinutes();

							$gotEnoughProfit = $currentProfitPercentage < $this->settings->getMinimumProfitPercentage();

							if (
								$tradeState == self::TRADE_STATE_SELL_WAIT_FOR_TRADES
							)
							{
								$lastOrderPrice = number_format($lastTrade->getPrice(), 7);
								$currentPrice = number_format(1/$sample, 7);
								
								$priceChanged = $lastOrderPrice != $currentPrice;

								if ($priceChanged && $gotEnoughProfit)
								{
									$this->data->logVerbose("Price has changed (was {$lastOrderPrice}, now: {$currentPrice}) since we submitted our offer but is still enough profit; so changing our current offer.");

									$this->sell($time, $lastTrade);

									$tradeState = self::TRADE_STATE_SELL_WAIT_FOR_TRADES;
								}
								else if ($priceChanged)
								{
									$this->data->logVerbose("Price has changed (was {$lastOrderPrice}, now: {$currentPrice}) since we submitted our offer and is not enough profit; so cancelling our current offer.");

									$this->cancel($time, $lastTrade);

									$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;
								}
							}
							else if (
								$tradeState == self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT
							&&	$gotEnoughProfit
							)
							{
								// Don't care about other conditions because they where previously met
								$this->sell($time);

								$tradeState = self::TRADE_STATE_SELL_WAIT_FOR_TRADES;
							}
							else if (!$shortBelowLong)
							{
								$tradeState = self::TRADE_STATE_SELL_WAIT_POSITIVE;
							}
							else if (!$holdLongEnough)
							{
								$tradeState = self::TRADE_STATE_SELL_DELAY;
							}
							else if (!$gotEnoughProfit)
							{
								$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;
							}
						}
					break;

				default:
						exit("Unhandled tradeState: {$tradeState}");
					break;
			}
		}

		$this->data->setT($time, "baseAssetAmount", $this->getAvailableBudgetForAsset($this->settings->getBaseAsset(), false));
		$this->data->setT($time, "counterAssetAmount", $this->getAvailableBudgetForAsset($this->settings->getCounterAsset(), false));

		$this->data->set("state", $state);
		$this->data->set("tradeState", $tradeState);
		$this->data->set("startOfBuyDelayDate", $startOfBuyDelayDate ? $startOfBuyDelayDate->toString() : null);

		$this->lastProcessingTime = new Time($time);
		$this->data->set("lastProcessingTime", $this->lastProcessingTime->toString());
		
		$this->data->logVerbose("[DONE] - tradeState = {$tradeState}");

		$this->data->save();
			
		return true;
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
					exit("TODO: How did this happen? Last trade type is invalid " . __FILE__ . " on line #" . __LINE__);
				}
				else if (
					$asset->getType() == $this->settings->getBaseAsset()->getType()
				&&	$lastTrade->getType() != Trade::TYPE_SELL
				)
				{
					exit("TODO: How did this happen? Last trade type is invalid " . __FILE__ . " on line #" . __LINE__);
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
			}
			
			//var_dump($lastTrade->getData(), $budget);

			return $budget;
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
		$budget = $this->getAvailableBudgetForAsset($this->settings->getBaseAsset());

		$trade = $this->settings->getAPI()->manageOffer($this, $processingTime, $this->settings->getBaseAsset(), $budget, $this->settings->getCounterAsset());

		$lastTrade = $this->data->getLastTrade();

		if ($lastTrade)
			$trade->setPreviousBotTradeID($lastTrade->getID());

		$trade->setProcessedAt($processingTime->getDateTime());
		$this->data->addTrade($trade);

		return $trade;
	}

	function cancel(Time $processingTime, Trade $trade)
	{
		$this->sell($processingTime, $trade, true);

		$trade->setState(Trade::STATE_CANCELLED);
		$this->data->saveTrade($trade);
	}

	function sell(Time $processingTime, Trade $updateExistingTrade = null, $cancelOffer = false)
	{
		$budget = $this->getAvailableBudgetForAsset($this->settings->getCounterAsset());

		$offerIDToUpdate = $updateExistingTrade ? $updateExistingTrade->getOfferID() : null;

		$trade = $this->settings->getAPI()->manageOffer($this, $processingTime, $this->settings->getCounterAsset(), $budget, $this->settings->getBaseAsset(), $offerIDToUpdate, $cancelOffer);

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

	function predict(\GalacticBot\Time $time)
	{
		$mediumTermSamplesArray = $this->mediumTermSamples->getArray();

		// Test array
		// $mediumTermSamplesArray = []; for($i=0; $i<30; $i++) { $mediumTermSamplesArray[] = $i+1; }

		$first = $mediumTermSamplesArray[0];
		$last = $mediumTermSamplesArray[count($mediumTermSamplesArray)-1];

		$now = new \GalacticBot\Time($time);

		$this->predictionBuffer->clear();
		
		foreach($mediumTermSamplesArray AS $i => $medium) {
			if ($i >= $this->settings->getPrognosisWindowMinutes())
				continue;

			$prediction = $medium;
			$prediction -= $first;
			$prediction += $last;

			$this->predictionBuffer->add($prediction);
				
			$now->add(1);
			
			$this->data->setT($now, "prediction", $prediction);
		}

		$this->predictionDirection = forecast_direction($mediumTermSamplesArray, $this->predictionBuffer->getArray(), $this->settings->getPrognosisWindowMinutes() * 0.5, $this->settings->getPrognosisWindowMinutes());
		
		/*
		var_dump("mediumTermSamplesArray = ", $mediumTermSamplesArray, "einde");
		echo "\n\n";
		var_dump("first = $first");
		var_dump("last = $last");
		var_dump($this->predictionBuffer);
		var_dump($this->predictionDirection);
		exit();
		*/
		
		$this->data->setT($time, "predictionDirection", $this->predictionDirection);
	}

}


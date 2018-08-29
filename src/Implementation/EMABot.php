<?php

namespace GalacticBot\Implementation;

include_once dirname(__FILE__) . "/../HelperFunctions.php";

/*
* Demo Bot implemention which checks if different EMA (Exponential Moving Averages) lines to cross to see if it needs to buy or sell
*/
class EMABot extends \GalacticBot\Bot
{

	const TRADE_STATE_BUFFERING					= "BUFFERING";
	const TRADE_STATE_BUY_DELAY					= "BUY_DELAY";
	const TRADE_STATE_BUY_WAIT_NEGATIVE_TREND	= "BUY_DELAY_NEG";

	const TRADE_STATE_SELL_WAIT					= "SELL_WAIT";
	const TRADE_STATE_SELL_DELAY				= "SELL_DELAY";
	const TRADE_STATE_SELL_WAIT_POSITIVE		= "SELL_WAIT_POSITIVE";
	const TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT	= "SELL_DELAY_MINP";
	const TRADE_STATE_SELL_WAIT_FOR_TRADES		= "SELL_TRADES_WAIT";

	const TRADE_STATE_DIP_WAIT					= "DIP_WAIT";

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
	
	protected function initialize()
	{
		$this->shortTermSamples = $this->data->getS("shortTerm", $this->settings->getShortTermSampleCount());
		$this->shortTermSaleSamples = $this->data->getS("shortTermSale", $this->settings->getShortTermSaleSampleCount());
		$this->mediumTermSamples = $this->data->getS("mediumTerm", $this->settings->getMediumTermSampleCount());
		$this->longTermSamples = $this->data->getS("longTerm", $this->settings->getLongTermSampleCount());

		$this->predictionBuffer = $this->data->getS("prediction", $this->settings->getPrognosisWindowMinutes());

		$this->shortAboveMedium = $this->data->get("shortAboveMedium") == 1;
		$this->shortAboveLong = $this->data->get("shortAboveLong") == 1;
	}

	public function getTradeStateLabel($forState) {
		$counter = $this->settings->getCounterAsset()->getAssetCode();

		switch($forState)
		{
			case self::TRADE_STATE_BUFFERING:					$label = "Waiting for enough data"; break;
			case self::TRADE_STATE_NONE:						$label = "Waiting for rise to buy $counter"; break;

			case self::TRADE_STATE_BUY_DELAY:					$label = "Delaying to buy $counter"; break;
			case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:		$label = "Negative trend, wait for bottom"; break;

			case self::TRADE_STATE_SELL_WAIT:					$label = "Waiting for rise to sell $counter"; break;
			case self::TRADE_STATE_SELL_DELAY:					$label = "Delaying to sell $counter"; break;
			case self::TRADE_STATE_SELL_WAIT_POSITIVE:			$label = "Holding, waiting for short above long"; break;
			case self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT:	$label = "Delaying to sell $counter (min profit%)"; break;
			case self::TRADE_STATE_SELL_WAIT_FOR_TRADES:		$label = "Waiting for offer to fulfill"; break;

			case self::TRADE_STATE_DIP_WAIT:					$label = "Waiting for short to fall below long"; break;
		}

		return $label;
	}

	protected function process(\GalacticBot\Time $time, $sample)
	{
		/*
		// Test buy
		$this->buy($time);
		exit();
		*/

		/*
		// Manually trigger trade/offer update
		$trade = $this->data->getTradeByID(9);
		$trade->updateFromAPIForBot($this->settings->getAPI(), $this);
		exit();
		*/

		$state = $this->data->get("state");
		$tradeState = $this->data->get("tradeState");

		$this->shortTermSamples->add($sample);
		$this->data->setS("shortTerm", $this->shortTermSamples);
		$this->shortTermValue = $this->shortTermSamples->getExponentialMovingAverage();
		$this->data->setT($time, "shortTermValue", $this->shortTermValue);

		$this->shortTermSaleSamples->add($sample);
		$this->data->setS("shortTermSale", $this->shortTermSaleSamples);
		$this->shortTermSaleValue = $this->shortTermSaleSamples->getExponentialMovingAverage();
		$this->data->setT($time, "shortTermSaleValue", $this->shortTermSaleValue);

		$this->mediumTermSamples->add($sample);
		$this->data->setS("mediumTerm", $this->mediumTermSamples);
		$this->mediumTermValue = $this->mediumTermSamples->getExponentialMovingAverage();
		$this->data->setT($time, "mediumTermValue", $this->mediumTermValue);

		$this->longTermSamples->add($sample);
		$this->data->setS("longTerm", $this->longTermSamples);
		$this->longTermValue = $this->longTermSamples->getExponentialMovingAverage();
		$this->data->setT($time, "longTermValue", $this->longTermValue);

		// "Predict" next values and determine the direction of the prediction
		$this->predict($time);

		$gotFullBuffers = $this->shortTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->shortTermSaleSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->mediumTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->longTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->predictionBuffer->getIsBufferFull();

		$startOfBuyDelayDate = $this->data->get("startOfBuyDelayDate") ? \GalacticBot\Time::fromString($this->data->get("startOfBuyDelayDate")) : null;

		$lastTrade = $this->data->getLastTrade();

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
				case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:
						if ($this->predictionDirection >= 0)
						{
							$tradeState = self::TRADE_STATE_DIP_WAIT;
						}
					break;
			}
					
			switch($tradeState)
			{
				case self::TRADE_STATE_DIP_WAIT:
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
						if ($lastTrade && $lastTrade->getType() == \GalacticBot\Trade::TYPE_BUY)
						{
							// This is a temporary sanity check
							// When the bot crashes for some reason and the latest state isn't saved
							// We could end up here, we bought the counter asset but we're waiting to buy
							// So we'll correct the stat here
							$tradeState = self::TRADE_STATE_SELL_WAIT;
						}
						else if ($this->shortTermValue > $this->longTermValue && $this->shortTermSaleValue > $this->longTermValue) // Should buy?
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
										
										if (!$time->isNow() && $this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
										{
											$this->data->logWarning("Not buying based on old data (live mode).");
										}
										else if ($this->buy($time))
										{
											$tradeState = self::TRADE_STATE_SELL_WAIT;
										}
										else
										{
											$this->data->logWarning("Trade failed to create (this also happens when a bot is paused).");
											$tradeState = self::TRADE_STATE_DIP_WAIT;
										}
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

							$shortAboveLong = ($this->shortTermSaleValue >= $this->longTermValue);

							$holdLongEnough = $lastCompletedTrade->getAgeInMinutes($time) >= $this->settings->getMinimumHoldMinutes();

							$gotEnoughProfit = $currentProfitPercentage >= $this->settings->getMinimumProfitPercentage();

							if ($tradeState == self::TRADE_STATE_SELL_WAIT_POSITIVE && $shortAboveLong)
								$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;

							if ($tradeState == self::TRADE_STATE_SELL_DELAY && $holdLongEnough)
								$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;

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

									if (!$time->isNow() && $this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
									{
										$this->data->logWarning("Not selling based on old data (live mode).");
									}
									else if ($this->sell($time, $lastTrade))
									{
										$tradeState = self::TRADE_STATE_SELL_WAIT_FOR_TRADES;
									}
									else
									{
										$this->data->logWarning("Trade failed to create (this also happens when a bot is paused).");
										$tradeState = self::TRADE_STATE_DIP_WAIT;
									}
								}
								else if ($priceChanged)
								{
									$this->data->logVerbose("Price has changed (was {$lastOrderPrice}, now: {$currentPrice}) since we submitted our offer and is not enough profit; so we're leaving our offer as it is.");

									// Not canceling our offer, we'll just wait for a better price

									/*
									$this->data->logVerbose("Price has changed (was {$lastOrderPrice}, now: {$currentPrice}) since we submitted our offer and is not enough profit; so cancelling our current offer.");

									$this->cancel($time, $lastTrade);
									$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;
									*/
								}
							}
							else if (
								$tradeState == self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT
							)
							{
								if (!$gotEnoughProfit)
								{
									// Waiting for enough profit, other conditions we're met previously so wo don't have to check them anymore
								}
								else if (!$time->isNow() && $this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
								{
									$this->data->logWarning("Not selling based on old data (live mode).");
								}
								// Don't care about other conditions because they where previously met
								else if ($this->sell($time))
								{
									$tradeState = self::TRADE_STATE_SELL_WAIT_FOR_TRADES;
								}
								else
								{
									$this->data->logWarning("Trade failed to create (this also happens when a bot is paused).");
									$tradeState = self::TRADE_STATE_DIP_WAIT;
								}
							}
							else if (!$shortAboveLong)
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

						//	if ($gotEnoughProfit)
						//		exit("------ GENOEG IS GENOEG ------");
						}
					break;

				default:
						exit("Unhandled tradeState: {$tradeState}");
					break;
			}
		}

		$this->data->setT($time, "baseAssetAmount", $this->getCurrentBaseAssetBudget());
		$this->data->setT($time, "counterAssetAmount", $this->getCurrentCounterAssetBudget());
		$this->data->setT($time, "totalHoldings", $this->getTotalHoldings());

		$this->data->set("state", $state);
		$this->data->set("tradeState", $tradeState);
		$this->data->set("startOfBuyDelayDate", $startOfBuyDelayDate ? $startOfBuyDelayDate->toString() : null);
		
		$this->data->logVerbose("[DONE] - tradeState = {$tradeState}");
	}

	function predict(\GalacticBot\Time $time)
	{
		$windowSize = $this->settings->getPrognosisWindowMinutes();

		// Get the last x samples from the medium term array
		$mediumTermSamplesArray = array_slice($this->mediumTermSamples->getArray(), -$windowSize, $windowSize);

		$first = $mediumTermSamplesArray[0];
		$last = $mediumTermSamplesArray[count($mediumTermSamplesArray)-1];

		$now = new \GalacticBot\Time($time);

		$this->predictionBuffer->clear();
		
		foreach($mediumTermSamplesArray AS $i => $medium) {
			if ($i >= $windowSize)
				continue;

			$prediction = $medium;
			$prediction -= $first;
			$prediction += $last;

			$this->predictionBuffer->add($prediction);
				
			$now->add(1);
			
			$this->data->setT($now, "prediction", $prediction);
		}

		$this->predictionDirection = \GalacticBot\forecast_direction($mediumTermSamplesArray, $this->predictionBuffer->getArray(), $this->settings->getPrognosisWindowMinutes() * 0.5, $this->settings->getPrognosisWindowMinutes());
	
		$this->data->setT($time, "predictionDirection", $this->predictionDirection);
		$this->data->setS("prediction", $this->predictionBuffer);
	}

}

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
	const TRADE_STATE_BUY_PENDING				= "BUY_PENDING";

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

	protected $settingDefaults = Array(
		
		// How long to wait before buying, after all checks for buying have passed
		"buyDelayMinutes" => 0,

		// How long to wait before cancelling a buy order which doesn't get filled
		"buyFillWaitMinutes" => 5,

		// How long to hold the counter asset at minimum before even checking if we need to sell
		"minimumHoldMinutes" => 0,
		
		// How long in minutes we should try to predict the price
		// Cannot be larger than 'mediumTermSampleCount' setting
		"prognosisWindowMinutes" => 30,

		// How much profit we want at minimum, doesn't sell if this percentage isn't met
		"minimumProfitPercentage" => 0.15,

		// How many samples are taken for the short term (buy in) EMA
		"shortTermSampleCount" => 15,

		// How many samples are taken for the short term (sale) EMA
		"shortTermSaleSampleCount" => 10,

		// How many samples are taken for the medium term EMA
		"mediumTermSampleCount" => 100,

		// How many samples are taken for the long term EMA
		"longTermSampleCount" => 220,

		// Tipping point (percentage) of when to force the direction of the bot to buy or sell
		"balanceTippingPointPercentage" => 66
	);
	
	protected function initialize()
	{
		$this->shortTermSamples = $this->data->getS("shortTerm", $this->settings->get("shortTermSampleCount"));
		$this->shortTermSaleSamples = $this->data->getS("shortTermSale", $this->settings->get("shortTermSaleSampleCount"));
		$this->mediumTermSamples = $this->data->getS("mediumTerm", $this->settings->get("mediumTermSampleCount"));
		$this->longTermSamples = $this->data->getS("longTerm", $this->settings->get("longTermSampleCount"));

		$this->predictionBuffer = $this->data->getS("prediction", $this->settings->get("prognosisWindowMinutes"));

		$this->shortAboveMedium = $this->data->get("shortAboveMedium") == 1;
		$this->shortAboveLong = $this->data->get("shortAboveLong") == 1;
	}

	public function onFullReset() {
		$maxMinutes = max(
			$this->settings->get("shortTermSampleCount"),
			$this->settings->get("shortTermSaleSampleCount"),
			$this->settings->get("mediumTermSampleCount"),
			$this->settings->get("longTermSampleCount")
		);

		$maxDateBack = \GalacticBot\Time::now();
		$maxDateBack->subtract($maxMinutes, "minutes");

		$this->lastProcessingTime = $maxDateBack;
		$this->data->set("lastProcessingTime", $maxDateBack->toString());
		$this->data->set("firstProcessingTime", null);
		$this->data->directSet("firstProcessingTime", null);

		$this->shortTermSamples->clear();
		$this->data->setS("shortTerm", $this->shortTermSamples);

		$this->shortTermSaleSamples->clear();
		$this->data->setS("shortTermSale", $this->shortTermSaleSamples);

		$this->mediumTermSamples->clear();
		$this->data->setS("mediumTerm", $this->mediumTermSamples);

		$this->longTermSamples->clear();
		$this->data->setS("longTerm", $this->longTermSamples);
		
		$this->data->set("tradeState", self::TRADE_STATE_BUFFERING);
	}

	public function getTradeStateLabel($forState) {
		$counter = $this->settings->getCounterAsset()->getCode();

		if (!$counter)
			$counter = "XLM";

		$label = null;

		if (!$this->data->get("lastProcessingTime"))
			return null;

		switch($forState)
		{
			case self::TRADE_STATE_BUFFERING:					$label = "Waiting for enough data"; break;
			case self::TRADE_STATE_NONE:						$label = "None, waiting for rise to buy $counter"; break;

			case self::TRADE_STATE_BUY_DELAY:					$label = "Delaying to buy $counter"; break;
			case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:		$label = "Negative trend, wait for bottom"; break;
			case self::TRADE_STATE_BUY_PENDING:					$label = "Waiting for buy order to fill"; break;

			case self::TRADE_STATE_SELL_WAIT:					$label = "Waiting for rise to sell $counter"; break;
			case self::TRADE_STATE_SELL_DELAY:					$label = "Delaying to sell $counter"; break;
			case self::TRADE_STATE_SELL_WAIT_POSITIVE:			$label = "Holding, waiting for short above long"; break;
			case self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT:	$label = "Delaying to sell $counter (min profit%)"; break;
			case self::TRADE_STATE_SELL_WAIT_FOR_TRADES:		$label = "Waiting for offer to fulfill"; break;

			case self::TRADE_STATE_DIP_WAIT:					$label = "Waiting for short to fall below long"; break;
		}

		return $label;
	}

	protected function checkAssetFlip($sample, &$tradeState)
	{
		$lastTrade = $this->data->getLastTrade();

		if (!$lastTrade || $lastTrade->getIsFilledCompletely() || !$lastTrade->isOpen())
		{
			$baseAssetAmount = $this->getCurrentBaseAssetBudget();
			$counterAssetAmountInBase = (1/$sample) * $this->getCurrentCounterAssetBudget();

			// There are no trades, our balance can overide what our state is (trying to buy or sell)

			$total = $baseAssetAmount + $counterAssetAmountInBase;

			$baseBalancePercentage = round(10000 * ($baseAssetAmount / $total)) / 100;
			$counterBalancePercentage = round(10000 * ($counterAssetAmountInBase / $total)) / 100;

			$balanceTippingPointPercentage = $this->settings->get("balanceTippingPointPercentage");
	
			if ($total > 0)
			{
				$this->profile("Check budget balance", __FILE__, __LINE__);
				
				if ($baseBalancePercentage >= $balanceTippingPointPercentage)
				{
					switch($tradeState)
					{
						case self::TRADE_STATE_SELL_WAIT:
						case self::TRADE_STATE_SELL_DELAY:
						case self::TRADE_STATE_SELL_WAIT_POSITIVE:
						case self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT:
						case self::TRADE_STATE_SELL_WAIT_FOR_TRADES:
								$this->data->logVerbose("Current asset balance is: base {$baseBalancePercentage}% and counter {$counterBalancePercentage}%. We've crossed the tipping point with the base balance. We're flipping our state to be able to buy the counter asset.");
								$tradeState = self::TRADE_STATE_BUY_DELAY;
							break;
					}
				}
				else if ($counterBalancePercentage >= $balanceTippingPointPercentage)
				{
					switch($tradeState)
					{
						case self::TRADE_STATE_DIP_WAIT:
						case self::TRADE_STATE_BUY_DELAY:
						case self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND:
						case self::TRADE_STATE_BUY_PENDING:
								$this->data->logVerbose("Current asset balance is: base {$baseBalancePercentage}% and counter {$counterBalancePercentage}%. We've crossed the tipping point with the counter balance. We're flipping our state to be able to buy the base asset.");
								$tradeState = self::TRADE_STATE_SELL_WAIT;
							break;
					}
				}
			}
		}
	}

	protected function process(\GalacticBot\Time $time, $sample) {
		/*
		// Test buy
		if ($time->isNow()) {
			$trade = $this->sell($time);
			$trade->updateFromAPIForBot($this);
			exit();
		}
		*/
		
		$this->profile("Read state and write to buffers", __FILE__, __LINE__);

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

		$this->profile("\"Predict\" next values and determine the direction of the prediction", __FILE__, __LINE__);
		$this->predict($time);

		$gotFullBuffers = $this->shortTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->shortTermSaleSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->mediumTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->longTermSamples->getIsBufferFull();
		$gotFullBuffers = $gotFullBuffers && $this->predictionBuffer->getIsBufferFull();

		$startOfBuyDelayDate = $this->data->get("startOfBuyDelayDate") ? \GalacticBot\Time::fromString($this->data->get("startOfBuyDelayDate")) : null;
		
		$this->profile("Update trade information", __FILE__, __LINE__);

		$lastTrade = $this->data->getLastTrade();

		$lastCompletedTrade = $this->data->getLastCompletedTrade();

		$this->data->logVerbose("- state = {$state}, tradeState = {$tradeState}");

		$this->profileEndTask();

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
			//exit();
		}
		else
		{
			$this->checkAssetFlip($sample, $tradeState);
			
			$this->profile("Act on trade state and current sample value", __FILE__, __LINE__);

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
				case self::TRADE_STATE_BUY_PENDING:
						if ($lastTrade && $lastTrade->getIsFilledCompletely())
						{
							$tradeState = self::TRADE_STATE_SELL_WAIT;
						}
						else if (!$lastTrade)
						{
							$this->data->logWarning("We where waiting for a buy order to complete, but the order is gone! Resetting our state.");
							$tradeState = self::TRADE_STATE_NONE;
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
				case self::TRADE_STATE_BUY_PENDING:
				case "":
						if ($tradeState != self::TRADE_STATE_BUY_PENDING && $lastTrade && $lastTrade->getType() == \GalacticBot\Trade::TYPE_BUY)
						{
							// This is a temporary sanity check
							// When the bot crashes for some reason and the latest state isn't saved we could end up here
							$tradeState = self::TRADE_STATE_BUY_PENDING;
						}

						if ($this->shortTermValue > $this->longTermValue && $this->shortTermSaleValue > $this->longTermValue) // Should buy?
						{
							if (!$startOfBuyDelayDate)
								$startOfBuyDelayDate = clone $time;

							// Buy
							if ($tradeState == self::TRADE_STATE_NONE) {
								$tradeState = self::TRADE_STATE_BUY_DELAY;
							}
								
							if ($tradeState == self::TRADE_STATE_BUY_DELAY || $tradeState == self::TRADE_STATE_BUY_PENDING) {
								if (
									$startOfBuyDelayDate->getAgeInMinutes($time) >= $this->settings->get("buyDelayMinutes")
								||	$tradeState == self::TRADE_STATE_BUY_PENDING
								) {
									if ($this->predictionDirection >= 0) {
										$startOfBuyDelayDate = null;
										
										if (!$time->isNow() && $this->getSettings()->getType() == self::SETTING_TYPE_LIVE)
										{
											$this->data->logWarning("Not buying based on old data (live mode).");
										}
										else if ($tradeState == self::TRADE_STATE_BUY_PENDING)
										{
											$this->data->logVerbose("We are waiting for an buy order to complete, but the price has changed lets update our order.");
										
											$lastOrderPrice = number_format($lastTrade->getPrice(), 7);
											$currentPrice = number_format(1/$sample, 7);
								
											$priceChanged = $lastOrderPrice != $currentPrice;

											if ($priceChanged)
											{
												$this->buy($time, $lastTrade, null, 1/$sample);

												$tradeState = self::TRADE_STATE_BUY_PENDING;

												$this->data->logVerbose("Buy order changed.");
											}
											else if ($lastTrade && $lastTrade->getAgeInMinutes($time) > $this->settings->get("buyFillWaitMinutes"))
											{
												if ($lastTrade->isOpen())
												{
													$this->data->logVerbose("Trade #{$lastTrade->getID()} is too old, lets assume no one is going to fill this and return to our previous state.");

													$this->cancel($time, $lastTrade);
												}

												$tradeState = self::TRADE_STATE_NONE;

												$this->checkAssetFlip($sample, $tradeState);
											}
										}
										else if ($this->buy($time))
										{
											$tradeState = self::TRADE_STATE_BUY_PENDING;
										}
										else
										{
											$this->data->logWarning("Trade #{$lastTrade->getID()} failed to create (this also happens when a bot is paused).");
											$tradeState = self::TRADE_STATE_DIP_WAIT;
										}
									} else /* negative trend: $this->predictionDirection < 0 */ {
										if ($tradeState == self::TRADE_STATE_BUY_PENDING)
										{
											$this->data->logVerbose("Cancel trade #{$lastTrade->getID()}, we're in a negative trend now.");

											$this->cancel($time, $lastTrade);

											$tradeState = self::TRADE_STATE_NONE;

											$this->checkAssetFlip($sample, $tradeState);
										}
										else
										{
											$tradeState = self::TRADE_STATE_BUY_WAIT_NEGATIVE_TREND;
										}
									}
								}
								else if ($lastTrade && $lastTrade->getAgeInMinutes($time) > $this->settings->get("buyFillWaitMinutes"))
								{
									if ($lastTrade->isOpen())
									{
										$this->data->logVerbose("Trade #{$lastTrade->getID()} is too old, lets assume no one is going to fill this and return to our previous state.");

										$this->cancel($time, $lastTrade);
									}

									$tradeState = self::TRADE_STATE_NONE;

									$this->checkAssetFlip($sample, $tradeState);
								}
							}
						}
						else if ($tradeState == self::TRADE_STATE_BUY_PENDING)
						{
							if ($lastTrade && $lastTrade->getAgeInMinutes($time) > $this->settings->get("buyFillWaitMinutes"))
							{
								if ($lastTrade->isOpen())
								{
									$this->data->logWarning("Trade #{$lastTrade->getID()} is too old, lets assume no one is going to fill this and return to our previous state.");

									$this->cancel($time, $lastTrade);
								}

								$tradeState = self::TRADE_STATE_NONE;
								
								$this->checkAssetFlip($sample, $tradeState);
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

							$holdLongEnough = $lastCompletedTrade->getAgeInMinutes($time) >= $this->settings->get("minimumHoldMinutes");

							$gotEnoughProfit = $currentProfitPercentage >= $this->settings->get("minimumProfitPercentage");

							if ($tradeState == self::TRADE_STATE_SELL_WAIT_POSITIVE && $shortAboveLong)
								$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;

							if ($tradeState == self::TRADE_STATE_SELL_DELAY && $holdLongEnough)
								$tradeState = self::TRADE_STATE_SELL_WAIT_MINIMUM_PROFIT;

							if (
								$tradeState == self::TRADE_STATE_SELL_WAIT_FOR_TRADES
							)
							{
								if ($lastTrade->getType() == \GalacticBot\Trade::TYPE_SELL)
									$lastOrderPrice = number_format(1/$lastTrade->getPrice(), 7);
								else
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
									else if ($this->sell($time, $lastTrade, null, 1/$currentPrice))
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

			$this->profileEndTask();
		}

		$this->profile("Save state", __FILE__, __LINE__);

		$this->data->set("state", $state);
		$this->data->set("tradeState", $tradeState);
		$this->data->set("startOfBuyDelayDate", $startOfBuyDelayDate ? $startOfBuyDelayDate->toString() : null);
		
		$this->data->logVerbose("[DONE] - tradeState = {$tradeState}");

		$this->profileEndTask();
	}

	function predict(\GalacticBot\Time $time)
	{
		$windowSize = $this->settings->get("prognosisWindowMinutes");

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
			
			// Do not save prediction (is slow when doing simulations or when processing a backbuffer)
			//$this->data->setT($now, "prediction", $prediction);
		}

		$this->predictionDirection = \GalacticBot\forecast_direction($mediumTermSamplesArray, $this->predictionBuffer->getArray(), $this->settings->get("prognosisWindowMinutes") * 0.5, $this->settings->get("prognosisWindowMinutes"));

		$this->predictionDirection *= -1;
	
		$this->data->setT($time, "predictionDirection", $this->predictionDirection);
		$this->data->setS("prediction", $this->predictionBuffer);
	}

}

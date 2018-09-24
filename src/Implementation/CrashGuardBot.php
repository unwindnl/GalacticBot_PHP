<?php

namespace GalacticBot\Implementation;

include_once dirname(__FILE__) . "/../HelperFunctions.php";

/*
* Buys in a counter asset and sells when a crash is detected. Buys in again when the price is going up for a specified amount of time.
*/
class CrashGuardBot extends \GalacticBot\Bot
{

	const TRADE_STATE_BUFFERING					= "BUFFERING";

	const TRADE_STATE_WAIT_BUY_ORDER			= "WAIT_BUY_ORDER";

	const TRADE_STATE_TRACK_DIP					= "TRACK_DIP";
	
	const TRADE_STATE_WAIT_SELL_ORDER			= "WAIT_SELL_ORDER";

	const TRADE_STATE_WAIT_FOR_RISE				= "WAIT_FOR_RISE";
	const TRADE_STATE_WAIT_FOR_RISE_HOLD		= "WAIT_FOR_RISE_HOLD";

	private $movingAverageSamples = null;

	protected $settingDefaults = Array(
		"paidPriceSampleCount" => 120,
		"currentPriceSampleCount" => 120,

		"maximumLossPercentage" => 1,

		"minimumRisePercentage" => 2,
		"minimumRiseTimeMinutes" => 5,
	);
		
	protected function initialize()
	{
		$this->paidPriceEMASamples = $this->data->getS("paidPriceEMASamples", $this->settings->get("paidPriceSampleCount"));
		$this->currentPriceEMASamples = $this->data->getS("currentPriceEMASamples", $this->settings->get("currentPriceSampleCount"));

		$this->outOfDipDate = $this->data->get("outOfDipDate", null);

		if ($this->outOfDipDate)
			$this->outOfDipDate = \GalacticBot\Time::fromString($this->outOfDipDate);
	}

	public function getTradeStateLabel($forState) {
		$counter = $this->settings->getCounterAsset()->getAssetCode();

		switch($forState)
		{
			case self::TRADE_STATE_BUFFERING:					$label = "Waiting for enough data"; break;
			case self::TRADE_STATE_NONE:						$label = "Gaining profit (" . number_format($this->data->get("differencePercentage"), 2) . "%), waiting for a dip"; break;

			case self::TRADE_STATE_WAIT_BUY_ORDER:				$label = "Waiting for buy order to complete"; break;

			case self::TRADE_STATE_TRACK_DIP:					$label = "Tracking current dip (" . number_format($this->data->get("differencePercentage"), 2) . "%), stay below loss %"; break;

			case self::TRADE_STATE_WAIT_SELL_ORDER:				$label = "Waiting for sell order to complete"; break;
			
			case self::TRADE_STATE_WAIT_FOR_RISE:				$label = "Waiting for rise  (" . number_format($this->data->get("differencePercentage"), 2) . "%)"; break;
			case self::TRADE_STATE_WAIT_FOR_RISE_HOLD:			$label = "Waiting for rise to hold long enough"; break;
		}

		return null;
	}

	protected function process(\GalacticBot\Time $time, $sample)
	{
		$state = $this->data->get("state");
		$tradeState = $this->data->get("tradeState");
		
		$lastTrade = $this->data->getLastTrade();
		$lastCompletedTrade = $this->data->getLastCompletedTrade();

		$currentPrice = $sample ? 1/$sample : null;

		$this->data->logVerbose("- state = {$state}, tradeState = {$tradeState}");

		if ($sample === null)
		{
			$this->data->logWarning("No sample data received, not processing this timeframe.");
			$this->data->save();
			exit();
		}
		else
		{
			switch($tradeState)
			{
				case self::TRADE_STATE_WAIT_BUY_ORDER:
						if ($lastTrade->getIsFilledCompletely())
						{
							$tradeState = self::TRADE_STATE_NONE;

							$this->paidPriceEMASamples->fillWithValue($lastTrade->getPaidPrice());
						}
						else
						{
							// TODO: adjust price
						}
					break;

				case self::TRADE_STATE_WAIT_SELL_ORDER:
						if ($lastTrade->getIsFilledCompletely())
						{
							$tradeState = self::TRADE_STATE_WAIT_FOR_RISE;
							
							$this->currentPriceEMASamples->fillWithValue($currentPrice);
						}
						else
						{
							// TODO: adjust price
						}
					break;
			}
			
			$this->paidPriceEMAValue = $this->paidPriceEMASamples->getExponentialMovingAverage();
		
			$differencePercentage = 0;
			$paidPrice = null;
			
			if ($lastCompletedTrade)
			{
				$paidPrice = $lastCompletedTrade->getPrice();

				if ($lastCompletedTrade->getType() == \GalacticBot\Trade::TYPE_BUY)
				{
					// Get closer to the current price when the current price is higher than we (averaged) paid
					if ($currentPrice >= $this->paidPriceEMAValue) // $this->paidPriceEMAValue)
					{
						$this->paidPriceEMASamples->add($currentPrice);
						$this->paidPriceEMAValue = $this->paidPriceEMASamples->getExponentialMovingAverage();
					}
				
					$this->data->setT($time, "paidPriceEMAValue", $this->paidPriceEMAValue);
					$differencePercentage = 100 * (($currentPrice / $this->paidPriceEMAValue) - 1);
				}
				else
				{
					if ($currentPrice <= $this->currentPriceEMAValue)
					{
						$this->currentPriceEMASamples->add($currentPrice);
						$this->data->setS("currentPriceEMASamples", $this->currentPriceEMASamples);
						$this->currentPriceEMAValue = $this->currentPriceEMASamples->getExponentialMovingAverage();
					}

					$this->data->setT($time, "currentPriceEMAValue", $this->currentPriceEMAValue);
					$differencePercentage = 100 * (($currentPrice / $this->currentPriceEMAValue) - 1);
				}
			}

			switch($tradeState)
			{
				case "":
				case self::TRADE_STATE_NONE:
				case self::TRADE_STATE_TRACK_DIP:
						$this->outOfDipDate = null;
						$tradeState = self::TRADE_STATE_NONE;
	
						if ($lastTrade)
						{
							if ($differencePercentage <= -$this->settings->get("maximumLossPercentage"))
							{
								if ($this->sell($time))
								{
									$tradeState = self::TRADE_STATE_WAIT_SELL_ORDER;
								}
							}
							else if ($differencePercentage < 0)
							{
								$tradeState = self::TRADE_STATE_TRACK_DIP;
							}
						}
						else if (!$lastTrade)
						{
							// We need to but the counter asset
							if ($this->buy($time))
							{
								$tradeState = self::TRADE_STATE_WAIT_BUY_ORDER;
							}
						}
					break;

				case self::TRADE_STATE_WAIT_FOR_RISE:
				case self::TRADE_STATE_WAIT_FOR_RISE_HOLD:
						if ($differencePercentage >= $this->settings->get("minimumRisePercentage"))
						{
							if (!$this->outOfDipDate)
							{
								$this->outOfDipDate = new \GalacticBot\Time($time);

								$this->data->logVerbose("We're out of the dip. Now wait for it to hold long enough to buy in again.");
							}
							else if ($this->outOfDipDate->getAgeInMinutes($time) >= $this->settings->get("minimumRiseTimeMinutes"))
							{
								$this->data->logVerbose("The rise is holding long enough, we can buy in again.");

								if ($this->buy($time))
								{
									$this->outOfDipDate = null;
									$tradeState = self::TRADE_STATE_WAIT_BUY_ORDER;
								}
							}
							else
							{
							}
						}
						else if ($tradeState == self::TRADE_STATE_WAIT_FOR_RISE_HOLD)
						{
							$this->outOfDipDate = null;
							$tradeState = self::TRADE_STATE_WAIT_FOR_RISE;
						}
					break;
	
				default:
						exit("Unhandled tradeState: {$tradeState}");
					break;
			}
		}

		$this->data->set("differencePercentage", $differencePercentage);
		
		$this->data->set("outOfDipDate", $this->outOfDipDate ? $this->outOfDipDate->toString() : null);
		
		$this->data->logVerbose("[DONE] - tradeState = {$tradeState}");
	}

}

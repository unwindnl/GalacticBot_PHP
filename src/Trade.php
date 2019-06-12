<?php

namespace GalacticBot;

/*
* A Trade is an offering on the Stellar network to trade an asset for a fixed price
*/
class Trade
{

	private $ID, $previousBotTradeID, $type, $state;
	
	private $offerID, $claimedOffers;
	
	private $sellAmount, $spentAmount, $amountRemaining, $boughtAmount, $price, $priceN, $priceD, $paidPrice, $fee;
	
	private $fillPercentage;

	private $createdAt, $processedAt, $updatedAt;

	const TYPE_BUY						= "BUY";
	const TYPE_SELL						= "SELL";

	const STATE_CREATED					= "CREATED";
	const STATE_REPLACED				= "REPLACED";
	const STATE_CANCELLED				= "CANCELLED";
	const STATE_FILLED					= "FILLED";

	function __construct()
	{
	}

	function isOpen()
	{
		return $this->state == self::STATE_CREATED;
	}

	function setData(Array $data)
	{
		$vars = get_object_vars($this);

		foreach($vars AS $k => $v)
			$this->$k = $data[$k];
	}

	function getData()
	{
		$data = [];
		$vars = get_object_vars($this);

		foreach($vars AS $k => $v)
			$data[$k] = $this->$k;

		return $data;
	}

	function getID() { return $this->ID; }
	function setID($ID) { $this->ID = $ID; }

	function getState() { return $this->state; }

	function getStateInfo() {
		$state = $this->getState();
		$label = "Unknown state ($state)";

		switch($state)
		{
			case self::STATE_CREATED:							$label = "Created"; break;
			case self::STATE_REPLACED:							$label = "Replaced"; break;
			case self::STATE_CANCELLED:							$label = "Cancelled"; break;
			case self::STATE_FILLED:							$label = "Filled"; break;
		}

		return Array(
			"state" => $state,
			"label" => $label
		);
	}

	function setState($state) { $this->state = $state; }

	function setProcessedAt(\DateTime $date) { $this->processedAt = $date->format("Y-m-d H:i:s"); }

	function setPreviousBotTradeID($previousBotTradeID) { $this->previousBotTradeID = $previousBotTradeID; }
	function getPreviousBotTradeID() { return $this->previousBotTradeID; }

	function getOfferID() { return $this->offerID; }

	function getType() { return $this->type; }
		
	function getPrice() { return $this->price; }
	function getSellAmount() { return $this->sellAmount; }
	function getPaidPrice() { return $this->paidPrice; }
	function getBoughtAmount() { return $this->boughtAmount; }
	function getSpentAmount() { return $this->spentAmount; }
	function getAmountRemaining() { return $this->amountRemaining; }

	function getCreatedAt() { global $_BASETIMEZONE; return new \DateTime($this->createdAt, $_BASETIMEZONE); }
	function getUpdatedAt() { global $_BASETIMEZONE; return new \DateTime($this->updatedAt, $_BASETIMEZONE); }
	function getProcessedAt() { return new \DateTime($this->processedAt); }

	function getFillPercentage() { return $this->fillPercentage; }

	function getIsFilledCompletely()
	{
		return $this->state == self::STATE_FILLED;
	}

	function getAgeInMinutes(Time $time)
	{
		$processedAt = Time::fromDateTime($this->getProcessedAt());
		return $processedAt->getAgeInMinutes($time);
	}
	
	function simulate($type, Bot $bot, Time $processingTime, \ZuluCrypto\StellarSdk\XdrModel\Asset $sellingAsset, $sellingAmount, \ZuluCrypto\StellarSdk\XdrModel\Asset $buyingAsset)
	{
		$price = $bot->getDataInterface()->getAssetValueForTime($processingTime);

		$this->type = $type;
		$this->offerID = "SIM_" . time();

		$this->amountRemaining = 0;
		$this->price = 1/$price;
		$this->paidPrice = $this->price;
		$this->fillPercentage = 100;
		$this->state = self::STATE_FILLED;

		$this->processedAt = $processingTime->toString();

		$claimedOffers = Array();
	
		if ($type == self::TYPE_BUY)
		{
			$this->sellAmount = $sellingAmount;
			$this->fee = 0.00001;
			$this->spentAmount = $sellingAmount + $this->fee;

			$claimedOffers[] = Array(
				"offerID" => $this->offerID,

				"sellingAssetType" => $buyingAsset->getType(),
				"sellingAssetCode" => $buyingAsset->getCode(),

				"sellingAmount" => $this->sellAmount * $price,

				"buyingAssetType" => $sellingAsset->getType(),
				"buyingAssetCode" => $sellingAsset->getCode(),
			);
		}
		else
		{
			$this->sellAmount = $sellingAmount;
			$this->fee = 0.00001;
			$this->spentAmount = $sellingAmount + $this->fee;

			$claimedOffers[] = Array(
				"offerID" => $this->offerID,

				"sellingAssetType" => $sellingAsset->getType(),
				"sellingAssetCode" => $sellingAsset->getCode(),

				"sellingAmount" => $sellingAmount * (1/$price),

				"buyingAssetType" => $buyingAsset->getType(),
				"buyingAssetCode" => $buyingAsset->getCode(),
			);
		}
		
		$this->boughtAmount = $claimedOffers[0]["sellingAmount"];

		$this->claimedOffers = json_encode($claimedOffers);
	}

	function addCompletedHorizonTradeForBot(\GalacticHorizon\Trade $trade, $bot)
	{
		if ($this->state == self::STATE_FILLED)
			return;

			$claimedOffers = @json_decode($this->claimedOffers);

			if (!$claimedOffers)
				$claimedOffers = [];
			else
				$claimedOffers = (Array)$claimedOffers;

			if (1)
			{
				$baseIsBot = $trade->getBaseAccount()->getPublicKey() == $bot->getSettings()->getAccountPublicKey();
				$otherAccountID = $baseIsBot ? $trade->getCounterAccount()->getPublicKey() : $trade->getBaseAccount()->getPublicKey();
				$uniqueID = $trade->getOfferID() . "_" . $trade->getCounterOfferID() . "_" . $otherAccountID . "_" . number_format($trade->getCounterAmount()->toFloat(), 7);

				$info = Array(
					"offerID" => $trade->getOfferID(),
					"counterOfferID" => $trade->getCounterOfferID(),
				);

				if ($baseIsBot)
				{
					$info["sellingAssetType"] = $trade->getBaseAsset()->getType();
					$info["sellingAssetCode"] = $trade->getBaseAsset()->getCode();

					$info["counterAssetType"] = $trade->getCounterAsset()->getType();
					$info["counterAssetCode"] = $trade->getCounterAsset()->getCode();

					$info["price"] = 1/$trade->getPrice()->toFloat();

					$info["sellingAmount"] = number_format((1/$trade->getPrice()->toFloat()) * $trade->getCounterAmount()->toFloat(), 7);
				}
				else
				{
					$info["counterAssetType"] = $trade->getBaseAsset()->getType();
					$info["counterAssetCode"] = $trade->getBaseAsset()->getCode();

					$info["sellingAssetType"] = $trade->getCounterAsset()->getType();
					$info["sellingAssetCode"] = $trade->getCounterAsset()->getCode();

					$info["price"] = 1/$trade->getPrice()->toFloat();

					$info["sellingAmount"] = $trade->getCounterAmount()->toFloat();
				}

				$claimedOffers[$uniqueID] = (object)$info;
			}

			$this->claimedOffers = json_encode($claimedOffers);

			$this->amountRemaining = $this->sellAmount;
			$this->boughtAmount = 0;
			$paidPrices = [];
	
			foreach($claimedOffers AS $offer)
			{
				$this->amountRemaining -= $offer->sellingAmount;

				if ($this->type == self::TYPE_BUY)
					$this->boughtAmount += $offer->sellingAmount * (1/$offer->price);
				else
					$this->boughtAmount += $offer->sellingAmount * ($offer->price);

				$paidPrices[] = $offer->price;
			}

			$this->amountRemaining = number_format($this->amountRemaining, 7);
			$this->boughtAmount = number_format($this->boughtAmount, 7);

			$percentage = 100 * (1-($this->amountRemaining / $this->sellAmount));

			$this->fillPercentage = round($percentage * 100) / 100; // Two decimals

			// Not really correct to just take an average of all paid prices
			$this->paidPrice = array_average($paidPrices);
			
			if ($this->fillPercentage >= 99.99) {
				// Make sure the offer is closed
				$bot->cancel(\GalacticBot\Time::now(true), $this, null);

				$this->state = self::STATE_FILLED;
			}
		
			$bot->getDataInterface()->saveTrade($this);
	}

	function getTradeInfo($trade, $fromTransactionResult = false) {
		$o = (object)Array(
			"offerID" => $this->offerID,
		);

		if ($fromTransactionResult) {
			$o->sellingAssetType = $trade->getAssetSold()->getType();
			$o->sellingAssetCode = $trade->getAssetSold()->getCode();

			$o->buyingAssetType = $trade->getAssetBought()->getType();
			$o->buyingAssetCode = $trade->getAssetBought()->getCode();
			
			$o->sellingAmount = $this->type == self::TYPE_BUY ? $trade->getAmountSold()->getScaledValue() : $trade->getAmountBought()->getScaledValue();
		} else {
			$o->sellingAssetType = $trade->getCounterAsset()->getType();
			$o->sellingAssetCode = $trade->getCounterAsset()->getCode();

			$o->buyingAssetType = $trade->getBaseAsset()->getType();
			$o->buyingAssetCode = $trade->getBaseAsset()->getCode();
			
			$o->sellingAmount = $this->type == self::TYPE_BUY ? $trade->getCounterAmount() : $trade->getBaseAmount();
			
			$o->price = $trade->getPrice();
		}

		return $o;
	}

	static function fromGalacticHorizonOperationResponseAndResultForBot(
		\GalacticHorizon\ManageOfferOperation $operation,
		\GalacticHorizon\TransactionResult $response,
		\GalacticHorizon\ManageOfferOperationResult $result,
		$transactionEnvelopeXdrString,
		$paidFee,
		\GalacticBot\Bot $bot
	) {
		global $_BASETIMEZONE;

		$now = new \DateTime(null, $_BASETIMEZONE);

		$o = new self();
		$o->state = self::STATE_CREATED;
		$o->transactionEnvelopeXdr = $transactionEnvelopeXdrString;
		$o->ID = null;
		$o->createdAt = $now->format("Y-m-d H:i:s");
		$o->offerID = $result->getOffer() ? $result->getOffer()->getOfferID() : null;
		$o->hash = $response ? $response->getHash() : null;

		$o->claimedOffers = json_encode([]);

		if ($bot->baseAssetIsNative()) {
			$o->type = $operation->getSellingAsset()->getType() == \GalacticHorizon\Asset::TYPE_NATIVE ? self::TYPE_BUY : self::TYPE_SELL;
		} else {
			$o->type = $operation->getSellingAsset()->getType() == \GalacticHorizon\Asset::TYPE_NATIVE ? self::TYPE_SELL : self::TYPE_BUY;
		}

		$o->priceN = $operation->getPrice()->getNumerator();
		$o->priceD = $operation->getPrice()->getDenominator();

		if ($o->type == self::TYPE_BUY)
			$o->price = $o->priceD / $o->priceN;
		else
			$o->price = $o->priceN / $o->priceD;

		if ($o->type == self::TYPE_BUY)
			$o->sellAmount = $operation->getAmount()->toFloat();
		else
			$o->sellAmount = $operation->getAmount()->toFloat();

		$o->fee = $paidFee;
		
		$o->spentAmount = $o->fee;

		$o->fillPercentage = 0;

		return $o;
	}

}


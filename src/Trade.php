<?php

namespace GalacticBot;

/*
* A Trade is an offering of an asset which is fulfilled or is not fulfilled.
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
		$date = new \DateTime();
		$this->createdAt = $date->format("Y-m-d H:i:s");
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

	function getCreatedAt() { return new \DateTime($this->createdAt); }
	function getProcessedAt() { return new \DateTime($this->processedAt); }

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
				"sellingAssetCode" => $buyingAsset->getAssetCode(),

				"sellingAmount" => $this->sellAmount * $price,

				"buyingAssetType" => $sellingAsset->getType(),
				"buyingAssetCode" => $sellingAsset->getAssetCode(),
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
				"sellingAssetCode" => $sellingAsset->getAssetCode(),

				"sellingAmount" => $sellingAmount * (1/$price),

				"buyingAssetType" => $buyingAsset->getType(),
				"buyingAssetCode" => $buyingAsset->getAssetCode(),
			);
		}
		
		$this->boughtAmount = $claimedOffers[0]["sellingAmount"];

		$this->claimedOffers = json_encode($claimedOffers);

		//var_dump($this);
		//exit();
		
		if ($type == self::TYPE_SELL)
		{
	//		var_dump($this);
	//		exit();
		}
	}

	function updateFromAPIForBot(StellarAPI $api, Bot $bot)
	{
		if (!$this->offerID && $this->claimedOffers) {
			$claimedOffers = json_decode($this->claimedOffers);

			foreach($claimedOffers AS $offer)
				if ($offer->offerID)
					$this->offerID = $offer->offerID;
		}

		if ($this->offerID) {
			$offerInfo = $api->getOfferInfoByID($bot, $this->offerID);

			if ($offerInfo["isOpen"])
			{
				$bot->getDataInterface()->logVerbose("Trade #{$this->ID} (offerID: #{$this->offerID}) is not fulfilled yet.");

				return;
			}
			else
			{
				$claimedOffers = [];

				foreach($offerInfo["trades"] AS $trade)
				{
					$claimedOffers[] = Array(
						"offerID" => $this->offerID,

						"sellingAssetType" => $trade->getCounterAsset()->getType(),
						"sellingAssetCode" => $trade->getCounterAsset()->getAssetCode(),

						"sellingAmount" => $this->type == self::TYPE_BUY ? $trade->getCounterAmount() : $trade->getBaseAmount(),

						"buyingAssetType" => $trade->getBaseAsset()->getType(),
						"buyingAssetCode" => $trade->getBaseAsset()->getAssetCode(),
					);
				}
			
				$this->claimedOffers = json_encode($claimedOffers);
		
				$bot->getDataInterface()->saveTrade($this);
			}
		}

		if ($this->claimedOffers)
		{
			if ($this->type == self::TYPE_BUY)
				$amountTotal = $this->sellAmount * 1/$this->price;
			else
				$amountTotal = $this->sellAmount * $this->price;

			$amountTotal = (float)number_format($amountTotal, 7, '.', '') - $this->fee;
			$amountLeft = $amountTotal;

			$claimedOffers = json_decode($this->claimedOffers);

			$this->boughtAmount = 0;

			foreach($claimedOffers AS $offer)
			{
				$this->boughtAmount += $offer->sellingAmount;
				$amountLeft -= $offer->sellingAmount;
			}

			$amountFulfilled = $amountTotal-$amountLeft;

			$fillPercentage = $amountFulfilled / $amountTotal;

			$this->fillPercentage = round($fillPercentage * 100 * 100) / 100;
		
			if ($this->fillPercentage >= 99.999)
				$this->state = self::STATE_FILLED;
	
			if ($this->type == self::TYPE_BUY)
				$this->spentAmount = number_format($this->fee + ($this->boughtAmount * $this->price), 7);
			else
				$this->spentAmount = number_format($this->fee + $this->boughtAmount, 7);

			$this->amountRemaining = number_format($amountLeft, 7);
			
			if ($this->type == self::TYPE_BUY)
				$this->paidPrice = number_format(1 / ($amountFulfilled / $this->spentAmount), 7);		
			else
				$this->paidPrice = number_format($this->spentAmount / $this->sellAmount, 7);		
		}
		else
		{
			exit("invalid trade");
		}

		$bot->getDataInterface()->saveTrade($this);
	}

	static function fromHorizonOperationAndResult(
		\ZuluCrypto\StellarSdk\XdrModel\Operation\ManageOfferOp $operation,
		\ZuluCrypto\StellarSdk\XdrModel\ManageOfferResult $result,
		$transactionEnvelopeXdrString,
		$paidFee
	)
	{
		$o = new self();
		$o->state = self::STATE_CREATED;
		$o->transactionEnvelopeXdr = $transactionEnvelopeXdrString;
		$o->ID = null;

		$o->offerID = $result->getOffer() ? $result->getOffer()->getOfferId() : null;

		$claimedOfferList = $result->getClaimedOffers();
		$claimedOffers = [];

		if ($claimedOfferList)
		{
			foreach($claimedOfferList AS $offer)
			{
				$claimedOffers[] = Array(
					"offerID" => $offer->getOfferId(),

					"sellingAssetType" => $offer->getAssetSold()->getType(),
					"sellingAssetCode" => $offer->getAssetSold()->getAssetCode(),
					"sellingAmount" => $offer->getAmountSold()->getScaledValue(),

					"buyingAssetType" => $offer->getAssetBought()->getType(),
					"buyingAssetCode" => $offer->getAssetBought()->getAssetCode(),
				);
			}
		}

		$o->type = $operation->getSellingAsset()->getType() == \ZuluCrypto\StellarSdk\XdrModel\Asset::TYPE_NATIVE ? self::TYPE_BUY : self::TYPE_SELL;

		$o->claimedOffers = json_encode($claimedOffers);

		$o->priceN = $operation->getPrice()->getNumerator();
		$o->priceD = $operation->getPrice()->getDenominator();

		if ($o->type == self::TYPE_BUY)
			$o->price = $o->priceD / $o->priceN;
		else
			$o->price = $o->priceN / $o->priceD;

		if ($o->type == self::TYPE_BUY)
			$o->sellAmount = $operation->getAmount()->getScaledValue();
		else
			$o->sellAmount = $operation->getAmount()->getScaledValue() * (1/$o->price);

		$o->fee = $paidFee;
		
		$o->spentAmount = $o->fee;

		$o->fillPercentage = 0;

		return $o;
	}

}


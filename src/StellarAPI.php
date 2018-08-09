<?php

namespace GalacticBot;

	/* TODO: Check on trustlines */

	class StellarAPI {

		const INTERVAL_MINUTE = 60000;
		const INTERVAL_FIVEMINUTES = 300000;
		const INTERVAL_FIFTEENMINUTES = 900000;

		private $URL;
	
		private $isTestNet;

		private function __construct($URL, $isTestNet) {
			$this->URL = $URL;
			$this->isTestNet = $isTestNet;
		}

		public static function getTestNetAPI() {
			return new self("https://horizon-testnet.stellar.org/", true);
		}

		public static function getPublicAPI() {
			return new self("https://horizon.stellar.org/", false);
		}

		function getIsTestNet() { return $this->isTestNet; }

		function get($path, Array $arguments) {
			$curl = curl_init();

			$path .= "?" . http_build_query($arguments);
			
			curl_setopt_array($curl, array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL => $this->URL . $path
			));

			$result = curl_exec($curl);

			return @json_decode($result);
		}

		static function getPublicOrderBook(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $sellingAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $buyingAsset, $limit = null) {
			$api = new self("https://horizon.stellar.org/", false);

			return $api->getOrderBook($bot, $sellingAsset, $buyingAsset, $limit);
		}

		function getOrderBook(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $sellingAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $buyingAsset, $limit = null) {
			$server = $this->isTestNet ? \ZuluCrypto\StellarSdk\Server::testNet() : \ZuluCrypto\StellarSdk\Server::publicNet();
			$server = new ExtendedServer($server, $this->isTestNet);

			$keypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($bot->getSettings()->getAccountSecret());
		
			$result = $server->getOrderBook($sellingAsset, $buyingAsset, $limit);

			return $result;
		}

		function getOfferInfoByID(Bot $bot, $offerID) {
			$server = $this->isTestNet ? \ZuluCrypto\StellarSdk\Server::testNet() : \ZuluCrypto\StellarSdk\Server::publicNet();
			$server = new ExtendedServer($server, $this->isTestNet);

			$keypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($bot->getSettings()->getAccountSecret());
		
			$trades = $server->getTradesForOffer($offerID);
	
			$account = $server->getExtendedAccount($keypair->getPublicKey());

			$openOffer = null;
			$offers = $account->getOffers();

			if (count($offers))
			{
				foreach($offers AS $o)
				{
					if ($o->getID() == $offerID)
					{
						$openOffer = $o;
					}
				}
			}

			$response = Array("trades" => $trades, "isOpen" => $openOffer ? true : false);

			return $response;
		}

		function manageOffer(Bot $bot, Time $time, \ZuluCrypto\StellarSdk\XdrModel\Asset $sellingAsset, $sellingAmount, \ZuluCrypto\StellarSdk\XdrModel\Asset $buyingAsset, $offerIDToUpdate = null, $cancelOffer = false)
		{
			global $_BASETIMEZONE;
			
			$price = $bot->getDataInterface()->getAssetValueForTime($time);

			$buyingAmount = $price * $sellingAmount;

			$buyingAmount = (float)number_format($buyingAmount, 7, '.', '');
			$sellingAmount = (float)number_format($sellingAmount, 7, '.', '');

			$server = $this->isTestNet ? \ZuluCrypto\StellarSdk\Server::testNet() : \ZuluCrypto\StellarSdk\Server::publicNet();
			$server = new ExtendedServer($server, $this->isTestNet);

			$keypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($bot->getSettings()->getAccountSecret());
		
			$price = new \ZuluCrypto\StellarSdk\XdrModel\Price($sellingAmount, $buyingAmount);

			$digits = 0;

			while(fmod($price->getNumerator(), 1) > 0 || fmod($price->getDenominator(), 1) > 0) {
				$price->setNumerator(10 * $price->getNumerator());
				$price->setDenominator(10  * $price->getDenominator());
				$digits++;

				if ($digits >= 9) {
					$price->setNumerator(round($price->getNumerator()));
					$price->setDenominator(round($price->getDenominator()));
				}
			}

			$transactionBuilder = $server->buildTransaction($keypair);

			$operation = new \ZuluCrypto\StellarSdk\XdrModel\Operation\ManageOfferOp(
				$sellingAsset,
				$buyingAsset,
				$cancelOffer ? 0 : $sellingAmount,
				$price,
				$offerIDToUpdate
			);

			$transactionBuilder->addOperation($operation);

			//var_dump("operation **** = ", 	$operation, "price = ", $price->toFloat());	exit();
			
			try
			{
				$response = $transactionBuilder->submit($keypair);

				$result = $response->getResult();

				var_dump("result = ", $result);
				var_dump("operation result = ", $result->getOperationResults()[0]);
				var_dump("operation result - claimedOffers = ", $result->getOperationResults()[0]->getClaimedOffers());
				var_dump("operation result - offer = ", $result->getOperationResults()[0]->getOffer());

				if ($cancelOffer)
					return true;

				return Trade::fromHorizonOperationAndResult($operation, $result->getOperationResults()[0], $result->getFeeCharged()->getScaledValue());
			}
			catch(\ZuluCrypto\StellarSdk\Horizon\Exception\PostTransactionException $exception)
			{
				// TODO: Catch all errors from https://www.stellar.org/developers/guides/concepts/list-of-operations.html

				var_dump("Exception = ", $exception->getResult());
				exit();
			}

			return false;
		}

		static function getPublicTradeAggregations(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $baseAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $counterAsset, Time $start, Time $end, $interval) {
			$api = new self("https://horizon.stellar.org/", false);

			return $api->getTradeAggregations($bot, $baseAsset, $counterAsset, $start, $end, $interval);
		}

		function getTradeAggregations(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $baseAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $counterAsset, Time $start, Time $end, $interval) {
			$limit = ($end->getTimestamp() - $start->getTimestamp()) / ($interval / 1000);
			$limit = round($limit);

			// Limit to 100 which seems to be the max
			$limit = min(100, $limit);

			/*
			$arguments = Array(
				"start_time" => $start->getTimestamp() * 1000,
				"end_time" => $end->getTimestamp() * 1000,
				"resolution" => $interval,
				"order" => "asc",
				"limit" => $limit+2
			);

			$this->setAssetParametersAs($baseAsset, $arguments, "base");
			$this->setAssetParametersAs($counterAsset, $arguments, "counter");
			var_dump($arguments);exit();

			$result = $this->get(
				"trade_aggregations",
				$arguments
			);
			*/

			$server = $this->isTestNet ? \ZuluCrypto\StellarSdk\Server::testNet() : \ZuluCrypto\StellarSdk\Server::publicNet();
			$server = new ExtendedServer($server, $this->isTestNet);

			$keypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($bot->getSettings()->getAccountSecret());
			
			//$account = $server->getExtendedAccount($keypair->getPublicKey());

			//var_dump($account, $account->getSequence());

			return $server->getTradeAggregations($baseAsset, $counterAsset, $start->getTimestamp() * 1000, $end->getTimestamp() * 1000, $interval, $limit, "asc");
		}

	}



<?php

namespace GalacticBot;

/*
* Minimal wrapper class to Stellar PHP API.
*/
class StellarAPI {

	const INTERVAL_MINUTE = 60000;
	const INTERVAL_FIVEMINUTES = 300000;
	const INTERVAL_FIFTEENMINUTES = 900000;

	private $URL;

	private $isTestNet;

	/**
	 * Private constructor, please use either the getTestNetAPI() or getPublicAPI() method.
	 */
	private function __construct($URL, $isTestNet) {
		$this->URL = $URL;
		$this->isTestNet = $isTestNet;
	}

	/**
	 * Create a new instance of the StellarAPI class pointing to the test net API
	 */
	public static function getTestNetAPI() {
		return new self("https://horizon-testnet.stellar.org/", true);
	}

	/**
	 * Create a new instance of the StellarAPI class pointing to the public API
	 */
	public static function getPublicAPI() {
		return new self("https://horizon.stellar.org/", false);
	}

	/**
	 * Returns if the instance is pointing to the test net
	 */
	public function getIsTestNet() { return $this->isTestNet; }

	/**
	 * Retrieves the current order book from the public Stellar Horizon API for a specific asset pair
	 */
	static function getPublicOrderBook(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $sellingAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $buyingAsset, $limit = null) {
		$api = new self("https://horizon.stellar.org/", false);

		return $api->getOrderBook($bot, $sellingAsset, $buyingAsset, $limit);
	}

	/**
	 * Retrieves the current order book from the Stellar Horizon API for a specific asset pair
	 */
	function getOrderBook(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $sellingAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $buyingAsset, $limit = null) {
		$server = $this->isTestNet ? \ZuluCrypto\StellarSdk\Server::testNet() : \ZuluCrypto\StellarSdk\Server::publicNet();
		$server = new ExtendedServer($server, $this->isTestNet);

		$keypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($bot->getSettings()->getAccountSecret());
	
		$result = $server->getOrderBook($sellingAsset, $buyingAsset, $limit);

		return $result;
	}

	/**
	 * Retrieves all info known (trade list and whether it's still open or not) for an offer by its offerId
	 */
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

	// Source: http://jonisalonen.com/2012/converting-decimal-numbers-to-ratios/
	function float2rat($n, $tolerance = 1.e-6)
	{
		$h1=1; $h2=0;
		$k1=0; $k2=1;
		$b = 1/$n;

		do {
			$b = 1/$b;
			$a = floor($b);
			$aux = $h1; $h1 = $a*$h1+$h2; $h2 = $aux;
			$aux = $k1; $k1 = $a*$k1+$k2; $k2 = $aux;
			$b = $b-$a;
		} while (abs($n-$h1/$k1) > $n*$tolerance);

		return [$h1, $k1];
	}

	/**
	 * Performs the Horizon API 'manageOffer' call (creates, updates or deletes an offer).
	 */
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

		$price = $this->float2rat($sellingAmount / $buyingAmount);

		$price = new \ZuluCrypto\StellarSdk\XdrModel\Price($price[0], $price[1]);

		$transactionBuilder = $server->buildTransaction($keypair);

		$operation = new \ZuluCrypto\StellarSdk\XdrModel\Operation\ManageOfferOp(
			$sellingAsset,
			$buyingAsset,
			$cancelOffer ? 0 : $sellingAmount,
			$price,
			$offerIDToUpdate
		);

		$transactionBuilder->addOperation($operation);
		
		try
		{
			$response = $transactionBuilder->submit($keypair);

			$result = $response->getResult();

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

	/**
	 * Get trades done in the past for a specific asset pair (from the pulic net)
	 */
	static function getPublicTradeAggregations(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $baseAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $counterAsset, Time $start, Time $end, $interval) {
		$api = new self("https://horizon.stellar.org/", false);

		return $api->getTradeAggregations($bot, $baseAsset, $counterAsset, $start, $end, $interval);
	}

	/**
	 * Get trades done in the past for a specific asset pair
	 */
	function getTradeAggregations(Bot $bot, \ZuluCrypto\StellarSdk\XdrModel\Asset $baseAsset, \ZuluCrypto\StellarSdk\XdrModel\Asset $counterAsset, Time $start, Time $end, $interval) {
		$limit = ($end->getTimestamp() - $start->getTimestamp()) / ($interval / 1000);
		$limit = round($limit);

		// Limit to 100 which seems to be the max
		$limit = min(100, $limit);

		$server = $this->isTestNet ? \ZuluCrypto\StellarSdk\Server::testNet() : \ZuluCrypto\StellarSdk\Server::publicNet();
		$server = new ExtendedServer($server, $this->isTestNet);

		$keypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($bot->getSettings()->getAccountSecret());
	
		return $server->getTradeAggregations($baseAsset, $counterAsset, $start->getTimestamp() * 1000, $end->getTimestamp() * 1000, $interval, $limit, "asc");
	}

}



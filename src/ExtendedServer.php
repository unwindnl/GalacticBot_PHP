<?php

namespace GalacticBot;

/*
* These are just quick and dirty implementations of missing functionality in the Stellar PHP API (zulucrypto/stellar-api).
*
* TODO: This code has to be replaced by a proper implementation.
*/
class ExtendedServer extends \ZuluCrypto\StellarSdk\Server
{

	function __construct(\ZuluCrypto\StellarSdk\Server $server, $isTestnet) {
		$this->setApiClient($server->getApiClient());
		$this->isTestnet = $isTestnet;
		$this->setSigningProvider($server->getSigningProvider());
	}

    /**
     * Returns the Account that matches $accountId or null if the account does
     * not exist
     *
     * @param $accountId Keypair|string the public account ID
     * @return Account|null
     * @throws Horizon\Exception\HorizonException
     */
    public function getExtendedAccount($accountId)
    {
        // Cannot be empty
        if (!$accountId) throw new InvalidArgumentException('Empty accountId');
        if ($accountId instanceof Keypair) {
            $accountId = $accountId->getPublicKey();
        }
        try {
            $response = $this->getApiClient()->get(sprintf('/accounts/%s', $accountId));
        }
        catch (HorizonException $e) {
            // Account not found, return null
            if ($e->getHttpStatusCode() === 404) {
                return null;
            }
            // A problem we can't handle, rethrow
            throw $e;
        }
        $account = ExtendedAccount::fromHorizonResponse($response);

		if ($account)
		  $account->setApiClient($this->getApiClient());
      
		return $account;
    }

	public function getTradesForOffer($offerID, $sinceCursor = null, $limit = 200)
    {
        $trades = [];
        $url = sprintf('/offers/%s/trades', $offerID);
        $params = [];
        
		if ($sinceCursor)
			$params['cursor'] = $sinceCursor;
        
		if ($limit)
			$params['limit'] = $limit;

        if ($params)
            $url .= '?' . http_build_query($params);

        $response = $this->getApiClient()->get($url);

        $rawTrades = $response->getRecords();

		if (count($rawTrades))
		{
			foreach($rawTrades AS $rawTrade)
			{
				$trade = TradeModel::fromRawResponseData($rawTrade);
				//$trade->setApiClient($this->getApiClient());
				$trades[] = $trade;
			}
		}

		return $trades;
    }

	public function getTradeAggregations($baseAsset, $counterAsset, $startTime, $endTime, $resolution, $limit = null, $order = null)
    {
        $url = '/trade_aggregations/';

        $params = [];
		
		$this->setAssetParametersAs($baseAsset, $params, "base");
		$this->setAssetParametersAs($counterAsset, $params, "counter");

		$params['start_time'] = $startTime;
		$params['end_time'] = $endTime;
		$params['resolution'] = $resolution;

		if ($limit)
			$params['limit'] = $limit;
  
		if ($order)
			$params['order'] = $order;
 
        $url .= '?' . http_build_query($params);

        $response = $this->getApiClient()->get($url);

		$trades = [];
        $records = $response->getRecords();

        foreach ($records as $recordData) {
            $trade = TradeAggregation::fromRawResponseData($recordData);
            $trades[] = $trade;
        }

        return $trades;
	}

	public function getOrderBook($sellingAsset, $buyingAsset, $limit = null)
    {
        $url = '/order_book/';

        $params = [];
		
		$this->setAssetParametersAs($sellingAsset, $params, "selling");
		$this->setAssetParametersAs($buyingAsset, $params, "buying");

		if ($limit)
			$params['limit'] = $limit;
 
        $url .= '?' . http_build_query($params);

        $response = $this->getApiClient()->get($url);
		
		return $response->getRawData();
	}

	function setAssetParametersAs($asset, Array &$arguments, $asType) {
		if ($asset->getType() == \ZuluCrypto\StellarSdk\XdrModel\Asset::TYPE_NATIVE) {
			$arguments[$asType . "_asset_type"] = "native";
		} else {
			if ($asset->getType() == \ZuluCrypto\StellarSdk\XdrModel\Asset::TYPE_ALPHANUM_4)
				$arguments[$asType . "_asset_type"] = "credit_alphanum4";
			else
				$arguments[$asType . "_asset_type"] = "credit_alphanum12";

			$arguments[$asType . "_asset_code"] = $asset->getAssetCode();
				$arguments[$asType . "_asset_issuer"] = $asset->getIssuer()->getAccountIdString();
		}
	}
 }


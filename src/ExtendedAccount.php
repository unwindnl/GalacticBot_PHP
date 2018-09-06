<?php

namespace GalacticBot;

/*
* These are just quick and dirty implementations of missing functionality in the Stellar PHP API (zulucrypto/stellar-api).
*
* TODO: This code has to be replaced by a proper implementation.
*/
class Offer extends \ZuluCrypto\StellarSdk\Model\RestApiModel
{
    protected $ID;

    public static function fromRawResponseData($rawData)
    {
        $object = new self();
        $object->loadFromRawResponseData($rawData);

        return $object;
    }

    public function __construct()
    {
    }

    public function loadFromRawResponseData($rawData)
    {
        parent::loadFromRawResponseData($rawData);

        if (isset($rawData['id'])) $this->ID = $rawData['id'];
    }

    public function getID()
    {
        return $this->ID;
    }

}

/*
* These are just quick and dirty implementations of missing functionality in the Stellar PHP API (zulucrypto/stellar-api).
*
* TODO: This code has to be replaced by a proper implementation.
*/
class ExtendedAccount extends \ZuluCrypto\StellarSdk\Model\Account
{
	
	private $minimumRequirement = null;

	public function getMinimumRequirement()
	{
		return $this->minimumRequirement;
	}

	public static function fromHorizonResponse(\ZuluCrypto\StellarSdk\Horizon\Api\HorizonResponse $response)
    {
        $rawData = $response->getRawData();

		if (isset($rawData["status"]) && $rawData["status"] == 404)
			return null;
		
        $object = new ExtendedAccount($rawData['id']);
        $object->accountId = $rawData['account_id'];
        $object->sequence = $rawData['sequence'];
        $object->subentryCount = $rawData['subentry_count'];
        $object->thresholds = $rawData['thresholds'];
        $object->data = [];
        if (isset($rawData['data'])) {
            foreach ($rawData['data'] as $key => $value) {
                $object->data[$key] = base64_decode($value);
            }
        }

		$minimumRequirement = 1;

        if (isset($rawData['balances'])) {
            foreach ($rawData['balances'] as $rawBalance) {
                $balance = new \ZuluCrypto\StellarSdk\Model\AssetAmount($rawBalance['balance'], $rawBalance['asset_type']);
                if (!$balance->isNativeAsset()) {
                    $balance->setAssetCode($rawBalance['asset_code']);
                    $balance->setAssetIssuerAccountId($rawBalance['asset_issuer']);
                    $balance->setLimit($rawBalance['limit']);
                }
                $object->balances[] = $balance;
				
				$minimumRequirement++;	
            }
        }
		
		if (isset($rawData['signers'])) {
            foreach ($rawData['signers'] as $rawSigner) {
				$minimumRequirement++;	
			}
		}

		if (isset($rawData['data'])) {
            foreach ($rawData['data'] as $rawSigner) {
				$minimumRequirement++;	
			}
		}

		// Reserve one for an outstanding offer, because we can have one at max
		$minimumRequirement++;

		$object->minimumRequirement = $minimumRequirement *= 0.5;

		// Add one stroop for the transaction fee
		$object->minimumRequirement += 0.0000001;

        return $object;
    }

	public function getBalances()
    {
		return $this->balances;
    }

	public function getOffers($sinceCursor = null, $limit = 50)
    {
        $offers = [];
        $url = sprintf('/accounts/%s/offers', $this->accountId);
        $params = [];
        if ($sinceCursor) $params['cursor'] = $sinceCursor;
        if ($limit) $params['limit'] = $limit;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        $response = $this->apiClient->get($url);
        $raw = $response->getRecords();
        foreach ($raw as $rawOffer) {
            $offer = Offer::fromRawResponseData($rawOffer);
            //$offer->setApiClient($this->getApiClient());
            $offers[] = $offer;
        }
		
        return $offers;
    }

}


<?php

namespace GalacticBot;

/*
* These are just quick and dirty implementations of missing functionality in the Stellar PHP API (zulucrypto/stellar-api).
*
* TODO: This code has to be replaced by a proper implementation.
*/
class TradeModel extends \ZuluCrypto\StellarSdk\Model\RestApiModel
{
    protected $ID;
 
    protected $offerID, $baseIsSeller;
 
	protected $priceN, $priceR;

	protected $baseAsset, $baseAmount, $baseAccount;
	protected $counterAsset, $counterAmount, $counterAccount;

    /**
     * @param array $rawData
     * @return Transaction
     */
    public static function fromRawResponseData($rawData)
    {
        $object = new self();
        $object->loadFromRawResponseData($rawData);

        return $object;
    }

    /**
     */
    public function __construct()
    {
    }

    /**
     * @param $rawData
     */
    public function loadFromRawResponseData($rawData)
    {
        parent::loadFromRawResponseData($rawData);

        if (isset($rawData['id'])) $this->ID = $rawData['id'];

        if (isset($rawData['offer_id'])) $this->offerID = $rawData['offer_id'];

        if (isset($rawData['base_amount'])) $this->baseAmount = $rawData['base_amount'];

        if (isset($rawData['base_is_seller']))
			$this->baseIsSeller = $rawData['base_is_seller'];

        if (isset($rawData['base_account']))
			$this->baseAccount = $rawData['base_account'];

        if (isset($rawData['counter_account']))
			$this->counterAccount = $rawData['counter_account'];

        if (isset($rawData['base_asset_type']))
		{
			if ($rawData['base_asset_type'] == "native")
			{
				$this->baseAsset = \ZuluCrypto\StellarSdk\XdrModel\Asset::newNativeAsset();
			}
			else
			{
				$this->baseAsset = \ZuluCrypto\StellarSdk\XdrModel\Asset::newCustomAsset(
					$rawData['base_asset_code'],
					$rawData['base_asset_issuer']
				);
			}
		}
	
        if (isset($rawData['counter_amount'])) $this->counterAmount = $rawData['counter_amount'];

        if (isset($rawData['counter_asset_type']))
		{
			if ($rawData['counter_asset_type'] == "native")
			{
				$this->counterAsset = \ZuluCrypto\StellarSdk\XdrModel\Asset::newNativeAsset();
			}
			else
			{
				$this->counterAsset = \ZuluCrypto\StellarSdk\XdrModel\Asset::newCustomAsset(
					$rawData['counter_asset_code'],
					$rawData['counter_asset_issuer']
				);
			}
		}
	
        if (isset($rawData['price'])) {
			$this->priceN = $rawData['price']['n'];
			$this->priceD = $rawData['price']['d'];
		} 
	}

    /**
     * @return number
     */
    public function getOfferID()
    {
        return $this->offerID;
    }

    public function getID() { return $this->ID; }

    public function getBaseIsSeller() { return $this->baseIsSeller; }

    public function getPrice() { return $this->priceN / $this->priceD; }

    public function getBaseAmount() { return $this->baseAmount; }
    public function getBaseAsset() { return $this->baseAsset; }
    public function getBaseAccount() { return $this->baseAccount; }

    public function getCounterAmount() { return $this->counterAmount; }
    public function getCounterAsset() { return $this->counterAsset; }
    public function getCounterAccount() { return $this->counterAccount; }

}

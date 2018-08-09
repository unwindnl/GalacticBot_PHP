<?php

namespace GalacticBot;

/*
 * These are just quick and dirty implementations of missing functionality in the Stellar PHP API (zulucrypto/stellar-api).
 * TODO: This code has to be replaced by a proper implementation.
 */
 
class TradeAggregation extends \ZuluCrypto\StellarSdk\Model\RestApiModel
{
    protected $timestamp;
    protected $tradeCount;
    protected $baseVolume;
    protected $counterVolume;
    
	protected $avg;
    
	protected $high, $highR;
	protected $low, $lowR;
	protected $open, $openR;
	protected $close, $closeR;

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

        if (isset($rawData['timestamp'])) $this->timestamp = $rawData['timestamp'];
        if (isset($rawData['trade_count'])) $this->tradeCount = $rawData['trade_count'];
        if (isset($rawData['base_volume'])) $this->baseVolume = $rawData['base_volume'];

        if (isset($rawData['counter_volume'])) $this->counterVolume = $rawData['counter_volume'];
        if (isset($rawData['avg'])) $this->avg = $rawData['avg'];

        if (isset($rawData['high'])) $this->high = $rawData['high'];
        if (isset($rawData['high_r'])) $this->highR = [$rawData['high_r']['N'], $rawData['high_r']['D']];

        if (isset($rawData['low'])) $this->low = $rawData['low'];
        if (isset($rawData['low_r'])) $this->lowR = [$rawData['low_r']['N'], $rawData['low_r']['D']];

        if (isset($rawData['open'])) $this->open = $rawData['open'];
        if (isset($rawData['open_r'])) $this->openR = [$rawData['open_r']['N'], $rawData['open_r']['D']];

        if (isset($rawData['close'])) $this->close = $rawData['close'];
        if (isset($rawData['close_r'])) $this->closeR = [$rawData['close_r']['N'], $rawData['close_r']['D']];
    }

    /**
     * @return number
     */
    public function getAvg()
    {
        return $this->avg;
    }

    /**
     * @return number
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
     
}

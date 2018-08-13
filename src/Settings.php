<?php

namespace GalacticBot;

/**
* Parses and loads Bot settings.
*/
// Todo: get the EMA bot settings away from here and to the EMABot class
class Settings
{
	
	private $dataInterface = null;

	// Unique bot ID
	private $ID = null;

	// Type of bot
	private $type = null;

	// Name of bot
	private $name = null;

	// Base asset (usually XLM / native)
	private $baseAsset = null;
	private $baseAssetInitialBudget = null;

	// Counter asset, for example MOBI
	private $counterAsset = null;

	// StellarAPI class instance
	private $API = null;

	// Secret of the Stellar account we're trading on
	private $accountSecret = null;
	
	/*
	* How long to wait before buying, after all checks for buying have passed
	*/
	private $buyDelayMinutes = null;
			
	/*
	* How long to hold the counter asset at minimum before even checking if we need to sell
	*/
	private $minimumHoldMinutes = null;
		
	/*
	* How long in minutes we should try to predict the price
	*/
	private $prognosisWindowMinutes = null;
		
	/*
	* How much profit we want at minimum, doesn't sell if this percentage isn't met
	*/
	private $minimumProfitPercentage = null;
		
	/*
	* How many samples are taken for the short term (buy in) EMA
	*/
	private $shortTermSampleCount = null;
		
	/*
	* How many samples are taken for the short term (sale) EMA
	*/
	private $shortTermSaleSampleCount = null;
		
	/*
	* How many samples are taken for the medium term EMA
	*/
	private $mediumTermSampleCount = null;
		
	/*
	* How many samples are taken for the long term EMA
	*/
	private $longTermSampleCount = null;

	/**
	* Parses the Bot settings
	*
	* @param DataInterface $dataInterface An instance of a implemented DataInterface class, please make sure to give each bot instance it's own DataInterface class instance and not a copy/the same
	* @param Array $settings
	*/
	public function __construct(
		DataInterface $dataInterface,
		Array $settings
    ) {
		$this->dataInterface = $dataInterface;

		$this->ID = self::getSetting($settings, "ID");
		$this->type = self::getSetting($settings, "type");
		$this->name = self::getSetting($settings, "name");
		$this->baseAsset = self::getSetting($settings, "baseAsset");
		$this->baseAssetInitialBudget = self::getSetting($settings, "baseAssetInitialBudget");

		$this->API = self::getSetting($settings, "API");
		$this->accountSecret = self::getSetting($settings, "accountSecret");

		$this->counterAsset = self::getSetting($settings, "counterAsset");

		foreach($settings AS $k => $v) {
			throw new \Exception("Unknown setting '$k'.");
		}
	}

	/**
	* Parses the customizable settings from a database.
	*/
	public function loadFromDataInterface()
	{
		$this->buyDelayMinutes = $this->dataInterface->getSetting("buyDelayMinutes", 0);
		$this->minimumHoldMinutes = $this->dataInterface->getSetting("minimumHoldMinutes", 0); 
		$this->prognosisWindowMinutes = $this->dataInterface->getSetting("prognosisWindowMinutes", 30); // Cannot be larger than 'mediumTermSampleCount' setting
		$this->minimumProfitPercentage = $this->dataInterface->getSetting("minimumProfitPercentage", 0.2);

		$this->shortTermSampleCount = $this->dataInterface->getSetting("shortTermSampleCount", 15);
		$this->shortTermSaleSampleCount = $this->dataInterface->getSetting("shortTermSaleSampleCount", 15);
		$this->mediumTermSampleCount = $this->dataInterface->getSetting("mediumTermSampleCount", 120);
		$this->longTermSampleCount = $this->dataInterface->getSetting("longTermSampleCount", 240);
	}

	/**
	* Returns the Bot identifier, usually a number but if your DataInterface supports it it could be a string.
	*
	* @return Number
	*/
	public function getID() { return $this->ID; }

	/**
	* Returns the Bot name
	*
	* @return String
	*/
	public function getName() { return $this->name; }

	/**
	* Returns the Bot type (Bot::TYPE_LIVE or Bot::TYPE_SIMULATION)
	*
	* @return String
	*/
	public function getType() { return $this->type; }
		
	/**
	* Returns the base asset - which usually is XLM native.
	* @return ZuluCrypto\StellarSdk\XdrModel\Asset
	*/
	public function getBaseAsset() { return $this->baseAsset; }

	/**
	* Returns the base asset budget the Bot will start with. You can't change this after the bot has started trading.
	* @return float
	*/
	public function getBaseAssetInitialBudget() { return $this->baseAssetInitialBudget; }

	/**
	* Returns the counter asset - for example MOBI
	* @return ZuluCrypto\StellarSdk\XdrModel\Asset
	*/
	public function getCounterAsset() { return $this->counterAsset; }

	/**
	* Instance of the StellarAPI class
	* @return StellarAPI
	*/
	public function getAPI() { return $this->API; }

	/**
	* Instance of the DataInterface implemtated class
	* @return DataInterface
	*/
	public function getDataInterface()
	{
		return $this->dataInterface;
	}

	/**
	* Stellar account secret
	* @return String
	*/
	public function getAccountSecret() { return $this->accountSecret; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getBuyDelayMinutes() { return $this->buyDelayMinutes; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getMinimumProfitPercentage() { return $this->minimumProfitPercentage; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getMinimumHoldMinutes() { return $this->minimumHoldMinutes; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getShortTermSampleCount() { return $this->shortTermSampleCount; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getShortTermSaleSampleCount() { return $this->shortTermSaleSampleCount; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getMediumTermSampleCount() { return $this->mediumTermSampleCount; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getLongTermSampleCount() { return $this->longTermSampleCount; }
			
	/**
	* Setting, see this class variables for more information
	*/
	public function getPrognosisWindowMinutes() { return $this->prognosisWindowMinutes; }

	/**
	* Gets a setting from the settings array and removes it from the array afterwards
	*/
	private function getOptionalSetting(Array &$settings, $name, $defaultValue = null)
	{
		$value = isset($settings[$name]) ? $settings[$name] : $defaultValue;

		@unset($settings[$name]);

		return $value;
	}

	/**
	* Gets a setting from the settings array and removes it from the array afterwards, will fail if the settings doesn't exist
	*/
	private function getSetting(Array &$settings, $name)
	{
		$value = $settings[$name];

		unset($settings[$name]);

		return $value;
	}

}


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

	// Base asset reservation - bot won't do anything with this amount of the base asset
	private $baseAssetReservationAmount = null;

	// Counter asset, for example MOBI
	private $counterAsset = null;

	// StellarAPI class instance
	private $API = null;

	// Secret of the Stellar account we're trading on
	private $accountSecret = null;

	// All defined settings for a bot, based on the BotClass::$settingDefaults array
	private $settings = [];

	/**
	* Parses the Bot settings
	*
	* @param DataInterface $dataInterface An instance of a implemented DataInterface class, please make sure to give each bot instance it's own DataInterface class instance and not a copy/the same
	* @param Array $options
	*/
	public function __construct(
		DataInterface $dataInterface,
		Array $options
    ) {
		$this->dataInterface = $dataInterface;

		$this->ID = self::getFromArray($options, "ID");
		$this->type = self::getFromArray($options, "type");
		$this->name = self::getFromArray($options, "name");

		$this->baseAsset = self::getFromArray($options, "baseAsset");
		$this->baseAssetReservationAmount = self::getOptionalFromArray($options, "baseAssetReservationAmount");

		$this->API = self::getFromArray($options, "API");
		$this->accountSecret = self::getFromArray($options, "accountSecret");

		$this->counterAsset = self::getFromArray($options, "counterAsset");

		foreach($options AS $k => $v) {
			throw new \Exception("Unknown option '$k'.");
		}
	}

	/**
	* Parses the customizable settings from a database.
	*/
	public function loadFromDataInterface($defaults)
	{
		foreach($defaults AS $name => $defaultValue)
		{
			$this->settings[$name] = $this->dataInterface->getSetting($name, $defaultValue);
		}
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
	* Returns the Bot type (Bot::SETTING_TYPE_SIMULATION or Bot::SETTING_TYPE_LIVE)
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
	* Returns the base asset reservation - the bot won't do anything with this amount.
	* @return float
	*/
	public function getBaseAssetReservationAmount() { return $this->baseAssetReservationAmount; }

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
	* Returns a specific setting which has to be defined first in BotClassName::$settingDefaults
	*/
	public function get($name)
	{
		return isset($this->settings[$name]) ? $this->settings[$name] : null;
	}

	/**
	* Stellar account secret
	* @return String
	*/
	public function getAccountSecret() { return $this->accountSecret; }
			
	/**
	* Gets a setting from the settings array and removes it from the array afterwards
	*/
	private function getOptionalFromArray(Array &$settings, $name, $defaultValue = null)
	{
		$value = isset($settings[$name]) ? $settings[$name] : $defaultValue;

		unset($settings[$name]);

		return $value;
	}

	/**
	* Gets a setting from the settings array and removes it from the array afterwards, will fail if the settings doesn't exist
	*/
	private function getFromArray(Array &$settings, $name)
	{
		$value = $settings[$name];

		unset($settings[$name]);

		return $value;
	}

}


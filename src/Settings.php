<?php

namespace GalacticBot;

class Settings
{
	
	private $dataInterface = null;

	// Unique bot ID
	private $ID = null;

	// Type of bot
	private $type = null;

	// Name of bot
	private $name = null;

	// Nameddsdgsdsg
	private $baseAsset = null;
	private $baseAssetInitialBudget = null;
	private $counterAsset = null;

	private $API = null;
	private $accountSecret = null;
	
	// Nameddsdgsdsg
	private $buyDelayMinutes = null;
			
	// Nameddsdgsdsg
	private $minimumHoldMinutes = null;
		
	// Nameddsdgsdsg
	private $prognosisWindowMinutes = null;
		
	// Nameddsdgsdsg
	private $minimumProfitPercentage = null;
		
	// Nameddsdgsdsg
	private $shortTermSampleCount = null;
	private $shortTermSaleSampleCount = null;
	private $mediumTermSampleCount = null;
	private $longTermSampleCount = null;

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

	public function getID() { return $this->ID; }
	public function getName() { return $this->name; }
	public function getType() { return $this->type; }
		
	public function getBaseAsset() { return $this->baseAsset; }
	public function getBaseAssetInitialBudget() { return $this->baseAssetInitialBudget; }
	public function getCounterAsset() { return $this->counterAsset; }

	public function getAPI() { return $this->API; }
	public function getAccountSecret() { return $this->accountSecret; }
			
	public function getBuyDelayMinutes() { return $this->buyDelayMinutes; }
	public function getMinimumProfitPercentage() { return $this->minimumProfitPercentage; }
	public function getMinimumHoldMinutes() { return $this->minimumHoldMinutes; }
			
	public function getShortTermSampleCount() { return $this->shortTermSampleCount; }
	public function getShortTermSaleSampleCount() { return $this->shortTermSaleSampleCount; }
	public function getMediumTermSampleCount() { return $this->mediumTermSampleCount; }
	public function getLongTermSampleCount() { return $this->longTermSampleCount; }

	public function getPrognosisWindowMinutes() { return $this->prognosisWindowMinutes; }

	public function getDataInterface()
	{
		return $this->dataInterface;
	}

	private function getOptionalSetting(Array &$settings, $name, $defaultValue = null)
	{
		return isset($settings[$name]) ? $settings[$name] : $defaultValue;
	}

	private function getSetting(Array &$settings, $name)
	{
		$value = $settings[$name];

		unset($settings[$name]);

		return $value;
	}

}


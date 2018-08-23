<?php

namespace GalacticBot;

/*
* Describes how the data interface should work.
*
* You need to implement this yourself or use the example MySQL implementation found in the Implementation/ folder.
*/
interface DataInterface
{

	/*
	* If you want to hold some/all data in memory then you need load all the data here, you should also save for what bot you're processing data if you support multiple bots.
	*/
	function loadForBot(Bot $bot, $force = false);

	/*
	* Save all (changed) data and reload - some settings/variables could be changed by other processes; this will make sure that changes are loaded in
	*/
	function saveAndReload();

	/*
	* Save all (changed) data
	*/
	function save();

	/*
	* Get the price for an asset at any givven time
	* @return float or null
	*/
	function getAssetValueForTime(Time $time);

	/*
	* Returns the value for a variable
	* @return string or $defaultValue
	*/
	function get($name, $defaultValue = null);

	/*
	* Sets the value for a variable (you can wait for a save() method call to sent this to you database)
	* @return void
	*/
	function set($name, $value);

	/*
	* Sets the value for a variable (this has to be sent to the database right away)
	* @return void
	*/
	function directSet($name, $value);

	/*
	* Checks if have a value for a setting
	* @return bool
	*/
	function isSetting($name);

	/*
	* Returns the settings value
	* @return string or $defaultValue
	*/
	function getSetting($name, $defaultValue = null);

	/*
	* Sets the settings value
	* @return void
	*/
	function setSetting($name, $value);

	/*
	* Gets a value for a variable on a specific time
	* @return string or null
	*/
	function getT(Time $time, $name);

	/*
	* Sets a value for a variable on a specific time
	* @return void
	*/
	function setT(Time $time, $name, $value);

	/*
	* Gets a SampleBuffer instance for a variable, will create an new one if no buffer with that name exists
	* @return SampleBuffer
	*/
	function getS($name, $maxLength);

	/*
	* Sets a SampleBuffer instance for a variable
	* @return void
	*/
	function setS($name, Samples $value);

	/*
	* Must clear all data for a Bot except for stored prices and setting.
	* @return void
	*/
	function clearAllExceptSampleDataAndSettings();

	/*
	* Add (store) a trade in the database
	* @return void
	*/
	function addTrade(Trade $trade);

	/*
	* Return the last added trade (sorted on Trade::ProcessedAt)
	* @return Trade
	*/
	function getLastTrade();

	/*
	* Return the last added trade which has the state Trade::STATE_FILLED (sorted on Trade::ProcessedAt)
	* @return Trade
	*/
	function getLastCompletedTrade();
	
	/*
	* Return a last by ID
	* @return Trade
	*/
	function getTradeByID($ID);
	
	/*
	* Saves changed to a Trade to the database
	* @return Trade
	*/
	function saveTrade(Trade $trade);

	/*
	* Returns a list of trades 
	* @param int $limit The maximum number of trades this method will return
	* @param bool $orderDesc True if should sort by ProcessedAt in descending order
	* @return Array
	*/
	function getTrades($limit, $orderDesc);

	/*
	* Returns all trades in a specific time frame
	* @return Array
	*/
	function getTradeInTimeRange(Time $begin, Time $end);

	/*
	* You can define how you want to log this (for example to a database, file or stdout) 
	* @param string $what Message that has to be logged
	* @return void
	*/
	function logVerbose($what);

	/*
	* You can define how you want to log this (for example to a database, file or stdout) 
	* @param string $what Message that has to be logged
	* @return void
	*/
	function logWarning($what);

	/*
	* You can define how you want to log this (for example to a database, file or stdout) 
	* @param string $what Message that has to be logged
	* @return void
	*/
	function logError($what);

}


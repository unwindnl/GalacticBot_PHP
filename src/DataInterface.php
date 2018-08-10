<?php

namespace GalacticBot;

interface DataInterface
{

	function loadForBot(Bot $bot, $force = false);

	function saveAndReload();
	function save();

	function getAssetValueForTime(Time $time);

	function get($name, $defaultValue = null);
	function set($name, $value);
	function directSet($name, $value);

	function isSetting($name);
	function getSetting($name, $defaultValue = null);
	function setSetting($name, $value);

	function getT(Time $time, $name);
	function setT(Time $time, $name, $value);

	function getS($name, $maxLength);
	function setS($name, Samples $value);

	function clearAllExceptSampleDataAndSettings();

	function addTrade(Trade $trade);
	function getLastTrade();
	function getLastCompletedTrade();
	function getTradeByID($ID);
	function saveTrade(Trade $trade);

	function getTrades($limit, $orderDesc);

	function logVerbose($what);
	function logWarning($what);
	function logError($what);

}


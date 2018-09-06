<?php

	define("_DATABASE_HOST", PASTE_DATABASE_SERVER_HERE);
	define("_DATABASE_USER", PASTE_DATABASE_USERNAME_HERE);
	define("_DATABASE_PASS", PASTE_DATABASE_PASSWORD_HERE);
	define("_DATABASE_NAME", PASTE_DATABASE_NAME_HERE);

	$liveSettings = new GalacticBot\Settings(
		// Create a new instance of the mysql data interface implementation
		// Sharing this for both won't work, it needs its own instance
		new GalacticBot\Implementation\MysqlDataInterface(
			_DATABASE_HOST,
			_DATABASE_USER,
			_DATABASE_PASS,
			_DATABASE_NAME
		),
		Array(

			// Unique number so we can tell bots apart programmatically
			"ID" => 1,
			
			// Type of bot (SETTING_TYPE_LIVE or SETTING_TYPE_SIMULATION)
			"type" => GalacticBot\Bot::SETTING_TYPE_LIVE,
			"API" => GalacticBot\StellarAPI::getPublicAPI(),

			// Name so we can tell bots apart in the webinterface
			"name" => "Live Bot",
			
			// Source or base asset, this will normally be the native (XLM) asset
			"baseAsset" => ZuluCrypto\StellarSdk\XdrModel\Asset::newNativeAsset(),

			// Amount of base asset the bot can't 'touch'
			"baseAssetReservationAmount" => 0,

			// Asset we want to trade with
			"counterAsset" => ZuluCrypto\StellarSdk\XdrModel\Asset::newCustomAsset("MOBI", "GA6HCMBLTZS5VYYBCATRBRZ3BZJMAFUDKYYF6AH6MVCMGWMRDNSWJPIH"),

			// The Stellar account secret - in this case this is on the PUBLIC network
			// as this is a live bot
			"accountSecret" => PASTE_YOUR_PUBLIC_ACCOUNT_SECRET_HERE
		)
	);

	// This is the configuration of the simulation bot
	// Please see the notes above for the live bot on how to configure this
	$simulationSettings = new GalacticBot\Settings(
		new GalacticBot\Implementation\MysqlDataInterface(
			_DATABASE_HOST,
			_DATABASE_USER,
			_DATABASE_PASS,
			_DATABASE_NAME
		),
		Array(
			"ID" => 2,
			"type" => GalacticBot\Bot::SETTING_TYPE_SIMULATION,
			"API" => GalacticBot\StellarAPI::getTestNetAPI(),

			"name" => "Simulation Bot",
			
			"baseAsset" => ZuluCrypto\StellarSdk\XdrModel\Asset::newNativeAsset(),

			"counterAsset" => ZuluCrypto\StellarSdk\XdrModel\Asset::newCustomAsset("MOBI", "GA6HCMBLTZS5VYYBCATRBRZ3BZJMAFUDKYYF6AH6MVCMGWMRDNSWJPIH"),

			"accountSecret" => PASTE_YOUR_TESTNET_ACCOUNT_SECRET_HERE
		)
	);

	// We'll create an list for all the bots we are working with
	$bots = [];

	// Add the live bot (ID: #1) to the bot list
	$bots[$liveSettings->getID()] = new GalacticBot\Implementation\EMABot($liveSettings);

	// Add the simulation bot (ID: #2) to the bot list
	$bots[$simulationSettings->getID()] = new GalacticBot\Implementation\EMABot($simulationSettings);


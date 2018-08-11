<?php

	// This script handles all the webinterface requests.
	// To run a but please see the worker.php script.

	// Make sure to always the correct working directory so no matter where
	// this script is called from, we're always making sure its being set
	// correctly.
	chdir(dirname(__FILE__));
	
	// Load all our dependencies
	require_once "vendor/autoload.php";
	
	// Load the database and bot configuration
	// Please copy setup.example.php to setup.php and fill in both the Stellar
	// addresses for your bots as well as the MySQL database settings
	require_once "setup.php";

	// We're not really using templates, just a quick and dirty way to separate
	// html from php - kinda how php was originally designed 🤓
	include_once "templates/page.header.php";

	// Are we doing something for a specific bot?
	$botID = isset($_REQUEST["ID"]) ? $_REQUEST["ID"] : null;

	if ($botID)
	{
		$bot = isset($bots[$botID]) ? $bots[$botID] : null;

		if ($bot)
		{
			$action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;

			if (defined("_ACTIONS_DISABLED") && _ACTIONS_DISABLED)
			{
				if ($action)
				{
					echo "<b style='color:red;'>All actions are disabled for this live demo.</b>";
				}
			}
			else if ($action == "update-settings")
			{
				// Quick and dirty way of updating settings without any checks on the values!
				foreach($_POST AS $k => $v)
				{
					if ($bot->getDataInterface()->isSetting($k))
					{
						$v = (float)$v;

						$bot->getDataInterface()->setSetting($k, $v);
					}
				}
				
				echo "<b>Settings updated.</b>";
			}
			else if ($action == "start")
			{
				echo "Action performed.";
				$bot->start();
			}
			else if ($action == "pause")
			{
				echo "Action performed.";
				$bot->pause();
			}
			else if ($action == "stop")
			{
				echo "Action performed.";
				$bot->stop();
			}
			else if ($action == "simulation-reset")
			{
				echo "Action performed.";
				$bot->simulationReset();
			}
			else if ($action)
			{
				echo "Unknown action '$action'.";
			}
			
			include_once "templates/details.php";
		}
		else
		{
			echo "No bot found with this ID.";
		}
	}
	else
	{
		// Show a list of all bots (from the $bots array which were setup in setup.php)
		include_once "templates/list.php";
	}

	// Page footer, and we're done!
	include_once "templates/page.footer.php";


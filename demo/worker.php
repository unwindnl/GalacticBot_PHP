<?php

	// Make sure to always the correct working directory so no matter where
	// this script is called from, we're always making sure its being set
	// correctly.
	chdir(dirname(__FILE__));
	
	// Load all our dependencies
	require_once "vendor/autoload.php";

	// See the README or index.php on how to create and configure the setup.php
	include_once "setup.php";

	include_once "storage.php";

	use Cocur\BackgroundProcess\BackgroundProcess;

	function exitWithError($error, $showUsage = true)
	{
		$usage = "
usage:
		
		worker.php checkstart
			Checks to see if all bots are running. If a bot isn't running or
			is crashed, this would make sure a bot will (re)start.
			Normaly this would be the only option you need to start by hand.
			Preferably you would call this every minute from a crontab to make
			sure all bots keep running.
	   
		worker.php stop
			Stops all running bots.
 
		worker.php run 1
			Runs the bot with the ID specified. You don't have to call this
			yourself. See the 'checkstart' command.

";

		exit("[ERROR] $error" . ($showUsage ? $usage : ""));
	}

	// Get the script command line arguments
	$argv = $_SERVER["argv"];

	// Remove script name
	$scriptName = array_shift($argv);

	// What do whe need to do?
	$action = array_shift($argv);

	switch($action)
	{
		case "checkstart":
		case "stop":
				$shouldStop = $action == "stop";

				$storage = new Storage("worker.json");

				if (!$storage->lock())
					exitWithError("Cannot lock 'worker.json'. Maybe it's already used by another process?", false);

				foreach($bots AS $bot)
				{
					$botID = $bot->getSettings()->getID();

					$PID = $storage->get("PID_" . $botID);

					$process = null;

					if ($PID)
					{
						$process = BackgroundProcess::createFromPID($PID);

						if ($process->isRunning())
						{
							echo "    - Bot #{$botID} (PID: {$PID}) is running\n";

							if ($shouldStop)
							{
								echo "    - Stopping bot\n";

								$process->stop();
							}
						}
						else
						{
							echo "    - Bot #{$botID} (PID: {$PID}) has stopped running\n";

							$process = null;
							
							$storage->set("PID_" . $botID, null);
						}
					}
					
					if (!$process && !$shouldStop)
					{
						$command = $_SERVER["_"] . " " . $scriptName . " run " . $botID . " 2>/dev/null";

						$process = new BackgroundProcess($command);
						$process->run();

						$PID = $process->getPid();

						echo "    - Started bot #{$botID} (PID: {$PID})\n";
						
						$storage->set("PID_" . $botID, $PID);
					}
				}

				$storage->unlock();
			break;

		case "run":
				$ID = array_shift($argv);

				// Todo: also check from here if bot is running

				echo "Starting bot #{$ID}\n";

				if (!isset($bots[$ID]))
					exitWithError("Unknown bot with ID #{$ID}.");

				$bots[$ID]->work();
			break;

		default:
				exitWithError("Unknown action '$action'.");
			break;
	}


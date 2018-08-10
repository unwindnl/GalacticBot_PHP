
	
	<h2>Bot #<?=$bot->getSettings()->getID()?> "<?=$bot->getSettings()->getName()?>" Details</h2>
	
	<p>
		<a href="?" class="button">Back to the overview</a>
	</p>
	
	<table>
		<tr>
			<th>Mode</th>
			<td><?=ucfirst(strtolower($bot->getSettings()->getType()))?></td>
		</tr>
		<tr>
			<th>State</th>
			<td><?=$bot->getStateInfo()["label"]?></td>
		</tr>
		<tr>
			<th>Trading State</th>
			<td><?=$bot->getTradeStateInfo()["label"]?></td>
		</tr>
		<tr>
			<th>Current base asset holdings</th>
			<td><?=$bot->getCurrentBaseAssetBudget()?></td>
		</tr>
		<tr>
			<th>Current counter asset holdings</th>
			<td><?=$bot->getCurrentCounterAssetBudget()?></td>
		</tr>

		<tr>
			<th>Last processed time</th>
			<td>
				<?
					if ($bot->getLastProcessingTime()) {
						echo $bot->getLastProcessingTime()->format("Y-m-d H:i:s");
					}
				?>
			</td>
		</tr>

	</table>

	<a href="?ID=<?=$bot->getSettings()->getID()?>" class="button">Refresh</a>

	
<? if($bot->getSettings()->getType() == GalacticBot\Bot::SETTING_TYPE_LIVE) { ?>
	<h2>Live Bot Actions</h2>
	
	<p>
		Please remember to at call 'php worker.php checkstart' from this installation after starting a bot.
	</p>

	<p>
		<a href="?ID=<?=$bot->getSettings()->getID()?>&action=start" class="button">Start</a>
		<a href="?ID=<?=$bot->getSettings()->getID()?>&action=pause" class="button">Pause</a>
		<a href="?ID=<?=$bot->getSettings()->getID()?>&action=stop" class="button">Stop</a>
	</p>
<? } else if($bot->getSettings()->getType() == GalacticBot\Bot::SETTING_TYPE_SIMULATION) { ?>
	<h2>Simulation Bot Actions</h2>
	
	<p>
		Please remember to at call 'php worker.php checkstart' from this installation after starting a bot.
	</p>

	<p>
		<a href="?ID=<?=$bot->getSettings()->getID()?>&action=start" class="button">Start</a>
		<a href="?ID=<?=$bot->getSettings()->getID()?>&action=stop" class="button">Stop</a>
		<a href="?ID=<?=$bot->getSettings()->getID()?>&action=simulation-reset" class="button">Reset</a>
	</p>
<? } ?>

	<h2>Log</h2>
	<p>
		Last 50 offers &amp; trades done by this bot.
	</p>
	<table>
		<tr>
			<th>Date</th>
			<th>Type</th>
			<th>State</th>
			<th>Amount</th>
			<th>Price</th>
		</tr>
	<? foreach($bot->getDataInterface()->getTrades(50, true) AS $trade) { ?>
		<tr>
			<td><?=$trade->getProcessedAt()->format("Y-m-d H:i:s")?></td>
			<td><?=ucfirst(strtolower($trade->getType()))?></td>
			<td><?=$trade->getStateInfo()["label"]?></td>
			<td><?=$trade->getSellAmount()?></td>
			<td><?=$trade->getPrice()?></td>
		</tr>
	<? } ?>
	</table>	
	<h2>Settings</h2>

	<p>
		All settings have prefilled default values.
		<br />
		Change any of the values and click on 'Save settings' to change the values in the database for this bot.
		<br />
		You will have to restart the bot for the changes to take effect.
	</p>

	<div class="pane">
		<form action="?ID=<?=$bot->getSettings()->getID()?>&action=update-settings" method="post">
			<table>

				<tr>
					<th>Buy delay</th>
					<td class="amount-input"><input type="number" name="buyDelayMinutes" value="<?=$bot->getDataInterface()->getSetting("buyDelayMinutes")?>" min="0" max="30" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th>Minimum hold</th>
					<td class="amount-input"><input type="number" name="minimumHoldMinutes" value="<?=$bot->getDataInterface()->getSetting("minimumHoldMinutes")?>" min="0" max="60" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th>Prognosis window</th>
					<td class="amount-input"><input type="number" name="prognosisWindowMinutes" value="<?=$bot->getDataInterface()->getSetting("prognosisWindowMinutes")?>" min="10" max="60" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th>Minimum profit before selling</th>
					<td class="amount-input"><input type="number" name="minimumProfitPercentage" value="<?=$bot->getDataInterface()->getSetting("minimumProfitPercentage")?>" min="0" max="100" step="0.01" /></td>
					<td><span></span> %</td>
				</tr>

				<tr>
					<th>Shortterm (buy)</th>
					<td class="amount-input"><input type="number" name="shortTermSampleCount" value="<?=$bot->getDataInterface()->getSetting("shortTermSampleCount")?>" min="0" max="120" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th>Shortterm (sale)</th>
					<td class="amount-input"><input type="number" name="shortTermSaleSampleCount" value="<?=$bot->getDataInterface()->getSetting("shortTermSaleSampleCount")?>" min="0" max="120" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th>Midterm</th>
					<td class="amount-input"><input type="number" name="mediumTermSampleCount" value="<?=$bot->getDataInterface()->getSetting("mediumTermSampleCount")?>" min="0" max="3600" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th>Longterm</th>
					<td class="amount-input"><input type="number" name="longTermSampleCount" value="<?=$bot->getDataInterface()->getSetting("longTermSampleCount")?>" min="0" max="3600" /></td>
					<td><span></span> minutes</td>
				</tr>

				<tr>
					<th></th>
					<td class="amount-input"><input type="submit" value="Save settings" /></td>
					<td></td>
				</tr>

			</table>
		</form>
	</div>


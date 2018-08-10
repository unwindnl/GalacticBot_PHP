
	<h2>Bot Overview</h2>
	
	<p>
		This will show all bots defined in setup.php with their current state and holdings.<br />
		Click on the 'Details' button for more information about a specific bot.
	</p>

	<table>
		<tr>
			<th>Bot #</th>
			<th>Name</th>
			<th>Type</th>
			<th>State</th>
			<th>Trading State</th>
			<th>Base Holdings</th>
			<th>Counter Holdings</th>
		</tr>
	<? foreach($bots AS $bot) { ?>
		<tr>
			<td><?=$bot->getSettings()->getID()?></td>
			<td><?=$bot->getSettings()->getName()?></td>
			<td><?=ucfirst(strtolower($bot->getSettings()->getType()))?></td>
			<td><?=$bot->getStateInfo()["label"]?></td>
			<td><?=$bot->getTradeStateInfo()["label"]?></td>
			<td><?=$bot->getCurrentBaseAssetBudget()?></td>
			<td><?=$bot->getCurrentCounterAssetBudget()?></td>
			<td>
				<?
					if ($bot->getLastProcessingTime()) {
						echo $bot->getLastProcessingTime()->format("Y-m-d H:i:s");
					}
				?>
			</td>
			<td><a href="?ID=<?=$bot->getSettings()->getID()?>" class="button">Details</a></td>
		</tr>
	<? } ?>
	</table>


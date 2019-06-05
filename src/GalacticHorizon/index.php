<?php

use GalacticHorizon\Client;
use GalacticHorizon\Transaction;
use GalacticHorizon\TransactionResult;

use GalacticHorizon\OperationResult;

use GalacticHorizon\PaymentOperation;
use GalacticHorizon\ManageOfferOperation;

use GalacticHorizon\Account;
use GalacticHorizon\Keypair;
use GalacticHorizon\Price;
use GalacticHorizon\Amount;
use GalacticHorizon\Asset;
use GalacticHorizon\Memo;
use GalacticHorizon\XDRBuffer;

include_once "lib.php";

/////

/*

$client = Client::createTestNetClient();

/////// --------------- TEST NET ACCOUNT CREATION AND FUNDING

$keypair = \GalacticHorizon\Keypair::newFromRandom();

echo "New account generated;\n - Secret: " . $keypair->getSecret() . "\n - Public: " . $keypair->getPublicKey() . "\n";

$account = \GalacticHorizon\Account::createFromKeyPair($keypair);

$client->fundTestAccount($account);


/////// --------------- PAYMENT TRANSACTION

$keypair = \GalacticHorizon\Keypair::newFromSecretKey("SCPKNPC3GTATMX2V7ILFKPVRIVTIUIVJAW4YLFWVKOTUKTBI272MVQZ4");
var_dump("From: Priv: " . $keypair->getSecret());
var_dump("From: Publ: " . $keypair->getPublicKey());
$sourceAccount = \GalacticHorizon\Account::createFromKeyPair($keypair);

$keypair = \GalacticHorizon\Keypair::newFromSecretKey("SAG37SM52EIHDOOX7K5SJ2MFIF2SOP2BBA5727QAVN6PIL35KZA44VYO");
var_dump("To: Priv: " . $keypair->getSecret());
var_dump("To: Publ:" . $keypair->getPublicKey());
$destinationAccount = \GalacticHorizon\Account::createFromKeyPair($keypair);

try {
	$payment = new PaymentOperation();
	$payment->setDestinationAccount($destinationAccount);
	$payment->setAsset(Asset::native());
	$payment->setAmount(Amount::fromFloat(100.5));

	$transaction = new Transaction($sourceAccount);
	$transaction->addOperation($payment);
	//$transaction->setMemo(Memo::fromText("Text hierzo"));
	$transaction->sign([$sourceAccount]);
	
	$result = $transaction->submit();

	var_dump("transaction result = ", $result);
} catch (\GalacticHorizon\Exception $e) {
	echo $e . "\n";
}

*/


/*
$xdr = "AAAAAAAAAGQAAAAAAAAAAQAAAAAAAAADAAAAAAAAAAEAAAAA9dCK5ZLJf6GcamYAYxpGpCCDW87noAHzmMphaxtqOxQAAAAABN5RVgAAAAAAAAAAASOckQAAAAFVU0QAAAAAAOimGoYeYK9g+Adz4GNG5ccsvlncrdo3YI1Y70JRHZ/cAAAAAAAhkcAAAAACAAAAAA==";

$buffer = XDRBuffer::fromBase32String($xdr);

$r = TransactionResult::fromXDRBuffer($buffer);

echo("\n\nr = " . (string)$r . "\n\n");

if ($r->getResult(0)->getClaimedOfferCount() > 0) {	
	for($i=0; $i<$r->getResult(0)->getClaimedOfferCount(); $i++) {
		echo $r->getResult(0)->getClaimedOffer($i);
	}
}

exit();
*/
$client = Client::createPublicClient();

exit();

$liveAccount = Account::createFromKeyPair(Keypair::createFromSecretKey("SD4FNGX5MXZ5DPBGV4F7HYLXAEZGW2OQ2DQUMUCOSJCWWYXVE7XGCCKQ"));

$sellingAsset = Asset::createNative();
$buyingAsset = Asset::createFromCodeAndIssuer("USD", Account::createFromPublicKey("GDUKMGUGDZQK6YHYA5Z6AY2G4XDSZPSZ3SW5UN3ARVMO6QSRDWP5YLEX"));

/*

$automaticlyFixTrustLineWithAmount = Amount::createFromFloat(200);

$manageOffer = new ManageOfferOperation();
$manageOffer->setSellingAsset($sellingAsset);
$manageOffer->setBuyingAsset($buyingAsset);
$manageOffer->setAmount(Amount::createFromFloat(0.22));
$manageOffer->setPrice(Price::createFromFloat(0.2129193));
$manageOffer->setOfferID(null);

try {
	$transaction = new Transaction($liveAccount);
	$transaction->addOperation($manageOffer);
	//$transaction->setMemo(Memo::fromText("Text hierzo"));
	$transaction->sign([$liveAccount]);
	
	$transactionResult = $transaction->submit($automaticlyFixTrustLineWithAmount);

	if ($transactionResult->getErrorCode() == TransactionResult::TX_SUCCESS) {
		echo "result =\n";
		echo $transactionResult->getResult(0) . "\n";
	} else {
		echo "Niet goed nie.\n";
	}

} catch (\GalacticHorizon\Exception $e) {
	echo $e . "\n";

	echo $e->getHttpResponseBody() . "\n";
}
*/


/*
$operation = new \GalacticHorizon\ChangeTrustOperation();
$operation->setAsset($buyingAsset);
$operation->setLimit(Amount::createFromFloat(0));

try {
	$transaction = new Transaction($liveAccount);
	$transaction->addOperation($operation);
	
	$transaction->sign([$liveAccount]);
	
	$transactionResult = $transaction->submit();

	if ($transactionResult->getErrorCode() == TransactionResult::TX_SUCCESS) {
		echo "JAH, goed gegaan ...\n";
		echo (string)$transactionResult . "\n";
	} else {
		echo "Niet goed nie.\n";
	}

} catch (\GalacticHorizon\Exception $e) {
	echo $e . "\n";

	echo $e->getHttpResponseBody() . "\n";
}
*/

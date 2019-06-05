<?php

namespace GalacticHorizon;

use Base32\Base32;

class Transaction implements XDRInputInterface, XDROutputInterface {

const MAX_OPS_PER_TX = 100;

const ENVELOPE_TYPE_SCP  = 1;
const ENVELOPE_TYPE_TX   = 2;
const ENVELOPE_TYPE_AUTH = 3;

private $operations = [];

private $signatures = [];

private $sourceAccount = null;
private $sequenceNumber = null;
private $fee = null;
private $timeBounds = null;
private $memo = null;

private $signees = null;

public function __construct(Account $sourceAccount) {
	$this->sourceAccount = $sourceAccount;
	$this->timeBounds = new TimeBounds();
	$this->memo = new Memo();
}

public function addOperation(Operation $operation) {
	if (count($this->operations) == self::MAX_OPS_PER_TX)
		throw new \ErrorException("Maximum number of operations per transaction exceeded.");

	$this->operations[] = $operation;
}

public function setMemo(Memo $memo) {
	$this->memo = $memo;
}

public function getFee() {
	// Todo: load base fee from network but not really relevant now as it's a fixed fee of 100 stroops
	if ($this->fee === null)
		return 100 * count($this->operations);

	return $this->fee;
}

private function operationsToXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeUnsignedInteger(count($this->operations));
	
	foreach($this->operations as $operation)
		$operation->toXDRBuffer($buffer);
}

private function signaturesToXDRBuffer(XDRBuffer &$buffer) {
	$buffer->writeUnsignedInteger(count($this->signatures));

	foreach($this->signatures AS $signature)
		$signature->toXDRBuffer($buffer);
}

public static function fromXDRBuffer(XDRBuffer &$buffer) {
	$sourceAccount = Account::fromXDRBuffer($buffer);

	$transaction = new self($sourceAccount);

	$transaction->fee = $buffer->readUnsignedInteger();
	
	$transaction->sequenceNumber = $buffer->readBigInteger();
	
	if ($buffer->readBoolean())
		$transaction->timeBounds = TimeBounds::fromXDRBuffer($buffer);

	$transaction->memo = Memo::fromXDRBuffer($buffer);

	$operationsCount = $buffer->readUnsignedInteger();

	for($o=0; $o<$operationsCount; $o++) {
		$transaction->operations[] = OperationFactory::fromXDRBuffer($buffer);
	}

	$transactionExt = $buffer->readUnsignedInteger();

	$signatureCount = $buffer->readUnsignedInteger();

	if ($signatureCount > 0) {
		for($i=0; $i<$signatureCount; $i++)
			$transaction->signatures[] = DecoratedSignature::fromXDRBuffer($buffer);

		//throw new \ErrorException("TODO: Validate signatures.");
	}

	return $transaction;
}

public function hasValue() { return true; }

public function toXDRBuffer(XDRBuffer &$buffer, $includeSignatures = true) {
	$this->sequenceNumber = $this->sourceAccount->getNextSequenceNumber();

	// Account ID (36 bytes)
	$this->sourceAccount->toXDRBuffer($buffer);

	// Fee (4 bytes)
	$buffer->writeUnsignedInteger($this->getFee());

	// Sequence number (8 bytes)
	$buffer->writeUnsignedBigInteger64($this->sequenceNumber);

	// Time Bounds are optional
	$buffer->writeOptional($this->timeBounds);

	// Memo (4 bytes if empty, 36 bytes maximum)
	$this->memo->toXDRBuffer($buffer);

	// Operations
	$this->operationsToXDRBuffer($buffer);

	// TransactionExt (union reserved for future use)
	$buffer->writeUnsignedInteger(0);

	if ($includeSignatures)
		$this->signaturesToXDRBuffer($buffer);
}

public function generateTransactionEnvelopeXDRBufferForTransaction(Transaction $transaction, $includeSignatures = true) {
	$xdrBuffer = new XDRBuffer();
	$xdrBuffer->writeHash(Client::getInstance()->getNetworkPassphrase());
	$xdrBuffer->writeUnsignedInteger(static::ENVELOPE_TYPE_TX);
	$transaction->toXDRBuffer($xdrBuffer, $includeSignatures);
	return $xdrBuffer;
}

public function clearSignatures() {
	$this->signatures = [];
}

public function sign(Array $signeeAccounts) {
	$this->signees = $signeeAccounts;

	$xdrBuffer = $this->generateTransactionEnvelopeXDRBufferForTransaction($this, false);
	
	$transactionHash = hash('sha256', $xdrBuffer->getRawBytes(), true);

	foreach($signeeAccounts AS $account)
		$this->signatures[] = $account->getKeypair()->signDecorated($transactionHash);
}

public function getOperationByType($type) {
	foreach($this->operations AS $operation)
		if ($operation->getType() == $type)
			return $operation;

	return null;
}

public function submit(Amount $automaticlyFixTrustLineWithAmount = null) {
	$xdrBuffer = new XDRBuffer();
	$this->toXDRBuffer($xdrBuffer);

	$envelopeXDR = $xdrBuffer->toBase64String();

	/*
	echo "\n --- envelopeXDR = $envelopeXDR\n\n";

	echo " nu weer terug lezen:\n";

	$bytes = base64_decode($envelopeXDR);

	$buffer = new XDRBuffer($bytes);

	$transaction = Transaction::fromXDRBuffer($buffer);

	echo $transaction . "\n";
	exit();
	*/

	$transactionResult = new TransactionResult();

	try {
		Client::getInstance()->post(
			"transactions/",
			Array(
				"tx" => $envelopeXDR
			),
			function($data) use (&$transactionResult) {
				//echo "XDR = \n" . $data->result_xdr . "\n\n";

				$resultXDRBuffer = XDRBuffer::fromBase32String($data->result_xdr);

				$transactionResult = TransactionResult::fromXDRBuffer($resultXDRBuffer);
				$transactionResult->setHash($data->hash);
				$transactionResult->setLedger($data->ledger);
				$transactionResult->setEnvelopeXDRString($data->envelope_xdr);
			}
		);
	} catch(\GalacticHorizon\Exception $e) {
		$json = @json_decode($e->getHttpResponseBody());

		if ($json && isset($json->extras)) {
			if (isset($json->extras->result_xdr)) {
				$resultXDRBuffer = XDRBuffer::fromBase32String($json->extras->result_xdr);

				$transactionResult = TransactionResult::fromXDRBuffer($resultXDRBuffer);
				$transactionResult->setEnvelopeXDRString($envelopeXDR);

				//echo " response = \n" . (string)$transactionResult . "\n\n";
				
				if ($automaticlyFixTrustLineWithAmount !== null) {
					$asset = null;

					if ($transactionResult->getResultCount() > 0) {
						if ($transactionResult->getResult(0)->getErrorCode() == ManageOfferOperationResult::MANAGE_BUY_OFFER_BUY_NO_TRUST) {
							$manageOfferOperation = $this->getOperationByType(Operation::TYPE_MANAGE_OFFER);

							if ($manageOfferOperation)
								$asset = $manageOfferOperation->getBuyingAsset();
						} else if ($transactionResult->getResult(0)->getErrorCode() == ManageOfferOperationResult::MANAGE_BUY_OFFER_SELL_NO_TRUST) {
							$manageOfferOperation = $this->getOperationByType(Operation::TYPE_MANAGE_OFFER);
						
							if ($manageOfferOperation)
								$asset = $manageOfferOperation->getSellingAsset();
						}
					}

					if ($asset) {
						if ($this->createTrustLine($asset, $automaticlyFixTrustLineWithAmount)) {

							// Resign
							$this->clearSignatures();
							$this->sign($this->signees);

							// Try to submit again but do not try again after that
							return $this->submit(null);
						}
					}
				}
			}
		}

		if ($transactionResult == null) {
			// Rethrow the exception, can't do anything here with this response
			throw $e;
		}
	}

	return $transactionResult;
}

public function createTrustLine(Asset $asset, Amount $limit) {
	$operation = new \GalacticHorizon\ChangeTrustOperation();
	$operation->setAsset($asset);
	$operation->setLimit($limit);

	$transaction = new self($this->sourceAccount);
	$transaction->addOperation($operation);
		
	$transaction->sign($this->signees);
		
	$transactionResult = $transaction->submit();

	if ($transactionResult->getErrorCode() == TransactionResult::TX_SUCCESS) {
		return true;
	}

	return false;
}

public function __tostring() {
	$str = [];
	$str[] = "(" . get_class($this) . ")";
	$str[] = " - Account = " . $this->sourceAccount;
	$str[] = " - Fee = " . $this->getFee();
	$str[] = " - Sequence number = " . $this->sequenceNumber->toString();
	$str[] = " - Time bounds = " . $this->timeBounds;
	$str[] = " - Memo = " . $this->memo;
	$str[] = " - Operations (count: " . count($this->operations) . "):";

	foreach($this->operations AS $operation)
		$str[] = IncreaseDepth((string)$operation);

	$str[] = " - Signatures (count: " . count($this->signatures) . "):";

	foreach($this->signatures AS $signature)
		$str[] = IncreaseDepth("* " . (string)$signature);
		
	return implode("\n", $str);
}

}


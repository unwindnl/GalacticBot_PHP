<?php

namespace GalacticHorizon;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

function IncreaseDepth($str) {
	$str = preg_replace("/^/m", "\t", $str);
	return $str;
}

//include_once "vendor/autoload.php";

include_once "XDRInputInterface.php";
include_once "XDROutputInterface.php";
include_once "XDRBuffer.php";
include_once "XDRDecoder.php";
include_once "XDREncoder.php";

include_once "Keypair.php";
include_once "AddressableKey.php";
include_once "Checksum.php";

include_once "MathSafety.php";
include_once "DecoratedSignature.php";
include_once "Client.php";

// Objects
include_once "Amount.php";
include_once "Price.php";
include_once "Asset.php";
include_once "Account.php";
include_once "AccountBalance.php";
include_once "AccountSigner.php";
include_once "TimeBounds.php";
include_once "Memo.php";

include_once "Transaction.php";
include_once "TransactionResult.php";

include_once "Operation.php";
include_once "OperationResult.php";

include_once "CreateAccountOperation.php";
include_once "CreateAccountOperationResult.php";

include_once "PaymentOperation.php";
include_once "PaymentOperationResult.php";

include_once "ManageSellOfferOperation.php";
include_once "ManageBuyOfferOperation.php";
include_once "OfferEntry.php";
include_once "ManageOfferOperationResult.php";

include_once "AllowTrustOperation.php";
include_once "AllowTrustOperationResult.php";

include_once "ChangeTrustOperation.php";
include_once "ChangeTrustOperationResult.php";

class Exception extends \RuntimeException {

const TYPE_SERVER_ERROR = "SERVER_ERROR";
const TYPE_REQUEST_ERROR = "REQUEST_ERROR";
const TYPE_INVALID_PARAMETERS = "INVALID_PARAMETERS";

const TYPE_UNIMPLEMENTED_FEATURE = "UNIMPLEMENTED_FEATURE";

protected $type;
protected $title;
protected $message;

protected $httpResponseCode = null;
protected $httpResponseBody = null;

protected $httpRequestURL = null;

public function getHttpResponseBody() { return $this->httpResponseBody; }

static function create($type, $message, \GuzzleHttp\Exception\BadResponseException $guzzleException = null) {
	$e = new self();
	$e->type = $type;

	switch($type) {
		case self::TYPE_SERVER_ERROR:			$e->title = "Cannot communicate with the Stellar network."; break;
		case self::TYPE_REQUEST_ERROR:			$e->title = "The Stellar network did not accept our request."; break;

		case self::TYPE_UNIMPLEMENTED_FEATURE:	$e->title = "An unimplemented feature was requested."; break;
	}

	$e->message = $message;

	if ($guzzleException) {
		$e->httpResponseCode = $guzzleException->getResponse()->getStatusCode();
		$e->httpResponseBody = (string)$guzzleException->getResponse()->getBody(true);

		$e->httpRequestURL = $guzzleException->getRequest()->getUri();
	}

	return $e;
}

public function __tostring() {
	$str = [];
	$str[] = "(Exception)";
	$str[] = " - Type = " . $this->type;
	$str[] = " - Title = " . $this->title;
	$str[] = " - Message = " . $this->message;
	$str[] = " - Request URL = " . $this->httpRequestURL;
	$str[] = " - Response code = " . $this->httpResponseCode;
		
	return implode("\n", $str);
}

}


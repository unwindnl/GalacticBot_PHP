<?php

namespace GalacticHorizon;

class Client {

const NETWORK_PASSPHRASE_PUBLIC		= "Public Global Stellar Network ; September 2015";
const NETWORK_PASSPHRASE_TEST		= "Test SDF Network ; September 2015";

const INTERVAL_MINUTE = 60000;
const INTERVAL_FIVEMINUTES = 300000;
const INTERVAL_FIFTEENMINUTES = 900000;

private $httpClient;
private $URL;
private $isTestNet;
private $networkPassphrase;

static $instance = null;

private function __construct($URL, $isTestNet) {
	$this->URL = $URL;
	$this->isTestNet = $isTestNet;
	$this->networkPassphrase = $isTestNet ? self::NETWORK_PASSPHRASE_TEST : self::NETWORK_PASSPHRASE_PUBLIC;
	$this->httpClient = new \GuzzleHttp\Client();
}

static function getInstance() {
	return self::$instance;
}

static function createTestNetClient() {
	self::$instance = new self("https://horizon-testnet.stellar.org/", true);

	return self::$instance;
}

static function createPublicClient() {
	self::$instance = new self("https://horizon.stellar.org/", false);

	return self::$instance;
}

static function createTemporaryPublicClient() {
	return new self("https://horizon.stellar.org/", false);
}

public function getNetworkPassphrase() {
	return $this->networkPassphrase;
}

public function fundTestAccount(Account $account) {
	$response = $this->httpClient->request("GET", sprintf("https://friendbot.stellar.org/?addr=%s", $account->getKeypair()->getPublicKey()));

	if ($response->getStatusCode() == 200) {
		$body = (string)$response->getBody();

		var_dump( json_decode($body) );
	}
}

public function get($URL, Array $data, callable $callback) {
	if ($data)
		$URL .= '?' . http_build_query($data);

	return $this->request("GET", $URL, [], $callback);
}

public function post($URL, Array $data, callable $callback) {
	return $this->request("POST", $URL, $data, $callback);
}

public function request($type, $URL, Array $data, callable $callback) {
	$result = false;
	
	try {
		$response = $this->httpClient->request(
			$type,
			sprintf("%s%s", $this->URL, $URL),
			[
				"form_params" => $data
			]
		);

		if ($response->getStatusCode() == 200) {
			$body = (string)$response->getBody();

			$callback(json_decode($body));

			$result = true;
		}
	} catch(\GuzzleHttp\Exception\ServerException $e) {
		throw \GalacticHorizon\Exception::create(
			\GalacticHorizon\Exception::TYPE_SERVER_ERROR,
			null,
			$e
		);
	} catch(\GuzzleHttp\Exception\ClientException $e) {
		if ($e->getResponse()->getStatusCode() == 404)
		{
		}
		else
		{
			throw \GalacticHorizon\Exception::create(
				\GalacticHorizon\Exception::TYPE_REQUEST_ERROR,
				null,
				$e
			);
		}
	}

	return $result;
}

public function callback($curl, $inData)
{
	//echo "[INDATA] " . $inData;
	
	$this->inBuffer .= $inData;

	while(strlen($this->inBuffer) > 0) {
		$ch = $this->inBuffer[0];
		$this->inBuffer = substr($this->inBuffer, 1);

		$this->lineBuffer .= $ch;

		if ($ch == "\n") {
			$line = $this->lineBuffer;
			$this->lineBuffer = "";

			if (!$line)
				continue;

			//echo "[LINE] " . $line . "\n";

			// Ignore "data: hello" handshake
			if (strpos($line, "data: \"hello\"") === 0)
				continue;

			if (strpos($line, "retry: ") === 0)
				$this->retryAfterTime = (int)substr($line, strlen('retry: '));

			if (strpos($line, "data: \"byebye\"") === 0) {
				//$openConnection = false;
				continue;
			}

			if (strpos($line, "id: ") === 0)
			{
				$this->cursor = trim(substr($line, strlen("id: ")));
				//var_dump("cursor changed to: ", $this->cursor);
				continue;
			}

			// Ignore lines that don't start with "data: "
			$sentinel = "data: ";

			if (strpos($line, $sentinel) !== 0)
				continue;

			// Remove sentinel prefix
			$json = trim(substr($line, strlen($sentinel)));
			$decoded = self::arrayToObject(json_decode($json, true));
				
			//var_dump("json = ", $json);

			if (is_object($decoded)) {
				$callback = $this->streamCallback;

				try
				{
					$callback($this->cursor, $decoded);
				}
				catch(Exception $e)
				{
					var_dump("Exception while running callback:", $e);
				}
			}
		}
	}

	return strlen($inData);
}	

public function stream($URL, Array $data, callable $callback, $automaticlyReconnect = true)
{
	$this->retryAfterTime = 1000;
	$this->streamCallback = $callback;
	$this->cursor = $data["cursor"];
	$this->inBuffer = "";
	$this->lineBuffer = "";

	//  . "?cursor=102837532999315457-0"

	while(1)
	{
		try
		{
			$data["cursor"] = $this->cursor;

			$curl = curl_init();
			curl_setopt_array(
				$curl, array(
					CURLOPT_URL => $this->URL . $URL . "?" . http_build_query($data),
					CURLOPT_HEADER => 0,
					CURLOPT_WRITEFUNCTION => array($this, "callback"),
					CURLOPT_HTTPHEADER => [
						'Accept: text/event-stream',
					],
					//CURLOPT_POST => 1,
					//CURLOPT_POSTFIELDS => $data
				)
			);

			//curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			var_dump($this->URL . $URL . "?" . http_build_query($data), $data);

			curl_exec($curl);

			if (curl_errno($curl)){
				var_dump(" Error: ", curl_error($curl));
			}

			curl_close($curl);
		}
		catch (Exception $e)
		{
			var_dump("Exception = ", $e);
		}

		sleep($this->retryAfterTime/1000);
	}

/*
	exit();

		while(1) {
			$response = $this->httpClient->get(
				sprintf("%s%s", $this->URL, $URL),
				[
					"stream" => true,
					"read_timeout" => null,
					"headers" => [
						'Accept' => 'text/event-stream',
					],
					"form_params" => $data
				]
			);

		//	var_dump($URL, $data);
		//	exit();
	 
			$body = $response->getBody();
		
			//while(!$body->eof()) {
			while(1) {
				$line = '';
				$char = null;

			echo ".";

				//while ($char != "\n") {
				
				$char = $body->read(1);

				while(strlen($char) > 0) {
					$line .= $char;
					$char = $body->read(1);
					echo ",";
				}

				if (!$line) {
					sleep(1);
					continue;
				}

			echo "[INCOMMING]: " . $line . "\n";


				
			}

			if ($automaticlyReconnect) {
				var_dump("waiting: ", $retryAfterTime);

				sleep($retryAfterTime / 1000);
			} else {
				return false;
			}
		}
	} catch(\GuzzleHttp\Exception\ServerException $e) {
		throw \GalacticHorizon\Exception::create(
			\GalacticHorizon\Exception::TYPE_SERVER_ERROR,
			null,
			$e
		);
	} catch(\GuzzleHttp\Exception\ClientException $e) {
		throw \GalacticHorizon\Exception::create(
			\GalacticHorizon\Exception::TYPE_REQUEST_ERROR,
			null,
			$e
		);
	}
*/
}

static function arrayToObject($data) {
	if (is_array($data)) {
		$numericIndices = false;

		foreach($data AS $i => $v)
			if (is_numeric($i))
				$numericIndices = true;

		if (!$numericIndices) {
			$data = (Object)$data;
		}
	}

	if (is_array($data))
		foreach($data AS $k => $v)
			$data[$k] = self::arrayToObject($v);
	else if (is_object($data))
		foreach($data AS $k => $v)
			$data->$k = self::arrayToObject($v);

	return $data;
}

static function setAssetParametersAs(Asset $asset, Array &$arguments, $asType) {
	if ($asset->getType() == Asset::TYPE_NATIVE) {
		$arguments[$asType . "_asset_type"] = "native";
	} else {
		if ($asset->getType() == Asset::TYPE_ALPHANUM_4)
			$arguments[$asType . "_asset_type"] = "credit_alphanum4";
		else
			$arguments[$asType . "_asset_type"] = "credit_alphanum12";

		$arguments[$asType . "_asset_code"] = $asset->getCode();
			$arguments[$asType . "_asset_issuer"] = $asset->getIssuer()->getKeypair()->getPublicKey();
	}
}

public function getOrderbookForAssetPair(Asset $sellingAsset, Asset $buyingAsset, $limit = null) {
   $params = [];
		
	self::setAssetParametersAs($sellingAsset, $params, "selling");
	self::setAssetParametersAs($buyingAsset, $params, "buying");

	if ($limit)
		$params['limit'] = $limit;
	
	$response = null;

	$this->get(
		"order_book/",
		$params,
		function($data) use (&$response) {
			$response = $data;
		}
	);

	return $response;
}

public function getTradeAggregations(Asset $baseAsset, Asset $counterAsset, \DateTime $start, \DateTime $end, $resolution, $order = null) {
	$start = $start->format("U") * 1000;
	$end = $end->format("U") * 1000;

	$limit = ($end - $start) / ($resolution / 1000);
	$limit = round($limit);

	// Limit to 100 which seems to be the max
	$limit = min(100, $limit);

	$params = [];
	
	self::setAssetParametersAs($baseAsset, $params, "base");
	self::setAssetParametersAs($counterAsset, $params, "counter");

	$params['start_time'] = $start;
	$params['end_time'] = $end;
	$params['resolution'] = $resolution;

	if ($limit)
		$params['limit'] = $limit;
  
	if ($order)
		$params['order'] = $order;

	$response = null;
	
	$this->get(
		"trade_aggregations/",
		$params,
		function($data) use (&$response) {
			$response = $data->_embedded->records;
		}
	);

	return $response;
}

}


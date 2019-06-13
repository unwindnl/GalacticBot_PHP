<?php

namespace GalacticHorizon {

use ParagonIE\Sodium\Core\Ed25519;
//use ZuluCrypto\StellarSdk\Derivation\Bip39\Bip39;
//use ZuluCrypto\StellarSdk\Derivation\HdNode;
//use ZuluCrypto\StellarSdk\XdrModel\DecoratedSignature;

/**
 * A public/private keypair for use with the Stellar network
 */
class Keypair
{

    /**
     * Base-32 encoded seed
     *
     * @var string
     */
    private $seed;

    /**
     * Bytes of the private key
     *
     * @var string
     */
    private $privateKey;

    /**
     * Base-32 public key
     *
     * @var string
     */
    private $publicKeyString;

    /**
     * Bytes of the public key
     *
     * @var string
     */
    private $publicKey;

	public static function createRandom() {
		return self::createFromRawSeed(random_bytes(32));
	}

	public static function createFromSecretKey($base32String) {
		return new Keypair($base32String);
	}

    private static function createFromRawSeed($rawSeed) {
        $seedString = AddressableKey::seedFromRawBytes($rawSeed);
        return new Keypair($seedString);
    }

    public static function createFromPublicKey($base32String) {
        $keypair = new Keypair();
        $keypair->setPublicKey($base32String);
        return $keypair;
    }

	/*
    public static function newFromMnemonic($mnemonic, $passphrase = '', $index = 0)
    {
        $bip39 = new Bip39();
        $seedBytes = $bip39->mnemonicToSeedBytesWithErrorChecking($mnemonic, $passphrase);

        $masterNode = HdNode::newMasterNode($seedBytes);

        $accountNode = $masterNode->derivePath(sprintf("m/44'/148'/%s'", $index));

        return static::newFromRawSeed($accountNode->getPrivateKeyBytes());
    }
	*/

    public function __construct($seedString = null) {
        if ($seedString)
            $this->setSeed($seedString);
    }

    public function signDecorated($value) {
        $this->requirePrivateKey();

        return new DecoratedSignature(
            $this->getHint(),
            $this->sign($value)
        );
    }

    public function sign($value) {
        $this->requirePrivateKey();

        return Ed25519::sign_detached($value, $this->getEd25519SecretKey());
    }

/*
     public function verifySignature($signature, $message) 
        return Ed25519::verify_detached($signature, $message, $this->publicKey);
    }
*/

    public function setPublicKey($base32String) {
        // Clear out all private key fields
        $this->privateKey = null;

        $this->publicKey = AddressableKey::getRawBytesFromBase32AccountId($base32String);
        $this->publicKeyString = $base32String;
    }

    public function setSeed($base32SeedString) {
        $this->seed = $base32SeedString;
        $this->privateKey = AddressableKey::getRawBytesFromBase32Seed($base32SeedString);
        $this->publicKeyString = AddressableKey::addressFromRawSeed($this->privateKey);
        $this->publicKey = AddressableKey::getRawBytesFromBase32AccountId($this->publicKeyString);
    }

    public function getHint() {
        return substr($this->publicKey, -4);
    }

    public function getPublicKeyChecksum() {
        $checksumBytes = substr($this->getPublicKeyBytes(), -2);

        $unpacked = unpack('v', $checksumBytes);

        return array_shift($unpacked);
    }

	public function getSecret() {
		$this->requirePrivateKey();

		return $this->seed;
	}

	/*
    public function getPrivateKeyBytes()
    {
        $this->requirePrivateKey();

        return AddressableKey::getRawBytesFromBase32Seed($this->seed);
    }

    public function getPublicKeyBytes()
    {
        return $this->publicKey;
    }

    public function getAccountId()
    {
        return $this->publicKeyString;
    }
	*/

    public function getPublicKey() {
        return $this->publicKeyString;
    }

    protected function requirePrivateKey() {
        if (!$this->privateKey) {
			$exception = \GalacticHorizon\Exception::create(
				\GalacticHorizon\Exception::TYPE_INVALID_PARAMETERS,
				"Private key is required to perform this operation.",
				$e
			);
		}
	}

	protected function getEd25519SecretKey() {
		$this->requirePrivateKey();

		$pk = '';
		$sk = '';

		Ed25519::seed_keypair($pk, $sk, $this->privateKey);

		return $sk;
	}

}

}


<?php

namespace GalacticHorizon;

use phpseclib\Math\BigInteger;

use Base32\Base32;

/**
 * Enables easy iteration through a blob of XDR data
 */
class XDRBuffer
{
    /**
     * @var string
     */
    protected $xdrBytes;

    /**
     * Current position within the bytes
     *
     * @var int
     */
    protected $position;

    public function __construct($xdrBytes = "")
    {
        $this->xdrBytes = $xdrBytes;
        $this->position = 0;
    }

	static function fromBase32String($string) {
		return new self(base64_decode($string));
	}

    public function getRawBytes() {
		return $this->xdrBytes;
	}

	public function writeUnsignedInteger($value) {
		$this->xdrBytes .= XDREncoder::unsignedInteger($value);
	}

	public function writeUnsignedBigInteger64($value) {
		$this->xdrBytes .= XDREncoder::unsignedBigInteger64($value);
	}

	public function writeSignedBigInteger64($value) {
		$this->xdrBytes .= XDREncoder::signedBigInteger64($value);
	}

	public function writeUnsignedInteger64($value) {
		$this->xdrBytes .= XDREncoder::unsignedInteger64($value);
	}

	public function writeString($value, $maximumLength = null) {
		$this->xdrBytes .= XDREncoder::string($value, $maximumLength);
	}

	public function writeOptional(XDROutputInterface $value) {
		if ($value !== null && $value->hasValue()) {
			$this->xdrBytes .= XDREncoder::boolean(true);
			$value->toXDRBuffer($this);
		} else {
			$this->xdrBytes .= XDREncoder::boolean(false);
		}
    }

	public function writeOpaqueFixed($value, $expectedLength = null, $padUnexpectedLength = false) {
		$this->xdrBytes .= XDREncoder::opaqueFixed($value, $expectedLength, $padUnexpectedLength);
	}

	public function writeOpaqueVariable($value) {
		$this->xdrBytes .= XDREncoder::opaqueVariable($value);
	}

	public function toBase64String() {
		return base64_encode($this->xdrBytes);
	}

	public function writeHash($data) {
		$this->xdrBytes .= hash('sha256', $data, true);
	}

	public function readHash() {
		$dataSize = 32;
        $this->assertBytesRemaining($dataSize);

        $data = substr($this->xdrBytes, $this->position, $dataSize);
        $this->position += $dataSize;

        return $data;
	}

    /**
     * @return int
     * @throws \ErrorException
     */
    public function readUnsignedInteger()
    {
        $dataSize = 4;
        $this->assertBytesRemaining($dataSize);

        $data = XDRDecoder::unsignedInteger(substr($this->xdrBytes, $this->position, $dataSize));
        $this->position += $dataSize;

        return $data;
    }

    /**
     * @return int
     * @throws \ErrorException
     */
    public function readUnsignedInteger64()
    {
        $dataSize = 8;
        $this->assertBytesRemaining($dataSize);

        $data = XDRDecoder::unsignedInteger64(substr($this->xdrBytes, $this->position, $dataSize));
        $this->position += $dataSize;

        return $data;
    }

    /**
     * @return BigInteger
     * @throws \ErrorException
     */
    public function readBigInteger()
    {
        $dataSize = 8;
        $this->assertBytesRemaining($dataSize);

        $bigInteger = new BigInteger(substr($this->xdrBytes, $this->position, $dataSize), 256);
        $this->position += $dataSize;

        return $bigInteger;
    }

    /**
     * @return int
     * @throws \ErrorException
     */
    public function readInteger()
    {
        $dataSize = 4;
        $this->assertBytesRemaining($dataSize);

        $data = XDRDecoder::signedInteger(substr($this->xdrBytes, $this->position, $dataSize));
        $this->position += $dataSize;

        return $data;
    }

    /**
     * @return int
     * @throws \ErrorException
     */
    public function readInteger64()
    {
        $dataSize = 8;
        $this->assertBytesRemaining($dataSize);

        $data = XDRDecoder::signedInteger64(substr($this->xdrBytes, $this->position, $dataSize));
        $this->position += $dataSize;

        return $data;
    }

    /**
     * @param $length
     * @return bool|string
     * @throws \ErrorException
     */
    public function readOpaqueFixed($length)
    {
        $this->assertBytesRemaining($length);

        $data = XDRDecoder::opaqueFixed(substr($this->xdrBytes, $this->position), $length);
        $this->position += $length;

        return $data;
    }

    /**
     * @param $length
     * @return string
     * @throws \ErrorException
     */
    public function readOpaqueFixedString($length)
    {
        $this->assertBytesRemaining($length);

        $data = XDRDecoder::opaqueFixedString(substr($this->xdrBytes, $this->position), $length);
        $this->position += $length;

        return $data;
    }

    /**
     * @return bool|string
     * @throws \ErrorException
     */
    public function readOpaqueVariable($maxLength = null)
    {
        $length = $this->readUnsignedInteger();
        $paddedLength = $this->roundTo4($length);

        if ($maxLength !== null && $length > $maxLength) {
            throw new \InvalidArgumentException(sprintf('length of %s exceeds max length of %s', $length, $maxLength));
        }

        $this->assertBytesRemaining($paddedLength);

        $data = XDRDecoder::opaqueFixed(substr($this->xdrBytes, $this->position), $length);
        $this->position += $paddedLength;

        return $data;
    }

    /**
     * @param null $maxLength
     * @return bool|string
     * @throws \ErrorException
     */
    public function readString($maxLength = null)
    {
        $strLen = $this->readUnsignedInteger();
        $paddedLength = $this->roundTo4($strLen);
        if ($strLen > $maxLength) throw new \InvalidArgumentException(sprintf('maxLength of %s exceeded (string is %s bytes)', $maxLength, $strLen));

        $this->assertBytesRemaining($paddedLength);

        $data = XDRDecoder::opaqueFixed(substr($this->xdrBytes, $this->position), $strLen);
        $this->position += $paddedLength;

        return $data;
    }

    /**
     * @return bool
     * @throws \ErrorException
     */
    public function readBoolean()
    {
        $dataSize = 4;
        $this->assertBytesRemaining($dataSize);

        $data = XDRDecoder::boolean(substr($this->xdrBytes, $this->position, $dataSize));
        $this->position += $dataSize;

        return $data;
    }

    /**
     * @param $numBytes
     * @throws \ErrorException
     */
    protected function assertBytesRemaining($numBytes)
    {
        if ($this->position + $numBytes > strlen($this->xdrBytes)) {
            throw new \ErrorException('Unexpected end of XDR data');
        }
    }

    public function getBytesRemaining() {
        return strlen($this->xdrBytes) - $this->position;
    }

    /**
     * rounds $number up to the nearest value that's a multiple of 4
     *
     * @param $number
     * @return int
     */
    protected function roundTo4($number)
    {
        $remainder = $number % 4;
        if (!$remainder) return $number;

        return $number + (4 - $remainder);
    }
}


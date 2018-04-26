<?php

use TorneLIB\MODULE_CRYPTO;
use PHPUnit\Framework\TestCase;

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
}

class cryptoTest extends TestCase {

	/** @var $Crypto MODULE_CRYPTO */
	private $Crypto;

	// Compressed strings setup over base64
	private $testCompressString = "Testing my string";
	private $gz0Base = "H4sIAAAAAAAEAwERAO7_VGVzdGluZyBteSBzdHJpbmf030_XEQAAAA";
	private $gz9Base = "H4sIAAAAAAACAwtJLS7JzEtXyK1UKC4pArIA9N9P1xEAAAA";
	private $bzBase = "QlpoNDFBWSZTWajSZJAAAAETgEAABAACoxwgIAAhoaA0IBppoKc4F16DpQXi7kinChIVGkySAA";

	private $testLongCompressString = "The following string contains data: This is a longer string to test the best compression on something that is worth compression.";
	private $testLongCompressedString = "H4sIAAAAAAACA02MQQrDMAwEv6IX5N68Ix9QU9UyONpgLQTy-tqFQmFg9zBMuR_r5iZvtIarRpFkn7MjqDVSXkpdZfOaMlBpiGL9pxFCSwpH4znPjuPsllkRMkgcRv-arpyFC53-ry0flwqd0IQAAAA";


	function setUp() {
		$this->Crypto              = new MODULE_CRYPTO();
	}

	/**
	 * @test
	 * @throws Exception
	 */
	function base64GzEncodeLevel0() {
		$gzString = $this->Crypto->base64_gzencode( $this->testCompressString, 0 );
		static::assertTrue( $gzString == $this->gz0Base );
	}

	/**
	 * @test
	 * @throws Exception
	 */
	function base64GzEncodeLevel9() {
		$myString = "Testing my string";
		$gzString = $this->Crypto->base64_gzencode( $myString, 9 );
		static::assertTrue( $gzString == $this->gz9Base );
	}

	/**
	 * @test
	 */
	function base64GzDecodeLevel0() {
		$gzString = $this->Crypto->base64_gzdecode( $this->gz0Base );
		static::assertTrue( $gzString == $this->testCompressString );
	}

	/**
	 * @test
	 */
	function base64GzDecodeLevel9() {
		$gzString = $this->Crypto->base64_gzdecode( $this->gz9Base );
		static::assertTrue( $gzString == $this->testCompressString );
	}

	/**
	 * @test
	 * @throws Exception
	 */
	function base64BzEncode() {
		if (function_exists('bzcompress')) {
			$bzString = $this->Crypto->base64_bzencode( $this->testCompressString );
			static::assertTrue( $bzString == $this->bzBase );
		} else {
			static::markTestSkipped('bzcompress is missing on this server, could not complete test');
		}
	}

	/**
	 * @test
	 * @throws Exception
	 */
	function base64BzDecode() {
		if (function_exists('bzcompress')) {
		$bzString = $this->Crypto->base64_bzdecode( $this->bzBase );
		static::assertTrue( $bzString == $this->testCompressString );
		} else {
			$this->markTestSkipped('bzcompress is missing on this server, could not complete test');
		}
	}

	/**
	 * @test
	 */
	function bestCompression() {
		$compressedString                  = $this->Crypto->base64_compress( $this->testLongCompressString );
		$uncompressedString                = $this->Crypto->base64_decompress( $compressedString );
		$uncompressedStringCompressionType = $this->Crypto->base64_decompress( $compressedString, true );
		// In this case the compression type has really nothing to do with the test. We just know that gz9 is the best type for our chosen data string.
		static::assertTrue( $uncompressedString == $this->testLongCompressString && $uncompressedStringCompressionType == "gz9" );
	}

	/**
	 * @test
	 */
	function mkPass() {
		static::assertTrue(strlen($this->Crypto->mkpass(1, 16)) == 16);
	}

	/**
	 * @test
	 */
	function randomSaltStaticMkPass() {
		static::assertTrue(strlen(MODULE_CRYPTO::getRandomSalt(1, 16)) == 16);
	}

	/**
	 * @test
	 */
	function randomSaltStaticMkPassBackwardCompat() {
		static::assertTrue(strlen(\TorneLIB\TorneLIB_Crypto::getRandomSalt(1, 16)) == 16);
	}

}

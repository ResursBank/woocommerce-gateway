<?php

namespace TorneLIB;

require_once (__DIR__ . "/../vendor/autoload.php");
require_once(__DIR__ . '/testurls.php');

use PHPUnit\Framework\TestCase;

/**
 * Class skippablePhpTest Tests that requires special conditions and is normally not tested
 * @package TorneLIB
 */
class skippablePhpTest extends TestCase {

	/** @var MODULE_CURL $CURL */
	private $CURL;
	/** @var MODULE_NETWORK $NETWORK */
	private $NETWORK;
	private $TorSetupAddress = "127.0.0.1:9050";
	private $TorSetupType = 4;      /* CURLPROXY_SOCKS4*/

	function setUp() {
		$this->CURL = new MODULE_CURL();
		$this->NETWORK = new MODULE_NETWORK();
	}

	/**
	 * @test
	 * @testdox Run in a platform (deprecated) and make sure follows are disabled per default
	 */
	function followRedirectSafeMode() {
		// http://php.net/manual/en/ini.sect.safe-mode.php
		if ( version_compare( PHP_VERSION, "5.4.0", ">=" ) ) {
			static::markTestSkipped( "Safe mode has been removed from this platform, so tests can not be performed" );

			return;
		}
		if ( filter_var( ini_get( 'safe_mode' ), FILTER_VALIDATE_BOOLEAN ) === true ) {
			$this->pemDefault();
			$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
			$redirectedUrls   = $this->CURL->getRedirectedUrls();
			static::assertTrue( $this->CURL->getCode( $redirectResponse ) >= 300 && $this->CURL->getCode( $redirectResponse ) <= 350 && ! preg_match( "/rerun/i", $this->CURL->getResponseBody( $redirectResponse ) ) && count( $redirectedUrls ) );

			return;
		}
		static::markTestSkipped( "Safe mode is available as an option. It is however not enabled on this platform and can not therefore be tested." );
	}

	/**
	 * @test
	 * @testdox Test proxy by using Tor Network (Requires Tor)
	 * @link https://www.torproject.org/ Required application
	 */
	function torNetwork() {
		exec( "service tor status", $ubuntuService );
		$serviceFound = false;
		foreach ( $ubuntuService as $row ) {
			// Unsafe control
			if ( preg_match( "/loaded: loaded/i", $row ) ) {
				$serviceFound = true;
			}
		}
		if ( ! $serviceFound ) {
			static::markTestSkipped( "Skip TOR Network tests: TOR Service not found in the current control" );
		} else {
			$this->CURL->setProxy( $this->TorSetupAddress, $this->TorSetupType );
			$CurlJson = $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() );
			$parsedIp = $this->NETWORK->getArpaFromAddr( $this->CURL->getParsed()->ip, true );
			static::assertTrue( $parsedIp > 0 );
		}
	}

}
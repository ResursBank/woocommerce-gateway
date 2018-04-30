<?php

namespace TorneLIB;

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
}
if ( file_exists( __DIR__ . "/../tornelib.php" ) ) {
	// Work with TorneLIBv5
	require_once( __DIR__ . '/../tornelib.php' );
}
require_once( __DIR__ . '/testurls.php' );

use PHPUnit\Framework\TestCase;

ini_set( 'memory_limit', - 1 );    // Free memory limit, some tests requires more memory (like ip-range handling)

class curlTest extends TestCase {
	private $StartErrorReporting;

	/** @var MODULE_NETWORK */
	private $NETWORK;
	/** @var MODULE_CURL */
	private $CURL;
	private $CurlVersion = null;

	/**
	 * @var string $bitBucketUrl Bitbucket URL without scheme
	 */
	private $bitBucketUrl = 'bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git';

	//function tearDown() {}
	function setUp() {
		//$this->setDebug(true);
		$this->StartErrorReporting = error_reporting();
		$this->NETWORK             = new MODULE_NETWORK();
		$this->CURL                = new MODULE_CURL();
		$this->CURL->setUserAgent( "PHPUNIT" );

		if ( function_exists( 'curl_version' ) ) {
			$CurlVersionRequest = curl_version();
			$this->CurlVersion  = $CurlVersionRequest['version'];
		}

		/*
		 * Enable test mode
		 */
		$this->CURL->setTestEnabled();
		$this->CURL->setSslUnverified( false );
	}

	private function disableSslVerifyByPhpVersions( $always = false ) {
		if ( version_compare( PHP_VERSION, '5.5.0', '<=' ) ) {
			$this->CURL->setSslVerify( false, false );
		} else if ( $always ) {
			$this->CURL->setSslVerify( false, false );
		}
	}

	function tearDown() {
		// DebugData collects stats about the curled session.
		// $debugData = $this->CURL->getDebugData();
	}

	/**
	 * iproute2 ifconfig
	 * @return mixed
	 */
	private function getIpListByIpRoute() {
		// Don't fetch 127.0.0.1
		exec( "ip addr|grep \"inet \"|sed 's/\// /'|awk '{print $2}'|grep -v ^127", $returnedExecResponse );

		return $returnedExecResponse;
	}

	private function pemDefault() {
		$this->CURL->setFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION', false );
		$this->CURL->setSslVerify( true );
	}

	private function setDebug( $setActive = false ) {
		if ( ! $setActive ) {
			error_reporting( E_ALL );
		} else {
			error_reporting( $this->StartErrorReporting );
		}
	}

	private function simpleGet() {
		return $this->CURL->doGet( \TESTURLS::getUrlSimple() );
	}

	/**
	 * Make sure we always get a protocol
	 *
	 * @param string $protocol
	 *
	 * @return string
	 */
	private function getProtocol( $protocol = 'http' ) {
		if ( empty( $protocol ) ) {
			$protocol = "http";
		}

		return $protocol;
	}

	private function urlGet( $parameters = '', $protocol = "http", $indexFile = 'index.php' ) {
		$theUrl = $this->getProtocol( $protocol ) . "://" . \TESTURLS::getUrlTests() . $indexFile . "?" . $parameters;

		return $this->CURL->doGet( $theUrl );
	}

	private function urlPost( $parameters = array(), $protocol = "http", $indexFile = 'index.php' ) {
		$theUrl = $this->getProtocol( $protocol ) . "://" . \TESTURLS::getUrlTests() . $indexFile;

		return $this->CURL->doPost( $theUrl, $parameters );
	}

	private function hasBody( $container ) {
		if ( is_array( $container ) && isset( $container['body'] ) ) {
			return true;
		}
		if ( is_object( $container ) ) {
			if ( is_string( $container->getBody() ) ) {
				return true;
			}
		}

		return false;
	}

	private function getBody( $container ) {
		if ( is_object( $container ) ) {
			return $container->getBody();
		} else {
			return $this->CURL->getBody();
		}

		return "";
	}

	private function getParsed( $container ) {
		if ( $this->hasBody( $container ) ) {
			if ( is_object( $container ) ) {
				return $container->getParsed();
			}

			return $container['parsed'];
		}

		return null;
	}

	/*function testSimpleGetProxy() {
		$this->pemDefault();
		exec( "service tor status", $ubuntuService );
		$serviceFound = false;
		foreach ( $ubuntuService as $row ) {
			// Unsafe control
			if ( preg_match( "/loaded: loaded/i", $row ) ) {
				$serviceFound = true;
			}
		}
		if ( $serviceFound ) {
			$this->CURL->setProxy( "127.0.0.1:9050", CURLPROXY_SOCKS5 );
			$container = $this->simpleGet();
			$ipType    = $this->NET->getArpaFromAddr( $this->CURL->getResponseBody( $container ), true );
			static::assertTrue( $ipType > 0 );

			return;
		}
		$this->markTestSkipped( "I can't test this simpleGetProxy since there are no tor service installed" );
	}*/

	/*	function testSimpleGetWsdlProxy() {
			$this->pemDefault();
			exec( "service tor status", $ubuntuService );
			$serviceFound = false;
			foreach ( $ubuntuService as $row ) {
				// Unsafe control
				if ( preg_match( "/loaded: loaded/i", $row ) ) {
					$serviceFound = true;
				}
			}
			if ( $serviceFound ) {
				$this->CURL->setProxy( "127.0.0.1:9050", CURLPROXY_SOCKS5 );
				$container = $this->getBody($this->CURL->doGet("https://" . $this->Urls['soap']));
				return;
			}
			$this->markTestSkipped( "I can't test this simpleGetProxy since there are no tor service installed" );
		}*/


	/**
	 * @test
	 * @testdox Runs a simple test to see if there is a container as it should
	 */
	function simpleGetUrl() {
		$this->pemDefault();
		$container = $this->simpleGet();
		static::assertTrue( $this->hasBody( $container ) );
	}

	/**
	 * @test
	 * @testdox Fetch a response and immediately pick up the parsed response, from the internally stored last response
	 */
	function getParsedSelf() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ParsedResponse = $this->CURL->getParsed();
		static::assertTrue( is_object( $ParsedResponse ) );
	}

	/**
	 * @test
	 * @testdox Make a direct call to the curl library
	 */
	function quickInitParsed() {
		$tempCurl = new MODULE_CURL( "https://identifier.tornevall.net/?json" );
		static::assertTrue( is_object( $tempCurl->getParsed() ) );
	}

	/**
	 * @test
	 * @testdox Make a direct call to the curl library and get the response code
	 */
	function quickInitResponseCode() {
		$tempCurl = new MODULE_CURL( "https://identifier.tornevall.net/?json" );
		static::assertTrue( $tempCurl->getCode() == 200 );
	}

	/**
	 * @test
	 * @testdox Make a direct call to the curl library and get the content of the body
	 */
	function quickInitResponseBody() {
		$tempCurl = new MODULE_CURL( "https://identifier.tornevall.net/?json" );
		// Some content must exists in the body
		static::assertTrue( strlen( $tempCurl->getBody() ) >= 10 );
	}

	/**
	 * @test
	 * @testdox Fetch a response and immediately pick up the parsed response, from own content
	 */
	function getParsedFromResponse() {
		$this->pemDefault();
		$container      = $this->urlGet( "ssl&bool&o=json&method=get" );
		$ParsedResponse = $this->CURL->getParsed( $container );
		static::assertTrue( is_object( $ParsedResponse ) );
	}

	/**
	 * @test
	 * @testdox Request a specific value from a parsed response
	 */
	function getParsedValue() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$pRes      = $this->CURL->getParsed();
		$ValueFrom = $this->CURL->getValue( 'methods' );
		static::assertTrue( is_object( $ValueFrom->_REQUEST ) );
	}

	/**
	 * @test
	 * @testdox Request a nested value from a parsed response
	 */
	function getParsedSubValue() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ValueFrom = $this->CURL->getValue( array( 'nesting', 'subarr4', 'child4' ) );
		static::assertTrue( count( $ValueFrom ) === 3 );
	}

	/**
	 * @test
	 * @testdox Request a value by sending wrong value into the parser (crash test)
	 */
	function getParsedSubValueNoArray() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ValueFrom = $this->CURL->getValue( new \stdClass() );
		static::assertTrue( empty( $ValueFrom ) );
	}

	/**
	 * @test
	 * @testdox Request a value that does not exist in a parsed response (Receive an exception)
	 */
	function getParsedSubValueFail() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ExpectFailure = false;
		try {
			$this->CURL->getValue( array( 'nesting', 'subarrfail' ) );
		} catch ( \Exception $parseException ) {
			$ExpectFailure = true;
		}
		static::assertTrue( $ExpectFailure );
	}

	/**
	 * @test
	 * @testdox Test if a web request has a valid body
	 */
	function getValidBody() {
		$this->pemDefault();
		$container = $this->simpleGet();
		$testBody  = $this->getBody( $container );
		static::assertTrue( ! empty( $testBody ) );
	}

	/**
	 * @test
	 * @testdox Receive a standard 200 code
	 */
	function getSimple200() {
		$this->pemDefault();
		$this->simpleGet();
		static::assertTrue( $this->CURL->getCode() == 200 );
	}

	/**
	 * @test
	 * @testdox Test SSL based web request
	 */
	function getSslUrl() {
		$this->pemDefault();
		$container = $this->urlGet( "ssl&bool", "https" );
		$testBody  = $this->getBody( $container );
		static::assertTrue( $this->getBody( $container ) && ! empty( $testBody ) );
	}

	/**
	 * @test
	 * @testdox Get exception on self signed certifications (we get error code 60)
	 */
	function getSslSelfSignedException() {
		$this->pemDefault();
		try {
			$this->CURL->doGet( \TESTURLS::getUrlSelfSigned() );
		} catch ( \Exception $e ) {
			// CURLE_PEER_FAILED_VERIFICATION = 51
			// CURLE_SSL_CACERT = 60
			static::assertTrue( $e->getCode() == 60 || $e->getCode() == 51 || $e->getCode() == 500 || $e->getCode() === NETCURL_EXCEPTIONS::NETCURL_SETSSLVERIFY_UNVERIFIED_NOT_SET, $e->getCode() );
		}
	}

	/**
	 * @test
	 * @testdox Get exception on mismatching certificates (host != certifcate host)
	 */
	function sslMismatching() {
		$this->pemDefault();
		try {
			$this->CURL->doGet( \TESTURLS::getUrlSelfSigned() );
		} catch ( \Exception $e ) {
			// CURLE_PEER_FAILED_VERIFICATION = 51
			// CURLE_SSL_CACERT = 60
			static::assertTrue( $e->getCode() == 60 || $e->getCode() == 51 || $e->getCode() == 500 || $e->getCode() === NETCURL_EXCEPTIONS::NETCURL_SETSSLVERIFY_UNVERIFIED_NOT_SET );
		}
	}

	/**
	 * @test
	 */
	function sslSelfSignedIgnore() {
		try {
			$this->CURL->setStrictFallback( true );
			$this->CURL->setSslVerify( true, true );
			$container = $this->CURL->getParsed( $this->CURL->doGet( \TESTURLS::getUrlSelfSigned() . "/tests/tornevall_network/index.php?o=json&bool" ) );
			if ( is_object( $container ) ) {
				static::assertTrue( isset( $container->methods ) );
			}
		} catch ( \Exception $e ) {
			static::markTestSkipped( "Got exception " . $e->getCode() . ": " . $e->getMessage() );
		}
	}

	/**
	 * @test
	 * @testdox Test that initially allows unverified ssl certificates should make netcurl to first call the url in a correct way and then, if this fails, make a quite risky failover into unverified mode - silently.
	 */
	function sslSelfSignedUnverifyOnRun() {
		$this->pemDefault();
		try {
			$this->CURL->setSslVerify( false );
			$container = $this->CURL->getParsed( $this->CURL->doGet( \TESTURLS::getUrlSelfSigned() . "/tests/tornevall_network/index.php?o=json&bool" ) );
			// The hasErrors function should return at least one error here
			if ( is_object( $container ) && ! $this->CURL->hasErrors() ) {
				static::assertTrue( isset( $container->methods ) );
			}
		} catch ( \Exception $e ) {
			static::markTestSkipped( "Got exception " . $e->getCode() . ": " . $e->getMessage() );
		}
	}

	/**
	 * @test
	 * @testdox Test parsed json response
	 */
	function getJson() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		static::assertTrue( is_object( $this->CURL->getParsed()->methods->_GET ) );
	}

	/**
	 * @test
	 * @testdox Check if we can parse a serialized response
	 */
	function getSerialize() {
		$this->pemDefault();
		$container = $this->urlGet( "ssl&bool&o=serialize&method=get" );
		$parsed    = $this->CURL->getParsed( $container );
		static::assertTrue( is_array( $parsed['methods']['_GET'] ) );
	}

	/**
	 * @test
	 * @testdox Test if XML/Serializer are parsed correctly
	 */
	function getXmlSerializer() {
		if ( ! class_exists( 'XML_Serializer' ) ) {
			static::markTestIncomplete( 'XML_Serializer test can not run without XML_Serializer' );

			return;
		}
		$this->pemDefault();
		// XML_Serializer
		$container = $this->getParsed( $this->urlGet( "ssl&bool&o=xml&method=get" ) );
		static::assertTrue( isset( $container->using ) && is_object( $container->using ) && $container->using['0'] == "XML/Serializer" );
	}

	/**
	 * @test
	 * @testdox Test if SimpleXml are parsed correctly
	 */
	function getSimpleXml() {
		$this->pemDefault();
		// SimpleXMLElement
		$container = $this->getParsed( $this->urlGet( "ssl&bool&o=xml&method=get&using=SimpleXMLElement" ) );
		static::assertTrue( isset( $container->using ) && is_object( $container->using ) && $container->using == "SimpleXMLElement" );
	}

	/**
	 * @test
	 * @testdox Test if a html response are converted to a proper array
	 */
	function getSimpleDom() {
		$this->pemDefault();
		// setParseHtml is no longer necessary
		//$this->CURL->setParseHtml( true );
		$container = null;
		try {
			$container = $this->getParsed( $this->urlGet( "ssl&bool&o=xml&method=get&using=SimpleXMLElement", null, "simple.html" ) );
		} catch ( \Exception $e ) {

		}
		// ByNodes, ByClosestTag, ById
		static::assertTrue( isset( $container['ById'] ) && count( $container['ById'] ) > 0 );
	}

	/**
	 * @test
	 */
	function getSimpleDomChain() {
		/** @var MODULE_CURL $getRequest */
		$getRequest = $this->urlGet( "ssl&bool&o=xml&method=get&using=SimpleXMLElement", null, "simple.html" );
		if ( method_exists( $getRequest, 'getParsed' ) ) {
			$parsed = $getRequest->getParsed();
			$dom    = $getRequest->getDomById();
		} else {
			static::markTestIncomplete( "For some reason $getRequest->getParsed() does not exist (PHP " . PHP_VERSION . ")" );

			return;
		}
		static::assertTrue( isset( $parsed['ByNodes'] ) && isset( $dom['html'] ) );
	}

	/***************
	 *  SSL TESTS  *
	 **************/

	/**
	 * @test
	 * @testdox SSL Certificates at custom location. Expected Result: Successful lookup with verified peer
	 */
	function sslCertLocation() {
		$successfulVerification = false;
		try {
			$this->CURL->setSslPemLocations( array( __DIR__ . "/ca-certificates.crt" ) );
			$this->getParsed( $this->urlGet( "ssl&bool&o=json", "https" ) );
			$successfulVerification = true;
		} catch ( \Exception $e ) {
		}
		static::assertTrue( $successfulVerification );
	}

	/**
	 * @test
	 */
	function setInternalPemLocation() {
		static::assertTrue( $this->CURL->setSslPemLocations( array( __DIR__ . "/ca-certificates.crt" ) ) );
	}

	/**
	 * @test
	 */
	function setInternalPemLocationBadFormat() {
		try {
			$this->CURL->setSslPemLocations( array( __DIR__ . "/" ) );
		} catch ( \Exception $e ) {
			static::assertTrue( $e->getCode() == NETCURL_EXCEPTIONS::NETCURL_PEMLOCATIONDATA_FORMAT_ERROR );
		}
	}

	/**
	 * @test
	 * @throws \Exception
	 */
	function unExistentCertificateBundle() {
		$this->CURL->setFlag( 'OVERRIDE_CERTIFICATE_BUNDLE', '/failCertBundle' );
		$this->CURL->setTrustedSslBundles( true );
		try {
			$this->getParsed( $this->urlGet( "ssl&bool&o=json", "https" ) );
		} catch ( \Exception $e ) {
			$assertThis = false;
			$errorCode  = $e->getCode();
			// CURLE_SSL_CACERT_BADFILE
			if ( intval( $errorCode ) == 77 ) {
				$assertThis = true;
			}
			static::assertTrue( $assertThis, $e->getMessage() . " (" . $e->getCode() . ")" );
		}
	}

	/**
	 * @test
	 * @testdox SSL Certificates are missing and certificate location is mismatching. Expected Result: Failing the url call
	 */
	function failingSsl() {
		$successfulVerification = true;
		try {
			$this->CURL->setSslVerify( true );
			$this->CURL->setStrictFallback( false );
			$this->CURL->doGet( \TESTURLS::getUrlMismatching() );
		} catch ( \Exception $e ) {
			$successfulVerification = false;
		}
		static::assertFalse( $successfulVerification );
	}

	/**
	 * @test
	 * @testdox Test the customized ip address
	 */
	function customIpAddrSimple() {
		$this->pemDefault();
		$returnedExecResponse = $this->getIpListByIpRoute();
		// Probably a bad shortcut for some systems, but it works for us in tests
		if ( ! empty( $returnedExecResponse ) && is_array( $returnedExecResponse ) ) {
			$NETWORK = new MODULE_NETWORK();
			$ipArray = array();
			foreach ( $returnedExecResponse as $ip ) {
				// Making sure this test is running safely with non locals only
				if ( ! in_array( $ip, $ipArray ) && $NETWORK->getArpaFromAddr( $ip, true ) > 0 && ! preg_match( "/^10\./", $ip ) && ! preg_match( "/^172\./", $ip ) && ! preg_match( "/^192\./", $ip ) ) {
					$ipArray[] = $ip;
				}
			}
			$this->CURL->IpAddr = $ipArray;
			$CurlJson           = $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() );
			static::assertNotEmpty( $this->CURL->getParsed()->ip );
		}
	}

	/**
	 * @test
	 * @testdox Test custom ip address setup (if more than one ip is set on the interface)
	 */
	function customIpAddrAllString() {
		$this->pemDefault();
		$ipArray              = array();
		$responses            = array();
		$returnedExecResponse = $this->getIpListByIpRoute();
		if ( ! empty( $returnedExecResponse ) && is_array( $returnedExecResponse ) ) {
			$NETWORK     = new MODULE_NETWORK();
			$lastValidIp = null;
			foreach ( $returnedExecResponse as $ip ) {
				// Making sure this test is running safely with non locals only
				if ( ! in_array( $ip, $ipArray ) && $NETWORK->getArpaFromAddr( $ip, true ) > 0 && ! preg_match( "/^10\./", $ip ) && ! preg_match( "/^172\./", $ip ) && ! preg_match( "/^192\./", $ip ) ) {
					$ipArray[] = $ip;
				}
			}
			if ( is_array( $ipArray ) && count( $ipArray ) > 1 ) {
				foreach ( $ipArray as $ip ) {
					$this->CURL->IpAddr = $ip;
					try {
						$CurlJson = $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() );
					} catch ( \Exception $e ) {

					}
					if ( isset( $this->CURL->getParsed()->ip ) && $this->NETWORK->getArpaFromAddr( $this->CURL->getParsed()->ip, true ) > 0 ) {
						$responses[ $ip ] = $this->CURL->getParsed()->ip;
					}
				}
			} else {
				$this->markTestSkipped( "ip address array is too short to be tested (" . print_R( $ipArray, true ) . ")" );
			}
		}
		static::assertTrue( count( $responses ) === count( $ipArray ) );
	}

	/**
	 * @test
	 * @testdox Run in default mode, when follows are enabled
	 */
	function followRedirectEnabled() {
		$this->pemDefault();
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		static::assertTrue( intval( $this->CURL->getCode( $redirectResponse ) ) >= 300 && intval( $this->CURL->getCode( $redirectResponse ) ) <= 350 && count( $redirectedUrls ) );
	}

	/**
	 * @test
	 * @testdox Run with redirect follows disabled
	 */
	function followRedirectDisabled() {
		$this->pemDefault();
		$this->CURL->setEnforceFollowLocation( false );
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		static::assertTrue( $this->CURL->getCode( $redirectResponse ) >= 300 && $this->CURL->getCode( $redirectResponse ) <= 350 && ! preg_match( "/rerun/i", $this->CURL->getBody( $redirectResponse ) ) && count( $redirectedUrls ) );
	}

	/**
	 * @test
	 * @testdox Activating the flag FOLLOWLOCATION_INTERNAL will make NetCurl make its own follow recursion
	 */
	function followRedirectDisabledFlagEnabled() {
		if ( version_compare( PHP_VERSION, '5.4.0', '<' ) ) {
			static::markTestIncomplete( 'Internal URL following may cause problems in PHP versions lower than 5.4 (' . PHP_VERSION . ')' );

			return;
		}
		$this->pemDefault();
		$this->CURL->setFlag( 'FOLLOWLOCATION_INTERNAL' );
		$this->CURL->setEnforceFollowLocation( false );
		/** @var MODULE_CURL $redirectResponse */
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		$responseCode     = $this->CURL->getCode( $redirectResponse );
		$curlBody         = $this->CURL->getBody();
		static::assertTrue( intval( $responseCode ) >= 200 && intval( $responseCode ) <= 300 && count( $redirectedUrls ) && preg_match( "/rerun/i", $curlBody ) );
	}

	/**
	 * @test
	 */
	function followRedirectManualDisable() {
		$this->pemDefault();
		$this->CURL->setEnforceFollowLocation( false );
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		static::assertTrue( $this->CURL->getCode( $redirectResponse ) >= 300 && $this->CURL->getCode( $redirectResponse ) <= 350 && ! preg_match( "/rerun/i", $this->CURL->getBody( $redirectResponse ) ) && count( $redirectedUrls ) );
	}

	/**
	 * @test
	 * @testdox Tests the overriding function setEnforceFollowLocation and the setCurlOpt-overrider. The expected result is to have setEnforceFollowLocation to be top prioritized over setCurlOpt here.
	 */
	function followRedirectManualEnableWithSetCurlOptEnforcingToFalse() {
		$this->pemDefault();
		$this->CURL->setEnforceFollowLocation( true );
		$this->CURL->setCurlOpt( CURLOPT_FOLLOWLOCATION, false );  // This is the doer since there are internal protection against the above enforcer
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		static::assertTrue( $this->CURL->getCode( $redirectResponse ) >= 300 && $this->CURL->getCode( $redirectResponse ) <= 350 && count( $redirectedUrls ) );
	}

	/**
	 * @test
	 * @testdox Test SoapClient by making a standard doGet()
	 */
	function wsdlSoapClient() {
		$assertThis = true;
		try {
			$this->CURL->setUserAgent( " +UnitSoapAgent" );
			$this->CURL->doGet( "http://" . \TESTURLS::getUrlSoap() );
		} catch ( \Exception $e ) {
			$assertThis = false;
		}
		static::assertTrue( $assertThis );
	}

	/**
	 * @test
	 * @testdox Test Soap by internal controllers
	 */
	function hasSoap() {
		static::assertTrue( $this->CURL->hasSoap() );
	}

	/**
	 * @test
	 */
	function throwableHttpCodes() {
		$this->pemDefault();
		$this->CURL->setThrowableHttpCodes();
		try {
			$this->CURL->doGet( "https://developer.tornevall.net/tests/tornevall_network/http.php?code=503" );
		} catch ( \Exception $e ) {
			static::assertTrue( $e->getCode() == 503 );

			return;
		}
	}

	/**
	 * @test
	 */
	function failUrl() {
		try {
			$this->CURL->doGet( "http://" . sha1( microtime( true ) ) );
		} catch ( \Exception $e ) {
			$errorMessage = $e->getMessage();
			static::assertTrue( ( preg_match( "/maximum tries/", $errorMessage ) ? true : false ) );
		}
	}

	/**
	 * @test
	 */
	public function setCurlOpt() {
		$oldCurl = $this->CURL->getCurlOpt();
		$this->CURL->setCurlOpt( array( CURLOPT_CONNECTTIMEOUT => 10 ) );
		$newCurl = $this->CURL->getCurlOpt();
		static::assertTrue( $oldCurl[ CURLOPT_CONNECTTIMEOUT ] != $newCurl[ CURLOPT_CONNECTTIMEOUT ] );
	}

	/**
	 * @test
	 */
	public function getCurlOpt() {
		$newCurl = $this->CURL->getCurlOptByKeys();
		static::assertTrue( isset( $newCurl['CURLOPT_CONNECTTIMEOUT'] ) );
	}

	/**
	 * @test
	 */
	function unsetFlag() {
		$first = $this->CURL->setFlag( "CHAIN", true );
		$this->CURL->unsetFlag( "CHAIN" );
		$second = $this->CURL->hasFlag( "CHAIN" );
		static::assertTrue( $first && ! $second );
	}

	/**
	 * @test
	 */
	function chainGet() {
		if ( version_compare( PHP_VERSION, '5.4.0', '>=' ) ) {
			$this->CURL->setFlag( "CHAIN" );
			static::assertTrue( method_exists( $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() ), 'getParsedResponse' ) );
			$this->CURL->unsetFlag( "CHAIN" );
		} else {
			static::markTestSkipped( 'Chaining PHP is not available in PHP version under 5.4 (This is ' . PHP_VERSION . ')' );
		}
	}

	/**
	 * @test
	 */
	function tlagEmptyKey() {
		try {
			$this->CURL->setFlag();
		} catch ( \Exception $setFlagException ) {
			static::assertTrue( $setFlagException->getCode() > 0 );
		}
	}

	/**
	 * @test
	 */
	function chainByInit() {
		$Chainer = new MODULE_CURL( null, null, null, array( "CHAIN" ) );
		if ( version_compare( PHP_VERSION, '5.4.0', '>=' ) ) {
			static::assertTrue( is_object( $Chainer->doGet( \TESTURLS::getUrlSimpleJson() )->getParsedResponse() ) );
		} else {
			static::markTestIncomplete( "Chaining can't be tested from PHP " . PHP_VERSION );
		}
	}

	/**
	 * @test
	 */
	function chainGetFail() {
		$this->CURL->unsetFlag( "CHAIN" );
		static::assertFalse( method_exists( $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() ), 'getParsedResponse' ) );
	}

	/**
	 * @test
	 */
	function getGitInfo() {
		try {
			$NetCurl              = $this->NETWORK->getGitTagsByUrl( "https://userCredentialsBanned@" . $this->bitBucketUrl );
			$GuzzleLIB            = $this->NETWORK->getGitTagsByUrl( "https://github.com/guzzle/guzzle.git" );
			$GuzzleLIBNonNumerics = $this->NETWORK->getGitTagsByUrl( "https://github.com/guzzle/guzzle.git", true, true );
			static::assertTrue( count( $NetCurl ) >= 0 && count( $GuzzleLIB ) >= 0 );
		} catch ( \Exception $e ) {

		}
	}

	/**
	 * @test
	 */
	function getGitIsTooOld() {
		// curl module for netcurl will probably always be lower than the netcurl-version, so this is a good way of testing
		static::assertTrue( $this->NETWORK->getVersionTooOld( "1.0.0", "https://" . $this->bitBucketUrl ) );
	}

	/**
	 * @test
	 */
	function getGitCurrentOrNewer() {
		// curl module for netcurl will probably always be lower than the netcurl-version, so this is a good way of testing
		$tags           = $this->NETWORK->getGitTagsByUrl( "https://" . $this->bitBucketUrl );
		$lastTag        = array_pop( $tags );
		$lastBeforeLast = array_pop( $tags );
		// This should return false, since the current is not too old
		$isCurrent = $this->NETWORK->getVersionTooOld( $lastTag, "https://" . $this->bitBucketUrl );
		// This should return true, since the last version after the current is too old
		$isLastBeforeCurrent = $this->NETWORK->getVersionTooOld( $lastBeforeLast, "https://" . $this->bitBucketUrl );
		static::assertTrue( $isCurrent === false || $isLastBeforeCurrent === true );
	}

	/**
	 * @test
	 */
	function timeoutChecking() {
		$def = $this->CURL->getTimeout();
		$this->CURL->setTimeout( 6 );
		$new = $this->CURL->getTimeout();
		static::assertTrue( $def['connecttimeout'] == 300 && $def['requesttimeout'] == 0 && $new['connecttimeout'] == 3 && $new['requesttimeout'] == 6 );
	}

	/**
	 * @test
	 */
	function internalException() {
		static::assertTrue( $this->NETWORK->getExceptionCode( 'NETCURL_EXCEPTION_IT_WORKS' ) == 1 );
	}

	/**
	 * @test
	 */
	function internalExceptionNoExists() {
		static::assertTrue( $this->NETWORK->getExceptionCode( 'NETCURL_EXCEPTION_IT_DOESNT_WORK' ) == 500 );
	}

	/**
	 * @test
	 */
	function driverControlList() {
		$driverList = array();
		try {
			$driverList = $this->CURL->getDrivers();
		} catch ( \Exception $e ) {
			echo $e->getMessage();
		}
		static::assertTrue( count( $driverList ) > 0 );
	}

	/**
	 * @test
	 */
	function driverControlNoList() {
		$driverList = false;
		try {
			$driverList = $this->CURL->getAvailableDrivers();
		} catch ( \Exception $e ) {
			echo $e->getMessage() . "\n";
		}
		static::assertTrue( is_array( $driverList ) );
	}

	/**
	 * @test
	 */
	public function getCurrentProtocol() {
		$oneOfThenm = MODULE_NETWORK::getCurrentServerProtocol( true );
		static::assertTrue( $oneOfThenm == "http" || $oneOfThenm == "https" );
	}

	/**
	 * @test
	 */
	function getSupportedDrivers() {
		static::assertTrue( count( $this->CURL->getSupportedDrivers() ) > 0 );
	}

	/**
	 * @test
	 */
	function setAutoDriver() {
		$driverset = $this->CURL->setDriverAuto();
		static::assertTrue( $driverset > 0 );
	}

	/**
	 * @test
	 */
	function getJsonByConstructor() {
		$quickCurl        = new MODULE_CURL( \TESTURLS::getUrlSimpleJson() );
		$identifierByJson = $quickCurl->getParsed();
		static::assertTrue( isset( $identifierByJson->ip ) );
	}

	/**
	 * @test
	 */
	function extractDomainIsGetUrlDomain() {
		static::assertCount( 3, $this->NETWORK->getUrlDomain( "https://www.aftonbladet.se/uri/is/here" ) );
	}

	/**
	 * @test
	 * @testdox Safe mode and basepath cechking without paramters - in our environment, open_basedir is empty and safe_mode is off
	 */
	function getSafePermissionFull() {
		static::assertFalse( $this->CURL->getIsSecure() );
	}

	/**
	 * @test
	 * @testdox Open_basedir is secured and (at least in our environment) safe_mode is disabled
	 */
	function getSafePermissionFullMocked() {
		ini_set( 'open_basedir', "/" );
		static::assertTrue( $this->CURL->getIsSecure() );
		// Reset the setting as it is affecting other tests
		ini_set( 'open_basedir', "" );
	}

	/**
	 * @test
	 * @testdox open_basedir is safe and safe_mode-checking will be skipped
	 */
	function getSafePermissionFullMockedNoSafeMode() {
		ini_set( 'open_basedir', "/" );
		static::assertTrue( $this->CURL->getIsSecure( false ) );
		// Reset the setting as it is affecting other tests
		ini_set( 'open_basedir', "" );
	}

	/**
	 * @test
	 * @testdox open_basedir is unsafe and safe_mode is mocked-active
	 */
	function getSafePermissionFullMockedSafeMode() {
		ini_set( 'open_basedir', "" );
		static::assertTrue( $this->CURL->getIsSecure( true, true ) );
	}

	/**
	 * @test
	 * @testdox LIB-212
	 */
	function hasSsl() {
		static::assertTrue( $this->CURL->hasSsl() );
	}

	/**
	 * @test
	 */
	public function getParsedDom() {
		$this->CURL->setParseHtml( true );
		/** @var MODULE_CURL $content */
		$phpAntiChain = $this->urlGet( "ssl&bool&o=xml&method=get&using=SimpleXMLElement", null, "simple.html" );  // PHP 5.3 compliant
		if ( method_exists( $phpAntiChain, 'getDomById' ) ) {
			$content = $phpAntiChain->getDomById();
			static::assertTrue( isset( $content['divElement'] ) );
			$this->CURL->setParseHtml( false );
		} else {
			static::markTestIncomplete( "getDomById is unreachable (PHP v" . PHP_VERSION . ")" );
		}
	}

	/**
	 * @test
	 * @testdox Activation of storing cookies locally
	 */
	public function enableLocalCookiesInSysTemp() {
		$this->CURL->setLocalCookies( true );
		try {
			$this->CURL->setFlag( 'NETCURL_COOKIE_TEMP_LOCATION', true );
		} catch ( \Exception $e ) {

		}
		// For Linux based systems, we go through /tmp
		static::assertStringStartsWith( "/tmp/netcurl", $this->CURL->getCookiePath() );
	}

	/**
	 * @test
	 * @throws \Exception
	 */
	public function enableLocalCookiesInSysTempProhibited() {
		$this->CURL->setLocalCookies( true );
		static::assertEquals( '', $this->CURL->getCookiePath() );
	}

	/**
	 * @test
	 * @testdox Set own temporary directory (remove it first so tests gives correct responses) - also testing directory creation
	 * @throws \Exception
	 */
	public function enableLocalCookiesSelfLocated() {
		$this->CURL->setLocalCookies( true );
		@rmdir( "/tmp/netcurl_self" );
		$this->CURL->setFlag( 'NETCURL_COOKIE_LOCATION', '/tmp/netcurl_self' );
		// For Linux based systems, we go through /tmp
		static::assertStringStartsWith( "/tmp/netcurl_self", $this->CURL->getCookiePath() );
		@rmdir( "/tmp/netcurl_self" );
	}

	/**
	 * @test
	 */
	function responseTypeHttpObject() {
		$this->CURL->setResponseType( NETCURL_RESPONSETYPE::RESPONSETYPE_OBJECT );
		/** @var NETCURL_HTTP_OBJECT $request */
		$request = $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() );
		$parsed  = $request->getParsed();
		static::assertTrue( get_class( $request ) == 'TorneLIB\NETCURL_HTTP_OBJECT' && is_object( $parsed ) && isset( $parsed->ip ) );
	}

	/**
	 * @test
	 * @testdox Request urls with NETCURL_HTTP_OBJECT
	 * @throws \Exception
	 */
	function responseTypeHttpObjectChain() {
		$this->CURL->setResponseType( NETCURL_RESPONSETYPE::RESPONSETYPE_OBJECT );
		/** @var NETCURL_HTTP_OBJECT $request */
		$request = $this->CURL->doGet( \TESTURLS::getUrlSimpleJson() )->getParsed();
		static::assertTrue( is_object( $request ) && isset( $request->ip ) );
	}

	/**
	 * @test
	 * @testdox Testing that switching between driverse (SOAP) works - when SOAP is not used, NetCURL should switch back to the regular driver
	 */
	function multiCallsSwitchingBetweenRegularAndSoap() {
		if ( version_compare( PHP_VERSION, '5.4.0', '<' ) ) {
			static::markTestIncomplete( "Multicall switching test is not compliant with PHP 5.3 - however, the function switching itself is supported" );

			return;
		}

		$driversUsed = array(
			'1' => 0,
			'2' => 0
		);

		$this->disableSslVerifyByPhpVersions( true );
		try {
			$this->CURL->setAuthentication( 'atest', 'atest' );
			$this->CURL->doGet( "http://identifier.tornevall.net/?json" )->getParsed();
			$driversUsed[ $this->CURL->getDriverById() ] ++;
			$this->CURL->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' )->getPaymentMethods();
			$driversUsed[ $this->CURL->getDriverById() ] ++;
			$this->CURL->doGet( "http://identifier.tornevall.net/?json" )->getParsed();
			$driversUsed[ $this->CURL->getDriverById() ] ++;
			$this->CURL->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' )->getPaymentMethods();
			$driversUsed[ $this->CURL->getDriverById() ] ++;
			$this->CURL->doGet( "http://identifier.tornevall.net/?json" )->getParsed();
			$driversUsed[ $this->CURL->getDriverById() ] ++;

			$this->assertTrue( $driversUsed[1] == 3 && $driversUsed[2] == 2 ? true : false );
		} catch ( \Exception $e ) {
			if ( $e->getCode() < 3 ) {
				static::markTestSkipped( 'Getting exception codes below 3 here, might indicate that your cacerts is not installed properly' );

				return;
			}
			throw new \Exception( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * @test
	 * @testdox Another way to extract stuff on
	 * @throws \Exception
	 */
	function soapIoParse() {
		if ( ! class_exists( 'TorneLIB\MODULE_IO' ) ) {
			static::markTestSkipped( "MODULE_IO is missing, this test is skipped" );

			return;
		}

		$this->disableSslVerifyByPhpVersions( true );
		$this->CURL->setAuthentication( 'atest', 'atest' );
		try {
			$php53UnChainified = $this->CURL->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' );
			$php53UnChainified->getPaymentMethods();
			$IO  = new MODULE_IO();
			$XML = $IO->getFromXml( $this->CURL->getBody(), true );
			$id  = ( isset( $XML[0] ) && isset( $XML[0]->id ) ? $XML[0]->id : null );
			$this->assertTrue( strlen( $id ) > 0 ? true : false );
		} catch ( \Exception $e ) {
			if ( $e->getCode() < 3 ) {
				static::markTestSkipped( 'Getting exception codes below 3 here, might indicate that your cacerts is not installed properly' );

				return;
			}
			throw new \Exception( $e->getMessage(), $e->getCode(), $e );
		}
	}

}

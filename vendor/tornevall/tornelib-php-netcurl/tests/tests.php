<?php

namespace TorneLIB;

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
}
if ( file_exists( __DIR__ . "/../tornelib.php" ) ) {
	// Work with TorneLIBv5
	require_once( __DIR__ . '/../tornelib.php' );
}

use Nette\Neon\Exception;
use PHPUnit\Framework\TestCase;

ini_set( 'memory_limit', - 1 );    // Free memory limit, some tests requires more memory (like ip-range handling)

class Tornevall_cURLTest extends TestCase {
	private $StartErrorReporting;

	/** @var TorneLIB_Network */
	private $NET;
	/** @var Tornevall_cURL */
	private $CURL;
	/** @var TorneLIB_Crypto */
	private $Urls;
	private $TorSetupAddress = "127.0.0.1:9050";
	private $TorSetupType = 4;      /* CURLPROXY_SOCKS4*/
	private $CurlVersion = null;

	public $specUrlUsername;
	public $specUrlPassword;
	/** @var bool Enables especially special SOAP features to be tested */
	private $skipSpecials = true;
	private $skipTor = true;

	//function tearDown() {}
	function setUp() {
		//$this->setDebug(true);
		$this->StartErrorReporting = error_reporting();
		$this->NET                 = new \TorneLIB\TorneLIB_Network();
		$this->CURL                = new \TorneLIB\Tornevall_cURL();
		$this->CURL->setUserAgent( "TorneLIB/NetCurl-PHPUNIT" );

		if ( function_exists( 'curl_version' ) ) {
			$CurlVersionRequest = curl_version();
			$this->CurlVersion  = $CurlVersionRequest['version'];
		}

		/*
		 * Enable test mode
		 */
		$this->CURL->setTestEnabled();
		$this->CURL->setSslUnverified( false );

		/*
		 * Set up testing URLS
		 */
		$this->Urls = array(
			'simple'      => 'http://identifier.tornevall.net/',
			'simplejson'  => 'http://identifier.tornevall.net/?json',
			'tests'       => 'developer.tornevall.net/tests/tornevall_network/',
			'soap'        => 'developer.tornevall.net/tests/tornevall_network/index.wsdl?wsdl',
			'httpcode'    => 'developer.tornevall.net/tests/tornevall_network/http.php',
			'selfsigned'  => 'https://dev-ssl-self.tornevall.nu',
			'mismatching' => 'https://dev-ssl-mismatch.tornevall.nu',
		);
	}

	function tearDown() {
		// DebugData collects stats about the curled session.
		// $debugData = $this->CURL->getDebugData();
	}

	/*public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
	}*/

	private function pemDefault() {
		$this->CURL->setFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION', false );
		//$this->CURL->setSslUnverified( true );
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
		return $this->CURL->doGet( $this->Urls['simple'] );
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
		$theUrl = $this->getProtocol( $protocol ) . "://" . $this->Urls['tests'] . $indexFile . "?" . $parameters;

		return $this->CURL->doGet( $theUrl );
	}

	private function urlPost( $parameters = array(), $protocol = "http", $indexFile = 'index.php' ) {
		$theUrl = $this->getProtocol( $protocol ) . "://" . $this->Urls['tests'] . $indexFile;

		return $this->CURL->doPost( $theUrl, $parameters );
	}

	private function hasBody( $container ) {
		if ( is_array( $container ) && isset( $container['body'] ) ) {
			return true;
		}
		if ( is_object( $container ) ) {
			if ( is_string( $container->getResponseBody() ) ) {
				return true;
			}
		}

		return false;
	}

	private function getBody( $container ) {
		if ( is_object( $container ) ) {
			return $container->getResponseBody();
		} else {
			return $this->CURL->getResponseBody();
		}

		return "";
	}

	private function getParsed( $container ) {
		if ( $this->hasBody( $container ) ) {
			if ( is_object( $container ) ) {
				return $container->getParsedResponse();
			}

			return $container['parsed'];
		}

		return null;
	}

	/*
	function ignoreTestNoSsl()
	{
		if ($this->CURL->hasSsl()) {
			$this->markTestSkipped("This instance seems to have SSL available so we can't assume it doesn't");
		} else {
			$this->assertFalse($this->CURL->hasSsl());
		}
	}
	*/

	/**
	 * Runs a simple test to see if there is a container as it should
	 */
	function testSimpleGet() {
		$this->pemDefault();
		$container = $this->simpleGet();
		$this->assertTrue( $this->hasBody( $container ) );
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
			$this->assertTrue( $ipType > 0 );

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
	 * Fetch a response and immediately pick up the parsed response, from the internally stored last response
	 */
	function testGetParsedSelf() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ParsedResponse = $this->CURL->getParsedResponse();
		$this->assertTrue( is_object( $ParsedResponse ) );
	}

	/**
	 * Make a direct call to the curl library
	 */
	function testQuickInitParsed() {
		$TempCurl = new Tornevall_cURL( "https://identifier.tornevall.net/?json" );
		$this->assertTrue( is_object( $TempCurl->getParsedResponse() ) );
	}

	/**
	 * Make a direct call to the curl library and get the response code
	 */
	function testQuickInitResponseCode() {
		$TempCurl = new Tornevall_cURL( "https://identifier.tornevall.net/?json" );
		$this->assertTrue( $TempCurl->getResponseCode() == 200 );
	}

	/**
	 * Make a direct call to the curl library and get the content of the body
	 */
	function testQuickInitResponseBody() {
		$TempCurl = new Tornevall_cURL( "https://identifier.tornevall.net/?json" );
		// Some content must exists in the body
		$this->assertTrue( strlen( $TempCurl->getResponseBody() ) >= 10 );
	}

	/**
	 * Fetch a response and immediately pick up the parsed response, from own content
	 */
	function testGetParsedFromResponse() {
		$this->pemDefault();
		$container      = $this->urlGet( "ssl&bool&o=json&method=get" );
		$ParsedResponse = $this->CURL->getParsedResponse( $container );
		$this->assertTrue( is_object( $ParsedResponse ) );
	}

	/**
	 * Request a specific value from a parsed response
	 */
	function testGetParsedValue() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$this->CURL->getParsedResponse();
		$ValueFrom = $this->CURL->getParsedValue( 'methods' );
		$this->assertTrue( is_object( $ValueFrom->_REQUEST ) );
	}

	/**
	 * Request a nested value from a parsed response
	 */
	function testGetParsedSubValue() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ValueFrom = $this->CURL->getParsedValue( array( 'nesting', 'subarr4', 'child4' ) );
		$this->assertTrue( count( $ValueFrom ) === 3 );
	}

	/**
	 * Request a value by sending wrong value into the parser (crash test)
	 */
	function testGetParsedSubValueNoArray() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ValueFrom = $this->CURL->getParsedValue( new \stdClass() );
		$this->assertTrue( empty( $ValueFrom ) );
	}

	/**
	 * Request a value that does not exist in a parsed response (Receive an exception)
	 */
	function testGetParsedSubValueFail() {
		$this->pemDefault();
		$this->urlGet( "ssl&bool&o=json&method=get" );
		$ExpectFailure = false;
		try {
			$this->CURL->getParsedValue( array( 'nesting', 'subarrfail' ) );
		} catch ( \Exception $parseException ) {
			$ExpectFailure = true;
		}
		$this->assertTrue( $ExpectFailure );
	}

	/**
	 * Test if a web request has a valid body
	 */
	function testValidBody() {
		$this->pemDefault();
		$container = $this->simpleGet();
		$testBody  = $this->getBody( $container );
		$this->assertTrue( ! empty( $testBody ) );
	}

	/**
	 * Receive a standard 200 code
	 */
	function testSimple200() {
		$this->pemDefault();
		$this->simpleGet();
		$this->assertTrue( $this->CURL->getResponseCode() == 200 );
	}

	/**
	 * Test SSL based web request
	 */
	function testSslUrl() {
		$this->pemDefault();
		$container = $this->urlGet( "ssl&bool", "https" );
		$testBody  = $this->getBody( $container );
		$this->assertTrue( $this->getBody( $container ) && ! empty( $testBody ) );
	}

	function testSslSelfSignedException() {
		$this->pemDefault();
		try {
			$this->CURL->doGet( $this->Urls['selfsigned'] );
		} catch ( \Exception $e ) {
			// CURLE_PEER_FAILED_VERIFICATION = 51
			// CURLE_SSL_CACERT = 60
			$this->assertTrue( $e->getCode() == 60 || $e->getCode() == 51 || $e->getCode() == 500 || $e->getCode() === TORNELIB_NETCURL_EXCEPTIONS::NETCURL_SETSSLVERIFY_UNVERIFIED_NOT_SET );
		}
	}

	function testSslMismatching() {
		$this->pemDefault();
		try {
			$this->CURL->doGet( $this->Urls['selfsigned'] );
		} catch ( \Exception $e ) {
			// CURLE_PEER_FAILED_VERIFICATION = 51
			// CURLE_SSL_CACERT = 60
			$this->assertTrue( $e->getCode() == 60 || $e->getCode() == 51 || $e->getCode() == 500 || $e->getCode() === TORNELIB_NETCURL_EXCEPTIONS::NETCURL_SETSSLVERIFY_UNVERIFIED_NOT_SET );
		}
	}

	function testSslSelfSignedIgnore() {
		$this->pemDefault();
		try {
			$this->CURL->setSslUnverified( true );
			$this->CURL->setSslVerify( false );
			$container = $this->CURL->getParsedResponse( $this->CURL->doGet( $this->Urls['selfsigned'] . "/tests/tornevall_network/index.php?o=json&bool" ) );
			if ( is_object( $container ) ) {
				$this->assertTrue( isset( $container->methods ) );
			}
		} catch ( \Exception $e ) {
			$this->markTestSkipped( "Got exception " . $e->getCode() . ": " . $e->getMessage() );
		}
	}

	/**
	 * Test that initially allows unverified ssl certificates should make netcurl to first call the url in a correct way and then,
	 * if this fails, make a quite risky failover into unverified mode - silently.
	 */
	function testSslSelfSignedUnverifyOnRun() {
		$this->pemDefault();
		try {
			$this->CURL->setSslUnverified( true );
			$container = $this->CURL->getParsedResponse( $this->CURL->doGet( $this->Urls['selfsigned'] . "/tests/tornevall_network/index.php?o=json&bool" ) );
			// The hasErrors function should return at least one error here
			if ( is_object( $container ) && $this->CURL->hasErrors() ) {
				$this->assertTrue( isset( $container->methods ) );
			}
		} catch ( \Exception $e ) {
			$this->markTestSkipped( "Got exception " . $e->getCode() . ": " . $e->getMessage() );
		}
	}

	/**
	 * Test parsed json response
	 */
	function testGetJson() {
		$this->pemDefault();
		$container = $this->urlGet( "ssl&bool&o=json&method=get" );
		$this->assertTrue( is_object( $this->CURL->getParsedResponse()->methods->_GET ) );
	}

	/**
	 * Check if we can parse a serialized response
	 */
	function testGetSerialize() {
		$this->pemDefault();
		$container = $this->urlGet( "ssl&bool&o=serialize&method=get" );
		$this->assertTrue( is_array( $this->CURL->getParsedResponse()['methods']['_GET'] ) );
	}

	/**
	 * Test if XML/Serializer are parsed correctly
	 */
	function testGetXmlSerializer() {
		$this->pemDefault();
		// XML_Serializer
		$container = $this->getParsed( $this->urlGet( "ssl&bool&o=xml&method=get" ) );
		$this->assertTrue( isset( $container->using ) && is_object( $container->using ) && $container->using['0'] == "XML/Serializer" );
	}

	/**
	 * Test if SimpleXml are parsed correctly
	 */
	function testGetSimpleXml() {
		$this->pemDefault();
		// SimpleXMLElement
		$container = $this->getParsed( $this->urlGet( "ssl&bool&o=xml&method=get&using=SimpleXMLElement" ) );
		$this->assertTrue( isset( $container->using ) && is_object( $container->using ) && $container->using == "SimpleXMLElement" );
	}

	/**
	 * Test if a html response are converted to a proper array
	 */
	function testGetSimpleDom() {
		$this->pemDefault();
		$this->CURL->setParseHtml( true );
		try {
			$container = $this->getParsed( $this->urlGet( "ssl&bool&o=xml&method=get&using=SimpleXMLElement", null, "simple.html" ) );
		} catch ( \Exception $e ) {

		}
		// ByNodes, ByClosestTag, ById
		$this->assertTrue( isset( $container['ById'] ) && count( $container['ById'] ) > 0 );
	}

	function testGetArpaLocalhost4() {
		$this->assertTrue( $this->NET->getArpaFromIpv4( "127.0.0.1" ) === "1.0.0.127" );
	}

	function testGetArpaLocalhost6() {
		$this->assertTrue( $this->NET->getArpaFromIpv6( "::1" ) === "1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0" );
	}

	function testGetArpaLocalhost4Second() {
		$this->assertTrue( $this->NET->getArpaFromIpv4( "192.168.12.36" ) === "36.12.168.192" );
	}

	function testGetArpaLocalhost6Second() {
		$this->assertTrue( $this->NET->getArpaFromIpv6( "2a01:299:a0:ff:10:128:255:2" ) === "2.0.0.0.5.5.2.0.8.2.1.0.0.1.0.0.f.f.0.0.0.a.0.0.9.9.2.0.1.0.a.2" );
	}

	function testGetArpaLocalhost4Nulled() {
		$this->assertEmpty( $this->NET->getArpaFromIpv4( null ) );
	}

	function testGetArpaLocalhost6Nulled() {
		$this->assertEmpty( $this->NET->getArpaFromIpv6( null ) );
	}

	function testGetArpaLocalhost4String() {
		$this->assertEmpty( $this->NET->getArpaFromIpv4( "fail here" ) );
	}

	function testGetArpaLocalhost6String() {
		$this->assertEmpty( $this->NET->getArpaFromIpv6( "fail here" ) );
	}

	function testGetArpaLocalhost6CorruptString1() {
		$this->assertEmpty( $this->NET->getArpaFromIpv6( "a : b \\" ) );
	}

	function testGetArpaLocalhost6CorruptString2() {
		$badString = "";
		for ( $i = 0; $i < 255; $i ++ ) {
			$badString .= chr( $i );
		}
		$this->assertEmpty( $this->NET->getArpaFromIpv6( $badString ) );
	}

	function testOctetV6() {
		$this->assertTrue( $this->NET->getIpv6FromOctets( "2.0.0.0.5.5.2.0.8.2.1.0.0.1.0.0.f.f.0.0.0.a.0.0.9.9.2.0.1.0.a.2" ) === "2a01:299:a0:ff:10:128:255:2" );
	}

	function testGetArpaAuto4() {
		$this->assertTrue( $this->NET->getArpaFromAddr( "172.16.12.3" ) === "3.12.16.172" );
	}

	function testGetArpaAuto6() {
		$this->assertTrue( $this->NET->getArpaFromAddr( "2a00:1450:400f:802::200e" ) === "e.0.0.2.0.0.0.0.0.0.0.0.0.0.0.0.2.0.8.0.f.0.0.4.0.5.4.1.0.0.a.2" );
	}

	function testGetIpType4() {
		$this->assertTrue( $this->NET->getArpaFromAddr( "172.22.1.83", true ) === TorneLIB_Network_IP_Protocols::PROTOCOL_IPV4 );
	}

	function testGetIpType6() {
		$this->assertTrue( $this->NET->getArpaFromAddr( "2a03:2880:f113:83:face:b00c:0:25de", true ) === TorneLIB_Network_IP_Protocols::PROTOCOL_IPV6 );
	}

	function testGetIpTypeFail() {
		$this->assertTrue( $this->NET->getArpaFromAddr( "This.Aint.An.Address", true ) === TorneLIB_Network_IP_Protocols::PROTOCOL_NONE );
	}

	function testMaskRangeArray24() {
		$this->assertCount( 255, $this->NET->getRangeFromMask( "192.168.1.0/24" ) );
	}

	function testMaskRangeArray16() {
		$this->assertCount( 65535, $this->NET->getRangeFromMask( "192.168.0.0/16" ) );
	}

	function testMaskRange24() {
		$this->assertTrue( $this->NET->isIpInRange( "192.168.1.55", "192.168.1.0/24" ) );
	}

	function testMaskRange24Fail() {
		$this->assertFalse( $this->NET->isIpInRange( "192.168.2.55", "192.168.1.0/24" ) );
	}

	function testMaskRange16() {
		$this->assertTrue( $this->NET->isIpInRange( "192.168.2.55", "192.168.0.0/16" ) );
	}

	function testMaskRange8() {
		$this->assertTrue( $this->NET->isIpInRange( "172.213.9.3", "172.0.0.0/8" ) );
	}
	/*
	function testMaskRangeArray8() {
		$this->assertCount(16777215, $this->NET->getRangeFromMask("192.0.0.0/8"));
	}
	*/

	/***************
	 *  SSL TESTS  *
	 **************/

	/**
	 * Test: SSL Certificates at custom location
	 * Expected Result: Successful lookup with verified peer
	 */
	function testSslCertLocation() {
		$this->CURL->setFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION', true );
		$successfulVerification = false;
		try {
			$this->CURL->setSslPemLocations( array( __DIR__ . "/ca-certificates.crt" ) );
			$container              = $this->getParsed( $this->urlGet( "ssl&bool&o=json", "https" ) );
			$successfulVerification = true;
		} catch ( \Exception $e ) {
		}
		$this->assertTrue( $successfulVerification );
	}

	/**
	 * Test: SSL Certificates at default location
	 * Expected Result: Successful lookup with verified peer
	 */
	function testSslDefaultCertLocation() {
		$this->pemDefault();

		$successfulVerification = false;
		try {
			$container              = $this->getParsed( $this->urlGet( "ssl&bool&o=json", "https" ) );
			$successfulVerification = true;
		} catch ( \Exception $e ) {
		}
		$this->assertTrue( $successfulVerification );
	}

	/**
	 * Test: SSL Certificates are missing and certificate location is mismatching
	 * Expected Result: Failing the url call
	 */
	function testFailingSsl() {
		$this->CURL->setFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION', true );
		$successfulVerification = true;
		try {
			$this->CURL->setSslVerify( false );
			$this->CURL->setSslUnverified( true );
			$container = $this->getParsed( $this->urlGet( "ssl&bool&o=json", "https" ) );
		} catch ( \Exception $e ) {
			$successfulVerification = false;
		}
		$this->assertFalse( $successfulVerification );
	}

	/**
	 * Test: SSL Certificates are missing and peer verification is disabled
	 * Expected Result: Successful lookup with unverified peer
	 */
	function testUnverifiedSsl() {
		$this->CURL->setFlag( '_DEBUG_TCURL_UNSET_LOCAL_PEM_LOCATION', true );
		$successfulVerification = false;
		$this->CURL->setSslPemLocations( array( "non-existent-file" ), true );
		try {
			$this->CURL->setSslUnverified( true );
			$container              = $this->getParsed( $this->urlGet( "ssl&bool&o=json", "https" ) );
			$successfulVerification = true;
		} catch ( \Exception $e ) {
		}
		$this->assertTrue( $successfulVerification );
	}

	private function getIpListByIpRoute() {
		// Don't fetch 127.0.0.1
		exec( "ip addr|grep \"inet \"|sed 's/\// /'|awk '{print $2}'|grep -v ^127", $returnedExecResponse );

		return $returnedExecResponse;
	}

	/**
	 * Test the customized ip address
	 */
	function testCustomIpAddrSimple() {
		$this->pemDefault();
		$returnedExecResponse = $this->getIpListByIpRoute();
		// Probably a bad shortcut for some systems, but it works for us in tests
		if ( ! empty( $returnedExecResponse ) && is_array( $returnedExecResponse ) ) {
			$NETWORK = new TorneLIB_Network();
			$ipArray = array();
			foreach ( $returnedExecResponse as $ip ) {
				// Making sure this test is running safely with non locals only
				if ( ! in_array( $ip, $ipArray ) && $NETWORK->getArpaFromAddr( $ip, true ) > 0 && ! preg_match( "/^10\./", $ip ) && ! preg_match( "/^172\./", $ip ) && ! preg_match( "/^192\./", $ip ) ) {
					$ipArray[] = $ip;
				}
			}
			$this->CURL->IpAddr = $ipArray;
			$CurlJson           = $this->CURL->doGet( $this->Urls['simplejson'] );
			$this->assertNotEmpty( $this->CURL->getParsedResponse()->ip );
		}
	}

	/**
	 * Test custom ip address setup (if more than one ip is set on the interface)
	 */
	function testCustomIpAddrAllString() {
		$this->pemDefault();
		$ipArray              = array();
		$responses            = array();
		$returnedExecResponse = $this->getIpListByIpRoute();
		if ( ! empty( $returnedExecResponse ) && is_array( $returnedExecResponse ) ) {
			$NETWORK = new TorneLIB_Network();
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
						$CurlJson = $this->CURL->doGet( $this->Urls['simplejson'] );
					} catch ( \Exception $e ) {

					}
					if ( isset( $this->CURL->getParsedResponse()->ip ) && $this->NET->getArpaFromAddr( $this->CURL->getParsedResponse()->ip, true ) > 0 ) {
						$responses[ $ip ] = $this->CURL->getParsedResponse()->ip;
					}
				}
			} else {
				$this->markTestSkipped( "ip address array is too short to be tested (" . print_R( $ipArray, true ) . ")" );
			}
		}
		$this->assertTrue( count( $responses ) === count( $ipArray ) );
	}

	/**
	 * Test proxy by using Tor Network (Requires Tor)
	 * @link https://www.torproject.org/ Required application
	 */
	function testTorNetwork() {
		$skipThis = $this->skipTor;
		if ( $skipThis ) {
			$this->markTestSkipped( __FUNCTION__ . " is a special test for TOR Networks. Normally this is not needed" );

			return;
		}
		$this->pemDefault();
		exec( "service tor status", $ubuntuService );
		$serviceFound = false;
		foreach ( $ubuntuService as $row ) {
			// Unsafe control
			if ( preg_match( "/loaded: loaded/i", $row ) ) {
				$serviceFound = true;
			}
		}
		if ( ! $serviceFound ) {
			$this->markTestSkipped( "Skip TOR Network tests: TOR Service not found in the current control" );
		} else {
			$this->CURL->setProxy( $this->TorSetupAddress, $this->TorSetupType );
			$CurlJson = $this->CURL->doGet( $this->Urls['simplejson'] );
			$parsedIp = $this->NET->getArpaFromAddr( $this->CURL->getParsedResponse()->ip, true );
			$this->assertTrue( $parsedIp > 0 );
		}
	}

	/**
	 * Run in default mode, when follows are enabled
	 */
	function testFollowRedirectEnabled() {
		$this->pemDefault();
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		$this->assertTrue( intval( $this->CURL->getResponseCode( $redirectResponse ) ) >= 300 && intval( $this->CURL->getResponseCode( $redirectResponse ) ) <= 350 && count( $redirectedUrls ) );
	}

	/**
	 * Run with redirect follows disabled
	 */
	function testFollowRedirectDisabled() {
		$this->pemDefault();
		$this->CURL->setEnforceFollowLocation( false );
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		$this->assertTrue( $this->CURL->getResponseCode( $redirectResponse ) >= 300 && $this->CURL->getResponseCode( $redirectResponse ) <= 350 && ! preg_match( "/rerun/i", $this->CURL->getResponseBody( $redirectResponse ) ) && count( $redirectedUrls ) );
	}

	function testFollowRedirectManualDisable() {
		$this->pemDefault();
		$this->CURL->setEnforceFollowLocation( false );
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		$this->assertTrue( $this->CURL->getResponseCode( $redirectResponse ) >= 300 && $this->CURL->getResponseCode( $redirectResponse ) <= 350 && ! preg_match( "/rerun/i", $this->CURL->getResponseBody( $redirectResponse ) ) && count( $redirectedUrls ) );
	}

	/**
	 * Tests the overriding function setEnforceFollowLocation and the setCurlOpt-overrider.
	 * The expected result is to have setEnforceFollowLocation to be top prioritized over setCurlOpt here.
	 */
	function testFollowRedirectManualEnableWithSetCurlOptEnforcingToFalse() {
		$this->pemDefault();
		$this->CURL->setEnforceFollowLocation( true );
		$this->CURL->setCurlOpt( CURLOPT_FOLLOWLOCATION, false );  // This is the doer since there are internal protection against the above enforcer
		$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
		$redirectedUrls   = $this->CURL->getRedirectedUrls();
		$this->assertTrue( $this->CURL->getResponseCode( $redirectResponse ) >= 300 && $this->CURL->getResponseCode( $redirectResponse ) <= 350 && preg_match( "/rerun/i", $this->CURL->getResponseBody( $redirectResponse ) ) && count( $redirectedUrls ) );
	}

	/**
	 * Run in a platform (deprecated) and make sure follows are disabled per default
	 */
	function testFollowRedirectSafeMode() {
		// http://php.net/manual/en/ini.sect.safe-mode.php
		if ( version_compare( PHP_VERSION, "5.4.0", ">=" ) ) {
			$this->markTestSkipped( "Safe mode has been removed from this platform, so tests can not be performed" );

			return;
		}
		if ( filter_var( ini_get( 'safe_mode' ), FILTER_VALIDATE_BOOLEAN ) === true ) {
			$this->pemDefault();
			$redirectResponse = $this->CURL->doGet( "http://developer.tornevall.net/tests/tornevall_network/redirect.php?run" );
			$redirectedUrls   = $this->CURL->getRedirectedUrls();
			$this->assertTrue( $this->CURL->getResponseCode( $redirectResponse ) >= 300 && $this->CURL->getResponseCode( $redirectResponse ) <= 350 && ! preg_match( "/rerun/i", $this->CURL->getResponseBody( $redirectResponse ) ) && count( $redirectedUrls ) );

			return;
		}
		$this->markTestSkipped( "Safe mode is available as an option. It is however not enabled on this platform and can not therefore be tested." );
	}

	function testHostResolveValidationSuccess() {
		$localNetwork = new TorneLIB_Network();
		$localNetwork->setAlwaysResolveHostvalidation( true );
		$urlData = $localNetwork->getUrlDomain( "http://www.tornevall.net/" );
		$this->assertTrue( $urlData[0] == "www.tornevall.net" );
	}

	function testHostResolveValidationFail() {
		$localNetwork = new TorneLIB_Network();
		$localNetwork->setAlwaysResolveHostvalidation( true );
		$urlData = $localNetwork->getUrlDomain( "http://failing.domain/" );
		$this->assertTrue( $urlData[0] == "" );
	}

	function testHostValidationNoResolve() {
		$localNetwork = new TorneLIB_Network();
		$urlData      = $localNetwork->getUrlDomain( "http://failing.domain/" );
		$this->assertTrue( $urlData[0] == "failing.domain" );
	}

	/**
	 * Test SoapClient by making a standard doGet()
	 */
	function testSoapClient() {
		$assertThis = true;
		try {
			$this->CURL->setUserAgent( " +UnitSoapAgent" );
			$this->CURL->doGet( "http://" . $this->Urls['soap'] );
		} catch ( \Exception $e ) {
			$assertThis = false;
		}
		$this->assertTrue( $assertThis );
	}

	/**
	 * Test Soap by internal controllers
	 */
	function testHasSoap() {
		$this->assertTrue( $this->CURL->hasSoap() );
	}

	function testBitStructure() {
		$myBits = array(
			'TEST1' => 1,
			'TEST2' => 2,
			'TEST4' => 4,
			'TEST8' => 8,
		);
		$myBit  = new TorneLIB_NetBits( $myBits );
		$this->assertCount( 9, $myBit->getBitStructure() );
	}

	/**
	 * Test if one bit is on (1)
	 */
	function testBitActive() {
		$myBits = array(
			'TEST1' => 1,
			'TEST2' => 2,
			'TEST4' => 4,
			'TEST8' => 8,
		);
		$myBit  = new TorneLIB_NetBits( $myBits );
		$this->assertTrue( $myBit->isBit( 8, 12 ) );
	}

	/**
	 * Test if one bit is off (0)
	 */
	function testBitNotActive() {
		$myBits = array(
			'TEST1' => 1,
			'TEST2' => 2,
			'TEST4' => 4,
			'TEST8' => 8,
		);
		$myBit  = new TorneLIB_NetBits( $myBits );
		$this->assertFalse( $myBit->isBit( 64, 12 ) );
	}

	/**
	 * Test if multiple bits are active (muliple settings by bit)
	 */
	function testMultiBitActive() {
		$myBits = array(
			'TEST1' => 1,
			'TEST2' => 2,
			'TEST4' => 4,
			'TEST8' => 8,
		);
		$myBit  = new TorneLIB_NetBits( $myBits );
		$this->assertTrue( $myBit->isBit( ( array( 8, 2 ) ), 14 ) );
	}

	/**
	 * Test correct returning bits
	 */
	function testBitArray() {
		$myBit    = new TorneLIB_NetBits();
		$bitArray = $myBit->getBitArray( "88" );      // 8 + 16 + 64
		$this->assertCount( 3, $bitArray );
	}

	/**
	 * Test large setup of bits
	 */
	function test16BitArray() {
		$myBit = new TorneLIB_NetBits();
		$myBit->setMaxBits( 16 );
		$bitArray = $myBit->getBitArray( ( 8 + 256 + 4096 + 8192 + 32768 ) );
		$this->assertCount( 5, $bitArray );
	}

	/**
	 * Test the same large setup of bits as above, but via the network library
	 */
	function testBitFromNet() {
		$this->NET = new TorneLIB_Network();
		$this->NET->BIT->setMaxBits( 16 );
		$bitArrList = $this->NET->BIT->getBitArray( 8 + 256 + 4096 + 8192 + 32768 );
		$this->assertCount( 5, $bitArrList );
	}

	function testBitModes() {
		$myBit    = array(
			'DEBIT'  => 1,
			'CREDIT' => 2,
			'ANNUL'  => 4
		);
		$bitClass = new TorneLIB_NetBits( $myBit );
		$bitArray = $bitClass->getBitArray( 255 );
		$this->assertTrue( in_array( 'DEBIT', $bitArray ) && in_array( 'CREDIT', $bitArray ) && in_array( 'ANNUL', $bitArray ) && in_array( 'BIT_128', $bitArray ) );
	}

	function testThrowable() {
		$this->pemDefault();
		$this->CURL->setThrowableHttpCodes();
		try {
			$this->CURL->doGet( "https://developer.tornevall.net/tests/tornevall_network/http.php?code=503" );
		} catch ( \Exception $e ) {
			$this->assertTrue( $e->getCode() == 503 );

			return;
		}
		$this->markTestSkipped( "No throwables was set up" );
	}

	function testFailUrl() {
		try {
			$this->CURL->doGet( "http://abc" . sha1( microtime( true ) ) );
		} catch ( \Exception $e ) {
			$errorMessage = $e->getMessage();
			$this->assertTrue( ( preg_match( "/maximum tries/", $errorMessage ) ? true : false ) );
		}
	}

	public function testSetCurlOpt() {
		$oldCurl = $this->CURL->getCurlOpt();
		$this->CURL->setCurlOpt( array( CURLOPT_CONNECTTIMEOUT => 10 ) );
		$newCurl = $this->CURL->getCurlOpt();
		$this->assertTrue( $oldCurl[ CURLOPT_CONNECTTIMEOUT ] != $newCurl[ CURLOPT_CONNECTTIMEOUT ] );
	}

	public function testGetCurlOpt() {
		$newCurl = $this->CURL->getCurlOptByKeys();
		$this->assertTrue( isset( $newCurl['CURLOPT_CONNECTTIMEOUT'] ) );
	}

	function testUnsetFlag() {
		$first = $this->CURL->setFlag( "CHAIN", true );
		$this->CURL->unsetFlag( "CHAIN" );
		$second = $this->CURL->hasFlag( "CHAIN" );
		$this->assertTrue( $first && ! $second );
	}

	function testChainGet() {
		$this->CURL->setFlag( "CHAIN" );
		$this->assertTrue( method_exists( $this->CURL->doGet( $this->Urls['simplejson'] ), 'getParsedResponse' ) );
		$this->CURL->unsetFlag( "CHAIN" );
	}

	function testFlagEmptyKey() {
		try {
			$this->CURL->setFlag();
		} catch ( \Exception $setFlagException ) {
			$this->assertTrue( $setFlagException->getCode() > 0 );
		}
	}

	function testChainByInit() {
		$Chainer = new Tornevall_cURL( null, null, null, array( "CHAIN" ) );
		$this->assertTrue( is_object( $Chainer->doGet( $this->Urls['simplejson'] )->getParsedResponse() ) );
	}

	function testChainGetFail() {
		$this->CURL->unsetFlag( "CHAIN" );
		$this->assertFalse( method_exists( $this->CURL->doGet( $this->Urls['simplejson'] ), 'getParsedResponse' ) );
	}

	function testDeprecatedIpClass() {
		$this->assertTrue( TorneLIB_Network_IP::PROTOCOL_IPV6 === 6 && TorneLIB_Network_IP::IPTYPE_V6 && TorneLIB_Network_IP_Protocols::PROTOCOL_IPV6 );
	}

	function testGetGitInfo() {
		try {
			$NetCurl              = $this->NET->getGitTagsByUrl( "http://userCredentialsBanned@bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git" );
			$GuzzleLIB            = $this->NET->getGitTagsByUrl( "https://github.com/guzzle/guzzle.git" );
			$GuzzleLIBNonNumerics = $this->NET->getGitTagsByUrl( "https://github.com/guzzle/guzzle.git", true, true );
			$this->assertTrue( count( $NetCurl ) >= 0 && count( $GuzzleLIB ) >= 0 );
		} catch ( \Exception $e ) {

		}
	}

	// This is sometimes untestable, since our local versions change from time to time
	/*function testGetMyVersionByGit() {
		$curlVersion    = $this->CURL->getVersion();
		$remoteVersions = $this->NET->getMyVersionByGitTag( $curlVersion, "http://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git" );
		// curl module for netcurl will probably always be lower than the netcurl-version, so this is a good way of testing
		$this->assertTrue( count( $remoteVersions ) > 0 );
	}*/

	function testGetIsTooOld() {
		// curl module for netcurl will probably always be lower than the netcurl-version, so this is a good way of testing
		$this->assertTrue( $this->NET->getVersionTooOld( "1.0.0", "http://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git" ) );
	}

	function testGetCurrentOrNewer() {
		// curl module for netcurl will probably always be lower than the netcurl-version, so this is a good way of testing
		$tags           = $this->NET->getGitTagsByUrl( "http://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git" );
		$lastTag        = array_pop( $tags );
		$lastBeforeLast = array_pop( $tags );
		// This should return false, since the current is not too old
		$isCurrent = $this->NET->getVersionTooOld( $lastTag, "http://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git" );
		// This should return true, since the last version after the current is too old
		$isLastBeforeCurrent = $this->NET->getVersionTooOld( $lastBeforeLast, "http://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git" );

		$this->assertTrue( $isCurrent === false && $isLastBeforeCurrent === true );
	}

	function testTimeouts() {
		$def = $this->CURL->getTimeout();
		$this->CURL->setTimeout( 6 );
		$new = $this->CURL->getTimeout();
		$this->assertTrue( $def['connecttimeout'] == 300 && $def['requesttimeout'] == 0 && $new['connecttimeout'] == 3 && $new['requesttimeout'] == 6 );
	}

	function testInternalException() {
		$this->assertTrue( $this->NET->getExceptionCode( 'NETCURL_EXCEPTION_IT_WORKS' ) == 1 );
	}

	function testInternalExceptionNoExists() {
		$this->assertTrue( $this->NET->getExceptionCode( 'NETCURL_EXCEPTION_IT_DOESNT_WORK' ) == 500 );
	}

	private function hasGuzzle( $useStream = false ) {
		try {
			if ( ! $useStream ) {
				return $this->CURL->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP );
			} else {
				return $this->CURL->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP_STREAM );
			}
		} catch (\Exception $e) {
			$this->markTestSkipped( "Can not test guzzle driver without guzzle (".$e->getMessage().")" );
		}
	}

	function testEnableGuzzle() {
		if ( $this->hasGuzzle() ) {
			$info = $this->CURL->doPost( "https://" . $this->Urls['tests'] . "?o=json&getjson=true&var1=HasVar1", array( 'var2' => 'HasPostVar1' ) );
			$this->CURL->getExternalDriverResponse();
			$parsed = $this->CURL->getParsedResponse( $info );
			$this->assertTrue( $parsed->methods->_REQUEST->var1 === "HasVar1" );
		} else {
			$this->markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	function testEnableGuzzleStream() {
		if ( $this->hasGuzzle( true ) ) {
			$info = $this->CURL->doPost( "https://" . $this->Urls['tests'] . "?o=json&getjson=true&getVar=true", array(
				'var1'    => 'HasVar1',
				'postVar' => "true"
			) );
			$this->CURL->getExternalDriverResponse();
			$parsed = $this->CURL->getParsedResponse( $info );
			$this->assertTrue( $parsed->methods->_REQUEST->var1 === "HasVar1" );
		} else {
			$this->markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	function testEnableGuzzleStreamJson() {
		if ( $this->hasGuzzle( true ) ) {
			$info = $this->CURL->doPost( "https://" . $this->Urls['tests'] . "?o=json&getjson=true&getVar=true", array(
				'var1'    => 'HasVar1',
				'postVar' => "true",
				'asJson'  => 'true'
			), CURL_POST_AS::POST_AS_JSON );
			$this->CURL->getExternalDriverResponse();
			$parsed = $this->CURL->getParsedResponse( $info );
			$this->assertTrue( $parsed->methods->_REQUEST->var1 === "HasVar1" );
		} else {
			$this->markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	function testEnableGuzzleWsdl() {
		if ( $this->hasGuzzle() ) {
			// Currently, this one will fail over to SimpleSoap
			$info = $this->CURL->doGet( "http://" . $this->Urls['soap'] );
			$this->assertTrue( is_object( $info ) );
		} else {
			$this->markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	function testEnableGuzzleErrors() {
		if ( $this->hasGuzzle() ) {
			try {
				$info = $this->CURL->doPost( $this->Urls['tests'] . "&o=json&getjson=true", array( 'var1' => 'HasVar1' ) );
			} catch ( \Exception $wrapError ) {
				$this->assertTrue( $wrapError->getCode() == 404 );
			}
		} else {
			$this->markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	function testDriverControlList() {
		$driverList = array();
		try {
			$driverList = $this->CURL->getDrivers();
		} catch ( \Exception $e ) {
			echo $e->getMessage();
		}
		$this->assertTrue( count( $driverList ) > 0 );
	}

	function testDriverControlNoList() {
		$driverList = false;
		try {
			$driverList = $this->CURL->getAvailableDrivers();
		} catch ( \Exception $e ) {
			echo $e->getMessage() . "\n";
		}
		$this->assertTrue( $driverList );
	}

	public function testGetProtocol() {
		$oneOfThenm = TorneLIB_Network::getCurrentServerProtocol( true );
		$this->assertTrue( $oneOfThenm == "http" || $oneOfThenm == "https" );
	}

	function testGetSupportedDrivers() {
		$this->assertTrue( count( $this->CURL->getSupportedDrivers() ) > 0 );
	}

	function testSetAutoDriver() {
		$driverset = $this->CURL->setDriverAuto();
		$this->assertTrue( $driverset > 0 );
	}

	function testByConstructor() {
		$identifierByJson = ( new Tornevall_cURL( $this->Urls['simplejson'] ) )->getParsedResponse();
		$this->assertTrue( isset( $identifierByJson->ip ) );
	}

}
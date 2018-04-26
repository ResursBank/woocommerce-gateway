<?php

namespace TorneLIB;

if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
}
if ( file_exists( __DIR__ . "/../tornelib.php" ) ) {
	// Work with TorneLIBv5
	require_once( __DIR__ . '/../tornelib.php' );
}

use PHPUnit\Framework\TestCase;
use \TorneLIB\Tornevall_cURL;

ini_set( 'memory_limit', - 1 );    // Free memory limit, some tests requires more memory (like ip-range handling)

//// START HERE
class ResursBank_cURLTest extends TestCase {

	/**
	 * @var Tornevall_cURL $CURL
	 */
	private $CURL;
	function setUp() {
		$this->CURL = new Tornevall_cURL();
	}

	function testMemberNull() {
		$localCurl = new Tornevall_cURL();
		$username  = "atest";
		$password  = "atest";
		$localCurl->setAuthentication( $username, $password, CURL_AUTH_TYPES::AUTHTYPE_BASIC );
		$specUrl = "https://omnitest.resurs.com/checkout/payments/null/updatePaymentReference";
		try {
			$null = $this->CURL->getParsedResponse( $localCurl->doPut( $specUrl, array( 'paymentReference' => null ), CURL_POST_AS::POST_AS_JSON ) );
		} catch ( \Exception $putUrlResponse ) {
		}
		$this->assertTrue( true );
	}

	function testSoapError() {
		$localCurl = new Tornevall_cURL();
		$wsdl      = $localCurl->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' );
		try {
			$wsdl->getPaymentMethods();
		} catch ( \Exception $e ) {
			$previousException = $e->getPrevious();
			$this->assertTrue( isset( $previousException->faultstring ) && ! empty( $previousException->faultstring ) && preg_match( "/unauthorized/i", $e->getMessage() ) );
		}
	}

	function testSoapAuthErrorInitialSoapFaultsWsdl() {
		$localCurl = new Tornevall_cURL();
		$localCurl->setAuthentication( "fail", "fail" );
		$localCurl->setFlag( 'SOAPWARNINGS' );
		try {
			$wsdl = $localCurl->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' );
			$wsdl->getPaymentMethods();
		} catch ( \Exception $e ) {
			$errorMessage = $e->getMessage();
			$errorCode    = $e->getCode();
			$this->assertTrue( $errorCode == 401 && preg_match( "/401 unauthorized/is", $errorMessage ) ? true : false );
		}
	}

	function testSoapAuthErrorInitialSoapFaultsNoWsdl() {
		$localCurl = new Tornevall_cURL();
		$localCurl->setSoapTryOnce( false );
		$localCurl->setAuthentication( "fail", "fail" );
		$localCurl->setFlag( 'SOAPWARNINGS' );
		try {
			$wsdl = $localCurl->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService', CURL_POST_AS::POST_AS_SOAP );
			$wsdl->getPaymentMethods();
		} catch ( \Exception $e ) {
			$errorMessage = $e->getMessage();
			$errorCode    = $e->getCode();
			$this->assertTrue( $errorCode == 401 && preg_match( "/401 unauthorized/is", $errorMessage ) ? true : false );
		}
	}

	function testSoapAuthErrorNoInitialSoapFaultsWsdl() {
		$localCurl = new Tornevall_cURL();
		$localCurl->setAuthentication( "fail", "fail" );
		try {
			$wsdl = $localCurl->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' );
			$wsdl->getPaymentMethods();
		} catch ( \Exception $e ) {
			// As of 6.0.16, this is the default behaviour even when SOAPWARNINGS are not active by setFlag
			$errorMessage = $e->getMessage();
			$errorCode    = $e->getCode();
			$this->assertTrue( $errorCode == 401 && preg_match( "/401 unauthorized/is", $errorMessage ) ? true : false );
		}
	}

	function testSoapAuthErrorNoInitialSoapFaultsNoWsdl() {
		$localCurl = new Tornevall_cURL();
		$localCurl->setSoapTryOnce( false );
		$localCurl->setAuthentication( "fail", "fail" );
		try {
			$wsdl = $localCurl->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService', CURL_POST_AS::POST_AS_SOAP );
			$wsdl->getPaymentMethods();
		} catch ( \Exception $e ) {
			// As of 6.0.16, this is the default behaviour even when SOAPWARNINGS are not active by setFlag
			$errorMessage = $e->getMessage();
			$errorCode    = $e->getCode();
			$this->assertTrue( $errorCode == 401 && preg_match( "/401 unauthorized/is", $errorMessage ) ? true : false );
		}
	}

	/*	function testAuthByConstructor() {
			if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
				$constructorAuth = ( new Tornevall_cURL( "https://omnitest.resurs.com/checkout/payments/661", array(), CURL_METHODS::METHOD_GET, array(
					'auth' => array(
						'username' => 'username',
						'password' => 'password'
					)
				) ) )->getParsedResponse();
				$this->assertTrue( is_object( $constructorAuth ) );
				return;
			}
			// Fails in lower than PHP 5.4
			$this->assertFalse(false);
		}

		function testAuthByNoConstructor() {
			$regularRequest = new Tornevall_cURL();
			$regularRequest->setAuthentication( "atest", "atest" );
			$chainRequest = $regularRequest->doGet( "https://omnitest.resurs.com/checkout/payments/661" )->getParsedResponse();
			$this->assertTrue( is_object( $chainRequest ) );
		}*/

	/*function testGuzzleStreamAuth() {
		try {
			$this->CURL->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP_STREAM );
			$this->CURL->setThrowableHttpCodes();
			$this->CURL->setAuthentication( "atest", "atest" );
			// Do not set a default content-type at this point, let the service itself ask for it
			// $this->CURL->setContentType();
			$this->CURL->doGet( "https://omnitest.resurs.com/checkout/payments/987" );
			$this->assertTrue( is_object( $this->CURL->getParsedResponse() ) );
		} catch ( \Exception $e ) {
			$this->markTestIncomplete( $e->getCode() . ": " . $e->getMessage() );
		}
	}*/

	function testRbSoapChain() {
		$localCurl = new Tornevall_cURL();
		$localCurl->setAuthentication( "atest", "atest" );
		try {
			$wsdlResponse = $localCurl->doGet( 'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl' )->getPaymentMethods();
			$this->assertTrue( is_array( $localCurl->getParsedResponse( $wsdlResponse ) ) );
		} catch ( \Exception $e ) {
			echo $e->getMessage();
		}
	}

}
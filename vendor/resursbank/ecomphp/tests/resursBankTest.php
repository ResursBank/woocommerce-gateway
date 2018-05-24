<?php

/**
 * Resurs Bank EComPHP - Test suite.
 *
 * Some of the tests in this suite is being made to check that the "share data between tests" works properly.
 * As setUp() resets tests to basic each time it runs, we can not share for example payments that we can make more
 * then one test on, with different kind of exepectations.
 *
 * @package EcomPHPTest
 * @author Resurs Bank AB, Tomas Tornevall <tomas.tornevall@resurs.se>
 * @version 0.2.0
 * @link https://test.resurs.com/docs/x/KYM0 Get started - PHP Section
 * @link https://resursbankplugins.atlassian.net/browse/ECOMPHP-214 Rebuilding!
 * @license Apache 2.0
 *
 */

namespace Resursbank\RBEcomPHP;

if ( file_exists( __DIR__ . "/../vendor/autoload.php" ) ) {
	require_once( __DIR__ . '/../vendor/autoload.php' );
} else {
	require_once( '../source/classes/rbapiloader.php' );
}

// Resurs Bank usages
use PHPUnit\Framework\TestCase;
use \Resursbank\RBEcomPHP\ResursBank;
use \Resursbank\RBEcomPHP\RESURS_CALLBACK_TYPES;
use \Resursbank\RBEcomPHP\RESURS_PAYMENT_STATUS_RETURNCODES;
use \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;
use \Resursbank\RBEcomPHP\RESURS_CALLBACK_REACHABILITY;
use \Resursbank\RBEcomPHP\RESURS_AFTERSHOP_RENDER_TYPES;

// curl wrapper, extended network handling functions etc
use TorneLIB\MODULE_CURL;
use TorneLIB\MODULE_IO;
use TorneLIB\NETCURL_PARSER;
use TorneLIB\TorneLIB_IO;
use \TorneLIB\Tornevall_cURL;
use \TorneLIB\TorneLIB_Network;
use TorneLIB\Tornevall_SimpleSoap;

// Global test configuration section starts here
require_once( __DIR__ . "/classes/ResursBankTestClass.php" );
require_once( __DIR__ . "/hooks.php" );

// Set up local user agent for identification with webservices
if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
	$_SERVER['HTTP_USER_AGENT'] = "EComPHP/Test-InternalClient";
}
ini_set( 'memory_limit', - 1 );
if ( file_exists( "/etc/ecomphp.json" ) ) {
	$ecomExt = @json_decode( @file_get_contents( "/etc/ecomphp.json" ) );
	if ( isset( $ecomExt->skip ) ) {
		define( 'SKIP_TEST', $ecomExt->skip );
	}
}

class resursBankTest extends TestCase {

	/**
	 * @var ResursBank $API EComPHP
	 */
	protected $API;

	/** @var RESURS_TEST_BRIDGE $TEST Used for standard tests and simpler flow setup */
	protected $TEST;

	/** @var string Username to web services */
	private $username = "ecomphpPipelineTest";
	/** @var string Password to web services */
	private $password = "4Em4r5ZQ98x3891D6C19L96TQ72HsisD";

	private $flowHappyCustomer = "8305147715";
	private $flowHappyCustomerName = "Vincent Williamsson Alexandersson";

	/** @var string Landing page for callbacks */
	private $callbackUrl = "https://test.resurs.com/signdummy/index.php?isCallback=1";

	/** @var string Landing page for signings */
	private $signUrl = "https://test.resurs.com/signdummy/index.php?isSigningUrl=1";

	function setUp() {
		$this->API = new ResursBank();
		$this->API->setDebug( true );
		$this->TEST = new RESURS_TEST_BRIDGE();
	}

	function tearDown() {

	}

	private function getHappyCustomerData() {
		$lastHappyCustomer = $this->TEST->share( 'happyCustomer' );
		if ( empty( $lastHappyCustomer ) ) {
			$this->getAddress( true );
			$lastHappyCustomer = $this->TEST->share( 'happyCustomer' );
		}
		if ( isset( $lastHappyCustomer[0] ) ) {
			return $lastHappyCustomer[0];
		}
	}

	private function getPaymentMethodsData() {
		$paymentMethods = $this->TEST->share( 'paymentMethods' );
		if ( empty( $paymentMethods ) ) {
			$this->getPaymentMethods();
			$paymentMethods = $this->TEST->share( 'paymentMethods' );
		}
		if ( isset( $paymentMethods[0] ) ) {
			return $paymentMethods[0];
		}
	}

	/**
	 * @test
	 */
	function clearStorage() {
		@unlink( __DIR__ . "/storage/shared.serialize" );
		static::assertTrue( ! file_exists( __DIR__ . '/storage/shared.serialize' ) );
	}

	/**
	 * @test
	 * @testdox Tests API credentials and getPaymentMethods. Expected result: Approved connection with a specific number of payment methods
	 *
	 * @throws \Exception
	 */
	function apiPaymentMethodsWithCredentials() {
		static::assertTrue( count( $this->TEST->getCredentialControl() ) > 0 );
	}

	/**
	 * @test
	 * @testdox EComPHP throws \Exceptions on credential failures
	 * @throws \Exception
	 */
	function apiPaymentMethodsWithWrongCredentials() {
		try {
			$this->TEST->getCredentialControl( false );
		} catch ( \Exception $e ) {
			static::assertTrue( ( $e->getCode() == 401 ) );
		}
	}

	/**
	 * @test
	 * @testdox Testing this suite's capabilities to share data between tests
	 */
	function shareDataOut() {
		$this->TEST->share( "outShare", 1 );
		$keys = $this->TEST->share( "thisKey", "thatValue" );
		static::assertTrue( count( $keys ) > 0 ? true : false );
	}

	/**
	 * @test
	 * @testdox Testing this suite's capabilites to retreive shared data
	 */
	function shareDataIn() {
		$keys = $this->TEST->share( "thisKey" );
		static::assertTrue( count( $keys ) > 0 ? true : false );
	}

	/**
	 * @test
	 * @testdox Testing this suite's capability to remove keys from shared data (necessary to reset things)
	 */
	function shareDataRemove() {
		if ( $this->TEST->share( "outShare" ) ) {
			$this->TEST->unshare( "outShare" );
			$keys = $this->TEST->share();
			static::assertTrue( is_array( $keys ) );

		} else {
			static::markTestSkipped( "Test has been started without shareDataOut" );
		}
	}

	/**
	 * @test
	 * @testdox Direct test - Basic getAddressTest with caching
	 */
	function getAddress( $noAssert = false ) {
		$happyCustomer = $this->TEST->ECOM->getAddress( $this->flowHappyCustomer );
		$this->TEST->share( 'happyCustomer', $happyCustomer, false );
		if ( ! $noAssert ) {
			static::assertContains( $this->flowHappyCustomerName, $happyCustomer->fullName );
		}

		return $happyCustomer;
	}

	/**
	 * @test
	 * @testdox getCurlHandle (using getAddress)
	 */
	function getAddressCurlHandle() {
		if ( ! class_exists( '\SimpleXMLElement' ) ) {
			static::markTestSkipped( "SimpleXMLElement missing" );
		}

		$this->TEST->ECOM->getAddress( $this->flowHappyCustomer );
		/** @var Tornevall_cURL $lastCurlHandle */

		if ( defined( 'TORNELIB_NETCURL_RELEASE' ) && version_compare( TORNELIB_NETCURL_RELEASE, '6.0.20', '<' ) ) {
			// In versions prior to 6.0.20, you need to first extract the SOAP body from simpleSoap itself (via getLibResponse).
			$lastCurlHandle = $this->TEST->ECOM->getCurlHandle( true );
			/** @var Tornevall_SimpleSoap $lastCurlHandle */
			$soapLibResponse = $lastCurlHandle->getLibResponse();
			$selfParser      = new TorneLIB_IO();
			$byIo            = $selfParser->getFromXml( $soapLibResponse['body'], true );
			static::assertTrue( ( $byIo->fullName == $this->flowHappyCustomerName ? true : false ) && ( $soapLibResponse['parsed']->fullName == $this->flowHappyCustomerName ? true : false ) );

			return;
		}

		// The XML parser in the IO MODULE should give the same response as the direct curl handle
		// From NetCURL 6.0.20 and the IO library, this could be extracted directly from the curl handle
		$selfParser = new TorneLIB_IO();
		// Get the curl handle without bulk request
		$lastCurlHandle = $this->TEST->ECOM->getCurlHandle();

		$byIo     = $selfParser->getFromXml( $lastCurlHandle->getResponseBody(), true );
		$byHandle = $lastCurlHandle->getParsedResponse();

		static::assertTrue( $byIo->fullName == $this->flowHappyCustomerName && $byHandle->fullName == $this->flowHappyCustomerName );
	}

	/**
	 * @test
	 * @testdox Test if getPaymentMethods work and in the same time cache it for future use
	 */
	function getPaymentMethods( $noAssert = false ) {
		$this->TEST->ECOM->setSimplifiedPsp( true );
		$paymentMethods = $this->TEST->ECOM->getPaymentMethods();
		foreach ( $paymentMethods as $method ) {
			$this->TEST->share( 'METHOD_' . $method->specificType, $method, false );
		}
		$this->TEST->share( 'paymentMethods', $paymentMethods, false );
		if ( ! $noAssert ) {
			static::assertGreaterThan( 1, $paymentMethods );
		}
	}

	/**
	 * Get a method that suites our needs of type, with the help from getPaymentMethods
	 *
	 * @param string $specificType
	 *
	 * @return mixed
	 */
	function getMethod( $specificType = 'INVOICE' ) {
		$specificMethod = $this->TEST->share( 'METHOD_' . $specificType );
		if ( empty( $specificMethod ) ) {
			$this->getPaymentMethods( false );
			$specificMethod = $this->TEST->share( 'METHOD_' . $specificType );
		}

		if ( isset( $specificMethod[0] ) ) {
			return $specificMethod[0];
		}

		return $specificMethod;
	}

	/**
	 * Get the payment method ID from the internal getMethod()
	 *
	 * @param string $specificType
	 *
	 * @return mixed
	 */
	function getMethodId( $specificType = 'INVOICE' ) {
		$specificMethod = $this->getMethod( $specificType );
		if ( isset( $specificMethod->id ) ) {
			return $specificMethod->id;
		}
	}

	/**
	 * @test
	 * @testdox Direct test - Test adding orderlines via the library and extract correct data
	 */
	function addOrderLine() {
		$this->TEST->ECOM->addOrderLine( "Product-1337", "One simple orderline", 800, 25 );
		$orderLines = $this->TEST->ECOM->getOrderLines();
		static::assertTrue( count( $orderLines ) > 0 && $orderLines[0]['artNo'] == "Product-1337" );
	}

	/**
	 * @test
	 */
	function generateSimpleSimplifiedInvoiceOrder( $noAssert = false ) {
		$customerData = $this->getHappyCustomerData();
		$this->TEST->ECOM->addOrderLine( "Product-1337", "One simple orderline", 800, 25 );
		$this->TEST->ECOM->setBillingByGetAddress( $customerData );
		$this->TEST->ECOM->setCustomer( "198305147715", "0808080808", "0707070707", "test@test.com", "NATURAL" );
		$this->TEST->ECOM->setSigning( $this->signUrl . '&success=true', $this->signUrl . '&success=false', false );
		$response = $this->TEST->ECOM->createPayment( $this->getMethodId() );
		if ( ! $noAssert ) {
			static::assertContains( 'BOOKED', $response->bookPaymentStatus );
		}

		return $response;
	}

	/**
	 * @test Direct test - Extract orderdata from library
	 * @testdox
	 * @throws \Exception
	 */
	function getOrderData() {
		$this->TEST->ECOM->setBillingByGetAddress( $this->flowHappyCustomer );
		$this->TEST->ECOM->addOrderLine( "RDL-1337", "One simple orderline", 800, 25 );
		$orderData = $this->TEST->ECOM->getOrderData();
		static::assertTrue( $orderData['totalAmount'] == "1000" );
	}

	/**
	 * @test
	 * @testdox Make sure the current version of ECom is not 1.0.0 and getCurrentRelease() says something
	 */
	function getCurrentReleaseTests() {
		$currentReleaseShouldNotBeEmpty = $this->TEST->ECOM->getCurrentRelease();  // php 5.5
		static::assertFalse( $this->TEST->ECOM->getIsCurrent( "1.0.0" ) && ! empty( $currentReleaseShouldNotBeEmpty ) );
	}

	/**
	 * @test
	 */
	function getAnnuityMethods() {
		$annuityObjectList = $this->TEST->ECOM->getPaymentMethodsByAnnuity();
		$annuityIdList     = $this->TEST->ECOM->getPaymentMethodsByAnnuity( true );
		static::assertTrue( count( $annuityIdList ) >= 1 && count( $annuityObjectList ) >= 1 );
	}

	/**
	 * @test
	 */
	function findPaymentsXmlBody() {
		$paymentScanList = $this->TEST->ECOM->findPayments( array( 'statusSet' => array( 'IS_DEBITED' ) ), 1, 10, array(
			'ascending'   => false,
			'sortColumns' => array( 'FINALIZED_TIME', 'MODIFIED_TIME', 'BOOKED_TIME' )
		) );

		$handle      = $this->TEST->ECOM->getCurlHandle();
		$requestBody = $handle->getRequestBody();
		static::assertTrue( strlen( $requestBody ) > 100 && count( $paymentScanList ) );
	}

	/**
	 * @test
	 */
	function hookExperiment1() {
		if ( ! function_exists( 'ecom_event_register' ) ) {
			static::markTestIncomplete( 'ecomhooks does not exist' );

			return;
		}
		ecom_event_register('update_store_id', 'inject_test_storeid');
		$customerData = $this->getHappyCustomerData();
		$this->TEST->ECOM->addOrderLine( "Product-1337", "One simple orderline", 800, 25 );
		$this->TEST->ECOM->setBillingByGetAddress( $customerData );
		$this->TEST->ECOM->setCustomer( "198305147715", "0808080808", "0707070707", "test@test.com", "NATURAL" );
		$this->TEST->ECOM->setSigning( $this->signUrl . '&success=true', $this->signUrl . '&success=false', false );
		$myPayLoad = $this->TEST->ECOM->getPayload();
		static::assertTrue(isset($myPayLoad['storeId']) && $myPayLoad['storeId'] >= 0);
	}
	/**
	 * @test
	 */
	function hookExperiment2() {
		if ( ! function_exists( 'ecom_event_register' ) ) {
			static::markTestIncomplete( 'ecomhooks does not exist' );

			return;
		}
		ecom_event_register('update_payload', 'ecom_inject_payload');
		$customerData = $this->getHappyCustomerData();
		$errorCode = 0;
		$this->TEST->ECOM->addOrderLine( "Product-1337", "One simple orderline", 800, 25 );
		$this->TEST->ECOM->setBillingByGetAddress( $customerData );
		$this->TEST->ECOM->setCustomer( "198305147715", "0808080808", "0707070707", "test@test.com", "NATURAL" );
		$this->TEST->ECOM->setSigning( $this->signUrl . '&success=true', $this->signUrl . '&success=false', false );
		try {
			$myPayLoad = $this->TEST->ECOM->getPayload();
			$response = $this->TEST->ECOM->createPayment( $this->getMethodId() );
		} catch (\Exception $e) {
			$errorCode = $e->getCode();
		}

		static::assertTrue(isset($myPayLoad['add_a_problem_into_payload']) && !isset($myPayLoad['signing']) && $errorCode == 3);
	}

	/**
	 * @test
	 * @testdox Expect arrays regardless of response
	 * @throws \Exception
	 */
	function getEmptyCallbacksList() {
		/**
		 * Standard request returns:
		 *
		 *   array(
		 *      [index-1] => stdObject
		 *      [index-2] => stdObject
		 *   )
		 *
		 *   asArrayRequest returns:
		 *   array(
		 *      [keyCallbackName1] => URL
		 *      [keyCallbackName2] => URL
		 *   )
		 *
		 * Standard request when empty should return array()
		 *
		 */

		try {
			$this->TEST->ECOM->unregisterEventCallback( 255, true );
		} catch (\Exception $e) {
		}
		$callbacks = $this->TEST->ECOM->getCallBacksByRest();
		static::assertTrue( is_array( $callbacks ) && ! count( $callbacks ) ? true : false );

	}

	/**
	 * @test
	 * @testdox Clean up special test data from share file
	 */
	function finalTest() {
		static::assertEmpty( $this->TEST->unshare( "thisKey" ) );
	}
}

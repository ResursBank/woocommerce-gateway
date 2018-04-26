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
use \TorneLIB\Tornevall_cURL;
use \TorneLIB\TorneLIB_Network;

// Global test configuration section starts here
require_once( __DIR__ . "/classes/ResursBankTestClass.php" );

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

class ResursBankTest extends TestCase {

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
		$this->API  = new ResursBank();
		$this->TEST = new RESURS_TEST_BRIDGE();
	}

	function tearDown() {

	}

	/**
	 * @test
	 * @testdox Tests API credentials and getPaymentMethods. Expected result: Approved connection with a specific number of payment methods
	 *
	 * @throws \Exception
	 */
	function apiPaymentMethodsWithCredentials() {
		$this->assertTrue( count( $this->TEST->getCredentialControl() ) > 0 );
	}

	/**
	 * @test
	 * @testdox EComPHP throws \Exceptions on credential failures
	 * @throws \Exception
	 */
	function apiPaymentMethodsWithWrongCredentials() {
		$this->expectException( "\Exception" );
		$this->TEST->getCredentialControl( false );
	}

	/**
	 * @test
	 * @testdox Testing this suite's capabilities to share data between tests
	 */
	function shareDataOut() {
		$this->TEST->share( "outShare", 1 );
		$keys = $this->TEST->share( "thisKey", "thatValue" );
		$this->assertTrue( count( $keys ) > 0 ? true : false );
	}

	/**
	 * @test
	 * @testdox Testing this suite's capabilites to retreive shared data
	 */
	function shareDataIn() {
		$keys = $this->TEST->share( "thisKey" );
		$this->assertTrue( count( $keys ) > 0 ? true : false );
	}

	/**
	 * @test
	 * @testdox Testing this suite's capability to remove keys from shared data (necessary to reset things)
	 */
	function shareDataRemove() {
		if ( $this->TEST->share( "outShare" ) ) {
			$this->TEST->unshare( "outShare" );
			$keys = $this->TEST->share();
			$this->assertTrue( is_array( $keys ) );

		} else {
			$this->markTestSkipped( "Test has been started without shareDataOut" );
		}
	}

	/**
	 * @test
	 * @testdox Direct test - Basic getAddressTest
	 */
	function getAddress() {
		$this->assertContains($this->flowHappyCustomerName, $this->TEST->ECOM->getAddress($this->flowHappyCustomer)->fullName);
	}

	/**
	 * @test
	 * @testdox Direct test - Test adding orderlines via the library and extract correct data
	 */
	function addOrderLine() {
		$this->TEST->ECOM->addOrderLine("RDL-1337", "One simple orderline", 800, 25);
		$orderLines = $this->TEST->ECOM->getOrderLines();
		$this->assertTrue(count($orderLines) > 0 && $orderLines[0]['artNo'] == "RDL-1337");
	}

	/**
	 * @test Direct test - Extract orderdata from library
	 * @testdox
	 * @throws \Exception
	 */
	function getOrderData() {
		$this->TEST->ECOM->setBillingByGetAddress($this->flowHappyCustomer);
		$this->TEST->ECOM->addOrderLine("RDL-1337", "One simple orderline", 800, 25);
		$this->assertTrue(($this->TEST->ECOM->getOrderData())['totalAmount'] == "1000");
	}

	/**
	 * @test
	 * @testdox Make sure the current version of ECom is not 1.0.0 and getCurrentRelease() says something
	 */
	function getCurrentReleaseTests() {
		$currentReleaseShouldNotBeEmpty = $this->TEST->ECOM->getCurrentRelease();  // php 5.5
		$this->assertFalse($this->TEST->ECOM->getIsCurrent("1.0.0") && !empty($currentReleaseShouldNotBeEmpty));
	}



	/**
	 * @test
	 * @testdox Clean up special test data from share file
	 */
	function finalTest() {
		$this->assertEmpty($this->TEST->unshare("thisKey"));
	}
}

<?php

namespace TorneLIB;

require_once( __DIR__ . "/../vendor/autoload.php" );
require_once( __DIR__ . '/testurls.php' );

use PHPUnit\Framework\TestCase;
use \TorneLIB\MODULE_SSL;

class guzzleTest extends TestCase {
	private $CURL;

	function setUp() {
		$this->CURL = new MODULE_CURL();
	}

	/**
	 * @param bool $useStream
	 *
	 * @return bool
	 */
	private function hasGuzzle( $useStream = false ) {
		try {
			if ( ! $useStream ) {
				return is_object( $this->CURL->setDriver( NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP ) ) ? true : false;
			} else {
				return is_object( $this->CURL->setDriver( NETCURL_NETWORK_DRIVERS::DRIVER_GUZZLEHTTP_STREAM ) ) ? true : false;
			}
		} catch ( \Exception $e ) {
			static::markTestSkipped( "Can not test guzzle driver without guzzle (" . $e->getMessage() . ")" );
		}
	}

	/**
	 * @test
	 */
	function enableGuzzle() {
		if ( $this->hasGuzzle() ) {
			$info = $this->CURL->doPost( "https://" . \TESTURLS::getUrlTests() . "?o=json&getjson=true&var1=HasVar1", array( 'var2' => 'HasPostVar1' ) )->getParsedResponse();
			//$this->CURL->getExternalDriverResponse();
			//$parsed = $this->CURL->getParsedResponse( $info );
			static::assertTrue( $info->methods->_REQUEST->var1 === "HasVar1" );
		} else {
			static::markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	/**
	 * @test
	 */
	function enableGuzzleStream() {
		if ( $this->hasGuzzle( true ) ) {
			$info = $this->CURL->doPost( "https://" . \TESTURLS::getUrlTests() . "?o=json&getjson=true&getVar=true", array(
				'var1'    => 'HasVar1',
				'postVar' => "true"
			) )->getParsedResponse();
			//$parsed = $this->CURL->getParsedResponse( $info );
			static::assertTrue( $info->methods->_REQUEST->var1 === "HasVar1" );
		} else {
			static::markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	/**
	 * @test
	 */
	function enableGuzzleStreamJson() {
		if ( $this->hasGuzzle( true ) ) {
			$info = $this->CURL->doPost( "https://" . \TESTURLS::getUrlTests() . "?o=json&getjson=true&getVar=true", array(
				'var1'    => 'HasVar1',
				'postVar' => "true",
				'asJson'  => 'true'
			), NETCURL_POST_DATATYPES::DATATYPE_JSON )->getParsedResponse();
			//$parsed = $this->CURL->getParsedResponse( $info );
			static::assertTrue( $info->methods->_REQUEST->var1 === "HasVar1" );
		} else {
			static::markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	/**
	 * @test
	 */
	function enableGuzzleWsdl() {
		if ( $this->hasGuzzle() ) {
			// Currently, this one will fail over to SimpleSoap
			$info = $this->CURL->doGet( "http://" . \TESTURLS::getUrlSoap() );
			static::assertTrue( is_object( $info ) );
		} else {
			static::markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}

	/**
	 * @test
	 */
	function enableGuzzleErrors() {
		if ( $this->hasGuzzle() ) {
			try {
				$this->CURL->doPost( \TESTURLS::getUrlTests() . "&o=json&getjson=true", array( 'var1' => 'HasVar1' ) );
			} catch ( \Exception $wrapError ) {
				static::assertTrue( $wrapError->getCode() == 404 );
			}
		} else {
			static::markTestSkipped( "Can not test guzzle driver without guzzle" );
		}
	}
}
<?php

namespace TorneLIB;

require_once( __DIR__ . "/../vendor/autoload.php" );

use PHPUnit\Framework\TestCase;
use \TorneLIB\MODULE_SSL;

class sslTest extends TestCase {

	/** @var MODULE_SSL */
	private $SSL;

	function setUp() {
		$this->SSL = new MODULE_SSL();
	}

	/**
	 * @test
	 * @testdox Get a certificate bundle
	 */
	public function getSslCertificate() {
		// Make sure the open_basedir is reset after other tests
		ini_set( 'open_basedir', "" );
		static::assertTrue( strlen( $this->SSL->getSslCertificateBundle( true ) ) > 0 );
	}

	/**
	 * @test
	 * @testdox If SSL is available, this will be a positive test
	 */
	public function getCurlSslAvailable() {
		$sslAvailable = MODULE_SSL::getCurlSslAvailable();
		static::assertCount( 0, $sslAvailable );
	}

	/**
	 * @test
	 * @testdox SSL hardening - nothing is allowed except for a correct SSL setup
	 */
	public function strictStream() {
		$sslArray = $this->SSL->getSslStreamContext();
		static::assertTrue( $sslArray['verify_peer'] == 1 && $sslArray['verify_peer_name'] == 1 && $sslArray['verify_host'] == 1 && $sslArray['allow_self_signed'] == 1 );
	}

	/**
	 * @test
	 * @testdox Make SSL validation sloppy, allow anything
	 */
	public function unStrictStream() {
		$this->SSL->setStrictVerification( false, true );
		$sslArray = $this->SSL->getSslStreamContext();
		static::assertTrue( $sslArray['verify_peer'] == false && $sslArray['verify_peer_name'] == false && $sslArray['verify_host'] == false && $sslArray['allow_self_signed'] == true );
	}

	/**
	 * @test
	 * @testdox Make SSL validation strict but allow self signed certificates
	 */
	public function strictStreamSelfSignedAllowed() {
		$this->SSL->setStrictVerification( true, true );
		$sslArray = $this->SSL->getSslStreamContext();
		static::assertTrue( $sslArray['verify_peer'] == true && $sslArray['verify_peer_name'] == true && $sslArray['verify_host'] == true && $sslArray['allow_self_signed'] == true );
	}

	/**
	 * @test
	 * @testdox Get a generated context stream prepared for the SSL configuration
	 */
	function sslStream() {
		$streamContext = $this->SSL->getSslStream();
		static::assertTrue( is_resource( $streamContext['stream_context'] ) );
	}

}

<?php

use TorneLIB\TorneLIB_IO;
use PHPUnit\Framework\TestCase;

require_once( '../vendor/autoload.php' );

class TorneLIB_IOTest extends TestCase {

	/** @var $IO TorneLIB_IO */
	private $IO;

	private $arr = array(
		'a' => 'b',
		'b' => array(
			'c' => 'd'
		)
	);
	private $obj;

	function setUp() {
		$this->IO                = new TorneLIB_IO();
		$this->obj               = new stdClass();
		$this->obj->a            = new stdClass();
		$this->obj->a->nextLevel = array(
			'arrayLevel' => "part 1",
			'nextLevel'  => array(
				'recursiveLevel' => 'yes'
			)
		);
	}

	function teardown() {

	}

	function testObjectToArray() {
		$convert = $this->IO->objectsIntoArray( $this->obj );
		$this->assertTrue( is_array( $convert['a'] ) && is_array( $convert['a']['nextLevel'] ) && is_array( $convert['a']['nextLevel']['nextLevel'] ) );
	}

	function testArrayToObject() {
		$convert = $this->IO->arrayObjectToStdClass( $this->arr );
		$this->assertTrue( isset( $convert->a ) && is_object( $convert->b ) && isset( $convert->b->c ) && $convert->b->c == "d" );
	}

	function testRenderJsonApiLike() {
		$this->assertTrue( strlen( $this->IO->renderJson( $this->obj ) ) >= 170 );
	}

	function testRenderSerializedApiLike() {
		$this->assertTrue( strlen( $this->IO->renderPhpSerialize( $this->obj ) ) >= 153 );
	}

	function testRenderYamlApiLike() {
		try {
			$yamlString = $this->IO->renderYaml( $this->obj );
		} catch ( \Exception $yamlException ) {

		}
		$this->assertTrue( strlen( $yamlString ) >= 90 );
	}

	function testRenderXmlApiLike() {
		$this->assertTrue( strlen( $this->IO->renderXml( $this->obj ) ) >= 248 );
	}
}

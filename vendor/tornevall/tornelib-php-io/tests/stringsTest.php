<?php

namespace IO\Data;

use PHPUnit\Framework\TestCase;
use TorneLIB\IO\Data\Strings;

require_once(__DIR__ . '/../vendor/autoload.php');

/**
 * Class stringsTest
 * @package IO\Data
 * @version 6.1.0
 * @since 6.0
 */
class stringsTest extends TestCase
{
    /**
     * @test
     * Test conversion of snake_case to camelCase.
     */
    public function getCamelCase()
    {
        static::assertTrue(
            (new Strings())->getCamelCase('base64url_encode') === 'base64urlEncode'
        );
    }

    /**
     * @test
     * Test conversion of camelCase to snake_case.
     */
    public function getSnakeCase()
    {
        static::assertTrue(
            (new Strings())->getSnakeCase('base64urlEncode') === 'base64url_encode'
        );
    }

    /**
     * @test
     * Translate stringed by to integerbytes.
     */
    public function getBytes()
    {
        static::assertTrue(
            (int)(new Strings())->getBytes('2048M') === 2147483648
        );
    }

    /**
     * @test
     * Testing base64 encoded string with the standardize camelCase.
     */
    public function getBase64Camel()
    {
        static::assertTrue(
            (new Strings())->base64urlEncode('base64') === 'YmFzZTY0'
        );
    }

    /**
     * @test
     * Testing the old snakecase variant.
     */
    public function getBase64Snake()
    {
        static::assertTrue(
            (new Strings())->base64url_encode('base64') === 'YmFzZTY0'
        );
    }
}

<?php

require_once(__DIR__ . '/../vendor/autoload.php');

use TorneLIB\Exception\ExceptionHandler;
use PHPUnit\Framework\TestCase;

class ExceptionHandlerTest extends TestCase
{
    /**
     * @test
     */
    public function definedConstant() {
        try {
            throw new ExceptionHandler(
                'Generic Driver is not available test.',
                'LIB_GENERIC_DRIVER_UNAVAILABLE'
            );
        } catch (ExceptionHandler $e) {
            static::assertTrue($e->getCode() === 65009);
        }
    }

    /**
     * @test
     */
    public function constantError() {
        try {
            throw new ExceptionHandler(
                'Test',
                'STRINGIFIED_ERROR'
            );
        } catch (ExceptionHandler $e) {
            static::assertTrue($e->getCode() === 65535);
        }
    }

    /**
     * @test
     */
    public function constantBadlyDefinedAsString() {
        try {
            throw new ExceptionHandler(
                'Test',
                '500'
            );
        } catch (ExceptionHandler $e) {
            static::assertTrue($e->getCode() === 500);
        }
    }

    /**
     * @test
     */
    public function stringifiedErrorCode() {
        try {
            throw new ExceptionHandler(
                'stringified error',
                0,
                null,
                'STRINGIFIED_ERROR'
            );
        } catch (ExceptionHandler $e) {
            static::assertTrue($e->getCode() === 'STRINGIFIED_ERROR');
        }
    }
}

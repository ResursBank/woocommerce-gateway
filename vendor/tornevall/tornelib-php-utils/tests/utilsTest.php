<?php

require_once(__DIR__ . '/../vendor/autoload.php');

use PHPUnit\Framework\TestCase;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Utils\Generic;
use TorneLIB\Utils\Memory;
use TorneLIB\Utils\Security;

/**
 * Class utilsTest
 * @version 1.0.0
 */
class utilsTest extends TestCase
{
    /**
     * @test
     * Adjust memory on fly.
     */
    public function testMemoryLimit()
    {
        $current = ini_get('memory_limit');
        (new Memory())->setMemoryLimit('2048M');
        $newCurrent = ini_get('memory_limit');

        static::assertTrue($current !== $newCurrent && $newCurrent === '2048M');
    }

    /**
     * @test
     * Adjust memory on fly.
     * @throws Exception
     */
    public function getMemoryLimitAdjusted()
    {
        $mem = new Memory();
        $mem->setMemoryLimit('2048M');
        static::assertTrue(
            (new Memory())->getMemoryLimitAdjusted('4096M')
        );
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function getDocBlockVersion()
    {
        static::assertTrue(
            version_compare(
                (new Generic())->getVersionByClassDoc(),
                '6.1.0',
                '>='
            )
        );
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function getDocBlockThrows()
    {
        static::assertTrue(
            (new Generic())->getDocBlockItem(
                'throws',
                'getDocBlockItem'
            ) === 'ReflectionException'
        );
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function getDocBlockSince()
    {
        $sinceString = (new Generic())->getDocBlockItem(
            '@since',
            'getDocBlockItem'
        );

        static::assertTrue(
            version_compare($sinceString, '6.1.0', '>=') ? true : false
        );
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function methodStates()
    {
        static::expectException(ExceptionHandler::class);
        (new Security())->getFunctionState('nisse');
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function getVersionByComposer()
    {
        static::assertStringStartsWith(
            '6.1',
            (new Generic())->getVersionByComposer(__FILE__)
        );
    }

    /**
     * @test
     */
    public function getTemplate()
    {
        $code = 0;
        $generic = new Generic();
        $generic->setTemplatePath(__DIR__ . '/templates');
        $html = $generic->getTemplate('test.html', ['$username' => 'Sven', 'regularVariable' => 'Yep, it is regular.']);

        try {
            $uglyRequest = new Generic();
            $uglyRequest->getTemplate(
                '/etc/passwd',
                [
                    '$username' => 'Sven',
                    'regularVariable' => 'Yep, it is regular.',
                ]
            );
        } catch (Exception $e) {
            $code = $e->getCode();
        }

        static::assertTrue(
            $code === 404 &&
            (bool)preg_match('/it is regular/', $html)
        );
    }

    /**
     * @test
     * @throws ReflectionException
     * @since 6.1.9
     */
    public function getClassShort()
    {
        $withReflection = (new Generic())->getShortClassName(Generic::class);
        $withoutReflection = (new Generic())->getShortClassName(Generic::class, true);

        static::assertTrue(
            $withoutReflection === 'Generic' &&
            $withReflection === 'Generic'
        );
    }
}

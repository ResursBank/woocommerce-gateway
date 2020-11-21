<?php

use PHPUnit\Framework\TestCase;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\MODULE_IO;
use TorneLIB\Utils\Generic;

require_once(__DIR__ . '/../vendor/autoload.php');

class arrayTest extends TestCase
{
    /**
     * @test
     * If exceptions for unknown methods works.
     */
    public function noRequest()
    {
        static::expectException(ExceptionHandler::class);

        (new MODULE_IO())->getNothing();
    }

    /**
     * @test
     * Test the array-to-stdobject-conversion tool.
     */
    public function arrayToObject()
    {
        $array = [
            'part1' => 'string1',
            'part2' => [
                'subsection' => 'subvalue',
            ],
        ];
        $content = (new Arrays())->arrayObjectToStdClass($array);

        static::assertTrue(
            isset($content->part1) &&
            isset($content->part2) &&
            isset($content->part2->subsection)
        );
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function objectToArray()
    {
        $array = [
            'part1' => 'string1',
            'part2' => [
                'subsection' => 'subvalue',
            ],
        ];
        $content = (new Arrays())->objectsIntoArray(
            (new Arrays())->arrayObjectToStdClass($array)
        );

        static::assertTrue(
            isset($content['part1']) &&
            isset($content['part2']) &&
            isset($content['part2']['subsection'])
        );
    }

    /**
     * @test
     * @since 6.1.0
     */
    public function getArrayPaired()
    {
        $arrays = new Arrays();
        $inputArray = [
            'a',
            'b',
            'c',
            'd',
        ];

        $assoc = $arrays->getArrayPair(
            $inputArray
        );
        static::assertTrue(count($assoc) === 2 && isset($assoc['a']));
    }

    /**
     * @throws Exception
     * @test
     */
    public function getSimpleHtmlAsJson() {
        $generic = new Generic();
        $generic->setTemplatePath(__DIR__ . '/templates');
        $html = $generic->getTemplate('simple.html');

        $htmlJson = json_decode((new Arrays())->getHtmlAsJson(
            $html,
            [
                'assoc' => true,
            ]
        ));
        static::assertTrue(isset($htmlJson->{'html[noClass]'}));
    }

    /**
     * @test
     */
    public function getZineHtmlAsJson()
    {
        $generic = new Generic();
        $generic->setTemplatePath(__DIR__ . '/templates');
        $html = $generic->getTemplate('moviezine.html');

        $htmlJsonString = (new Arrays())->getHtmlAsJson($html);
        $htmlJsonStringAssoc = (new Arrays())->getHtmlAsJson(
            $html,
            [
                'assoc' => true,
                'duplicate' => 'assoc',
            ]
        );
        $htmlArrayStringAssoc = (new Arrays())->getHtmlAsArray(
            $html,
            [
                'assoc' => true,
                'duplicate' => 'assoc',
            ]
        );
        $htmlJson = json_decode($htmlJsonString);
        static::assertTrue(isset($htmlJson->children) && !empty($htmlJsonStringAssoc) && isset($htmlArrayStringAssoc['html[no_lightbox]']));
    }

    /**
     * @test
     */
    public function getZineElementFromHtmlJson()
    {
        $generic = new Generic();
        $generic->setTemplatePath(__DIR__ . '/templates');
        $html = $generic->getTemplate('moviezine.html');

        $arrays = new Arrays();
        $htmlAssoc = $arrays->getHtmlElements($html, ['div', 'a'], ['class' => 'inner_article', 'assoc' => true]);
        $elements = $arrays->getHtmlElements($html, ['div', 'a'], ['class' => 'inner_article']);
        static::assertCount(20, $elements);
        static::assertCount(2, $htmlAssoc);
    }
}

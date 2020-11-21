<?php

use PHPUnit\Framework\TestCase;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\IO\Data\Content;
use TorneLIB\Utils\Generic;

require_once(__DIR__ . '/../vendor/autoload.php');

class contentTest extends TestCase
{
    /**
     * @var string $xml
     */
    private $xml = '<?xml version="1.0"?><XMLResponse><a><nextLevel><arrayLevel>part 1</arrayLevel><nextLevel><recursiveLevel>yes</recursiveLevel></nextLevel></nextLevel></a></XMLResponse>';

    /**
     * @var string
     */
    private $cdataxml = '<foo><a><![CDATA[Hello, world!]]></a></foo>';

    /**
     * @var string
     */
    private $rssDataXml = '<?xml version="1.0" encoding="UTF-8" ?><rss><channel><item><title><![CDATA[Tom & Jerry]]></title></item></channel></rss>';

    /**
     * @test
     */
    public function getFromXml()
    {
        $fromXml = (new Content())->getFromXml($this->xml, 1, 'a');

        static::assertTrue(
            isset($fromXml->a)
        );
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function getFromCdata()
    {
        $fromXml = (new Content())->getFromXml($this->cdataxml, 1, 'a');

        static::assertTrue(
            isset($fromXml->a)
        );
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function getFromRss()
    {
        $fromXml = (new Content())->getFromXml($this->rssDataXml, 1, 'channel');
        static::assertTrue(
            isset($fromXml->channel)
        );
    }

    /**
     * @test
     */
    public function getXmlFromArray()
    {
        $array = [
            'part1' => 'sträng1',
            'part2' => [
                'subsection' => 'subvalue',
            ],
        ];

        // Note: There is a bug in 6.0 that prevents recursion.
        $responseXml = (new Content())->getXmlFromArray($array);
        static::assertTrue(
            preg_match(
                '/subsection/is',
                $responseXml
            ) ? true : false
        );
    }
}

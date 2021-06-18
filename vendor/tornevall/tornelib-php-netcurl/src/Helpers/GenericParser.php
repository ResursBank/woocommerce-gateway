<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE.md for license details.
 */

namespace TorneLIB\Helpers;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Content;
use TorneLIB\Module\Network\Wrappers\RssWrapper;

/**
 * Class GenericParser A generic parser which is shared over many classes.
 *
 * @package TorneLIB\Module\Config
 * @since 6.1.0
 */
class GenericParser
{
    /**
     * @param $string
     * @param string $returnData
     * @return int|string
     * @since 6.1.0
     */
    public static function getHttpHead($string, $returnData = 'code')
    {
        if (!is_string($string)) {
            // Casting is probably not the right way to handle this so we'll reset it instead.
            $string = '';
        }
        $return = $string;
        $headString = preg_replace(
            '/(.*?)\sHTTP\/(.*?)\s(.*)$/is',
            '$3',
            trim($string)
        );

        if ((bool)preg_match('/\s/', $headString)) {
            $headContent = explode(' ', $headString, 2);

            // Make sure there is no extras when starting to extract this data.
            if (($returnData === 'code' && (int)$headContent[1] > 0) ||
                (
                    !is_numeric($headContent[0]) &&
                    0 === stripos($headContent[0], "http") &&
                    (bool)preg_match(
                        '/\s/',
                        $headContent[1]
                    )
                )
            ) {
                // Drop one to the left, and retry.
                $headContent = explode(' ', trim($headContent[1]), 2);
            }

            switch ($returnData) {
                case 'code':
                    if ((int)$headContent[0]) {
                        $return = (int)$headContent[0];
                    }
                    break;
                case 'message':
                    $return = isset($headContent[1]) ? (string)$headContent[1] : '';
                    break;
                default:
                    $return = $string;
                    break;
            }
        }

        return $return;
    }

    /**
     * @param $content
     * @param $contentType
     * @return array|mixed
     * @since 6.1.0
     */
    public static function getParsed($content, $contentType)
    {
        $return = $content;

        switch ($contentType) {
            case !empty($contentType) && (bool)preg_match('/\/xml|\+xml/i', $contentType):
                // More detection possibilites.
                /* <?xml version="1.0" encoding="UTF-8"?><rss version="2.0"*/

                // If Laminas is available, prefer that engine before simple xml.
                if ((bool)preg_match('/\/xml|\+xml/i', $contentType) && class_exists('Laminas\Feed\Reader\Reader')) {
                    $return = (new RssWrapper())->getParsed($content);
                    break;
                }
                $return = (new Content())->getFromXml($content);
                break;
            case (bool)preg_match('/\/json/i', $contentType):
                // If this check is not a typecasted check, things will break bad.
                if (is_array($content)) {
                    // Did we get bad content?
                    $content = json_encode($content);
                }
                $return = json_decode($content, false);
                break;
            default:
                break;
        }

        return $return;
    }
}

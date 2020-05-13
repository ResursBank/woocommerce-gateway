<?php
/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Helpers;

use Exception;
use TorneLIB\Exception\Constants;

/**
 * Class Version netcurl version guard, throws errors when running too low PHP-versions (below 5.4).
 *
 * @package TorneLIB\Helpers
 * @since 6.1.0
 * @version 6.1.0
 */
class Version
{
    /**
     * @param string $lowest
     * @param string $op
     * @throws Exception
     */
    public static function getRequiredVersion($lowest = '5.5', $op = '<')
    {
        if (version_compare(PHP_VERSION, $lowest, $op)) {
            throw new Exception(
                sprintf(
                    'Your PHP version is way too old (%s)! It is time to upgrade. ' .
                    'Try somthing above PHP 7.2 where PHP still has support. ' .
                    'If you still have no idea what this is, check out %s.',
                    PHP_VERSION,
                    'https://docs.tornevall.net/x/DoBPAw'
                ),
                Constants::LIB_TOO_OLD_PHP
            );
        }
    }
}

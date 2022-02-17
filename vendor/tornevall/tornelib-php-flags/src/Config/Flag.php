<?php

/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Config;

/**
 * Class Flag Static caller.
 *
 * @package TorneLIB\Config
 * @version 6.1.0
 */
class Flag
{
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(sprintf('TorneLIB\Flags::_%s', $name), $arguments);
    }
}

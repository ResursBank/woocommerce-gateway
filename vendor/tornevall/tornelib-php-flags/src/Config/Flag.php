<?php

/**
 * Copyright © Tomas Tornevall / Tornevall Networks. All rights reserved.
 * See LICENSE for license details.
 */

namespace TorneLIB\Config;

use TorneLIB\Flags;

/**
 * Class Flag Static caller.
 *
 * @package TorneLIB\Config
 * @version 6.1.5
 */
class Flag
{
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(sprintf('TorneLIB\Flags::_%s', $name), $arguments);
    }

    /**
     * Inherited setFlag. Sets internal flags and allows them to be locked.
     *
     * @param string $flagKey
     * @param null $flagValue
     * @param false $lock
     * @return bool
     * @since 6.1.5
     */
    public static function setFlag($flagKey = '', $flagValue = null, $lock = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Inherited getFlag.
     *
     * @param string $flagKey
     * @return mixed|null
     * @since 6.1.5
     */
    public static function getFlag($flagKey = '')
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Inherited isFlag. Returns whether a flag is true or false.
     * If flag is not set, false is automatically returned.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.5
     */
    public static function isFlag($flagKey = ''): bool
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Inherited hasFlag. Returns boolean if the flag exists.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.5
     */
    public static function hasFlag($flagKey = ''): bool
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @param array $arrayData
     * @return bool
     * @since 6.1.5
     */
    public static function isAssoc(array $arrayData): bool
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @param array $flags
     * @param array $lock
     * @return Flags
     * @since 6.1.5
     */
    public static function setFlags($flags = [], $lock = [])
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @return array
     * @since 6.1.5
     */
    public static function getFlags(): array
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Unset flag. The real function for deleteFlag and removeFlag.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.5
     */
    public static function unsetFlag($flagKey = ''): bool
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Remove flag. Equivalent to deleteFlag.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.5
     */
    public static function removeFlag($flagKey = ''): bool
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Delete flag. Equivalent to removeFlag.
     *
     * @param string $flagKey
     * @return bool
     * @since 6.1.5
     */
    public static function deleteFlag($flagKey = ''): bool
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @return Flags
     * @since 6.1.5
     */
    public static function clearAllFlags(): Flags
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @return Flags
     * @since 6.1.5
     */
    public static function getInstance(): Flags
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @return array
     * @since 6.1.5
     */
    public static function getAllFlags(): array
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }
}

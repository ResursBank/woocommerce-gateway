<?php

namespace TorneLIB\Utils;

/**
 * Class Ini
 * @package TorneLIB\Utils
 * @version 6.1.2
 */
class Ini
{
    /**
     * Check if the setting is settable with ini_set().
     *
     * @param $setting
     * @return bool
     * @since 6.1.0
     */
    public function getIniSettable($setting)
    {
        static $ini_all;

        if (!function_exists('ini_set')) {
            return false;
        }

        if (!isset($ini_all)) {
            $ini_all = false;
            // Sometimes `ini_get_all()` is disabled via the `disable_functions` option for "security purposes".
            if (function_exists('ini_get_all')) {
                $ini_all = ini_get_all();
            }
        }

        // Bit operator to workaround https://bugs.php.net/bug.php?id=44936 which changes access level
        // to 63 in PHP 5.2.6 - 5.2.17.
        if (isset($ini_all[$setting]['access']) &&
            (INI_ALL === ($ini_all[$setting]['access'] & 7)
                || INI_USER === ($ini_all[$setting]['access'] & 7))
        ) {
            return true;
        }

        // If we were unable to retrieve the details, fail gracefully to assume it's changeable.
        if (!is_array($ini_all)) {
            return true;
        }

        return false;
    }
}
<?php

namespace TorneLIB\Module\Network;

use TorneLIB\Module\Network;

/**
 * Class Statics Static requests without dependencies.
 *
 * @package TorneLIB\Module\Network
 */
abstract class Statics
{
    /**
     * Return information about currently used server protocol (HTTP or HTTPS).
     * If returnprotocol is false, returned result will be true for https.
     *
     * @param bool $returnProtocol
     * @return bool|string
     * @since 6.0.15
     */
    public static function getCurrentServerProtocol($returnProtocol = false)
    {
        if (isset($_SERVER['HTTPS'])) {
            if ($_SERVER['HTTPS'] == "on") {
                if (!$returnProtocol) {
                    $return = true;
                } else {
                    $return = "https";
                }
            } else {
                if (!$returnProtocol) {
                    $return = false;
                } else {
                    $return = "http";
                }
            }

            return $return;
        }

        if (!$returnProtocol) {
            $return = false;
        } else {
            $return = "http";
        }

        return $return;
    }

    /**
     * @param $redirecturl
     * @since 6.1.0
     */
    public static function redirect($redirecturl = '', $replaceHeader = false, $responseCode = 301)
    {
        (new Domain())->redirect($redirecturl, $replaceHeader, $responseCode);
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(
            [
                new Network(),
                $name
            ],
            $arguments
        );
    }
}

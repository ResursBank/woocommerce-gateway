<?php

namespace TorneLIB;

if (!class_exists('NETCURL_NETWORK_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_NETWORK_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_NETWORK_DRIVERS Supported network Addons
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    abstract class NETCURL_NETWORK_DRIVERS
    {
        const DRIVER_NOT_SET = 0;
        const DRIVER_CURL = 1;
        const DRIVER_WORDPRESS = 1000;
        const DRIVER_GUZZLEHTTP = 1001;
        const DRIVER_GUZZLEHTTP_STREAM = 1002;

        /**
         * @deprecated Internal driver should be named DRIVER_CURL
         */
        const DRIVER_INTERNAL = 1;
        const DRIVER_SOAPCLIENT = 2;

        /** @var int Using the class itself */
        const DRIVER_OWN_EXTERNAL = 100;

    }
}

if (!class_exists('TORNELIB_CURL_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TORNELIB_CURL_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class TORNELIB_CURL_DRIVERS
     *
     * @package    TorneLIB
     * @deprecated .0.20 Use NETCURL_NETWORK_DRIVERS
     */
    abstract class TORNELIB_CURL_DRIVERS extends NETCURL_NETWORK_DRIVERS
    {
    }
}

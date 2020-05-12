<?php

namespace TorneLIB;

if (!class_exists('NETCURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_ENVIRONMENT Unittest helping class
     *
     * @package    TorneLIB
     * @since      6.0.0
     * @deprecated 6.0.20 Not in use
     */
    abstract class NETCURL_ENVIRONMENT
    {
        const ENVIRONMENT_PRODUCTION = 0;
        const ENVIRONMENT_TEST = 1;
    }
}

if (!class_exists('TORNELIB_CURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TORNELIB_CURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /** @noinspection PhpDeprecationInspection */

    /**
     * Class TORNELIB_CURL_ENVIRONMENT
     *
     * @package    TorneLIB
     * @deprecated Use NETCURL_ENVIRONMENT
     * @since      6.0.0
     * @deprecated 6.0.20 Not in use
     */
    abstract class TORNELIB_CURL_ENVIRONMENT extends NETCURL_ENVIRONMENT
    {
    }
}

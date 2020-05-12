<?php

namespace TorneLIB;

if (!class_exists('NETCURL_RESPONSETYPE', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_RESPONSETYPE', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_RESPONSETYPE Assoc or object?
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    abstract class NETCURL_RESPONSETYPE
    {
        const RESPONSETYPE_ARRAY = 0;
        const RESPONSETYPE_OBJECT = 1;
    }

    if (!class_exists('TORNELIB_CURL_RESPONSETYPE', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
        !class_exists('TorneLIB\TORNELIB_CURL_RESPONSETYPE', NETCURL_CLASS_EXISTS_AUTOLOAD)
    ) {

        /**
         * Class TORNELIB_CURL_RESPONSETYPE
         *
         * @package    TorneLIB
         * @deprecated 6.0.20 Use NETCURL_RESPONSETYPE
         */
        abstract class TORNELIB_CURL_RESPONSETYPE extends NETCURL_RESPONSETYPE
        {
        }
    }
}

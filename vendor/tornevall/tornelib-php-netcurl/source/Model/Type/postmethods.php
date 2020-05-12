<?php

namespace TorneLIB;

if (!class_exists('NETCURL_POST_METHODS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_POST_METHODS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_POST_METHODS List of methods available in this library
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    abstract class NETCURL_POST_METHODS
    {
        const METHOD_GET = 0;
        const METHOD_POST = 1;
        const METHOD_PUT = 2;
        const METHOD_DELETE = 3;
        const METHOD_HEAD = 4;
        const METHOD_REQUEST = 5;
    }
}

if (!class_exists('CURL_METHODS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\CURL_METHODS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_POST_METHODS
     */
    abstract class CURL_METHODS extends NETCURL_POST_METHODS
    {
    }
}

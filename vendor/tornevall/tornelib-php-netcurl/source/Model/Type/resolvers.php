<?php

namespace TorneLIB;

if (!class_exists('NETCURL_RESOLVER', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_RESOLVER', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_RESOLVER Class definitions on how to resolve things on lookups
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    abstract class NETCURL_RESOLVER
    {
        const RESOLVER_DEFAULT = 0;
        const RESOLVER_IPV4 = 1;
        const RESOLVER_IPV6 = 2;
    }
}

if (!class_exists('CURL_RESOLVER', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\CURL_RESOLVER', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_RESOLVER
     */
    abstract class CURL_RESOLVER extends NETCURL_RESOLVER
    {
    }
}

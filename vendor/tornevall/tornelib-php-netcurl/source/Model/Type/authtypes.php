<?php

namespace TorneLIB;

if (!class_exists('NETCURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class CURL_AUTH_TYPES Available authentication types for use with password protected sites
     * The authentication types listed in this section defines what is fully supported by the module. In other cases you might be on your own.
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    abstract class NETCURL_AUTH_TYPES
    {
        const AUTHTYPE_NONE = 0;
        const AUTHTYPE_BASIC = 1;
    }
}
if (!class_exists('CURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\CURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_AUTH_TYPES
     */
    abstract class CURL_AUTH_TYPES extends NETCURL_AUTH_TYPES
    {
    }
}
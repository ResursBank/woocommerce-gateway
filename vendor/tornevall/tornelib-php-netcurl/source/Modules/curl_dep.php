<?php

namespace TorneLIB;

if (!class_exists('Tornevall_cURL', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\Tornevall_cURL', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class MODULE_CURL
     *
     * @package    TorneLIB
     * @throws \Exception
     * @deprecated 6.0.20
     */
    class Tornevall_cURL extends MODULE_CURL
    {
        function __construct(
            $requestUrl = '',
            $requestPostData = [],
            $requestPostMethod = NETCURL_POST_METHODS::METHOD_POST,
            $requestFlags = []
        ) {
            return parent::__construct($requestUrl, $requestPostData, $requestPostMethod);
        }
    }
}

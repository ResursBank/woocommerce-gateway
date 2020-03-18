<?php

namespace TorneLIB;

if (!class_exists('Tornevall_SimpleSoap', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\Tornevall_SimpleSoap', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class MODULE_CURL
     *
     * @package    TorneLIB
     * @deprecated 6.0.20 Use MODULE_SOAP
     */
    class Tornevall_SimpleSoap extends MODULE_SOAP
    {
        function __construct(string $Url, $that = null)
        {
            parent::__construct($Url, $that);
        }
    }
}

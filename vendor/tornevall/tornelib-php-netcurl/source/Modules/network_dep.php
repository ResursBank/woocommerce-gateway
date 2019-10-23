<?php

namespace TorneLIB;

if (!class_exists('TorneLIB_Network', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TorneLIB_Network', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class MODULE_CURL
     *
     * @package    TorneLIB
     * @deprecated 6.0.20 Use MODULE_NETWORK
     */
    class TorneLIB_Network extends MODULE_NETWORK
    {
        function __construct()
        {
            parent::__construct();
        }
    }
}

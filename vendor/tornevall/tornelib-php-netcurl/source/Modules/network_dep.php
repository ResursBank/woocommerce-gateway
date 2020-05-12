<?php

namespace TorneLIB;

if (!class_exists('TorneLIB_Network', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TorneLIB_Network', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class MODULE_CURL
     *
     * @package    TorneLIB
     * @deprecated From 6.0.20 use MODULE_NETWORK, removed in 6.1 and replaced with PSR4 compliances.
     */
    class TorneLIB_Network extends MODULE_NETWORK
    {
        public function __construct()
        {
            parent::__construct();
        }
    }
}

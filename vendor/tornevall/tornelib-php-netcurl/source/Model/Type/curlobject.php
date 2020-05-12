<?php

namespace TorneLIB;

if (!class_exists('NETCURL_HTTP_OBJECT', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_HTTP_OBJECT', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_CURLOBJECT
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    class NETCURL_HTTP_OBJECT
    {
        private $NETCURL_HEADER;
        private $NETCURL_BODY;
        private $NETCURL_CODE;
        private $NETCURL_PARSED;
        private $NETCURL_URL;
        private $NETCURL_IP;

        public function __construct($header = [], $body = '', $code = 0, $parsed = '', $url = '', $ip = '')
        {
            $this->NETCURL_HEADER = $header;
            $this->NETCURL_BODY = $body;
            $this->NETCURL_CODE = $code;
            $this->NETCURL_PARSED = $parsed;
            $this->NETCURL_URL = $url;
            $this->NETCURL_IP = $ip;
        }

        public function getHeader()
        {
            return $this->NETCURL_HEADER;
        }

        public function getBody()
        {
            return $this->NETCURL_BODY;
        }

        public function getCode()
        {
            return $this->NETCURL_CODE;
        }

        public function getParsed()
        {
            return $this->NETCURL_PARSED;
        }

        public function getUrl()
        {
            $this->NETCURL_URL;
        }

        public function getIp()
        {
            return $this->NETCURL_IP;
        }
    }
}

if (!class_exists('TORNELIB_CURLOBJECT', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TORNELIB_CURLOBJECT', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class TORNELIB_CURLOBJECT
     *
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_HTTP_OBJECT
     */
    class TORNELIB_CURLOBJECT extends NETCURL_HTTP_OBJECT
    {
        public $header;
        public $body;
        public $code;
        public $parsed;
        public $url;
        public $ip;
    }
}

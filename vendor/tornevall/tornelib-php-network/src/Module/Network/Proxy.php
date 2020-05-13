<?php

namespace TorneLIB\Module\Network;

class Proxy
{
    /**
     * @var array $proxyHeaders List of scannable proxy headers from webserver.
     *
     * @since 6.0
     */
    private $proxyHeaders = array(
        'HTTP_VIA',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED_FOR_IP',
        'VIA',
        'X_FORWARDED_FOR',
        'FORWARDED_FOR',
        'X_FORWARDED',
        'FORWARDED',
        'CLIENT_IP',
        'FORWARDED_FOR_IP',
        'HTTP_PROXY_CONNECTION'
    );

    /**
     * @var array $proxyAddressList Address list with catched proxy addresses if published by client.
     *
     * @since 6.1.0
     */
    private $proxyAddressList = array();

    /**
     * Address constructor.
     * @since 6.1.0
     */
    public function __construct()
    {
        $this->fetchProxyHeaders();
    }

    /**
     * Pick up data from browser client if any.
     *
     * @return array
     * @since 6.1.0
     */
    private function fetchProxyHeaders()
    {
        if (isset($_SERVER) && is_array($_SERVER)) {
            foreach ($this->proxyHeaders as $headerKey) {
                $this->proxyAddressList[$headerKey] = isset($_SERVER[$headerKey]) ? $_SERVER[$headerKey] : null;
            }
        }

        return $this->proxyAddressList;
    }

    /**
     * @return array
     * @snice 6.1.0
     */
    public function getProxyHeaders()
    {
        return $this->proxyHeaders;
    }

    /**
     * @param bool $withValues
     * @return array
     * @since 6.1.0
     */
    public function getProxyData($withValues = true)
    {
        $return = array();
        foreach ($this->proxyAddressList as $key => $value) {
            if (($withValues && !empty($value)) || !$withValues) {
                $return[$key] = $value;
            }
        }

        return $return;
    }
}

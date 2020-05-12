<?php

namespace TorneLIB;

if (!class_exists('NETCURL_IP_PROTOCOLS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_IP_PROTOCOLS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_IP_PROTOCOLS IP Address Types class
     *
     * @package TorneLIB
     * @since   6.0.20
     * @deprecated Entirely removed from 6.1.0
     */
    abstract class NETCURL_IP_PROTOCOLS
    {
        const PROTOCOL_NONE = 0;
        const PROTOCOL_IPV4 = 4;
        const PROTOCOL_IPV6 = 6;
    }

}
if (!class_exists('TorneLIB_Network_IP', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TorneLIB_Network_IP', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class TorneLIB_Network_IP
     *
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_IP_PROTOCOLS - entirely removed from netcurl 6.1
     */
    abstract class TorneLIB_Network_IP extends NETCURL_IP_PROTOCOLS
    {
        const IPTYPE_NONE = 0;
        const IPTYPE_V4 = 4;
        const IPTYPE_V6 = 6;
    }
}

if (!class_exists('TorneLIB_Network_IP_Protocols', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TorneLIB_Network_IP_Protocols', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /** @noinspection PhpDeprecationInspection */

    /**
     * Class TorneLIB_Network_IP_Protocols
     *
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_IP_PROTOCOLS
     */
    abstract class TorneLIB_Network_IP_Protocols extends TorneLIB_Network_IP
    {
    }
}

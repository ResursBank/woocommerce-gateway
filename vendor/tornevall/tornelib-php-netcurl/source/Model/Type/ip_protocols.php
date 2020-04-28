<?php

/**
 * Copyright 2018 Tomas Tornevall & Tornevall Networks
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Tornevall Networks netCurl library - Yet another http- and network communicator library
 * Each class in this library has its own version numbering to keep track of where the changes are. However, there is a
 * major version too.
 *
 * @package TorneLIB
 */

namespace TorneLIB;

if (!class_exists('NETCURL_IP_PROTOCOLS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_IP_PROTOCOLS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_IP_PROTOCOLS IP Address Types class
     *
     * @package TorneLIB
     * @since   6.0.20
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
     * @deprecated 6.0.20 Use NETCURL_IP_PROTOCOLS
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

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

if (!class_exists('NETCURL_NETWORK_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_NETWORK_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_NETWORK_DRIVERS Supported network Addons
     *
     * @package TorneLIB
     * @since   6.0.20
     */
    abstract class NETCURL_NETWORK_DRIVERS
    {
        const DRIVER_NOT_SET = 0;
        const DRIVER_CURL = 1;
        const DRIVER_WORDPRESS = 1000;
        const DRIVER_GUZZLEHTTP = 1001;
        const DRIVER_GUZZLEHTTP_STREAM = 1002;

        /**
         * @deprecated Internal driver should be named DRIVER_CURL
         */
        const DRIVER_INTERNAL = 1;
        const DRIVER_SOAPCLIENT = 2;

        /** @var int Using the class itself */
        const DRIVER_OWN_EXTERNAL = 100;

    }
}

if (!class_exists('TORNELIB_CURL_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TORNELIB_CURL_DRIVERS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class TORNELIB_CURL_DRIVERS
     *
     * @package    TorneLIB
     * @deprecated .0.20 Use NETCURL_NETWORK_DRIVERS
     */
    abstract class TORNELIB_CURL_DRIVERS extends NETCURL_NETWORK_DRIVERS
    {
    }
}

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

if ( ! class_exists('NETCURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD) && ! class_exists('TorneLIB\NETCURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD)) {
    /**
     * Class NETCURL_ENVIRONMENT Unittest helping class
     *
     * @package    TorneLIB
     * @since      6.0.0
     * @deprecated 6.0.20 Not in use
     */
    abstract class NETCURL_ENVIRONMENT
    {
        const ENVIRONMENT_PRODUCTION = 0;
        const ENVIRONMENT_TEST = 1;
    }
}

if ( ! class_exists('TORNELIB_CURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD) && ! class_exists('TorneLIB\TORNELIB_CURL_ENVIRONMENT', NETCURL_CLASS_EXISTS_AUTOLOAD)) {
    /** @noinspection PhpDeprecationInspection */

    /**
     * Class TORNELIB_CURL_ENVIRONMENT
     *
     * @package    TorneLIB
     * @deprecated Use NETCURL_ENVIRONMENT
     * @since      6.0.0
     * @deprecated 6.0.20 Not in use
     */
    abstract class TORNELIB_CURL_ENVIRONMENT extends NETCURL_ENVIRONMENT
    {
    }
}
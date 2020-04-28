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

if (!class_exists('NETCURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class CURL_AUTH_TYPES Available authentication types for use with password protected sites
     * The authentication types listed in this section defines what is fully supported by the module. In other cases you might be on your own.
     *
     * @package TorneLIB
     * @since   6.0.20
     */
    abstract class NETCURL_AUTH_TYPES
    {
        const AUTHTYPE_NONE = 0;
        const AUTHTYPE_BASIC = 1;
    }
}
if (!class_exists('CURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\CURL_AUTH_TYPES', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * @package    TorneLIB
     * @deprecated 6.0.20 Use NETCURL_AUTH_TYPES
     */
    abstract class CURL_AUTH_TYPES extends NETCURL_AUTH_TYPES
    {
    }
}
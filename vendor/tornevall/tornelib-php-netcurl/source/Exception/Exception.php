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

if (!class_exists('TorneLIB_NETCURL_EXCEPTIONS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TorneLIB_NETCURL_EXCEPTIONS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_EXCEPTIONS
     *
     * @package TorneLIB
     */
    abstract class NETCURL_EXCEPTIONS
    {
        const NETCURL_NO_ERROR = 0;
        const NETCURL_EXCEPTION_IT_WORKS = 1;
        const NETCURL_EXCEPTION_IT_DOESNT_WORK = 500;


        /**
         * @deprecated
         */
        const NETCURL_CURL_MISSING = 1000;
        const NETCURL_SETFLAG_KEY_EMPTY = 1001;

        /**
         * @deprecated
         */
        const NETCURL_COOKIEPATH_SETUP_FAIL = 1002;
        const NETCURL_IPCONFIG_NOT_VALID = 1003;

        /**
         * @deprecated
         */
        const NETCURL_SETSSLVERIFY_UNVERIFIED_NOT_SET = 1004;
        const NETCURL_DOMDOCUMENT_CLASS_MISSING = 1005;
        const NETCURL_GETPARSEDVALUE_KEY_NOT_FOUND = 1006;
        const NETCURL_SOAPCLIENT_CLASS_MISSING = 1007;
        const NETCURL_SIMPLESOAP_GETSOAP_CREATE_FAIL = 1008;
        const NETCURL_WP_TRANSPORT_ERROR = 1009;
        const NETCURL_CURL_DISABLED = 1010;

        /**
         * @deprecated
         */
        const NETCURL_NOCOMM_DRIVER = 1011;
        /**
         * @deprecated
         */
        const NETCURL_EXTERNAL_DRIVER_MISSING = 1012;

        const NETCURL_GUZZLESTREAM_MISSING = 1013;
        const NETCURL_HOSTVALIDATION_FAIL = 1014;
        const NETCURL_PEMLOCATIONDATA_FORMAT_ERROR = 1015;
        const NETCURL_DOMDOCUMENT_EMPTY = 1016;
        const NETCURL_NO_DRIVER_AVAILABLE_NOT_EVEN_CURL = 1017;
        const NETCURL_UNEXISTENT_FUNCTION = 1018;
        const NETCURL_PARSE_XML_FAILURE = 1019;
        const NETCURL_IO_PARSER_MISSING = 1020;
        const NETCURL_GUZZLE_RESPONSE_EXCEPTION = 1021;
        const NETCURL_WP_REQUEST_ERROR = 1022;
    }
}

if (!class_exists('TorneLIB_NETCURL_EXCEPTIONS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\TorneLIB_NETCURL_EXCEPTIONS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class TORNELIB_NETCURL_EXCEPTIONS
     *
     * @package    TorneLIB
     * @deprecated Use NETCURL_EXCEPTIONS
     */
    abstract class TORNELIB_NETCURL_EXCEPTIONS extends NETCURL_EXCEPTIONS
    {
    }
}
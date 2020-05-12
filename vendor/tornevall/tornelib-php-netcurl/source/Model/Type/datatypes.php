<?php

namespace TorneLIB;

if (!class_exists('NETCURL_POST_DATATYPES', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\NETCURL_POST_DATATYPES', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * Class NETCURL_POST_DATATYPES
     * Prepared formatting for POST-content in this library (Also available from for example PUT)
     *
     * @package TorneLIB
     * @since 6.0.20
     * @deprecated Replaced with PSR4 compliances in v6.1
     */
    abstract class NETCURL_POST_DATATYPES
    {
        const DATATYPE_NOT_SET = 0;
        const DATATYPE_JSON = 1;
        const DATATYPE_SOAP = 2;
        const DATATYPE_XML = 3;
        const DATATYPE_SOAP_XML = 4;
    }
}
if (!class_exists('CURL_POST_AS', NETCURL_CLASS_EXISTS_AUTOLOAD) &&
    !class_exists('TorneLIB\CURL_POST_AS', NETCURL_CLASS_EXISTS_AUTOLOAD)
) {
    /**
     * @package TorneLIB
     * @deprecated 6.0.20 Use NETCURL_POST_DATATYPES
     */
    abstract class CURL_POST_AS extends NETCURL_POST_DATATYPES
    {
        /**
         * @deprecated Use NETCURL_POST_DATATYPES::DATATYPE_DEFAULT
         */
        const POST_AS_NORMAL = 0;
        /**
         * @deprecated Use NETCURL_POST_DATATYPES::DATATYPE_JSON
         */
        const POST_AS_JSON = 1;
        /**
         * @deprecated Use NETCURL_POST_DATATYPES::DATATYPE_SOAP
         */
        const POST_AS_SOAP = 2;
    }
}
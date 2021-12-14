<?php

namespace TorneLIB\Exception;

/**
 * Class Constants
 * @package TorneLIB\Exception
 * @version 6.1.14
 */
abstract class Constants
{
    /**
     * A "this is not an error" constant. For testing purposes. Everything should have an error code
     * so if this code ever occurs except for during tests, something went wrong.
     * @var int
     * @since 6.1.0
     */
    const LIB_NO_ERROR = 0;

    /**
     * All unhandled error codes unless we choose to transform into HTTP return codes.
     * @var int
     * @since 6.1.0
     */
    const LIB_UNHANDLED = 65535;

    /**
     * Library detected an invalid URL.
     * @var int
     * @since 6.1.0
     */
    const LIB_INVALID_URL = 1002;

    /**
     * Usually thrown when there's no URL present.
     * @var int
     * @since 6.1.0
     */
    const LIB_EMPTY_URL = 1003;

    /**
     * When trying to fetch headers from a curl session that is based on multiple requests, correct url must also be specifed.
     * @var int
     * @since 6.1.0
     */
    const LIB_MULTI_HEADER = 1004;

    /**
     * Normally thrown from a magic when variables are not set.
     * @var int
     * @since 6.1.0
     */
    const LIB_CONFIGWRAPPER_VAR_NOT_SET = 1005;

    /**
     * Thrown when phpUtils tries to (ini)set memory limit and is not allowed to.
     * @var int
     * @since 6.1.0
     */
    const LIB_UTILS_MEMORY_FAILSET = 1006;

    /**
     * Thrown on obsolete methods, used in earlier versions.
     * @var int
     * @since 6.1.0
     */
    const LIB_METHOD_OBSOLETE = 1007;

    /**
     * When no proper network driver could be found to communicate with.
     * @var int
     * @since 6.1.0
     */
    const LIB_NETCURL_NETWRAPPER_NO_DRIVER_FOUND = 1008;

    /**
     * Wrapper is unhandled.
     * @var int
     * @since 6.1.0
     */
    const LIB_NETCURL_NETWRAPPER_UNHANDLED_WRAPPER = 1009;

    /**
     * Used by getUrlDomain amongst others when validating host or domain name.
     * @var int
     * @since 6.1.0
     */
    const LIB_NETCURL_DOMAIN_OR_HOST_VALIDATION_FAILURE = 1010;

    /**
     * Used during ip address validations.
     * @var int
     * @since 6.1.0
     */
    const LIB_NETCURL_INVALID_IP_ADDRESS = 1011;

    /**
     * When an exception are thrown during a multi-url request, exceptions are collected and thrown back with this code.
     * @var int
     * @since 6.1.7
     */
    const LIB_NETCURL_CURL_MULTI_EXCEPTION_DISCOVERY = 1012;

    /**
     * Invalid path or file location.
     * @var int
     * @since 6.1.8
     */
    const LIB_INVALID_PATH = 1013;

    /**
     * When SoapClient is not instantiated before usage.
     * @var int
     * @since 6.1.11
     */
    const LIB_NETCURL_SOAPINSTANCE_MISSING = 1014;

    /**
     * @var int
     * @since 6.1.13
     */
    const LIB_NETCURL_SOAP_TIMEOUT = 1015;

    /**
     * @var int
     * @since 6.1.13
     */
    const LIB_NETCURL_SOAP_REQUEST_TIMER_NOT_READY = 1016;

    /**
     * Cipher does not exist in current openssl driver.
     * @var int
     * @since 6.1.8
     */
    const LIB_SSL_CIPHER_UNAVAILABLE = 3000;

    /**
     * No key or IV set for encryption.
     * @var int
     * @since 6.1.0
     */
    const LIB_SSL_CIPHER_NO_KEYS = 3001;

    /**
     * Genric database error.
     * @var int
     * @since 6.1.10
     */
    const LIB_DATABASE_GENERIC = 4000;

    /**
     * Database not set yet ("use schema").
     * @var int
     * @since 6.1.10
     */
    const LIB_DATABASE_NOT_SET = 4001;

    /**
     * Database type is not implemented.
     * @var int
     * @since 6.1.9
     */
    const LIB_DATABASE_NOT_IMPLEMENTED = 4002;

    /**
     * When database classes are loading configuration files that is empty, they can not be used properly.
     * @var int
     * @since 6.1.9
     */
    const LIB_DATABASE_EMPTY_JSON_CONFIG = 4003;

    /**
     * When not MySQL method is available.
     * @var int
     * @since 6.1.9
     */
    const LIB_DATABASE_DRIVER_UNAVAILABLE = 4004;

    /**
     * When trying to handle a database connection without initialization.
     * @var int
     * @since 6.1.9
     */
    const LIB_DATABASE_NO_CONNECTION_INITIALIZED = 4005;

    /**
     * When connection fails to a database server.
     * @var int
     * @since 6.1.9
     */
    const LIB_DATABASE_CONNECTION_EXCEPTION = 4006;


    /**
     * Library class should not be used any more as it is considred deprecated/obsolete.
     * @var int
     * @since 6.1.0
     */
    const LIB_DEPRECATED_CLASS = 65000;

    /**
     * Library class is not available, as it is missing or not installed.
     * @var int
     * @since 6.1.0
     */
    const LIB_CLASS_UNAVAILABLE = 65001;

    /**
     * Method/function unavailable. Normally thrown when library or driver is missing. Seen in CryptoLib.
     * @var int
     * @since 6.1.0
     */
    const LIB_METHOD_OR_LIBRARY_UNAVAILABLE = 65002;

    /**
     * Method/function is disabled.
     * @var int
     * @since 6.1.0
     */
    const LIB_METHOD_OR_LIBRARY_DISABLED = 65003;

    /**
     * Library class is not available, as it has been disabled (preferrably from php.ini).
     * @var int
     * @since 6.1.0
     */
    const LIB_CLASS_DISABLED = 65004;

    /**
     * IO Library could not extract data properly.
     * @var int
     * @since 6.1.0
     */
    const LIB_IO_EXTRACT_XPATH_ERROR = 65005;

    /**
     * Thrown from the Flag class.
     * @var int
     * @since 6.1.0
     */
    const LIB_FLAG_EXCEPTION = 65006;

    /**
     * PHP version is too old.
     * @var int
     * @since 6.1.5
     */
    const LIB_TOO_OLD_PHP = 65007;

    /**
     * SSL capabilities unavailable.
     * @var int
     * @since 6.1.9
     */
    const LIB_SSL_UNAVAILABLE = 65008;

    /**
     * Generic unavailable driver.
     * @var int
     * @since 6.1.9
     */
    const LIB_GENERIC_DRIVER_UNAVAILABLE = 65009;
}

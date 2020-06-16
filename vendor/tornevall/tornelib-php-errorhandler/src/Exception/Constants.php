<?php

namespace TorneLIB\Exception;

/**
 * Class Constants
 * @package TorneLIB\Exception
 */
abstract class Constants
{
    /**
     * A "this is not an error" constant. For testing purposes. Everything should have an error code
     * so if this code ever occurs except for during tests, something went wrong.
     * @var int
     */
    const LIB_NO_ERROR = 0;

    /**
     * All unhandled error codes unless we choose to transform into HTTP return codes.
     * @var int
     */
    const LIB_UNHANDLED = 65535;

    /**
     * Library detected an invalid URL.
     * @var int
     */
    const LIB_INVALID_URL = 1002;

    /**
     * Usually thrown when there's no URL present.
     * @var int
     */
    const LIB_EMPTY_URL = 1003;

    /**
     * When trying to fetch headers from a curl session that is based on multiple requests, correct url must also be specifed.
     * @var int
     */
    const LIB_MULTI_HEADER = 1004;

    /**
     * Normally thrown from a magic when variables are not set.
     * @var int
     */
    const LIB_CONFIGWRAPPER_VAR_NOT_SET = 1005;

    /**
     * Thrown when phpUtils tries to (ini)set memory limit and is not allowed to.
     * @var int
     */
    const LIB_UTILS_MEMORY_FAILSET = 1006;

    /**
     * Thrown on obsolete methods, used in earlier versions.
     * @var int
     */
    const LIB_METHOD_OBSOLETE = 1007;

    /**
     * @var int When no proper network driver could be found to communicate with.
     */
    const LIB_NETCURL_NETWRAPPER_NO_DRIVER_FOUND = 1008;

    /**
     * @var int Wrapper is unhandled.
     */
    const LIB_NETCURL_NETWRAPPER_UNHANDLED_WRAPPER = 1009;

    /**
     * @var int Used by getUrlDomain amongst others when validating host or domain name.
     */
    const LIB_NETCURL_DOMAIN_OR_HOST_VALIDATION_FAILURE = 1010;

    /**
     * @var int Used during ip address validations.
     */
    const LIB_NETCURL_INVALID_IP_ADDRESS = 1011;

    /**
     * @var int When an exception are thrown during a multi-url request, exceptions are collected and thrown back with this code.
     * @since 6.1.7
     */
    const LIB_NETCURL_CURL_MULTI_EXCEPTION_DISCOVERY = 1012;

    /**
     * @var int Invalid path or file location.
     * @since 6.1.8
     */
    const LIB_INVALID_PATH = 1013;

    /**
     * @var int Cipher does not exist in current openssl driver.
     */
    const LIB_SSL_CIPHER_UNAVAILABLE = 3000;

    /**
     * @var int No key or IV set for encryption.
     */
    const LIB_SSL_CIPHER_NO_KEYS = 3001;

    /**
     * @var int Library class should not be used any more as it is considred deprecated/obsolete.
     */
    const LIB_DEPRECATED_CLASS = 65000;

    /**
     * @var int Library class is not available, as it is missing or not installed.
     */
    const LIB_CLASS_UNAVAILABLE = 65001;

    /**
     * @var int Method/function unavailable. Normally thrown when library or driver is missing. Seen in CryptoLib.
     */
    const LIB_METHOD_OR_LIBRARY_UNAVAILABLE = 65002;

    /**
     * @var int Method/function is disabled.
     */
    const LIB_METHOD_OR_LIBRARY_DISABLED = 65003;

    /**
     * @var int Library class is not available, as it has been disabled (preferrably from php.ini).
     */
    const LIB_CLASS_DISABLED = 65004;

    /**
     * @var int IO Library could not extract data properly.
     */
    const LIB_IO_EXTRACT_XPATH_ERROR = 65005;

    /**
     * @var int Thrown from the Flag class.
     */
    const LIB_FLAG_EXCEPTION = 65006;

    /**
     * @var int PHP version is too old.
     * @since 6.1.5
     */
    const LIB_TOO_OLD_PHP = 65007;

    /**
     * @var int SSL capabilities unavailable.
     * @since 6.1.9
     */
    const LIB_SSL_UNAVAILABLE = 65008;

    /**
     * @var int Generic unavailable driver.
     * @since 6.1.9
     */
    const LIB_GENERIC_DRIVER_UNAVAILABLE = 65009;
}

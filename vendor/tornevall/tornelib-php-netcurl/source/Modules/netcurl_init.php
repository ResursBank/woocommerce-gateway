<?php

namespace TorneLIB;

// Library Release Information
if (!defined('NETCURL_RELEASE')) {
    define('NETCURL_RELEASE', '6.0.28');
}
if (!defined('NETCURL_MODIFY')) {
    define('NETCURL_MODIFY', '20200422');
}
if (!defined('TORNELIB_NETCURL_RELEASE')) {
    // Compatibility constant
    define('TORNELIB_NETCURL_RELEASE', NETCURL_RELEASE);
}
if (!defined('NETCURL_SKIP_AUTOLOAD')) {
    define('NETCURL_CLASS_EXISTS_AUTOLOAD', true);
} else {
    define('NETCURL_CLASS_EXISTS_AUTOLOAD', false);
    // If the autoloader prevention is set for this module, we probably want to do the same for our
    // relative CRYPTO/IO
    if (!defined('CRYPTO_SKIP_AUTOLOAD')) {
        define('CRYPTO_SKIP_AUTOLOAD', true);
    }
    if (!defined('IO_SKIP_AUTOLOAD')) {
        define('IO_SKIP_AUTOLOAD', true);
    }
}

if (defined('NETCURL_REQUIRE')) {
    if (!defined('NETCURL_REQUIRE_OPERATOR')) {
        define('NETCURL_REQUIRE_OPERATOR', '==');
    }
    define(
        'NETCURL_ALLOW_AUTOLOAD',
        version_compare(
            NETCURL_RELEASE,
            NETCURL_REQUIRE,
            NETCURL_REQUIRE_OPERATOR
        ) ? true : false
    );
} else {
    if (!defined('NETCURL_ALLOW_AUTOLOAD')) {
        define('NETCURL_ALLOW_AUTOLOAD', true);
    }
}

if (file_exists(__DIR__ . '/../../vendor/autoload.php') &&
    (defined('NETCURL_ALLOW_AUTOLOAD') &&
        NETCURL_ALLOW_AUTOLOAD === true)
) {
    require_once(__DIR__ . '/../../vendor/autoload.php');
}

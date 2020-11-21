<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit18c99fa4a0300e5dbf36f867cf0fe894
{
    public static $files = array (
        'bc521b269795605ef2585a7369f0017e' => __DIR__ . '/..' . '/tornevall/tornelib-php-network/src/Network.php',
        '90d189b1df317b83da222c214c8e0dc4' => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/ecomhooks.php',
        'e6cb3e061b463a34be4630aa7d1ecca2' => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'TorneLIB\\' => 9,
        ),
        'R' => 
        array (
            'Resursbank\\RBEcomPHP\\' => 21,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TorneLIB\\' => 
        array (
            0 => __DIR__ . '/..' . '/tornevall/tornelib-php-bitmask/src',
            1 => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/src',
            2 => __DIR__ . '/..' . '/tornevall/tornelib-php-errorhandler/src',
            3 => __DIR__ . '/..' . '/tornevall/tornelib-php-flags/src',
            4 => __DIR__ . '/..' . '/tornevall/tornelib-php-io/src',
            5 => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/src',
            6 => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl-deprecate-60/src',
            7 => __DIR__ . '/..' . '/tornevall/tornelib-php-network/src',
            8 => __DIR__ . '/..' . '/tornevall/tornelib-php-utils/src',
            9 => __DIR__ . '/..' . '/tornevall/tornelib-php-version/src',
        ),
        'Resursbank\\RBEcomPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit18c99fa4a0300e5dbf36f867cf0fe894::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit18c99fa4a0300e5dbf36f867cf0fe894::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}

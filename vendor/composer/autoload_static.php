<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1136d0f4cd6de89f36c68a6a852dd39d
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
        ),
        'Resursbank\\RBEcomPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1136d0f4cd6de89f36c68a6a852dd39d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1136d0f4cd6de89f36c68a6a852dd39d::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}

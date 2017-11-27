<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit37e03b8d42b542aab7be33e1e3fcc2ab
{
    public static $files = array (
        'a2ffb7dc7e05ad2dc2ed262a34ab8f48' => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/source/tornevall_crypto.php',
        'b5ed896fad722458ed922152c008d871' => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/source/tornevall_io.php',
        '4ffe1473ea96509277529f6bb64eca0e' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/tornevall_network.php',
        'fdb019b34e65128f0d694491918a3983' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/tornevall_netcurl_exceptions.php',
        'e6cb3e061b463a34be4630aa7d1ecca2' => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
    );

    public static $prefixLengthsPsr4 = array (
        '\\' => 
        array (
            '\\TorneLIB\\' => 10,
            '\\Resursbank\\RBEcomPHP\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        '\\TorneLIB\\' => 
        array (
            0 => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/source/tornevall_io.php',
            1 => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/tornevall_network.php',
        ),
        '\\Resursbank\\RBEcomPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit37e03b8d42b542aab7be33e1e3fcc2ab::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit37e03b8d42b542aab7be33e1e3fcc2ab::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitdc9984229e436ea8d2b2d3144f87170e
{
    public static $files = array (
        'a2ffb7dc7e05ad2dc2ed262a34ab8f48' => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/source/tornevall_crypto.php',
        'b5ed896fad722458ed922152c008d871' => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/source/tornevall_io.php',
        'ad018ca6880fd0f3071fee4e20010952' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/netcurl_init.php',
        'eae8af6c08d5e813aca8933280e2c98f' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Interface/drivers.php',
        'ac9f6d2090958022f72d020a08550438' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/Drivers/guzzle.php',
        '066a58ca44970046efe383a6391258aa' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/Drivers/wordpress.php',
        '1487f9727827260fb0df319ac39f393d' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/parser.php',
        '806020ff7f04231f064091eb26a90168' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/drivers.php',
        'df878c9cd49b76db71defd667414646a' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/ssl.php',
        'bf537d28f5e1ba3b09e964153311c868' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Addons/netbits.php',
        '50a8ab9d35edefc11097247e100de04b' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Exception/Exception.php',
        '4fde2b865094bb92dbc31b68d5007f47' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/authtypes.php',
        'f10557b53c2de67bb302efc444b174ad' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/curlobject.php',
        '7d51a11a7daee398635225d60ff43f8b' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/datatypes.php',
        '7b90c7b2d53f361f9bab7fb14590b5e5' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/drivers.php',
        'a96b03857f16b18b34910e5df00f863e' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/environment.php',
        'bc1c565e1649c5306e547b86134f435a' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/ip_protocols.php',
        '6b8e025b2c346231dac727359f4ae563' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/postmethods.php',
        '826a8c409b244ab44ed0d29f361de31e' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/resolvers.php',
        '39fa5b0cfd59d90b59fc509582c5491d' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Model/Type/responses.php',
        '4a7a2444f2d491c7d8161d1dc7d59ca1' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/network.php',
        '2ec79023d995178b0fb9ece85f227b80' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/curl.php',
        'fdd125965a2f74a934f756f47ef5f84f' => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source/Modules/soap.php',
        '90d189b1df317b83da222c214c8e0dc4' => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/ecomhooks.php',
        'e6cb3e061b463a34be4630aa7d1ecca2' => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
    );

    public static $prefixLengthsPsr4 = array (
        '\\' => 
        array (
            '\\TorneLIB\\' => 10,
            '\\Resursbank\\RBEcomPHP\\' => 22,
        ),
        'T' => 
        array (
            'TorneLIB\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        '\\TorneLIB\\' => 
        array (
            0 => __DIR__ . '/..' . '/tornevall/tornelib-php-crypto/source',
        ),
        '\\Resursbank\\RBEcomPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/resursbank/ecomphp/source/classes/rbapiloader.php',
        ),
        'TorneLIB\\' => 
        array (
            0 => __DIR__ . '/..' . '/tornevall/tornelib-php-netcurl/source',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitdc9984229e436ea8d2b2d3144f87170e::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitdc9984229e436ea8d2b2d3144f87170e::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}

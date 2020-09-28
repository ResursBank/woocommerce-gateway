# EComPHP - Resurs Bank ECommerce Library for PHP

Resurs EComPHP Gateway for Resurs Bank shopflows, with functionality enough to getting started fast. It communicates with the Simplified Flow API for booking payments, Configuration Service and the After Shop Service API for finalizing, crediting and annulments etc. This full version of the gateway communicates with Hosted Payment Flow and Resurs Checkout (supporting both REST and SOAP). A PHP-reference for EComPHP is located at https://test.resurs.com/autodocs/apigen/ecomphp-1.3-develop/, if you want to take a look at our automatically generated documentation.

As EComPHP is continuously developed, you should take a look at our bitbucket repo to keep this information updated. It can be found at https://bitbucket.org/resursbankplugins/resurs-ecomphp

## Regular requirements, dependencies and information

* For EComPHP 1.3 at least PHP 5.6 (Use composer!)
* [OpenSSL](https://www.openssl.org) - or similar. SSL drivers are *required* for communication with Resurs Bank.
* [curl](https://curl.haxx.se): php-curl with SSL support (Make sure the above SSL requirement is fulfilled!).
* php-xml and streams (For the SOAP parts).
* EComPHP uses [NetCURL](https://www.netcurl.org) for "mixed calls" (SOAP vs REST). The packagist component is located [here](https://www.netcurl.org/packagist).
* If you plan to *ONLY* use Resurs Checkout (checkout only, with no aftershop, callbacks or needs of listing payment methods etc) - there should be no need for SoapClient.

### Obsoletions

* EComPHP 1.2 was completely revoked when 1.3 was released.
* EComPHP 1.1-ns and 1.0-nons has nearly dropped maintenance as of july 2020.

## Installing

For Ubuntu, you can quickly fetch those with apt-get like below, if your system is missing them:

    apt-get install php-curl php-xml php-soap
     
There might be a slight chance that you also need openssl or similar, as our services runs on https-only (normally openssl are delivered automatically, but sometimes they do not - apt-get install openssl might work in those cases if you have access to the server).

### Using composer

    composer require resursbank/ecomphp:^1.3

If you are planning to deploy a plugin bundled with this package, you can run composer with the parameter --prefer-dist
You should also make sure that the repositories that is also downloaded together with this package is cleaned up properly: The .git directories must be removed, or a composer install is required before using it. Such deployment could look like this:

    #!/bin/bash
    composer clearcache
    rm -rf vendor composer.lock
    composer install --prefer-dist
    find vendor/ -type d -name .git -exec rm -rf {} \; >/dev/null 2>&1
    find vendor/ -name .gitignore -exec rm {} \; >/dev/null 2>&1

### Getting started

This is a short example of how to get started, but you can [take a look at our documentation for details and getting started for real](https://test.resurs.com/docs/x/TYNM).

    <?php
        require_once("vendor/autoload.php");
        $resurs = new \Resursbank\RBEcomPHP\ResursBank($myMerchantUserName, $myMerchantPassword);
        $methods = $resurs->getPaymentMethods();
        print_r($methods);
    ?>

# PHP versions verified

Take a look at [this page](https://www.php.net/supported-versions.php) if you're unsure which PHP versions that are still supported by the PHP team.
As of february 2020, only 7.3 and 7.4 have full support open. 7.2 still do have security patch support, but is on deprecation. All older versions are completely unsupported and should probably get upgrade by you also.

### Verified PHP versions

    5.6 - 7.4 (Bamboo & Pipelines)
    8.0a3     (Bamboo only, as of 1.3.42)
    < 5.6     (No longer tested)

Testing PHP 8.0 is possible when using phpunit 9 and a compiled version of PHP 8, like below:

    composer --dev require phpunit/phpunit ^9 --ignore-platform-reqs
    /usr/local/php8alpha/bin/php vendor/bin/phpunit

## What this library do and do not

* If you are used to work with the simplified flow and wish to use Hosted/Checkout, you can stick to the use of the older SimplifiedFlow variables, as this library converts what's missing between the different flows.
* The EComPHP-library takes care of many things that developers usually miss in their development. Especially the payload handling.
* Both SOAP and REST is supported, under the condition that there are drivers available for it.

# EComPHP 1.3 - PHP Library for Resurs Bank ECommerce #

Resurs EComPHP Gateway for Resurs Bank shopflows, with functionality enough to getting started fast. It communicates with the Simplified Flow API for booking payments, Configuration Service and the After Shop Service API for finalizing, crediting and annulments etc. This full version of the gateway communicates with Hosted Payment Flow and Resurs Checkout (supporting both REST and SOAP). A PHP-reference for EComPHP is located at https://test.resurs.com/autodocs/apigen/ecomphp-1.3-develop/, if you want to take a look at our automatically generated documentation.

As EComPHP is continuously developed, you should take a look at our bitbucket repo to keep this information updated. It can be found at https://bitbucket.org/resursbankplugins/resurs-ecomphp


## Regular requirements and dependencies

* For EComPHP 1.0 (With no namespaces) at least PHP 5.2
* For EComPHP 1.1 (With namespaces) at least PHP 5.3
* For EComPHP 1.3 at least PHP 5.3 (Requires composer for dependencies)
* For EComPHP 2.0 at least PHP 5.3 (Requires composer for dependencies)
* [OpenSSL](https://www.openssl.org): For reaching Resurs Bank REST/SOAP
* [curl](https://curl.haxx.se): php-curl with SSL support
* php-xml and streams (For the SOAP parts)
* EComPHP uses [NetCURL](https://www.netcurl.org) for calls via both SOAP and REST. The packagist component is located [here](https://www.netcurl.org/packagist)
* If you plan to ONLY use Resurs Checkout based functions there should be no need for SoapClient. However, there are functions in EComPHP that utilizes the SOAP-based flow before the REST-services (Callback UPDATE, getPaymentMethods, etc)

### Installing curl, xml, soapclient, etc

For Ubuntu, you can quickly fetch those with apt-get like below, if your system is missing them:

    apt-get install php-curl php-xml php-soap
     
There might be a slight chance that you also need openssl or similar, as our services runs on https-only (normally openssl are delivered automatically, but sometimes they do not - apt-get install openssl might work in those cases if you have access to the server).


# PHP versions verified

### December 9, 2019

    Pipelines/Bamboo Verified : 5.6 - 7.4
                       Legacy : 5.4 - 5.5
                   Unverified : 5.3 and lower.


## What this library do and do not

* If you are used to work with the simplified flow and wish to use Hosted/Checkout, you can stick to the use of the older SimplifiedFlow variables, as this library converts what's missing between the different flows.
* The EComPHP-library honors a kind of developer sloppiness - if something is forgotten in the payload that used to be required standard fields, the library will fills it in (as good as possible) for you, as you send your payload to it
* Both SOAP and REST is supported

[Take a look on our documentation for details and getting started](https://test.resurs.com/docs/x/TYNM)



# Using composer

Did you decide to go with the experimental composer edition of EComPHP?
If so, **you should look at v1.3** which is the first version that is fully composer-supported. *The older versions are not prepared and deployed as such packages.*

First of all, the release is based on the branch develop/1.2 and is currently in alpha development state so we cannot guarantee anything to function. However, if you're aware of that you're on your own for the moment, you can go for this:

    composer require resursbank/ecomphp:1.3.*

If you are planning to deploy a plugin bundled with this package, you can run composer with the parameter --prefer-dist
You should also make sure that the repositories that is also downloaded together with this package is cleaned up properly: The .git directories must be removed, or a composer install is required before using it.

Then you may go with something like this in your first born code:

    <?php
        require_once("vendor/autoload.php");
        $resurs = new \Resursbank\RBEcomPHP\ResursBank($myMerchantUserName, $myMerchantPassword);
        $methods = $resurs->getPaymentMethods();
        print_r($methods);
    ?>


## Deploying (composer)

Deployment with composer usually only requires an installation. However, if you need to bundle the composer package with all dependencies in a installation package that is not built for using composer you need to set up your package, so that the included extra repos is considered "a part of the package". Such deployment script may look like this:

    #!/bin/bash
    composer clearcache
    rm -rf vendor composer.lock
    composer install --prefer-dist
    find vendor/ -type d -name .git -exec rm -rf {} \; >/dev/null 2>&1
    find vendor/ -name .gitignore -exec rm {} \; >/dev/null 2>&1

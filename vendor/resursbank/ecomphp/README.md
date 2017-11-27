# EComPHP - PHP Gateway for Resurs Bank ECommerce Services #

Resurs EComPHP Gateway is a simplifier for our webservices, with functionality enough to getting started fast. It communicates with the Simplified Flow API for booking payments, Configuration Service and the After Shop Service API for finalizing, crediting and annulments etc. This full version of the gateway communicates with Hosted Payment Flow and Resurs Checkout (supporting both REST and SOAP). A PHP-reference for EComPHP is located at https://test.resurs.com/autodocs/ecomphp-apigen/, if you want to take a look at our automatically generated documentation.

As EComPHP is continuously developed, you should take a look at our bitbucket repo to keep this information updated. It can be found at https://bitbucket.org/resursbankplugins/resurs-ecomphp


## Composerized version

Did you decide to go with the experimental composer edition of EComPHP?

First of all, the release is based on the branch develop/1.2 and is currently in alpha development state so we cannot guarantee anything to function. However, if you're aware of that you're on your own for the moment, you can go for this:

    composer require resursbank/ecomphp:dev-develop/composerize

Then you may go with something like this in your first born code:

    <?php
        require_once("vendor/autoload.php");
        $resurs = new \Resursbank\RBEcomPHP\ResursBank($myMerchantUserName, $myMerchantPassword);
        $methods = $resurs->getPaymentMethods();
        print_r($methods);
    ?>



## Regular requirements and dependencies

* For EComPHP 1.0 (With no namespaces) at least PHP 5.2
* For EComPHP 1.1 (With namespaces) at least PHP 5.3
* For EComPHP 1.2 (Namespace release only) at least PHP 5.3
* OpenSSL: For reaching Resurs webservices that is restricted to https only
* curl: php-curl and php-xml (For the SOAP parts)
* EComPHP uses [this curl library](https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php/browse/classes/tornevall_network.php) (currently bundled) for calls via both SOAP and REST. It is available via composer and packagist [here](https://packagist.org/packages/tornevall/tornelib-php-netcurl)
* If you plan to ONLY use Resurs Checkout based functions there should be no need for SoapClient. However, registration of the callback UPDATE, currently **requires** SoapClient as it do not exist in the rest-calls

As this module uses [curl](https://curl.haxx.se) and [SoapClient](http://php.net/manual/en/class.soapclient.php) to work, there are dependencies in curl and xml libraries (as shown above). For Ubuntu, you can quickly fetch those with apt-get (apt-get install php-curl php-xml) if they do not already exists in your system. There might be a slight chance that you also need openssl or similar, as our services runs on https-only (normally openssl are delivered automatically, but sometimes they do not - apt-get install openssl might work in those cases if you have access to the server).

## PHP 7.2

Does the library work with PHP 7.2?

- Tests are verified to [run with 7.2 RC5](https://resursbankplugins.atlassian.net/browse/ECOMPHP-180) so far


## What this library do and do not

* If you are used to work with the simplified flow and wish to use Hosted/Checkout, you can stick to the use of the older SimplifiedFlow variables, as this library converts what's missing between the different flows.
* The EComPHP-library honors a kind of developer sloppiness - if something is forgotten in the payload that used to be required standard fields, the library will fills it in (as good as possible) for you, as you send your payload to it
* Both SOAP and REST is supported


[Take a look on our documentation for details and getting started](https://test.resurs.com/docs/x/TYNM)
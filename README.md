# Resurs Bank payment gateway for WooCommerce

 * Compatible with WooCommerce 2.6 up to 3.3.x
 * Plugin verified with PHP version 5.4 - 7.1 
 * Addon scripts verified with PHP 5.3 - 7.2 (EComPHP 1.3.x and NetCURL 6.0.20)


## Description

This is a payment gateway for Resurs Bank, that supports three Resurs Bank shopflows. Depending on which flow that's being selected, there are also requirements on different communication drivers, but in short - to get full control over this plugin - it is good to have them all available.

Requirements and content:

 * At least PHP 5.4
 * [curl](https://curl.haxx.se): For communication via rest (Hosted flow and Resurs Checkout)
 * [PHP streams](http://php.net/manual/en/book.stream.php)
 * [SoapClient](http://php.net/manual/en/class.soapclient.php)
 * [EComPHP](https://test.resurs.com/docs/x/TYNM) (Bundled) [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-ecomphp.git)
 * [NetCURL](https://docs.tornevall.net/x/CYBiAQ) (Bundled) [Bitbucket](https://www.netcurl.org)

SoapClient uses PHP streams to communicate and covers everything but the functions located in the hosted flow/checkout (meaning simplified flow, aftershop flow (finalization, annullments, crediting), payment methods listings and much more)

[Project URL](https://test.resurs.com/docs/display/ecom/WooCommerce) - [Plugin URL](https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/)


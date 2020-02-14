# Resurs Bank payment gateway for WooCommerce

The plugin was once written for WooCommerce v2.6 and up but as of today, we've started to change the requirements. It is no longer guaranteed that this plugin is compatible with such old versions. Ever since WooCommerce [discoverd a file deletion vulnerable (click here)](https://blog.ripstech.com/2018/wordpress-design-flaw-leads-to-woocommerce-rce/) our goal is to patch away deprecated functions.

 * Compatibility: WooCommerce 2.6 up to 3.9
 * Plugin verified with PHP version 5.6 - 7.4
 * Plugin verified with WordPress 5.0 alpha

### Can I upgrade WooCommerce with your plugin installed?

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade warnings.

## Getting started / Installing

For "normal production users" we recommend you to just install this plugin by using WordPress plugin installer. If that is not possible and your site requires manual installations, you can either go to [the official wordpress site](https://sv.wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/) and download the plugin or [our bitbucket-repo](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/downloads/?tab=tags) and look for a matching tag to download.

When downloaded manually, unzip/untar the archive into your plugin path (just remember that using our repo-tags may unpack the files in a substructure, so make sure all files goes right into its own path). It is however *recommended* that you use WordPress download site instead of our repo. 

## Description

This is a payment gateway for Resurs Ba2k, that supports three Resurs Bank shopflows. Depending on which flow that's being selected, there are also requirements on different communication drivers, but in short - to get full control over this plugin - it is good to have them all available.

# Prerequisites

The below requisites usually is active in a webserver/phpcore.

 * At least PHP 5.4
 * [curl](https://curl.haxx.se): For communication via rest (Hosted flow and Resurs Checkout)
 * [PHP streams](http://php.net/manual/en/book.stream.php)
 * [SoapClient](http://php.net/manual/en/class.soapclient.php)
 
## Bundled prerequisites

 * [EComPHP](https://test.resurs.com/docs/x/TYNM) (Bundled) [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-ecomphp.git)
 * [NetCURL](http://www.netcurl.org/docs) (Bundled) [Bitbucket](https://www.netcurl.org)

SoapClient uses PHP streams to communicate and covers everything but the functions located in the hosted flow/checkout (meaning simplified flow, aftershop flow (finalization, annullments, crediting), payment methods listings and much more)

# Documentation

[Project URL](https://test.resurs.com/docs/display/ecom/WooCommerce) - [Plugin URL](https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/)

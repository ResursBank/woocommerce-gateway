# Resurs Bank payment gateway for WooCommerce (Legacy/Maintenance) #

We are only supporting WooCommerce 3.5.0 and up.

* Compatibility: WooCommerce - at least 3.4.0 and up to 5.8.0
* Plugin verified with PHP version 7.0 - 8.0

The plugin was, historically, written for WooCommerce v2.6 and up but since WordPress and WooCommerce dropped old
PHP-versions, you should probably not expect anything to work if you still run 2.6. It was also written in an era where
coding standards was considered as "less important" amongst developers. That's why the codebase is anything but
beautiful.

## Multisite/WordPress Networks ##

The plugin **do** support WordPress networks (aka multisite), however it does not support running one webservice account over many sites at once. The main rule that Resurs Bank works with is that one webservice account only works for one site. Running multiple sites do **require** multiple webservice accounts!

## Plugin needs translation ##

[![Crowdin](https://badges.crowdin.net/resursbankwoocommerce/localized.svg)](https://crowdin.com/project/resursbankwoocommerce)

Help us translate the plugin by joining [Crowdin](https://crwd.in/resursbankwoocommerce)!

### Can I upgrade WooCommerce with your plugin installed? ###

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually changes (
after internal tests has been made) to match the base requirements, so you can upgrade without upgrade warnings.

## Getting started / Installing ##

For "normal production users" we recommend you to just install this plugin by using WordPress plugin installer. If that
is not possible and your site requires manual installations, you can either go
to [the official wordpress site](https://sv.wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/) and
download the plugin
or [our bitbucket-repo](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/downloads/?tab=tags)
and look for a matching tag to download.

When downloaded manually, unzip/untar the archive into your plugin path (just remember that using our repo-tags may
unpack the files in a substructure, so make sure all files goes right into its own path). It is however *recommended*
that you use WordPress download site instead of our repo.

## Description ##

This is a payment gateway for Resurs Ba2k, that supports three Resurs Bank shopflows. Depending on which flow that's
being selected, there are also requirements on different communication drivers, but in short - to get full control over
this plugin - it is good to have them all available.

# Prerequisites ##

* WooCommerce: v3.5.0 or higher (old features are ditched) and the actual support is set much higher.
* WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly
  recommended to go for the latest version as soon as possible if you're not already there.
  See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more
  information.
* HTTPS *must* be enabled in both directions. This is a callback security measure.
* XML and SoapClient must be available.
* Curl is *recommended* but not necessary.
* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug
  2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
  Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions
  of PHP.

SoapClient uses PHP streams to communicate and covers everything but the functions located in the hosted flow/checkout (
meaning simplified flow, aftershop flow (finalization, annullments, crediting), payment method listings and much more)

# Documentation #

* [Project URL](https://test.resurs.com/docs/display/ecom/WooCommerce)
* [Plugin URL](https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/)

## Frequently Asked Questions ##

You may want to look further at https://test.resurs.com/docs/display/ecom/WooCommerce for updates regarding this plugin

### Plugin is causing 40X errors on my site ###

There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message there are few things to take in consideration:

* 401 = Unauthorized.
  **Cause**: Bad credentials
  **Solution**: Contact Resurs Bank support for support questions regarding API credentials.
* 403 = Forbidden.
  **Cause**: This may be more common during test.
  **Solution:** Resolution: Contact Resurs Bank for support.

### There's an order created but there is no order information connected to Resurs Bank ###

This is a common question about customer actions and how the order has been created/signed. Most of the details is usually placed in the order notes for the order, but if you need more information you could also consider contacting Resurs Bank support.

=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 2.2.81
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC Tested up to: 5.8.0

Resurs Bank Payment Gateway for WooCommerce -- Maintenance release.

== Description ==

= About =

Official payment gateway for Resurs Bank (maintenance version).
Full support for all active shop flows. **SoapClient** is required as many of the administrative actions are using SOAP.
Requirements follows WooCommerce requirements, which means (as of june 2021) PHP 7.0 or higher.

[![Crowdin](https://badges.crowdin.net/resursbankwoocommerce/localized.svg)](https://crowdin.com/project/resursbankwoocommerce)

Help us translate the plugin by joining [Crowdin](https://crwd.in/resursbankwoocommerce)!

= Compatibility and requirements =

* WooCommerce: v3.5.0 or higher!
* [curl](https://curl.haxx.se) or [PHP stream](https://php.net/manual/en/book.stream.php) features active (for REST based actions).
  To not loose important features, we recommend you to have this extension enabled - if you currently run explicitly with streams.
* Included: [NetCURL](https://netcurl.org/docs/) [Bitbucket](https://www.netcurl.org). NetCURL handles all of the communication
  drivers just mentioned.
* **Required**: [SoapClient](https://php.net/manual/en/class.soapclient.php) with xml drivers.
* **Included** [EComPHP](https://test.resurs.com/docs/x/TYNM) (Bundled vendor) [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-ecomphp.git)
* WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly
  recommended to go for the latest version as soon as possible if you're not already there.
* HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug
  2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
  Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions
  of PHP. Syntax for this release is written for releases lower than 7.0

= Upgrade notice =

When developing the plugin for Woocommerce, we usually follow the versions for WooCommerce and always upgrading when
there are new versions out. That said, it is **normally** also safe to upgrade to the latest woocommerce.


== Can I upgrade WooCommerce with your plugin installed? ==

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually
changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade
warnings.

[Project URL](https://test.resurs.com/docs/display/ecom/WooCommerce) - [Plugin URL](https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/)


= Contribute =

Help us translate the plugin by joining [Crowdin](https://crwd.in/resurs-bank-woocommerce-gateway)!

Do you think there are ways to make our plugin even better? Join our project for woocommerce at [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce)

Want to add a new language to this plugin? You can contribute via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/resurs-bank-payment-gateway-for-woocommerce).


== Installation ==

1. Upload the plugin archive to the "/wp-content/plugins/" directory
2. Activate the plugin through the "Plugins" menu in WordPress
3. Configure the plugin via admin control panel

(Or install and activate the plugin through the WordPress plugin installer)
If you are installing the plugin manually, make sure that the plugin folder contains a folder named includes and that includes directory are writable, since that's where the payment methods are stored.

= Upgrading =

When upgrading the plugin via WordPress plugin manager, make sure that you payment methods are still there. If you are unsure, just visit the configuration panel for Resurs Bank once after upgrading since, the plugin are rewriting files if they are missing.

As of v2.2.12, we do support SWISH and similar "instant debitable" payment methods, where payments tend to be finalized/debited long before shipping has been made. You can read more about it [here](https://test.resurs.com/docs/display/ecom/CHANGELOG+-+WooCommerce#CHANGELOG-WooCommerce-2.2.12)


== Frequently Asked Questions ==

You may want to look further at [https://test.resurs.com/docs/display/ecom/WooCommerce](https://test.resurs.com/docs/display/ecom/WooCommerce) for updates regarding this plugin

= Plugin is causing 40X errors on my site =

There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message there are few things to take in consideration:

* 401 = Unauthorized.
**Cause**: Bad credentials
**Solution**: Contact Resurs Bank support for support questions regarding API credentials.

* 403 = Forbidden.
**Cause**: This may be more common during test.
**Solution:** Resolution: Contact Resurs Bank for support.

= There's an order created but there is no order information connected to Resurs Bank =

This is a common question about customer actions and how the order has been created/signed. Most of the details is usually placed in the order notes for the order, but if you need more information you could also consider contacting Resurs Bank support.

= Handling decimals =

Setting decimals to 0 in WooCommerce will result in an incorrect rounding of product prices. It is therefore adviced to set decimal points to 2.


== Screenshots ==

Docs are continuously updated at [https://test.resurs.com/docs/display/ecom/WooCommerce](https://test.resurs.com/docs/display/ecom/WooCommerce)


== Changelog ==

= In this release (2.2.80): =

* [WOO-599](https://resursbankplugins.atlassian.net/browse/WOO-599) Ip control section for woo

= 2.2.73-2.2.79 =

* Readme files updated several times.
* [WOO-597](https://resursbankplugins.atlassian.net/browse/WOO-597) If getAddress-form is entirely disabled and methods for both natural and legal is present

= 2.2.72 =

* [WOO-596](https://resursbankplugins.atlassian.net/browse/WOO-596) When trying to discover composer.json version/name data, platform renders warnings
* [WOO-595](https://resursbankplugins.atlassian.net/browse/WOO-595) Slow/crashing platform on API timeouts
* [WOO-594](https://resursbankplugins.atlassian.net/browse/WOO-594) Product pages stuck on Resurs-API timeouts
* [WOO-593](https://resursbankplugins.atlassian.net/browse/WOO-593) Partially handle server timeouts

For a full list of changes, [look here](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/CHANGELOG.md) - CHANGELOG.md is also included in this package.

== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.

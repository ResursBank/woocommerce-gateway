=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 5.0
Tested up to: 5.8.1
Requires PHP: 5.4
Stable tag: 2.2.68
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC Tested up to: 5.8.0

Resurs Bank Payment Gateway for WooCommerce.
WooCommerce Tested up to: 5.8.0
Maintenance release.

== Description ==

= About =

Official payment gateway for Resurs Bank (maintenance version).
Full support for all active shop flows. *SoapClient* is required as many of the administrative actions are using SOAP.
Requirements follows WooCommerce requirements, which means (as of june 2021) PHP 7.0 or higher.

[![Crowdin](https://badges.crowdin.net/resursbankwoocommerce/localized.svg)](https://crowdin.com/project/resursbankwoocommerce)

Help us translate the plugin by joining [Crowdin](https://crwd.in/resursbankwoocommerce)!

= Compatibility and requirements =

* WooCommerce: v3.4.0 or higher (old features are ditched) and the actual support is set much higher.
* WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly
  recommended to go for the latest version as soon as possible if you're not already there.
* HTTPS *must* be enabled in both directions. This is a callback security measure.
* XML and SoapClient must be available.
* Curl is *recommended* but not necessary.
* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug
  2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
  Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions
  of PHP.


= Upgrade notice =

When developing the plugin for Woocommerce, we usually follow the versions for WooCommerce and always upgrading when
there are new versions out. That said, it is *normally* also safe to upgrade to the latest woocommerce.


== Can I upgrade WooCommerce with your plugin installed? ==

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually
changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade
warnings.


= Requirements and content =

 * Required: At least PHP 7.0
 * Required: [curl](https://curl.haxx.se) or [PHP stream](http://php.net/manual/en/book.stream.php) features active (for REST based actions).
 * Required: [SoapClient](http://php.net/manual/en/class.soapclient.php) with xml drivers.
 * Included: [EComPHP](https://test.resurs.com/docs/x/TYNM) (Bundled) [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-ecomphp.git)
 * Included: [NetCURL](https://netcurl.org/docs/) [Bitbucket](https://www.netcurl.org)

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

You may want to look at https://test.resurs.com/docs/display/ecom/WooCommerce for updates regarding this plugin


== Screenshots ==

Docs are continuously updated at https://test.resurs.com/docs/display/ecom/WooCommerce


== Changelog ==

In this release:

# 2.2.65-66

* [WOO-584](https://resursbankplugins.atlassian.net/browse/WOO-584) Saving credentials is problematic the first round after wp-admin-reload.
* [WOO-585](https://resursbankplugins.atlassian.net/browse/WOO-585) PAYMENT_PROVIDER with government id

# 2.2.64

* [WOO-576](https://resursbankplugins.atlassian.net/browse/WOO-576) $return is sometimes not set when return occurs
* Tagged version for WooCommerce.

# 2.2.63

Info update only.

# 2.2.62

* [WOO-575](https://resursbankplugins.atlassian.net/browse/WOO-575) Order status gets an incorrect jump on bookSignedPayment and status=FINALIZED
* [WOO-574](https://resursbankplugins.atlassian.net/browse/WOO-574) Remove FINALIZATION and move "instant finalizations" into UPDATE.
* [WOO-573](https://resursbankplugins.atlassian.net/browse/WOO-573) Remove unnecessary callbacks
* [WOO-572](https://resursbankplugins.atlassian.net/browse/WOO-572) Activate logging of order stock handling

In last release:

* [WOO-570](https://resursbankplugins.atlassian.net/browse/WOO-570) Resurs Annuity Factors Widget Error -- Unable to fetch .variations_form: json.find is not a function

For a full list of changes, [look here](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/CHANGELOG.md) - CHANGELOG.md is also included in this package.

== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.

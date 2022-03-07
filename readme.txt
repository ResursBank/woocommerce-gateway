=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 2.2.89
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC Tested up to: 5.8.0

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

= About =

Official payment gateway for Resurs Bank.

Help us translate the plugin by joining [Crowdin](https://crwd.in/resursbankwoocommerce)!

= Supported shop flows =

* [Simplified Shop Flow](https://test.resurs.com/docs/display/ecom/Simplified+Flow+API). Integrated checkout that works with WooCommerce built in features.
* [Resurs Checkout Web](https://test.resurs.com/docs/display/ecom/Resurs+Checkout+Web). Iframe integration. Currently supporting **RCOv1 and RCOv2**.
* [Hosted Payment Flow](https://test.resurs.com/docs/display/ecom/Hosted+Payment+Flow). A paypal like checkout where most of the payment events takes place at Resurs Bank.

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
2. Make sure the plugin has write access to itself under the includes folder (see below)
3. Activate the plugin through the "Plugins" menu in WordPress
4. Configure the plugin via admin control panel

(Or install and activate the plugin through the WordPress plugin installer)

= Write access to the includes folder =

The most commonly used path to the plugin folder's include-path is set to:
/wp-content/plugin/resurs-bank-payment-gateway-for-woocommerce/includes
This path has to be write-accessible for your web server or the plugin won't work properly since the payment methods are written to disk as classes. If you have login access to your server by SSH you could simply run this kind of command:

    chmod a+rw <wp-root>/wp-content/plugin/resurs-bank-payment-gateway-for-woocommerce/includes

If you have a FTP-client or similar, make sure to give this path write-access for at least your webserver. If you know what you are doing you should limit this to your web server.

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

= 2.2.88 - 2.2.89 =

* Hotfix for errorhandler problems (PHP 8.0+)

= 2.2.87 =

* Hotfix for ecom requirements.

= 2.2.86 =

* [WOO-602](https://resursbankplugins.atlassian.net/browse/WOO-602) Centralize requirements
* [WOO-601](https://resursbankplugins.atlassian.net/browse/WOO-601) strftime is deprecated in PHP 8.1.0

= 2.2.84 - 2.2.85 =

* Cleanup, corrections.

= 2.2.83 =

* Libraries and readme patch.
* Simplified the way we fetch version information in plugin which is used by the ecommerce php library, making tags easier to handle.

= 2.2.82 =

* [WOO-600](https://resursbankplugins.atlassian.net/browse/WOO-600) Hotfix: QueryMonitor reports missing dependency

= 2.2.81 =

* Hotfix: Added ip-tracking feature for whitelisting-help in wp-admin.

= 2.2.80 =

* [WOO-599](https://resursbankplugins.atlassian.net/browse/WOO-599) Ip control section for woo


For a full list of changes, [look here](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/CHANGELOG.md) - CHANGELOG.md is also included in this package.

== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.

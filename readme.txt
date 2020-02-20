=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 5.3.2
Requires PHP: 5.4
Stable tag: 2.2.26
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.
Maintenance release.


== Description ==


= About =

This is a payment gateway for Resurs Bank, that supports three Resurs Bank shopflows. Depending on which flow that's being selected, there are also requirements on different communication drivers, but in short - to get full control over this plugin - it is good to have them all available.

Tested with WooCommerce up to v3.8.2


= Compatibility =

The plugin was once written for WooCommerce v2.6 and up but as of today, we've started to change the requirements. It is no longer guaranteed that this plugin is compatible with such old versions. Ever since WooCommerce [discoverd a file deletion vulnerable (click here)](https://blog.ripstech.com/2018/wordpress-design-flaw-leads-to-woocommerce-rce/) our goal is to patch away deprecated functions.

 * Compatibility: WooCommerce - at least 3.x and up to 3.9.X
 * Plugin verified with PHP version 5.6 - 7.3


= Upgrade notice =

When developing the plugin for Woocommerce, we usually follow the versions for WooCommerce and always upgrading when there are new versions out. That said, it is *normally* also safe to upgrade to the latest woocommerce.


== Can I upgrade WooCommerce with your plugin installed? ==

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade warnings.


= Requirements and content =

 * At least PHP 5.6
 * [curl](https://curl.haxx.se): For communication via rest (Hosted flow and Resurs Checkout)
 * [PHP streams](http://php.net/manual/en/book.stream.php)
 * [SoapClient](http://php.net/manual/en/class.soapclient.php)
 * [EComPHP](https://test.resurs.com/docs/x/TYNM) (Bundled) [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-ecomphp.git)
 * [NetCURL](https://docs.tornevall.net/x/CYBiAQ) (Bundled) [Bitbucket](https://www.netcurl.org)

[Project URL](https://test.resurs.com/docs/display/ecom/WooCommerce) - [Plugin URL](https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/)


= Contribute =

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

For prior versions [look here](https://resursbankplugins.atlassian.net/projects/WOO?selectedItem=com.atlassian.jira.jira-projects-plugin:release-page&status=released-archived).

= 2.2.25 =

    * [WOO-260] - Shortcode för "delbetal från..." WordPress
    * [WOO-433] - Upgrade to latest ecom to run with cached getPayment(+Methods)-requests
    * [WOO-436] - Prepare woocommerce to support internal RCO tests.
    * [WOO-438] - Make sure that instances are as few as possible in wp-admin (getPayment, orderview, etc)
    * [WOO-439] - FI form translations (contribution)
    * [WOO-440] - Merge new ecom, based on ECOMPHP-343, into woo for testings
    * [WOO-453] - Prepare testdpt. for special plugins
    * [WOO-423] - Last received callbacks mismatch green box issue
    * [WOO-426] - Aftershop exceptions vs loggning
    * [WOO-432] - Session and admin notices may break wp-admin update page (PHP 7.3?)
    * [WOO-434] - Check plugin interferences (update templates, list callbacks form correct tab only, etc)
    * [WOO-435] - Eventually race conditions makes order stock reduce twice
    * [WOO-437] - "description of non-object"

== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.

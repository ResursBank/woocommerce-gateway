=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 5.0
Requires PHP: 5.4
Stable tag: 2.2.14
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.


== Description ==

= Important Notes =

As of v2.2.12, we do support SWISH and similar "instant debitable" payment methods, where payments tend to be finalized/debited long before shipping has been made. You can read more about it [here](https://test.resurs.com/docs/display/ecom/CHANGELOG+-+WooCommerce#CHANGELOG-WooCommerce-2.2.12)


= About =

This is a payment gateway for Resurs Bank, that supports three Resurs Bank shopflows. Depending on which flow that's being selected, there are also requirements on different communication drivers, but in short - to get full control over this plugin - it is good to have them all available.


= Compatibility =

The plugin was once written for WooCommerce v2.6 and up but as of today, we've started to change the requirements. It is no longer guaranteed that this plugin is compatible with such old versions. Ever since WooCommerce [discoverd a file deletion vulnerable (click here)](https://blog.ripstech.com/2018/wordpress-design-flaw-leads-to-woocommerce-rce/) our goal is to patch away deprecated functions.

 * Compatibility: WooCommerce - at least 3.x and up to 3.5 (it might eventually work with older versions too, however this will remain unconfirmed)
 * Plugin verified with PHP version 5.4 - 7.3
 * Plugin verified with WordPress 5.0 alpha
 * Addon scripts verified with PHP 5.3 - 7.3 (EComPHP 1.3.x and NetCURL 6.0.20+)


== Can I upgrade WooCommerce with your plugin installed? ==

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade warnings.


= Requirements and content =

 * At least PHP 5.4
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

The changelog is listing only the latest releases.
For a full list of our releases, have a look at [this site](https://resursbankplugins.atlassian.net/projects/WOO?selectedItem=com.atlassian.jira.jira-projects-plugin:release-page&status=released-archived)

= 2.2.14 =

    * [WOO-300] - Making RCO compatible with other payment methods
    * [WOO-301] - Deprecate iframe location "in payment methods list"
    * [WOO-304] - bookPaymentResponse gives us "DENIED" status. Add this to a metadata-tag
    * [WOO-273] - Text domain-problem
    * [WOO-296] - Resurs order ids in list view no longer visible on activation
    * [WOO-297] - Payment method list says unauthorized even if no credentials are added
    * [WOO-299] - Felaktigt orderid hos Resurs
    * [WOO-305] - Deprecated functions and variables that break code inspections
    * [WOO-309] - Synchronize orderlines in async interceptor-mode for RCO
    * [WOO-310] - Zero fees prevents negative fees be sent into ECom Payload (discount-fee-ish-fix)

= 2.2.13 =

Bad luck numbering abandoned.

= 2.2.12 =

    * [WOO-270] - Optional controller to change order status behaviour on callbacks
    * [WOO-286] - Problem solving direct debited payment methods during callback handling (SWISH, etc)
    * [WOO-293] - Allow more testings (by filter) in register callbacks section
    * [WOO-294] - Allow event logging
    * [WOO-298] - WooCommerce 3.5.0 and WP5 checks
    * [WOO-289] - Fees are added to "Resurs cart" even if they have 0 amount
    * [WOO-292] - nb_NO locale may be broken in next release
    * [WOO-295] - Slow shopflow configuration


== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.


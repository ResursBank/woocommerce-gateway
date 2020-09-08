=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 5.4.1
Requires PHP: 5.4
Stable tag: 2.2.36
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.
Maintenance release.


== Description ==

= About =

Official payment gateway for Resurs Bank, with full support for all non-deprecated shop flows.
SoapClient is required as many of the administrative actions are using SOAP.

= Compatibility =

The plugin was once written for WooCommerce v2.6 and up but as of today, we've started to change the requirements. It is no longer guaranteed that this plugin is compatible with such old versions. Ever since WooCommerce [discoverd a file deletion vulnerable (click here)](https://blog.ripstech.com/2018/wordpress-design-flaw-leads-to-woocommerce-rce/) our goal is to patch away deprecated functions.

 * Compatibility: WooCommerce - at least 3.x and up to 4.2.x
 * Plugin verified with PHP version 5.6 - 7.4 - avoid running on older PHP releases!


= Upgrade notice =

When developing the plugin for Woocommerce, we usually follow the versions for WooCommerce and always upgrading when there are new versions out. That said, it is *normally* also safe to upgrade to the latest woocommerce.


== Can I upgrade WooCommerce with your plugin installed? ==

If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade warnings.


= Requirements and content =

 * Required: At least PHP 5.6
 * Required: [curl](https://curl.haxx.se) or [PHP stream](http://php.net/manual/en/book.stream.php) features active (for REST based actions).
 * Required: [SoapClient](http://php.net/manual/en/class.soapclient.php) with xml drivers.
 * Included: [EComPHP](https://test.resurs.com/docs/x/TYNM) (Bundled) [Bitbucket](https://bitbucket.org/resursbankplugins/resurs-ecomphp.git)
 * Included: [NetCURL](https://netcurl.org/docs/) [Bitbucket](https://www.netcurl.org)

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

= 2.2.36 =

    * [WOO-490] - Activate WSDL cache for performance
    * [WOO-491] - Slow responses from API causes timeouts
    * [WOO-488] - Possible NATURAL vs LEGAL issue, where LEGAL methods won't shop up in checkout
    * [WOO-489] - Company name is not properly filled in

= 2.2.30 - 2.2.35 =

    * [WOO-477] - 2.2.32 ecomphp+netcurl patch
    * [WOO-478] - WC Tested up to: 4.2.0
    * [WOO-467] - Mockfail issue, index event-type not found
    * [WOO-470] - getAddress triggered but not executed on site when merchant has NATURAL methods only
    * [WOO-480] - Payment methods for simple/hosted should be sorted in "portal order"
    * [WOO-482] - New specificTypes in PSP
    * [WOO-483] - Visa bara betalmetod externt kort(Visa/MC) vid val av land som skilljer sig från ombudets land hos Resurs.
    * [WOO-473] - Delivery address  and user-info-change (RCO-535)
    * [WOO-485] - Payment methods are not limited to selected merchant country
    * [WOO-468] - Spinner/Text on simplified "Purchase order"

== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.

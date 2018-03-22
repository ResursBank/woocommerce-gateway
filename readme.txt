=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 4.9.1
Requires PHP: 5.4
Stable tag: 2.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.


== Description ==

Resurs Bank payment gateway for WooCommerce

 * Compatible with WooCommerce 2.6 up to 3.3.x
 * Verified with PHP version 5.4 - 7.1


Requirements:

 * At least PHP 5.4
 * [curl](https://curl.haxx.se)
 * [SoapClient](http://php.net/manual/en/class.soapclient.php)

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


== Frequently Asked Questions ==

You may want to look at https://test.resurs.com/docs/display/ecom/WooCommerce for updates regarding this plugin


== Screenshots ==

Docs are continuously updated at https://test.resurs.com/docs/display/ecom/WooCommerce


== Changelog ==

The changelog is listing only the latest releases.
For a full list of our releases, have a look at [this site](https://resursbankplugins.atlassian.net/projects/WOO?selectedItem=com.atlassian.jira.jira-projects-plugin:release-page&status=released-archived)

= 2.2.4 =

    * [WOO-226] - Counting non countables control - (first) PHP 7.2 compliant patch
    * [WOO-230] - Show PHP version in RB-admin panel
    * [WOO-229] - Editing payment methods that belongs to the PSP-sphere in simplified mode crashes wooAdminMethodEditor

= 2.2.3 =

    * [WOO-225] - Form fields must not be empty (RCO)

= 2.2.2 =

    * [WOO-95] - updatePaymentReference to WooCommerce orderId
    * [WOO-223] - getValidatedCallbackDigest()
    * [WOO-222] - Invoice numbers are not properly updated (shown from payment admin) if the invoice sequence are primary nulled


== Upgrade Notice ==

    * (1.2.3) If you are upgrading from 1.2.0 to 1.2.3 and using hosted flow, an update of the current payment methods may be required, to get the "Read more"-issue fixed.
    * (1.1.0) If you are upgrading through WordPress autoupdater, you also have to upgrade the payment methods configuration page afterwards.


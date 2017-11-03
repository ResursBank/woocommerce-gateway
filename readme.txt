=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 4.8.2
Requires PHP: 5.4
Stable tag: 2.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.


== Description ==

Resurs Bank payment gateway for WooCommerce

 * Compatible with WooCommerce 5.6 up to 3.0.x - 3.2.x
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

For a full list of our releases, have a look at [this site](https://resursbankplugins.atlassian.net/projects/WOO?selectedItem=com.atlassian.jira.jira-projects-plugin:release-page&status=released-archived)

= 2.1.5 =

    * [WOO-201] - Single product view: Support annuity factors
    * [WOO-203] - Selectors for annuity factors not "refreshed"
    -theme patches-
    * [DEMOSHOP-17] - Go to shop on chosen flow
    * [DEMOSHOP-18] - No updates in cart count
    * [DEMOSHOP-20] - Cart shaker bug
    * [DEMOSHOP-22] - Update theme products
    * [DEMOSHOP-23] - Part payment with annuity factors (WOO-201)
    * [DEMOSHOP-24] - Hide unsupported payment methods (PSP) for simplified/hosted (Via EComPHP)
    * [DEMOSHOP-25] - Payment method adaption
    * [DEMOSHOP-27] - Hide PSP from Simplified (DEMOSHOP-24 continued)
    * [DEMOSHOP-28] - Can't check out simplified after plugin 2.1.3


= 2.1.3 =

    * [WOO-198] - Support multiple credentials i demoshop mode (special templates only)
    * [WOO-199] - Test plugin with WooCommerce 3.2.0


= 2.1.2 =

    * [WOO-189] - PSP methods are half way supported in simple/hosted flow, but should be excluded from listing
    * [WOO-190] - Aftershop functions should add notices in shop admin
    * [WOO-197] - EComPHP change of behaviour in getPaymentMethods
    * [WOO-195] - Not an actual bug: error setting certificate verify locations (allow, in test, setting SSL validation state disabled)
    * EComPHP 1.1.22 added, with refactored afterShop support


= 2.1.1 =

    * [WOO-193] - Reduce order stock disappeared from wp-admin (hotfix)
    * [WOO-191] - Callback update registrator runs on pages that does not belong to the function
    * [WOO-192] - getPaymentInvoices can't handle prepared objects


== Upgrade Notice ==

    * (1.2.3) If you are upgrading from 1.2.0 to 1.2.3 and using hosted flow, an update of the current payment methods may be required, to get the "Read more"-issue fixed.
    * (1.1.0) If you are upgrading through WordPress autoupdater, you also have to upgrade the payment methods configuration page afterwards.


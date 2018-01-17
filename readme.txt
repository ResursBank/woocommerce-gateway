=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 4.9.1
Requires PHP: 5.4
Stable tag: 2.2.2
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

The changelog is listing only the latest releases.
For a full list of our releases, have a look at [this site](https://resursbankplugins.atlassian.net/projects/WOO?selectedItem=com.atlassian.jira.jira-projects-plugin:release-page&status=released-archived)

= 2.2.2 =

    * [WOO-95] - updatePaymentReference to WooCommerce orderId

= 2.2.1 =

    * [WOO-217] - "Threeflags" compatibility for hosted lost in space
    * [WOO-218] - Synchronous completion of an order (simplifiedFlow) no longer works properly
    * [WOO-219] - Order stock reduces more than one time on BOOKED
    * [WOO-220] - Synchronous completion of an order (hostedFlow) no longer works properly

= 2.2.0 =

    * [WOO-155] - Upgrade to EComPHP 1.2.x by exchange 1.1 functions in current to those running in 1.2
    * [WOO-168] - Support PAYMENT_PROVIDER in  simplified flow.
    * [WOO-172] - Validate AfterShop flow functions
    * [WOO-173] - WooCommerce order view and details from Resurs
    * [WOO-174] - When internal aftershop is disabled show notice in order view
    * [WOO-179] - Prevent class conflicts by renaming external library classes - put them in namespace
    * [WOO-181] - Investigate the specrows vs tax-calculating on "show inc. or ex. tax"
    * [WOO-191] - Callback update registrator runs on pages that does not belong to the function
    * [WOO-216] - Allow cards without card numbers
    * [WOO-154] - isResursDemo() might cause eternal loops on plugin activation in some systems
    * [WOO-175] - If order is finalized from payment admin woocommerce might not get updated properly
    * [WOO-176] - Updating cart (with discounts etc) fails
    * [WOO-193] - Reduce order stock disappeared from wp-admin
    * [WOO-211] - Hosted flow needs payload before cleanup to get own back/success-url
    * [WOO-212] - Trace The WC_Cart->tax argument is deprecated since version 2.3. Use WC_Tax directly
    * [WOO-213] - EC1.2 and invoice sequencing does not work when invoice sequence is null
    * [WOO-214] - Backurl checkout
    * [WOO-215] - Plugin no longer checks if payment method is enabled or disabled


== Upgrade Notice ==

    * (1.2.3) If you are upgrading from 1.2.0 to 1.2.3 and using hosted flow, an update of the current payment methods may be required, to get the "Read more"-issue fixed.
    * (1.1.0) If you are upgrading through WordPress autoupdater, you also have to upgrade the payment methods configuration page afterwards.


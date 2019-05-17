=== Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 5.2
Requires PHP: 5.4
Stable tag: 2.2.18
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.
Maintenance release.


== Description ==

= Important Notes =

As of v2.2.12, we do support SWISH and similar "instant debitable" payment methods, where payments tend to be finalized/debited long before shipping has been made. You can read more about it [here](https://test.resurs.com/docs/display/ecom/CHANGELOG+-+WooCommerce#CHANGELOG-WooCommerce-2.2.12)


= About =

This is a payment gateway for Resurs Bank, that supports three Resurs Bank shopflows. Depending on which flow that's being selected, there are also requirements on different communication drivers, but in short - to get full control over this plugin - it is good to have them all available.


= Compatibility =

The plugin was once written for WooCommerce v2.6 and up but as of today, we've started to change the requirements. It is no longer guaranteed that this plugin is compatible with such old versions. Ever since WooCommerce [discoverd a file deletion vulnerable (click here)](https://blog.ripstech.com/2018/wordpress-design-flaw-leads-to-woocommerce-rce/) our goal is to patch away deprecated functions.

 * Compatibility: WooCommerce - at least 3.x and up to 3.5 (it might eventually work with older versions too, however this will remain unconfirmed)
 * Plugin verified with PHP version 5.4 - 7.3
 * Addon scripts verified with PHP 5.3 - 7.3 (EComPHP 1.3.x and NetCURL 6.0.20+)


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

= 2.2.18 =

    * [WOO-395] - (T) Avoid hard coding of the part payment widget
    * [WOO-283] - (T) PAYMENT_PROVIDER and LEGAL
    * [WOO-291] - (T) Only shipping information are shown in wp-admin (orders)
    * [WOO-302] - (T) Add get-parameter in callback urls that tells about environment
    * [WOO-351] - (T) Check if the meta data can be used for fetching wrongly created orders
    * [WOO-376] - (T) Woocommerce upated so wc tested up to should be changed also
    * [WOO-380] - (T) WordPress 5.2 is imminent
    * [WOO-384] - (T) Payment method models is unsupported in multisites
    * [WOO-393] - (T) Renamed payment method titles may be confusing  when running simplified
    * [WOO-348] - (B) After a denied payment, updatePaymentReference no longer works and the orderid will not be updated on the new order if "continued" with an approved govid
    * [WOO-349] - (B) Status handling looks weird with merchants created via "MP"
    * [WOO-373] - (B) The list of payment methods in admin excludes methods if they inherit same description (array overwriting)
    * [WOO-374] - (B) Not all payment methods are listed in admin (MP)
    * [WOO-377] - (B) Add_order_note is missing a pointer (important)
    * [WOO-379] - (B) Two windowed orders makes javascript throw "Unknown errors"
    * [WOO-383] - (B) When credentials are used on more than one platform in the same time with incremental updateOrderReferences sites may collide
    * [WOO-387] - (B) Ending session tries to destroy a session when it does not exist on applied filters
    * [WOO-388] - (B) Payment method name is empty in wp-admin orderview when simplified is used
    * [WOO-389] - (B) Guard curl functions (curl_init, curl_exec).
    * [WOO-391] - (B) Check which payment method that is shown in the order list when the first checkout failed
    * [WOO-385] - (B) Order ids are not updated properly
    * [WOO-386] - (B) Statuses are not updated after denied payments


== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.


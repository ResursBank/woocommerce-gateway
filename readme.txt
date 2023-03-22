=== Resurs Bank Payment Gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Resurs, Payment, Payment gateway, ResursBank, payments
Requires at least: 5.5
Tested up to: 6.0
Requires PHP: 7.0
Stable tag: 2.2.105
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC Tested up to: 7.5.0

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

= About =

Official payment gateway for Resurs.

Help us translate the plugin by joining [Crowdin](https://crwd.in/resursbankwoocommerce)!

= Supported shop flows =

* [Simplified Shop Flow](https://test.resurs.com/docs/display/ecom/Simplified+Flow+API). Integrated checkout that works with WooCommerce built in features.
* [Resurs Checkout Web](https://test.resurs.com/docs/display/ecom/Resurs+Checkout+Web). Iframe integration. Currently supporting **RCOv1 and RCOv2**.
* [Hosted Payment Flow](https://test.resurs.com/docs/display/ecom/Hosted+Payment+Flow). A paypal like checkout where most of the payment events takes place at Resurs.

== Multisite/WordPress Networks ==

The plugin **do** support WordPress networks (aka multisite), however it does not support running one webservice account over many sites at once. The main rule that Resurs works with is that one webservice account only works for one site. Running multiple sites do **require** multiple webservice accounts!


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
* HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs.
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

1. Upload the plugin archive to the "/wp-content/plugins/" directory.
2. Make sure the plugin has write access to itself under the includes folder (see below).
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Configure the plugin via admin control panel.
5. On upgrades, make sure you synchronize language if WordPress suggest this.

(Or install and activate the plugin through the WordPress plugin installer)

= Write access to the includes folder =

The most commonly used path to the plugin folder's include-path is set to:
/wp-content/plugin/resurs-bank-payment-gateway-for-woocommerce/includes
This path has to be write-accessible for your web server or the plugin won't work properly since the payment methods are written to disk as classes. If you have login access to your server by SSH you could simply run this kind of command:

    chmod a+rw <wp-root>/wp-content/plugin/resurs-bank-payment-gateway-for-woocommerce/includes

If you have a FTP-client or similar, make sure to give this path write-access for at least your webserver.

= Upgrading =

When upgrading the plugin via WordPress plugin manager, make sure that you payment methods are still there. If you are unsure, just visit the configuration panel for Resurs once after upgrading since, the plugin are rewriting files if they are missing.

As of v2.2.12, we do support SWISH and similar "instant debitable" payment methods, where payments tend to be finalized/debited long before shipping has been made. You can read more about it [here](https://test.resurs.com/docs/display/ecom/CHANGELOG+-+WooCommerce#CHANGELOG-WooCommerce-2.2.12).


== Frequently Asked Questions ==

You may want to look further at [https://test.resurs.com/docs/display/ecom/WooCommerce](https://test.resurs.com/docs/display/ecom/WooCommerce) for updates regarding this plugin.

= Plugin is causing 40X errors on my site =

There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message there are few things to take in consideration:

* 401 = Unauthorized.
**Cause**: Bad credentials
**Solution**: Contact Resurs support for support questions regarding API credentials.

* 403 = Forbidden.
**Cause**: This may be more common during test.
**Solution:** Resolution: Contact Resurs for support.

= There's an order created but there is no order information connected to Resurs =

This is a common question about customer actions and how the order has been created/signed. Most of the details is usually placed in the order notes for the order, but if you need more information you could also consider contacting Resurs support.

= Handling decimals =

Setting decimals to 0 in WooCommerce will result in an incorrect rounding of product prices. It is therefore adviced to set decimal points to 2.


== Screenshots ==

Docs are continuously updated at [https://test.resurs.com/docs/display/ecom/WooCommerce](https://test.resurs.com/docs/display/ecom/WooCommerce)


== Changelog ==

For a full list of changes, [look here](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/CHANGELOG.md) - CHANGELOG.md is also included in this package.

= 2.0.105 =

* [WOO-1163](https://resursbankplugins.atlassian.net/browse/WOO-1163) Tax classes/Shipping no longer returns data from WooCommerce 7.5.0

= 2.2.104 =

* Making sure deliveryAddress exists before checking it.

= 2.2.103 =

[WOO-1004](https://resursbankplugins.atlassian.net/browse/WOO-1004) Verifiera att usererrormessage är det som exponeras vid bookPayment på kassasidan \(gamla\)

= 2.2.102 =

[WOO-1013](https://resursbankplugins.atlassian.net/browse/WOO-1013) Character destroys deliveryInfo

= 2.2.101 =

[WOO-809](https://resursbankplugins.atlassian.net/browse/WOO-809) Empty customer data validation issues
[WOO-690](https://resursbankplugins.atlassian.net/browse/WOO-690) \(Gamla\) Stoppa frusna ordrar från att sättas i "completed".
[WOO-698](https://resursbankplugins.atlassian.net/browse/WOO-698) \(Gamla\) Fixa adress på orderinfo

= 2.2.100 =

* Updated strings for tested-up-to.

= 2.2.99 =

* [WOO-667](https://resursbankplugins.atlassian.net/browse/WOO-667) finalizepayment no longer fires errors due to a fix in ecom
* [WOO-665](https://resursbankplugins.atlassian.net/browse/WOO-665) kredit blir fel status completed
* [WOO-662](https://resursbankplugins.atlassian.net/browse/WOO-662) Statusqueue issues

= 2.2.98 =

* [WOO-676](https://resursbankplugins.atlassian.net/browse/WOO-676) Ändra DENIED-meddelande

= 2.2.97 =

[WOO-659](https://resursbankplugins.atlassian.net/browse/WOO-659) Removing order lines from orders created by other methods than Resurs

= 2.2.96 =

[WOO-637](https://resursbankplugins.atlassian.net/browse/WOO-637) Purchase-Overlay are shown by mistake in some themes

= 2.2.95 =

[WOO-636](https://resursbankplugins.atlassian.net/browse/WOO-636) WP \+ Woo tested up to...


== Upgrade Notice ==

Make sure your payment methods are still there after upgrading the plugin.
Also, make sure you synchronize language if WordPress suggest this.

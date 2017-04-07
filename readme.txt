=== Plugin Name ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments
Requires at least: 3.0.1
Tested up to: 4.7.3
Stable tag: 2.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.


== Description ==

Resurs Bank payment gateway for WooCommerce.
Tested with WooCommerce up to version 2.6.14
Requires PHP 5.4 or later.
For the use of OmniCheckout you also need cURL (EComPHP).

[Project URL](https://test.resurs.com/docs/display/ecom/WooCommerce) - [Plugin URL](https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/)



== Installation ==

1. Upload the plugin archive to the "/wp-content/plugins/" directory
2. Activate the plugin through the "Plugins" menu in WordPress
3. Configure the plugin via admin control panel

(Or install and activate the plugin through the WordPress plugin installer)
If you are installing the plugin manually, make sure that the plugin folder contains a folder named includes and that includes directory are writable, since that's where the payment methods are stored.

= Upgrading =

If you are upgrading through WordPress autoupdater, you also have to upgrade the payment methods configuration page afterwards.


== Frequently Asked Questions ==

You may want to look at https://test.resurs.com/docs/display/ecom/WooCommerce for updates regarding this plugin


== Screenshots ==

Docs are continuously updated at https://test.resurs.com/docs/display/ecom/WooCommerce


== Changelog ==


= 2.0.1 =

    * [WOO-96] - Wrong title set in initial startup, showing only "resurs bank" if not configured by merchant
    * [WOO-97] - Frontend password checking won't work as we switch between test/production (RB #73564)

= 2.0.0 (alpha1+RC1) =

    * [WOO-3] - Administration Control Panel UI - Refactored (RB #70342)
    * [WOO-29] - Generate payment method configuration on fly while running getPaymentMethods()
    * [WOO-52] - Save changes-button-clarification
    * [WOO-53] - Message at callbacks section
    * [WOO-55] - When callbacks are updating, check that they are really working and is not blocked
    * [WOO-59] - Fix the combination of waitForFraudControl=F and annulIfFrozen=T
    * [WOO-60] - Settings for the Resurs Checkout shopflow
    * [WOO-61] - Can not save changes for settings/shopflow
    * [WOO-62] - Taxrates in configuration
    * [WOO-63] - Fully support at least english and swedish in the adminui
    * [WOO-64] - Admin nonce awareness (visual)
    * [WOO-67] - Hjälptext vid "Inställningar för RB Shopflöde"
    * [WOO-68] - Helptext at default settings
    * [WOO-72] - Extend payment methods list with editable fee information
    * [WOO-75] - Resurs Checkout should be default when installing the plugin
    * [WOO-76] - Resurs Bank logotype instead of text at tab
    * [WOO-81] - Remove deprecated configuration views
    * [WOO-89] - Upgrade test to 2.x from a prior 1.x version
    * [WOO-90] - Update changelog (readme.txt)
    * [WOO-42] - Initial titles looks wrong
    * [WOO-51] - Drop down vid moms
    * [WOO-54] - Activate/Deactivate is just spinning
    * [WOO-56] - Title details are missing
    * [WOO-57] - Fees are not saved from payment method editor
    * [WOO-58] - All payment methods gets the same title
    * [WOO-65] - Callbacks are reloading from webservices each page refresh
    * [WOO-66] - Helptext at settings shopflow
    * [WOO-71] - Dual messages are shown in the list of methods when using Resurs Checkout
    * [WOO-77] - As advanced settings are changing, there might be a mixed behaviour in the store
    * [WOO-79] - Descriptions and information are not shown in the checkout
    * [WOO-80] - Extra specrow in bookPayemnt
    * [WOO-82] - When cleaning up cookies, the session warning alert shows each reload (related to callback updates)
    * [WOO-84] - Minor issues in the shopflow settings section
    * [WOO-86] - Landing page are failing when paying with annulmentCustomerGovId
    * [WOO-88] - Translations needs to be adjustified (SE/EN)
    * [WOO-69] - Spinner is not showing at reload
    * [WOO-70] - When configuring Resrs Checkout, it is possible to also click and edit the payment methods via the listview
    * [WOO-73] - When configuring Resurs Checkout, it is possible to also click and edit the payment methods via the listview
    * [WOO-74] - Fix tax selection view
    * [WOO-78] - Adjust texts
    * [WOO-83] - Fees are saving in the ajax editor even if it's not filled in
    * [WOO-14] - Simplified flow/AdminPanel, get payment methods live
    * [WOO-20] - Payment methods list, light editor, live viewing in checkout (RB #72057/WOO-20)
    * [WOO-21] - Changing order reference names (RB #71949)
    * [WOO-22] - Transform password fields to hidden data content (RB #68625)
    * [WOO-28] - Payment method activation panel issues (RB #72703)
    * [WOO-33] - "Tool tips" (labels - or descriptions) are missing as extra descriptions
    * [WOO-34] - Checkboxes missing the "Active" text
    * [WOO-35] - Setting credential password should render automatic listing of payment methods
    * [WOO-36] - Credential checking while updating merchant account
    * [WOO-37] - Column for customized method titles
    * [WOO-38] - Clarify the title in the method editor
    * [WOO-39] - Processing spinner while updating adminUI data
    * [WOO-40] - Resurs Checkout Configuration Tab
    * [WOO-41] - Make sure the config covers all requirements
    * [WOO-43] - AdminUI callback setter
    * [WOO-47] - resursbankadmin.js 1.x-deprecation
    * [WOO-48] - AdminUI 1.x deprecation (redirect to new interface)
    * [WOO-50] - Support multiple element updates via adminui-js
    * [WOO-30] - ')[' restricts PHP 5.3 usage
    * [WOO-44] - Demoshop vs getaddress
    * [WOO-45] - Realtime fetching of payment methods might fail if credentials are wrong
    * [WOO-46] - Nested onloads (resursbankadmin.js) found
    * [WOO-49] - Order view statuses looks bad after the use of internal AdminUI-labels

= 1.2.7.19 =

 * [WOO-32] - Some parts of the system still needs the transent configuration to set output values properly (RB #72692)

= 1.2.7.18 =

 * [WOO-27] - Updating payment methods keeps deprecated methods listed in admin (RB #72243)

= 1.2.7.17 =

 * [WOO-26] - Payment methods are not showing up in admin (RB #72208)

= 1.2.7.16 =

 * [WOO-23] - Division by zero on line 939
 * [WOO-24] - Undefined indexes and properties

= 1.2.7.15 =

 * [WOO-2] - Set up better error messages for local bookings / BookByAjaxError (RB #71514)
 * [WOO-10] - Support callback event BOOKED (RB #71526)
 * [WOO-11] - Clear old session on "not returning to thank you"Clear old session on "not returning to thank you" / avoid omniRef on empty cart (RB #71608)
 * [WOO-12] - Log DENY from Resurs Checkout (RB #71619)
 * [WOO-15] - Callbacks: Order notes are not set for each callback received if status is already set (RB #70226)
 * [WOO-16] - Streamlined flow of behaviour: Fail/Backurls always cancels an order (RB #71877)
 * [WOO-18] - Do not set completed if finalization is unavailable (RB #71905)

= 1.2.7.14 =

 * [WOO-9] - E-Mail confirmations are sent before booking completed (RB #70191)
 * [WOO-8] - Zero/null values are not properly encoded, using PHP >= 7 (RB #70321)

= 1.2.7.13 =

 Ghost Version. Releasing something with a version number of 13, on friday 13th is probably not a good idea.

= 1.2.7.12 =

 * Resurs issue #71430: Moving reduceOrderStock to successUrl (Resurs Checkout/Omni)

= 1.2.7.11 =

 * cUrl NetworkLib updated

= 1.2.7.10 =

 * Delivery address- and shipping address form fields are not properly removed as Resurs Checkout is active in some themes

 = 1.2.7.9 =

 * Use SKU instead of postid for setting article (optional)

= 1.2.8 =

 * Compatibility issues discovered with PHP 7

= 1.2.7.7 =

 * Handling of failed orders updated: Backurl now uses successurl to pass the same way as a proper order, but cancels it instead when payments are failing
 * Session set order references no longer expires after 900s
 * OmniAjax also handles radio buttons
 * Translations updated
 
= 1.2.7.6 =

 * Minor fixes for javascript checkout form related to Resurs Checkout

= 1.2.7.5 =

 * Minor fixes for javascript, iframe and error handling

= 1.2.7.4 =

 * Resurs Issue 69993: Update of EComPHP - cUrl-library changed to get more details out of webcalls
 * Resurs Issue 69842: Update of invalidation of they way we handle sessions with order references (hotfix part 2, related to 69827)
 * Fix for sending data from Resurs Checkout to background process, where checkboxes are set from true to false

= 1.2.7.3 =

 * Independent problem, that does not clear notices after they have been shown (during order process) - non critical issue

= 1.2.7.2 =

 * Resurs Issue 69842: Invalidation of they way we handle sessions with order references (hotfix part 2, related to 69827)

= 1.2.7.1 =

 * Resurs Issue 69827: Better handling of wooCommerce internal errors during payment processing (hotfix, part 1)

= 1.2.7 =

 * Replacement URL for callbacks, default home_url should be empty
 * Added improved support for Resurs Checkout (Formerly known as Omni Checkout)
 * OmniJS 0.04, to support live communication between shop and iframe
 * Resurs Issue 69163: Fixed nested calls for document.ready in resursbank.js
 * Changed jQuery() to $RB() in resursbank.js
 * resursbank.js got methods to create orders in background before sending payment registrations to Resurs Bank (Resurs Checkout)
 * Resurs Checkout now uses the supported iframe communication (https://test.resurs.com/docs/x/5ABV)
 * Minified versions of all js updated, even though that they are currently not in use
 * Resurs Issue 69135: Removed deprecated &amp;-urls from callbacks
 * Resurs Issue 69048: Digest failures during callbacks changed from error code 401 to 406
 * Changed behaviour on how to handle fees and extra fees
 * Resurs Issue 68814: When changing cart on checkout page, this is also propagated to the iframe (especiallt coupons are affected)
 * Fixed some payment min-max-amount issues - in test environment, payments that are not accepted based on min-max are shown to notify the tester
 * Resurs Issue 69347: Shop shows "Array" (string) in the payment method selection when no payment methods are available
 * Options to reduce order stock added, also fixed (in the same place) a problem where customer cart don't get emptied properly
 * Resurs Issue 69199: Secured payment flows with nonces
 * Credentials for Resurs Bank configuration fields are now properly set to "password", not "text"
 * More options are available for Resurs Checkout-flow, amongst them is where to place the iframe in the store (Issue 68547) due to different template behaviours
 * While using Resurs Checkout, WooCommerce local "payment agreement" checkbox is removed, since the agreement is located in the iframe

    + EComPHP

 * toJsonByType() made lesser sensitive with other methods than simplified
 * Added support for Resurs Checkout and iframe updating (PUT) which also rendered ...
 * ... omniUpdateOrder() which supports PUT-calls to update the iframe with new cart data
 * Added support for changing environments manually
 * Removed a quite weird test value that got stuck in the code
 * Added support for setting ResursCheckout BOOKED callbacks
 * Added support for setting form template rules manually in case of quickfixes from Resurs Bank in the getFormTemplateRules()
 * Changed getFormTemplateRules() at REVOLVING_CREDIT level
 * Added clearOcSHop() and getOmniUrl() - which makes it possible to remove the oc-shop.js from Resurs Bank iframe loader, in case the resizer has to be built differently
 * annulPayment fix where the class resurs_addMetaData only should be loaded if exists
 * Made renderSpecContainer public instead of private, to meet requirements from developers
 * Moved phpdoc for getCostOfPurchase() to test.resurs.com-docs
 * Added renderType UPDATE for future "update payments" at Resurs Bank

= 1.2.6.3 =

 * Hotfixed production url for OmniCheckout (EComPHP mostly)


= 1.2.6.2 =

 * Resurs Issue 68267: Externally added payment fees missing in the specrow-array
 * Resurs Issue 68025, 68324, 68359: Logotype icons support (For branded payment methods)
 * Hotfix: Add support for custom callback urls
 * Hotfix: Behaviour of streamline fixes
 * PreOmni: shopUrl is required for iFrame communication

= 1.2.6.1 =

 * Wrong order for some options in the wp-admin interface


= 1.2.6 =

 * Wrong order for some options in the wp-admin interface (1.2.6.1)
 * Resurs-Issue 68051: CostOfPurchase (CSS) fails on customized sites that is not following standard paths
 * Resurs-Issue 68052: Danish translations completed
 * Added customCallbackUrls (Hotfix)
 * EComPHP now allows changes of test urls on fly (setTestUrl)
 * Allow getAddress work in production mode while in test mode



= 1.2.5 =

 * Resurs-Issue 66174: Support for Resurs Bank Omni Checkout
 * Resurs-Issue 67860: Making sure default settings are there after installation
 * Resurs-Issue 67817: Labels missing for some field in simplified shop slow ("LEGAL" only)
 * Resurs-Issue 67815: New option for getAddress for completely disable the usage
 * Resurs-Issue 67796: Behaviour for switching between "NATURAL" and "LEGAL" when getAddress is active changed
 * Resurs-Issue 67782: Corrections made in translation files
 * Resurs-Issue 67659: Fixed issues with PART_PAYMENT and specificType returned from wsdl
 * Resurs-Issue 67642: Minor fix for "Read more"-bootstrap buttons
 * Resurs-Issue 67641: Fixed a legacy bug that only occurs when a representative have one payment method configured (EComPHP)
 * Resurs-Issue 67584: "Update payment methods"-button missing text fixed at last
 * Resurs-Issue 67393: "Read more"-button showing up on "existing card"-methods
 * Resurs-Issue 66174: Support for Omni Checkout added
 * Resurs-Issue 66202: All upgrade notices removed

 Also added assets in the repo!


= 1.2.4 =

 * Resurs-Issue 67077: Continued updates for CSS


= 1.2.3 =

* Resurs-Issue 67077: Read more added for hosted flow (Moved from 1.2.1)


= 1.2.0 =

* Resurs-Issue 66684: HostedFlow implementation
* Resurs-Issue 66524: Upgrading plugins removes dynamically created payment methods
* Resurs-Issue 66174: PreInitialized OmniCheckout - (Incl add of js/resursbankomni.js and staticflows), INCLUDE_RESURS_OMNI, etc
* Resurs-Issue 66819: EComPHP - Call to a member function getPaymentMethods() on null
* Resurs-Issue 66806: jQuery dependencies updated
* Environment checking when initializing Resurs-flow moved to function getServerEnv()
* EComPHP Snapshot 20160712 Update (Details at https://test.resurs.com/docs/x/94VM)


= 1.1.2 =

* Resurs-Issue 66520 - Minor css issue with getCostOfPurchase

= 1.1.1 =

* Fix: Typos

= 1.1.0 =

* Resurs-Issue 62081 - Switchover from old deprecated to new simplified shopflow
* Resurs-Issue 63541 - AUTOMATIC_FRAUD_CONTROL are not accepted at callback level
* Resurs-Issue 63666 - Minor fixes
* Resurs-Issue 63556 - Code cleanup
* Resurs-Issue 62982 - Checking connections against proxies through http headers may be a security issue
* Resurs-Issue 62876 - Cards should only display "Read more" when they are brand new

= 1.0.3 =

* Resurs-Issue 63541 - AUTOMATIC_FRAUD_CONTROL are not accepted at callback level (hotfix)

= 1.0.2 =

* Resurs-Issue 62118 - Update: Updates of SEKKI requirements (Link to "Read more" are replacing all legal links, to meet with new requirements)
* Resurs-Issue 62494 - Fix: IP address validation failures (Access to undeclared static property)
* No issue - Fix: Minor graphical fixes in payment information
* No issue - Fix: yourCustomerId changed from ? to - when booking payments

= 1.0.1 =

* Resurs-Issue 61725 - Add: Payment information from Resurs Bank shown in order admin
* Resurs-Issue 61919 - Update: Language files (Includes for issue 62002)
* Resurs-Issue 61897 - Add: Emulation of finalizeIfBooked in simplified flow (and some fixes)
* Resurs-Issue 62002 - Update: Language files updated (We now support EN+SE completely)
* Resurs-Issue 62003 - Fix: Securing directory structure
* No issue - Fix: Deprecation of PASSWORD_EXPIRATION


= 1.0.0 =

* Resurs-Issue 61071 Add: Implementation Extended AfterShopFlow (Partial handlers)
* Resurs-Issue 60842 Add: Aftershop WordPress using EcomBridge AfterShop
* Resurs-Issue 60915 Add: Aftershop-WooCommerce: Partial Annullments
* Resurs-Issue 60914 Add: Aftershop-WooCommerce: Partial Crediting
* Resurs-Issue 61231 Add: Checkbox for activating Extended AftershopFlow (Related to 61071)
* Resurs-Issue 59612 Fix: Payment method name are sent as specline when payment fees are included
* Resurs-Issue 61315 Fix: Frozen orders are not properly annulled from Woocommerce admin
* Resurs-Issue 61316 Fix: Partial annulled orders are not entirely annuled at Resurs Ecommerce
* Resurs-Issue 61398 Fix: Choosing customer type will update the information in the "get address"-field
* Resurs-Issue 61402 Fix: When choosing customer type "Company" the address request are updating wrong fields
* Resurs-Issue 61401 Behaviour changed: Locking the field "Company name" when "private person" (NATURAL) are selected
* Resurs-Issue 61691 Fix: add_fee() does not add payment fees in order confirmations but payment admin see the specrows for newer versions of woocommerce
* Resurs-Issue 61486 Prepared plugin for wordpress repo. Version 0.2 has been officially released as a plugin at wordpress.org (160204)

= 0.4 =

* Resurs-Issue 59482: Add: Helper for billing address added, to make it easier to get the correct address depending on the customer type
* Resurs-Issue 59295: Fix: Making sure that the correct payment method are used depending on customer type
* Resurs-Issue 60161: Fix: PHP 5.3 is not supported, but no notice are shown - from 0.3 the admin gui has a warning displayed if current version is lower than 5.4.0

= 0.3 =

Skipped.

= 0.2 =

Externally developed, no changelog available.


== Upgrade Notice ==

= 1.2.3 =

If you are upgrading from 1.2.0 to 1.2.3 and using hosted flow, an update of the current payment methods may be required, to get the "Read more"-issue fixed.


= 1.1.0 =

If you are upgrading through WordPress autoupdater, you also have to upgrade the payment methods configuration page afterwards.


# 2.2.105 #

* [WOO-1163](https://resursbankplugins.atlassian.net/browse/WOO-1163) Tax classes/Shipping no longer returns data from WooCommerce 7.5.0

# 2.2.104 #

* Making sure deliveryAddress exists before checking it.

# 2.2.103 #

[WOO-1004](https://resursbankplugins.atlassian.net/browse/WOO-1004) Verifiera att usererrormessage är det som exponeras vid bookPayment på kassasidan \(gamla\)

# 2.2.102 #

[WOO-1013](https://resursbankplugins.atlassian.net/browse/WOO-1013) Character destroys deliveryInfo

# 2.2.101 #

[WOO-809](https://resursbankplugins.atlassian.net/browse/WOO-809) Empty customer data validation issues
[WOO-690](https://resursbankplugins.atlassian.net/browse/WOO-690) \(Gamla\) Stoppa frusna ordrar från att sättas i "completed".
[WOO-698](https://resursbankplugins.atlassian.net/browse/WOO-698) \(Gamla\) Fixa adress på orderinfo

# 2.2.100 #

* Updated strings for tested-up-to.

# 2.2.99 #

* [WOO-667](https://resursbankplugins.atlassian.net/browse/WOO-667) finalizepayment no longer fires errors due to a fix in ecom
* [WOO-665](https://resursbankplugins.atlassian.net/browse/WOO-665) kredit blir fel status completed
* [WOO-662](https://resursbankplugins.atlassian.net/browse/WOO-662) Statusqueue issues

# 2.2.98 #

* [WOO-676](https://resursbankplugins.atlassian.net/browse/WOO-676) Ändra DENIED-meddelande

# 2.2.97 #

* [WOO-659](https://resursbankplugins.atlassian.net/browse/WOO-659) Removing order lines from orders created by other methods than Resurs

# 2.2.96 #

* [WOO-637](https://resursbankplugins.atlassian.net/browse/WOO-637) Purchase-Overlay are shown by mistake in some themes

# 2.2.95 #

* [WOO-636](https://resursbankplugins.atlassian.net/browse/WOO-636) WP \+ Woo tested up to...

# 2.2.94 #

* [WOO-630](https://resursbankplugins.atlassian.net/browse/WOO-630) Trustly visar också Resurs-loggan
* [WOO-628](https://resursbankplugins.atlassian.net/browse/WOO-628) Annuity factor settings has url reference as default value
* [WOO-627](https://resursbankplugins.atlassian.net/browse/WOO-627) Unconditionally always show payment methods \(and rebuild them on fly\)
* [WOO-629](https://resursbankplugins.atlassian.net/browse/WOO-629) Rebranded logotype

# 2.2.93 #

* [WOO-623](https://resursbankplugins.atlassian.net/browse/WOO-623) No radioknapps when getAddress is disabled with multiple customerTypes
* [WOO-619](https://resursbankplugins.atlassian.net/browse/WOO-619) Add warning about wordpress networks
* [WOO-618](https://resursbankplugins.atlassian.net/browse/WOO-618) If payment method list is single-customerType only

# 2.2.92 #

[WOO-616](https://resursbankplugins.atlassian.net/browse/WOO-616) Validate that radio buttons are not shown when only one payment method type is available

# 2.2.91 #

* [WOO-614](https://resursbankplugins.atlassian.net/browse/WOO-614) Descriptions containing ' in payment method classes
* [WOO-615](https://resursbankplugins.atlassian.net/browse/WOO-615) payment\_complete is not implemented for "finalized" orders

# 2.2.89 - 2.2.90 #

* [WOO-612](https://resursbankplugins.atlassian.net/browse/WOO-612) Static transient data are not cleaned up on class rewrites
* [WOO-611](https://resursbankplugins.atlassian.net/browse/WOO-611) Företagsfaktura ogiltigförklaras "med hjälp av" felaktig session
* [WOO-609](https://resursbankplugins.atlassian.net/browse/WOO-609) "Restricted" methods in prod?
* [WOO-607](https://resursbankplugins.atlassian.net/browse/WOO-607) PHP 8 soaprequest failures
* [WOO-610](https://resursbankplugins.atlassian.net/browse/WOO-610) Allow importing data automatically from v2.
* [WOO-606](https://resursbankplugins.atlassian.net/browse/WOO-606) Remove invoice peek requests
* [WOO-605](https://resursbankplugins.atlassian.net/browse/WOO-605) Change log destination
* [WOO-604](https://resursbankplugins.atlassian.net/browse/WOO-604) Handle customer type from respective written method class

# 2.2.89 (Hotfix) #

[WOO-607](https://resursbankplugins.atlassian.net/browse/WOO-607) PHP 8 soaprequest failures

# 2.2.87 - 2.2.88 #

* Hotfix for ecom requirements.

# 2.2.86 #

* [WOO-602](https://resursbankplugins.atlassian.net/browse/WOO-602) Centralize requirements
* [WOO-601](https://resursbankplugins.atlassian.net/browse/WOO-601) strftime is deprecated in PHP 8.1.0

# 2.2.84 - 2.2.85 #

* Cleanup.

# 2.2.83 #

* Libraries and readme patch.
* Simplified the way we fetch version information in plugin which is used by the ecommerce php library, making tags easier to handle.

# 2.2.82 #

* [WOO-600](https://resursbankplugins.atlassian.net/browse/WOO-600) Hotfix: QueryMonitor reports missing dependency

# 2.2.81

* Hotfix: Added ip-tracking feature for whitelisting-help in wp-admin.

# 2.2.80 #

* [WOO-599](https://resursbankplugins.atlassian.net/browse/WOO-599) Ip control section for woo

# 2.2.73-2.2.79 #

* Readme files updated several times.
* [WOO-597](https://resursbankplugins.atlassian.net/browse/WOO-597) If getAddress-form is entirely disabled and methods for both natural and legal is present

# 2.2.72 #

* [WOO-596](https://resursbankplugins.atlassian.net/browse/WOO-596) When trying to discover composer.json version/name data, platform renders warnings
* [WOO-595](https://resursbankplugins.atlassian.net/browse/WOO-595) Slow/crashing platform on API timeouts
* [WOO-594](https://resursbankplugins.atlassian.net/browse/WOO-594) Product pages stuck on Resurs-API timeouts
* [WOO-593](https://resursbankplugins.atlassian.net/browse/WOO-593) Partially handle server timeouts

# 2.2.71 #

* [WOO-592](https://resursbankplugins.atlassian.net/browse/WOO-592) Race conditions on callbacks, second edition

# 2.2.70 #

* [WOO-591](https://resursbankplugins.atlassian.net/browse/WOO-591) get\_cost\_of\_purchase: Rename call \(Also related to ECOMPHP-431\)
* [WOO-587](https://resursbankplugins.atlassian.net/browse/WOO-587) Stuck callbacks
* [WOO-583](https://resursbankplugins.atlassian.net/browse/WOO-583) Customer country may or may not be wrongfully returned as an empty string in the checkout and therefore not showing payment methods properly

# 2.2.65-69 #

* [WOO-584](https://resursbankplugins.atlassian.net/browse/WOO-584) Saving credentials is problematic the first round
  after wp-admin-reload.
* [WOO-585](https://resursbankplugins.atlassian.net/browse/WOO-585) PAYMENT_PROVIDER with government id

# 2.2.64 #

* [WOO-576](https://resursbankplugins.atlassian.net/browse/WOO-576) $return is sometimes not set when return occurs

# 2.2.63 #

Informative updates.

# 2.2.62 #

* [WOO-575](https://resursbankplugins.atlassian.net/browse/WOO-575) Order status gets an incorrect jump on
  bookSignedPayment and status=FINALIZED
* [WOO-574](https://resursbankplugins.atlassian.net/browse/WOO-574) Remove FINALIZATION and move "instant finalizations"
  into UPDATE.
* [WOO-573](https://resursbankplugins.atlassian.net/browse/WOO-573) Remove unnecessary callbacks
* [WOO-572](https://resursbankplugins.atlassian.net/browse/WOO-572) Activate logging of order stock handling

# 2.2.61 #

* [WOO-570](https://resursbankplugins.atlassian.net/browse/WOO-570) Resurs Annuity Factors Widget Error -- Unable to
  fetch .variations\_form: json.find is not a function

# 2.2.60 #

* [WOO-567](https://resursbankplugins.atlassian.net/browse/WOO-567) Variation Products and annuity factors

# 2.2.59 #

* [WOO-566](https://resursbankplugins.atlassian.net/browse/WOO-566) annuityfactors not calculating payFrom properly
* [WOO-568](https://resursbankplugins.atlassian.net/browse/WOO-568) Suomi translations update for "Read more"

# 2.2.58 #

* [WOO-565](https://resursbankplugins.atlassian.net/browse/WOO-565) Byte mellan simpla och rco gör att fler formulär
  dyker upp i kassan än vad som behövs
* [WOO-564](https://resursbankplugins.atlassian.net/browse/WOO-564) Byta flöde i "prod" kan orsaka problem för slutkund
* [WOO-561](https://resursbankplugins.atlassian.net/browse/WOO-561) Fel efter nekat köp när man byter personnummer
* [WOO-560](https://resursbankplugins.atlassian.net/browse/WOO-560) Efter ett denied-köp blir det fel betalmetod i wc
* [WOO-549](https://resursbankplugins.atlassian.net/browse/WOO-549) Read more not showing in hosted
* [WOO-558](https://resursbankplugins.atlassian.net/browse/WOO-558) Pluginet renderar iframe även på success-sidan
* [WOO-563](https://resursbankplugins.atlassian.net/browse/WOO-563) setStoreId filter should not be an integer \(prepare
  for future api's\)
* [WOO-562](https://resursbankplugins.atlassian.net/browse/WOO-562) Synkronisera billing-address med getPayment
* [WOO-544](https://resursbankplugins.atlassian.net/browse/WOO-544) Remove margul entries \(demoshop\)
* [WOO-513](https://resursbankplugins.atlassian.net/browse/WOO-513) Clean up old demoshop content
* [WOO-479](https://resursbankplugins.atlassian.net/browse/WOO-479) rcojs-facelift

# 2.2.53 - 2.2.57 #

* [WOO-553](https://resursbankplugins.atlassian.net/browse/WOO-553) Status fryst hos Resurs ger "inväntar betalning" i
  woo-commerce
* [WOO-545](https://resursbankplugins.atlassian.net/browse/WOO-545) Plugin could interfere with other parts of the
  system when not configured
* [WOO-541](https://resursbankplugins.atlassian.net/browse/WOO-541) Old ecom-classes
* [WOO-557](https://resursbankplugins.atlassian.net/browse/WOO-557) min-max filter
* [WOO-556](https://resursbankplugins.atlassian.net/browse/WOO-556) Store metadata for each received status that has
  been retrieved from the API
* [WOO-554](https://resursbankplugins.atlassian.net/browse/WOO-554) UserAgent saknas på RCO
* [WOO-548](https://resursbankplugins.atlassian.net/browse/WOO-548) Ability to disable internal form field validation
  and leave it to Resurs
* [WOO-543](https://resursbankplugins.atlassian.net/browse/WOO-543) Utred om kortnummer är ett krav på befintligt kort
* [WOO-536](https://resursbankplugins.atlassian.net/browse/WOO-536) Testing woo with upcoming ECOMPHP-409
* [WOO-509](https://resursbankplugins.atlassian.net/browse/WOO-509) Notify user on disabled configuration if plugin is
  disabled in the plugin options
* [WOO-507](https://resursbankplugins.atlassian.net/browse/WOO-507) Add information about selected flow in user-agent
* [WOO-459](https://resursbankplugins.atlassian.net/browse/WOO-459) New event for rcojs: checkout:purchase-denied

# 2.2.52 #

* [WOO-530](https://resursbankplugins.atlassian.net/browse/WOO-530) Using getaddress should render setting country if
  exists, based on the country set for the plugin
* [WOO-531](https://resursbankplugins.atlassian.net/browse/WOO-531) Woocommerce cancellation schedule should not cancel
  orders that is active in Resurs but "waiting for payment" in WooCommerce

# 2.2.51 #

* [WOO-527](https://resursbankplugins.atlassian.net/browse/WOO-527) Optionize the session handling filter

# 2.2.50 #

* [WOO-526](https://resursbankplugins.atlassian.net/browse/WOO-526) WOO-525 invalidates form validation

# 2.2.49 #

* [WOO-525](https://resursbankplugins.atlassian.net/browse/WOO-525) Which error messages is shown on screen?

# 2.2.45 - 2.2.48 #

* [WOO-523](https://resursbankplugins.atlassian.net/browse/WOO-523) Resurs Bank payments interferes with other payments
  if customer "backed out" from payment during payment
* [WOO-522](https://resursbankplugins.atlassian.net/browse/WOO-522) One lonely company payment method without naturals
  may break the natural method flow
* [WOO-524](https://resursbankplugins.atlassian.net/browse/WOO-524) "Instant finalized" refuses to update to completed

# 2.2.44 #

* [WOO-510] - Prevent plugin to run in product editor
* [WOO-517] - When disabling wp-admin-interferences, storefront is also partially disabled

# 2.2.43 #

* [WOO-487] - Fraud & Callbacks descriptions
* [WOO-501] - Metadata under the order has been hidden since the tax-on-discount release so the blue box has to show
  more info
* [WOO-506] - Prevent tab-logotype patches on tabs that belongs to other plugins
* [WOO-508] - govid should always be shown regardless of fields for getaddress
* [WOO-465] - Do not reopen password box if already open on username-click

# 2.2.42 #

* [WOO-503] - Incomplete payments via RCO causes session conflicts that renders parts of the store "uninloggable".

# 2.2.41 #

* [WOO-496] - Add options for how discounts should be handled (discounts applied without tax amount and proper VAT pct)
* [WOO-499] - The amount of metadata in each order may be confusing for merchants
* [WOO-502] - creditPayment shipping CC
* [WOO-497] - Apply discount settings to order rows
* [WOO-500] - Initially apply same setup to aftershop as WOO-497

# 2.2.30 - 2.2.44 #

* [WOO-510] - Prevent plugin to run in product editor
* [WOO-515] - Discounts + Hosted, gets errors.
* [WOO-517] - When disabling wp-admin-interferences, storefront is also partially disabled
* [WOO-477] - 2.2.32 ecomphp+netcurl patch
* [WOO-478] - WC Tested up to: 4.2.0
* [WOO-467] - Mockfail issue, index event-type not found
* [WOO-470] - getAddress triggered but not executed on site when merchant has NATURAL methods only
* [WOO-480] - Payment methods for simple/hosted should be sorted in "portal order"
* [WOO-482] - New specificTypes in PSP
* [WOO-483] - Visa bara betalmetod externt kort(Visa/MC) vid val av land som skilljer sig från ombudets land hos Resurs.
* [WOO-473] - Delivery address and user-info-change (RCO-535)
* [WOO-485] - Payment methods are not limited to selected merchant country
* [WOO-468] - Spinner/Text on simplified "Purchase order"
* [WOO-492] - Deprecation: WC_Legacy_Cart::coupons_enabled since 2.5.0
* [WOO-490] - Activate WSDL cache for performance
* [WOO-491] - Slow responses from API causes timeouts
* [WOO-488] - Possible NATURAL vs LEGAL issue, where LEGAL methods won't shop up in checkout
* [WOO-489] - Company name is not properly filled in
* [WOO-493] - Still race conditions in BOOKED (reduce order stock issue)? #11902
* [WOO-495] - Unable to credit payments with coupons

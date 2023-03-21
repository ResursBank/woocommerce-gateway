var $RB = jQuery.noConflict();

/**
 * Preconfigure how to handle billing- and shipping address fields depending on configuration.
 */
function getRcoFieldSetup() {
    if (omnivars["disableStandardFieldsForShipping"] === "1") {
        jQuery('div').remove('.woocommerce-billing-fields');
        jQuery('div').remove('.woocommerce-shipping-fields');
    } else {
        if (omnivars["showResursCheckoutStandardFieldsTest"] !== "1") {
            jQuery('.woocommerce-billing-fields').hide();
            jQuery('.woocommerce-shipping-fields').hide();
        } else {
            if (omnivars["isResursTest"] !== "1") {
                jQuery('.woocommerce-billing-fields').hide();
                jQuery('.woocommerce-shipping-fields').hide();
            }
        }
    }
}

$RB(document).ready(function ($) {
    // rcoFacelift back-compatible checkout.
    // The part below is set to run if rcoFacelift is not available.
    console.log(
        'RCO Remote Trust (legacy): ',
        getRcoRemote('legacy') ? true : false,
        '-- Detection through rcoFace is set to ',
        rcoFacelift
    );
    if (!rcoFacelift &&
        typeof ResursCheckout !== "undefined" &&
        typeof omnivars !== "undefined" &&
        omnivars !== null
    ) {
        $RB('#resurs-checkout-container').html(getRcoRemote('html'));

        getRcoFieldSetup();
        // "Instantiate" Legacy RCO System here.
        var resursCheckout = ResursCheckout('#resurs-checkout-container');

        // Automatically raise debugging if in test mode (= Disabled for production).
        if (typeof omnivars.isResursTest !== "undefined" && omnivars.isResursTest !== null && omnivars.isResursTest === "1") {
            resursCheckout.setDebug(1);
        }

        // Initialize RCO Legacy here.
        resursCheckout.init();
        if (typeof omnivars["iframeShape"] !== "undefined" && omnivars["iframeShape"] !== "") {
            resursCheckout.setOnIframeReady(function (iframeElement) {
                iframeElement.setAttribute('style', omnivars["iframeShape"]);
            });
        }

        // Set up handler for purchase fail.
        resursCheckout.setPurchaseFailCallback(function () {
            // OmniRef.
            var omniRef;
            if (typeof omnivars.OmniRef !== "undefined") {
                omniRef = omnivars.OmniRef;
                var preBookUrl = omnivars.OmniPreBookUrl + "&pRef=" + omniRef + "&purchaseFail=1&set-no-session=1";
                $RB.ajax(
                    {
                        url: preBookUrl,
                        type: "GET"
                    }
                ).success(
                    function (successData) {
                        // Do nothing, as we actually only touch the status.
                        //console.log(successData);
                    }
                ).fail(
                    function (x, y) {
                        handleResursCheckoutError(getResursPhrase("purchaseAjaxInternalFailure"));
                    }
                );
            }
            handleResursCheckoutError(getResursPhrase("resursPurchaseNotAccepted"));
        });

        // Set up handler for purchase denied.
        resursCheckout.setPurchaseDeniedCallback(function () {
            var omniRef;
            if (typeof omnivars.OmniRef !== "undefined") {
                omniRef = omnivars.OmniRef;
                var preBookUrl = omnivars.OmniPreBookUrl + "&pRef=" + omniRef + "&purchaseFail=1&set-no-session=1&purchaseDenied=1";
                $RB.ajax(
                    {
                        url: preBookUrl,
                        type: "GET"
                    }
                ).success(
                    function (successData) {
                    }
                ).fail(
                    function (x, y) {
                        handleResursCheckoutError(getResursPhrase("purchaseAjaxInternalDenied"));
                    }
                );
            }
            handleResursCheckoutError(getResursPhrase("resursPurchaseNotAccepted"));
        });

        // Set up handler for handling customer.
        resursCheckout.setCustomerChangedEventCallback(function (customerData) {
            // @todo This is a non-facelift section and should normally not be required unless Resurs has
            // @todo an older version for the used site. This segments could probably be removed (but not now).
            if (omnivars["disableStandardFieldsForShipping"] === "0") {
                return;
            }
            console.log("ResursCheckoutJS: [ClientSide] Received customer data update from iframe");
            var rendered = customerData["customerData"];
            var billing = rendered["address"];
            var delivery = rendered["delivery"];
            var useShipping = false;
            var triggerUpdateCheckout = false;
            var fillFields = {
                "first_name": "firstname",
                "last_name": "surname",
                "address_1": "address",
                "address_2": "addressExtra",
                "city": "city",
                "country": "countryCode",
                "email": "email",
                "postcode": "postal",
                "phone": "telephone"
            };
            if ($RB('#ship-to-different-address-checkbox').length > 0) {
                if (typeof delivery["firstname"] !== "undefined") {
                    useShipping = true;
                    if (!$RB('#ship-to-different-address-checkbox').is(':checked')) {
                        console.log("ResursCheckoutJS: [ClientSide] Shipping checkbox is not checked and there is a delivery address set in the iframe. Shipping fields should enable.");
                        $RB('#ship-to-different-address-checkbox').attr("checked", false);
                        $RB('#ship-to-different-address-checkbox').click();
                    }
                } else {
                    if ($RB('#ship-to-different-address-checkbox').is(':checked')) {
                        console.log("ResursCheckoutJS: [ClientSide] Shipping checkbox is checked but there is no delivery address set in the iframe. Shipping fields should disable.");
                        $RB('#ship-to-different-address-checkbox').click();
                    }
                }
            }
            $RB("[id^=billing_]").each(function (i, f) {
                if (typeof f.type === "string" && (f.type == "text" || f.type == "email" || f.type == "tel")) {
                    var b_name = f.id.substr(f.id.indexOf("_") + 1);
                    if (typeof fillFields[b_name] !== "undefined") {
                        var useField = fillFields[b_name];
                        if (typeof billing[useField] !== "undefined") {
                            triggerUpdateCheckout = true;
                            f.value = billing[useField];
                        }
                    }
                }
            });
            if (useShipping) {
                console.log("ResursCheckout: [ClientSide] Shipping is now included!");
                triggerUpdateCheckout = false;
                $RB("[id^=shipping_]").each(function (i, f) {
                    if (typeof f.type === "string" && (f.type == "text" || f.type == "email" || f.type == "tel")) {
                        var b_name = f.id.substr(f.id.indexOf("_") + 1);
                        if (typeof fillFields[b_name] !== "undefined") {
                            var useField = fillFields[b_name];
                            if (typeof delivery[useField] !== "undefined") {
                                if (useField == "postal" && delivery[useField] != "") {
                                    triggerUpdateCheckout = true;
                                }
                                f.value = delivery[useField];
                            }
                        }
                    }
                });
            }
            if (triggerUpdateCheckout) {
                console.log('triggerUpdateCheckout');
                $RB('body').trigger('update_checkout');
            }
        });

        // Handle the payment (create it) when customer is ready.
        resursCheckout.setBookingCallback(function (omniJsObject) {
            var omniRef = omnivars.OmniRef;
            var currentResursCheckoutFrame = document.getElementsByTagName("iframe")[0];
            var postData = {};
            // Fetch other fields found in the checkout form and merge the data, so it can be
            // sent with the rest of the order handling.
            $RB('[name*="checkout"] input,textarea').each(
                function (i, e) {
                    if (typeof e.name !== "undefined") {
                        if (e.type == "checkbox") {
                            if (e.checked === true) {
                                omniJsObject[e.name] = e.value;
                            } else {
                                omniJsObject[e.name] = 0;
                            }
                        } else if (e.type == "radio") {
                            omniJsObject[e.name] = $RB('[name="' + e.name + '"]:checked').val();
                        } else {
                            omniJsObject[e.name] = e.value;
                        }
                    }
                }
            );

            // Make sure the iframe is present before we start backend comms.
            if (typeof currentResursCheckoutFrame !== "undefined" && typeof currentResursCheckoutFrame.src !== "undefined") {
                var errorString = "";
                // Prepare order and make it annullable on errors.
                if (omniRef != "" && typeof omnivars.OmniPreBookUrl !== "undefined") {

                    // Create order via backend url helper.
                    var preBookUrl = omnivars.OmniPreBookUrl + "&orderRef=" + omniRef;
                    $RB.ajax(
                        {
                            url: preBookUrl,
                            type: "POST",
                            data: omniJsObject
                        }
                    ).success(
                        function (successData) {
                            var success = typeof successData.success !== 'undefined' ? successData.success : false;
                            var errorCode = typeof successData.errorCode !== 'undefined' ? successData.errorCode : 0;
                            var errorString = typeof successData.errorString !== 'undefined' ? successData.errorString : '';

                            if (success) {
                                resursCheckout.confirmOrder(success);
                            } else {
                                var contactUs = getResursPhrase("contactSupport");
                                handleResursCheckoutError(errorString + " (" + errorCode + ") " + contactUs);
                            }

                            return false;
                        }
                    ).fail(
                        function (x) {
                            rbRefUpdated = false;
                            resursCheckout.confirmOrder(false);
                            var errorString = getResursPhrase("theAjaxWentWrong");
                            if (typeof x.statusText !== "undefined") {
                                errorString = x.statusText;
                            }
                            if (typeof x.responseJSON !== "undefined" && typeof x.responseJSON.errorString !== "undefined") {
                                errorString = x.statusText + " - " + x.responseJSON.errorString;
                            }
                            var partialError = getResursPhrase("theAjaxWentWrongWithThisMessage");
                            var contactUs = getResursPhrase("contactSupport");

                            console.log("Resurs Bank preBook failed.");
                            console.dir(x);

                            handleResursCheckoutError(partialError + ' ' + errorString + ' - ' + contactUs);
                            return false;
                        }
                    );
                }
            }
        });
    }
});

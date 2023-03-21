var $RB = jQuery.noConflict();
var rcoDebug = false;

/**
 * Customer data container for the RCO.
 * @type {{payment: null, customer: null}}
 */
var rcoContainer = {
    customer: {},
    payment: {},
    wooCommerce: {}
};

/**
 * Debugging in RCOv2 or higher.
 * @param noticeText
 */
function rcoDebugNote(noticeText) {
    if (rcoDebug) {
        console.log(
            '[RCOv2+] ',
            noticeText
        );
        if (typeof arguments[1] !== "undefined") {
            console.log(arguments[1]);
        }
    }
}


/**
 * Render and return the rest of the checkout form to engines that requires it.
 * @returns {{}}
 */
function getRbPostData() {
    var postData = {};
    $RB('[name*="checkout"] input,textarea').each(
        function (i, e) {
            if (typeof e.name !== "undefined") {
                if (e.type === "checkbox") {
                    if (e.checked === true) {
                        postData[e.name] = e.value;
                    } else {
                        postData[e.name] = 0;
                    }
                } else if (e.type === "radio") {
                    postData[e.name] = $RB('[name="' + e.name + '"]:checked').val();
                } else {
                    postData[e.name] = e.value;
                }
            }
        }
    );
    return postData;
}

/**
 * Parse and return static data that never change.
 * @param successData
 * @returns {{errorString: (string), success: (*|boolean), errorCode: (*|number)}}
 */
function getRcoSuccessData(successData) {
    return {
        success: typeof successData.success !== 'undefined' ? successData.success : false,
        errorCode: typeof successData.errorCode !== 'undefined' ? successData.errorCode : 0,
        errorString: typeof successData.errorString !== 'undefined' ? successData.errorString : ''
    };
}

/**
 * Handle failures and denies.
 * @param eventData
 * @param rejectType
 */
function getRcoRejectPayment(eventData, rejectType) {
    var preBookUrl = omnivars.OmniPreBookUrl + "&pRef=" + omnivars.OmniRef + "&purchaseFail=1&set-no-session=1";
    if (rejectType === 'deny') {
        preBookUrl += '&purchaseDenied=1';
    }
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
    handleResursCheckoutError(getResursPhrase("resursPurchaseNotAccepted"));
}

// RCO Facelift Handler. If you are looking for the prior framework handler, it is available via rcojs.js - however,
// those scripts are disabled as soon as the flag rcoFacelift (legacy checking) is set to true.
$RB(document).ready(function ($) {
    if (typeof $ResursCheckout !== 'undefined' || !getRcoRemote('legacy')) {
        if (null !== document.getElementById('resurs-checkout-container')) {
            // Set rcoFacelift to true if the new rco interface is available.
            rcoFacelift = true;
            getRcoFieldSetup();

            $ResursCheckout.create({
                paymentSessionId: getRcoRemote('paymentSessionId'),
                baseUrl: getRcoRemote('baseUrl'),
                hold: true,
                containerId: 'resurs-checkout-container'
            });

            // purchasedenied is no longer supported by framework -- only in postMsg.
            // purchasefail
            $ResursCheckout.onPaymentFail(function (event) {
                rcoDebugNote('onPaymentFail Triggered.', event);
                getRcoRejectPayment(event, 'fail');
            });
            // user-info-change => onCustomerChange (setCustomerChangedEventCallback equivalent).
            $ResursCheckout.onCustomerChange(function (event) {
                rcoDebugNote('onCustomerChange Triggered.', event);
                rcoContainer.customer = event
            });

            // payment-method-change => onPaymentChange (Apparently never used in woocommerce).
            $ResursCheckout.onPaymentChange(function (event) {
                rcoDebugNote('onPaymentChange Triggered.', event);
                rcoContainer.payment = event
            });

            // onSubmit (CreateOrder -- setBookingCallback equivalent).
            $ResursCheckout.onSubmit(function (event) {
                // At this point, we will ajaxify this:
                // {
                //     customer: {},   /// Customer data.
                //     payment: {},    /// Checkout/Payment data.
                //     wooCommerce: {} /// WooCommerce Request Forms (leftovers that is still not removed).
                // }
                rcoDebugNote('onSubmit Triggered.', event);
                rcoContainer.wooCommerce = getRbPostData();
                var preBookUrl = omnivars.OmniPreBookUrl + "&orderRef=" + omnivars.OmniRef;
                $RB.ajax(
                    {
                        url: preBookUrl,
                        type: "POST",
                        data: rcoContainer
                    }
                ).success(
                    function (successData) {
                        var contactUs = getResursPhrase("contactSupport");
                        var requestResponse = getRcoSuccessData(successData);
                        if (requestResponse.success) {
                            $ResursCheckout.release();
                        } else {
                            handleResursCheckoutError(
                                requestResponse.errorString + " (" + requestResponse.errorCode + ") " + contactUs
                            );
                        }
                        return false;
                    }
                )
            });
        }
    }
});

/*!
 * ResursCheckoutJS v0.10
 *  Generic Resurs Bank iFrame-driver for Resurs Checkout, for catching events in the Resurs Checkout iFrame.
 *  Used for the older version of RCO-Interceptor, maintenance only!
 *  Docs and info-urls resides in the non-minified version.
 *  Maintained by Resurs Bank: onboarding@resurs.se
 *
 */

/*
 * DOCS
 *
 * Located at https://test.resurs.com/docs/x/5ABV with changelog.
 *
 * Usage example:
 *
 *      // User configured callback: Handle the booking event in the shop (if this function not exists, ResursJS will automatically confirm the order)
 *      function resursBookingCallback(resursJsObject) {
 *          // Put your code to handle the customer order here (resursJsObject contains at most ssn, address and deliveryaddress)
 *      }
 *      var resursCheckout = ResursCheckout();
 *      resursCheckout.init();
 *
 * If you have a container not named resurs-checkout-container, you may initialize ResursJS by doing this:
 *
 *      var resursCheckout = ResursCheckout('#myResursElement');
 *
 *  Developers - Activate debugging:
 *
 *      resursCheckout.setDebug(true);
 */

if (typeof ResursCheckout !== "function" && typeof ResursCheckout === "undefined") {
    function ResursCheckout() {
        var currentResursEventNamePrefix = "checkout";
        var resursCheckoutElement = "";     // defined element for where Resurs Checkout iframe is (or should be) located
        var resursCheckoutFrame = "";
        var resursCheckoutVersion = "0.10";
        var resursCheckoutData = {"paymentMethod": "", "customerData": {}};
        var resursCheckoutDebug = false;
        var resursCheckoutBookingCallback = null;
        var resursCheckoutPurchaseFail = null;
        var resursCheckoutPurchaseDenied = null;
        var resursCheckoutCustomerChange = null;
        var resursCheckoutIframeReady = null;
        var resursPostMessageElement = null;
        // If there is an argument, there is something else implemented as the Resurs Checkout-container than the default.
        if (typeof arguments[0] !== "undefined") {
            resursCheckoutElement = arguments[0];   // Accepting user defined elements
        } else {
            resursCheckoutElement = "resurs-checkout-container";    // If there are no user defined element, fall back to default
        }
        // Backwarding to wider browser compatibility as developers might have already been starting to use #elementIdNames instead of elementIdNames
        if (resursCheckoutElement.substr(0, 1) === "#") {
            resursCheckoutElement = resursCheckoutElement.substr(1);
        }

        /*
         * Developer note: querySelector can do the same here, but has limited compatibility down to IE8. As we try to be
         * as browser friendly as possible, we also try to use as browser wide functions as possible.
         */

        // Find out whether our specified element exists or not and, if not, try to fall back to the prior name
        if (null === document.getElementById(resursCheckoutElement) && null !== document.getElementById('omni-checkout-container')) {
            resursCheckoutElement = "omni-checkout-container";
            if (resursCheckoutDebug) {
                console.log("ResursCheckoutJS: [Config] Former element of Resurs Checkout is present");
            }
        }

        // We should now have an element defined where an iframe for Resurs Bank should reside. This is where we prepare that element for
        // the postmessaging.
        if (null !== document.getElementById(resursCheckoutElement)) {
            resursPostMessageElement = document.getElementById(resursCheckoutElement);
            if (resursCheckoutDebug) {
                console.log("ResursCheckoutJS: [Config] iframe container is set to " + resursCheckoutElement);
            }
        }

        var resursCheckoutDomain = "";
        /*
         * If RESURSCHECKOUT_IFRAME_URL (RESURSCHECKOUT_IFRAME_URL for compatibility) is set, the script will know where the communication will be. Without this, there may be problems.
         */
        if (typeof RESURSCHECKOUT_IFRAME_URL !== "undefined") {
            resursCheckoutDomain = RESURSCHECKOUT_IFRAME_URL;
        }
        if (typeof OMNICHECKOUT_IFRAME_URL !== "undefined") {
            resursCheckoutDomain = OMNICHECKOUT_IFRAME_URL;
        }
        /**
         * iFrame speech function.
         * @param data
         */
        var postMessage = function (data) {
            var resursCheckoutWindow;
            if (null !== resursPostMessageElement) {
                if (resursCheckoutDebug) {
                    console.log("ResursCheckoutJS: [PrePostMessage] Using resursPostMessageElement as destination");
                }
                resursCheckoutFrame = resursPostMessageElement.getElementsByTagName("iframe")[0];
                resursCheckoutWindow = resursCheckoutFrame.contentWindow || resursCheckoutFrame.contentDocument;
            } else {
                if (resursCheckoutDebug) {
                    console.log("ResursCheckoutJS: [PrePostMessage] Locating postMessage-element as resursPostMessageElement is missing");
                }
                resursCheckoutFrame = document.getElementsByTagName("iframe")[0];
                resursCheckoutWindow = resursCheckoutFrame.contentWindow || resursCheckoutFrame.contentDocument;
            }
            if (resursCheckoutWindow && typeof resursCheckoutWindow.postMessage === "function" && typeof resursCheckoutFrame.src !== "undefined" && typeof resursCheckoutDomain === 'string' && resursCheckoutDomain !== '' && resursCheckoutFrame.src.indexOf(resursCheckoutDomain) > -1) {
                if (resursCheckoutDebug) {
                    console.log("ResursCheckoutJS: [postMessage] " + JSON.stringify(data));
                }
                resursCheckoutWindow.postMessage(JSON.stringify(data), resursCheckoutDomain);
            } else {
                if (resursCheckoutDebug) {
                    console.log("ResursCheckoutJS: [postMessage] iframe window missing postMessage function");
                }
            }
        };
        var ResursCheckout = {
            /**
             * Global events callback setup
             * @param eventCallbackSet
             */
            setBookingCallback: function (eventCallbackSet) {
                resursCheckoutBookingCallback = eventCallbackSet;
            },
            setPurchaseFailCallback: function (eventCallbackSet) {
                resursCheckoutPurchaseFail = eventCallbackSet;
            },
            setPurchaseDeniedCallback: function (eventCallbackSet) {
                resursCheckoutPurchaseDenied = eventCallbackSet;
            },
            setCustomerChangedEventCallback: function (eventCallbackSet) {
                resursCheckoutCustomerChange = eventCallbackSet;
            },
            setOnIframeReady: function (eventCallbackSet) {
                resursCheckoutIframeReady = eventCallbackSet;
            },
            setDebug: function (activateDebug) {
                if (activateDebug == 1) {
                    activateDebug = true;
                }
                resursCheckoutDebug = activateDebug;
                console.log("ResursCheckoutJS: [Config] Verbosity level raised");
            },
            confirmOrder: function (successfulness) {
                if (typeof successfulness !== "boolean") {
                    successfulness = false;
                }
                if (resursCheckoutDebug) {
                    console.log("ResursCheckoutJS: [Outbound] Confirm order with boolean: " + successfulness);
                }
                postMessage({
                    eventType: currentResursEventNamePrefix + ":order-status",
                    orderReady: successfulness
                });
            },
            postFrame: function (postMessageData) {
                if (typeof postMessageData === "object") {
                    if (resursCheckoutDebug) {
                        console.log("ResursCheckoutJS: [Outbound] Sending message to iframe: " + JSON.stringify(postMessageData));
                    }
                    postMessage(postMessageData);
                }
            },
            init: function () {
                window.addEventListener('message', function (event) {
                    var origin = event.origin || event.originalEvent.origin;
                    /* Validate origin and do nothing if resursCheckoutDomain is missing */
                    if (origin !== resursCheckoutDomain || typeof event.data !== 'string') {
                        return;
                    }
                    var eventDataReceive = event.data;
                    var eventDataObject = {};
                    // ignore anything that is not JSON
                    try {
                        eventDataObject = JSON.parse(eventDataReceive);
                    } catch (e) {
                        return;
                    }
                    if (eventDataObject.hasOwnProperty('eventType') && typeof eventDataObject.eventType === 'string') {
                        switch (eventDataObject.eventType) {
                            case currentResursEventNamePrefix + ":loaded":
                                postMessage({
                                    eventType: currentResursEventNamePrefix + ":set-purchase-button-interceptor",
                                    checkOrderBeforeBooking: true
                                });
                                if (typeof resursCheckoutIframeReady === "function") {
                                    resursCheckoutIframeReady(resursCheckoutFrame);
                                }
                                break;
                            default:
                                /*
                                 * If there is a callback registered, for handling ResursCheckout from elsewhere, send it over.
                                 * In any other case, ignore all events except for the booking rule.
                                 */
                                if (eventDataObject.eventType.indexOf(currentResursEventNamePrefix) > -1) {
                                    if (eventDataObject.eventType == currentResursEventNamePrefix + ":payment-method-change") {
                                        if (resursCheckoutDebug) {
                                            console.log("ResursCheckoutJS: [Inbound] payment-method-change");
                                        }
                                        resursCheckoutData.paymentMethod = eventDataObject.method;
                                    } else if (eventDataObject.eventType == currentResursEventNamePrefix + ":user-info-change") {
                                        if (resursCheckoutDebug) {
                                            console.log("ResursCheckoutJS: [Inbound] user-info-change");
                                        }
                                        resursCheckoutData.customerData = {
                                            "address": (typeof eventDataObject.address !== "undefined" ? eventDataObject.address : {}),
                                            "delivery": (typeof eventDataObject.delivery !== "undefined" ? eventDataObject.delivery : {}),
                                            "ssn": eventDataObject.ssn,
                                            "paymentMethod": (typeof resursCheckoutData.paymentMethod !== "undefined" ? resursCheckoutData.paymentMethod : "")
                                        };
                                        if (typeof resursCheckoutCustomerChange === "function") {
                                            resursCheckoutCustomerChange(resursCheckoutData);
                                        }
                                    } else if (eventDataObject.eventType == currentResursEventNamePrefix + ":purchase-failed") {
                                        if (typeof resursCheckoutPurchaseFail === "function") {
                                            resursCheckoutPurchaseFail();
                                        } else if (typeof resursCheckoutPurchaseFail === "string") {
                                            if (typeof window[resursCheckoutPurchaseFail] === "function") {
                                                window[resursCheckoutPurchaseFail]();
                                            }
                                        }
                                    } else if (eventDataObject.eventType == currentResursEventNamePrefix + ":purchase-denied") {
                                        if (typeof resursCheckoutPurchaseDenied === "function") {
                                            resursCheckoutPurchaseDenied();
                                        } else if (typeof resursCheckoutPurchaseDenied === "string") {
                                            if (typeof window[resursCheckoutPurchaseDenied] === "function") {
                                                window[resursCheckoutPurchaseDenied]();
                                            }
                                        }
                                    } else if (eventDataObject.eventType == currentResursEventNamePrefix + ":puchase-button-clicked") {
                                        /*
                                         * Passing order booking to a user defined callback if exists.
                                         */
                                        if (typeof resursCheckoutBookingCallback === "function") {
                                            if (resursCheckoutDebug) {
                                                console.log("ResursCheckoutJS: [Outbound] puchase-button-clicked event received, bounce back through user defined callback.");
                                            }
                                            resursCheckoutBookingCallback(resursCheckoutData, eventDataObject);
                                        } else if (typeof resursCheckoutBookingCallback === "string") {
                                            if (typeof window[resursCheckoutBookingCallback] === "function") {
                                                window[resursCheckoutBookingCallback](resursCheckoutData, eventDataObject);
                                            }
                                        } else {
                                            /*
                                             * If no callbacks was found we'll consider that no callbacks are defined
                                             * in the shop. In that case, we'll just continue with the booking part.
                                             */
                                            if (resursCheckoutDebug) {
                                                console.log("ResursCheckoutJS: [Outbound] puchase-button-clicked event received and no user defined callbacks was found. Cosidering autoConfirm.");
                                            }
                                            postMessage({
                                                eventType: currentResursEventNamePrefix + ":order-status",
                                                orderReady: true
                                            });
                                        }
                                    }
                                }
                        }
                    }
                }, false);
            },
        };
        return ResursCheckout;
    }
}

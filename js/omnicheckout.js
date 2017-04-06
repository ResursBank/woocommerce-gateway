/*! ResursCheckoutJS v0.06 - Generic Reurs Bank iFrame-driver for Resurs Checkout, for catching events in the Resurs Checkout iFrame */

/*
 * CHANGELOG
 *
 * The changelog has moved to https://test.resurs.com/docs/x/5ABV
 *
 * Dependencies to external libraries:
 *
 *      - None.
 *
 * Requirements:
 *
 *      - RESURSCHECKOUT_IFRAME_URL (OMNICHECKOUT_IFRAME_URL) needs to be set from your store, to define where events are sent from.
 *      - Make sure that shopUrl is sent and matches with the target iFrame URL, when creating the iFrame at API level.
 *      - A html element that holds the iframe
 *
 * What this script won't do:
 *
 *      - Live updates of the shop cart (pushing data into the frame that occurs when the cart is changed). This a feature that should be completely backend based.
 *
 * The script is written so that you can put it on a webpage without having it primarily activated (to avoid colliding with other scripts).
 * It utilizes the message handler in the Resurs Checkout iframe, so that you can push an order into the store in the background, as the checkout is completed at Resurs Bank.
 *
 * Events that this module catches from Resurs Checkout:
 *
 *      checkout:loaded                 - Handled by this script when the iFrame has loaded and is ready
 *      checkout:user-info-change       - Stored until checkout is finished (Data passed to function resursCheckoutCustomerChange if set)
 *      checkout:payment-method-change  - Stored until checkout is finished
 *      checkout:booking-order          - Passed with necessary customer data to a callback, or dropped if no callback is set
 *
 *      As Resurs Checkout will rename events in short, please take a look at the variable currentResursEventNamePrefix, if you need to make a quickfix.
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
 *  Developers only - Activate debugging:
 *
 *      resursCheckout.setDebug(true);
 */

if (typeof ResursCheckout !== "function" && typeof ResursCheckout === "undefined") {
    var currentResursEventNamePrefix = "checkout";
    function ResursCheckout() {
        var resursCheckoutElement = "";
        var resursCheckoutFrame = "";
        var resursCheckoutVersion = "0.06";
        var resursCheckoutData = {"paymentMethod": "", "customerData": {}};
        var resursCheckoutDebug = false;
        var resursCheckoutBookingCallback = null;
        var resursCheckoutPurchaseFail = null;
        var resursCheckoutCustomerChange = null;
        /*
         * If there is an argument, there is something else implemented as the Resurs Checkout-container than the default.
         */
        if (typeof arguments[0] !== "undefined") {
            resursCheckoutElement = arguments[0];
        } else {
            resursCheckoutElement = "#resurs-checkout-container";
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
        var function_exists = function (functionName) {
            if (typeof functionName === "function") {
                return true;
            }
        };
        var postMessage = function (data) {
            var resursCheckoutWindow;
            /*
             * Find the current active iframe
             */
            resursCheckoutFrame = document.getElementsByTagName("iframe")[0];
            resursCheckoutWindow = resursCheckoutFrame.contentWindow || resursCheckoutFrame.contentDocument;
            if (resursCheckoutWindow && typeof resursCheckoutDomain === 'string' && resursCheckoutDomain !== '') {
                resursCheckoutWindow.postMessage(JSON.stringify(data), resursCheckoutDomain);
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
            setCustomerChangedEventCallback: function(eventCallbackSet) {
                resursCheckoutCustomerChange = eventCallbackSet;
            },
            setDebug: function (activateDebug) {
                if (activateDebug == 1) {
                    activateDebug = true;
                }
                resursCheckoutDebug = activateDebug;
                console.log("ResursCheckoutJS verbosity level raised");
            },
            confirmOrder: function (successfulness) {
                if (typeof successfulness !== "boolean") {
                    successfulness = false;
                }
                if (resursCheckoutDebug) {
                    console.log("ResursCheckoutJS - Confirm order with boolean: " + successfulness);
                }
                postMessage({
                    eventType: currentResursEventNamePrefix + ":order-status",
                    orderReady: successfulness
                });
            },
            postFrame: function (postMessageData) {
                if (typeof postMessageData === "object") {
                    if (resursCheckoutDebug) {
                        console.log("ResursCheckoutJS - Sending message to iframe: " + JSON.stringify(postMessageData));
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
                                break;
                            default:
                                /*
                                 * If there is a callback registered, for handling ResursCheckout from elsewhere, send it over.
                                 * In any other case, ignore all events except for the booking rule.
                                 */
                                if (eventDataObject.eventType.indexOf(currentResursEventNamePrefix) > -1) {
                                    if (eventDataObject.eventType == currentResursEventNamePrefix + ":payment-method-change") {
                                        if (resursCheckoutDebug) {
                                            console.log("ResursCheckoutJS - payment-method-change");
                                        }
                                        resursCheckoutData.paymentMethod = eventDataObject.method;
                                    } else if (eventDataObject.eventType == currentResursEventNamePrefix + ":user-info-change") {
                                        if (resursCheckoutDebug) {
                                            console.log("ResursCheckoutJS - user-info-change");
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
                                        } else if (resursCheckoutPurchaseFail === "string") {
                                            if (typeof window[resursCheckoutPurchaseFail] === "function") {
                                                window[resursCheckoutPurchaseFail]();
                                            }
                                        }
                                    } else if (eventDataObject.eventType == currentResursEventNamePrefix + ":puchase-button-clicked") {
                                        /*
                                         * Passing order booking to a user defined callback if exists.
                                         */
                                        if (typeof resursCheckoutBookingCallback === "function") {
                                            if (resursCheckoutDebug) {
                                                console.log("ResursCheckoutJS - puchase-button-clicked event received, user defined callback is used.");
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
                                                console.log("ResursCheckoutJS - puchase-button-clicked event received and not callbacks was found. Automatically confirming.");
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

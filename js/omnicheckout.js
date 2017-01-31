/*! OmniJS v0.04 - Generic iFrame-driver for Resurs Bank OmniCheckout, for catching events in the Omnicheckout iFrame */

/*
 * CHANGELOG
 *
 * 0.04
 *      - Event for purchase-failed added as callbacks
 *      - Event for booked payment updated
 *
 * 0.03
 *		- Events that fails for IE/Firefox fixed
 *
 * 0.02
 *      - Renaming of events
 *      - Add paymentMethod into the customerData object so that customization of the real method follow the flow
 *
 * Dependencies:
 *
 *      - None.
 *
 * Requirements:
 *
 *      - OMNICHECKOUT_IFRAME_URL needs to be set from your store, to define where events are sent from.
 *      - Make sure that shopUrl is sent and matches with the target iFrame URL, when creating the iFrame at API level.
 *      - A html element that holds the iframe
 *
 * Currently unsupported:
 *
 *      - Live updates of the shop cart (pushing data into the frame that occurs when the cart is changed)
 *
 * The script is written so that you can put it on a webpage without having it primarily activated.
 * It utilizes the message handler in the Omni iFrame, so that you can push an order into the store in the background,
 * as the checkout is completed at Resurs Bank.
 *
 * Events to catch from Omni:
 *
 *      omnicheckout:loaded                 - Handled by this script when the iFrame has loaded and is ready
 *      omnicheckout:user-info-change       - Stored until checkout is finished
 *      omnicheckout:payment-method-change  - Stored until checkout is finished
 *      omnicheckout:booking-order          - Passed with necessary customer data to a callback, or dropped if no callback is set
 *
 * Usage example:
 *
 *      // User configured callback: Handle the booking event in the shop (if this function not exists, OmniJS will automatically confirm the order)
 *      function omniBookingCallback(omniJsObject) {
 *          // Put your code to handle the customer order here (omniJsObject contains at most ssn, address and deliveryaddress)
 *      }
 *      var omniCheckout = ResursOmni();
 *      omniCheckout.init();
 *
 * If you have a container not named omni-checkout-container, you may initialize OmniJS by doing this:
 *
 *      var omniCheckout = ResursOmni('#myOmniElement');
 *
 *  Developers only - Activate debugging:
 *
 *      omniCheckout.setDebug(true);
 */

if (typeof ResursOmni !== "function" && typeof ResursOmni === "undefined") {
    function ResursOmni() {
        var omniElement = "";
        var omniFrame = "";
        var omniJSVersion = "0.04";
        var omnidata = {"paymentMethod":"", "customerData":{}};
        var omniDebug = false;
        var omniBookingCallback = null;
        var omniPurchaseFail = null;
        /*
         * If there is an argument, there is something else implemented as the omnicheckout container than the default.
         */
        if (typeof arguments[0] !== "undefined") { omniElement = arguments[0]; } else { omniElement = "#omni-checkout-container"; }
        var omnicheckoutDomain = "";
        /*
         * If OMNICHECKOUT_IFRAME_URL is set, the script will know where the communication will be. Without this, there may be problems.
         */
        if (typeof OMNICHECKOUT_IFRAME_URL !== "undefined") { omnicheckoutDomain = OMNICHECKOUT_IFRAME_URL; }
        var function_exists = function(functionName) {
            if (typeof functionName === "function") {
                return true;
            }
        };
        var postMessage = function (data) {
            var omniWindow;
            /*
             * Find the current active iframe
             */
            omniFrame = document.getElementsByTagName("iframe")[0];
            omniWindow = omniFrame.contentWindow || omniFrame.contentDocument;
            if (omniWindow && typeof omnicheckoutDomain === 'string' && omnicheckoutDomain !== '') {
                omniWindow.postMessage(JSON.stringify(data), omnicheckoutDomain);
            }
        };
        var ResursOmni = {
            /**
             * Global events callback setup
             * @param eventCallbackSet
             */
            setBookingCallback: function(eventCallbackSet) {
                omniBookingCallback = eventCallbackSet;
            },
            setPurchaseFailCallback: function(eventCallbackSet) {
                omniPurchaseFail = eventCallbackSet;
            },
            setDebug: function (activateDebug) {
                if (activateDebug == 1) {
                    activateDebug = true;
                }
                omniDebug = activateDebug;
                console.log("OmniJS verbosity level raised");
            },
            confirmOrder: function(successfulness) {
                if (typeof successfulness !== "boolean") {
                    successfulness = false;
                }
                if (omniDebug) {
                    console.log("OmniJS - Confirm order with boolean: " + successfulness);
                }
                postMessage({
                    eventType: 'omnicheckout:order-status',
                    orderReady: successfulness
                });
            },
            postFrame: function(postMessageData) {
                if (typeof postMessageData === "object") {
                    if (omniDebug) {
                        console.log("OmniJS - Sending message to iframe: " + JSON.stringify(postMessageData));
                    }
                    postMessage(postMessageData);
                }
            },
            init: function() {
                window.addEventListener('message', function(event) {
                    var origin = event.origin || event.originalEvent.origin;
                    /* Validate origin and do nothing if omnicheckoutDomain is missing */
                    if (origin !== omnicheckoutDomain || typeof event.data !== 'string') {
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
                            case 'omnicheckout:loaded':
                                postMessage({
                                    eventType: 'omnicheckout:set-purchase-button-interceptor',
                                    checkOrderBeforeBooking: true
                                });
                                break;
                            default:
                                /*
                                 * If there is a callback registered, for handling omnicheckout from elsewhere, send it over.
                                 * In any other case, ignore all events except for the booking rule.
                                 */
                                if (eventDataObject.eventType.indexOf("omnicheckout") > -1) {
                                    if (eventDataObject.eventType == "omnicheckout:payment-method-change") {
                                        if (omniDebug) { console.log("OmniJS - payment-method-change"); }
                                        omnidata.paymentMethod = eventDataObject.method;
                                    } else if (eventDataObject.eventType == "omnicheckout:user-info-change") {
                                        if (omniDebug) {
                                            console.log("OmniJS - user-info-change");
                                        }
                                        omnidata.customerData = {
                                            "address": (typeof eventDataObject.address !== "undefined" ? eventDataObject.address : {}),
                                            "delivery": (typeof eventDataObject.delivery !== "undefined" ? eventDataObject.delivery : {}),
                                            "ssn": eventDataObject.ssn,
                                            "paymentMethod": (typeof omnidata.paymentMethod !== "undefined" ? omnidata.paymentMethod : "")
                                        };
                                    } else if (eventDataObject.eventType == "omnicheckout:purchase-failed") {
                                        if (typeof omniPurchaseFail === "function") {
                                            omniPurchaseFail();
                                        } else if (omniPurchaseFail === "string") {
                                            if (typeof window[omniPurchaseFail] === "function") {
                                                window[omniPurchaseFail]();
                                            }
                                        }
                                    } else if (eventDataObject.eventType == "omnicheckout:puchase-button-clicked") {
                                        /*
                                         * Passing order booking to a user defined callback if exists.
                                         */
                                        if (typeof omniBookingCallback === "function") {
                                            if (omniDebug) {
                                                console.log("OmniJS - puchase-button-clicked event received, user defined callback is used.");
                                            }
                                            omniBookingCallback(omnidata, eventDataObject);
                                        } else if (typeof omniBookingCallback === "string") {
                                            if (typeof window[omniBookingCallback] === "function") {
                                                window[omniBookingCallback](omnidata, eventDataObject);
                                            }
                                        } else if (typeof omniBookEvent === "function") {
                                            if (omniDebug) {console.log("puchase-button-clicked event received, default callback is used.");}
                                            omniBookEvent(omnidata, eventDataObject);
                                        } else {
                                            /*
                                             * If no callbacks was found we'll consider that no callbacks are defined
                                             * in the shop. In that case, we'll just continue with the booking part.
                                             */
                                            if (omniDebug) { console.log("OmniJS - puchase-button-clicked event received and not callbacks was found. Automatically confirming."); }
                                            postMessage({
                                                eventType: 'omnicheckout:order-status',
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
        return ResursOmni;
    }
}

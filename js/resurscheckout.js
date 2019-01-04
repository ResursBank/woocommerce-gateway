/**
 * Resurs Checkout JS v1.0.0
 */
window._resursCheckout = (function() {

    var _settings = {
        debug: false,
        container: 'resurs-checkout-container',
        domain: null,
        interceptor: true,
        on: {
            loaded: null,
            booked: null,
            fail: null,
            change: null
        }
    };
    var _container;
    var _frame;
    var _contentWindow;
    var _eventTypes = null;
    var _callbacks = {
        loaded: [],
        booked: [],
        fail: [],
        change: []
    };
    var _frameData = {};


    document.addEventListener('DOMContentLoaded', function(event) {
        _eventTypes = {
            'checkout:loaded': _handleLoadedEvent,
            'checkout:user-info-change': _handleUserInfoChangeEvent,
            'checkout:payment-method-change': _handlePaymentMethodChangeEvent,
            'checkout:puchase-button-clicked': _handlePurchaseButtonClickEvent,
            'checkout:purchase-failed': _handlePurchaseFailEvent
        }

        _setup();
    });

    var init = function(settings) {
        // Merge default settings with the user provided settings
        _extend(_settings, settings);
        // Push the callbacks to the _callbacks array so events can be stacked
        for (var event in _settings.on) {
            if (typeof _settings.on[event] === "function" && _callbacks.hasOwnProperty(event)) {
                _callbacks[event].push(_settings.on[event]);
            }
        }
    }

    var on = function(event, callback) {
        if (!_callbacks.hasOwnProperty(event)) {
            console.error(event + ' is not supported.');
            return;
        }

        // Stack the events
        _callbacks[event].push(callback);
    }

    var _extend = function(a, b) {
        for (var key in b) {
            if (b[key] === Object(b[key]) && typeof b[key] !== 'function') {
                _extend(a[key], b[key]);
            } else {
                a[key] = b[key];
            }
        }
    }

    var _setup = function() {
        _container = document.getElementById(_settings.container);
        if (!_container) {
            return;
        }

        _frame = _container.getElementsByTagName('iframe')[0];
        if (!_frame) {
            return;
        }

        _contentWindow = _frame.contentWindow || _frame.contentDocument;

        if (typeof RESURSCHECKOUT_IFRAME_URL !== 'undefined') {
            _settings.domain = RESURSCHECKOUT_IFRAME_URL;
        }

        if (!_settings.domain) {
            console.error('You need to set a domain for the iframe communication to work.');
        }

        window.addEventListener('message', _handlePostMessages);
    }

    var _postMessage = function(data) {
        if (typeof _contentWindow.postMessage !== "function") {
            console.error("The iframe element is not supporting postMessage.");
        }

        if (_frame.src.indexOf(_settings.domain) === -1) {
            console.error('The domain set for ResursCheckoutJS must be the same as that of the iframe.');
        }

        _contentWindow.postMessage(JSON.stringify(data), _settings.domain);
    }

    var _handlePostMessages = function(event) {
        // Don't do anything if it doesn't come from a trusted source.
        if (event.origin !== _settings.domain || typeof event.data !== 'string') {
            return;
        }

        try {
            var eventData = JSON.parse(event.data);
        } catch (e) {
            return;
        }

        // Call the handler for this event.
        if (eventData.hasOwnProperty('eventType') && _eventTypes[eventData.eventType]) {
            _eventTypes[eventData.eventType](eventData);
        }
    }

    var _handleLoadedEvent = function(data) {
        if (_settings.interceptor) {
            _postMessage({
                eventType: "checkout:set-purchase-button-interceptor",
                checkOrderBeforeBooking: true
            });
        }

        _runEventCallback('loaded', {});
    }

    var _handleUserInfoChangeEvent = function(data) {
        _frameData = {
            address: data.address || {},
            delivery: data.delivery || {},
            ssn: data.ssn || '',
            paymentMethod: data.paymentMethod || ''
        };

        _runEventCallback('change', _frameData);
    }

    var _handlePaymentMethodChangeEvent = function(data) {
        _frameData.paymentMethod = data.method || '';

        _runEventCallback('change', _frameData);
    }

    var _handlePurchaseButtonClickEvent = function(data) {

        _runEventCallback('booked', {
            rcoData: _frameData,
            confirm: function(confirm) {
                _postMessage({
                    eventType: 'checkout:order-status',
                    orderReady: confirm
                });
            }
        })
    }

    var _handlePurchaseFailEvent = function(data) {

        _runEventCallback('fail', {});
    }

    var _runEventCallback = function(type, params) {
        // Running all stacked events
        for (var i = 0; i < _callbacks[type].length; i++) {
            _callbacks[type][i](params);
        }
    }

    return {
        init: init,
        on: on,
        data: _frameData
    }

})();
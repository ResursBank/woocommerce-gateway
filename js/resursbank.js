var $resurs_bank = jQuery.noConflict();

$resurs_bank(document).ready(function ($) {
    resursBankLoaded();
});
$resurs_bank(document).on('updated_checkout', function () {
    resursBankCheckUpdates();
});

/**
 * Translation object between RCO and WooCommerce default fields.
 *
 * @type {{firstname: string, surname: string, address: string, addressExtra: string, postal: string, city: string, countryCode: string, telephone: string, email: string}}
 */
var resursCheckoutAddressObject = {
    "firstname": "first_name",
    "surname": "last_name",
    "address": "address_1",
    "addressExtra": "address_2",
    "postal": "postcode",
    "city": "city",
    "countryCode": "country",
    "telephone": "phone",
    "email": "email"
};

/**
 * Ajaxify element dismissals
 *
 * @param element
 */
function resurs_bank_dismiss(element) {
    $resurs_bank(element).html(getResursBankSpinner('dismiss_image_' + element));
    resurs_bank_ajaxify('dismiss_element', {'element': element}, function (response, postdata) {
        if (typeof response['responseAdmin']['dismissed'] !== 'undefined') {
            $resurs_bank('#' + response['responseAdmin']['dismissed']).remove();
        }
        if (typeof response['response']['dismissed'] !== 'undefined') {
            $resurs_bank('#' + response['response']['dismissed']).remove();
        }
    });
}

function resurs_bank_ajaxify(action, postdata, runFunction) {
    $resurs_bank.ajax(
        {
            url: resurs_bank_payment_gateway['backend_nonce'] + "&run=" + action,
            method: 'POST',
            data: postdata
        }
    ).success(function (response) {
        if (typeof runFunction === 'function') {
            runFunction(response, postdata);
        }
    });
}

function resursBankCheckUpdates() {
    // Form field inheritage.
    if ($resurs_bank('#resursbankcustom_getaddress_governmentid').length === 1) {
        $resurs_bank("input[id*='resursbankcustom_government_id_']").filter(':visible').val($resurs_bank('#resursbankcustom_getaddress_governmentid').val());
    }
}

function resursBankFormFieldChange(o) {
    if (o.id.indexOf('resursbankcustom_government_id_') > -1) {
        $resurs_bank('#resursbankcustom_getaddress_governmentid').val(o.value);
    }
}

function resursBankHandleCheckoutForms() {
    $resurs_bank('.woocommerce-billing-fields').hide();
    $resurs_bank('.woocommerce-shipping-fields').hide();
}

/**
 * getCostOfPurchaseHtml
 * @param id
 * @param total
 */
function getResursCostOfPurchase(id, total) {
    window.open(
        resurs_bank_payment_gateway['getCostOfPurchaseBackendUrl'] + "&method=" + id + "&amount=" + total,
        'resursCostOfPurchasePopup',
        'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px'
    );
}

var releaseRco;

function setResursCheckoutInterceptor() {
    // In interceptor mode, we hook into a global ajax hook to look after checkout responses, as redirecting
    // will be anchor based, to prevent the checkout to redirect itself somewhere.
    $resurs_bank(document).ajaxSuccess(function (event, xhr, settings) {
        if (typeof xhr.responseJSON !== 'undefined') {
            var processOrderResponse = xhr.responseJSON;
            // Go when everything is ok.
            if (typeof processOrderResponse['result'] !== 'undefined' && typeof releaseRco !== 'undefined') {
                if (processOrderResponse['result'] === 'success') {
                    releaseRco.confirm(true);
                }
            }
        }
    });

    if (typeof _resursCheckout !== 'undefined') {
        _resursCheckout.init({
            debug: false,
            container: 'resurs-checkout-container',
            domain: resurs_bank_payment_gateway['iframeUrl'],
            interceptor: true,
            on: {
                loaded: null,
                booked: function (data) {
                    releaseRco = data;
                    $resurs_bank('#place_order').click();
                },
                fail: function () {

                },
                change: function (changeDataObject) {
                    getResursCheckoutFields(changeDataObject);
                }
            }
        });
    }
}

function setResursCheckoutFields(addressObject, addressType) {
    var currentMatchingKey;
    for (var addrKey in addressObject) {
        if (typeof resursCheckoutAddressObject[addrKey] !== 'undefined') {
            currentMatchingKey = resursCheckoutAddressObject[addrKey];
            if (addressObject[addrKey] !== '' && $resurs_bank('#currentMatchingKey').length > -1) {
                $resurs_bank('#' + addressType + '_' + currentMatchingKey).val(addressObject[addrKey]);
            }
        }
    }
}

function getWoocommerceShippingCheckboxByResurs(hasShipping) {
    if ($resurs_bank('#ship-to-different-address-checkbox').length > 0) {
        var shippingAddressBox = $resurs_bank('#ship-to-different-address-checkbox')[0];
        if (!shippingAddressBox.checked && hasShipping) {
            shippingAddressBox.click();
        }
        if (shippingAddressBox.checked && !hasShipping) {
            shippingAddressBox.click();
        }
    }
}

function getResursCheckoutFields(changeDataObject) {
    var paymentMethod = changeDataObject['paymentMethod'];
    if (typeof changeDataObject['address'] !== 'undefined') {
        setResursCheckoutFields(changeDataObject['address'], 'billing');
    }
    if (typeof changeDataObject['delivery'] !== 'undefined' && typeof changeDataObject['delivery']['firstname'] !== 'undefined') {
        getWoocommerceShippingCheckboxByResurs(true);
        setResursCheckoutFields(changeDataObject['delivery'], 'shipping');
    } else {
        getWoocommerceShippingCheckboxByResurs(false);
    }
}

function resursBankLoaded() {
    $resurs_bank('#resursbankcustom_getaddress_governmentid').on('keyup', function (a) {
        var currentFormField = $resurs_bank("input[id*='resursbankcustom_government_id_']").filter(':visible');
        currentFormField.val(this.value);
    });

    setResursCheckoutInterceptor();

    if (typeof resursBankWooInitialize === 'function') {
        resursBankWooInitialize();
    }
    if (resurs_bank_payment_gateway['suggested_flow'] === 'checkout') {
        resursBankHandleCheckoutForms();
    }

}


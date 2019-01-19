var $resurs_bank = jQuery.noConflict();

$resurs_bank(document).ready(function ($) {
    resursBankLoaded();
});
$resurs_bank(document).on('updated_checkout', function () {
    resursBankCheckUpdates();
});

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

function resursBankLoaded() {
    $resurs_bank('#resursbankcustom_getaddress_governmentid').on('keyup', function (a) {
        var currentFormField = $resurs_bank("input[id*='resursbankcustom_government_id_']").filter(':visible');
        currentFormField.val(this.value);
    });
    if (typeof resursBankWooInitialize === 'function') {
        resursBankWooInitialize();
    }
    if (resurs_bank_payment_gateway['suggested_flow'] === 'checkout') {
        resursBankHandleCheckoutForms();
    }
}

$resurs_bank = jQuery.noConflict();

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
    resurs_bank_ajaxify('dismiss_element', {'element': element}, function (response, postdata) {
        if (typeof response['dismissed'] !== 'undefined') {
            $resurs_bank('#' + response['dismissed']).remove();
        }
    });
}

function resurs_bank_ajaxify(action, postdata, runFunction) {
    $resurs_bank.ajax({
            url: resurs_bank_payment_gateway['backend_nonce'] + "&run=" + action,
            method: 'POST',
            data: postdata
        }
    ).success(function (response, textStatus) {
        if (typeof runFunction === 'function') {
            runFunction(response, postdata);
        }
    });
}

function resursBankCheckUpdates() {
    console.log('Resurs Bank has discovered checkout updates.');
}

function resursBankFormFieldChange(o) {

}


function resursBankLoaded() {
    if (typeof resursBankWooInitialize === 'function') {
        resursBankWooInitialize();
    }
}


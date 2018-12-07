$resurs_bank = jQuery.noConflict();

/**
 * Ajaxify element dismissals
 *
 * @param element
 */
function resurs_bank_dismiss(element) {
    console.log("Find and kill " + element);
    $resurs_bank(element).hide('medium');
}

function resurs_bank_ajaxify() {
    $resurs_bank.ajax({
            url: resurs_bank_payment_gateway['backend_nonce']
        }
    );
}
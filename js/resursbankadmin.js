/*!
 * Resurs Bank AdminPanel
 */

window.onload = function () {
    jQuery(document).ready(function ($) {
        jQuery('#woocommerce_resurs-bank_registerCallbacksButton').val(rb_buttons.registerCallbacksButton);
        jQuery('#woocommerce_resurs-bank_refreshPaymentMethods').val(rb_buttons.refreshPaymentMethods);
        jQuery('select[id*="finalizeIfBooked"]').bind("change", function () {
                if (this.value === "true") {
                    var doUpdateMethods = confirm('This requires that callbacks are up to date. Do you want to fix this now?');
                    if (doUpdateMethods) {
                        jQuery('input[id*="registerCallbacksButton"]').click();
                    }
                }
            }
        );


        if (jQuery('#paymentMethodName').length > 0) {
            var methodName = jQuery('#paymentMethodName').html();
            var iconFieldName = "#woocommerce_" + methodName + "_icon";
            var iconField = jQuery(iconFieldName);
            if (iconField.length > 0) {
                iconField.after('<br><img src="' + iconField.val() + '">');
            }
        }


        var $el, $ps, $up, totalHeight;
        jQuery(".resurs-read-more-box .button").click(function () {
            jQuery('.resurs-read-more-box')
                .css({
                    // Set height to prevent instant jumpdown when max height is removed
                    "height": jQuery('#resursInfo').height,
                    "max-height": 9999
                }).animate({
                "height": jQuery('#resursInfo').height
            });

            // fade out read-more
            jQuery('#resursInfoButton').fadeOut();

            // prevent jump-down
            return false;

        });
    });
}

var fullFlowCollection = [];
var currentFlowCollection = [];
var flowRules = {
    "se": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
    "dk": ["resurs_bank_hosted"],
    "no": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
    "fi": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
};
function adminResursChangeFlowByCountry(o) {
    var country = o.value.toLowerCase();
    var ruleList = flowRules[country];
    if (fullFlowCollection.length == 0) {
        if (typeof flowRules[country] !== "undefined") {
            jQuery('#woocommerce_resurs-bank_flowtype option').each(function (i, e) {
                fullFlowCollection.push(e);
            });
        }
    }
    if (fullFlowCollection.length > 0) {
        jQuery('#woocommerce_resurs-bank_flowtype').empty();
        jQuery(fullFlowCollection).each(function (i, e) {
            var opVal = e.value.toLowerCase();
            if (jQuery.inArray(opVal, ruleList) > -1) {
                jQuery('#woocommerce_resurs-bank_flowtype').append(e);
            }
        });
    }
}
/*!
 * Resurs Bank AdminPanel
 */

window.onload = function() {
    jQuery(document).ready(function( $ ) {
            jQuery('#woocommerce_resurs-bank_registerCallbacksButton').val(rb_buttons.registerCallbacksButton);
            jQuery('#woocommerce_resurs-bank_refreshPaymentMethods').val(rb_buttons.refreshPaymentMethods);
            jQuery('select[id*="finalizeIfBooked"]').bind("change", function() {
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
                iconField.after('<br><img src="'+iconField.val()+'">');
            }
        }


        var $el, $ps, $up, totalHeight;
        jQuery(".resurs-read-more-box .button").click(function() {
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

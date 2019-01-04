/**!
 * Woocommerce initializator rewrite.
 */

function resursBankWooInitialize() {
    woocommerce_resurs_bank_gateway = {
        init: function() {
            var that = this;
            //this.register_payment_update();
            // register updates on initialization
            $resurs_bank(document).ajaxStop(function () {
                that.register_payment_update();
            });
        },
        register_payment_update: function() {
            $resurs_bank('input[name="payment_method"]').on('change', function () {
                $resurs_bank('body').trigger('update_checkout');
            });
        }
    };
    woocommerce_resurs_bank_gateway.init();
}
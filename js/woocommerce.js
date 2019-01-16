/**!
 * Woocommerce initializator rewrite.
 */

function resursBankWooInitialize() {
    woocommerce_resurs_bank_gateway = {
        init: function () {
            var that = this;
            //this.register_payment_update();
            // register updates on initialization
            $resurs_bank(document).ajaxStop(function () {
                that.register_payment_update();
            });
        },
        register_payment_update: function () {
            // Trigger updates on customer type in get address.
            $resurs_bank('input[name*="resursbankcustom_getaddress_customertype"]').on('change', function () {
                $resurs_bank('body').trigger('update_checkout');
            });

            $resurs_bank('#billing_company').on('change', function () {
                $resurs_bank('body').trigger('update_checkout');
            });

            // Trigger updates on payment method changes.
            $resurs_bank('input[name="payment_method"]').on('change', function () {
                $resurs_bank('body').trigger('update_checkout');
            });
        }
    };
    woocommerce_resurs_bank_gateway.init();
}
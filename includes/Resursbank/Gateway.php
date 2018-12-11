<?php

// Gateway related files, should be written to not conflict with neighbourhood.

if (!defined('ABSPATH')) {
    exit;
}

function resursbank_payment_gateway_initialize()
{
    // Make sure this gateway is not already there
    if (class_exists('WC_Resursbank_Gateway')) {
        return;
    }

    // Make sure WooCommerce is there.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Class Resursbank_Gateway
     */
    class WC_Gateway_ResursBank extends WC_Payment_Gateway
    {

        public function resurs_bank_checkout_fields()
        {
            echo "Y";
        }

        /**
         * Resursbank_Gateway constructor.
         */
        function __construct()
        {
            add_filter('woocommerce_checkout_fields', array($this, 'resurs_bank_checkout_fields'));
        }

    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}

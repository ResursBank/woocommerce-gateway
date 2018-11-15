<?php

// Gateway related files, should be written to not conflict with neighbourhood.

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
        /**
         * Resursbank_Gateway constructor.
         */
        function __construct()
        {
        }

    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}

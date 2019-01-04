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
        protected $RB;

        /**
         * Resursbank_Gateway constructor.
         */
        function __construct()
        {
            $this->setup();
        }

        private function setup()
        {
            $this->id = 'resurs_bank_payment_gateway';
            $this->title = 'Resurs Bank Payment Gateway';
            $this->method_description = __(
                'Complete payment solution for Resurs Bank, with support for multiple countries.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );
        }
    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}



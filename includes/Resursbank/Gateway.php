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
        protected $CORE;
        protected $RB;
        protected $METHOD;
        protected $CHECKOUT;

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

        /**
         * Is this method enabled?
         *
         * @return bool
         * @TODO Configurable option on flows not matching with RCO
         */
        protected function getEnabled()
        {
            return true;
        }

        protected function getPaymentFormFields()
        {
            $resursPaymentFormField = '';

            // Former version: onkeyup was used (for inherit fields?)

            // TODO: is company control (match method with company and if customer has chosen company)
            // TODO: company_government_id
            // TODO: contact_government_id

            if ($this->FLOW === \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                $resursPaymentFormField .= apply_filters('resursbank_paymentform_government_id', null);
                if ($this->METHOD->type === 'CARD') {
                    $resursPaymentFormField .= apply_filters('resursbank_paymentform_card', null);
                }
            }

            // If method is befintligt kort, do not run this.
            if ($this->METHOD->specificType !== 'CARD') {
                $resursPaymentFormField .= apply_filters('resursbank_paymentform_read_more', null);
            }

            return $resursPaymentFormField;
        }

        public function is_available()
        {
            return $this->getEnabled();
        }
    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}



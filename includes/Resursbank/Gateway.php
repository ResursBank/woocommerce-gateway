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
                'resurs-bank-payment-gateway-for-woocommerce'
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

        private function getPaymentFormFieldHtml($filterName, $PAYMENT_METHOD)
        {
            $hasFilteredCustomHtml = apply_filters(
                'resursbank_get_customer_field_html_' . $filterName,
                '',
                $PAYMENT_METHOD);
            if (empty($hasFilteredCustomHtml)) {
                $hasFilteredCustomHtml = apply_filters(
                    'resursbank_get_customer_field_html_generic',
                    '',
                    $PAYMENT_METHOD,
                    $filterName
                );
            }

            return $hasFilteredCustomHtml;
        }

        protected function getPaymentFormFields($PAYMENT_METHOD)
        {
            $resursPaymentFormField = '';

            // Former version: onkeyup was used (for inherit fields?)

            // TODO: is company control (match method with company and if customer has chosen company)
            // TODO: company_government_id
            // TODO: contact_government_id

            $postData = Resursbank_Core::getPostData();

            if ($this->FLOW === \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {

                $isLegal = false;
                if (in_array('LEGAL', (array)$PAYMENT_METHOD->customerType)) {
                    if (Resursbank_Core::getIsLegal()) {
                        $isLegal = true;
                    }
                }

                $formFieldHtml['applicant_natural_government_id'] = $this->getPaymentFormFieldHtml(
                    'government_id',
                    $PAYMENT_METHOD
                );

                if ($isLegal) {
                    $formFieldHtml['contact_government_id'] = $this->getPaymentFormFieldHtml(
                        'contact_government_id',
                        $PAYMENT_METHOD
                    );
                }

                // Card requirements - government id + card number
                if ($this->METHOD->type === 'CARD') {
                    $formFieldHtml['applicant_natural_card'] = $this->getPaymentFormFieldHtml('card', $PAYMENT_METHOD);
                } else {

                    // Natural cases, globally - gov, phone, mobile, email
                    $formFieldHtml['applicant_natural_phone'] = $this->getPaymentFormFieldHtml(
                        'applicant_phone',
                        $PAYMENT_METHOD
                    );
                    $formFieldHtml['applicant_natural_mobile'] = $this->getPaymentFormFieldHtml(
                        'applicant_mobile',
                        $PAYMENT_METHOD
                    );
                    $formFieldHtml['applicant_natural_email'] = $this->getPaymentFormFieldHtml(
                        'applicant_email',
                        $PAYMENT_METHOD
                    );

                }
            }

            // If method is befintligt kort, do not run this.
            if ($this->METHOD->specificType !== 'CARD') {
                $formFieldHtml['readmore'] = apply_filters(
                    'resursbank_get_customer_field_html_read_more',
                    '',
                    $PAYMENT_METHOD
                );
            }

            $resursPaymentFormField = implode("\n", $formFieldHtml);

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



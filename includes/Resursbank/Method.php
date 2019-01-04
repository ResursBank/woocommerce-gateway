<?php

if (!defined('ABSPATH')) {
    exit;
}

use \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;

if (!class_exists('WC_Resursbank_Method') && class_exists('WC_Gateway_ResursBank')) {
    /**
     * Generic Method Class for Resurs Bank
     * Class WC_Resursbank_Method
     */
    class WC_Resursbank_Method extends WC_Gateway_ResursBank
    {
        public $title;

        protected $METHOD;
        protected $METHOD_TYPE;
        protected $CORE;
        protected $FLOW;
        protected $RESURSBANK;

        /**
         * WC_Resursbank_Method constructor.
         *
         * @param $paymentMethod Object or string
         * @param $country
         * @param $connection \Resursbank\RBEcomPHP\ResursBank
         * @throws Exception
         */
        function __construct($paymentMethod, $country, $connection)
        {
            $this->getActions();

            $this->CORE = new Resursbank_Core();
            $this->FLOW = $this->CORE->getFlowByEcom($this->CORE->getFlowByCountry($country));
            $this->RESURSBANK = $connection;

            // id, description, title
            if (is_object($paymentMethod)) {
                $this->METHOD = $paymentMethod;
                // Use resursbank_ instead of resurs_bank to avoid conflicts with prior versions.
                $this->id = 'resursbank_' . $paymentMethod->id;
                $this->title = $paymentMethod->description;
            } else {
                // Validate flow.
                if ($paymentMethod !== $this->FLOW) {
                    throw new Exception(
                        __('Payment method name and flow mismatch.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        400
                    );
                }
            }

            $this->createFlow($this->FLOW);
        }

        /**
         * When RCO, disable checkout terms checkboxes as it is included in the iframe.
         *
         * @param $page_id
         * @return int
         */
        public function setCheckoutTerms($page_id)
        {
            if ($this->getIsResursCheckout()) {
                return 0;
            }

            return $page_id;
        }

        /**
         * @return bool
         */
        public function getIsResursCheckout()
        {
            $return = false;
            if ($this->FLOW === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $return = true;
            }
            return $return;
        }

        /**
         * @param $isAvailable
         * @param $paymentMethod
         * @return mixed
         */
        private function getIsAvailable($isAvailable, $paymentMethod)
        {
            return $isAvailable;
        }

        /**
         * @param $fragments
         * @return mixed
         */
        public function resursBankOrderReviewFragments($fragments)
        {
            return $fragments;
        }

        /**
         * Defines what payment method type we're running, so that we can configure Resurs Checkout differently.
         *
         * @param int $methodType
         * @throws Exception
         */
        public function createFlow($methodType)
        {
            if ($methodType === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $this->title = __('Resurs Checkout', 'resurs-bank-payment-gateway-for-woocommerce');
            }

            $this->RESURSBANK->setPreferredPaymentFlowService($this->FLOW);
            $this->updateMethodInitializer();

            if (intval($this->CORE->getSession('session_gateway_method_init')) === 1) {
                add_action('woocommerce_after_checkout_form', array($this, 'resursCheckoutIframeContainer'));
            }
        }

        public function is_available()
        {
            $isAvailable = apply_filters('resurs_bank_payment_method_is_available', true, $this->METHOD);

            return $isAvailable;
        }

        /**
         * Session counter that keeps in track of how many times this class has been passed and loaded.
         *
         * @return bool|void
         */
        public function updateMethodInitializer()
        {
            return $this->CORE->setSession(
                'session_gateway_method_init',
                intval($this->CORE->getSession('session_gateway_method_init')) + 1
            );
        }


        // RCO

        /**
         * Prepare container for Resurs Checkout
         */
        public function resursCheckoutIframeContainer()
        {
            if ($this->FLOW !== RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                echo '<div id="resurs-checkout-container"></div>';
            }
        }

        /**
         * @param $fields
         * @return mixed
         */
        public function resursBankCheckoutFields($fields)
        {
            return $fields;
        }


        /**
         *
         */
        private function getActions()
        {
            add_filter('resurs_bank_payment_method_is_available', array($this, 'getIsAvailable'));
            add_filter('woocommerce_get_terms_page_id', array($this, 'setCheckoutTerms'), 1);
            add_filter('woocommerce_update_order_review_fragments', array($this, 'resursBankOrderReviewFragments'), 0,
                1);
            add_filter('woocommerce_checkout_fields', array($this, 'resursBankCheckoutFields'));

            add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        }


    }

}

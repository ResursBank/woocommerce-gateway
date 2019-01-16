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
            parent::__construct();
            $this->CORE = new Resursbank_Core();
            $this->FLOW = $this->CORE->getFlowByEcom($this->CORE->getFlowByCountry($country));
            $this->RESURSBANK = $connection;

            // id, description, title
            if (is_object($paymentMethod)) {
                $this->METHOD = $paymentMethod;

                // Use resursbank_ instead of resurs_bank to avoid conflicts with prior versions.
                $this->id = 'resursbank_' . $paymentMethod->id;
                $this->title = $paymentMethod->description;
                $this->description = $paymentMethod->description;
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
            $this->getActions();
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
         * This method should normally never be required as the gateway handles availablility
         * in another way than in prior version (2.x)
         *
         * @param $isAvailable
         * @return mixed
         */
        private function getIsAvailable($isAvailable)
        {
            return $isAvailable;
        }

        /**
         * @param $fragments
         * @return mixed
         * @throws Exception
         */
        public function resursBankOrderReviewFragments($fragments)
        {
            // When order is in review state, we can consider the last action as "in checkout".
            Resursbank_Core::setCustomerIsInCheckout();

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

        /**
         * @return bool|mixed|void
         */
        public function is_available()
        {
            $isAvailable = apply_filters('resurs_bank_payment_method_is_available', true, $this->METHOD);

            return $isAvailable;
        }

        /**
         * Session counter that keeps in track of how many times this class has been passed and loaded.
         *
         * @return bool|void
         * @throws Exception
         */
        public function updateMethodInitializer()
        {
            // This always happens in the checkout.
            return $this->CORE->setSession(
                'session_gateway_method_init',
                intval($this->CORE->getSession('session_gateway_method_init')) + 1
            );
        }

        /**
         * @param $args
         * @TODO Finish it when it's time for it (Currently it doesn't seem to pass anything here.)
         */
        public function resursBankPaymentWooCommerceApi($args)
        {
            die;
        }

        /**
         * @param $args
         * @TODO Finish it when it's time for it (Currently it doesn't seem to pass anything here.)
         */
        public function resursBankCheckoutProcess($args)
        {
            die;
        }

        /**
         * Handle the current order.
         *
         * This is a rewrite of the old version process_payment rather than a copy-paste as we're merging
         * the way how to handle multiple flows in one piece. It does not directly include the static
         * RCO flow as RCO must happen in backend.
         *
         * @param int $order_id
         * @return array
         * @throws Exception
         */
        public function process_payment($order_id)
        {
            global $woocommerce;

            $return = array(
                'result' => '',         // success
                'redirect' => ''        // processSuccessUrl
            );

            $order = new WC_Order($order_id);

            $lastLocationWasCheckout = Resursbank_Core::getWasInCheckout();
            if (!$lastLocationWasCheckout) {
                wc_add_notice(
                    __('Unable to process your order. Your session has expired. Please reload the checkout and try again (error #getWasInCheckout).', 'resurs-bank-payment-gateway-for-woocommerce')
                    , 'error'
                );

                return array(
                    'result' => 'failure',
                    'redirect' => html_entity_decode($order->get_cancel_order_url())
                );
            }

            $processSuccessUrl = $this->get_return_url($order);

            return $return;
        }

        /**
         * Handle special form fields. This method resided in the former method class files and has been converted to
         * a modern way handling Resurs Bank flows, without the deprecated flow dependency start_payment_session which
         * infested the prior release.
         */
        public function payment_fields()
        {
            echo $this->getPaymentFormFields($this->METHOD);
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

        //


        /**
         *
         */
        private function getActions()
        {
            add_filter('resurs_bank_payment_method_is_available', array($this, 'getIsAvailable'));
            add_filter('woocommerce_get_terms_page_id', array($this, 'setCheckoutTerms'), 1);
            add_filter('woocommerce_checkout_fields', array($this, 'resursBankCheckoutFields'));
            add_action('woocommerce_checkout_process', 'resursBankCheckoutProcess', 1);

            //add_action('woocommerce_api_' . $this->id, array($this, 'resursBankPaymentWooCommerceApi'));
            //add_action('woocommerce_api_wc_resursbank_method', array($this, 'resursBankPaymentWooCommerceApi'));

            //woocommerce_api_wc_resurs_bank
            add_filter(
                'woocommerce_update_order_review_fragments', array(
                $this,
                'resursBankOrderReviewFragments'
            ),
                10,
                1
            );
        }


    }

}

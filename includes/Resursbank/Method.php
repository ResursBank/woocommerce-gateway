<?php

/** @noinspection PhpCSValidationInspection */

if (!defined('ABSPATH')) {
    exit;
}

use Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;
use Resursbank\RBEcomPHP\ResursBank;

if (!class_exists('WC_Resursbank_Method') && class_exists('WC_Gateway_ResursBank')) {
    /**
     * Generic Method Class for Resurs Bank
     * Class WC_Resursbank_Method
     */
    class WC_Resursbank_Method extends WC_Gateway_ResursBank
    {
        /** @var */
        public $title;

        /** @var */
        protected $METHOD_TYPE;
        /** @var Resursbank_Core */
        protected $CORE;
        /** @var RESURS_FLOW_TYPES */
        protected $FLOW;
        /** @var ResursBank */
        protected $RESURSBANK;

        /**
         * WC_Resursbank_Method constructor.
         *
         * @param $paymentMethod Object or string
         * @param $country
         * @param $connection ResursBank
         * @throws Exception
         */
        public function __construct($paymentMethod, $country, $connection)
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
                        __(
                            'Payment method name and flow mismatch.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        400
                    );
                }
            }

            $this->REQUEST = Resursbank_Core::getQueryRequest();

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
                $this->title = __('Resurs Checkout', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
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
         * @return array|bool
         * @throws Exception
         */
        private function canProcessPayment($order)
        {
            $lastLocationWasCheckout = Resursbank_Core::getWasInCheckout();

            if (!$lastLocationWasCheckout) {
                throw new \Exception(
                    __(
                        'Unable to process your order. Your session has expired. Please reload the checkout and try again (error #getWasInCheckout).',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    400
                );
            }

            // Validate exceptions silently - this one is used for special exceptions. Throwing something at this moment,
            // will reflect an error in the checkout.
            do_action('resursbank_validate_process_payment');

            return true;
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
            $resursOrderId = $this->getResursOrderId($order_id);

            /** @var WC_Cart $cart */
            $cart = $woocommerce->cart;

            if (get_class($cart) !== 'WC_Cart') {
                return $this->setPaymentError($order, 'No cart is present', 400);
            }

            try {
                $this->canProcessPayment($order);
            } catch (\Exception $e) {
                return $this->setPaymentError($order, $e->getMessage(), $e->getCode());
            }

            $storeId = apply_filters('resursbank_set_storeid', '');
            if (!empty($storeId)) {
                $this->RESURSBANK->setStoreId($storeId);
                update_post_meta($order_id, 'resursStoreId', $storeId);
            }

            /** @link https://test.resurs.com/docs/x/moBx */
            $this->setCustomerSigningData($order_id, $resursOrderId, $order);
            $this->setResursCustomerBasicData();
            $this->setResursCustomerData('billing');
            $this->setResursCustomerData('shipping');
            $this->setResursCart($cart);

            $bookPaymentResult = $this->createResursOrder($order_id, $resursOrderId, $order, $this->METHOD);

            if ($this->FLOW === RESURS_FLOW_TYPES::HOSTED_FLOW) {
                $redirectUrl = array_shift($bookPaymentResult);
                $return = $this->setResultArray($order, 'success', $redirectUrl);
            }

            if ($this->FLOW === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                // Create order and set statuses.
                $return = $this->setOrderDetails(
                    $order_id,
                    $resursOrderId,
                    $order,
                    $bookPaymentResult
                );
            }

            // $orderReceivedUrl = $this->get_return_url($order);

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

            //woocommerce_api_wc_resurs_bank
            add_filter(
                'woocommerce_update_order_review_fragments',
                array(
                    $this,
                    'resursBankOrderReviewFragments'
                ),
                10,
                1
            );
        }


    }

}

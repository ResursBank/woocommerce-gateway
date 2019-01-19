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
                if ($this->FLOW === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                    // My new name.
                    $this->id = 'resursbank_checkout';
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
         * @throws Exception
         */
        public function updateMethodInitializer()
        {
            // This always happens in the checkout.
            $this->CORE->setSession(
                'session_gateway_method_init',
                intval($this->CORE->getSession('session_gateway_method_init')) + 1
            );
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
        private function getRcoException($message, $code)
        {
            $html = '<div class="resursCheckoutIframeException">';

            $html .= sprintf(
                    __(
                        'An error (%s) occured when trying to set up a shopUrl for Resurs Checkout',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $code
                ) . ':<br>';

            $html .= '<div class="resursCheckoutIframeExceptionMessage">' . $message . '</div>';
            $html .= '</div>';

            return $html;
        }

        /**
         * Prepare container for Resurs Checkout.
         */
        public function resursCheckoutIframeContainer()
        {
            global $woocommerce;
            $html = '';
            $hasErrors = false;

            // Do not bother doing stuff if not in RCO.
            if ($this->FLOW !== RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                return '<div id="resurs-checkout-container" style="display: none !important;"></div>';
            }

            try {
                $this->RESURSBANK->setShopUrl($this->getProperShopUrl());
            } catch (Exception $e) {
                $html = $this->getRcoException($e->getMessage(), $e->getCode());
                $hasErrors = true;
            }
            try {
                $this->canProcessPayment();
            } catch (\Exception $e) {
                $html = $this->getRcoException($e->getMessage(), $e->getCode());
                $hasErrors = true;
            }

            // Initiate order iframe.
            $resursOrderId = $this->getResursOrderId(null);

            $this->prepareResursOrder(
                null,
                $resursOrderId,
                $woocommerce->cart,
                null
            );

            // If no errors occured, fetch the iframe.
            if (!$hasErrors) {
                try {
                    $bookPaymentResult = $this->createResursOrder(null, $resursOrderId, null, $this->METHOD);
                    // Silent internal errors.
                    if (isset($bookPaymentResult['result']) && $bookPaymentResult['result'] === 'failure') {
                        // Terminate current session orderid if errors occurs here.
                        Resursbank_Core::terminateRco();
                        wc_add_notice(_(
                                'An error occured while trying to initialize Resurs Bank iframe solution.',
                                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                        wp_safe_redirect(wc_get_cart_url());
                        die;
                    }
                    $html = array_shift($bookPaymentResult); // the iframe code
                } catch (Exception $e) {
                    $html = $this->getRcoException($e->getMessage(), $e->getCode());
                }
            }

            // Show errors or final result.
            echo $this->setIframeContainer($html);
        }

        /**
         * @param $html
         * @return string
         */
        private function setIframeContainer($html)
        {
            return sprintf('<div id="resurs-checkout-container">%s</div>', $html);
        }

        /**
         * Create actions and filters.
         */
        private function getActions()
        {
            add_filter('resurs_bank_payment_method_is_available', array($this, 'getIsAvailable'));
            add_filter('woocommerce_get_terms_page_id', array($this, 'setCheckoutTerms'), 1);

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

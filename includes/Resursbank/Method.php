<?php

if (!defined('ABSPATH')) {
    exit;
}

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
            $this->CORE = new Resursbank_Core();
            $this->FLOW = $this->CORE->getFlowByEcom($this->CORE->getFlowByCountry($country));
            $this->RESURSBANK = $connection;

            // id, description, title
            if (is_object($paymentMethod)) {
                $this->METHOD = $paymentMethod;
                $this->id = $paymentMethod->id;
                $this->title = $paymentMethod->description;
            } else {
                // Validate flow.
                if ($paymentMethod !== $this->FLOW) {
                    throw new Exception(
                        __('Payment method name and flow mismatch',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        400
                    );
                }
            }

            $this->createFlow($this->FLOW);
        }

        /**
         * Defines what payment method type we're running, so that we can configure Resurs Checkout differently.
         *
         * @param int $methodType
         * @throws Exception
         */
        public function createFlow($methodType)
        {
            if ($methodType === \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $this->title = __('Resurs Checkout', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
            }

            $this->RESURSBANK->setPreferredPaymentFlowService($this->FLOW);
        }

    }

}

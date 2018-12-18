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

        const SIMPLIFIED_FLOW = 1;
        const HOSTED_FLOW = 2;
        const RESURS_CHECKOUT = 3;

        protected $METHOD_TYPE;

        /**
         * WC_Resursbank_Method constructor.
         * @param $id
         */
        function __construct($paymentMethod)
        {
            // id, description, title
        }

        /**
         * Defines what payment method type we're running, so that we can configure Resurs Checkout differently.
         *
         * @param int $methodType
         */
        public function setMethodType($methodType = self::SIMPLIFIED_FLOW)
        {
            // Redeclare the flow here.
        }
    }

}

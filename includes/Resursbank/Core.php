<?php

/**
 * Core functions class for Resurs Bank containing static data handlers and some dynamically called methods.
 *
 * Class Resursbank_Core
 */
class Resursbank_Core
{
    /**
     * @var \Resursbank\RBEcomPHP\ResursBank Resurs Bank Ecommerce Library
     */
    private $RB;
    private static $gatewayClass = 'WC_Gateway_ResursBank';

    function __construct()
    {
        $this->RB = new Resursbank\RBEcomPHP\ResursBank();
    }

    /**
     * Decide whether we're going to use internal ecomphp or the one delivered with the prior plugin.
     * This makes it possible to keep using what's available regardless if the old plugin is active
     * or the new is in use.
     *
     * @return bool
     */
    public static function getInternalEcomEngine()
    {
        if (class_exists('Resursbank\RBEcomPHP\ResursBank')) {
            return false;
        }
        return true;
    }

    /**
     * Generate gateway list for woocommerce
     *
     * @param $woocommerceGateways
     * @return array
     */
    public static function getResursGateways($woocommerceGateways)
    {
        if (is_array($woocommerceGateways) && !in_array(self::getGatewayClass(), $woocommerceGateways)) {
            //$woocommerceGateways[] = self::getGatewayClass();
        }

        return $woocommerceGateways;
    }

    public static function getAvailableGateways($availableGateways)
    {
        unset($availableGateways[self::getGatewayClass()]);

        // TODO: Add them dynamically
        $availableGateways['test1'] = new WC_Resursbank_Method('Test method 1');
        $availableGateways['test2'] = new WC_Resursbank_Method('Test method 2');
        return $availableGateways;
    }

    /**
     * Fetch stored payment method and return it to developer
     *
     * @return stdClass
     */
    public static function getPaymentMethod() {
        $paymentMethod = new stdClass();

        return $paymentMethod;
    }

    /**
     * @param $key
     * @param $value
     * @param string $namespace
     */
    public static function setResursOption($key, $value, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        //get_option();
    }

    /**
     * @param $key
     * @param string $namespace
     */
    public static function getResursOption($key, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        //update_option();
    }

    /**
     * Get the name of the primary class
     *
     * @return string
     */
    public static function getGatewayClass()
    {
        return self::$gatewayClass;
    }

    /**
     * Prepare enqueing
     */
    public static function setResursBankScripts()
    {
        $varsToLocalize = array(
            'resurs_bank_payment_gateway' => array(
                'available' => true,
                'backend_url' => _RESURSBANK_GATEWAY_BACKEND,
                'backend_nonce' => wp_nonce_url(_RESURSBANK_GATEWAY_BACKEND, 'resursBankBackendRequest',
                    'resursBankGatewayNonce')
            )
        );

        wp_enqueue_style(
            'resurs_bank_payment_gateway_css',
            _RESURSBANK_GATEWAY_URL . 'css/resursbank.css',
            array(),
            true
        );

        wp_enqueue_script(
            'resurs_bank_payment_gateway_js',
            _RESURSBANK_GATEWAY_URL . 'js/resursbank.js',
            array('jquery'),
            true
        );

        if (is_array($varsToLocalize)) {
            foreach ($varsToLocalize as $varKey => $varArray) {
                wp_localize_script('resurs_bank_payment_gateway_js', $varKey, $varArray);
            }
        }

    }

}

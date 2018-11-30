<?php

/**
 * Class Resursbank_Config
 */
abstract class Resursbank_Config
{

    /**
     * Return prepare configuration content array.
     *
     * The array is based on WooCommerce configuration array, however as we wish to [try] using dynamic
     * forms differently the base configuration will be rendered by Adminforms itself. Primary goal is to
     * make it easier to create configuration and just having one place to edit.
     *
     * @return array
     */
    public static function getConfigurationArray()
    {
        // By WooCommerce unsupported array variables
        //      title - shown as a head title
        //      display - If missing, this is always true (makes it possible to show/hide configuration options)

        $configurationArray = array(
            'configuration' => array(
                'title' => __(
                    'Merchant Configuration',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ),
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'default' => false,
                'description' => __(
                    'If not enabled, all vital functions in the plugin are shut off. Functions affecting prior orders will still function.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ),
                'tip' => __(
                    'This is the major plugin switch. If not checked, it will be competely disabled, except for that you can still edit this administration control.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                )
            ),
            'resurs_obsolete_coexistence_disable' => array(
                'title' => __(
                    'Disable coexisting plugin',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ),
                'tip' => __(
                    'This feature turns off prior versions of Resurs Bank Payment Gateway if it exists and is enabled.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => __('Disable', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
            ),
            'API' => array(
                'title' => __('API', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'type' => 'title',
            ),
            'environment' => array(
                'title' => __('Environment', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'type' => 'select',
                'options' => array(
                    'test' => __('Test/Staging', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                    'live' => __('Production', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                ),
                'default' => 'test',
                'display' => true,
                'size' => 3,
                'description' => __('Choose if you want to run with test/staging or production. The setting is global for all webservice accounts/countries you configure.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
            ),
            'dynamic_test' => array(
                'title' => 'One dynamic box',
                'type' => 'select',
                'options' => array('dynamic'),
                'description' => 'This is only a test',
                'display' => false,
            )
        );

        return $configurationArray;
    }

}
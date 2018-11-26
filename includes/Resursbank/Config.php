<?php

/**
 * Class Resursbank_Config
 */
abstract class Resursbank_Config {

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
                'title' => __('Merchant Configuration', 'woocommerce'),
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable', 'woocommerce'),
                'default' => false,
                'tip' => __('If not enabled, all vital functions in the plugin are shut off. Functions affecting prior orders will still function.'),
                'description' => __(
                    'This is the major plugin switch. If not checked, it will be competely disabled, except for that you can still edit this administration control.',
                    'woocommerce'
                )
            ),
            'API' => array(
                'title' => __('API', 'woocommerce'),
                'type' => 'title',
            ),
            'environment' => array(
                'type' => 'select',
                'options' => array(
                    'test' => __('Test/Staging', 'woocommerce'),
                    'live' => __('Production', 'woocommerce'),
                ),
                'default' => 'test',
                'display' => false,
                'description' => __('', 'woocommerce'),
            )
        );

        return $configurationArray;
    }

}
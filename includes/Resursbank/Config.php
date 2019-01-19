<?php

if (!defined('ABSPATH')) {
    exit;
}

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
        $configurationArray = array
        (
            'basic' => array(
                'title' => __('Basic settings', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'settings' => array(
                    'configuration' => array(
                        'title' => __(
                            'Merchant Configuration',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'type' => 'title',
                    ),
                    'enabled' => array(
                        'title' => __(
                            'Enable/Disable',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
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
                    'defaultTax' => array(
                        'title' => __(
                            'Default tax class',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'type' => 'select',
                        'options' => 'dynamic_get_tax_classes',
                        'size' => '3',
                        'tip' => __(
                            'Used by the plugin when no other options are available (for example in payment fees).',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'display' => false,
                    ),
                    'API' => array(
                        'title' => __('API', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                        'type' => 'title',
                    ),
                    'environment' => array(
                        'title' => __('Environment', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                        'type' => 'select',
                        'options' => array(
                            'test' => __(
                                'Test/Staging',
                                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                            ),
                            'live' => __(
                                'Production',
                                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                            ),
                        ),
                        'default' => 'test',
                        'tip' => __(
                            'Chosen environment used by the plugin. This setting is global for all configured credentials. If you for example choose test as the environment, it will also be used for the entire plugin.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ),
                    'crentials' => array(
                        'title' => __('Credentials', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                        'type' => 'filter',
                        'filter' => 'get_credentials_html'
                    ),
                ),
            ),
            'advanced' => array(
                'title' => __('Advanced settings', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'settings' => array(
                    'configuration' => array(
                        'title' => __(
                            'Miscellaneous settings',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'type' => 'title',
                    ),
                    'paymentMethodListTimer' => array(
                        'title' => __(
                            'Payment method update interval (in seconds)',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'default' => '21600',
                        'type' => 'text',
                        'tip' => __(
                            'Defines how often, in seconds, for which the plugin should check for and update payment methods by itself. Value must be larger than 300 seconds. Can be defined as 1m=1 month, 2d=2 days, 3h=3 hours and so on. This option is supported in cron mode.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
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
                    'shopSettings' => array(
                        'title' => __(
                            'Shop related settings',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'type' => 'title'
                    ),
                    'useProfileStoreId' => array(
                        'title' => __(
                            'Configure StoreID access on user level',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'tip' => __(
                            'This feature enables ability to configure how store ids should be handled in the order list views, on user level.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'display' => false,
                        'type' => 'checkbox',
                        'default' => false,
                        'label' => __('Enabled', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                    ),
                    'forcePaymentSigning' => array(
                        'title' => __(
                            'Force signed payments',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'tip' => __(
                            'This feature enables signing of payments in simplified and hosted flows, which requires all payments to be signed.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'display' => true,
                        'type' => 'checkbox',
                        'default' => false,
                        'label' => __('Enabled', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                    ),
                    'validateShopUrlHost' => array(
                        'title' => __(
                            'RCO shopUrl validation',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'tip' => __(
                            'While setting up a shopUrl for Resurs Checkout, we can make a DNS lookup to validate that you are really using a valid host for your shop.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'display' => true,
                        'type' => 'checkbox',
                        'default' => false,
                        'label' => __('Enabled', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                    ),
                    'pluginFlags' => array(
                        'title' => __(
                            'Plugin behaviour flags',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'default' => '',
                        'type' => 'text',
                        'tip' => __(
                            'Flags that changes common behaviour in special functions',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ),
                )
            ),
            'about' => array(
                'title' => __('About', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'settings' => array(
                    'information' => array(
                        'title' => __(
                            'Plugin information',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'type' => 'title',
                    ),
                    'plugindata' => array(
                        'title' => __(
                            'Plugin information',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'type' => 'filter',
                        'filter' => 'get_plugin_data'
                    ),
                )
            ),
        );

        return $configurationArray;
    }
}

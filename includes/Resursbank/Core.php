<?php

if (!defined('ABSPATH')) {
    exit;
}

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
     * Check if developer mode is running
     *
     * @return bool
     */
    public static function getDeveloperMode()
    {
        if (defined('_RESURSBANK_DEVELOPER_MODE') && _RESURSBANK_DEVELOPER_MODE) {
            return true;
        }
        return false;
    }

    /**
     * Return list of payment methods from Resurs Bank (legacy)
     *
     * @param $woocommerceGateways
     * @return array
     */
    public static function getResursGateways($woocommerceGateways)
    {
        /*if (is_array($woocommerceGateways) && !in_array(self::getGatewayClass(), $woocommerceGateways)) {
            $woocommerceGateways[] = self::getGatewayClass();
        }*/

        return $woocommerceGateways;
    }

    /**
     * Return generated classes for each available Resurs Bank payment method
     *
     * @param $availableGateways
     * @return mixed
     */
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
    public static function getPaymentMethod()
    {
        $paymentMethod = new stdClass();

        return $paymentMethod;
    }

    /**
     * Fetch default value from a configuration item
     *
     * @param $item
     * @return null
     */
    private static function getDefaultValue($item)
    {
        $return = null;
        if (isset($item['default'])) {
            $return = $item['default'];
        }
        return $return;
    }

    /**
     * Fetch correct option values from WP config.
     *
     * If namespace is set (default), this function will try to fetch one serialized configuration row
     * instead of using a specific configuration key.
     *
     * @param $key
     * @param string $namespace
     * @return bool|mixed|null
     */
    public static function getResursOption($key = '', $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        $value = null;
        $confValues = Resursbank_Config::getConfigurationArray();

        if (!empty($namespace)) {
            $configuration = @unserialize(get_option($namespace));
            // If no key is defined, but still a namespace, just return the full array and ignore the
            // rest of this method.
            if (empty($key)) {
                return $configuration;
            }
        } else {
            // If no key and no namespace, a developer has done it totally wrong.
            if (empty($key)) {
                return null;
            }
            $configuration = get_option('Resurs_Bank_' . $key);
        }

        if (is_array($configuration) && isset($configuration[$key])) {
            $value = $configuration[$key];
        } elseif (is_object($configuration) && isset($configuration->{$key})) {
            $value = $configuration->{$key};
        }

        if (is_null($value) && isset($confValues[$key])) {
            $value = self::getDefaultValue($confValues[$key]);
        }

        if (isset($confValues[$key]) && $confValues[$key]['type'] === 'checkbox') {
            if (strtolower($value) === 'yes' || (bool)$value) {
                $value = true;
            } else {
                $value = false;
            }
        }

        return $value;
    }

    /**
     * Update a configuration option.
     *
     * If the default namespace is used, configuration data are fetched from a single key row
     * as an array.
     *
     * @param $key
     * @param $value
     * @param string $namespace
     * @return bool
     */
    public static function setResursOption($key, $value, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        $updateSuccess = false;
        if (!empty($key)) {
            if (!empty($namespace)) {
                $allOptions = get_option($namespace);
                $allOptions[$key] = $value;
                $updateSuccess = update_option($namespace, $allOptions);
            } else {
                $updateSuccess = update_option('Resurs_Bank_' . $key, $value);
            }
        }

        return $updateSuccess;
    }

    /**
     * Extract a true or false value from a setting by key [and namespace].
     *
     * @param $key
     * @param string $namespace
     * @return bool
     */
    public static function getTrue($key, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        $return = false;
        $value = self::getResursOption($key, $namespace);
        if (strtolower($value) === 'yes' || (bool)$value) {
            $return = true;
        }
        return $return;
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
            _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
        );

        wp_enqueue_script(
            'resurs_bank_payment_gateway_js',
            _RESURSBANK_GATEWAY_URL . 'js/resursbank.js',
            array('jquery'),
            _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
        );

        if (is_array($varsToLocalize)) {
            foreach ($varsToLocalize as $varKey => $varArray) {
                wp_localize_script('resurs_bank_payment_gateway_js', $varKey, $varArray);
            }
        }

    }

    public static function resurs_obsolete_coexistence_disable() {
        $return = self::getTrue('resurs_obsolete_coexistence_disable');
        $return = true;
        return $return;
    }

    /**
     * Legacy way to fetch current version of WooCommerce
     *
     * @param string $versionRequest
     * @param string $operator
     * @return bool
     */
    public static function getVersionWoocommerceCompare($versionRequest = "3.0.0", $operator = ">=")
    {
        $return = false;
        if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, $versionRequest, $operator)) {
            $return = true;
        }
        return $return;
    }

    /**
     * @return string
     */
    public static function getPluginVersion()
    {
        if (defined('_RESURSBANK_GATEWAY_VERSION')) {
            return _RESURSBANK_GATEWAY_VERSION;
        }
        return 'i.have.no.idea-beta';
    }


}

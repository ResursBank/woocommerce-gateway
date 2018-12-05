<?php

if (!defined('ABSPATH')) {
    exit;
}

use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
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
        $this->RB = Resursbank_Core::getConnection();
    }

    /**
     * Get chosen environment for Resurs Bank.
     *
     * @return string
     */
    public static function getEnvironment() {
        $environment = self::getResursOption('environment');
        return $environment;
    }

    /**
     * Get chosen environment and translate it to EComPHP-style.
     *
     * @return int
     */
    public static function getEcomEnvironment() {
        $env = self::getEnvironment();
        if ($env === 'live') {
            return RESURS_ENVIRONMENTS::PRODUCTION;
        }
        return RESURS_ENVIRONMENTS::TEST;
    }

    /**
     * Initialize EComPHP.
     *
     * @param string $username
     * @param string $password
     * @param string $country
     * @return ResursBank
     * @throws Exception
     */
    private static function getConnection($username = '', $password = '', $country = '')
    {
        $connection = new ResursBank();
        if (!empty($username) && !empty($password)) {
            $connection->setAuthentication($username, $password);
        }
        $connection->setEnvironment(self::getEcomEnvironment());

        return $connection;
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
     * @param string $country
     * @return array|bool|mixed|null
     */
    public static function getCredentialsByCountry($country = '')
    {
        $credentialsList = self::getResursOption('credentials');
        if (!empty($country) && isset($credentialsList[$country])) {
            return $credentialsList[$country];
        }
        if (!is_array($credentialsList)) {
            $credentialsList = array();
        }
        return $credentialsList;
    }

    public static function getPaymentMethods($country = '')
    {
        $credentials = self::getCredentialsByCountry($country);

        if (isset($credentials['username'])) {
            $RB = self::getConnection($credentials['username'], $credentials['password']);
        }
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
     * Generate a configurable array out of WooCommerce taxrate classes.
     *
     * Configurable = Resurs Bank wp-admin friendly ratelist
     *
     * @param array $taxClasses
     * @param string $tax_class
     * @param null $customer
     * @return array
     */
    public static function getTaxRateList($taxClasses = array(), $tax_class = '', $customer = null)
    {
        $allTaxClasses = self::getTaxRateClasses($taxClasses, $tax_class, $customer);
        $rateArray = array();

        if (is_array($allTaxClasses) && count($allTaxClasses)) {
            foreach ($allTaxClasses as $arrayData) {
                // Only match once
                if (!isset($rateArray[$arrayData['rate']])) {
                    $rateArray[$arrayData['rate']] = $arrayData['label'];
                }
            }
        }

        return $rateArray;
    }

    /**
     * Get taxclasses from WooCommerce that contains taxrates.
     *
     * @param array $taxClasses
     * @param string $tax_class
     * @param null $customer
     * @return array
     */
    public static function getTaxRateClasses($taxClasses = array(), $tax_class = '', $customer = null)
    {
        $currentRateList = WC_Tax::get_rates();
        $taxClassList = WC_Tax::get_tax_classes();
        if (is_array($taxClassList)) {
            foreach ($taxClassList as $taxClass) {
                $currentRateList += WC_Tax::get_rates($taxClass);
            }
        }

        return $currentRateList;
    }

    /**
     * Get full configuration array.
     *
     * @return array
     */
    public static function getConfiguration()
    {
        return Resursbank_Config::getConfigurationArray();
    }

    /**
     * Return true if constructor should be used in admin panel.
     *
     * @return bool
     */
    public static function getSectionsByConstructor()
    {
        return (defined('_RESURSBANK_SECTIONS_BY_CONSTRUCTOR')) ? _RESURSBANK_SECTIONS_BY_CONSTRUCTOR : false;
    }

    /**
     * Fetch default value from a configuration item.
     *
     * @param $key
     * @return string|null If null, nothing was found.
     */
    public static function getDefaultValue($key)
    {
        $return = null;
        $configurationArray = Resursbank_Config::getConfigurationArray();

        foreach ($configurationArray as $itemKey => $itemArray) {
            if (isset($itemArray['settings'])) {
                foreach ($itemArray as $settingKey => $settingArray) {
                    if (isset($settingArray[$key]) && isset($settingArray[$key]['default'])) {
                        $return = $settingArray[$key]['default'];
                        break;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Get all default values bulked.
     * @return array
     */
    public static function getDefaultConfiguration()
    {
        $allConfig = array();
        $defaultConfig = Resursbank_Core::getConfiguration();
        foreach ($defaultConfig as $section => $sectionArray) {
            if (isset($sectionArray['settings'])) {
                foreach ($sectionArray['settings'] as $settingKey => $settingArray) {
                    if (isset($settingArray['default'])) {
                        $allConfig[$settingKey] = self::getDefaultValue($settingKey);
                    }
                }
            }
        }
        return $allConfig;
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
        $defaultValue = self::getDefaultValue($key);

        if (!empty($namespace)) {
            // This is usually serialized when returned.
            $nsOpt = get_option($namespace);
            if (!empty($nsOpt)) {
                $configuration = $nsOpt;
            } else {
                $configuration = array();
            }
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

        if (is_null($value) && !is_null($defaultValue)) {
            $value = $defaultValue;
        }

        if (isset($confValues[$key]) && isset($confValues[$key]['type'])) {
            if ($confValues[$key]['type'] === 'checkbox') {
                if (strtolower($value) === 'yes' || (bool)$value) {
                    $value = true;
                } else {
                    $value = false;
                }
            }
        }

        return $value;
    }

    /**
     * Administrator permissions or in admin control.
     *
     * @return bool
     */
    function getUserIsAdmin()
    {
        if (current_user_can('administrator') || is_admin()) {
            return true;
        }

        return false;
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
    public static function setResursOption($key = '', $value = '', $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        $updateSuccess = false;
        if (!empty($key)) {
            if (!empty($namespace)) {
                // If key is an array and namespace is not empty, try to store inbound array to configuration.
                if (is_array($key)) {

                    // Use the built in configuration to fill up with missing keys.
                    $allOptions = self::getResursOption();
                    foreach ($key as $optionKey => $optionValue) {
                        $allOptions[$optionKey] = $optionValue;
                    }
                    $updateSuccess = update_option($namespace, $allOptions);

                }
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
     * @param $filename
     * @return string|null Null is unexistent
     */
    private static function getGraphicsUrl($filename)
    {
        $return = null;
        $gUrl = _RESURSBANK_GATEWAY_URL . 'images';
        if (file_exists(_RESURSBANK_GATEWAY_PATH . 'images/' . $filename)) {
            $return = $gUrl . '/' . $filename;
        }
        return $return;
    }

    /**
     * Get URLs to used graphics
     *
     * @param string $name
     * @return array
     */
    public static function getGraphics($name = '')
    {
        $return = array(
            'add' => self::getGraphicsUrl('add-16.png'),
            'delete' => self::getGraphicsUrl('delete-16.png'),
            'spinner' => self::getGraphicsUrl('spinner.png'),
        );
        if (!empty($name)) {
            return isset($return[$name]) ? $return[$name] : null;
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
                'graphics' => Resursbank_Core::getGraphics(),
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

        if (is_admin()) {
            wp_enqueue_script(
                'resurs_bank_payment_gateway_admin_js',
                _RESURSBANK_GATEWAY_URL . 'js/resursadmin.js',
                array('jquery'),
                _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
            );
        }

        if (is_array($varsToLocalize)) {
            foreach ($varsToLocalize as $varKey => $varArray) {
                wp_localize_script('resurs_bank_payment_gateway_js', $varKey, $varArray);
            }
        }

    }

    /**
     * Allow or disable coexisting plugins via dynamic configuration
     *
     * @return bool
     */
    public static function resurs_obsolete_coexistence_disable()
    {
        $return = self::getTrue('resurs_obsolete_coexistence_disable');
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
     * Get this plugin version
     *
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

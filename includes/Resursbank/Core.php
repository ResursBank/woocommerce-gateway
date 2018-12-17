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
     * @var ResursBank Ecommerce Library
     */
    private $RB;

    /**
     * @var string
     */
    private static $gatewayClass = 'WC_Gateway_ResursBank';

    /**
     * Resursbank_Core constructor.
     *
     * @throws Exception
     */
    function __construct()
    {
        $this->RB = $this->getConnection();
    }

    /**
     * Fetch payment methods for country based selection.
     *
     * @param string $country
     * @return array|mixed
     * @throws Exception
     */
    public function getPaymentMethods($country = '')
    {
        $credentials = $this->getCredentialsByCountry($country);
        $return = array();

        $currentEnvironment = self::getResursOption('environment');

        if (isset($credentials[$currentEnvironment]['username'])) {
            $connection = $this->getConnection($credentials[$currentEnvironment]['username'],
                $credentials[$currentEnvironment]['password']);
            $return = $connection->getPaymentMethods(array(), true);
        }

        return $return;
    }

    /**
     * @param string $country
     * @return array
     */
    private function getCredentialsByCountry($country = '')
    {
        $credentialsList = $this->getResursOptionStatically('credentials');
        if (!empty($country)) {
            if (isset($credentialsList[$country])) {
                $credentialsList = $credentialsList[$country];
            } else {
                $credentialsList = array();
            }
        }
        if (!is_array($credentialsList)) {
            $credentialsList = array();
        }
        return $credentialsList;
    }

    /**
     * TODO
     */
    private function getFlowByCountry()
    {
    }

    /**
     * Get chosen environment for Resurs Bank.
     *
     * @return string
     */
    private function getEnvironment()
    {
        $environment = $this->getResursOptionStatically('environment');
        return $environment;
    }

    /**
     * Get options via internal calls but via a static method
     *
     * @param string $key
     * @param string $namespace
     * @return bool|mixed|null
     */
    public function getResursOptionStatically($key = '', $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        return Resursbank_Core::getResursOption($key, $namespace);
    }

    /**
     * Get chosen environment and translate it to EComPHP-style.
     *
     * @param null $dynamic Dynamially fetch correct environment (used by get_payment_methods)
     * @return int
     */
    private function getEcomEnvironment($dynamic = null)
    {
        $return = RESURS_ENVIRONMENTS::TEST;
        $env = $this->getEnvironment();
        if (!is_null($dynamic) && $dynamic === 'test' || $dynamic === 'live') {
            $env = $dynamic;
        }
        if ($env === 'live') {
            $return = RESURS_ENVIRONMENTS::PRODUCTION;
        }
        return $return;
    }

    /**
     * Initialize EComPHP.
     *
     * @param string $username
     * @param string $password
     * @param null $environment
     * @return ResursBank
     * @throws Exception
     */
    private function getConnection($username = '', $password = '', $environment = null)
    {
        $this->RB = new ResursBank();
        if (!empty($username) && !empty($password)) {
            $this->RB->setAuthentication($username, $password);
        }
        if (is_null($environment)) {
            $this->RB->setEnvironment(self::getEcomEnvironment());
        } else {
            $this->RB->setEnvironment($environment);
        }

        return $this->RB;
    }


    /** ** METHOD BELOW IS STATIC, THE ABOVE REQUIRES INSTATIATION ** */

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
     * Using API method to request payment methods list
     *
     * @param $credentials
     * @param bool $cron
     * @return mixed
     * @throws Exception
     */
    private static function getPaymentMethodsByApi($credentials, $cron = false)
    {
        $CORE = new Resursbank_Core();
        $methodList = array();
        $hasException = false;
        $exceptions = array();

        foreach ($credentials as $credentialCountry => $credentialArrayContent) {
            foreach ($credentialArrayContent as $environment => $credentialArray) {
                if ($environment === 'test' || $environment === 'live' && (!empty($credentialArray['username']))) {
                    $request = $CORE->getConnection(
                        $credentialArray['username'],
                        $credentialArray['password'],
                        $CORE->getEcomEnvironment($environment));
                    try {
                        $methodList[$credentialCountry][$environment] = $request->getPaymentMethods(array(), true);
                    } catch (\Exception $e) {
                        $hasException = true;
                        $exceptions[] = $credentialCountry . '/' . $environment . ': [' . $e->getCode() . ']' . $e->getMessage();
                    }
                }
            }
        }

        if ($hasException) {
            throw new \Exception('getPaymentMethods exception -- ' . implode(', ', $exceptions), 400);
        }

        self::setStoredPaymentMethods($methodList);

        return $methodList;
    }

    /**
     * If payment methods are stored, decide from where it should be taken. If the data is too old,
     * it will recreate the method list live.
     *
     * @param $methodList
     * @param $credentials
     * @param bool $cron
     * @param $requestEnvironment
     * @return mixed
     * @throws Exception
     */
    private static function getStoredPaymentMethodData($methodList, $credentials, $cron = false, $requestEnvironment)
    {
        // Running cronjobs should always update methods.
        $nextUpdateTimer = $cron ? 1 : 21600;
        if (!isset($methodList['lastRequest']) || (isset($methodList['lastRequest']) && intval($methodList['lastRequest']) < (time() - $nextUpdateTimer))) {
            $methodList = self::getPaymentMethodsByApi($credentials, $cron);
        } else {
            return $methodList['methods'];
        }

        return $methodList;
    }

    /**
     * @param $country
     * @param $requestEnvironment
     * @return mixed
     * @throws Exception
     */
    private static function getStoredPaymentMethodsByCountry($country, $requestEnvironment)
    {
        $CORE = new Resursbank_Core();
        $credentials = $CORE->getCredentialsByCountry($country);

        if (!count($credentials)) {
            throw new \Exception(
                __(
                    'Payment methods are not available for country',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ) . ' ' . $country, 400
            );
        }

        $currentEnvironment = self::getResursOption('environment');

        $request = $CORE->getConnection(
            $credentials[$currentEnvironment]['username'],
            $credentials[$currentEnvironment]['password'],
            $CORE->getEcomEnvironment()
        );
        $methodList[$country] = $request->getPaymentMethods(array(), true);

        return $methodList;
    }

    /**
     * Get stored payment methods if present. Otherwise get them live.
     *
     * @param string $country
     * @param bool $cron
     * @param $requestEnvironment
     * @return bool|mixed|null
     * @throws Exception
     */
    public static function getStoredPaymentMethods($country = '', $cron = false, $requestEnvironment)
    {
        $methodList = self::getResursOption('paymentMethods');
        $credentials = self::getResursOption('credentials');

        if (isset($methodList['methods']) || $cron) {
            $methodList = self::getStoredPaymentMethodData($methodList, $credentials, $cron, $requestEnvironment);
        }

        // If no methods are visible in the config
        if (!is_array($methodList)) {
            if (!empty($country)) {
                $methodList = self::getStoredPaymentMethodsByCountry($country, $credentials, false,
                    $requestEnvironment);
            } else {
                if (is_array($credentials)) {
                    $methodList = self::getPaymentMethodsByApi($credentials, $cron);
                } else {
                    throw new \Exception(
                        __(
                            'Request failed due to missing credentials.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ) . ' ' . $country, 400
                    );
                }
            }
        }

        return $methodList;
    }

    /**
     * Update payment methods in configuration
     *
     * @param $methodObject
     * @return bool
     */
    public static function setStoredPaymentMethods($methodObject)
    {
        $storedArray = array(
            'lastRequest' => time(),
            'methods' => $methodObject
        );

        return self::setResursOption('paymentMethods', $storedArray);
    }

    /**
     * Returns payment methods in specified formatting
     *
     * @param string $country
     * @param null $isCron
     * @return array
     * @throws Exception
     */
    public static function get_payment_methods($country = '', $isCron = null)
    {
        if (empty($country)) {
            $requestCountry = (isset($_REQUEST['country']) ? $_REQUEST['country'] : '');
        } else {
            $requestCountry = $country;
        }

        $cron = isset($_REQUEST['cron']) ? true : false;
        if (!is_null($isCron)) {
            $cron = $isCron;
        }
        $requestEnvironment = null;
        if (isset($_REQUEST['environment'])) {
            $requestEnvironment = empty($_REQUEST['environment']) ? $_REQUEST['environment'] : self::getResursOption('environment');
        }
        $return = self::getStoredPaymentMethods($requestCountry, $cron, $requestEnvironment);

        if ($cron) {
            return array('updated' => true);
        }

        return $return;
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
     * @return bool|mixed|null
     */
    public static function resursbank_get_coexist_dismissed()
    {
        return self::getResursOption('dismiss_resursbank_coexist_message');
    }

    /**
     * Prepare dynamic options for dismissed notices and elements.
     *
     * @param $configArray
     * @return array
     */
    public static function resursbank_get_dismissed_elements($configArray)
    {
        $elements = array('dismiss_resursbank_coexist_message');

        $elementArray = array(
            'dismissed' => array(
                'title' => __('Dismissed notices', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'settings' => array(
                    'dismissed_title' => array(
                        'title' => __('Restore notices and elements hidden by plugin',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                        'type' => 'title',
                    )
                ),
            ),
        );

        $hasElements = false;
        foreach ($elements as $optionName) {
            if (self::getResursOption($optionName)) {
                $hasElements = true;
                $elementArray['dismissed']['settings'][$optionName] = array(
                    'title' => $optionName,
                    'type' => 'checkbox',
                    'default' => false,
                );
            }
        }

        if ($hasElements) {
            $configArray += $elementArray;
        }

        return $configArray;
    }

    /**
     * @param $array
     * @param $request
     * @return mixed
     */
    public static function resursbank_set_dismissed_element($array, $request)
    {
        $element = isset($request['element']) ? preg_replace('/^#/', '', $request['element']) : false;
        $array['success'] = true;
        $array['dismissed'] = $element;

        if (self::setResursOption('dismiss_' . $element, true)) {
            $array['success'] = true;
            $array['dismissed'] = $element;
        }

        return $array;
    }

    /**
     * Coming controller for where nonces are require to pass safely or if they
     * are not required at all.
     *
     * @param string $runRequest
     * @return bool
     */
    private static function getRequiredNonce($runRequest = '')
    {
        $requiredOnRequest = array(
            'get_payment_methods',
            'get_registered_callbacks',
        );

        $return = in_array($runRequest, $requiredOnRequest);

        // If return is true (= nonce is required to run) but it is on the other hand allowed to run in cron mode
        // tell the running process to allow it by saing "no, nonce is not required".
        if ($return && self::getCanRunInCron($runRequest)) {
            $return = false;
        }

        return $return;
    }

    /**
     * Defines which functions that can run in cron mode without admin requirements
     *
     * @param string $runRequest
     * @return bool
     */
    private static function getCanRunInCron($runRequest = '')
    {
        $requiredOnRequest = array(
            'get_payment_methods',
        );

        // Making sure that, if it is cronable, it is also running in cron mode.
        return in_array($runRequest, $requiredOnRequest) && isset($_REQUEST['cron']) ? true : false;
    }

    /**
     * Require admin on specific requests
     *
     * @param string $runRequest
     * @return bool
     */
    private static function getRequiredAdmin($runRequest = '')
    {
        $requiredOnRequest = array(
            'get_payment_methods',
            'get_registered_callbacks',
        );

        // If request is listed above and admin is missing, return true as this means that the user is not admin.
        $return = (in_array($runRequest, $requiredOnRequest) && !is_admin()) ? false : true;

        if (!$return) {
            $return = isset($_REQUEST['cron']) && self::getCanRunInCron($runRequest) ? false : true;
        }

        return $return;
    }

    /**
     * Verify that the nonce is correct.
     *
     * @param string $runRequest
     * @return bool
     * @throws Exception
     */
    public static function resursbank_verify_nonce($runRequest = '')
    {
        $return = false;

        // Check for both token (deprecated) and resursBankGatewayNonce
        if (wp_verify_nonce(
                (isset($_REQUEST['token']) ? $_REQUEST['token'] : null),
                'resursBankBackendRequest'
            ) ||
            wp_verify_nonce(
                (isset($_REQUEST['resursBankGatewayNonce']) ? $_REQUEST['resursBankGatewayNonce'] : null),
                'resursBankBackendRequest'
            )
        ) {
            // If nonce is verified.
            $return = true;

            if (!self::getRequiredAdmin($runRequest)) {
                throw new \Exception(
                    __(
                        'Security verification failure',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ) . ' - ' .
                    __(
                        'Must be administator',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ), 400
                );
            }
        }

        if (!$return && self::getRequiredNonce($runRequest)) {
            throw new \Exception(
                __(
                    'Security verification failure',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ) . ' - ' .
                __(
                    'Security key mismatch',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ), 400
            );
        }

        return $return;
    }

    /**
     * Return list of payment methods from Resurs Bank (legacy)
     *
     * @param $woocommerceGateways
     * @return array
     */
    public static function getResursGateways($woocommerceGateways)
    {
        /*        if (is_array($woocommerceGateways) && !in_array(self::getGatewayClass(), $woocommerceGateways)) {
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
        //$availableGateways['test1'] = new WC_Resursbank_Method('Test method 1');
        //$availableGateways['test2'] = new WC_Resursbank_Method('Test method 2');
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
        $configurationArray = Resursbank_Config::getConfigurationArray();
        $configurationArray = apply_filters('resursbank_config_array', $configurationArray);

        return $configurationArray;
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
        $defaultConfig = apply_filters('resursbank_config_array', $defaultConfig);

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
            /** @noinspection PhpUndefinedVariableInspection */
            if ($confValues[$key]['type'] === 'checkbox') {
                if (strtolower($value) === 'yes' || (bool)$value || strtolower($value) === 'on') {
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

        if (!empty($namespace)) {
            if (!empty($key) && is_array($key)) {
                // If key is an array and namespace is not empty, try to store inbound array to configuration.
                $updateSuccess = self::setOptionArray($key, $namespace);
            } else {
                $updateSuccess = self::setOptionArray(array($key => $value), $namespace);
            }
        } else {
            $updateSuccess = update_option('Resurs_Bank_' . $key, $value);
        }

        return $updateSuccess;
    }

    /**
     * @param $keyArray
     * @param string $namespace
     * @return bool
     */
    private static function setOptionArray($keyArray, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        $return = false;
        $allOptions = self::getResursOption();

        // If the incoming key is an array, we should presume that the correct values are already
        // stored correctly.
        if (is_array($keyArray)) {
            // Use the built in configuration to fill up with missing keys.
            foreach ($keyArray as $optionKey => $optionValue) {
                $allOptions[$optionKey] = $optionValue;
            }

            $return = update_option($namespace, $allOptions);
        }

        return $return;
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
        if (strtolower($value) === 'yes' || (bool)$value || strtolower($value) === 'on') {
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
            'spinner' => self::getGraphicsUrl('spin.gif'),
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

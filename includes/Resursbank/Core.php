<?php

/** @noinspection PhpCSValidationInspection */

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
    private $RESURSBANK;

    /**
     * @var string
     */
    private static $gatewayClass = 'WC_Gateway_ResursBank';

    const RESURS_SHOPFLOW_SIMPLIFIED = 'simplified';
    const RESURS_SHOPFLOW_HOSTED = 'hosted';
    const RESURS_SHOPFLOW_CHECKOUT = 'checkout';

    /**
     * Resursbank_Core constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->RESURSBANK = $this->getConnection();
    }

    /**
     * Fetch payment methods for country based selection.
     *
     * This request is being sent directly to Resurs Bank API, so to use a cached version of payment methods,
     * use the static function getStoredPaymentMethods() instead.
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
            $connection = $this->getConnection(
                $credentials[$currentEnvironment]['username'],
                $credentials[$currentEnvironment]['password']
            );
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
        $env = self::getResursOption('environment');
        if (!is_null($dynamic) && $dynamic === 'test' || $dynamic === 'live') {
            $env = $dynamic;
        }
        if ($env === 'live') {
            $return = RESURS_ENVIRONMENTS::PRODUCTION;
        }
        return $return;
    }

    /**
     * Get flow type based on EcomPHP responses.
     *
     * @param string $flow
     * @return \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES
     */
    public function getFlowByEcom($flow = '')
    {
        $return = '';

        switch ($flow) {
            case Resursbank_Core::RESURS_SHOPFLOW_CHECKOUT:
                $return = \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::RESURS_CHECKOUT;
                break;
            case Resursbank_Core::RESURS_SHOPFLOW_SIMPLIFIED:
                $return = \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::SIMPLIFIED_FLOW;
                break;
            case Resursbank_Core::RESURS_SHOPFLOW_HOSTED:
                $return = \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::HOSTED_FLOW;
                break;
            default:
                break;
        }

        //Resursbank_Core::RESURS_SHOPFLOW_CHECKOUT
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
    protected function getConnection($username = '', $password = '', $environment = null)
    {
        $this->RESURSBANK = new ResursBank();

        if (!empty($username) && !empty($password)) {
            $this->RESURSBANK->setAuthentication($username, $password);
        }

        if (is_null($environment)) {
            $this->RESURSBANK->setEnvironment(self::getEcomEnvironment());
        } else {
            $this->RESURSBANK->setEnvironment($environment);
        }

        return $this->RESURSBANK;
    }

    /**
     * Extract strings from postmeta that has been transitioned into arrays. Return as strings when meta is proper.
     *
     * @param $postId
     * @return array|mixed
     */
    public function getPostMeta($postId, $key)
    {
        $postMeta = get_post_meta($postId, $key);
        if (is_array($postMeta)) {
            return @array_shift($postMeta);
        }
        return $postMeta;
    }

    /**
     * Self initialize EComPHP based on country data.
     *
     * @param $country
     * @return ResursBank
     * @throws Exception
     */
    public function getConnectionByCountry($country)
    {
        if (empty($country)) {
            $country = self::getCustomerCountry();
        }
        $currentEnvironment = self::getResursOption('environment');
        $credentials = $this->getCredentialsByCountry($country);

        if (!count($credentials)) {
            throw new \Exception(
                sprintf(
                    __(
                        'Payment methods are not available for country "%s" or site has not yet been configured.',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $country
                ),
                400
            );
        }

        return $this->getConnection(
            $credentials[$currentEnvironment]['username'],
            $credentials[$currentEnvironment]['password'],
            $this->getEcomEnvironment($currentEnvironment)
        );
    }

    /**
     * Find out which flow we're using for a specific country.
     *
     * @param string $country
     * @return mixed|string
     * @throws Exception
     */
    public function getFlowByCountry($country = '')
    {
        $return = '';
        $resursCredentials = self::getResursCore()->getCredentialsByCountry($country);

        if (isset($resursCredentials['shopflow'])) {
            return $resursCredentials['shopflow'];
        }

        return $return;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function getWasInCheckout()
    {
        $getValue = (bool)self::getResursCore()->getSession('resursbank_location_last_checkout');
        $getValue = apply_filters('resursbank_allow_place_order', $getValue);

        return $getValue;
    }

    /**
     * @return bool
     */
    private function isSession()
    {
        global $woocommerce;

        $return = false;
        if (isset($woocommerce->session)) {
            $return = true;
        }

        return $return;
    }

    /**
     * Renders datainfo array for about section.
     *
     * @param $key
     * @param $value
     * @return array
     */
    private static function get_data_info_array($key, $value)
    {
        return array(
            'name' => $key,
            'value' => $value,
        );
    }


    /**
     * @param $active
     * @return mixed
     */
    public static function activateStoreIdConfig($active)
    {
        $active = (bool)self::getResursOption('useProfileStoreId');
        return (bool)$active;
    }

    /**
     * @return string|void
     */
    private static function getNA()
    {
        return __('Not available.', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
    }

    /**
     * Plugin information data that is internally supported
     *
     * @param $pluginInfoKey
     * @return array
     */
    public static function get_data_info($pluginInfoKey)
    {
        if (empty($pluginInfoKey) || !is_string($pluginInfoKey)) {
            return;
        }
        $curlVersion = null;
        if (!function_exists('curl_version')) {
            $curl = curl_version();
        }
        $curlVersion = isset($curl['version']) ? $curl['version'] : self::getNA();

        $data = array(
            'version_php' => array(
                'name' => 'PHP version',
                'value' => PHP_VERSION
            ),
            'version_gateway' => array(
                'name' => 'Gateway version',
                'value' => _RESURSBANK_GATEWAY_VERSION
            ),
            'version_ecomphp' => array(
                'name' => 'EComPHP',
                'value' => ECOMPHP_VERSION
            ),
            'version_curl' => array(
                'name' => 'curl',
                'value' => $curlVersion
            ),
            'version_ssl' => array(
                'name' => 'SSL/https',
                'value' => (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : self::getNA())
            ),
            'version_module_curl' => array(
                'name' => 'MODULE_CURL',
                'value' => (defined('NETCURL_RELEASE') ? NETCURL_RELEASE : self::getNA())
            ),
        );

        if (isset($data[$pluginInfoKey]) && is_array($data)) {
            return self::get_data_info_array($data[$pluginInfoKey]['name'], $data[$pluginInfoKey]['value']);
        }
    }

    /**
     * @return array
     */
    public static function getPluginDataArray()
    {
        return array(
            'version_php',
            'version_gateway',
            'version_ecomphp',
            'version_curl',
            'version_ssl',
            'version_module_curl',
        );
    }

    /**
     * @return string
     */
    public static function getPluginData()
    {
        $pluginData = self::getPluginDataArray();
        $pluginData = apply_filters('resursbank_data_info_array', $pluginData);

        $pluginInformationHtml = '<table width="800px" class="resursGatewayConfigCredentials" style="table-layout: auto !important;" id="resurs_bank_credential_table">';
        foreach ($pluginData as $pluginInfoKey) {
            $pluginInformationContent = apply_filters('resursbank_data_info_' . $pluginInfoKey, $pluginInfoKey);
            // Make sure filters is doing it right.
            if (is_array($pluginInformationContent) &&
                isset($pluginInformationContent['name']) &&
                isset($pluginInformationContent['value']) &&
                !empty($pluginInformationContent['name']) &&
                !empty($pluginInformationContent['value'])
            ) {
                $value = htmlentities($pluginInformationContent['value']);
                if ($value === self::getNA()) {
                    $value = '<span style="font-weight: bold; font-style: italic;color: #990000;">' . $value . '</span>';
                }
                $pluginInformationHtml .= '<tr>';
                $pluginInformationHtml .= '<td width="300px" style="font-weight: bold;">' . htmlentities($pluginInformationContent['name']) . '</td>';
                $pluginInformationHtml .= '<td width="500px">' . $value . '</td>';
                $pluginInformationHtml .= '</tr>';
            }

        }
        $pluginInformationHtml .= '</table>';

        return $pluginInformationHtml;
    }

    /**
     * @param $isCheckout
     * @param bool $storePrior
     * @return bool
     * @throws Exception
     */
    private static function setCustomerPageTrack($isCheckout)
    {
        $CORE = self::getResursCore();

        // Makes sure that integrators can modify their checkout injection protection if there are more places
        // required to block or allow.
        $isCheckout = apply_filters('resursbank_location_last_checkout', $isCheckout);
        $CORE->setSession('resursbank_location_last_checkout', $isCheckout);

        return true;
    }

    /**
     * Tell session when customer is outside checkout and store the value
     *
     * @return bool
     * @throws Exception
     */
    public static function setCustomerIsOutsideCheckout()
    {
        return self::setCustomerPageTrack(false);
    }

    /**
     * Tell session when customer is really in the checkout.
     *
     * @return bool
     * @throws Exception
     */
    public static function setCustomerIsInCheckout()
    {
        return self::setCustomerPageTrack(true);
    }

    /**
     * @param $key
     * @param $value
     */
    public function setSession($key, $value)
    {
        if ($this->isSession()) {
            WC()->session->set($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * @param $key
     * @return array|mixed|string
     */
    public function getSession($key)
    {
        $return = null;

        if ($this->isSession()) {
            $return = WC()->session->get($key);
        } else {
            if (isset($_SESSION[$key])) {
                $return = $_SESSION[$key];
            }
        }

        return $return;
    }

    /** ** METHOD BELOW IS STATIC, THE ABOVE REQUIRES INSTATIATION ** */

    /**
     * @return Resursbank_Core
     * @throws Exception
     */
    public static function getResursCore()
    {
        return new Resursbank_Core();
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
     * Using API method to request payment methods list
     *
     * @param $credentials
     * @param bool $cron
     * @return mixed
     * @throws Exception
     */
    private static function getPaymentMethodsByApi($credentials, $cron = false)
    {
        $CORE = self::getResursCore();
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
     * Translate time into seconds.
     *
     * @param $currentValue
     * @return int
     */
    public static function getPaymentListTimer($currentValue)
    {
        @preg_match_all('/(\d{1,2})(\w+)/i', $currentValue, $newValues);

        if (isset($newValues[1][0]) && isset($newValues[2]) && isset($newValues[2][0])) {

            $intValue = $newValues[1][0];
            $valueType = $newValues[2][0];

            if (is_numeric($intValue) && is_string($valueType)) {
                switch ($valueType) {
                    case 'd':
                        $currentValue = $intValue * 86400;
                        break;
                    case 'h':
                        $currentValue = $intValue * 60 * 60;
                        break;
                    case 'm':
                        $currentValue = $intValue * 60;
                        break;
                    default:
                        break;
                }
            }
        }

        // Always return as integers.
        $return = intval($currentValue);
        if ($return < 300) {
            $return = 21600;
        }

        return $return;
    }

    /**
     * @param $checkout WC_Checkout
     * @return string
     * @todo Add the getaddress field depending on country and checkout type
     */
    public static function resursBankGetAddress($checkout)
    {
        echo sprintf('
        <input type="radio" name="resursbankcustom_getaddress_customertype[]" value="NATURAL" checked="checked"> %s
        <input type="radio" name="resursbankcustom_getaddress_customertype[]" value="LEGAL"> %s
        ', __('Private', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
            __('Company', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce')
        );

        if (self::getCustomerCountry() === 'SE') {
            woocommerce_form_field('resursbankcustom_getaddress_governmentid', array(
                'type' => 'text',
                'class' => array('form-row-wide resurs_ssn_field'),
                'label' => __('Government ID', 'resurs-bank-payment-gateway-for-woocommerce'),
                'placeholder' => __(
                    'Enter your government id (social security number)',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ),
            ), $checkout->get_value('resursbankcustom_address_governmentid'));
        }
    }

    /**
     * @param WC_Order $order
     * @return int|null
     */
    public static function getCustomerId($order)
    {
        $return = null;

        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
        } else {
            $current_user = get_currentuserinfo();
        }

        if (isset($current_user->ID)) {
            $return = $current_user->ID;
        }

        // Created orders has higher priority since this id might have been created during order processing
        if (!is_null($order)) {
            $return = $order->get_user_id();
        }

        return $return;
    }

    /**
     * @param $key
     * @return mixed
     */
    public static function getFlag($key)
    {
        $return = null;

        $pluginFlags = (array)self::getResursOption('pluginFlags');
        if (isset($pluginFlags[$key])) {
            $return = $pluginFlags[$key];
        }

        return $return;
    }

    /**
     * @param $key
     * @param string $value
     */
    public static function setFlag($key, $value = '')
    {
        $pluginFlags = (array)self::getResursOption('pluginFlags');
        $pluginFlags[$key] = $value;
        self::setResursOption('pluginFlags', $pluginFlags);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function deleteFlag($key)
    {
        $return = false;
        $pluginFlags = (array)self::getResursOption('pluginFlags');
        if (isset($pluginFlags[$key])) {
            unset($pluginFlags[$key]);
            self::setResursOption('pluginFlags', $pluginFlags);
            $return = true;
        }
        return $return;
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
        $nextUpdateTimer = (int)self::getResursOption('paymentMethodListTimer');
        if ($nextUpdateTimer < 300) {
            $nextUpdateTimer = 21600;
        }
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
    private static function getStoredPaymentMethodsByCountry($country)
    {
        $CORE = self::getResursCore();
        $request = $CORE->getConnectionByCountry($country);
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
                $methodList = self::getStoredPaymentMethodsByCountry(
                    $country,
                    $credentials,
                    false,
                    $requestEnvironment
                );
            } else {
                if (is_array($credentials)) {
                    $methodList = self::getPaymentMethodsByApi($credentials, $cron);
                } else {
                    throw new \Exception(
                        __(
                            'Request failed due to missing credentials.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ) . ' ' . $country,
                        400
                    );
                }
            }
        } else {
            if (!empty($country) && isset($methodList[$country])) {
                $methodList = $methodList[$country];
                if (isset($methodList[$requestEnvironment])) {
                    $methodList = $methodList[$requestEnvironment];
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
    public static function getCoexistDismissed()
    {
        return self::getResursOption('dismiss_resursbank_coexist_message');
    }

    /**
     * Prepare dynamic options for dismissed notices and elements.
     *
     * @param $configArray
     * @return array
     */
    public static function resursbankGetDismissedElements($configArray)
    {
        $elements = array('dismiss_resursbank_coexist_message');

        $elementArray = array(
            'dismissed' => array(
                'title' => __('Dismissed notices', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                'settings' => array(
                    'dismissed_title' => array(
                        'title' => __(
                            'Restore notices and elements hidden by plugin',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        ),
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
    public static function setDismissedElement($array, $request)
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
                        'Security verification failure.',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ) . ' - ' .
                    __(
                        'You must be administator.',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ), 400
                );
            }
        }

        if (!$return && self::getRequiredNonce($runRequest)) {
            throw new \Exception(
                __(
                    'Security verification failure.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ) . ' - ' .
                __(
                    'Security key mismatch.',
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
    public static function getMethodsFromGateway($woocommerceGateways)
    {
        if (is_array($woocommerceGateways) && !in_array(self::getGatewayClass(), $woocommerceGateways)) {
            $woocommerceGateways[] = self::getGatewayClass();
        }

        return $woocommerceGateways;
    }

    /**
     * Find out where we are. Returns empty string when not in checkout.
     *
     * @param bool $overrideAdmin
     * @return string
     * @TODO Check whether this might be needed in widget-mode (cost example).
     */
    public static function getCustomerCountry($overrideAdmin = false)
    {
        global $woocommerce;
        $currentCustomerCountry = '';

        if (!is_admin() || $overrideAdmin) {
            if (is_checkout()) {
                $currentCustomerCountry = $woocommerce->customer->get_billing_country();
            }
            if (empty($currentCustomerCountry)) {
                // Should not be called statically.
                $WC_Countries = new WC_Countries();
                $currentCustomerCountry = $WC_Countries->get_base_country();
            }
        }

        return $currentCustomerCountry;
    }

    /**
     * Return generated classes for each available Resurs Bank payment method
     *
     * @param $availableGateways
     * @return mixed
     * @throws Exception
     */
    public static function getAvailableGateways($availableGateways)
    {
        $resursCore = self::getResursCore();

        self::getResursCore()->setSession(
            'session_gateway_method_init',
            0
        );

        unset($availableGateways[self::getGatewayClass()]);
        unset($availableGateways['resurs_bank_payment_gateway']);
        try {
            $paymentMethodCountry = self::getCustomerCountry();
            $currentFlow = $resursCore->getFlowByEcom($resursCore->getFlowByCountry($paymentMethodCountry));
            $currentEnvironment = self::getResursOption('environment');

            if ($currentFlow === \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $availableGateways['resursbank_checkout'] = new WC_Resursbank_Method(
                    $currentFlow,
                    $paymentMethodCountry,
                    $resursCore->getConnectionByCountry($paymentMethodCountry)
                );
            } else {
                try {
                    $methods = self::getStoredPaymentMethods($paymentMethodCountry, false, $currentEnvironment);
                    foreach ($methods as $methodIndex => $paymentMethodData) {
                        if ($gateway = self::getGateway($paymentMethodData, $paymentMethodCountry)) {
                            $availableGateways['resursbank_' . $paymentMethodData->id] = $gateway;
                        }
                    }
                } catch (\Exception $paymentMethodsException) {
                }
            }

        } catch (\Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
        }

        return $availableGateways;
    }

    /**
     * @param $paymentMethodData
     * @param $paymentMethodCountry
     * @return WC_Resursbank_Method|null
     * @throws Exception
     */
    private static function getGateway($paymentMethodData, $paymentMethodCountry)
    {
        $resursCore = self::getResursCore();
        $return = null;

        if (Resursbank_Core::getValidatedLimit($paymentMethodData)) {
            $return = new WC_Resursbank_Method(
                $paymentMethodData,
                $paymentMethodCountry,
                $resursCore->getConnectionByCountry($paymentMethodCountry)
            );
        }

        return $return;
    }

    public static function getCart()
    {
        global $woocommerce;

        return get_class($woocommerce->cart) === 'WC_Cart' ? $woocommerce->cart : '';
    }

    /**
     * @return bool
     */
    public static function getValidatedLimit($resursPaymentMethod)
    {
        /** @var WC_Cart $cart */
        $cart = self::getCart();
        $return = true;

        if ($cart->total > $resursPaymentMethod->maxLimit || $cart->total < $resursPaymentMethod->minLimit) {
            $return = false;
        }

        return $return;
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
     *
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
     * @param bool $request
     * @return array
     */
    public static function getPostData($request = false)
    {
        if (!isset($_REQUEST['post_data'])) {
            if (!$request) {
                return array();
            } else {
                return $_REQUEST;
            }
        }
        if (is_string($_REQUEST['post_data'])) {
            parse_str($_REQUEST['post_data'], $postData);
        } else {
            $postData = $_REQUEST['post_data'];
        }

        return $postData;
    }

    /**
     * @return array
     */
    public static function getQueryRequest()
    {
        $query = array();

        // Fetch data from request uri first.
        $request = parse_url($_SERVER['REQUEST_URI']);

        if (!isset($request['query'])) {
            // No query found.
            $request['query'] = '';
        }

        $request['query'] = str_replace('amp;', '', $request['query']);
        parse_str($request['query'], $query);

        // Merge other requestdata into the query.
        foreach ($_REQUEST as $requestKey => $requestValue) {
            $query[$requestKey] = $requestValue;
        }

        return $query;
    }

    /**
     * Get WooCommerce post_data-object.
     *
     * @param bool $request If $_POST[post_data] is missing, try use the entire $_REQUEST-object instead.
     * @param null $specificKey
     * @return array
     */
    public static function getDefaultPostDataParsed($request = false, $specificKey = null)
    {
        $postData = self::getPostData($request);
        $newData = array();

        if (is_array($postData) && count($postData)) {
            foreach ($postData as $key => $val) {
                $keySplit = explode('_', $key, 2);
                if (isset($keySplit[1])) {
                    if (!isset($newData[$keySplit[0]])) {
                        $newData[$keySplit[0]] = array();
                    }
                    // Making precautions to not overwrite already set data.
                    if (empty($newData[$keySplit[0]][$keySplit[1]])) {
                        $newData[$keySplit[0]][$keySplit[1]] = $val;
                    }
                } else {
                    $newData[$key] = $val;
                }
            }
        }

        if (!is_null($specificKey) && isset($newData[$specificKey])) {
            return $newData[$specificKey];
        }

        return $newData;
    }

    /**
     * Find out if checkout customer has filled in some field that can be considered LEGAL payment.
     *
     * @return bool
     */
    public static function getIsLegal()
    {
        $postData = self::getDefaultPostDataParsed(true);
        $resursData = self::getResursCustomPostData();
        $return = false;

        if (!count($postData) || !isset($postData['billing'])) {
            return false;
        }

        if (is_array($postData['billing']) &&
            isset($postData['billing']['company']) &&
            !empty($postData['billing']['company'])
        ) {
            $return = true;
        } elseif (isset($resursData['getaddress_customertype']) && is_array($resursData['getaddress_customertype']) &&
            in_array('LEGAL', $resursData['getaddress_customertype'])
        ) {
            $return = true;
        }

        return $return;
    }

    /**
     * Get Resurs Bank customized payment fields.
     *
     * @param bool $request
     * @return array
     */
    public static function getResursCustomPostData($request = false)
    {
        $postData = self::getDefaultPostDataParsed($request);
        $return = array();
        if (isset($postData['resursbankcustom']) && is_array($postData['resursbankcustom'])) {
            $return = $postData['resursbankcustom'];
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
     * Activates bootstrap for getCostOfPurchaseHtml, via CDN.
     *
     * @param $cssArray
     * @return array
     */
    public static function getCostOfPurchaseCss($cssArray)
    {
        $useBootStrap = (bool)self::getResursOption('getCostOfPurchaseBootstrap');

        if ($useBootStrap) {
            $cssArray[] = 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css';
        }

        return $cssArray;
    }

    /**
     * @return string
     */
    private static function getCostOfPurchaseHtmlBefore()
    {
        $buttonCssClasses = apply_filters(
            'resursbank_readmore_button_css_class',
            'btn btn-info active woocommerce button'
        );

        return sprintf(
            '<div class="cost-of-purchase-box">
                <button class="%s" type="button" onclick="window.close()">
                %s
                </button>
                ',
            $buttonCssClasses,
            __(
                'Close',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            )
        );
    }

    private static function resursbankCostOfPurchaseCss()
    {
        return (array)apply_filters(
            'resursbank_cost_of_purchase_css',
            array(_RESURSBANK_GATEWAY_URL . 'css/costofpurchase.css')
        );
    }

    /**
     * @throws Exception
     */
    public static function getCostOfPurchaseHtml()
    {
        $CORE = self::getResursCore();

        $method = self::getDefaultPostDataParsed(true, 'method');
        $amount = self::getDefaultPostDataParsed(true, 'amount');

        if (empty($method)) {
            throw new Exception(
                __(
                    'No payment method has been selected.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ),
                400
            );
        }

        $useCssStyle = self::resursbankCostOfPurchaseCss();
        $API = $CORE->getConnectionByCountry(self::getCustomerCountry(true));
        $API->setCostOfPurcaseHtmlBefore(self::getCostOfPurchaseHtmlBefore());
        $API->setCostOfPurcaseHtmlAfter('</div>');
        try {
            echo $API->getCostOfPurchase($method, $amount, true, $useCssStyle);
        } catch (Exception $e) {
            echo __(
                'The cost of purchase module is not available right now. Please try again later.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );
        }
        die;
    }

    /**
     * Prepare enqueing
     */
    public static function setResursBankScripts()
    {
        $CORE = self::getResursCore();
        $varsToLocalize = array(
            'resurs_bank_payment_gateway' => array(
                'available' => true,
                'graphics' => Resursbank_Core::getGraphics(),
                'backend_url' => _RESURSBANK_GATEWAY_BACKEND,
                'backend_nonce' => wp_nonce_url(
                    _RESURSBANK_GATEWAY_BACKEND,
                    'resursBankBackendRequest',
                    'resursBankGatewayNonce'
                ),
                'getCostOfPurchaseBackendUrl' => _RESURSBANK_GATEWAY_BACKEND . '&run=get_cost_of_purchase_html',
                'suggested_flow' => $CORE->getFlowByCountry(self::getCustomerCountry())
            )
        );

        wp_enqueue_style(
            'resurs_bank_payment_gateway_css',
            _RESURSBANK_GATEWAY_URL . 'css/resursbank.css',
            array(),
            _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
        );

        if (is_checkout()) {
            wp_enqueue_script(
                'resurs_bank_payment_checkout_js',
                _RESURSBANK_GATEWAY_URL . 'js/resurscheckout.js',
                array(
                    'jquery',
                ),
                _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
            );
        }

        wp_enqueue_script(
            'resurs_bank_payment_gateway_js',
            _RESURSBANK_GATEWAY_URL . 'js/resursbank.js',
            array(
                'jquery',
            ),
            _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
        );

        if (is_checkout()) {
            wp_enqueue_script(
                'resurs_bank_payment_woocommerce_js',
                _RESURSBANK_GATEWAY_URL . 'js/woocommerce.js',
                array(
                    'jquery',
                ),
                _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
            );
        }

        if (is_admin()) {
            wp_enqueue_script(
                'resurs_bank_payment_gateway_admin_js',
                _RESURSBANK_GATEWAY_URL . 'js/resursadmin.js',
                array(
                    'jquery',
                ),
                _RESURSBANK_GATEWAY_VERSION . (self::getDeveloperMode() ? '-' . time() : '')
            );
        }

        if (is_array($varsToLocalize)) {
            foreach ($varsToLocalize as $varKey => $varArray) {
                wp_localize_script('resurs_bank_payment_gateway_js', $varKey, $varArray);
            }
        }

        if ($iFrameUrl = self::getIframeUrl() && !is_admin()) {
            wp_localize_script(
                'resurs_bank_payment_gateway_js',
                'RESURSCHECKOUT_IFRAME_URL',
                (array)$iFrameUrl
            );
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public static function getIframeUrl()
    {
        // Do not do this in admin.
        if (is_admin()) {
            return;
        }
        $CORE = self::getResursCore();
        $iFrameUrl = '';

        // Fetch RCO iframe URL and push it to js. This usually fails on initial plugin startups, when
        // no credentials are preset.
        try {
            $iFrameUrl = self::getResursCore()->getConnectionByCountry(
                self::getCustomerCountry()
            )->getCheckoutUrl(
                $CORE->getEcomEnvironment()
            );
        } catch (\Exception $iframeUrlException) {
        }

        return $iFrameUrl;
    }

    /**
     * Allow or disable coexisting plugins via dynamic configuration
     *
     * @return bool
     */
    public static function resursObsoleteCoexistenceDisable()
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

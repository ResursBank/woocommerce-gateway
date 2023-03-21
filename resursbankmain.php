<?php

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/v3core.php');
require_once(__DIR__ . '/functions_settings.php');
require_once(__DIR__ . '/functions_gateway.php');

use Resurs\WooCommerce\Service\Queue\QueueHandler;
use Resursbank\Ecommerce\Types\Callback;
use Resursbank\Ecommerce\Types\CheckoutType;
use Resursbank\Ecommerce\Types\OrderStatus;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Model\Type\DataType;
use TorneLIB\Model\Type\RequestMethod;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;
use TorneLIB\MODULE_NETWORK;
use TorneLIB\Utils\Security;

$resurs_obsolete_coexistence_disable = (bool)apply_filters('resurs_obsolete_coexistence_disable', null);
if ($resurs_obsolete_coexistence_disable && !is_admin()) {
    return;
}

$resursGlobalNotice = false;

/**
 * Initialize Resurs Bank Plugin when plugins is finally loaded
 */
function woocommerce_gateway_resurs_bank_init()
{
    $enabled = true;
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    if (class_exists('WC_Resurs_Bank')) {
        return;
    }
    $enabled = apply_filters('resurs_bank_init_enabled', $enabled);
    if (!$enabled) {
        return;
    }

    /**
     * Class WC_Resurs_Bank
     */
    class WC_Resurs_Bank extends WC_Payment_Gateway
    {
        /** @var ResursBank */
        protected $flow;
        protected $rates;
        private $callback_types;
        private $baseLiveURL;
        private $baseTestURL;
        private $serverEnv;

        /**
         * Constructor method for Resurs Bank plugin.
         *
         * This method initializes various properties and fetches payment
         * methods, either from the transient API or from Resurs Bank API.
         * It is also responsible for calling generate_payment_gateways,
         * if these need to be refreshed.
         *
         */
        public function __construct()
        {
            add_action('woocommerce_api_wc_resurs_bank', [$this, 'check_callback_response']);

            // Payment method area
            add_filter(
                'woocommerce_update_order_review_fragments',
                [
                    $this,
                    'resursBankInheritOrderReviewFragments',
                ],
                10,
                1
            );

            hasResursOmni();

            //$this->title = "Resurs Bank";
            $this->id = 'resurs-bank';
            $this->method_title = 'Resurs Bank Administration';

            $this->method_description = __(
                'Resurs Bank gateway configuration for WooCommerce.',
                'resurs-bank-payment-gateway-for-woocommerce'
            );

            $this->has_fields = false;
            $this->callback_types = $this->getCallbackTypes();
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->login = $this->get_option('login');
            $this->password = $this->get_option('password');
            $this->baseLiveURL = $this->get_option('baseLiveURL');
            $this->baseTestURL = $this->get_option('baseTestURL');
            $this->serverEnv = $this->get_option('serverEnv');

            $this->flowOptions = null;

            if (hasEcomPHP()) {
                if (!empty($this->login) && !empty($this->password)) {
                    /** @var ResursBank */
                    $this->flow = initializeResursFlow();

                    $setSessionEnable = true;
                    $setSession = isset($_REQUEST['set-no-session']) ? $_REQUEST['set-no-session'] : null;
                    if ($setSession === 1) {
                        $setSessionEnable = false;
                    } else {
                        $setSessionEnable = true;
                    }

                    /*
                     * Not using is_checkout() since themes may not work the same way.
                     * In some cases, there won't be any session set if this is done. So we'll look for
                     * the session instead.
                     */
                    if (isset(WC()->session) && $setSessionEnable) {
                        $omniRef = $this->flow->getPreferredPaymentId(25, 'RC');
                        $newOmniRef = $omniRef;
                        $currentOmniRef = WC()->session->get('omniRef');
                        $omniId = WC()->session->get('omniid');
                        if (isset($_REQUEST['event-type']) &&
                            $_REQUEST['event-type'] == 'prepare-omni-order' &&
                            isset($_REQUEST['orderRef']) &&
                            !empty($_REQUEST['orderRef'])
                        ) {
                            $omniRef = $_REQUEST['orderRef'];
                            $currentOmniRefAge = 0;
                            $omniRefCreated = time();
                        }

                        $omniRefCreated = WC()->session->get('omniRefCreated');
                        $currentOmniRefAge = time() - $omniRefCreated;
                        // $currentOmniRefAge > 900
                        if (empty($currentOmniRef)) {
                            // Create by empty references.
                            WC()->session->set('omniRef', $omniRef);
                            WC()->session->set('omniRefCreated', time());
                            WC()->session->set('omniRefAge', $currentOmniRefAge);
                        }
                    } else {
                        if (isset($_REQUEST['omnicheckout_nonce']) &&
                            wp_verify_nonce(
                                $_REQUEST['omnicheckout_nonce'],
                                'omnicheckout'
                            )) {
                            if (isset($_REQUEST['purchaseFail']) && $_REQUEST['purchaseFail'] == 1) {
                                $returnResult = [
                                    'success' => false,
                                    'errorString' => '',
                                    'errorCode' => '',
                                    'verified' => false,
                                    'hasOrder' => false,
                                    'resursData' => [],
                                    'denied' => isset($_REQUEST['purchaseDenied']) ? (bool)$_REQUEST['purchaseDenied'] : false,
                                ];
                                if (isset($_GET['pRef'])) {
                                    $purchaseFailOrderId = wc_get_order_id_by_payment_id($_GET['pRef']);
                                    $purchareFailOrder = new WC_Order($purchaseFailOrderId);
                                    if (!$returnResult['denied']) {
                                        $purchareFailOrder->update_status(
                                            'failed',
                                            __(
                                                'Resurs Bank denied purchase',
                                                'resurs-bank-payment-gateway-for-woocommerce'
                                            )
                                        );
                                    } else {
                                        update_post_meta($purchaseFailOrderId, 'soft_purchase_denied', true);
                                        $purchareFailOrder->update_status(
                                            'failed: denied',
                                            __(
                                                'Resurs Bank denied purchase',
                                                'resurs-bank-payment-gateway-for-woocommerce'
                                            )
                                        );
                                    }
                                    update_post_meta($purchaseFailOrderId, 'soft_purchase_fail', true);
                                    WC()->session->set('resursCreatePass', 0);
                                    $returnResult['success'] = true;
                                    $returnResult['errorString'] = 'Denied by Resurs';
                                    $returnResult['errorCode'] = '200';
                                    $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                                    die();
                                }
                                $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                                die();
                            }
                        }
                    }
                }
            }

            if (hasWooCommerce('2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
                    $this,
                    'process_admin_options',
                ]);
            } else {
                add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
            }

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        // Payment method area

        /**
         * @param $fragments
         * @return mixed
         * @throws Exception
         */
        public function resursBankInheritOrderReviewFragments($fragments)
        {
            // When order is in review state, we can consider the last action as "in checkout".
            ResursBank3_PreCore::setCustomerIsInCheckout();

            return $fragments;
        }

        /**
         * @return array
         */
        public function getCallbackTypes()
        {
            return [
                'UNFREEZE' => [
                    'uri_components' => [
                        'paymentId' => 'paymentId',
                    ],
                    'digest_parameters' => [
                        'paymentId' => 'paymentId',
                    ],
                ],
                'BOOKED' => [
                    'uri_components' => [
                        'paymentId' => 'paymentId',
                    ],
                    'digest_parameters' => [
                        'paymentId' => 'paymentId',
                    ],
                ],
                'UPDATE' => [
                    'uri_components' => [
                        'paymentId' => 'paymentId',
                    ],
                    'digest_parameters' => [
                        'paymentId' => 'paymentId',
                    ],
                ],
                'TEST' => [
                    'uri_components' => [
                        'prm1' => 'param1',
                        'prm2' => 'param2',
                        'prm3' => 'param3',
                        'prm4' => 'param4',
                        'prm5' => 'param5',
                    ],
                    'digest_parameters' => [
                        'parameter1' => 'param1',
                        'parameter2' => 'param2',
                        'parameter3' => 'param3',
                        'parameter4' => 'param4',
                        'parameter5' => 'param5',
                    ],
                ],
            ];
        }

        /**
         * Are we in omni mode?
         *
         * @return bool
         */
        public function isResursOmni()
        {
            // Returned from somewhere else
            return isResursOmni();
        }

        /**
         * Initialize the form fields for the plugin
         */
        public function init_form_fields()
        {
            $this->form_fields = getResursWooFormFields();

            /*
             * In case of upgrades where defaults are not yet set, automatically set them up.
             */
            if (!hasResursOptionValue('getAddress')) {
                setResursOption('getAddress', 'true');
            }
            if (!hasResursOptionValue('getAddressUseProduction')) {
                setResursOption('getAddressUseProduction', 'false');
            }
            if (!hasResursOptionValue('streamlineBehaviour')) {
                setResursOption('streamlineBehaviour', 'true');
            }

            if (!isResursDemo()) {
                unset($this->form_fields['getAddressUseProduction'], $this->form_fields['ga_login'], $this->form_fields['ga_password']);
            }

            if (isset($this->form_fields['flowtype']) && isset($this->form_fields['flowtype']['options']) && is_array($this->form_fields['flowtype']['options']) && isset($this->form_fields['flowtype']['options']['resurs_bank_omnicheckout'])) {
                unset($this->form_fields['flowtype']['options']['resurs_bank_omnicheckout']);
            }
        }

        /**
         * Check the callback event received and perform the appropriate action
         *
         * @throws Exception
         */
        public function check_callback_response()
        {
            global $wpdb;

            $mySession = false;
            $url_arr = parse_url($_SERVER['REQUEST_URI']);
            $url_arr['query'] = str_replace('amp;', '', $url_arr['query']);
            parse_str($url_arr['query'], $request);
            if (!is_array($request)) {
                $request = [];
            } else {
                if (count($request) === 1 && isset($request['wc-api'])) {
                    echo '<div style="width: 800px;">' . __(
                            'Something went wrong during what we suppose should have been a redirect somewhere. This URL should contain much more data than the WC_Resurs_Bank-parameter if it should be considered a proper redirect. Please, contact support if you land here.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '</div>';
                    die();
                }
            }
            if (!count($request) && isset($_GET['event-type'])) {
                $request = $_GET;
            }
            $event_type = isset($request['event-type']) ? $request['event-type'] : '';

            if ($event_type == 'TEST') {
                set_transient('resurs_callbacks_received', time());
                set_transient('resurs_callbacks_content', $_REQUEST);
                header('HTTP/1.0 204 CallbackWithoutDigestTriggerOK');
                die();
            }

            if ($event_type === 'noevent') {
                $myResponse = null;
                $myBool = false;
                $errorMessage = '';
                $errorCode = null;

                $setType = isset($_REQUEST['puts']) ? $_REQUEST['puts'] : '';
                $setValue = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
                $reqNamespace = isset($_REQUEST['ns']) ? $_REQUEST['ns'] : '';
                $reqType = isset($_REQUEST['wants']) ? $_REQUEST['wants'] : '';
                $reqNonce = isset($_REQUEST['ran']) ? $_REQUEST['ran'] : '';

                $newPaymentMethodsList = null;
                $envVal = null;
                if (!empty($reqType) || !empty($setType)) {
                    if (wp_verify_nonce($reqNonce, 'requestResursAdmin') && !empty($reqType)) {
                        $mySession = true;
                        $reqType = str_replace($reqNamespace . '_', '', $reqType);
                        $myBool = true;
                        $myResponse = getResursOption($reqType);
                        if (empty($myResponse)) {
                            // Make sure this returns a string and not a bool.
                            $myResponse = '';
                        }
                    } elseif (!empty($setType)) {
                        // Prevent weird errors with nonces.
                        $skipNonceErrors = getResursFlag('SKIP_NONCE_ERRORS') ? true : false;
                        if ($skipNonceErrors || wp_verify_nonce($reqNonce, 'requestResursAdmin')) {
                            $mySession = true;
                            $failSetup = false;
                            $subVal = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
                            $envVal = isset($_REQUEST['e']) ? $_REQUEST['e'] : '';
                            if ($setType === 'woocommerce_resurs-bank_password') {
                                $testUser = $subVal;
                                $testPass = $setValue;
                                $flowEnv = getServerEnv();
                                if (!empty($envVal)) {
                                    if ($envVal === 'test') {
                                        $flowEnv = RESURS_ENVIRONMENTS::TEST;
                                    } elseif ($envVal === 'live') {
                                        $flowEnv = RESURS_ENVIRONMENTS::PRODUCTION;
                                    } elseif ($envVal === 'production') {
                                        $flowEnv = RESURS_ENVIRONMENTS::PRODUCTION;
                                    }
                                    $newFlow = initializeResursFlow(
                                        $testUser,
                                        $testPass,
                                        $flowEnv,
                                        true
                                    );
                                } else {
                                    // Default to test (we need the extra params here to set "force new flow" during those tests
                                    // regardless of if the ecom-flow should be reused.
                                    $newFlow = initializeResursFlow(
                                        $testUser,
                                        $testPass,
                                        RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET,
                                        true
                                    );
                                }
                                try {
                                    $newPaymentMethodsList = $newFlow->getPaymentMethods([], true);
                                    $myBool = true;
                                } catch (Exception $e) {
                                    if ($newFlow->hasTimeoutException()) {
                                        set_transient('resurs_connection_timeout', time(), 60);
                                    }
                                    $myBool = false;
                                    $failSetup = true;
                                    /** @var $errorMessage */
                                    $errorMessage = $e->getMessage();
                                    /** @var $prevError Exception */
                                    $prevError = $e->getPrevious();
                                    if (!empty($prevError)) {
                                        $errorMessage = $prevError->getMessage();
                                    }
                                }
                            }
                            if (isset($newPaymentMethodsList['error']) && !empty($newPaymentMethodsList['error'])) {
                                $failSetup = true;
                                $errorMessage = $newPaymentMethodsList['error'];
                                $myBool = false;
                            }
                            $setType = str_replace($reqNamespace . '_', '', $setType);
                            if (!$failSetup) {
                                $myBool = true;
                                setResursOption($setType, $setValue);
                                setResursOption('login', $subVal);
                                if (!empty($envVal)) {
                                    setResursOption('serverEnv', $envVal);
                                }

                                setResursOption('resursAnnuityDuration', 0);
                                setResursOption('resursAnnuityMethod', '');
                                setResursOption('resursCurrentAnnuityFactors', []);
                                delete_transient('resursTemporaryPaymentMethodsTime');
                                delete_transient('resursTemporaryPaymentMethods');

                                // Set up a maintenance "grace period" for where the front page is not allowed
                                // to run entirely, due to the risk of bad orders when changing credentials.
                                // The grace period is by default 20 sec, but can be changed from configuration.
                                $gracePeriod = getResursOption('credentialsMaintenanceTimeout');
                                // Move to the section where we set up stuff.
                                if ((int)$gracePeriod > 0) {
                                    set_transient('rb_credentials_update', 1, $gracePeriod);
                                }

                                $myResponse['element'] = ['currentResursPaymentMethods', 'callbackContent'];
                                set_transient('resurs_bank_last_callback_setup', 0);
                                $myResponse['html'] = '<br>' .
                                    '<div class="labelBoot labelBoot-success labelBoot-big labelBoot-nofat labelBoot-center">' . __(
                                        'All clear. Annuity factors has been reset! Do not forget to save your settings.',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ) . '</div><br><br>';
                            }
                        }
                    }
                } else {
                    if (isset($_REQUEST['run'])) {
                        // If there is complications with nonce checking, the flag ADMIN_NONCE_IGNORE makes it possible
                        // for admins to disable this check temporarily, since there has been problems with it
                        // recently.
                        $adminNonceIgnore = (bool)getResursFlag('ADMIN_NONCE_IGNORE');
                        if ($adminNonceIgnore || wp_verify_nonce($reqNonce, 'requestResursAdmin')) {
                            $mySession = true;
                            $arg = null;
                            if (isset($_REQUEST['arg'])) {
                                $arg = $_REQUEST['arg'];
                            }
                            $responseArray = [];
                            if ($_REQUEST['run'] == 'updateResursPaymentMethods') {
                                try {
                                    $responseArray = true;
                                } catch (Exception $e) {
                                    $errorMessage = $e->getMessage();
                                }
                            } elseif ($_REQUEST['run'] == 'annuityDuration') {
                                $data = isset($_REQUEST['data']) ? $_REQUEST['data'] : null;
                                // Making sure we only accept numeric requests on annuity.
                                if (!empty($data) && is_numeric($data)) {
                                    setResursOption('resursAnnuityDuration', $data);
                                }
                            } elseif ($_REQUEST['run'] == 'annuityToggle') {
                                $priorAnnuity = getResursOption('resursAnnuityMethod');
                                $annuityFactors = $this->flow->getAnnuityFactors($arg);
                                setResursOption('resursCurrentAnnuityFactors', $annuityFactors);
                                $selectorOptions = '';
                                // Also kill self
                                $scriptit = 'resursRemoveAnnuityElements(\'' . $arg . '\')';
                                if ($priorAnnuity == $arg) {
                                    $selector = '';
                                    $responseHtml = '<span id="annuityClick_' . $arg . '" class="status-disabled tips" data-tip="' . __(
                                            'Disabled',
                                            'woocommerce'
                                        ) . '" onclick="runResursAdminCallback(\'annuityToggle\', \'' . $arg . '\');' . $scriptit . ';">-</span>' . "\n" . $selector;
                                    setResursOption('resursAnnuityMethod', '');
                                    setResursOption('resursAnnuityDuration', '');
                                    $isEnabled = 'no';
                                } else {
                                    if (is_array($annuityFactors) && count($annuityFactors)) {
                                        $firstDuration = '';
                                        foreach ($annuityFactors as $factor) {
                                            if (!$firstDuration) {
                                                $firstDuration = $factor->duration;
                                            }
                                            $selectorOptions .= '<option value="' . $factor->duration . '">' . $factor->paymentPlanName . '</option>';
                                        }
                                        setResursOption('resursAnnuityMethod', $arg);
                                        setResursOption('resursAnnuityDuration', $firstDuration);
                                    }
                                    $isEnabled = 'yes';
                                    $selector = '<select class="resursConfigSelectShort" id="annuitySelector_' . $arg . '" onchange="runResursAdminCallback(\'annuityDuration\', \'' . $arg . '\', this.value)">' . $selectorOptions . '</select>';
                                    $responseHtml = '<span id="annuityClick_' . $arg . '" class="status-enabled tips" data-tip="' . __(
                                            'Enabled',
                                            'woocommerce'
                                        ) . '" onclick="runResursAdminCallback(\'annuityToggle\', \'' . $arg . '\');' . $scriptit . ';">-</span>' . "\n" . $selector;
                                }
                                $responseArray['valueSet'] = $isEnabled;
                                $responseArray['element'] = 'annuity_' . $arg;
                                $responseArray['html'] = $responseHtml;
                            } elseif ($_REQUEST['run'] == 'methodToggle') {
                                $dbMethodName = 'woocommerce_resurs_bank_nr_' . $arg . '_settings';
                                $responseMethod = get_option($dbMethodName);
                                if (is_array($responseMethod) && count($responseMethod)) {
                                    $myBool = true;
                                    $isEnabled = $responseMethod['enabled'];
                                    if ($isEnabled == 'yes' || $isEnabled == 'true' || $isEnabled == '1') {
                                        $isEnabled = 'no';
                                        $responseHtml = '<span class="status-disabled tips" data-tip="' . __(
                                                'Disabled',
                                                'woocommerce'
                                            ) . '">-</span>';
                                    } else {
                                        $isEnabled = 'yes';
                                        $responseHtml = '<span class="status-enabled tips" data-tip="' . __(
                                                'Enabled',
                                                'woocommerce'
                                            ) . '">-</span>';
                                    }
                                    setResursOption('enabled', $isEnabled, $dbMethodName);
                                    $responseArray['valueSet'] = $isEnabled;
                                    $responseArray['element'] = 'status_' . $arg;
                                    $responseArray['html'] = $responseHtml;
                                } else {
                                    $errorMessage = __(
                                        'Configuration has not yet been initiated.',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    );
                                }
                            } elseif ($_REQUEST['run'] == 'getRefundCapability') {
                                $refundable = '';
                                if (isset($_REQUEST['data']) && isset($_REQUEST['data']['paymentId'])) {
                                    try {
                                        canResursRefund($_REQUEST['data']['paymentId']);
                                    } catch (Exception $e) {
                                        if ($e->getCode() === 1234) {
                                            // Only return false at this point if this is a special payment.
                                            // Some payment methods does not allow refunding and this is stated here only.
                                            $refundable = 'no';
                                        }
                                    }
                                    $responseArray = [
                                        'refundable' => $refundable,
                                    ];
                                }
                            } elseif ($_REQUEST['run'] === 'getMyCallbacks') {
                                $responseArray = [
                                    'callbacks' => [],
                                ];
                                $login = getResursOption('login');
                                $password = getResursOption('password');

                                if (!empty($login) && !empty($password)) {
                                    $lastFetchedCacheTime = time() - get_transient('resurs_callback_templates_cache_last');
                                    $lastFetchedCache = get_transient('resurs_callback_templates_cache');
                                    $_REQUEST['force'] = true;
                                    if ($lastFetchedCacheTime >= 86400 || empty($lastFetchedCache) || isset($_REQUEST['force'])) {
                                        try {
                                            $responseArray['callbacks'] = $this->flow->getCallBacksByRest(true);
                                            set_transient('resurs_callback_templates_cache_last', time());
                                            $myBool = true;
                                            set_transient(
                                                'resurs_callback_templates_cache',
                                                $responseArray['callbacks']
                                            );
                                            $responseArray['cached'] = false;
                                        } catch (Exception $e) {
                                            if ($this->flow->hasTimeoutException()) {
                                                set_transient('resurs_connection_timeout', time(), 60);
                                            }

                                            $errorMessage = $e->getMessage();
                                            $errorCode = $e->getCode();

                                            // Extra controller of curl, as we MIGHT get wrong error codes here
                                            // when streams fails the connection.
                                            if (!function_exists('curl_init') || !function_exists('curl_exec')) {
                                                $errorCode = 500;
                                                $errorMessage = 'curl failed';
                                            }
                                        }
                                    } else {
                                        $myBool = true;
                                        $responseArray['callbacks'] = $lastFetchedCache;
                                        $responseArray['cached'] = true;
                                    }
                                }
                            } elseif ($_REQUEST['run'] == 'setMyCallbacks') {
                                $responseArray = [];
                                $login = getResursOption('login');
                                $password = getResursOption('password');
                                if (!empty($login) && !empty($password)) {
                                    set_transient('resurs_bank_last_callback_setup', time());
                                    try {
                                        $salt = uniqid(mt_rand(), true);
                                        // Deprecation of transient storage.
                                        //set_transient('resurs_bank_digest_salt', $salt);
                                        setResursOption('resurs_bank_digest_salt', $salt, 'wc_resurs2_salt');
                                        $regCount = 0;
                                        $responseArray['registeredCallbacks'] = 0;
                                        $rList = [];
                                        set_transient('resurs_callback_templates_cache_last', 0);
                                        // Unregister callbacks if they are not already removed.
                                        try {
                                            $this->flow->unregisterEventCallback(
                                                Callback::AUTOMATIC_FRAUD_CONTROL &
                                                Callback::ANNULMENT &
                                                Callback::FINALIZATION,
                                                true
                                            );
                                        } catch (Exception $e) {
                                        }

                                        foreach ($this->callback_types as $callback => $options) {
                                            $setUriTemplate = $this->register_callback($callback, $options);
                                            $rList[$callback] = $setUriTemplate;
                                            $regCount++;
                                        }
                                        if ($regCount > 0) {
                                            $myBool = true;
                                        }
                                        set_transient('resurs_callbacks_sent', time());
                                        $triggeredTest = $this->flow->triggerCallback();
                                        $responseArray['registeredCallbacks'] = $regCount;
                                        $responseArray['registeredTemplates'] = $rList;
                                        $responseArray['testTriggerActive'] = $triggeredTest;
                                        $responseArray['testTriggerTimestamp'] = date(
                                            'Y-m-d (H:i:s)',
                                            time()
                                        );
                                    } catch (Exception $e) {
                                        $responseArray['errorstring'] = $e->getMessage();
                                    }
                                }
                            } elseif ($_REQUEST['run'] == 'getRbIpInfo') {
                                $NET = new NetWrapper();
                                $curlInfoResponse = null;
                                $errorMessage = '';
                                try {
                                    $curlInfo = $NET->request('https://ipv4.netcurl.org/');
                                    $curlInfoResponse = $curlInfo->getParsed();
                                } catch (Exception $e) {
                                    $errorMessage = sprintf('Exception (%s): %s' . $e->getCode(), $e->getMessage());
                                }
                                $soapResponded = 'No';
                                try {
                                    // Check if curl is available before using it.
                                    Security::getCurrentFunctionState('curl_init');
                                    Security::getCurrentFunctionState('curl_exec');
                                    $curlDriver = new CurlWrapper();
                                    $leaveTracesRequest = $curlDriver->request(
                                        'https://test.resurs.com/ecommerce-test/ws/V4/ConfigurationService?wsdl',
                                        [],
                                        RequestMethod::GET,
                                        DataType::JSON
                                    );
                                    $responseFromRequest = $leaveTracesRequest->getBody();
                                    if ((bool)preg_match('/<?xml/i', $responseFromRequest)) {
                                        $soapResponded = 'Yes';
                                    }
                                } catch (Exception $e) {
                                    $soapResponded = sprintf('%s (%s)', $e->getMessage(), $e->getCode());
                                }
                                $showInfo = ['ip', 'host', 'SSL_PROTOCOL'];
                                $curlInfoReturn = [];
                                $responseArray['errormessage'] = '';
                                if (is_object($curlInfoResponse)) {
                                    foreach ($showInfo as $key) {
                                        if (isset($curlInfoResponse->{$key})) {
                                            $curlInfoReturn[$key] = sprintf(
                                                '<b>%s</b>: %s', $key,
                                                $curlInfoResponse->{$key}
                                            );
                                        }
                                    }
                                } else {
                                    if (empty($errorMessage)) {
                                        $errorMessage = __(
                                            'Could not reach service right now. Are your server connected or allowed to do outgoing traffic?',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        );
                                    }
                                    $responseArray['errormessage'] = $errorMessage;
                                }
                                $curlInfoReturn['soapResponded'] = '<b>' . sprintf(
                                        'Resurs Request contains XML: %s',
                                        $soapResponded
                                    ) . '</b>';
                                $responseArray['externalinfo'] = implode(",<br>\n", $curlInfoReturn);
                            } elseif ($_REQUEST['run'] == 'getNetCurlTag') {
                                $NET = new MODULE_NETWORK();
                                $curlTags = $NET->getGitTagsByUrl('https://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git');
                                $responseArray['netCurlTag'] = is_array($curlTags) && count($curlTags) ? array_pop($curlTags) : [];
                            } elseif ($_REQUEST['run'] == 'getEcomTag') {
                                $NET = new MODULE_NETWORK();
                                $ecomTags = $NET->getGitTagsByUrl('https://bitbucket.org/resursbankplugins/resurs-ecomphp.git');
                                $responseArray['ecomTag'] = is_array($ecomTags) && count($ecomTags) ? array_pop($ecomTags) : [];
                            } elseif ($_REQUEST['run'] == 'resursTriggerTest') {
                                set_transient('resurs_callbacks_sent', time());
                                set_transient('resurs_callbacks_received', 0);
                                $triggeredTest = $this->flow->triggerCallback();
                                $responseArray['errorstring'] = '';
                                $responseArray['testTriggerActive'] = $triggeredTest;
                                $responseArray['testTriggerTimestamp'] = date(
                                    'Y-m-d (H:i:s)',
                                    time()
                                );
                                $boxColor = 'labelBoot labelBoot-danger';

                                $responseArray['html'] = sprintf(
                                    '<div class="labelBoot %s" style="margin-bottom:5px; margin-top: 5px; font-size:14px;">
                                        %s</div>
                                        ',
                                    $boxColor,
                                    __(
                                        'Waiting for callback',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    )
                                );

                                $responseArray['html'] = apply_filters(
                                    'resurs_trigger_test_callback',
                                    $responseArray['html']
                                );
                            } elseif ($_REQUEST['run'] == 'getLastCallbackTimestamp') {
                                // Timestamp of when callback received this platform
                                $lastRecv = get_transient('resurs_callbacks_received');
                                if ($lastRecv > 0) {
                                    // Content of what the callback received.
                                    $cbContent = get_transient('resurs_callbacks_content');

                                    // Timestamp used on callback registration.
                                    $transLastTs = get_transient('resurs_bank_callback_ts');

                                    $myBool = true;
                                    $responseArray['element'] = 'lastCbRec';

                                    $translation = [
                                        'ok' => __(
                                            'OK',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ),
                                        'firstcall' => __(
                                            'Waiting...',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ),
                                        'waiting' => __(
                                            'Waiting...',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ),
                                        'notYetReceived' => __(
                                            'Not yet received',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ),
                                    ];
                                    $lastTimeText = __(
                                        'Last received test trigger: ',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    );
                                    $never = false;
                                    $responseText = $translation['notYetReceived'];
                                    if ($lastRecv > 0) {
                                        $ts = date('Y-m-d, H:i:s', $lastRecv);
                                    } else {
                                        $never = true;
                                        $ts = __(
                                            'Never or not yet.',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        );
                                    }

                                    $boxColor = 'labelBoot-success';
                                    $responseArray['notimers'] = false;

                                    $responseArray['proceed'] = false;
                                    if (isset($cbContent['ts'])) {
                                        if (empty($transLastTs)) {
                                            $boxColor = 'labelBoot-warning';
                                            $responseText = $translation['firstcall'];
                                        } elseif ($transLastTs !== $cbContent['ts']) {
                                            $responseText = $translation['notYetReceived'];
                                            $boxColor = 'labelBoot-danger';
                                        } else {
                                            if ($never) {
                                                $responseArray['proceed'] = true;
                                                $responseText = $translation['notYetReceived'];
                                                $boxColor = 'labelBoot-danger';
                                            } else {
                                                $boxColor = 'labelBoot-success';
                                                $responseText = $translation['ok'];
                                                $responseArray['notimers'] = true;
                                            }
                                        }
                                    }

                                    $responseArray['html'] = sprintf(
                                        '<div style="margin-bottom:5px; margin-top: 5px; font-size:14px;">
                                        <span id="receivedCallbackConfirm" class="labelBoot %s" style="font-size: 14px !important;">
                                        %s (%s %s)
                                        </span></div>',
                                        $boxColor,
                                        $responseText,
                                        $lastTimeText,
                                        $ts
                                    );

                                    $responseArray['html'] = apply_filters(
                                        'resurs_trigger_test_callback_timestamp',
                                        $responseArray['html']
                                    );
                                }
                            } elseif ($_REQUEST['run'] == 'cleanRbSettings') {
                                $numDel = $wpdb->query('DELETE FROM ' . $wpdb->options . " WHERE option_name LIKE '%resurs%bank%'");
                                $responseArray['deleteOptions'] = $numDel;
                                $responseArray['element'] = 'process_cleanResursSettings';
                                if ($numDel > 0) {
                                    $myBool = true;
                                    $responseArray['html'] = 'OK';
                                } else {
                                    $responseArray['html'] = '';
                                }
                            } elseif ($_REQUEST['run'] == 'cleanRbCache') {
                                try {
                                    $wpdb->query('DELETE FROM ' . $wpdb->options . " WHERE option_name LIKE '%resursTemporary%'");
                                } catch (Exception $dbException) {
                                }
                                $myBool = true;
                                $responseArray['html'] = 'OK';
                                $responseArray['element'] = 'process_cleanResursMethods';
                            } elseif ($_REQUEST['run'] == 'cleanRbMethods') {
                                $numDel = 0;
                                $numConfirm = 0;
                                try {
                                    $wpdb->query('DELETE FROM ' . $wpdb->options . " WHERE option_name LIKE '%resursTemporaryPaymentMethods%'");
                                } catch (Exception $dbException) {
                                }
                                // Make sure that the globs does not return anything else than an array.
                                $globIncludes = glob(plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath() . '*.php');
                                if (is_array($globIncludes)) {
                                    foreach ($globIncludes as $filename) {
                                        @unlink($filename);
                                        $numDel++;
                                    }
                                    $globIncludes = glob(plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath() . '*.php');
                                    if (is_array($globIncludes)) {
                                        foreach ($globIncludes as $filename) {
                                            $numConfirm++;
                                        }
                                    }
                                }
                                $responseArray['deleteFiles'] = 0;
                                $responseArray['element'] = 'process_cleanResursMethods';
                                if ($numConfirm != $numDel) {
                                    $responseArray['deleteFiles'] = $numDel;
                                    $responseArray['html'] = 'OK';
                                    $myBool = true;
                                } else {
                                    $responseArray['html'] = '';
                                }
                            } elseif ($_REQUEST['run'] == 'setNewPaymentFee') {
                                $responseArray['update'] = 0;
                                if (isset($_REQUEST['data']) && count($_REQUEST['data'])) {
                                    $paymentFeeData = $_REQUEST['data'];
                                    if (isset($paymentFeeData['feeId']) && isset($paymentFeeData['feeValue'])) {
                                        $feeId = preg_replace(
                                            '/^[a-z0-9]$/i',
                                            '',
                                            $paymentFeeData['feeId']
                                        );
                                        $feeValue = doubleval($paymentFeeData['feeValue']);
                                        $methodNameSpace = 'woocommerce_resurs_bank_nr_' . $feeId . '_settings';
                                        $responseArray['feeId'] = $feeId;
                                        $responseArray['oldValue'] = getResursOption('price', $methodNameSpace);
                                        $responseArray['update'] = setResursOption(
                                            'price',
                                            $feeValue,
                                            $methodNameSpace
                                        ) === true ? 1 : 0;
                                    }
                                }
                            }
                            $myResponse = [
                                $_REQUEST['run'] . 'Response' => $responseArray,
                            ];
                        }
                    }
                }
                $response = [
                    'response' => $myResponse,
                    'success' => $myBool,
                    'session' => $mySession === true ? 1 : 0,
                    'errorMessage' => nl2br($errorMessage),
                    'errorCode' => $errorCode,
                ];
                $this->returnJsonResponse($response);
                exit;
            }
            if ($event_type === 'check_signing_response') {
                $this->check_signing_response();

                return;
            }
            if ($event_type === 'prepare-omni-order') {
                $this->prepare_omni_order();

                return;
            }

            $orderId = wc_get_order_id_by_payment_id($request['paymentId']);
            $order = new WC_Order($orderId);
            $currentValidation = (bool)$this->validateCallback($request);

            try {
                $testOrderId = $order->get_id();
            } catch (Exception $e) {
                $testOrderId = 0;
            }

            if ($testOrderId === 0) {
                // If you really need to reply 20X, you should do that yourself.
                $code = apply_filters(
                    'resurs_bank_order_is_not_outs',
                    410
                );
                $message = 'Order is not ours';
                header(sprintf('HTTP/1.1 %d %s', $code, $message), true, $code);
                echo $code . ': ' . $message;
                exit;
            }

            // SKIP_DIGEST_VALIDATION is for test purposes only.
            if (!$currentValidation && !getResursFlag('SKIP_DIGEST_VALIDATION')) {
                $order->add_order_note(
                    sprintf(
                        __(
                            '[Resurs Bank] The event %s was rejected by the plugin when the digest was processed. ' .
                            'The salt key may need to be updated, by re-registering the callbacks again.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $event_type
                    )
                );

                header('HTTP/1.1 406 Digest rejected by plugin', true, 406);
                echo '406: Digest rejected.';
                exit;
            }

            $currentValidationString = sprintf(
                __(
                    'By OrderID %s',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ),
                $currentValidation
            );

            if (getResursFlag('SKIP_DIGEST_VALIDATION')) {
                $order->add_order_note(
                    __(
                        '[Resurs Bank] Experimental setting SKIP_DIGEST_VALIDATION is active and therefore saltkey-digest validation is disabled on this callback.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )
                );
            }

            $currentStatus = $order->get_status();
            $order->add_order_note(
                sprintf(
                    __(
                        '[Resurs Bank] Notice only: Callback %s received (Method %s). Additional result flag: %s. ' .
                        'Status change will be queued.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $event_type,
                    isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '?',
                    isset($request['result']) ? $request['result'] : __(
                        'No extra flags.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )
                )
            );

            if (updateResursOrderBillingData($order, $request['paymentId'])) {
                update_post_meta($order->get_id(), 'customerSynchronization', time());
                $order->add_order_note(
                    __(
                        '[Resurs Bank] Billing Address mismatched with customer data in getPayment. Customer has been updated.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )
                );
            }

            // Entry logging by metadata.
            update_post_meta($orderId, 'hasCallback' . $event_type, time());

            /**
             * Watch out for race conditions! We've queued the callback updates.
             * @see https://resursbankplugins.atlassian.net/browse/WOO-573
             */
            switch ($event_type) {
                case 'UPDATE':
                case 'UNFREEZE':
                    $this->updateOrderByResursPaymentStatus(
                        $order,
                        $request['paymentId']
                    );
                    ThirdPartyHooksSetPaymentTrigger('callback', $request['paymentId'], $orderId, $event_type);
                    break;
                case 'TEST':
                    break;
                case 'BOOKED':
                    ThirdPartyHooksSetPaymentTrigger('callback', $request['paymentId'], $orderId, $event_type);

                    if ($currentStatus !== 'cancelled') {
                        $optionReduceOrderStock = getResursOption('reduceOrderStock');
                        $hasReduceStock = get_post_meta($orderId, 'hasReduceStock');

                        rbSimpleLogging(
                            sprintf(
                                'Callback BOOKED received. Stock reduction is %s. ' .
                                'Current status (hasReduceStock) for reduction is "%s".',
                                $optionReduceOrderStock ? 'Active' : 'Disabled',
                                $hasReduceStock ? 'Already Handled.' : 'Not handled.'
                            )
                        );

                        if ($optionReduceOrderStock) {
                            if (empty($hasReduceStock)) {
                                resursEventLogger(
                                    'Callback BOOKED received. Plugin is set to handle stock reduction. ' .
                                    'Metadata (hasReduceStock) is not yet set. This is the first time this ' .
                                    'part is reached.'
                                );
                                update_post_meta($orderId, 'hasReduceStock', time());
                                $order->add_order_note(
                                    __(
                                        '[Resurs Bank] Stock reducing requested to be handled by Resurs ' .
                                        'Bank (Callback).',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    )
                                );
                                if (isWooCommerce3()) {
                                    wc_reduce_stock_levels($order->get_id());
                                } else {
                                    $order->reduce_order_stock();
                                }
                            } else {
                                resursEventLogger(
                                    'Callback BOOKED received. Plugin is set to handle stock reduction, but ' .
                                    'stock has already been marked as handled. Reduction skipped.'
                                );
                            }
                        }

                        $this->updateOrderByResursPaymentStatus(
                            $order,
                            $request['paymentId']
                        );
                    } else {
                        $order->add_order_note(
                            __(
                                '[Resurs Bank] The order was placed in status cancelled when BOOKED arrived so ' .
                                'the callback will remain unhandled.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                    }
                    break;
                default:
                    break;
            }
            header('HTTP/1.1 204 Accepted');
            die();
        }

        /**
         * Used to fetch AND convert current callbacks only.
         *
         * @return string
         */
        private function getCurrentSalt()
        {
            $currentStoredSalt = getResursOption('resurs_bank_digest_salt', 'wc_resurs2_salt');

            // Deprecating transient storages.
            $currentDeprecatedSalt = get_transient('resurs_bank_digest_salt');

            if (empty($currentStoredSalt) && !empty($currentDeprecatedSalt)) {
                $return = $currentDeprecatedSalt;
                setResursOption('resurs_bank_digest_salt', $currentDeprecatedSalt);
            } else {
                $return = $currentStoredSalt;
            }

            return (string)$return;
        }

        private function getValidatedDigestResponse($paymentId, $currentSalt, $digest, $result)
        {
            return $this->flow->getValidatedCallbackDigest($paymentId, $currentSalt, $digest, $result);
        }

        /**
         * @param $request
         * @return bool
         */
        private function validateCallback($request)
        {
            $success = null;

            $paymentId = isset($request['paymentId']) ? $request['paymentId'] : null;
            $digest = isset($request['digest']) ? $request['digest'] : null;
            $result = isset($request['result']) ? $request['result'] : null;

            $testDigestArray = [$paymentId];

            if ($paymentId !== wc_get_payment_id_by_order_id($paymentId)) {
                $testDigestArray[] = wc_get_payment_id_by_order_id($paymentId);
            }
            if ($paymentId !== wc_get_order_id_by_payment_id($paymentId)) {
                $testDigestArray[] = wc_get_order_id_by_payment_id($paymentId);
            }

            // Suspecting that payment id and/or references may change depending
            // on the current setup, we will scan through more options before denying
            // a callback.
            foreach ($testDigestArray as $testId) {
                if ($this->getValidatedDigestResponse(
                    $testId,
                    $this->getCurrentSalt(),
                    $digest,
                    $result
                )
                ) {
                    $success = $testId;
                    break;
                }
            }

            return (string)$success;
        }

        /**
         * @param $code
         * @return mixed|string
         */
        public function getOrderStatusByResursReturnCode($code)
        {
            $return = 'Unknown';
            $arrayList = rb_order_status_array();

            if (isset($arrayList[$code])) {
                $return = $arrayList[$code];
            }

            return $return;
        }

        /**
         * @param mixed $woocommerceOrder
         * @param string $paymentIdOrPaymentObject
         * @return void
         * @throws Exception
         */
        private function updateOrderByResursPaymentStatus(
            $woocommerceOrder,
            $paymentIdOrPaymentObject = ''
        ) {
            QueueHandler::setOrderStatusWithNotice($woocommerceOrder->get_id(), $paymentIdOrPaymentObject);
        }

        /**
         * Register a callback event (EComPHP)
         *
         * @param string $type The callback type to be registered
         * @param array $options The parameters for the SOAP request
         *
         * @return bool|mixed|string
         * @throws Exception
         */
        public function register_callback($type, $options)
        {
            $uriTemplate = null;
            if (false === is_object($this->flow)) {
                /** @var ResursBank */
                $this->flow = initializeResursFlow();
            }

            try {
                $testTemplate = home_url('/');
                $useTemplate = $testTemplate;
                $customCallbackUri = resursOption('customCallbackUri');
                $registeredTs = strftime('%y%m%d%H%M', time());
                setResursOption('resurs_callback_registered_ts', $registeredTs);
                if (!empty($customCallbackUri) && $testTemplate != $customCallbackUri) {
                    $useTemplate = $customCallbackUri;
                }
                $uriTemplate = $useTemplate;
                $uriTemplate = add_query_arg('wc-api', 'WC_Resurs_Bank', $uriTemplate);
                $uriTemplate .= '&event-type=' . $type;
                foreach ($options['uri_components'] as $key => $value) {
                    $uriTemplate .= '&' . $key . '=' . '{' . $value . '}';
                }
                if ($type === 'TEST') {
                    $uriTemplate .= '&thisRandomValue=' . rand(10000, 32000);
                } else {
                    $uriTemplate .= '&digest={digest}';
                }
                $uriTemplate .= '&env=' . getServerEnv();
                $uriTemplate .= '&ts=' . $registeredTs;
                set_transient('resurs_bank_callback_ts', $registeredTs);
                $xDebugTest = getResursFlag('XDEBUG_SESSION_START');
                if (!empty($xDebugTest)) {
                    $uriTemplate .= '&XDEBUG_SESSION_START=' . $xDebugTest;
                }
                $callbackType = $this->flow->getCallbackTypeByString($type);
                $this->flow->setCallbackDigestSalt($this->getCurrentSalt());
                $this->flow->setRegisterCallbackFailover();
                $this->flow->setRegisterCallback($callbackType, $uriTemplate);
            } catch (Exception $e) {
                throw new Exception($e);
            }

            return $uriTemplate;
        }

        /**
         * Get digest parameters for register callback
         *
         * @param array $params The parameters
         * @return array         The parameters reordered
         */
        public function get_digest_parameters($params)
        {
            $arr = [];
            foreach ($params as $key => $value) {
                $arr[] = $value;
            }

            return $arr;
        }


        /**
         * Initialize the web services through EComPHP-Simplified
         * @param string $username The username for the API, is fetched from options if not specified
         * @param string $password The password for the API, is fetched from options if not specified
         * @return bool
         */
        public function init_webservice($username = '', $password = '')
        {
            try {
                /** @var ResursBank */
                $this->flow = initializeResursFlow();
            } catch (Exception $initFlowException) {
                return false;
            }

            return true;
        }

        // Payment spec functions is a part of the bookPayment functions

        /**
         * Get specLines for initiated payment session
         * @param WC_Cart $cart WooCommerce cart containing order items
         * @return array The specLines for startPaymentSession
         */
        protected static function get_spec_lines($cart)
        {
            $spec_lines = [];
            foreach ($cart as $item) {
                /** @var WC_Product $data */
                $data = $item['data'];
                $wcRates = WC_Tax::get_rates($data->get_tax_class());
                $rates = is_array($wcRates) ? @array_shift($wcRates) : [];

                if (isset($rates['rate'])) {
                    $vatPct = (double)$rates['rate'];
                } else {
                    $vatPct = 0;
                }
                $priceExTax = (!isWooCommerce3() ? $data->get_price_excluding_tax() : wc_get_price_excluding_tax($data));
                $totalVatAmount = ($priceExTax * ($vatPct / 100));
                $setSku = $data->get_sku();
                $bookArtId = $data->get_id();
                $postTitle = $data->get_title();
                $optionUseSku = getResursOption('useSku');
                if ($optionUseSku && !empty($setSku)) {
                    $bookArtId = $setSku;
                }
                $artDescription = (empty($postTitle) ? __(
                    'Article description missing',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ) : $postTitle);
                $spec_lines[] = [
                    'id' => $bookArtId,
                    'artNo' => $bookArtId,
                    'description' => $artDescription,
                    'quantity' => $item['quantity'],
                    'unitMeasure' => '',
                    'unitAmountWithoutVat' => $priceExTax,
                    'vatPct' => $vatPct,
                    'totalVatAmount' => ($priceExTax * ($vatPct / 100)) * $item['quantity'],
                    'totalAmount' => (($priceExTax * $item['quantity']) + ($totalVatAmount * $item['quantity'])),
                    'type' => 'ORDER_LINE',
                ];
            }

            return $spec_lines;
        }

        /**
         * Get and convert payment spec from cart, convert it to Resurs SpecRows
         *
         * @param WC_Cart $cart Order items
         * @param bool $specLinesOnly Return only the array of SpecLines
         *
         * @return array The paymentSpec for startPaymentSession
         * @throws Exception
         */
        protected static function get_payment_spec($cart, $specLinesOnly = false)
        {
            global $woocommerce, $resurs_is_payment_spec;
            $flow = initializeResursFlow();

            //$payment_fee_tax_pct = (float) getResursOption( 'pricePct' );
            /** @var WC_Cart $currentCart */
            $currentCart = $cart->get_cart();
            if (!count($currentCart)) {
                // If there is no articles in the cart, there's no use to add
                // shipping.
                return [];
            }
            $spec_lines = self::get_spec_lines($currentCart);
            $shipping = (float)$cart->shipping_total;
            $shipping_tax = (float)$cart->shipping_tax_total;
            $shipping_total = (float)($shipping + $shipping_tax);

            $shipping_tax_pct = 0;
            if ($shipping_tax > 0) {
                try {
                    $shipping_tax_pct = (
                    !is_nan(
                        @round(
                            $shipping_tax / $shipping,
                            2
                        ) * 100
                    ) ? @round($shipping_tax / $shipping, 2) * 100 : 0
                    );
                } catch (Exception $e) {
                }
            }

            $spec_lines[] = [
                'id' => 'frakt',
                'artNo' => '00_frakt',
                'description' => __('Shipping', 'resurs-bank-payment-gateway-for-woocommerce'),
                'quantity' => '1',
                'unitMeasure' => '',
                'unitAmountWithoutVat' => $shipping,
                'vatPct' => $shipping_tax_pct,
                'totalVatAmount' => $shipping_tax,
                'totalAmount' => $shipping_total,
                'type' => 'SHIPPING_FEE',
            ];
            $payment_method = $woocommerce->session->chosen_payment_method;
            $payment_fee = getResursOption('price', 'woocommerce_' . $payment_method . '_settings');
            $payment_fee = (float)(isset($payment_fee) ? $payment_fee : '0');
            $payment_fee_tax_class = getResursOption('priceTaxClass');
            if (!hasWooCommerce('2.3', '>=')) {
                $payment_fee_tax_class_rates = $cart->tax->get_rates(
                    $payment_fee_tax_class
                );
                $payment_fee_tax = $cart->tax->calc_tax(
                    $payment_fee,
                    $payment_fee_tax_class_rates,
                    false,
                    true
                );
            } else {
                // ->tax has been deprecated since WC 2.3
                $payment_fee_tax_class_rates = WC_Tax::get_rates($payment_fee_tax_class);
                $payment_fee_tax = WC_Tax::calc_tax(
                    $payment_fee,
                    $payment_fee_tax_class_rates,
                    false,
                    true
                );
            }

            $payment_fee_total_tax = 0;
            foreach ($payment_fee_tax as $value) {
                $payment_fee_total_tax = $payment_fee_total_tax + $value;
            }
            $tax_rates_pct_total = 0;
            foreach ($payment_fee_tax_class_rates as $key => $rate) {
                $tax_rates_pct_total = $tax_rates_pct_total + (float)$rate['rate'];
            }

            $ResursFeeName = '';
            $fees = $cart->get_fees();

            if (is_array($fees)) {
                foreach ($fees as $fee) {
                    /*
                     * Ignore this fee if it matches the Resurs description.
                     */
                    if ($fee->tax > 0) {
                        $rate = ($fee->tax / $fee->amount) * 100;
                    } else {
                        $rate = 0;
                    }
                    if (!empty($fee->id) && ($fee->amount > 0 || $fee->amount < 0)) {
                        $spec_lines[] = [
                            'id' => $fee->id,
                            'artNo' => $fee->id,
                            'description' => $fee->name,
                            'quantity' => 1,
                            'unitMeasure' => '',
                            'unitAmountWithoutVat' => $fee->amount,
                            'vatPct' => !is_nan($rate) ? $rate : 0,
                            'totalVatAmount' => $fee->tax,
                            'totalAmount' => $fee->amount + $fee->tax,
                        ];
                    }
                }
            }
            if (wc_coupons_enabled()) {
                $coupons = $cart->get_coupons();
                if (is_array($coupons) && count($coupons) > 0) {
                    /**
                     * @var  $code
                     * @var  $coupon WC_Coupon
                     */
                    foreach ($coupons as $code => $coupon) {
                        $post = get_post($coupon->get_id());
                        $couponId = $coupon->get_id();
                        $couponCode = $coupon->get_code();
                        $couponDescription = $post->post_excerpt;
                        if (empty($couponDescription)) {
                            $couponDescription = sprintf(
                                '%s_%s',
                                $couponCode,
                                __(
                                    'coupon',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                        }

                        $discountType = $coupon->get_discount_type();
                        $exTaxAmount = $discountType !== 'fixed_cart' ? $cart->get_coupon_discount_amount($code) : ceil($cart->get_coupon_discount_amount($code));
                        $incTaxAmount = $discountType !== 'fixed_cart' ? $cart->get_coupon_discount_amount(
                            $code,
                            false
                        ) : ceil($cart->get_coupon_discount_amount($code, false));
                        $exTax = 0 - $exTaxAmount;
                        $incTax = 0 - $incTaxAmount;
                        $vatPct = (bool)getResursOption('coupons_include_vat') ? (($incTax - $exTax) / $exTax) * 100 : 0;
                        $unitAmountWithoutVat = (bool)getResursOption('coupons_include_vat') ? $exTax : $incTax;
                        $totalAmount = $flow->getTotalAmount($unitAmountWithoutVat, $vatPct, 1);
                        $totalVatAmount = (bool)getResursOption('coupons_include_vat') ?
                            $flow->getTotalVatAmount(
                                $unitAmountWithoutVat,
                                $vatPct, 1
                            ) : 0;

                        $couponArtNo = sprintf(
                            '%s_%s',
                            $couponCode,
                            __(
                                'coupon',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );

                        $spec_lines[] = [
                            'id' => $couponId,
                            'artNo' => $couponArtNo,
                            'description' => $couponDescription,
                            'quantity' => 1,
                            'unitMeasure' => '',
                            'unitAmountWithoutVat' => (float)$unitAmountWithoutVat,
                            'vatPct' => $vatPct,
                            'totalVatAmount' => (float)$totalVatAmount,
                            'totalAmount' => (float)$totalAmount,
                            'type' => 'DISCOUNT',
                        ];
                    }
                }
            }
            $ourPaymentSpecCalc = self::calculateSpecLineAmount($spec_lines);
            if ($specLinesOnly) {
                return $spec_lines;
            } else {
                $payment_spec = [
                    'specLines' => $spec_lines,
                    'totalAmount' => $ourPaymentSpecCalc['totalAmount'],
                    'totalVatAmount' => $ourPaymentSpecCalc['totalVatAmount'],
                ];
            }

            return $payment_spec;
        }

        /**
         * @param array $specLine
         * @return array
         */
        protected static function calculateSpecLineAmount($specLine = [])
        {
            $setPaymentSpec = ['totalAmount' => 0, 'totalVatAmount' => 0]; // defaults
            if (is_array($specLine) && count($specLine)) {
                foreach ($specLine as $row) {
                    $setPaymentSpec['totalAmount'] += $row['totalAmount'];
                    $setPaymentSpec['totalVatAmount'] += $row['totalVatAmount'];
                }
            }

            return $setPaymentSpec;
        }

        /**
         * Extract postdata from WC post_data
         *
         * @param string $dataContent
         * @return array
         */
        private function splitPostData($dataContent = '')
        {
            return rbSplitPostData($dataContent);
        }

        /**
         * Get translated label for field
         *
         * @param $fieldName
         * @param $customerType
         * @return mixed
         */
        private function get_payment_method_form_label($fieldName, $customerType)
        {
            $labels = [
                'contact-government-id' => __('Contact government id', 'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-government-id' => __(
                    'Applicant government ID',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ),
                'applicant-full-name' => __('Applicant full name', 'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-email-address' => __(
                    'Applicant email address',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ),
                'applicant-telephone-number' => __(
                    'Applicant telephone number',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ),
                'applicant-mobile-number' => __(
                    'Applicant mobile number',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ),
                'card-number' => __('Card number', 'resurs-bank-payment-gateway-for-woocommerce'),
            ];
            $labelsLegal = [
                'applicant-government-id' => __('Company government ID', 'resurs-bank-payment-gateway-for-woocommerce'),
            ];

            $setLabel = $labels[$fieldName];
            if (isset($labelsLegal[$fieldName]) &&
                !empty($labelsLegal[$fieldName]) &&
                $customerType !== 'NATURAL'
            ) {
                $setLabel = $labelsLegal[$fieldName];
            }

            return $setLabel;
        }

        /**
         * @param $method
         * @param $paymentSpec
         * @param $method_class
         * @return null|string
         * @throws Exception
         */
        public function get_payment_method_form($method, $paymentSpec, $method_class)
        {
            global $woocommerce;
            $cart = $woocommerce->cart;

            $fieldGenHtml = null;
            $post_data = isset($_REQUEST['post_data']) ? $this->splitPostData($_REQUEST['post_data']) : [];

            // Get the read more from internal translation if not set
            $read_more = __(
                'Read more',
                'resurs-bank-payment-gateway-for-woocommerce'
            );

            $id = $method->id;
            $type = $method->type;
            $specificType = $method->specificType;

            if (!isset($_REQUEST['ssnCustomerType'])) {
                $_REQUEST['ssnCustomerType'] = 'NATURAL';
                if (isset($method_class->customerTypes) &&
                    in_array('LEGAL', (array)$method_class->customerTypes, true) &&
                    !in_array('NATURAL', (array)$method_class->customerTypes, true)
                ) {
                    $_REQUEST['ssnCustomerType'] = 'LEGAL';
                }
            }
            if (isset($post_data['ssnCustomerType'])) {
                $_REQUEST['ssnCustomerType'] = $post_data['ssnCustomerType'];
            }

            $customerType = in_array(
                $_REQUEST['ssnCustomerType'],
                (array)$method->customerType
            ) ? $_REQUEST['ssnCustomerType'] : 'NATURAL';
            $mustShowGov = apply_filters(
                'resurs_bank_force_govid_field',
                (getResursOption('forceGovIdField') ? true : false)
            );
            if ($type === 'PAYMENT_PROVIDER') {
                $requiredFormFields = $this->flow->getTemplateFieldsByMethodType(
                    $method,
                    $customerType,
                    'PAYMENT_PROVIDER'
                );
            } else {
                // Always display the field on resurs internals.
                $mustShowGov = true;
                $requiredFormFields = $this->flow->getTemplateFieldsByMethodType($method, $customerType, $specificType);
            }
            if ($this->getMinMax($paymentSpec['totalAmount'], $method->minLimit, $method->maxLimit)) {
                $buttonCssClasses = 'btn btn-info active';
                $ajaxUrl = admin_url('admin-ajax.php');

                // SIMPLIFIED
                if (!isResursHosted()) {
                    $fieldGenHtml .= '<div>' . $method_class->description . '</div>';
                    foreach ($requiredFormFields['fields'] as $fieldName) {
                        $doDisplay = 'block';
                        $fieldType = 'text';
                        $mandatory = '';
                        $streamLineBehaviour = getResursOption('streamlineBehaviour');
                        if ($streamLineBehaviour) {
                            if ($this->flow->canHideFormField($fieldName)) {
                                $doDisplay = 'none';
                            }
                            // When applicant government id and getAddress is enabled so that data can be collected
                            // from that point, the request field is not necessary to be shown all the time.
                            if ($fieldName === 'applicant-government-id') {
                              $mandatory = '*';
                                $optionGetAddress = getResursOption('getAddress');
                                // $mustShowGov is unconditional: This setting forces the gov id to ALWAYS show
                                // regardless of payment method.
                                if ($optionGetAddress && !$mustShowGov) {
                                    $doDisplay = 'none';
                                }
                                if ($type === 'PAYMENT_PROVIDER' && !$mustShowGov) {
                                    $doDisplay = 'none';
                                }
                            }
                        }

                        $setLabel = $this->get_payment_method_form_label($fieldName, $customerType);
                        $fieldGenHtml .= '<div style="display:' . $doDisplay . ';width:100%;" class="resurs_bank_payment_field_container">';
                        $mandatoryString = !empty($mandatory) ? '<span style="font-size:10px !important;" title="'.__('Mandatory', 'resurs-bank-payment-gateway-for-woocommerce').'">'.$mandatory.' </span> ' : '';
                        $fieldGenHtml .= '<label for="' . $fieldName . '" style="width:100%;display:block;">' . $mandatoryString . $setLabel . '</label>';
                        $fieldGenHtml .= '<input onkeyup="rbFormChange(\'' . $fieldName . '\', this)" id="' . $fieldName . '" type="'.$fieldType.'" name="' . $fieldName . '">';
                        $fieldGenHtml .= '</div>';
                    }

                    $translation = [];
                    $costOfPurchase = $ajaxUrl . '?action=get_priceinfo_ajax';
                    if ($type !== 'PAYMENT_PROVIDER') {
                        $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' . __(
                                $read_more,
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . '</button>';
                    }
                    $fieldGenHtml .= '<input type="hidden" value="' . $id . '" class="resurs-bank-payment-method">';
                } else {
                    // HOSTED
                    $costOfPurchase = $ajaxUrl . '?action=get_priceinfo_ajax';
                    $fieldGenHtml = $this->description . '<br><br>';
                    if ($specificType !== 'CARD') {
                        $fieldGenHtml .=
                            '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' .
                            __(
                                $read_more,
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) .
                            '</button>';
                    }
                }
            }

            return $fieldGenHtml;
        }

        /**
         * If payment amount is within allowed limits of payment method
         *
         * @param $totalAmount
         * @param $min
         * @param $max
         * @return bool
         */
        public function getMinMax($totalAmount, $min, $max)
        {
            return (
                $totalAmount >= apply_filters('resursbank_min_amount', $min) &&
                $totalAmount <= apply_filters('resursbank_max_amount', $max)
            );
        }


        /**
         * Function formerly known as the forms session, where forms was created from a response from Resurs.
         * From now on, we won't get any returned values from this function. Instead, we'll create the form at this
         * level.
         *
         * @param int $payment_id The chosen payment method
         * @param null $method_class
         *
         * @throws Exception
         */
        public function start_payment_session($payment_id, $method_class = null)
        {
            global $woocommerce;
            $this->flow = initializeResursFlow();
            $sessionErrorMessage = '';
            $currentCountry = getResursOption('country');
            $minMaxError = null;
            $methodList = null;
            $fieldGenHtml = '';

            $post_data = isset($_REQUEST['post_data']) ? $this->splitPostData($_REQUEST['post_data']) : [];
            if (isset(WC()->session)) {
                $ssnCustomerTypeInfo = isset($post_data['ssnCustomerType']) ? $post_data['ssnCustomerType'] : 'NATURAL';
                rbSimpleLogging(
                    sprintf('payment session getAddress ssnCustomerType changed: %s.', $ssnCustomerTypeInfo)
                );
                WC()->session->set(
                    'ssnCustomerType',
                    $ssnCustomerTypeInfo
                );
            }

            $cart = $woocommerce->cart;
            $paymentSpec = $this->get_payment_spec($cart);
            $sessionHasErrors = false;

            //$resursTemporaryPaymentMethodsTime = get_transient('resursTemporaryPaymentMethodsTime');
            //$timeDiff = time() - $resursTemporaryPaymentMethodsTime;

            /*
             * From resursbank_settings.php (2022-03-04):
             *
             *   $this->paymentMethods = $this->flow->getPaymentMethods([], true);
             *   set_transient('resursTemporaryPaymentMethods', serialize($this->paymentMethods));
             *
             * This feature is based on a legacy issue with shopFlow, there the primary idea was to make
             * sure that payment methods are always up to day API-side. One specific feature that was used was
             * campaigns which disabled payment methods after a specific set date. To avoid that those payment
             * methods are left available, the integrations had in mind to make sure that payment methods
             * were periodically re-fetched. In "modern time" this is not necessary. Besides, if there are
             * operational networking issues, the list in --this-- section could negatively affect the checkout.
             * If payment methods are not available, no forms will neither be generated.
             *
             * Resolution: In wp-admin, the transient variable that we are using below will always be written
             * when payment methods are fetched manually instead of this sequentially control. As we want to make sure
             * things really properly works, we will however not remove the entire code below, since the risk
             * is higher that we create new bugs in the flow.
             *
             * In short, the payment method handling has to be rebuilt.
             */
            try {
                // Fetch methods through what's saved from wp-admin.
                // Note: unserialization is not a very smart thing to do.
                $methodList = unserialize(get_transient('resursTemporaryPaymentMethods'));

                // If transient fetching failed, re-fetch methods and store them again.
                // Tested 220405: Lacking the list is not a showstopper for the checkout.
                // This should in a natural way not fail since we do not have any expiration time
                // set on the transients.
                if (!is_array($methodList) || (is_array($methodList) && !count($methodList))) {
                    rbSimpleLogging('Resurs Payment Methods is not set - re-fetch in progress.');
                    $methodList = $this->flow->getPaymentMethods([], true);
                    set_transient('resursTemporaryPaymentMethods', serialize($methodList));
                    set_transient('resursTemporaryPaymentMethodsTime', time());
                }
            } catch (Exception $e) {
                $sessionHasErrors = true;
                $sessionErrorMessage = $e->getMessage();
                resursEventLogger(
                    sprintf(
                        'PaymentMethod CustomerForm Renderer Exception (%s): %s.',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            }

            if (!$sessionHasErrors) {
                if (is_array($methodList)) {
                    foreach ($methodList as $methodIndex => $method) {
                        if (strtolower($method->id) == strtolower($payment_id)) {
                            $fieldGenHtml = $this->get_payment_method_form($method, $paymentSpec, $method_class);
                            break;
                        }
                    }
                } else {
                    $fieldGenHtml = __(
                        'Something went wrong while trying to get the required form fields for the payment methods',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }
            } else {
                // Append errors to the fieldGenHtml content in case of errors that must reach us.
                $fieldGenHtml .= !empty($sessionErrorMessage) ? $sessionErrorMessage : '';
            }
            if (!empty($fieldGenHtml)) {
                echo $fieldGenHtml;
            }
        }

        /**
         * @param $order_id
         * @return string
         * @since 2.2.7
         */
        private function getSuccessUrl($order_id, $preferredId)
        {
            $success_url = home_url('/');
            $success_url = add_query_arg('wc-api', 'WC_Resurs_Bank', $success_url);
            $success_url = add_query_arg('order_id', $order_id, $success_url);
            $success_url = add_query_arg('utm_nooverride', '1', $success_url);
            $success_url = add_query_arg('event-type', 'check_signing_response', $success_url);
            $success_url = add_query_arg('set-no-session', '1', $success_url);
            $success_url = add_query_arg('payment_id', $preferredId, $success_url);
            if (isResursHosted()) {
                $success_url = add_query_arg('flow-type', 'check_hosted_response', $success_url);
            }

            return $success_url;
        }

        /**
         * @since 2.2.7
         */
        public function process_payment_prepare_customer()
        {
            $this->flow->setBillingAddress(
                $_REQUEST['billing_last_name'] . ' ' . $_REQUEST['billing_first_name'],
                $_REQUEST['billing_first_name'],
                $_REQUEST['billing_last_name'],
                $_REQUEST['billing_address_1'],
                (empty($_REQUEST['billing_address_2']) ? '' : $_REQUEST['billing_address_2']),
                $_REQUEST['billing_city'],
                $_REQUEST['billing_postcode'],
                $_REQUEST['billing_country']
            );
            if (isset($_REQUEST['ship_to_different_address'])) {
                $this->flow->setDeliveryAddress(
                    $_REQUEST['shipping_last_name'] . ' ' . $_REQUEST['shipping_first_name'],
                    $_REQUEST['shipping_first_name'],
                    $_REQUEST['shipping_last_name'],
                    $_REQUEST['shipping_address_1'],
                    (empty($_REQUEST['shipping_address_2']) ? '' : $_REQUEST['shipping_address_2']),
                    $_REQUEST['shipping_city'],
                    $_REQUEST['shipping_postcode'],
                    $_REQUEST['shipping_country']
                );
            }
        }

        /**
         * @param $order_id
         *
         * @return string
         * @since 2.2.7
         */
        public function process_payment_get_payment_id($order_id)
        {
            if (getResursOption('postidreference')) {
                $preferredId = $order_id;
            } else {
                $preferredId = $this->flow->getPreferredPaymentId();
            }
            update_post_meta($order_id, 'paymentId', $preferredId);

            return $preferredId;
        }

        /**
         * @param $order
         *
         * @return null|string
         * @since 2.2.7
         */
        public function process_payment_get_backurl($order)
        {
            $backurl = html_entity_decode($order->get_cancel_order_url());
            if (isResursHosted()) {
                $backurl .= '&isBack=1';
            } else {
                $backurl .= '&isSimplified=1';
            }

            return $backurl;
        }

        /**
         * @param $paymentMethodData
         *
         * @return string
         * @since 2.2.7
         */
        public function process_payment_get_customer_type($paymentMethodData)
        {
            $useCustomerType = '';
            if (!is_array($paymentMethodData->customerType)) {
                if ($paymentMethodData->customerType === 'NATURAL') {
                    $useCustomerType = 'NATURAL';
                } elseif ($paymentMethodData->customerType === 'LEGAL') {
                    $useCustomerType = 'LEGAL';
                }
            } else {
                $useCustomerType = 'NATURAL';
            }

            return $useCustomerType;
        }

        /**
         * @param $paymentMethodInformation
         *
         * @since 2.2.7
         */
        public function process_payment_set_card_info($paymentMethodInformation)
        {
            if (isset($paymentMethodInformation->specificType) &&
                ($paymentMethodInformation->specificType === 'REVOLVING_CREDIT' ||
                    $paymentMethodInformation->specificType === 'CARD')) {
                if ($paymentMethodInformation->specificType === 'REVOLVING_CREDIT') {
                    $this->flow->setCardData();
                } elseif (isset($_REQUEST['card-number'])) {
                    $this->flow->setCardData($_REQUEST['card-number']);
                }
            }
        }

        /**
         * @param $order
         * @param $order_id
         * @param $shortMethodName
         * @param $preferredId
         * @param $paymentMethodInformation
         * @param $supportProviderMethods
         * @param $bookDataArray
         * @param $urlFail
         * @return array|void
         * @since 2.2.7
         */
        public function process_payment_hosted(
            $order,
            $order_id,
            $shortMethodName,
            $preferredId,
            $paymentMethodInformation,
            $supportProviderMethods,
            $bookDataArray,
            $urlFail
        ) {
            $hostedFlowBookingFailure = false;
            $hostedFlowUrl = null;
            $hostedBookPayment = null;

            $customerId = getResursWooCustomerId($order);
            if (!is_null($customerId)) {
                $this->flow->setMetaData('CustomerId', $customerId);
            }

            if ($paymentMethodInformation->type === 'PAYMENT_PROVIDER' && !$supportProviderMethods) {
                wc_add_notice(
                    __(
                        'The payment method is not available for the selected payment flow',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'error'
                );

                return;
            } else {
                try {
                    // Going payload-arrays in ECOMPHP is deprecated so we'll do it right
                    $hostedFlowUrl = $this->flow->createPayment($shortMethodName, $bookDataArray);
                } catch (Exception $hostedException) {
                    $hostedFlowBookingFailure = true;
                    wc_add_notice($hostedException->getMessage(), 'error');
                    rbSimpleLogging(
                        $hostedException->getMessage(),
                        __FUNCTION__
                    );
                }
            }

            if (!$hostedFlowBookingFailure && !empty($hostedFlowUrl)) {
                $order->update_status('pending');
                update_post_meta($order_id, 'paymentId', $preferredId);
                update_post_meta($order_id, 'hosted_redirect_time', date('Y-m-d H:i:s', time()));
                $order->add_order_note(
                    __(
                        '[Resurs Bank] Customer was redirected to finalize the purchase at Resurs Bank.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )
                );

                return [
                    'result' => 'success',
                    'redirect' => $hostedFlowUrl,
                ];
            } else {
                $order->update_status(
                    'failed',
                    __(
                        'An error occured during the update of the booked payment (hostedFlow) - the payment id which was never received properly',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )
                );

                return [
                    'result' => 'failure',
                    'redirect' => $urlFail,
                ];
            }
        }

        /**
         * @param $order_id
         * @param $shortMethodName
         * @param $paymentMethodInformation
         * @param $supportProviderMethods
         * @param $bookDataArray
         * @param WC_Order $order
         * @return array|void
         * @since 2.2.7
         */
        public function process_payment_simplified(
            $order_id,
            $shortMethodName,
            $paymentMethodInformation,
            $supportProviderMethods,
            $bookDataArray,
            $order
        ) {
            if ($paymentMethodInformation->type === 'PAYMENT_PROVIDER' && !$supportProviderMethods) {
                wc_add_notice(
                    __(
                        'The payment method is not available for the selected payment flow',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'error'
                );

                return;
            } else {
                $storeIdTest = !getResursFlag('STORE_ID') ? null : getResursFlag('STORE_ID');
                $storeId = apply_filters('resursbank_set_storeid', $storeIdTest);
                if (!empty($storeId)) {
                    $this->flow->setStoreId($storeId);
                    update_post_meta($order_id, 'resursStoreId', $storeId);
                }

                $customerId = getResursWooCustomerId($order);
                if (!is_null($customerId)) {
                    $this->flow->setMetaData('CustomerId', $customerId);
                }

                if (isset(WC()->session) && !empty(WC()->session)) {
                    rbSimpleLogging(
                        sprintf(
                            'bookPayment for customerType %s executing.',
                            WC()->session->get('ssnCustomerType')
                        )
                    );
                }

                // If woocommerce forms do offer phone and email, while our own
                // don't, use them (moved to the section of setCustomer)
                $bookPaymentResult = $this->flow->createPayment($shortMethodName, $bookDataArray);

                rbSimpleLogging(
                    print_r($bookPaymentResult, true),
                    __FUNCTION__
                );
            }
            return $bookPaymentResult;
        }

        /**
         * @param WC_Order $order
         * @param $order_id
         * @param $bookPaymentResult
         * @param $preferredId
         * @return array|void
         * @throws Exception
         * @since 2.2.7
         */
        public function process_payment_handle_payment_result($order, $order_id, $bookPaymentResult, $preferredId)
        {
            $bookedStatus = trim(isset($bookPaymentResult->bookPaymentStatus) ? $bookPaymentResult->bookPaymentStatus : null);
            $bookedPaymentId = isset($bookPaymentResult->paymentId) ? $bookPaymentResult->paymentId : null;
            if (empty($bookedPaymentId)) {
                $bookedStatus = 'FAILED';
            } else {
                update_post_meta($order_id, 'paymentId', $bookedPaymentId);
            }

            $return = [];

            add_post_meta(
                $order_id,
                'orderBookStatus',
                sprintf(
                    '%s (%s)',
                    $bookedStatus,
                    date('Y-m-d H:i:s', time())
                )
            );

            switch ($bookedStatus) {
                case 'FINALIZED':
                    define('RB_SYNCHRONOUS_MODE', true);
                    try {
                        $order->add_order_note(
                            __(
                                '[Resurs Bank] The payment are signed, booked and finalized.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                        $this->updateOrderByResursPaymentStatus(
                            $order,
                            $bookedPaymentId
                        );
                    } catch (Exception $e) {
                        $order->add_order_note(
                            sprintf(
                                '[Resurs Bank] Error %d in %s: %s.',
                                $e->getCode(),
                                __FUNCTION__,
                                $e->getMessage()
                            )
                        );
                        wc_add_notice($e->getMessage(), 'error');
                        $getRedirectUrl = wc_get_cart_url();
                    }
                    WC()->cart->empty_cart();

                    $return = ['result' => 'success', 'redirect' => $this->get_return_url($order)];
                    break;
                case 'BOOKED':
                    $currentStatus = $order->get_status();
                    if ($currentStatus !== 'processing') {
                        $order->update_status('processing');
                    }
                    $optionReduceOrderStock = getResursOption('reduceOrderStock');
                    $hasReduceStock = get_post_meta($order_id, 'hasReduceStock');
                    // This is not an actual callback. This is a function connected to process_payment.
                    resursEventLogger(
                        sprintf(
                            'Function %s executed. Stock reduction is %s. ' .
                            'Current status (hasReduceStock) for reduction is "%s".',
                            __FUNCTION__,
                            $optionReduceOrderStock ? 'Active' : 'Disabled',
                            $hasReduceStock ? 'Already Handled.' : 'Not handled.'
                        )
                    );

                    if ($optionReduceOrderStock) {
                        if (empty($hasReduceStock)) {
                            resursEventLogger(
                                'Function %s executed. Plugin is set to handle stock reduction. ' .
                                'Metadata (hasReduceStock) is not yet set. This is the first time this part is reached.'
                            );
                            update_post_meta($order_id, 'hasReduceStock', time());
                            if (isWooCommerce3()) {
                                wc_reduce_stock_levels($order_id);
                            } else {
                                $order->reduce_order_stock();
                            }
                        } else {
                            resursEventLogger(
                                'Function %s executed. Plugin is set to handle stock reduction, but ' .
                                'stock has already been marked as handled. Reduction skipped.'
                            );
                        }
                    }
                    WC()->cart->empty_cart();

                    $return = ['result' => 'success', 'redirect' => $this->get_return_url($order)];
                    break;
                case 'FROZEN':
                    $order->update_status('on-hold');
                    WC()->cart->empty_cart();

                    $return = ['result' => 'success', 'redirect' => $this->get_return_url($order)];
                    break;
                case 'SIGNING':
                    $signingUrl = isset($bookPaymentResult->signingUrl) ? $bookPaymentResult->signingUrl : null;
                    if (!is_null($signingUrl)) {
                        return [
                            'result' => 'success',
                            'redirect' => $signingUrl,
                        ];
                    }
                    update_post_meta($order_id, 'orderSignFailed', true);
                    $order->update_status('failed');
                    wc_add_notice(
                        __(
                            'Payment can not complete. A problem with the signing url occurred. Contact customer services for more information.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'error'
                    );
                    break;
                case 'DENIED':
                    $order->update_status('failed');
                    update_post_meta($order_id, 'orderDenied', true);
                    wc_add_notice(
                        __(
                            'The payment can not complete. Please choose another payment method.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'error'
                    );

                    break;
                case 'FAILED':
                    update_post_meta($order_id, 'orderBookFailed', true);

                    $order->update_status(
                        'failed',
                        __(
                            'An error occured during the update of the booked payment. The payment ID was never received properly in the payment process',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    wc_add_notice(
                        __(
                            'An unknown error occured. Please, try again later',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'error'
                    );

                    break;
                default:
                    wc_add_notice(
                        __(
                            'An unknown error occured. Please, try again later',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'error'
                    );

                    break;
            }

            return $return;
        }

        /**
         * Process the payment
         * @param int $order_id WooCommerce order ID
         * @return array|void Null on failure, array on success
         * @throws Exception
         */
        public function process_payment($order_id)
        {
            global $woocommerce;

            // Skip procedure of process_payment if the session is based on a Resurs Checkout, by using the internal constant
            if (defined('OMNICHECKOUT_PROCESSPAYMENT')) {
                return;
            }

            // Initializing stuff
            $order = new WC_Order($order_id);
            $bookDataArray = [];
            $className = isset($_REQUEST['payment_method']) ? $_REQUEST['payment_method'] : null;
            $shortMethodName = str_replace('resurs_bank_nr_', '', $className);
            $paymentMethodInformation = $this->getTransientMethod($shortMethodName);
            /** @var ResursBank */
            $this->flow = initializeResursFlow();
            $this->process_payment_prepare_customer();

            setResursPaymentMethodMeta($order_id, $shortMethodName);
            $preferredId = $this->process_payment_get_payment_id($order_id);
            $success_url = $this->getSuccessUrl($order_id, $preferredId);
            $backurl = $this->process_payment_get_backurl($order);
            $urlFail = html_entity_decode($order->get_cancel_order_url());
            if (!isResursHosted()) {
                $urlFail .= '&isSimplifiedFail=1';
            }
            // Unencoded urls in failurl is not a problem in regular flows (only RCO).
            $this->flow->setSigning(
                $success_url,
                $urlFail,
                false,
                $backurl
            );
            $this->flow->setWaitForFraudControl(getResursOption('waitForFraudControl'));
            $this->flow->setAnnulIfFrozen(getResursOption('annulIfFrozen'));
            $this->flow->setFinalizeIfBooked(getResursOption('finalizeIfBooked'));
            $this->flow->setPreferredId($preferredId);
            $cart = $woocommerce->cart;
            // TODO: Old style payment spec generator should be fixed.
            $paymentSpec = $this->get_payment_spec($cart, true);
            $bookDataArray['specLine'] = $paymentSpec;

            $fetchedGovernmentId = (isset($_REQUEST['applicant-government-id']) ? trim($_REQUEST['applicant-government-id']) : '');
            if (empty($fetchedGovernmentId) && isset($_REQUEST['ssn_field']) && !empty($_REQUEST['ssn_field'])) {
                $fetchedGovernmentId = $_REQUEST['ssn_field'];
                $_REQUEST['applicant-government-id'] = $fetchedGovernmentId;
            }
            $ssnCustomerType = (isset($_REQUEST['ssnCustomerType']) ?
                trim($_REQUEST['ssnCustomerType']) : $this->process_payment_get_customer_type($paymentMethodInformation));
            if ($ssnCustomerType === 'LEGAL' &&
                $paymentMethodInformation->type === 'PAYMENT_PROVIDER'
            ) {
                $fetchedGovernmentId = null;
            }

            // Special cases
            // * If applicant phone is missing, try use billing phone instead
            // * If applicant mail is missing, try use billing email instead
            $this->flow->setCustomer(
                $fetchedGovernmentId,
                (isset($_REQUEST['applicant-telephone-number']) ? trim($_REQUEST['applicant-telephone-number']) : (isset($_REQUEST['billing_phone']) ? trim($_REQUEST['billing_phone']) : '')),
                (isset($_REQUEST['applicant-mobile-number']) && !empty($_REQUEST['applicant-mobile-number']) ? trim($_REQUEST['applicant-mobile-number']) : null),
                (isset($_REQUEST['applicant-email-address']) ? trim($_REQUEST['applicant-email-address']) : (isset($_REQUEST['billing_email']) ? trim($_REQUEST['billing_email']) : '')),
                $ssnCustomerType,
                (isset($_REQUEST['contact-government-id']) ? trim($_REQUEST['contact-government-id']) : null)
            );
            $this->process_payment_set_card_info($paymentMethodInformation);

            $supportProviderMethods = true;
            try {
                if (isResursHosted()) {
                    return $this->process_payment_hosted(
                        $order,
                        $order_id,
                        $shortMethodName,
                        $preferredId,
                        $paymentMethodInformation,
                        $supportProviderMethods,
                        $bookDataArray,
                        $urlFail
                    );
                } else {
                    $bookPaymentResult = $this->process_payment_simplified(
                        $order_id,
                        $shortMethodName,
                        $paymentMethodInformation,
                        $supportProviderMethods,
                        $bookDataArray,
                        $order
                    );
                }
            } catch (Exception $bookPaymentException) {
                wc_add_notice(
                    __($bookPaymentException->getMessage(), 'resurs-bank-payment-gateway-for-woocommerce'),
                    'error'
                );
                rbSimpleLogging(
                    'Order ' . $order_id . ' error: ' . $bookPaymentException->getLongException(),
                    __FUNCTION__
                );

                return;
            }

            return $this->process_payment_handle_payment_result($order, $order_id, $bookPaymentResult, $preferredId);
        }

        /**
         * Get specific payment method object, from transient
         *
         * @param string $methodId
         *
         * @return array
         * @throws Exception
         */
        public function getTransientMethod($methodId = '')
        {
            if (empty($this->flow)) {
                /** @var ResursBank */
                $this->flow = initializeResursFlow();
            }
            $methodList = $this->flow->getPaymentMethods([], true);
            if (is_array($methodList)) {
                foreach ($methodList as $methodArray) {
                    if (strtolower($methodArray->id) == strtolower($methodId)) {
                        return $methodArray;
                    }
                }
            }

            return [];
        }

        /**
         * @param $error
         * @return mixed
         */
        public function error_prepare_omni_order($error)
        {
            return $error;
        }

        /**
         * Secure update of correct orderLines (when payment reference updates are activated).
         *
         * @param $requestedPaymentId
         * @param $paymentSpec
         * @param $returnResult
         * @param $flow
         * @return mixed
         */
        private function updateOrderLines($requestedPaymentId, $paymentSpec, $returnResult, $flow)
        {
            try {
                $secondPaymentId = wc_get_order_id_by_payment_id($requestedPaymentId);

                // Synchronize items with Resurs session before creating order locally. On failures,
                // this should not go further in the process.
                $flow->updateCheckoutOrderLines($requestedPaymentId, $paymentSpec['specLines']);
                $returnResult['success'] = true;
            } catch (Exception $e) {
                $returnResult['success'] = false;
                $code = $e->getCode();
                if (!(int)$code) {
                    $code = 500;
                }

                if (getResursOption('postidreference')) {
                    $reUpdateOrderByDifferentId = $this->updateOrderLines(
                        $secondPaymentId,
                        $paymentSpec,
                        $returnResult,
                        $flow
                    );
                }

                if (!(bool)$reUpdateOrderByDifferentId['success']) {
                    $returnResult['errorString'] = $e->getMessage();
                    $returnResult['errorCode'] = $code;
                    $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                    die;
                } else {
                    $returnResult = $reUpdateOrderByDifferentId;
                }
            }

            return $returnResult;
        }

        /**
         * Extract post data, retrieve billing address depending on legacy or non legacy (facelift).
         * @return array
         */
        public function getCustomerBillingAddress($customerData)
        {
            // Make place for FaceLift
            if (isset($customerData['billingAddress'])) {
                $return = isset($customerData['billingAddress']) && is_array($customerData['billingAddress']) ? $customerData['billingAddress'] : [];
            } else {
                $return = isset($customerData['address']) && is_array($customerData['address']) ? $customerData['address'] : [];
            }
            return $return;
        }

        /**
         * @param $legacy
         * @return string[]
         */
        public function getCustomerWooFormFields($legacy)
        {
            if ($legacy) {
                $return = [
                    'first_name' => 'firstname',
                    'last_name' => 'surname',
                    'address_1' => 'address',
                    'address_2' => 'addressExtra',
                    'city' => 'city',
                    'postcode' => 'postal',
                    'country' => 'countryCode',
                    'email' => 'email',
                    'phone' => 'telephone',
                ];
            } else {
                $return = [
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'address_1' => 'addressRow1',
                    'address_2' => 'addressExtra',
                    'city' => 'city',
                    'postcode' => 'postalCode',
                    'country' => 'countryCode',
                    'email' => 'email',
                    'phone' => 'phone',
                ];
            }
            return $return;
        }

        /**
         * Transform RCO order request by its legacy state.
         * @param $resursBillingAddress
         * @param $customerData
         * @param $legacy
         * @return array
         */
        public function getCustomerBillingTransformed($resursBillingAddress, $customerData, $legacy)
        {
            if ($legacy) {
                $return = [
                    'first_name' => !empty($resursBillingAddress['firstname']) ? $resursBillingAddress['firstname'] : '',
                    'last_name' => !empty($resursBillingAddress['surname']) ? $resursBillingAddress['surname'] : '',
                    'address_1' => !empty($resursBillingAddress['address']) ? $resursBillingAddress['address'] : '',
                    'address_2' => !empty($resursBillingAddress['addressExtra']) ? $resursBillingAddress['addressExtra'] : '',
                    'city' => !empty($resursBillingAddress['city']) ? $resursBillingAddress['city'] : '',
                    'postcode' => !empty($resursBillingAddress['postal']) ? $resursBillingAddress['postal'] : '',
                    'country' => !empty($resursBillingAddress['countryCode']) ? $resursBillingAddress['countryCode'] : '',
                    'email' => !empty($resursBillingAddress['email']) ? $resursBillingAddress['email'] : '',
                    'phone' => !empty($resursBillingAddress['telephone']) ? $resursBillingAddress['telephone'] : '',
                ];
            } else {
                $return = [
                    'first_name' => !empty($resursBillingAddress['firstName']) ? $resursBillingAddress['firstName'] : '',
                    'last_name' => !empty($resursBillingAddress['lastName']) ? $resursBillingAddress['lastName'] : '',
                    'address_1' => !empty($resursBillingAddress['addressRow1']) ? $resursBillingAddress['addressRow1'] : '',
                    'address_2' => !empty($resursBillingAddress['addressExtra']) ? $resursBillingAddress['addressExtra'] : '',
                    'city' => !empty($resursBillingAddress['city']) ? $resursBillingAddress['city'] : '',
                    'postcode' => !empty($resursBillingAddress['postalCode']) ? $resursBillingAddress['postalCode'] : '',
                    'country' => getResursOption('country'),
                    'email' => !empty($customerData['email']) ? $customerData['email'] : '',
                    'phone' => !empty($customerData['phone']) ? $customerData['phone'] : '',
                ];
            }

            return $return;
        }

        /**
         * Extract post data, retrieve shipping/delivery address depending on legacy or non legacy (facelift).
         * @return array
         */
        public function getCustomerShippingAddress($customerData)
        {
            // Make place for FaceLift
            if (isset($customerData['deliveryAddress'])) {
                $return = isset($customerData['deliveryAddress']) && is_array($customerData['deliveryAddress']) ? $customerData['deliveryAddress'] : [];
            } else {
                $return = isset($customerData['delivery']) && is_array($customerData['delivery']) ? $customerData['delivery'] : [];
            }
            return $return;
        }

        /**
         * Prepare the order for the checkout
         */
        public function prepare_omni_order()
        {
            /**
             * What will be created if successful, and what will report undefined variable if unsuccessful
             * @var WC_Checkout $resursOrder
             */
            $resursOrder = null;
            $updatePaymentReference = false;

            // Get incoming request
            $url_arr = parse_url($_SERVER['REQUEST_URI']);
            $url_arr['query'] = str_replace('amp;', '', $url_arr['query']);
            parse_str($url_arr['query'], $request);

            /*
             * Get requested order reference
             */
            //$requestedPaymentId = isset($request['orderRef']) && !empty($request['orderRef']) ? $request['orderRef'] : null;

            /*
             * Check the order reference against the session
             */
            $requestedPaymentId = WC()->session->get('omniRef');
            $requestedUpdateOrder = WC()->session->get('omniId');

            /*
             * Get the customer data that should be created with the order
             */
            $customerData = isset($_POST['customerData']) && is_array($_POST['customerData']) ? $_POST['customerData'] : [];

            $faceliftCustomer = isset($_POST['customer']) && is_array($_POST['customer']) ? $_POST['customer'] : [];
            $faceliftPayment = isset($_POST['payment']) && is_array($_POST['payment']) ? $_POST['payment'] : [];
            $faceliftWc = isset($_POST['wooCommerce']) && is_array($_POST['wooCommerce']) ? $_POST['wooCommerce'] : [];
            $legacy = is_array($faceliftCustomer) && !count($faceliftCustomer);
            if (!$legacy) {
                $customerData = $faceliftCustomer;
            }

            /*
             * Get, if exists, the payment method and use it
             */
            if ($legacy) {
                $omniPaymentMethod = isset($_REQUEST['paymentMethod']) && !empty($_REQUEST['paymentMethod']) ? $_REQUEST['paymentMethod'] : 'resurs_bank_omnicheckout';
            } else {
                $omniPaymentMethod = isset($faceliftPayment['id']) && !empty($faceliftPayment['id']) ? $faceliftPayment['id'] : 'resurs_bank_omnicheckout';
                // Restore postdata from the request.

                foreach ($faceliftWc as $itemKey => $itemValue) {
                    $_POST[$itemKey] = $itemValue;
                    $_REQUEST[$itemKey] = $_POST[$itemKey];
                }
            }

            $errorString = '';
            $errorCode = '';
            // Default json data response
            $returnResult = [
                'success' => false,
                'errorString' => '',
                'errorCode' => '',
                'verified' => false,
                'hasOrder' => false,
                'resursData' => [],
            ];

            $returnResult['resursData']['reqId'] = $requestedPaymentId;
            $returnResult['resursData']['reqLocId'] = $requestedUpdateOrder;
            $returnResult['success'] = false;

            $flow = initializeResursFlow();
            $paymentSpec = self::get_payment_spec(WC()->cart);
            if (isset($paymentSpec['specLines']) && is_array($paymentSpec['specLines'])) {
                $returnResult = $this->updateOrderLines($requestedPaymentId, $paymentSpec, $returnResult, $flow);
            }

            if (isset($_REQUEST['updateReference'])) {
                if (isset($_REQUEST['omnicheckout_nonce'])) {
                    if (wp_verify_nonce($_REQUEST['omnicheckout_nonce'], 'omnicheckout')) {
                        if (isset($_REQUEST['orderRef']) && isset($_REQUEST['orderId'])) {
                            // Use the new way to detect updated references as the old is about to get removed.
                            if (!getResursUpdatePaymentReferenceResult($_REQUEST['orderId'])) {
                                $order = new WC_Order($_REQUEST['orderId']);

                                // If we experience successful order references here, the first
                                // backend call may have failed.
                                $updatePaymentReferenceStatus = $this->updatePaymentReference(
                                    $order,
                                    $flow,
                                    $_REQUEST['orderRef'],
                                    $_REQUEST['orderId']
                                );
                                $returnResult['updatePaymentReferenceStatus'] = $updatePaymentReferenceStatus;
                                if (!is_string($updatePaymentReferenceStatus) && (bool)$updatePaymentReferenceStatus === true) {
                                    update_post_meta($_REQUEST['orderId'], 'paymentId', $_REQUEST['orderId']);
                                    update_post_meta($_REQUEST['orderId'], 'paymentIdLast', $requestedPaymentId);
                                    $returnResult['success'] = true;
                                    $this->returnJsonResponse($returnResult, 200);
                                } else {
                                    update_post_meta($_REQUEST['orderId'], 'paymentId', $requestedPaymentId);
                                    update_post_meta($_REQUEST['orderId'], 'paymentIdLast', $requestedPaymentId);

                                    $returnResult['success'] = true;
                                    $this->returnJsonResponse($returnResult, 200);
                                }
                            } else {
                                $returnResult['success'] = true;
                                $this->returnJsonResponse($returnResult, 200);
                            }
                        } else {
                            $returnResult['success'] = false;
                            $returnResult['errorString'] = 'Order reference or orderId not set';
                            $returnResult['errorCode'] = 404;
                            $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                        }
                        die;
                    }
                }
            }

            if (!is_array($customerData)) {
                $customerData = [];
            }
            if (!count($customerData)) {
                $returnResult['errorString'] = 'No customer data set';
                $returnResult['errorCode'] = '404';
                $this->returnJsonResponse($returnResult);
            }

            $responseCode = 0;
            $allowOrderCreation = false;

            // Without the nonce, no background order can prepare
            if (isset($_REQUEST['omnicheckout_nonce'])) {
                // Debugging only.
                $debugWithoutNonceProblems = false;
                if (wp_verify_nonce($_REQUEST['omnicheckout_nonce'], 'omnicheckout') || $debugWithoutNonceProblems) {
                    $hasInternalErrors = false;
                    $returnResult['verified'] = true;

                    // This procedure normally works.
                    $testLocalOrder = wc_get_order_id_by_payment_id($requestedPaymentId);
                    if ((empty($testLocalOrder) && $requestedUpdateOrder) || (!is_numeric($testLocalOrder) && is_numeric($testLocalOrder) && $testLocalOrder != $requestedUpdateOrder)) {
                        $testLocalOrder = $requestedUpdateOrder;
                    }

                    $returnResult['resursData']['locId'] = $requestedPaymentId;

                    // If the order has already been created, the user may have been clicking more than one time in the frame, eventually due to payment method changes.
                    $wooBillingAddress = [];
                    $wooDeliveryAddress = [];
                    $resursBillingAddress = $this->getCustomerBillingAddress($customerData);
                    $resursDeliveryAddress = $this->getCustomerShippingAddress($customerData);
                    $failBilling = true;
                    $customerEmail = !empty($resursBillingAddress['email']) ? $resursBillingAddress['email'] : '';
                    if (count($resursBillingAddress)) {
                        $wooBillingAddress = $this->getCustomerBillingTransformed(
                            $resursBillingAddress,
                            $customerData,
                            $legacy
                        );
                        $failBilling = false;
                    }
                    if ($failBilling) {
                        $returnResult['errorString'] = 'Billing address update failed';
                        $returnResult['errorCode'] = '404';
                        $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                    }
                    if (count($resursDeliveryAddress)) {
                        $_POST['ship_to_different_address'] = true;

                        $wooDeliveryAddress = [];
                        foreach ($this->getCustomerWooFormFields($legacy) as $wooItem => $wooValue) {
                            $wooDeliveryAddress[$wooItem] = $this->getDeliveryFrom(
                                $wooValue,
                                $resursDeliveryAddress,
                                $wooBillingAddress,
                                $wooItem
                            );
                        }
                    } else {
                        // Helper for "sameAddress"-cases.
                        $_POST['ship_to_different_address'] = false;
                        $wooDeliveryAddress = $wooBillingAddress;
                    }

                    WC()->session->set('OMNICHECKOUT_PROCESSPAYMENT', true);
                    define('OMNICHECKOUT_PROCESSPAYMENT', true);

                    if (!$testLocalOrder) {
                        // WooCommerce POST-helper. Since we force removal of required fields in woocommerce, we need
                        // to help wooCommerce to adding the correct fields at this level to possibly pass through
                        // the internal field validation.
                        foreach ($wooBillingAddress as $billingKey => $billingValue) {
                            if (!isset($_POST[$billingKey])) {
                                $_POST['billing_' . $billingKey] = $billingValue;
                                $_REQUEST['billing_' . $billingKey] = $billingValue;
                            }
                        }
                        foreach ($wooDeliveryAddress as $deliveryKey => $deliveryValue) {
                            if (!isset($_POST[$deliveryKey])) {
                                $_POST['shipping_' . $deliveryKey] = $deliveryValue;
                                $_REQUEST['shipping_' . $deliveryKey] = $deliveryValue;
                            }
                        }

                        // Having a brand new order to process.
                        $resursOrder = new WC_Checkout();
                        try {
                            // As we work with the session, we'd try to get the current order that way.
                            // process_checkout() does a lot of background work for this.
                            $internalErrorMessage = '';
                            $internalErrorCode = 0;
                            try {
                                // Create order by WOO internal API.
                                $resursOrder->process_checkout();
                                $wcNotices = wc_get_notices();
                                if (isset($wcNotices['error']) && count($wcNotices['error'])) {
                                    $hasInternalErrors = true;
                                    $wcErrorCollection = [];
                                    foreach ($wcNotices['error'] as $arr) {
                                        $wcErrorCollection[] = $arr['notice'];
                                    }
                                    $internalErrorMessage = implode("<br>\n", $wcErrorCollection);
                                    $internalErrorCode = 200;
                                    $returnResult['success'] = false;
                                    $returnResult['errorString'] = !empty($internalErrorMessage) ? $internalErrorMessage : 'OrderId missing';
                                    $returnResult['errorCode'] = $internalErrorCode;
                                    wc_clear_notices();
                                    $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                                }
                            } catch (Exception $e) {
                                $hasInternalErrors = true;
                                $internalErrorMessage = $e->getMessage();
                                $internalErrorCode = $e->getCode();
                            }
                            $order = null;
                            $orderId = null;
                            try {
                                $orderId = WC()->session->get('order_awaiting_payment');
                                setResursPaymentMethodMeta($orderId);
                                $order = new WC_Order($orderId);
                            } catch (Exception $e) {
                                $hasInternalErrors = true;
                                $internalErrorMessage = $e->getMessage();
                                $internalErrorCode = $e->getCode();
                            }

                            if ($order !== null) {
                                setResursOrderMetaData(
                                    $order->get_id(),
                                    'RcoProcessPaymentStart',
                                    time()
                                );
                            }

                            WC()->session->set('omniId', $orderId);
                            $returnResult['orderId'] = $orderId;
                            $returnResult['session'] = WC()->session;
                            $returnResult['hasInternalErrors'] = $hasInternalErrors;
                            $returnResult['updatePaymentReferenceStatus'] = null;
                            if ($orderId > 0 && !$hasInternalErrors) {
                                /** @var WC_Gateway_ResursBank_Omni $omniClass */
                                $omniClass = new WC_Gateway_ResursBank_Omni();
                                $order->set_payment_method($omniClass);
                                $order->set_address($wooBillingAddress, 'billing');
                                $order->set_address($wooDeliveryAddress, 'shipping');
                                update_post_meta($orderId, 'paymentId', $requestedPaymentId);
                                update_post_meta($orderId, 'omniPaymentMethod', $omniPaymentMethod);

                                $hasInternalErrors = false;
                                $internalErrorMessage = null;
                                $updatePaymentReferenceStatus = $this->updatePaymentReference(
                                    $order,
                                    $flow,
                                    $requestedPaymentId,
                                    $orderId
                                );
                                // If we experience successful order references here, the first
                                // backend call may have failed.
                                if (!is_string($updatePaymentReferenceStatus) && (bool)$updatePaymentReferenceStatus === true) {
                                    update_post_meta($orderId, 'paymentId', $orderId);
                                    update_post_meta($orderId, 'paymentIdLast', $requestedPaymentId);
                                } else {
                                    update_post_meta($orderId, 'paymentId', $requestedPaymentId);
                                    update_post_meta($orderId, 'paymentIdLast', $requestedPaymentId);
                                }
                                $returnResult['updatePaymentReferenceStatus'] = $updatePaymentReferenceStatus;
                            } else {
                                $returnResult['success'] = false;
                                $returnResult['errorString'] = !empty($internalErrorMessage) ? $internalErrorMessage : 'OrderId missing';
                                $returnResult['errorCode'] = $internalErrorCode;
                                $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                                die();
                            }
                        } catch (Exception $createOrderException) {
                            $returnResult['success'] = false;
                            $returnResult['errorString'] = $createOrderException->getMessage();
                            $returnResult['errorCode'] = $createOrderException->getCode();
                            $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                            die();
                        }
                        $returnResult['success'] = true;
                        $responseCode = 200;
                        WC()->session->set('resursCreatePass', '1');
                    } else {
                        // If the order already exists, continue without errors (if we reached this code,
                        // it has been because of the nonce which should be considered safe enough)
                        $order = new WC_Order($testLocalOrder);
                        // Making sure this data follows.
                        $omniClass = new WC_Gateway_ResursBank_Omni();
                        $order->set_payment_method($omniClass);
                        update_post_meta($order->get_id(), 'omniPaymentMethod', $omniPaymentMethod);
                        setResursPaymentMethodMeta($order->get_id());
                        $currentOrderStatus = $order->get_status();
                        // Going generic response, to make it possible to updateOrderReference on the fly
                        // in this state.
                        $returnResult['success'] = true;
                        $returnResult['errorCode'] = 200;
                        if ($currentOrderStatus === 'failed') {
                            $order->set_status(
                                'pending',
                                __(
                                    '[Resurs Bank] Customer retried to place order, after failure.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                        }

                        $updatePaymentReferenceStatus = $this->updatePaymentReference(
                            $order,
                            $flow,
                            $requestedPaymentId,
                            $testLocalOrder
                        );

                        // If we experience successful order references here, the first
                        // backend call may have failed.
                        if (!is_string($updatePaymentReferenceStatus) &&
                            (bool)$updatePaymentReferenceStatus === true
                        ) {
                            update_post_meta($order->get_id(), 'paymentId', $order->get_id());
                            update_post_meta($order->get_id(), 'paymentIdLast', $requestedPaymentId);
                        } else {
                            $order->add_order_note(
                                sprintf(
                                    __(
                                        '[Resurs Bank] Order id reference could not be updated during payment: %s.',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $updatePaymentReferenceStatus
                                )
                            );
                            update_post_meta($order->get_id(), 'paymentId', $requestedPaymentId);
                            update_post_meta($order->get_id(), 'paymentIdLast', $requestedPaymentId);
                        }

                        $responseCode = $returnResult['errorCode'];
                        $order->set_address($wooBillingAddress, 'billing');
                        $order->set_address($wooDeliveryAddress, 'shipping');
                        $order->save();
                        $returnResult['hasOrder'] = true;
                        $returnResult['usingOrder'] = $testLocalOrder;
                        $returnResult['errorString'] = 'Order already exists';
                        $returnResult['updatePaymentReferenceStatus'] = $updatePaymentReferenceStatus;
                    }
                } else {
                    $returnResult['errorString'] = __(
                        'The nonce key has expired or mismatching, so the payment can not be accepted. Please reload the page and try again. If this happens again, contact support.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                    $returnResult['errorCode'] = 403;
                    $responseCode = 403;
                }
            } else {
                $returnResult['errorString'] = __(
                    'The nonce key is missing, so the payment can not be accepted. Please reload the page and try again. If this happens again, contact support.',
                    'resurs-bank-payment-gateway-for-woocommerce'
                );
                $returnResult['errorCode'] = 403;
                $responseCode = 403;
            }

            $this->returnJsonResponse($returnResult, $responseCode, $resursOrder);
        }

        /**
         * Get delivery address by delivery address with fallback on billing.
         * @param $key
         * @param $deliveryArray
         * @param $billingArray
         * @return string
         */
        public function getDeliveryFrom($key, $deliveryArray, $billingArray, $secondKey)
        {
            if (isset($deliveryArray[$key]) && !empty($deliveryArray[$key])) {
                if (is_null($key)) {
                    $return = $deliveryArray[$key];
                } else {
                    $return = $deliveryArray[$key];
                }
            } elseif (isset($deliveryArray[$secondKey]) && !empty($deliveryArray[$secondKey])) {
                if (is_null($secondKey)) {
                    $return = $deliveryArray[$secondKey];
                } else {
                    $return = $deliveryArray[$secondKey];
                }
            } elseif (isset($billingArray[$key]) && !empty($billingArray[$key])) {
                if (is_null($key)) {
                    $return = $billingArray[$key];
                } else {
                    $return = $billingArray[$key];
                }
            } elseif (isset($billingArray[$secondKey]) && !empty($billingArray[$secondKey])) {
                if (is_null($secondKey)) {
                    $return = $billingArray[$secondKey];
                } else {
                    $return = $billingArray[$secondKey];
                }
            } else {
                $return = '';
            }

            return $return;
        }

        /**
         * @param $order
         * @param ResursBank $flow
         * @param $requestedPaymentId
         * @param $requestedUpdateOrder
         * @return string
         */
        private function updatePaymentReference($order, $flow, $requestedPaymentId, $requestedUpdateOrder)
        {
            if (getResursOption('postidreference')) {
                if (empty($requestedUpdateOrder)) {
                    $currentPaymentId = r_wc_get_order_id_by_order_item_id('paymentId');
                }

                if (getResursUpdatePaymentReferenceResult($requestedUpdateOrder)) {
                    $order->add_order_note(
                        sprintf(
                            __(
                                '[Resurs Bank] updatePaymentReference has already been executed once.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                            $requestedPaymentId,
                            $requestedUpdateOrder
                        )
                    );
                    return true;
                }

                if (!empty($requestedPaymentId) && !empty($requestedUpdateOrder)) {
                    // Blindly try this once again.
                    try {
                        $updatePaymentReferenceStatus = $flow->updatePaymentReference(
                            $requestedPaymentId,
                            $requestedUpdateOrder
                        );
                        update_post_meta($requestedUpdateOrder, 'updateResursReferenceSuccess', true);
                        update_post_meta($requestedUpdateOrder, 'updateResursReferenceMessage', '');
                        $order->add_order_note(
                            sprintf(
                                __(
                                    '[Resurs Bank] updatePaymentReference successful. Changed from %s to %s.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $requestedPaymentId,
                                $requestedUpdateOrder
                            )
                        );
                    } catch (Exception $e) {
                        // Errors that will be refelected down to order notices where the references is
                        // not properly handled. This requires ECom-upgrades.
                        // 1-100 may be thrown from ecommerce
                        // 400-500 is webserver based errors.
                        if (
                            (
                                $e->getCode() >= 400 &&
                                $e->getCode() < 500
                            ) ||
                            (
                                $e->getCode() >= 1 &&
                                $e->getCode() < 100
                            )
                        ) {
                            $message = 'Exception ' . $e->getCode() . ' indicates problem.';
                            $eString = $e->getMessage();
                            //$returnResult['errorCode'] = 200;
                            $updatePaymentReferenceStatus = empty($eString) ? $message : $eString;
                        } else {
                            $updatePaymentReferenceStatus = $e->getMessage();
                        }
                        update_post_meta(
                            $requestedUpdateOrder,
                            'updateResursReferenceSuccess',
                            false
                        );
                        update_post_meta(
                            $requestedUpdateOrder,
                            'updateResursReferenceMessage',
                            sprintf(
                                '%s: %s',
                                $e->getCode(),
                                $e->getMessage()
                            ),
                            false
                        );

                        $order->add_order_note(
                            sprintf(
                                __(
                                    '[Resurs Bank] updatePaymentReference received exception from API, when trying set %s to %s: (%s) %s. Is it already updated?',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $requestedPaymentId,
                                $requestedUpdateOrder,
                                $e->getCode(),
                                $e->getMessage()
                            )
                        );
                    }
                } else {
                    $updatePaymentReferenceStatus = 'Reference or order id is missing.';
                    $order->add_order_note(
                        __(
                            '[Resurs Bank] Reference or order id is missing, so the reference can not be updated.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                }
            } else {
                $updatePaymentReferenceStatus = 'Disabled';
            }

            return $updatePaymentReferenceStatus;
        }

        /**
         * @param array $jsonArray
         * @param int $responseCode
         * @param null $resursOrder
         */
        private function returnJsonResponse($jsonArray = [], $responseCode = 200, $resursOrder = null)
        {
            header('Content-Type: application/json', true, $responseCode);
            echo json_encode($jsonArray);
            die();
        }

        /**
         * Check result of signing, book the payment and complete the order
         */
        public function check_signing_response()
        {
            global $woocommerce;

            $url_arr = parse_url($_SERVER['REQUEST_URI']);
            $url_arr['query'] = str_replace('amp;', '', $url_arr['query']);
            parse_str($url_arr['query'], $request);
            $order_id = isset($request['order_id']) && !empty($request['order_id']) ? $request['order_id'] : null;
            /** @var $order WC_Order */
            $order = new WC_Order($order_id);
            $getRedirectUrl = $this->get_return_url($order);
            $currentStatus = $order->get_status();

            $paymentId = wc_get_payment_id_by_order_id($order_id);
            $isHostedFlow = false;
            $requestedPaymentId = isset($request['payment_id']) ? $request['payment_id'] : '';
            $hasBookedHostedPayment = false;
            $bookedPaymentId = 0;
            $bookedStatus = null;
            $paymentInfo = null;

            $flowType = isset($request['flow-type']) ? $request['flow-type'] : '';

            if (isset($_REQUEST['flow-type']) && empty($flowType)) {
                $flowType = $_REQUEST['flow-type'];
            }
            $eventType = isset($request['event-type']) ? $request['event-type'] : '';
            if (isset($_REQUEST['event-type']) && empty($eventType)) {
                $eventType = $_REQUEST['event-type'];
            }
            if (isset($request['flow-type'])) {
                if ($request['flow-type'] == 'check_hosted_response') {
                    if (isResursHosted()) {
                        $isHostedFlow = true;
                        $bookedPaymentId = $requestedPaymentId;
                        try {
                            $paymentInfo = $this->flow->getPayment($requestedPaymentId);
                        } catch (Exception $e) {
                        }
                        $bookedStatus = 'BOOKED';
                        // If unable to credit/debit, it may have been annulled
                        if (!$this->flow->canCredit($paymentInfo) && !$this->flow->canDebit($paymentInfo)) {
                            $bookedStatus = 'FAILED';
                        }
                        // Able to credit the order by not debit, it may be finalized.
                        if ($this->flow->canCredit($paymentInfo) && !$this->flow->canDebit($paymentInfo)) {
                            $bookedStatus = 'FINALIZED';
                        }
                        if (isset($paymentInfo->frozen)) {
                            $bookedStatus = 'FROZEN';
                        }
                    }
                } elseif ($flowType == 'check_omni_response') {
                    /*
                     * This part will from now take care of successful orders - the stuff that has been left below is however needed to "finalize"
                     * the payment when the customer is redirected back to the landing page.
                     *
                     * (Finalize in this case is not just Resurs finalization, it's also about completing the order at the WooCom-side)
                     */
                    WC()->session->set('omniRef', null);
                    WC()->session->set('omniRefCreated', null);
                    WC()->session->set('omniRefAge', null);
                    WC()->session->set('omniId', null);

                    $paymentId = isset($request['payment_id']) && !empty($request['payment_id']) ? $request['payment_id'] : null;
                    $order_id = wc_get_order_id_by_payment_id($paymentId);
                    $order = new WC_Order($order_id);

                    $storeIdTest = !getResursFlag('STORE_ID') ? null : getResursFlag('STORE_ID');
                    $storeId = apply_filters('resursbank_set_storeid', $storeIdTest);
                    if (!empty($storeId)) {
                        update_post_meta($order_id, 'resursStoreId', $storeId);
                    }

                    if ($request['failInProgress'] == '1' ||
                        (
                            isset($_REQUEST['failInProgress']) &&
                            $_REQUEST['failInProgress'] == '1'
                        )
                    ) {
                        $order->update_status(
                            'cancelled',
                            __(
                                'The payment failed during purchase',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                        wc_add_notice(
                            __(
                                'The purchase from Resurs Bank was by some reason not accepted. Please contact ' .
                                'customer services, or try again with another payment method.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                            'error'
                        );
                        update_post_meta($order_id, 'rcoOrderFailed', true);

                        WC()->session->set('order_awaiting_payment', true);
                        $getRedirectUrl = wc_get_cart_url();
                    } else {
                        $getRedirectUrl = $this->get_return_url($order);
                        $order->add_order_note(
                            '[Resurs Bank] ' .
                            __(
                                'The payment are signed and booked. Waiting for further statuses.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );

                        try {
                            $this->updateOrderByResursPaymentStatus($order, $paymentId);
                        } catch (Exception $e) {
                            $order->add_order_note($e->getMessage());
                        }
                        WC()->cart->empty_cart();
                        WC()->session->set('OMNICHECKOUT_PROCESSPAYMENT', false);
                        if ($order !== null) {
                            setResursOrderMetaData(
                                $order->get_id(),
                                'RcoProcessPaymentEnd',
                                time()
                            );
                        }
                    }
                    wp_safe_redirect($getRedirectUrl);

                    return;
                }
            }

            if ($paymentId !== $requestedPaymentId && !$isHostedFlow) {
                $order->update_status('failed');
                wc_add_notice(
                    __(
                        'The payment can not complete. Please choose another payment method.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'error'
                );
            }

            $signedResult = null;
            $bookSigned = false;

            if (!$isHostedFlow) {
                try {
                    $signedResult = $this->flow->bookSignedPayment($paymentId);
                    $bookedStatus = isset($signedResult->bookPaymentStatus) ? $signedResult->bookPaymentStatus : null;
                    $order->add_order_note(
                        sprintf(
                            __(
                                'Customer returned from signing. Routine for book signed payment executed, got response %s.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                            $bookedStatus
                        )
                    );
                    $bookSigned = true;
                } catch (Exception $bookSignedException) {
                    resursEventLogger(
                        sprintf(
                            'bookSignedPaymentException (%s): %s',
                            $bookSignedException->getCode(),
                            $bookSignedException->getMessage()
                        )
                    );
                }
                if ($bookSigned) {
                    //$bookedStatus = isset($signedResult->bookPaymentStatus) ? $signedResult->bookPaymentStatus : null;
                    $bookedPaymentId = isset($signedResult->paymentId) ? $signedResult->paymentId : null;
                }
            }
            if ((empty($bookedPaymentId) && !$bookSigned) && !$isHostedFlow) {
                // This is where we land where $bookSigned gets false, normally when there is an exception at the bookSignedPayment level
                // Before leaving this process, we'll check if something went wrong and the booking is already there
                $hasGetPaymentErrors = false;
                $exceptionMessage = null;
                $getPaymentExceptionMessage = null;
                $paymentCheck = null;
                try {
                    $paymentCheck = $this->flow->getPayment($paymentId);
                } catch (Exception $getPaymentException) {
                    $hasGetPaymentErrors = true;
                    $getPaymentExceptionMessage = $getPaymentException->getMessage();
                }
                $paymentIdCheck = isset($paymentCheck->paymentId) ? $paymentCheck->paymentId : null;
                /* If there is a payment, this order has been already got booked */
                if (!empty($paymentIdCheck)) {
                    wc_add_notice(
                        __('The payment already exists', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'error'
                    );
                } else {
                    /* If not, something went wrong further into the processing */
                    if ($hasGetPaymentErrors) {
                        if (isset($getPaymentException) && !empty($getPaymentException)) {
                            //$exceptionMessage = $getPaymentException->getMessage();
                            wc_add_notice(__(
                                'We could not finish your order. Please, contact support for more information.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ), 'error');
                        }
                        wc_add_notice($exceptionMessage, 'error');
                    } else {
                        wc_add_notice(__(
                            'An unknown error occured in signing method. Please, try again later',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ), 'error');
                    }
                }
                /* We should however not return with a success */
                //wp_safe_redirect($this->get_return_url($order));
                wp_safe_redirect(wc_get_cart_url());
            }
            try {
                /* So, if we passed through the above control, it's time to check out the status */
                if ($bookedPaymentId) {
                    update_post_meta($order_id, 'paymentId', $bookedPaymentId);
                } else {
                    /* When things fail, and there is no id available (we should hopefully never get here, since we're making other controls above) */
                    $bookedStatus = 'DENIED';
                }

                add_post_meta(
                    $order_id,
                    'orderBookStatus',
                    sprintf(
                        '%s (%s)',
                        $bookedStatus,
                        date('Y-m-d H:i:s', time())
                    )
                );

                /* Continue. */
                if ($bookedStatus === 'FROZEN') {
                    $order->update_status(
                        'on-hold',
                        __(
                            '[Resurs Bank] The payment are frozen, while waiting for manual control.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                } elseif ($bookedStatus === 'BOOKED') {
                    $order->update_status(
                        'processing',
                        __(
                            '[Resurs Bank] The payment are signed and booked.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                } elseif ($bookedStatus === 'FINALIZED') {
                    try {
                        $order->add_order_note(
                            __(
                                '[Resurs Bank] The payment are signed, booked and finalized.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                        $this->updateOrderByResursPaymentStatus(
                            $order,
                            $bookedPaymentId
                        );
                    } catch (Exception $e) {
                        $order->add_order_note(
                            sprintf(
                                '[Resurs Bank] Error %d in %s: %s.',
                                $e->getCode(),
                                __FUNCTION__,
                                $e->getMessage()
                            )
                        );
                        wc_add_notice($e->getMessage(), 'error');
                        $getRedirectUrl = wc_get_cart_url();
                    }
                } elseif ($bookedStatus === 'DENIED') {
                    $order->update_status('failed');
                    update_post_meta($order_id, 'orderDenied', true);
                    wc_add_notice(
                        __(
                            'The payment can not complete. Please choose another payment method.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'error'
                    );
                    $getRedirectUrl = wc_get_cart_url();
                } elseif ($bookedStatus === 'FAILED') {
                    $order->update_status(
                        'failed',
                        __(
                            'An error occured during the update of the booked payment. The payment id was never received properly in signing response',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    wc_add_notice(
                        __(
                            'An unknown error occured. Please, try again later',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'error'
                    );
                    $getRedirectUrl = wc_get_cart_url();
                }
            } catch (Exception $e) {
                wc_add_notice(
                    __(
                        'Something went wrong during the signing process.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'error'
                );
                $getRedirectUrl = wc_get_cart_url();
            }

            $hasAnnulment = get_post_meta($order->get_id(), 'hasAnnulment', true);
            if (!$getRedirectUrl || $hasAnnulment == '1') {
                $getRedirectUrl = wc_get_cart_url();
            }

            wp_safe_redirect($getRedirectUrl);
        }

        /**
         * Generate the payment methods that were returned from Resurs Bank API
         *
         * @param array $payment_methods The payment methods
         */
        public function generate_payment_gateways($payment_methods)
        {
            $methods = [];
            $class_files = [];
            $idMerchant = 0;
            foreach ($payment_methods as $payment_method) {
                $methods[] = 'resurs-bank-id-' . $payment_method->id;
                $class_files[] = 'resurs_bank_nr_' . $idMerchant . '_' . $payment_method->id . '.php';
                $this->write_class_to_file($payment_method);
                $idMerchant++;
            }
            $this->UnusedPaymentClassesCleanup($class_files);
            set_transient('resurs_bank_class_files', $class_files);
        }

        /**
         * Generates and writes a class for a specified payment methods to file
         *
         * @param stdClass $payment_method A payment method return from Resurs Bank API
         */
        public function write_class_to_file($payment_method)
        {
            write_resurs_class_to_file($payment_method);
        }

        /**
         * Validate the payment fields
         *
         * Never called from within this class, only by those that extends from this class and that are created in write_class_to_file
         *
         * @return bool Whether or not the validation passed
         * @throws Exception
         */
        public function validate_fields()
        {
            global $woocommerce;
            if (getResursOption('resursvalidate')) {
                return true;
            }

            $className = $_REQUEST['payment_method'];

            $methodName = str_replace('resurs_bank_nr_', '', $className);
            $transientMethod = $this->getTransientMethod($methodName);
            $countryCode = isset($_REQUEST['billing_country']) ? $_REQUEST['billing_country'] : getResursOption('country');
            $customerType = isset($_REQUEST['ssnCustomerType']) ? $_REQUEST['ssnCustomerType'] : 'NATURAL';
            if (isset($transientMethod->customerType) &&
                in_array('LEGAL', (array)$transientMethod->customerType) &&
                !in_array('NATURAL', (array)$transientMethod->customerType)
            ) {
                $customerType = 'LEGAL';
            }

            /** @var $flow ResursBank */
            $flow = initializeResursFlow();
            $regEx = $flow->getRegEx(null, $countryCode, $customerType);
            // TODO: Leave the oldFlowSimulator/regex behind and replace with own field generators.
            $methodFieldsRequest = $flow->getTemplateFieldsByMethodType($transientMethod, $customerType);
            $methodFields = $methodFieldsRequest['fields'];
            if (isset($transientMethod->specificType) &&
                $transientMethod->specificType === 'CARD' &&
                isset($methodFields) &&
                in_array('card-number', $methodFields, true)
            ) {
                foreach ($methodFields as $idx => $fld) {
                    if ($fld === 'card-number') {
                        unset($methodFields[$idx]);
                        break;
                    }
                }
            }

            $fetchedGovernmentId = (isset($_REQUEST['applicant-government-id']) ? trim($_REQUEST['applicant-government-id']) : '');
            if (empty($fetchedGovernmentId) && isset($_REQUEST['ssn_field']) && !empty($_REQUEST['ssn_field'])) {
                $_REQUEST['applicant-government-id'] = $_REQUEST['ssn_field'];
            }

            $errors = [];

            $validationFail = false;
            foreach ($methodFields as $fieldName) {
                if (isset($_REQUEST[$fieldName]) && isset($regEx[$fieldName])) {
                    if ($fieldName === 'applicant-government-id' &&
                        empty($_REQUEST[$fieldName]) &&
                        $flow->getCanSkipGovernmentIdValidation()) {
                        continue;
                    }
                    $regExString = $regEx[$fieldName];
                    $regExString = str_replace('\\\\', '\\', $regExString);
                    $fieldNameOriginal = $fieldName;
                    $fieldData = isset($_REQUEST[$fieldName]) ? trim($_REQUEST[$fieldName]) : '';
                    $fieldNameTranslated = $this->get_payment_method_form_label($fieldName, $customerType);
                    if (!empty($fieldNameTranslated)) {
                        $fieldName = $fieldNameTranslated;
                    }
                    $invalidFieldError = sprintf(
                        __(
                            'The field <b>"%s"</b> has invalid information ("%s") or is missing.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $fieldName,
                        $fieldData
                    );

                    if ($fieldNameOriginal === 'card-number' && empty($fieldData)) {
                        continue;
                    }
                    $fieldContent = trim($_REQUEST[$fieldNameOriginal]);
                    if (preg_match('/email/', $fieldNameOriginal)) {
                        if (!isset($errors[$invalidFieldError]) && !filter_var($fieldContent, FILTER_VALIDATE_EMAIL)) {
                            $errors[$invalidFieldError] = true;
                            wc_add_notice($invalidFieldError, 'error');
                            $validationFail = true;
                            break;
                        }
                        if (!isset($errors[$invalidFieldError]) && empty($fieldContent)) {
                            $errors[$invalidFieldError] = true;
                            wc_add_notice($invalidFieldError, 'error');
                            $validationFail = true;
                            break;
                        }
                    } else {
                        if (!isset($errors[$invalidFieldError]) && !preg_match('/' . $regExString . '/', trim($fieldContent))) {
                            $errors[$invalidFieldError] = true;
                            wc_add_notice($invalidFieldError, 'error');
                            $validationFail = true;
                            break;
                        }
                        // Empty data should only validate empty if we have a regex to validate with.
                        if (!isset($errors[$invalidFieldError]) && !empty($regExString) && empty($fieldContent)) {
                            $errors[$invalidFieldError] = true;
                            wc_add_notice($invalidFieldError, 'error');
                            $validationFail = true;
                            break;
                        };
                    }
                }
            }
            if ($validationFail) {
                return false;
            }

            return true;
        }

        /**
         * @return bool
         */
        public function is_valid_for_use()
        {
            return true;
        }

        /**
         * Retrieves the best guess of the client's actual IP address.
         * Takes into account numerous HTTP proxy headers due to variations
         * in how different ISPs handle IP addresses in headers between hops.
         *
         * Developer note 2016-03-02
         *
         * Since proxy based headers, that is normally sent by web-browsers or proxy engines, can be manipulated/spoofed,
         * we are not accepting manipulated headers of this type, by default.
         *
         * Therefore, we're not accepting headers that could be manipulated by default.
         */
        public static function get_ip_address()
        {
            $return = $_SERVER['REMOTE_ADDR'];

            $handleNatConnections = getResursOption('handleNatConnections');
            if ($handleNatConnections) {
                // check for shared internet/ISP IP
                if (!empty($_SERVER['HTTP_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
                    $return = $_SERVER['HTTP_CLIENT_IP'];
                }
                // check for IPs passing through proxies
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    // check if multiple ips exist in var
                    $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    foreach ($iplist as $ip) {
                        if (self::validate_ip($ip)) {
                            $return = $ip;
                            break;
                        }
                    }
                }
                if (!empty($_SERVER['HTTP_X_FORWARDED']) && self::validate_ip($_SERVER['HTTP_X_FORWARDED'])) {
                    $return = $_SERVER['HTTP_X_FORWARDED'];
                }
                if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
                    $return = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
                }
                if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && self::validate_ip($_SERVER['HTTP_FORWARDED_FOR'])) {
                    $return = $_SERVER['HTTP_FORWARDED_FOR'];
                }
                if (!empty($_SERVER['HTTP_FORWARDED']) && self::validate_ip($_SERVER['HTTP_FORWARDED'])) {
                    $return = $_SERVER['HTTP_FORWARDED'];
                }
            }

            // return unreliable ip since all else failed
            return $return;
        }

        /** @var string $ip Access to undeclared static property fix */
        private static $ip;

        /**
         * Ensures an ip address is both a valid IP and does not fall within
         * a private network range.
         *
         * @access public
         *
         * @param string $ip
         *
         * @return bool
         */
        public static function validate_ip($ip)
        {
            if (filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 |
                    FILTER_FLAG_IPV6 |
                    FILTER_FLAG_NO_PRIV_RANGE |
                    FILTER_FLAG_NO_RES_RANGE
                ) === false
            ) {
                return false;
            }

            self::$ip = $ip;

            return true;
        }

        /**
         * Output the admin options for the plugin. Also used for checking for various buttonclicks, for example registering callbacks
         */
        public function admin_options()
        {
            $_REQUEST['tab'] = 'tab_resursbank';
            $_REQUEST['section'] = '';
            $url = admin_url('admin.php');
            $url = add_query_arg('page', $_REQUEST['page'], $url);
            $url = add_query_arg('tab', $_REQUEST['tab'], $url);
            $url = add_query_arg('section', $_REQUEST['section'], $url);
            wp_safe_redirect($url);
            die('Deprecated space');
        }

        /**
         * @param $temp_class_files
         */
        private function UnusedPaymentClassesCleanup($temp_class_files)
        {
            $allIncludes = [];
            $path = plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath();
            $globIncludes = glob(plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath() . '*.php');
            if (is_array($globIncludes)) {
                foreach ($globIncludes as $filename) {
                    $allIncludes[] = str_replace($path, '', $filename);
                }
            }
            if (is_array($temp_class_files)) {
                foreach ($allIncludes as $exclude) {
                    if (!in_array($exclude, $temp_class_files)) {
                        @unlink($path . $exclude);
                    }
                }
            }
        }

        /**
         * Get available payment methods. Either from Resurs Bank API or transient cache
         *
         * @param bool $force_file_refresh If new files should be forced or not
         * @param bool $skipGateway Set to true if you want to skip the gateway generator (normally, you want this while listing methods in a checkout, not else)
         *
         * @return array Array containing an error message, if any errors occurred, and the payment methods, if any available and no errors occurred.
         * @throws Exception
         */
        public function get_payment_methods($force_file_refresh = false, $skipGateway = false)
        {
            $returnArr = [];
            try {
                $paymentMethods = $this->flow->getPaymentMethods([], true);
                if (!$skipGateway) {
                    $this->generate_payment_gateways($paymentMethods);
                }
                /*
                 *  This is normally wanted by some parts of the system
                 */
                set_transient('resurs_bank_payment_methods', $paymentMethods);
                $returnArr['error'] = '';
                $returnArr['methods'] = $paymentMethods;
                $returnArr['generate_new_files'] = true;
            } catch (Exception $e) {
                $returnArr['error'] = $e->getMessage();
                $returnArr['methods'] = '';
                $returnArr['generate_new_files'] = false;
            }

            return $returnArr;
        }

        /**
         * @param int $sum
         * @return string|void
         * @throws Exception
         */
        public static function get_annuity_html($sum = 0)
        {
            $displayAnnuity = '';
            $return = '';
            $annuityFactorPrice = $sum;
            if ((int)$sum === 0 && isset($_REQUEST['sum']) && (int)$_REQUEST['sum']) {
                $annuityFactorPrice = (float)$_REQUEST['sum'];
            }

            /** @var $flow ResursBank */
            $flow = initializeResursFlow();
            $annuityMethod = trim(getResursOption('resursAnnuityMethod'));

            $customWidgetSetting = intval(getResursOption('partPayWidgetPage'));
            if ($customWidgetSetting <= 1) {
                $customWidgetSetting = 0;
            }

            if (!empty($annuityMethod)) {
                try {
                    $methodList = $flow->getPaymentMethodSpecific($annuityMethod);
                    if (!is_array($methodList) && !is_object($methodList)) {
                        $methodList = [];
                    }
                    $allowAnnuity = false;
                    if ((is_array($methodList) && count($methodList)) || is_object($methodList)) {
                        $allowAnnuity = true;
                    }
                    if ($allowAnnuity) {
                        $annuityFactors = getResursOption('resursCurrentAnnuityFactors');
                        $annuityDuration = getResursOption('resursAnnuityDuration');
                        $payFrom = $flow->getAnnuityPriceByDuration(
                            $annuityFactorPrice,
                            $annuityFactors,
                            $annuityDuration
                        );
                        $currentCountry = getResursOption('country');
                        if ($currentCountry !== 'FI') {
                            $paymentLimit = 150;
                        } else {
                            $paymentLimit = 15;
                        }
                        $chosenPaymentLimit = $paymentLimit;

                        if (isResursTest()) {
                            // Clean out lowest limit in test and always show this.
                            $paymentLimit = 0;
                        }

                        //$realPaymentLimit = $paymentLimit;
                        if ((int)$payFrom >= $paymentLimit || $payFrom === 0) {
                            $payFromAnnuity = wc_price($payFrom);
                            $costOfPurchase = admin_url('admin-ajax.php') . "?action=get_priceinfo_ajax&method=$annuityMethod&amount=" . $annuityFactorPrice;
                            $onclick = 'window.open(\'' . $costOfPurchase . '\')';

                            // https://test.resurs.com/docs/pages/viewpage.action?pageId=7208965#Hooks/filtersv2.2-Filter:Partpaymentwidgetstring
                            $defaultAnnuityString = sprintf(
                                __(
                                    'Part pay from %s per month',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $payFromAnnuity
                            );
                            if (!$payFrom) {
                                $defaultAnnuityString = '';
                                $pipeString = '';
                            } else {
                                $pipeString = ' | ';
                            }
                            $useAnnuityString = $defaultAnnuityString;
                            $customAnnuityString = apply_filters(
                                'resursbank_custom_annuity_string',
                                $defaultAnnuityString,
                                $payFromAnnuity
                            );
                            if (!empty($customAnnuityString)) {
                                $useAnnuityString = $customAnnuityString;
                            }

                            if ($customWidgetSetting > 0) {
                                /** @var WP_Post $customWidgetPost */
                                $customWidgetPost = get_post($customWidgetSetting);

                                $tags = [
                                    '/\[costOfPurchase\]/i',
                                    '/\[payFromAnnuity\]/i',
                                    '/\[defaultAnnuityString\]/i',
                                    '/\[paymentLimit\]/i',
                                    '/\[annuityFactors\]/i',
                                    '/\[annuityDuration\]/i',
                                    '/\[payFrom\]/i',
                                ];
                                $replaceWith = [
                                    $costOfPurchase,
                                    $payFromAnnuity,
                                    $defaultAnnuityString,
                                    $paymentLimit,
                                    print_r($annuityFactors, true),
                                    $annuityDuration,
                                    $payFrom,
                                ];

                                $postContent = preg_replace(
                                    $tags,
                                    $replaceWith,
                                    $customWidgetPost->post_content
                                );

                                $displayAnnuity = sprintf(
                                    '<div class="resursPartPaymentInfo">%s</div>',
                                    $postContent
                                );
                            } else {
                                $displayAnnuity .= '<div id="resursAnnuityFactorHtml" class="resursPartPaymentInfo">';
                                if (isResursTest()) {
                                    $displayAnnuity .= sprintf(
                                        '<div class="resursAnnuityStyle">%s</div>',
                                        sprintf(
                                            __(
                                                'Test enabled: In production, this information is shown when the ' .
                                                'minimum payment amount is above <b>%s</b>. Currently the payment amount is %s.',
                                                'resurs-bank-payment-gateway-for-woocommerce'
                                            ),
                                            $chosenPaymentLimit,
                                            $payFrom
                                        )
                                    );
                                }
                                $displayAnnuity .= sprintf(
                                    '<span>%s</span>%s<span class="resursPartPayInfoLink" onclick="%s">%s</span></div>',
                                    $useAnnuityString,
                                    $pipeString,
                                    $onclick,
                                    __(
                                        'Read more',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    )
                                );
                            }

                            $return = $displayAnnuity;
                        }
                    }
                } catch (Exception $annuityException) {
                    // In the multilingual demoshop there might be exceptions when the session is lost.
                    // Exceptions may also occur there, when the wrong payment method is checked and wrong language is chosen.
                    $displayAnnuity .= __(
                            'Annuity factors can not be displayed for the moment',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . ': ' . $annuityException->getMessage();
                    $return = $displayAnnuity;
                }
            }

            if (!is_ajax()) {
                return isset($return) ? $return : null;;
            }

            header('Content-Type: application/json;charset=UTF-8');
            echo json_encode([
                'html' => isset($return) ? $return : '',
            ]);

            die;
        }

        /**
         * Get address for a specific government ID
         *
         * @return void Prints the address data as JSON
         * @throws Exception
         */
        public static function get_address_ajax()
        {
            $results = [];
            if (isset($_REQUEST) && 'SE' == getResursOption('country')) {
                $customerType = isset($_REQUEST['customerType']) ? ($_REQUEST['customerType'] !== 'LEGAL' ? 'NATURAL' : 'LEGAL') : 'NATURAL';

                $serverEnv = getResursOption('serverEnv');
                /*
                 * Overriding settings here, if we want getAddress picked from production instead of test.
                 * The only requirement for this to work is that we are running in test and credentials for production is set.
                 */
                $userProd = getResursOption('ga_login');
                $passProd = getResursOption('ga_password');
                //$selectedCountry = getResursOption('country');
                $getAddressUseProduction = getResursOption('getAddressUseProduction');
                $disabledProdTests = true;      // TODO: Set this to false in future, when we're ready again (https://resursbankplugins.atlassian.net/browse/WOO-44)
                if ($getAddressUseProduction &&
                    isResursDemo() &&
                    $serverEnv === 'test' &&
                    !empty($userProd) &&
                    !empty($passProd) &&
                    !$disabledProdTests
                ) {
                    $results = getAddressProd($_REQUEST['ssn'], $customerType, self::get_ip_address());
                } else {
                    /** @var ResursBank */
                    $flow = initializeResursFlow();
                    try {
                        $results = $flow->getAddress($_REQUEST['ssn'], $customerType, self::get_ip_address());
                    } catch (Exception $e) {
                        $results = [
                            'error' => __(
                                'Can not get the address from current government ID',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                        ];
                    }
                }
            }
            header('Content-type: application/json; charset=utf-8');
            echo json_encode($results);
            die();
        }

        public static function get_priceinfo_ajax()
        {
            global $styles;
            require_once(__DIR__ . '/resursbankgateway.php');

            /* Styles Section */
            $styleSheets = '';
            $styles = [];
            $wooCommerceStyle = realpath(get_stylesheet_directory()) . '/css/woocommerce.css';
            if (file_exists($wooCommerceStyle)) {
                $styles[] = get_stylesheet_directory_uri() . '/css/woocommerce.css';
            }
            $costOfPurchaseCss = getResursOption('costOfPurchaseCss');
            if (!empty($costOfPurchaseCss)) {
                $styles[] = $costOfPurchaseCss;
            }
            $styles[] = plugin_dir_url(__FILE__) . 'css/resursinternal.css';

            /** @var $flow ResursBank */
            $flow = initializeResursFlow();
            $method = $_REQUEST['method'];
            $amount = (float)isset($_REQUEST['amount']) ? $_REQUEST['amount'] : 0;
            $costOfPriceInfoCountries = ['DK'];
            $selectedCountry = getResursOption('country');
            if (in_array($selectedCountry, $costOfPriceInfoCountries)) {
                $costOfPurchaseHtml = $flow->getCostOfPriceInformation($flow->getPaymentMethods(), $amount, true, true);
            } else {
                $costOfPurchaseHtml = $flow->getCostOfPriceInformation($method, $amount, false, true);
            }
            if (is_array($styles)) {
                foreach ($styles as $styleHttp) {
                    $styleSheets .= sprintf(
                        '<link rel="stylesheet" media="all" type="text/css" href="%s">',
                        $styleHttp
                    );
                }
            }

            $displayContent = sprintf(
                '
                    <a class="woocommerce button button-cancel" onclick="window.close()" href="javascript:void(0);">%s</a>
                    %s
                    ',
                __('Close', 'resurs-bank-payment-gateway-for-woocommerce'),
                $costOfPurchaseHtml
            );

            printf(
                '<html><head>
                    %s
                    </head><body>%s</body></html>',
                $styleSheets,
                $displayContent
            );
            die();
        }

        /**
         * Get information about selected payment method in checkout, to control the method listing
         */
        public static function get_address_customertype($return = false)
        {
            /** @var $flow ResursBank */
            $flow = initializeResursFlow();
            $methodsHasErrors = false;
            $methodsErrorMessage = null;
            $paymentMethods = null;

            try {
                // getAddress CustomerType resolving does not need to fetch realtime payment methods from Resurs Bank.
                // That may be a potential destroyer during checkout.
                $paymentMethods = unserialize(get_transient('resursTemporaryPaymentMethods'));
                // Only fetch methods if necessary!
                if (!is_array($paymentMethods) || (is_array($paymentMethods) && !count($paymentMethods))) {
                    rbSimpleLogging('Resurs Payment Methods is not set - re-fetch in progress.');
                    $methodList = $flow->getPaymentMethods([], true);
                    set_transient('resursTemporaryPaymentMethods', serialize($methodList));
                }
            } catch (Exception $e) {
                $methodsHasErrors = true;
                $methodsErrorMessage = $e->getMessage();
            }
            $requestedCustomerType = isset($_REQUEST['customerType']) ? $_REQUEST['customerType'] : 'NATURAL';
            rbSimpleLogging(
                sprintf(
                    '%s requested customer type: %s.',
                    __FUNCTION__,
                    $requestedCustomerType
                )
            );
            $responseArray = [
                'natural' => [],
                'legal' => [],
                'hasNatural' => false,
                'hasLegal' => false,
            ];

            if (is_array($paymentMethods)) {
                foreach ($paymentMethods as $objId) {
                    if (isset($objId->id) && isset($objId->customerType)) {
                        $nr = 'resurs_bank_nr_' . $objId->id;
                        if (is_array($objId->customerType)) {
                            foreach ($objId->customerType as $customerType) {
                                $lCaseType = strtolower($customerType);
                                $responseArray[sprintf('has%s', ucfirst($lCaseType))] = true;
                                $responseArray[$lCaseType][] = $nr;
                            }
                        } else {
                            $lCaseType = strtolower($objId->customerType);
                            $responseArray[sprintf('has%s', ucfirst($lCaseType))] = true;
                            $responseArray[$lCaseType][] = $nr;
                        }
                    }
                }
            }

            if ($methodsHasErrors) {
                $responseArray = [
                    'errorstring' => $methodsErrorMessage,
                ];
            }

            $responseArray = apply_filters('resurs_bank_js_payment_methods', $responseArray);

            if ($return) {
                return $responseArray;
            }

            header('Content-Type: application/json');
            print(json_encode($responseArray));
            die();
        }

        /**
         * Get the plugin url
         *
         * @return string
         */
        public function plugin_url()
        {
            return untrailingslashit(plugins_url('/', __FILE__));
        }

        /**
         * Get the plugin url
         *
         * @return string
         */
        public static function plugin_url_static()
        {
            return untrailingslashit(plugins_url('/', __FILE__));
        }

        /**
         * Prepare and set up custom order rows with Resurs Bank IF there is any discount on the
         * order. Making sure shipping are handled correctly as shipping goes separately into
         * WC orders.
         *
         * @param $order WC_Order
         * @param $resursFlow ResursBank
         * @param bool $isFullOrderHandle Set to true if this is a "handle full order instead of partial" order.
         * @return bool Returns true if this method indicates refunds with discount.
         */
        private static function getOrderRowsByRefundedDiscountItems($order, $resursFlow, $isFullOrderHandle = false)
        {
            $refundVatSettings = getResursStoredPaymentVatData($order->get_id());

            $return = false;
            $discountTotal = $order->get_discount_total();
            if ($discountTotal > 0) {
                $orderItems = $order->get_items();
                /** @var $item WC_Order_Item_Product */
                foreach ($orderItems as $item) {
                    $product = new WC_Product($item->get_product_id());
                    $orderItemQuantity = $item->get_quantity();
                    $refundedQuantity = $order->get_qty_refunded_for_item($item->get_id());
                    $rowsLeftToHandle = $orderItemQuantity + $refundedQuantity;
                    $itemQuantity = preg_replace('/^-/', '', $item->get_quantity());
                    $articleId = resurs_get_proper_article_number($product);
                    $amountPct = !is_nan(
                        @round($item->get_total_tax() / $item->get_total(), 2) * 100
                    ) ? @round($item->get_total_tax() / $item->get_total(), 2) * 100 : 0;

                    $itemTotal = preg_replace('/^-/', '', ($item->get_total() / $itemQuantity));
                    $itemTotalTax = preg_replace('/^-/', '', ($item->get_total_tax() / $itemQuantity));
                    $vatPct = 0;
                    $totalAmount = (float)$itemTotal + (float)$itemTotalTax;

                    if ($refundVatSettings['coupons_include_vat']) {
                        $vatPct = $amountPct;
                        $totalAmount = (float)$itemTotal;
                    }

                    if ($itemTotal > 0) {
                        $return = true;
                        $resursFlow->addOrderLine(
                            $articleId,
                            $product->get_title(),
                            $totalAmount,
                            $vatPct,
                            '',
                            'ORDER_LINE',
                            $rowsLeftToHandle
                        );
                    }
                }
            }

            /**
             * Test existing shipping lines in a dry run test. Order rows should only
             * be added into the order if the discount is missing and the process is not a full
             * cancellation of the order as shipping might troll orders.
             */
            $hasShippingTest = resurs_refund_shipping($order, $resursFlow, true);

            if ($hasShippingTest) {
                if (!$isFullOrderHandle) {
                    $return = resurs_refund_shipping($order, $resursFlow);
                } elseif ($return) {
                    resurs_refund_shipping($order, $resursFlow);
                }
            }

            return $return;
        }

        /**
         * @param $orderId
         * @param $wcThis
         * @return void
         */
        public static function order_status_completed($orderId, $wcThis)
        {
            $rbOrderId = wc_get_payment_id_by_order_id($orderId);
            if (!empty($rbOrderId)) {
                $canIgnoreFrozen = (bool)get_post_meta($orderId, 'ignore_frozen', true);

                try {
                    $ecomRequest = initializeResursFlow();
                    $findEcom = $ecomRequest->getPayment($rbOrderId);
                } catch (Exception $e) {
                    // If we land in this, something went wrong in the order check and we can probably
                    // skip the other controls.
                    return;
                }

                // Check if order is frozen and put it on hold if true.
                if (!$canIgnoreFrozen && $ecomRequest->isFrozen($findEcom)) {
                    $wcThis->set_status('on-hold');
                    $wcThis->save();
                    throw new Exception('Payment is in frozen state. Can not finalize!', 999);
                }
            }
        }

        /**
         * Called when the status of an order is changed
         *
         * @param int $order_id The order id
         * @param string $old_status_slug The old status
         * @param string $new_status_slug The new stauts
         * @return bool|void
         * @throws Exception
         */
        public static function order_status_changed($order_id, $old_status_slug, $new_status_slug)
        {
            global $woocommerce, $current_user;

            if (defined('RB_SYNCHRONOUS_MODE')) {
                return;
            }

            $order = new WC_Order($order_id);
            $payment_method = $order->get_payment_method();

            $payment_id = get_post_meta($order->get_id(), 'paymentId', true);
            if (!(bool)preg_match('/resurs_bank/', $payment_method)) {
                return;
            }

            /** @var $resursFlow ResursBank */
            $resursFlow = initializeResursFlow();
            $resursFlow->resetPayload();

            if (isset($_REQUEST['wc-api']) || isset($_REQUEST['cancel_order'])) {
                if (isset($_REQUEST['isBack'])) {
                    update_post_meta($order->get_id(), 'resursCancelUrl', 'backUrl/hosted');
                }
                if (isset($_REQUEST['isSimplified'])) {
                    update_post_meta($order->get_id(), 'resursCancelUrl', 'backUrl/simplified');
                }
                if (isset($_REQUEST['isSimplifiedFail'])) {
                    update_post_meta($order->get_id(), 'resursCancelUrl', 'failUrl/simplified');
                }
                return;
            }

            $url = admin_url('post.php');
            $url = add_query_arg('post', $order_id, $url);
            $url = add_query_arg('action', 'edit', $url);
            //$old_status = get_term_by('slug', sanitize_title($old_status_slug), 'shop_order_status');

            $flowErrorMessage = null;

            if ($payment_id) {
                try {
                    $payment = getPaymentInfo($order, $payment_id);
                    if (!isset($payment->status)) {
                        $payment->status = [];
                    }
                    if (isset($payment->id) && $payment_id !== $payment->id) {
                        // If something went wrong during the order processing at customer level
                        // we can still prevent wrong id's to be fixed at this point.
                        $payment_id = $payment->id;
                    }
                } catch (Exception $getPaymentException) {
                    return;
                }
                if (isset($payment, $payment->booked)) {
                    if (false === is_array($payment->status)) {
                        $status = [$payment->status];
                    } else {
                        $status = $payment->status;
                    }
                } else {
                    return;
                }
            } else {
                // No payment id, no Resurs handling
                return;
            }

            switch ($old_status_slug) {
                case 'pending':
                    break;
                case 'failed':
                    break;
                case 'processing':
                    break;
                case 'completed':
                    break;
                case 'on-hold':
                    break;
                case 'cancelled':
                    if (in_array('IS_ANNULLED', $status)) {
                        getResursRequireSession();
                        $_SESSION['resurs_bank_admin_notice'] = [
                            'type' => 'error',
                            'message' => __(
                                'This order is already annulled and cannot be changed.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                        ];

                        wp_set_object_terms($order_id, $old_status_slug, 'shop_order_status', false);
                        wp_safe_redirect($url);
                        exit;
                    }
                    break;
                case 'refunded':
                    if (in_array('IS_CREDITED', $status)) {
                        getResursRequireSession();
                        $_SESSION['resurs_bank_admin_notice'] = [
                            'type' => 'error',
                            'message' => __(
                                'This order is already credited and cannot be changed.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                        ];

                        wp_set_object_terms($order_id, $old_status_slug, 'shop_order_status', false);
                        wp_safe_redirect($url);
                        exit;
                    }
                    break;
                default:
                    break;
            }

            $throwStatusError = null;

            $currentRunningUser = getResursWordpressUser();
            $currentRunningUsername = getResursWordpressUser('user_login');
            $resursFlow->setLoggedInUser($currentRunningUsername);
            $returnValue = null;

            do_action('resurs_bank_order_status_update', $order->get_id(), $new_status_slug);

            switch ($new_status_slug) {
                case 'pending':
                    break;
                case 'failed':
                    break;
                case 'processing':
                    break;
                case 'completed':
                    $flowErrorMessage = '';

                    $canIgnoreFrozen = (bool)get_post_meta($order_id, 'ignore_frozen', true);
                    $isFrozen = $resursFlow->isFrozen($payment);

                    if ($resursFlow->canDebit($payment) || $isFrozen) {
                        try {
                            /**
                             * Full-Finalize orders with getPayment()-validation if status is
                             * a "first time handled" order.
                             *
                             * @link https://test.resurs.com/docs/display/ecom/paymentStatus
                             */
                            if (
                                !$resursFlow->canCredit($payment_id) &&
                                !$resursFlow->getIsDebited($payment_id) &&
                                !$resursFlow->getIsCredited($payment_id) &&
                                !$resursFlow->getIsAnnulled($payment_id)
                            ) {
                                // If order is only debitable and not creditable, then
                                // use the getPayment-validation instead of customizations.
                                $customFinalize = false;
                            } else {
                                $customFinalize = self::getOrderRowsByRefundedDiscountItems(
                                    $order,
                                    $resursFlow,
                                    true
                                );
                            }
                            rbSimpleLogging(
                                sprintf('%s: Finalization - Payment Content', $payment_id)
                            );
                            // Checking frozen status here, instead so that we can use the prior natural flow
                            // for bad finalizations.
                            if (!$isFrozen) {
                                $successFinalize = $resursFlow->paymentFinalize(
                                    $payment_id,
                                    null,
                                    false,
                                    $customFinalize
                                );
                            } else {
                                if (!$canIgnoreFrozen) {
                                    QueueHandler::setOrderStatusWithNotice($order->get_id(), $payment_id);
                                    throw new Exception('Payment is in frozen state. Can not finalize!', 999);
                                }
                                $emergencyString = sprintf(
                                    'Emergency Mode: Order status for %s is set to be allowed to be ' .
                                    'changed, via ignore_frozen meta data, despite the frozen state.',
                                    $payment_id
                                );
                                rbSimpleLogging($emergencyString);
                                $order->add_order_note($emergencyString);
                                break;
                            }
                            rbSimpleLogging(print_r($payment, true));
                            rbSimpleLogging(
                                sprintf(
                                    '%s: Finalization %s',
                                    $payment_id,
                                    ($successFinalize ? 'OK' : 'NOT OK')
                                )
                            );
                        } catch (Exception $e) {
                            // Checking code 29 is not necessary since this is automated in EComPHP
                            $flowErrorMessage = sprintf(
                                __(
                                    '[Error %s] Finalization Failure: %s.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $e->getCode(),
                                $e->getMessage()
                            );

                            rbSimpleLogging(
                                sprintf(
                                    __(
                                        '%s: FinalizationException: %s - %s. Old status (%s) restored.',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $payment_id,
                                    $e->getCode(),
                                    $e->getMessage(),
                                    $old_status_slug
                                )
                            );
                        }
                    } else {
                        // Generate a notice if the order has been debited from for example payment admin.
                        // This notice requires that an order is not debitable (if it is, there's more to debit anyway,
                        // so in that case the above finalization event will occur)
                        if ($resursFlow->getIsDebited()) {
                            if ($resursFlow->getInstantFinalizationStatus($payment) & (OrderStatus::AUTO_DEBITED)) {
                                rbSimpleLogging($payment_id . ': InstantFinalization/IsDebited detected.');
                                $order->add_order_note(
                                    __(
                                        'This order is now marked completed as a result of the payment method behaviour (automatic finalization).',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    )
                                );
                            } else {
                                rbSimpleLogging($payment_id . ': Already finalized.');
                                $order->add_order_note(
                                    __(
                                        'This order has already been finalized externally.',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    )
                                );
                            }
                        } else {
                            $alreadyFinalized = false;
                            if ($resursFlow->getInstantFinalizationStatus($payment) & (OrderStatus::AUTO_DEBITED)) {
                                $alreadyFinalized = true;
                                $orderNote = __(
                                    'The payment method for this order indicates that the payment has been automatically finalized.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                );
                            } else {
                                resursEventLogger(
                                    sprintf(
                                        '%s: Can not finalize due to the current remote order status.',
                                        $payment_id
                                    )
                                );
                            }
                            if (!$alreadyFinalized && !empty($orderNote)) {
                                $order->add_order_note($orderNote);
                                $flowErrorMessage = $orderNote;
                            }
                        }
                    }
                    if (!empty($flowErrorMessage)) {
                        getResursRequireSession();
                        $_SESSION['resurs_bank_admin_notice'] = [
                            'type' => 'error',
                            'message' => $flowErrorMessage,
                        ];
                        $order->update_status(
                            $old_status_slug,
                            __(
                                '[Resurs Bank] Reset to prior status.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                        throw new Exception($flowErrorMessage);
                    }
                    wp_safe_redirect($url);
                    break;
                case 'on-hold':
                    break;
                case 'cancelled':
                    if (!empty($currentRunningUser) &&
                        (
                            $resursFlow->canCredit($payment_id) ||
                            $resursFlow->canAnnul($payment_id)
                        )
                    ) {
                        try {
                            $resursFlow->resetPayload();

                            $customCancel = self::getOrderRowsByRefundedDiscountItems(
                                $order,
                                $resursFlow,
                                true
                            );
                            if ($customCancel) {
                                $resursFlow->setGetPaymentMatchKeys(['artNo', 'description', 'unitMeasure']);
                            }
                            $resursFlow->paymentCancel($payment_id, null, $customCancel);
                            $order->add_order_note(
                                __(
                                    'Cancelled status set: Resurs Bank API was called for cancellation.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                        } catch (Exception $e) {
                            $flowErrorMessage = sprintf(
                                __(
                                    '[Error %s] Cancellation Failure: %s.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $e->getCode(),
                                $e->getMessage()
                            );
                        }
                    } else {
                        $logstr = sprintf(
                            'Annul/Credit Criterias Check: ' .
                            'currentRunningUser (%s), canAnnul: %s, canCredit: %s, isAnnulled: %s.',
                            $currentRunningUser,
                            $resursFlow->canAnnul($payment) ? 'true' : 'false',
                            $resursFlow->canCredit($payment) ? 'true' : 'false',
                            $resursFlow->getIsAnnulled($payment) ? 'true' : 'false'
                        );
                        rbSimpleLogging($logstr);
                        //$order->add_order_note($logstr);
                        if (!$resursFlow->getIsAnnulled($payment)) {
                            $flowErrorMessage = setResursNoAutoCancellation($order);
                        }
                    }
                    if (null !== $flowErrorMessage) {
                        getResursRequireSession();
                        $_SESSION['resurs_bank_admin_notice'] = [
                            'type' => 'error',
                            'message' => $flowErrorMessage,
                        ];
                        //wp_set_object_terms($order_id, $old_status_slug, 'shop_order_status', false);
                        $order->update_status($old_status_slug);
                        throw new Exception($flowErrorMessage);
                    }
                    wp_safe_redirect($url);
                    break;
                case 'refunded':
                    if (!empty($currentRunningUser) &&
                        (
                            $resursFlow->canCredit($payment) ||
                            $resursFlow->canAnnul($payment)
                        )
                    ) {
                        try {
                            $customCancel = self::getOrderRowsByRefundedDiscountItems(
                                $order,
                                $resursFlow,
                                true
                            );
                            if ($customCancel) {
                                $resursFlow->setGetPaymentMatchKeys(
                                    [
                                        'artNo',
                                        'description',
                                        'unitMeasure',
                                    ]
                                );
                            }
                            $returnValue = $resursFlow->paymentCancel($payment_id, null, $customCancel);
                            $order->add_order_note(
                                __(
                                    'Refunded status set: Resurs Bank API was called for cancellation.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                        } catch (Exception $e) {
                            $flowErrorMessage = sprintf(
                                __(
                                    '[Error %s] Refund Failure: %s.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $e->getCode(),
                                $e->getMessage()
                            );
                        }
                    } else {
                        $logstr = sprintf(
                            'Annul/Credit Criterias Check: ' .
                            'currentRunningUser (%s), canAnnul: %s, canCredit: %s, isAnnulled: %s.',
                            $currentRunningUser,
                            $resursFlow->canAnnul($payment) ? 'true' : 'false',
                            $resursFlow->canCredit($payment) ? 'true' : 'false',
                            $resursFlow->getIsAnnulled($payment) ? 'true' : 'false'
                        );
                        rbSimpleLogging($logstr);
                        //$order->add_order_note($logstr);
                        if (!$resursFlow->getIsAnnulled($payment)) {
                            $flowErrorMessage = setResursNoAutoCancellation($order);
                        }
                    }
                    if (null !== $flowErrorMessage) {
                        getResursRequireSession();
                        $_SESSION['resurs_bank_admin_notice'] = [
                            'type' => 'error',
                            'message' => $flowErrorMessage,
                        ];
                        //wp_set_object_terms($order_id, $old_status_slug, 'shop_order_status', false);
                        $order->update_status($old_status_slug);
                        throw new Exception($flowErrorMessage);
                    }
                    if (!is_ajax()) {
                        wp_safe_redirect($url);
                    }
                    break;
                default:
                    break;
            }

            if (null !== $returnValue) {
                return $returnValue;
            }
        }
        // Class ends here
    }

    /**
     * Adds the SSN field to the checkout form for fetching a address
     *
     * @param WC_Checkout $checkout The WooCommerce checkout object
     *
     * @return WC_Checkout           The WooCommerce checkout object
     */
    function add_ssn_checkout_field($checkout)
    {
        if (!getResursOption('enabled')) {
            return $checkout;
        }

        if (!apply_filters('resurs_getaddress_enabled', true)) {
            return $checkout;
        }

        $selectedCountry = getResursOption('country');
        $optionGetAddress = getResursOption('getAddress');
        $private = __('Private', 'resurs-bank-payment-gateway-for-woocommerce');
        $company = __('Company', 'resurs-bank-payment-gateway-for-woocommerce');
        if (!isResursOmni()) {
            // Here we use the translated or not translated values for Private and Company radiobuttons
            $resursTemporaryPaymentMethodsTime = get_transient('resursTemporaryPaymentMethodsTime');
            $timeDiff = apply_filters('resurs_methodlist_timediff', time() - $resursTemporaryPaymentMethodsTime);

            $checkoutErrorString = '';
            $errorOnLiveData = false;
            if ($timeDiff >= 3600) {
                /** @var $theFlow ResursBank */
                $theFlow = initializeResursFlow();
                try {
                    $methodList = $theFlow->getPaymentMethods([], true);
                    set_transient('resursTemporaryPaymentMethodsTime', time(), 3600);
                    set_transient('resursTemporaryPaymentMethods', serialize($methodList), 3600);
                } catch (Exception $e) {
                    // Can't save transients if this is down. So try to refetch this list.
                    $methodList = unserialize(get_transient('resursTemporaryPaymentMethods'));
                    $errorOnLiveData = true;
                }
            } else {
                $methodList = unserialize(get_transient('resursTemporaryPaymentMethods'));
            }
            $naturalCount = 0;
            $legalCount = 0;
            if (is_array($methodList)) {
                foreach ($methodList as $method) {
                    $customerType = $method->customerType;
                    if (is_array($customerType)) {
                        if (in_array('NATURAL', $customerType)) {
                            $naturalCount++;
                        }
                        if (in_array('LEGAL', $customerType)) {
                            $legalCount++;
                        }
                    } else {
                        if ($customerType === 'NATURAL') {
                            $naturalCount++;
                        }
                        if ($customerType === 'LEGAL') {
                            $legalCount++;
                        }
                    }
                }
            }

            $viewNatural = 'display:;';
            $viewLegal = 'display:;';
            if ($naturalCount > 0 && !$legalCount) {
                $viewNatural = 'display: none;';
            }
            if (!$naturalCount && $legalCount) {
                $viewLegal = 'display: none;';
            }

            if ($errorOnLiveData) {
                if ($theFlow->hasTimeoutException()) {
                    set_transient('resurs_connection_timeout', time(), 60);
                    $checkoutErrorString = __(
                        'Resurs Bank connection timeout!',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                } else {
                    $checkoutErrorString = __(
                        'Resurs Bank has connection errors!',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }

                echo sprintf(
                    '<div style="border: 1px solid #990000; text-align: center; padding: 3px; font-weight: bold; color: #990000;">%s</div>',
                    $checkoutErrorString
                );
            }

            // On both company+natural methods, enforce displaying of fields.
            // But we have to check if they ARE in duality before deciding the rest.
            if (hasDualCustomerTypes() && $optionGetAddress) {
                if ($naturalCount) {
                    echo '<span id="ssnCustomerRadioNATURAL" style="' . $viewNatural . '"><input type="radio" id="ssnCustomerTypeNATURAL" onclick="getMethodType(\'natural\')" checked="checked" name="ssnCustomerType" value="NATURAL"> ' . $private . '</span> ';
                }
                if ($legalCount) {
                    echo '<span id="ssnCustomerRadioLEGAL" style="' . $viewLegal . '"><input type="radio" id="ssnCustomerTypeLEGAL" onclick="getMethodType(\'legal\')" name="ssnCustomerType" value="LEGAL"> ' . $company . '</span>';
                }
            } elseif (hasDualCustomerTypes()) {
                // If optionGetAddress is disabled but we still have more than one customerType, the buttons
                // still need to be shown.
                if ($naturalCount) {
                    echo '<span id="ssnCustomerRadioNATURAL" style="' . $viewNatural . '"><input type="radio" id="ssnCustomerTypeNATURAL" onclick="getMethodType(\'natural\')" checked="checked" name="ssnCustomerType" value="NATURAL"> ' . $private . '</span> ';
                }
                if ($legalCount) {
                    echo '<span id="ssnCustomerRadioLEGAL" style="' . $viewLegal . '"><input type="radio" id="ssnCustomerTypeLEGAL" onclick="getMethodType(\'legal\')" name="ssnCustomerType" value="LEGAL"> ' . $company . '</span>';
                }
            }

            echo '<input type="hidden" id="resursSelectedCountry" value="' . $selectedCountry . '">';
            $placeHolderField = __(
                'Enter your government id (social security number)',
                'resurs-bank-payment-gateway-for-woocommerce'
            );
            $govIdLabel = __('Government ID', 'resurs-bank-payment-gateway-for-woocommerce');
            if (!$naturalCount) {
                $placeHolderField = __(
                    'Enter your company government ID',
                    'resurs-bank-payment-gateway-for-woocommerce'
                );
                $govIdLabel = __('Company government id', 'resurs-bank-payment-gateway-for-woocommerce');
            }
            // Only show govid-fields if enabled.
            if ($optionGetAddress) {
                woocommerce_form_field('ssn_field', [
                    'type' => 'text',
                    'class' => ['ssn form-row-wide resurs_ssn_field'],
                    'label' => $govIdLabel,
                    'placeholder' => $placeHolderField,
                ], $checkout->get_value('ssn_field'));
                if ('SE' === $selectedCountry) {
                    $translation = [];

                    $get_address = (!empty($translation)) ? $translation['get_address'] : __(
                        'Get address',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                    printf(
                        '<a href="#" class="button" id="fetch_address">%s</a>
                                <span id="fetch_address_status" style="display: none;"><img src="%sloader.gif" border="0"></span><br>
                                ',
                        $get_address,
                        plugin_dir_url(__FILE__)
                    );
                }
            }
        }

        return $checkout;
    }

    /**
     * Adds Resurs Bank javascript file
     *
     * @return null Returns null if Resurs Bank plugin is not enabled
     * @throws Exception
     */
    function enqueue_script()
    {
        global $woocommerce;

        if (!getResursOption('enabled')) {
            return;
        }
        $OmniVars = [];
        if (isResursOmni()) {
            $omniRefAge = null;
            wp_enqueue_script(
                'resursomni',
                plugin_dir_url(__FILE__) . 'js/omnicheckout.js',
                [],
                RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') &&
                RB_ALWAYS_RELOAD_JS === true ? '-' . time() : '')
            );
            $omniBookUrl = home_url('/');
            $omniBookUrl = add_query_arg('wc-api', 'WC_Resurs_Bank', $omniBookUrl);
            $omniBookUrl = add_query_arg('event-type', 'prepare-omni-order', $omniBookUrl);
            $omniBookUrl = add_query_arg('set-no-session', '1', $omniBookUrl);
            $omniBookNonce = wp_nonce_url($omniBookUrl, 'omnicheckout', 'omnicheckout_nonce');

            /** @var $flow Resursbank\RBEcomPHP\ResursBank */
            $flow = initializeResursFlow();
            $sEnv = getServerEnv();
            $OmniUrl = $flow->getCheckoutUrl($sEnv);
            //apply_filters('resurs_get_omni_url', $OmniUrl);
            $debugOmniUrl = getResursFlag('OMNIURL');
            if (!empty($debugOmniUrl)) {
                $OmniUrl = $debugOmniUrl;
            }

            $isWooSession = false;
            $setSession = null;
            if (isset(WC()->session)) {
                $isWooSession = true;
            }
            if ($isWooSession) {
                $omniRef = WC()->session->get('omniRef');
                $omniRefCreated = WC()->session->get('omniRefCreated');
                $omniRefAge = (int)WC()->session->get('omniRefAge');
            }

            $gateways = WC()->payment_gateways()->get_available_payment_gateways();

            $OmniVars = [
                'RESURSCHECKOUT_IFRAME_URL' => $OmniUrl,
                'ACCEPT_CHECKOUT_PREFIXES' => getResursFlag('ACCEPT_CHECKOUT_PREFIXES'),
                'RESURSCHECKOUT' => home_url(),
                'OmniPreBookUrl' => $omniBookNonce,
                'OmniRef' => isset($omniRef) && !empty($omniRef) ? $omniRef : null,
                'OmniRefCreated' => isset($omniRefCreated) && !empty($omniRefCreated) ? $omniRefCreated : null,
                'OmniRefAge' => $omniRefAge,
                'isResursTest' => isResursTest(),
                'iframeShape' => getResursOption(
                    'iframeShape',
                    'woocommerce_resurs_bank_omnicheckout_settings'
                ),
                'disableStandardFieldsForShipping' => getResursOption(
                    'disableStandardFieldsForShipping',
                    'woocommerce_resurs_bank_omnicheckout_settings'
                ),
                'showResursCheckoutStandardFieldsTest' => getResursOption(
                    'showResursCheckoutStandardFieldsTest'
                ),
                'gatewayCount' => (is_array($gateways) ? count($gateways) : 0),
                'postidreference' => getResursOption('postidreference'),
            ];
            $setSession = isset($_REQUEST['set-no-session']) ? $_REQUEST['set-no-session'] : null;
            if ((int)$setSession === 1) {
                $setSessionEnable = false;
            } else {
                $setSessionEnable = true;
            }

            // During the creation of new RCO variables, make sure they are not duplicates from older orders.
            if ($setSessionEnable && function_exists('WC') && $isWooSession) {
                // According to WOO-11 there may still be traces of an old session when
                // a payment isn't properly fulfilled. The inProcess below is intended
                // to discover such sessions and clean them up. It is especially important
                // to get rid of variables like omniRef. If this isn't handled properly
                // this also affects (for some reason) incognito mode.
                $currentOmniRef = WC()->session->get('omniRef');
                $inProcess = (bool)WC()->session->get('OMNICHECKOUT_PROCESSPAYMENT');
                // The resursCreatePass variable is only set when everything was successful.
                $resursCreatePass = WC()->session->get('resursCreatePass');
                $orderControl = wc_get_order_id_by_payment_id($currentOmniRef);
                if (!empty($orderControl) && !empty($currentOmniRef)) {
                    $checkOrder = new WC_Order($orderControl);
                    // currentOrderStatus checks what status the order had when created
                    $currentOrderStatus = $checkOrder->get_status();
                    $preventCleanup = [
                        'pending',
                        'failed',
                    ];
                    $allowCleanupSession = false;
                    if (!in_array($currentOrderStatus, $preventCleanup)) {
                        $allowCleanupSession = true;
                    }
                    if (($resursCreatePass && $currentOmniRef) || ($allowCleanupSession)) {
                        if ($inProcess) {
                            WC()->session->set('OMNICHECKOUT_PROCESSPAYMENT', false);
                            if ($checkOrder !== null) {
                                setResursOrderMetaData(
                                    $checkOrder->get_id(),
                                    'RcoProcessPaymentStart',
                                    time()
                                );
                            }
                            $refreshUrl = wc_get_cart_url();
                            $thisSession = new WC_Session_Handler();
                            $thisSession->destroy_session();
                            wp_safe_redirect($refreshUrl);
                        }
                    }
                }
            }
        }

        $resursLanguageLocalization = [
            'getAddressEnterGovernmentId' => __(
                'Enter social security number',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'getAddressEnterCompany' => __(
                'Enter corporate government identity',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'labelGovernmentId' => __(
                'Government id',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'labelCompanyId' => __(
                'Corporate government id',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
        ];

        $generalJsTranslations = [
            'deliveryRequiresSigning' => __(
                'Changing delivery address requires signing',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'ssnElementMissing' => __(
                'I can not show errors since the element is missing',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'purchaseAjaxInternalFailure' => __(
                'The purchase has failed, due to an internal server error: The shop could not properly update the order.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'purchaseAjaxInternalDenied' => __(
                'The purchase was denied: The shop could not properly update the order. Please contact support for more information.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'updatePaymentReferenceFailure' => __(
                'The purchase was processed, but the payment reference failed to update',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'resursPurchaseNotAccepted' => __(
                'The purchase was rejected by Resurs Bank. Please contact customer services, or try again with another payment method.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'theAjaxWasNotAccepted' => __(
                'Something went wrong when we tried to book your order. Please contact customer support for more information.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'theAjaxWentWrong' => __(
                'An internal error occured while trying to book the order. Please contact customer support for more information.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'theAjaxWentWrongWithThisMessage' => __(
                'An internal error occurred while trying to book the order:',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'contactSupport' => __(
                'Please contact customer support for more information.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
        ];

        $customerTypes = WC_Resurs_Bank::get_address_customertype(true);

        $wcCustomerCountry = isset($woocommerce->customer) &&
        method_exists($woocommerce->customer, 'get_billing_country') ?
            $woocommerce->customer->get_billing_country() : '';

        if ($wcCustomerCountry === 'undefined' || $wcCustomerCountry === '') {
            $wcCustomerCountry = getResursOption('country');
        }

        // resursCountry and wcCustomerCountry is added here for debugging purposes.
        $resursVars = [
            'ResursBankAB' => true,
            'customerTypes' => $customerTypes,
            'resursSpinnerLocal' => plugin_dir_url(__FILE__) . 'spinnerLocal.gif',
            'resursCheckoutMultipleMethods' => omniOption('resursCheckoutMultipleMethods'),
            'showCheckoutOverlay' => getResursOption('showCheckoutOverlay'),
            'inProductPage' => is_product(),
            'resursCountry' => getResursOption('country'),
            'forceGovIdField' => (bool)getResursOption('forceGovIdField') ? 1 : 0,
            'wcCustomerCountry' => $wcCustomerCountry,
        ];

        $oneRandomValue = null;
        $randomizeJsLoaders = getResursOption('randomizeJsLoaders');
        if ($randomizeJsLoaders) {
            $oneRandomValue = '?randomizeMe=' . rand(1024, 65535);
        }
        $ajaxObject = ['ajax_url' => admin_url('admin-ajax.php')];
        wp_enqueue_style(
            'resursInternal',
            plugin_dir_url(__FILE__) . 'css/resursinternal.css',
            [],
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? '-' . time() : '')
        );
        wp_enqueue_script(
            'resursbankmain',
            plugin_dir_url(__FILE__) . 'js/resursbank.js' . $oneRandomValue,
            ['jquery'],
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? '-' . time() : '')
        );

        if (isResursOmni(true)) {
            // Legacy RCOJS is depending on rcoface, due to auto detection.
            wp_enqueue_script(
                'rcojs',
                plugin_dir_url(__FILE__) . 'js/rcojs.js' . $oneRandomValue,
                ['jquery', 'rcoface'],
                RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? '-' . time() : '')
            );
        }
        wp_localize_script('resursbankmain', 'rb_getaddress_fields', $resursLanguageLocalization);
        wp_localize_script('resursbankmain', 'rb_general_translations', $generalJsTranslations);
        wp_localize_script('resursbankmain', 'ajax_object', $ajaxObject);
        wp_localize_script('resursbankmain', 'omnivars', $OmniVars);
        wp_localize_script('resursbankmain', 'resursvars', $resursVars);
    }

    /**
     * Adds Javascript to the Resurs Bank Payment Gateway settings panel
     *
     * @param string $hook The current page
     *
     * @return null Returns null current page is not correct
     */
    function admin_enqueue_script($hook)
    {
        /** @var WP_Post $post */
        global $post;

        $images = plugin_dir_url(__FILE__) . 'img/';
        $resursLogo = $images . 'resurs-standard.png';

        wp_enqueue_style(
            'resursInternal',
            plugin_dir_url(__FILE__) . 'css/resursinternal.css',
            [],
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? '-' . time() : '')
        );
        wp_enqueue_script(
            'resursBankAdminScript',
            plugin_dir_url(__FILE__) . 'js/resursbankadmin.js',
            [],
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? '-' . time() : '')
        );

        $requestForCallbacks = callbackUpdateRequest();

        $callbackUriCacheTime = get_transient('resurs_callback_templates_cache_last');
        $lastFetchedCacheTime = $callbackUriCacheTime > 0 ? date('Y-m-d, H:i', $callbackUriCacheTime) : '';
        $resursMethod = false;
        $resursPayment = '';

        // post_type = shop_order
        if ($post instanceof WP_Post && $post->post_type === 'shop_order' &&
            ($methodInfoMeta = getResursPaymentMethodMeta(isset($post->ID) ? $post->ID : 0))
        ) {
            $resursMeta = getResursPaymentMethodMeta($post->ID, 'resursBankMetaPaymentMethodType');
            if (!empty($resursMeta)) {
                $resursMethod = true;
                $resursPayment = wc_get_payment_id_by_order_id($post->ID);
            } else {
                $username = getResursOption('login');
                // No username configured?
                if (!empty($username) || !getResursOption('enabled')) {
                    $flow = initializeResursFlow();
                    $methodInfo = $flow->getPaymentMethodSpecific($methodInfoMeta);
                    $resursMeta = isset($methodInfo->type) ? $methodInfo->type : '';
                    $resursMetaSpecific = isset($methodInfo->specificType) ? $methodInfo->specificType : '';
                    setResursOrderMetaData($post->ID, 'resursBankMetaPaymentMethodType', $resursMeta);
                    setResursOrderMetaData($post->ID, 'resursBankMetaPaymentMethodSpecificType', $resursMetaSpecific);
                }
            }
        }

        $adminJs = [
            'resursSpinner' => plugin_dir_url(__FILE__) . 'loader.gif',
            'resursSpinnerLocal' => plugin_dir_url(__FILE__) . 'loaderLocal.gif',
            'resursFeePen' => plugin_dir_url(__FILE__) . 'img/pen16x.png',
            'callbackUrisCache' => __(
                'The list of urls below is cached from an earlier response from Resurs Bank',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'callbackUrisCacheTime' => $lastFetchedCacheTime,
            'callbacks_registered' => __(
                'callbacks has been registered',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'update_callbacks' => __('Update callbacks again', 'resurs-bank-payment-gateway-for-woocommerce'),
            'update_test' => __('Trigger test callback', 'resurs-bank-payment-gateway-for-woocommerce'),
            'useZeroToReset' => __(
                'To remove the fee properly, set the value to 0',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'notAllowedValue' => __(
                'The entered value is not allowed here',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'requestForCallbacks' => $requestForCallbacks,
            'noCallbacksSet' => __(
                'No registered callbacks could be found',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'annulCantBeAlone' => __(
                'This setting requires waitForFraudControl to be active',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'couldNotSetNewFee' => __('Unable to set new fee', 'resurs-bank-payment-gateway-for-woocommerce'),
            'newFeeHasBeenSet' => __('Fee has been updated', 'resurs-bank-payment-gateway-for-woocommerce'),
            'callbacks_pending' => __('Waiting for callback', 'resurs-bank-payment-gateway-for-woocommerce'),
            'callbacks_not_received' => __('Callback not yet received', 'resurs-bank-payment-gateway-for-woocommerce'),
            'callbacks_slow' => __(
                'It seems that your site has not received any callbacks yet. Either your site are unreachable, or the callback tester is for the moment slow.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
            'resursBankTabLogo' => $resursLogo,
            'resursMethod' => $resursMethod,
            'resursPaymentId' => $resursPayment,
            'methodDoesNotSupportRefunding' => __(
                'Resurs Bank does not support partial annulling for this payment method!',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
        ];

        $addAdminJs = apply_filters('resursAdminJs', null);
        if (is_array($addAdminJs)) {
            foreach ($addAdminJs as $key => $adminJsValue) {
                if (!empty($key) && !isset($adminJs[$key]) && !empty($adminJsValue)) {
                    $adminJs[$key] = $adminJsValue;
                }
            }
        }

        wp_localize_script('resursBankAdminScript', 'adminJs', $adminJs);
        $configUrl = home_url('/');
        $configUrl = add_query_arg('event-type', 'noevent', $configUrl);
        $configUrl = add_query_arg('wc-api', 'WC_Resurs_Bank', $configUrl);
        $adminAjax = [
            'ran' => wp_nonce_url($configUrl, 'requestResursAdmin', 'ran'),
        ];
        wp_localize_script('resursBankAdminScript', 'rbAjaxSetup', $adminAjax);

        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            if (!isset($_SESSION['resurs_bank_admin_notice'])) {
                $_SESSION['resurs_bank_admin_notice'] = [];
            }
            $_SESSION['resurs_bank_admin_notice']['message'] = __(
                'The Resurs Bank Addon for WooCommerce may not work properly in PHP 5.3 or older. You should consider upgrading to 5.4 or higher.',
                'resurs-bank-payment-gateway-for-woocommerce'
            );
            $_SESSION['resurs_bank_admin_notice']['type'] = 'resurswoo_phpversion_deprecated';
        }

        if (!isset($_REQUEST['section'])) {
            return;
        }
        if ('wc_resurs_bank' !== $_REQUEST['section']) {
            return;
        }
    }

    /**
     * Start session on Wordpress init
     */
    function start_session()
    {
        /** @var bool $do_not_start_session Disable internal handling of session. */
        $do_not_start_session = (bool)apply_filters('resursbank_start_session_before', null);

        /** @var bool $session_outside_admin Disable session creation when in admin if true. */
        $session_outside_admin = (bool)apply_filters('resursbank_start_session_outside_admin_only', null);

        if (!$do_not_start_session) {
            if ($session_outside_admin) {
                if (!is_admin() && session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
            } elseif (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
    }

    /**
     * End session on Wordpress login and logout
     */
    function end_session()
    {
        /** @var bool $do_not_start_session Disable internal handling of session. */
        $do_not_start_session = (bool)apply_filters('resursbank_start_session_before', null);

        /** @var bool $session_outside_admin Disable session creation when in admin if true. */
        $session_outside_admin = (bool)apply_filters('resursbank_start_session_outside_admin_only', null);

        if (!$do_not_start_session) {
            if ($session_outside_admin) {
                if (!is_admin() && session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
            } else {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
            }
        }
    }

    /**
     * Used to enable wp_safe_redirect in certain situations
     * @todo Is this really doing something?
     */
    function app_output_buffer()
    {
        if (isset($_REQUEST['woocommerce_resurs-bank_refreshPaymentMethods']) || isset($_REQUEST['second_update_status']) || isset($_REQUEST['save']) || isset($_SESSION)) {
            ob_start();
        }
    }

    function test_before_shipping()
    {
    }

    $pluginPaymentMethods = plugin_dir_path(__FILE__) . '/' . getResursPaymentMethodModelPath() . '*.php';
    // If glob returns null (error) nothing should run
    $incGlob = glob($pluginPaymentMethods);

    if (is_array($incGlob) && !count($incGlob)) {
        $transientRecord = get_transient('resursTemporaryPaymentMethods');

        if (!empty($transientRecord)) {
            $username = getResursOption('login');
            $password = getResursOption('password');

            /**
             * On errors in this method, suppress the E_NOTICE an continue.
             * This should never occur unless someone entered invalid data manually.
             * @see https://www.php.net/manual/en/function.unserialize.php
             */
            $methodList = @unserialize($transientRecord);

            // Make sure there are credentials available before running this.
            // If site is not yet configured, we want to prevent the site from not loading properly.
            // We also want to securely check if there are any pre-set payment method list.
            if (!empty($username) &&
                !empty($password) &&
                isset($methodList) &&
                is_array($methodList) &&
                count($methodList)
            ) {
                // Call writing script before the loader in case there are lost payment methods.
                $writeMethodResult = rewriteMethodsOnFly();
                if (!$writeMethodResult) {
                    add_filter('woocommerce_no_available_payment_methods_message', 'resursHasNoMethods', 999, 1);
                }
            }
        }
    }

    if (is_array($incGlob) && count($incGlob)) {
        foreach ($incGlob as $filename) {
            if (!in_array($filename, get_included_files())) {
                include $filename;
            }
        }
    }

    // We do the globbing by a static file to make it more safe.
    $staticGlob = glob(plugin_dir_path(__FILE__) . '/staticflows/*omni.php');
    if (is_array($staticGlob)) {
        foreach ($staticGlob as $filename) {
            if (!in_array($filename, get_included_files())) {
                include $filename;
            }
        }
    }

    function rb_settings_pages($settings)
    {
        $settings[] = include(plugin_dir_path(__FILE__) . '/resursbank_settings.php');

        return $settings;
    }


    /* Payment gateway stuff */

    /**
     * Add the Gateway to WooCommerce
     *
     * @param array $methods The available payment methods
     * @return array          The available payment methods
     */
    function woocommerce_add_resurs_bank_gateway($methods)
    {
        // If the filter is active in parts of admin, we should warn that the below part is disabled.
        // Remember: current_user_can('administrator')
        $simplifiedEnabled = apply_filters('resurs_bank_checkout_methods_enabled', true);

        if (!$simplifiedEnabled) {
            foreach ($methods as $id => $m) {
                if (is_string($m) && preg_match('/^resurs_bank_/i', $m)) {
                    unset($methods[$id]);
                }
            }

            return $methods;
        }

        $methods[] = 'WC_Resurs_Bank';
        if (is_admin() && is_array($methods)) {
            foreach ($methods as $id => $m) {
                if (is_string($m) && preg_match('/^resurs_bank_/i', $m)) {
                    unset($methods[$id]);
                }
            }
            $methods = array_values($methods);
        }

        return $methods;
    }

    /**
     * Remove the gateway from the available payment options at checkout
     *
     * @param array $gateways The array of payment gateways
     * @return array           The array of payment gateways
     */
    function woocommerce_resurs_bank_available_payment_gateways($gateways)
    {
        unset($gateways['resurs-bank']);
        global $woocommerce;

        $selectedCountry = getResursOption('country');
        $customerCountry = isset($woocommerce->customer) &&
        method_exists($woocommerce->customer, 'get_billing_country') ?
            $woocommerce->customer->get_billing_country() : '';

        if (empty($customerCountry)) {
            $customerCountry = getResursOption('country');
            /*rbSimpleLogging(
                sprintf('Customer country not set, using %s.', $customerCountry)
            );*/
        }/* else {
            rbSimpleLogging(
                sprintf(
                    'Customer country used for current customer: %s.',
                    $customerCountry
                )
            );
        }*/

        // Do not distribute payment methods for countries that do not belong to current
        // Resurs setup, with an exception for VISA/Mastercard.
        if (strtolower($customerCountry) !== strtolower($selectedCountry)) {
            foreach ($gateways as $gatewayName => $gatewayClass) {
                if (preg_match('/^resurs_bank_nr/i', $gatewayName)) {
                    $type = isset($gatewayClass->type) ? $gatewayClass->type : '';
                    $specificType = isset($gatewayClass->specificType) ? $gatewayClass->specificType : '';

                    if (strtoupper($type) === 'PAYMENT_PROVIDER' && preg_match('/card/i', $specificType)) {
                        continue;
                    }
                    unset($gateways[$gatewayName]);
                }
            }
        }

        return $gateways;
    }

    /**
     * @param $columns
     *
     * @return array
     */
    function resurs_order_column_header($columns)
    {
        $new_columns = [];
        $hasColumnOnce = false;
        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;
            if (!$hasColumnOnce && ($column_name == 'order_number' || $column_name == 'order_title')) {
                if (getResursOption('showPaymentIdInOrderList')) {
                    $new_columns['resurs_order_id'] = __(
                        'Resurs Reference',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }
                $new_columns['resurs_payment_method'] = __(
                    'Resurs Method',
                    'resurs-bank-payment-gateway-for-woocommerce'
                );
                $hasColumnOnce = true;
            }
        }

        return $new_columns;
    }


    /**
     * @param $column
     */
    function resurs_order_column_info($column)
    {
        global $post;

        $postMeta = get_post_meta($post->ID);
        // Overrides the omniPaymentMethod that is only there for backward compatibility
        $newMethodInfo = getResursPaymentMethodMeta($post->ID);
        if (isset($postMeta['_payment_method'])) {
            $metaMethod = $postMeta['_payment_method'];
            if (is_array($postMeta)) {
                $metaMethod = array_pop($postMeta['_payment_method']);
            }
            if (!preg_match('/^resurs_bank_/', $metaMethod)) {
                return;
            }
        }

        if ($column === 'resurs_order_id') {
            $resursId = wc_get_payment_id_by_order_id($post->ID);
            echo $resursId;
        }
        if ($column === 'resurs_payment_method') {
            $omniMethod = get_post_meta($post->ID, 'omniPaymentMethod');
            if (!empty($newMethodInfo)) {
                echo $newMethodInfo;
                return;
            }

            if (is_array($omniMethod) && isset($omniMethod[0])) {
                echo $omniMethod[0];
                return;
            }
        }
    }


    /**
     * @since A while.
     */
    function resurs_annuity_factors()
    {
        global $product;

        /** @var $product WC_Product_Simple */
        $annuityMethod = trim(getResursOption('resursAnnuityMethod'));
        if (is_object($product) && !empty($annuityMethod)) {
            $annuityFactorPrice = wc_get_price_to_display($product);

            echo WC_Resurs_Bank::get_annuity_html($annuityFactorPrice);
        }
    }

    /**
     * Check if payment method on supplied order stems from Resurs Bank.
     *
     * @param WC_Order $order
     * @return bool
     */
    function isResursBankOrder(WC_Order $order)
    {
        return (bool)preg_match(
            '/resurs_bank/',
            (string)$order->get_payment_method()
        );
    }

    /**
     * This function allows partial refunding based on amount rather than article numbers.
     *
     * Written experimental for the future - eventually - since the logics allows a lot more than we have time to fix right now.
     * For example, in this function we also need to figure out how much that is actually left to annul or credit before sending the actions.
     * If we try to credit more than is authorized or credit a part of the payment that is already annulled, the credit will fail.
     *
     * NOTE: If you ever consider to rewrite this plugin, do it properly. Do not use this method.
     *
     * @param $orderId
     * @param int $refundId
     * @return bool
     * @throws Exception
     */
    function resurs_order_refund($orderId, $refundId)
    {
        $refundObject = new WC_Order_Refund($refundId);
        $order = new WC_Order($orderId);
        if (!isResursBankOrder($order)) {
            return false;
        }
        $refundVatSettings = getResursStoredPaymentVatData($order->get_id());

        $refundStatus = false;
        $resursOrderId = wc_get_payment_id_by_order_id($orderId);

        /** @var WC_Order_Item_Product $refundItems */
        $refundItems = $refundObject->get_items();

        /** @var $refundFlow ResursBank */
        $refundFlow = initializeResursFlow();

        if (!$refundFlow->canAnnul($resursOrderId) &&
            !$refundFlow->canCredit($resursOrderId)
        ) {
            return true;
        }

        $refundFlow->resetPayload();
        $refundFlow->setPreferredPaymentFlowService(CheckoutType::SIMPLIFIED_FLOW);

        $matchGetPaymentKeys = (array)apply_filters('resurs_match_getpayment_keys', []);
        if (is_array($matchGetPaymentKeys) && count($matchGetPaymentKeys)) {
            //$refundFlow->setGetPaymentMatchKeys(['artNo', 'description', 'unitMeasure']);
            $refundFlow->setGetPaymentMatchKeys($matchGetPaymentKeys);
        }
        $refundPriceAlwaysOverride = (bool)apply_filters('resurs_refund_price_override', false);
        $totalDiscount = $order->get_total_discount();

        // Refund discount indicator. If above 0, the below actions should honor discount.
        $refundDiscount = $refundObject->get_discount_total();

        if (is_array($refundItems) && count($refundItems)) {
            /** @var WC_Order_Item_Product $item */
            foreach ($refundItems as $item) {
                // Calculate the default tax out of the current values.
                $amountPct = !is_nan(
                    @round($item->get_total_tax() / $item->get_total(), 2) * 100
                ) ? @round($item->get_total_tax() / $item->get_total(), 2) * 100 : 0;

                /** @var WC_Product $product */
                $product = $item->get_product();

                // Positive decimal.
                $itemQuantity = preg_replace('/^-/', '', $item->get_quantity());
                $articleId = resurs_get_proper_article_number($product);
                $itemTotal = preg_replace('/^-/', '', ($item->get_total() / $itemQuantity));
                $itemTotalTax = preg_replace('/^-/', '', ($item->get_total_tax() / $itemQuantity));

                if ($refundDiscount) {
                    $realAmount = $itemTotal + $itemTotalTax;
                    $vatPct = 0;
                    if ($refundVatSettings['coupons_include_vat']) {
                        $vatPct = $amountPct;
                        $realAmount = (float)$itemTotal;
                    }
                } else {
                    $realAmount = (float)$itemTotal;
                    $vatPct = $amountPct;
                }

                // Regenerate the cancellation orderline with positive decimals.
                $refundFlow->addOrderLine(
                    $articleId,
                    $product->get_title(),
                    $realAmount,
                    $vatPct,
                    '',
                    'ORDER_LINE',
                    $itemQuantity
                );
            }
        }

        $errors = false;
        $errorString = null;
        $errorCode = null;
        $hasShippingRefund = resurs_refund_shipping($refundObject, $refundFlow);

        try {
            if ((float)$totalDiscount > 0) {
                $refundFlow->setGetPaymentMatchKeys(['artNo', 'description', 'unitMeasure']);
            }

            // Refund "normally" when there is no discount.
            // Go for woocommerce settings when discounts are added as the sums has to be manipulated.
            $refundStatus = $refundFlow->paymentCancel(
                $resursOrderId,
                null,
                (float)$totalDiscount > 0 || $hasShippingRefund || $refundPriceAlwaysOverride ? true : false
            );
        } catch (Exception $e) {
            $errors = true;
            $errorCode = $e->getCode();
            $errorString = $e->getMessage();
        }

        if (!$errors) {
            $order->add_order_note(
                sprintf(
                    __(
                        '[Resurs Bank] Refund/cancellation sent to API successfully.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )
                )
            );
        }
        return $refundStatus;
    }


    /**
     * @param bool $isEditable
     * @param $that WC_Admin_Order
     *
     * @return bool
     */
    function resurs_order_is_editable($isEditable, $that)
    {
        $resursOrderId = wc_get_payment_id_by_order_id($that->get_id());

        try {
            canResursRefund($resursOrderId);
        } catch (Exception $e) {
            if ($e->getCode() === 1234) {
                // Only return false at this point if this is a special payment.
                // Some payment methods does not allow refunding and this is stated here only.
                return false;
            }
        }

        // Go the normal way if this option is disabled.
        if (!getResursOption('resursOrdersEditable')) {
            return $isEditable;
        }

        if (!empty($resursOrderId)) {
            return true;
        }

        return $isEditable;
    }

    // Refund is not the same as woocommerce_before_delete_order_item.
    // woocommerce_before_delete_order_item has been used earlier to remove articles from Resurs Bank
    // but seems to be unavailable in newer versions (at least from where we usually did it).
    // That's why the refunding action has been disabled - it was'nt necessary at the time.
    add_action('woocommerce_order_refunded', 'resurs_order_refund', 10, 2);
    add_action('woocommerce_before_delete_order_item', 'resurs_remove_order_item');
    add_filter('wc_order_is_editable', 'resurs_order_is_editable', 10, 2);
    add_action('woocommerce_after_checkout_form', 'resurs_after_checkout_form');
    //add_filter('wc_get_price_decimals', 'resurs_order_price_decimals');

    add_filter('woocommerce_get_settings_pages', 'rb_settings_pages');
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_gateway');
    add_filter('woocommerce_available_payment_gateways', 'woocommerce_resurs_bank_available_payment_gateways');
    add_filter('woocommerce_before_checkout_billing_form', 'add_ssn_checkout_field');
    add_action('woocommerce_order_status_changed', 'WC_Resurs_Bank::order_status_changed', 10, 3);
    add_action('woocommerce_order_status_completed', 'WC_Resurs_Bank::order_status_completed', 10, 3);
    add_action('wp_enqueue_scripts', 'enqueue_script', 0);
    add_action('admin_enqueue_scripts', 'admin_enqueue_script');
    add_action('wp_ajax_get_address_ajax', 'WC_Resurs_Bank::get_address_ajax');
    add_action('wp_ajax_nopriv_get_address_ajax', 'WC_Resurs_Bank::get_address_ajax');
    add_action('wp_ajax_get_annuity_html', 'WC_Resurs_Bank::get_annuity_html');
    add_action('wp_ajax_nopriv_get_annuity_html', 'WC_Resurs_Bank::get_annuity_html');
    add_action('wp_ajax_get_priceinfo_ajax', 'WC_Resurs_Bank::get_priceinfo_ajax');
    add_action('wp_ajax_nopriv_get_priceinfo_ajax', 'WC_Resurs_Bank::get_priceinfo_ajax');
    add_action('wp_ajax_get_cost_ajax', 'WC_Resurs_Bank::get_priceinfo_ajax'); // Keeping compatibility.
    add_action('wp_ajax_nopriv_get_cost_ajax', 'WC_Resurs_Bank::get_priceinfo_ajax'); // Keeping compatibility.
    add_action('wp_ajax_get_address_customertype', 'WC_Resurs_Bank::get_address_customertype');
    add_action('wp_ajax_nopriv_get_address_customertype', 'WC_Resurs_Bank::get_address_customertype');
    add_action('init', 'start_session', 1);
    add_action('wp_logout', 'end_session');
    add_action('wp_login', 'end_session');
    add_action('init', 'app_output_buffer', 2);
    if (function_exists('resurs_bank_admin_notice')) {
        add_action('admin_notices', 'resurs_bank_admin_notice');
    }

    add_action('woocommerce_before_checkout_shipping_form', 'test_before_shipping');
    add_action('woocommerce_admin_order_data_after_order_details', 'resurs_order_data_info_after_order');
    add_action('woocommerce_admin_order_data_after_billing_address', 'resurs_order_data_info_after_billing');
    add_action('woocommerce_admin_order_data_after_shipping_address', 'resurs_order_data_info_after_shipping');
    add_filter('woocommerce_order_button_html', 'resurs_omnicheckout_order_button_html'); // Omni
    add_filter('woocommerce_no_available_payment_methods_message', 'resurs_omnicheckout_payment_gateways_check');
    add_action('woocommerce_single_product_summary', 'resurs_annuity_factors');

    add_filter('manage_edit-shop_order_columns', 'resurs_order_column_header');
    add_action('manage_shop_order_posts_custom_column', 'resurs_order_column_info');

    add_filter('plugin_action_links', 'plugin_page_resurs_bank_for_woocommerce_settings', 10, 2);
    add_filter('is_protected_meta', 'resurs_protected_meta_data', 10, 3);
}

function resurs_after_checkout_form()
{
    if (getResursOption('showCheckoutOverlay')) {
        $customOverlayMessage = getResursOption('checkoutOverlayMessage');
        $overlayMessage = empty($customOverlayMessage) ?
            __('Please wait while we process your order...', 'resurs-bank-payment-gateway-for-woocommerce') :
            $customOverlayMessage;

        echo '<div class="purchaseActionsWrapper" id="purchaseActionsWrapper" style="display: none !important; text-align: center; align-content: center; background-color: #FFFFFF !important; padding: 5px;">' .
            '<div style="text-align: center; vertical-align: middle; font-weight:bold; background-color:#FFFFFF; border: 1px solid white;">'
            . $overlayMessage .
            '</div></div>';
        echo '<div id="purchaseActions" class="purchaseActions" style="display: none !important;"></div>';
    }
}

/**
 * @param $links
 * @param $file
 * @return array
 */
function plugin_page_resurs_bank_for_woocommerce_settings($links, $file)
{
    $basename = trim(plugin_basename(__FILE__));
    if ($basename == $file || $file === 'resurs-bank-payment-gateway-for-woocommerce/resursbankgateway.php') {
        $links[] = sprintf(
            '<a href="admin.php?page=wc-settings&tab=tab_resursbank">%s</a>',
            __(
                'Settings'
            )
        );
    }
    return $links;
}

/**
 * @param null $order
 * @throws Exception
 */
function resurs_order_data_info_after_order($order = null)
{
    resurs_order_data_info($order, 'AO');
}

/**
 * @param null $order
 * @throws Exception
 */
function resurs_order_data_info_after_billing($order = null)
{
    resurs_order_data_info($order, 'AB');
}

/**
 * @param null $order
 * @throws Exception
 */
function resurs_order_data_info_after_shipping($order = null)
{
    resurs_order_data_info($order, 'AS');
}

/**
 * @param null $instant
 */
function resurs_no_debit_debited($instant = null)
{
    if (!(bool)$instant) {
        $message = __(
            'It seems this order has already been finalized from an external system - if your order is finished you may update it here aswell',
            'resurs-bank-payment-gateway-for-woocommerce'
        );
    } else {
        $message = __(
            'It seems this order has been instantly finalized due to the payment method type. This means that you probably must handle it manually.',
            'resurs-bank-payment-gateway-for-woocommerce'
        );
    } ?>
    <div class="notice notice-error">
        <p><?php echo $message; ?></p>
    </div>
    <?php
}

/**
 * Fetch payment information for the order view.
 *
 * @param $order
 * @param string $getPaymentId
 * @param false $fallback
 * @return array|int|mixed|stdClass|null
 * @throws ResursException
 * @throws ExceptionHandler
 */
function getPaymentInfo($order, $getPaymentId = '', $fallback = false)
{
    $resursPaymentIdLast = get_post_meta($order->get_id(), 'paymentIdLast', true);

    $rb = initializeResursFlow();
    $checkTimeouts = (int)get_transient('resurs_connection_timeout');
    if ($checkTimeouts) {
        $rb->setFlag('GET_PAYMENT_BY_REST');
        // Extend connection timeout timer for payment information.
        set_transient('resurs_connection_timeout', time(), 600);
    }
    $resursPaymentInfo = null;
    try {
        $resursPaymentInfo = $rb->getPayment($getPaymentId);
    } catch (Exception $e) {
        if ($rb->hasTimeoutException()) {
            // On timeouts, just skip the failovers.
            set_transient('resurs_connection_timeout', time(), 60);
        }
        if (resursOption('postidreference')) {
            if ($e->getCode() === 8) {
                if (!empty($resursPaymentIdLast) && $getPaymentId !== $resursPaymentIdLast) {
                    $resursPaymentInfo = getPaymentInfo($order, $resursPaymentIdLast, $fallback);
                    $fallback = true;
                } else {
                    if (!$fallback) {
                        // When the paymentIdLast is not properly registered, we'll get an empty string here.
                        // In this case, something gone terribly wrong, so we have to fallback to an originating id.
                        // This probably occurs when the "terribly wrong" part is about the updatePaymentReference, where
                        // this actually has been successful but not properly been registered.
                        $fallback = true;
                        $resursPaymentInfo = getPaymentInfo($order, $order->get_id(), $fallback);
                    } else {
                        throw new Exception('Order was not found at Resurs Bank', 8);
                    }
                }
            } else {
                throw $e;
            }
        } else {
            // Do not make a second lookup if postidreferences are disabled and just throw.
            throw $e;
        }
    }
    if (is_object($resursPaymentInfo)) {
        $resursPaymentInfo->fallback = $fallback;
    }

    return $resursPaymentInfo;
}

/**
 * Hook into WooCommerce OrderAdmin fetch payment data from Resurs Bank.
 * This hook are tested from WooCommerce 2.1.5 up to WooCommerce 2.5.2
 * @param WC_Order $order
 * @param null $orderDataInfoAfter
 *
 * @throws Exception
 */
function resurs_order_data_info($order = null, $orderDataInfoAfter = null)
{
    global $orderInfoShown;
    $resursPaymentInfo = null;
    $showOrderInfoAfterOption = getResursOption('showOrderInfoAfter', 'woocommerce_resurs-bank_settings');
    $showOrderInfoAfter = !empty($showOrderInfoAfterOption) ? $showOrderInfoAfterOption : 'AO';
    if ($showOrderInfoAfter != $orderDataInfoAfter) {
        return;
    }
    if ($orderInfoShown) {
        return;
    }

    $orderInfoShown = true;
    $renderedResursData = '';
    $orderId = null;
    $resursPaymentId = get_post_meta($order->get_id(), 'paymentId', true);
    $resursMethodId = get_post_meta($order->get_id(), 'resursBankMetaPaymentMethod', true);
    $orderId = $order->get_id();
    $hasNoResursOrder = false;
    $isResursMethod = $order->get_payment_method();
    if (0 !== strpos($isResursMethod, "resurs_bank_")) {
        unset($resursPaymentId);
    }
    if (!empty($resursPaymentId)) {
        $hasError = '';
        try {
            /** @var $rb ResursBank */
            $rb = initializeResursFlow();
            try {
                $rb->setFlag('GET_PAYMENT_BY_SOAP');
                $resursPaymentInfo = getPaymentInfo($order, $resursPaymentId);
                //$customerUpdated = updateResursOrderBillingData($order, $resursPaymentInfo);
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                if ($e->getCode() === 8) {
                    // REFERENCED_DATA_DONT_EXISTS
                    $errorMessage = __(
                            'Referenced data don\'t exist',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) .
                        "<br>\n<br>\n";
                    $errorMessage .= __(
                        'This error might occur when for example a payment doesn\'t exist at Resurs Bank. Normally this happens when payments have failed or aborted before it can be completed',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }

                $checkoutPurchaseFailTest = get_post_meta($orderId, 'soft_purchase_fail', true);
                $checkoutRcoPurchaseFailTest = get_post_meta($orderId, 'rcoOrderFailed', true);
                $resursCancelUrlUsage = get_post_meta($orderId, 'resursCancelUrl', true);

                if ($checkoutPurchaseFailTest == '1') {
                    $errorMessage = __(
                        'The order was denied at Resurs Bank and therefore has not been created',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }
                if ($checkoutRcoPurchaseFailTest === '1') {
                    $errorMessage = __(
                        'This order failed or was cancelled by customer during external actions.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }
                if (!empty($resursCancelUrlUsage)) {
                    $errorMessage = sprintf(
                        __(
                            'This order has been cancelled during customer interactions. Returning URL was set to %s.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $resursCancelUrlUsage
                    );
                }

                echo '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                    <div style="padding: 30px;border:none;" id="resursInfo">
                        <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url(__FILE__) . '/img/logo.png' . '"></span>
                        <fieldset>
                        <b>' .
                    __(
                        'Following error ocurred when we tried to fetch information about the payment',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</b><br>
                        <br>
                        ' . $errorMessage . '<br>
                    </fieldset>
                    </div>
			    ';

                echo '</div>';

                $hasNoResursOrder = true;
            }

            if (!$hasNoResursOrder) {
                $currentWcStatus = $order->get_status();
                $notIn = ['completed', 'cancelled', 'refunded'];
                if (!$rb->canDebit($resursPaymentInfo) &&
                    $rb->getIsDebited($resursPaymentInfo) &&
                    !in_array($currentWcStatus, $notIn)
                ) {
                    if ($rb->getInstantFinalizationStatus($resursPaymentInfo) & (OrderStatus::AUTO_DEBITED)) {
                        resurs_no_debit_debited(true);
                    } else {
                        resurs_no_debit_debited();
                    }
                }
            }
        } catch (Exception $e) {
            $hasError = $e->getMessage();
        }
        $renderedResursData .= '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                <div class="resurs-read-more-box">
                <div style="padding: 30px;border:none;" id="resursInfo">
                ';

        if (isset($resursPaymentInfo->fallback) && (bool)$resursPaymentInfo->fallback) {
            $resursPaymentIdLast = get_post_meta($order->get_id(), 'paymentIdLast', true);

            if (empty($resursPaymentIdLast)) {
                $resursPaymentIdLast = sprintf(__(
                    'Incomplete. The fallback tried to use %d.',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ), $order->get_id());
            }
        }

        $unsetKeys = [];
        if (empty($hasError)) {
            $fail = null;
            try {
                // We no longer use WooCommerce paymentdiffs to decide what's happened to the order as - for example - a
                // partially debited and annulled order may give a falsely annulled status in the end. Instead,
                // we ask EComPHP for the most proper, current, status.
                $currentOrderStatus = ucfirst($rb->getOrderStatusStringByReturnCode($rb->getOrderStatusByPayment($resursPaymentInfo)));
            } catch (\Exception $e) {
                $currentOrderStatus = '<i>' . $e->getMessage() . '</i>';
            }

            if (empty($currentOrderStatus)) {
                $currentOrderStatus = __('Not set', 'resurs-bank-payment-gateway-for-woocommerce');
                if ($rb->isFrozen($resursPaymentInfo)) {
                    $currentOrderStatus = __('Frozen', 'resurs-bank-payment-gateway-for-woocommerce');
                }
            }

            if (isset($resursPaymentInfo->paymentMethodId)) {
                $methodInfoMeta = getResursPaymentMethodMeta($orderId);
                if (empty($methodInfoMeta)) {
                    setResursPaymentMethodMeta($orderId, $resursPaymentInfo->paymentMethodId);
                }
                $methodInfoType = getResursPaymentMethodMeta($orderId, 'resursBankMetaPaymentMethodType');
                if (empty($methodInfoType)) {
                    $flow = initializeResursFlow();
                    $methodInfo = $flow->getPaymentMethodSpecific($methodInfoMeta);
                    setResursOrderMetaData($orderId, 'resursBankMetaPaymentMethodType', $methodInfo->type);
                    setResursOrderMetaData(
                        $orderId,
                        'resursBankMetaPaymentMethodSpecificType',
                        $methodInfo->specificType
                    );
                }
            }

            if (!$hasNoResursOrder) {
                $renderedResursData .= '<div class="resurs_orderinfo_text paymentInfoWrapStatus paymentInfoHead">';
                $renderedResursData .= sprintf(
                    __(
                        'Status from Resurs Bank: %s.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $currentOrderStatus
                );
                $renderedResursData .= '</div>
                     <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url(__FILE__) . '/img/logo.png' . '"></span>
                ';

                $addressInfo = '';
                $deliveryInfo = '';
                if (!empty($resursPaymentInfo) && is_object($resursPaymentInfo->customer->address)) {
                    $addressInfo .= isset($resursPaymentInfo->customer->address->addressRow1) && !empty($resursPaymentInfo->customer->address->addressRow1) ? $resursPaymentInfo->customer->address->addressRow1 . "\n" : '';
                    $addressInfo .= isset($resursPaymentInfo->customer->address->addressRow2) && !empty($resursPaymentInfo->customer->address->addressRow2) ? $resursPaymentInfo->customer->address->addressRow2 . "\n" : '';
                    $addressInfo .= isset($resursPaymentInfo->customer->address->postalArea) && !empty($resursPaymentInfo->customer->address->postalArea) ? $resursPaymentInfo->customer->address->postalArea . "\n" : '';
                    $addressInfo .= (isset($resursPaymentInfo->customer->address->country) && !empty($resursPaymentInfo->customer->address->country) ? $resursPaymentInfo->customer->address->country : '') . ' ' . (isset($resursPaymentInfo->customer->address->postalCode) && !empty($resursPaymentInfo->customer->address->postalCode) ? $resursPaymentInfo->customer->address->postalCode : '') . "\n";
                }
                if (!empty($resursPaymentInfo) && isset($resursPaymentInfo->deliveryAddress) && is_object($resursPaymentInfo->deliveryAddress)) {
                    $deliveryInfo .= isset($resursPaymentInfo->deliveryAddress->addressRow1) && !empty($resursPaymentInfo->deliveryAddress->addressRow1) ? $resursPaymentInfo->deliveryAddress->addressRow1 . "\n" : '';
                    $deliveryInfo .= isset($resursPaymentInfo->deliveryAddress->addressRow2) && !empty($resursPaymentInfo->deliveryAddress->addressRow2) ? $resursPaymentInfo->deliveryAddress->addressRow2 . "\n" : '';
                    $deliveryInfo .= isset($resursPaymentInfo->deliveryAddress->postalArea) && !empty($resursPaymentInfo->deliveryAddress->postalArea) ? $resursPaymentInfo->deliveryAddress->postalArea . "\n" : '';
                    $deliveryInfo .= (isset($resursPaymentInfo->deliveryAddress->country) && !empty($resursPaymentInfo->deliveryAddress->country) ? $resursPaymentInfo->deliveryAddress->country : '') . ' ' . (isset($resursPaymentInfo->deliveryAddress->postalCode) && !empty($resursPaymentInfo->deliveryAddress->postalCode) ? $resursPaymentInfo->deliveryAddress->postalCode : '') . "\n";
                } else {
                    // No delivery set? Use billing.
                    $deliveryInfo = $addressInfo;
                }

                ThirdPartyHooksSetPaymentTrigger('orderinfo', $resursPaymentId, $order->get_id());

                $unsetKeys = [
                    'id',
                    'paymentMethodId',
                    'storeId',
                    'paymentMethodName',
                    'paymentMethodType',
                    'totalAmount',
                    'limit',
                    'fraud',
                    'frozen',
                    'customer',
                    'deliveryAddress',
                    'paymentDiffs',
                ];

                $renderedResursData .= '
                <br>
                <fieldset>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Payment ID', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (isset($resursPaymentInfo->id) && !empty($resursPaymentInfo->id) ? $resursPaymentInfo->id : '') .
                    '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Payment method ID', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->paymentMethodId) &&
                    !empty($resursPaymentInfo->paymentMethodId) ? $resursPaymentInfo->paymentMethodId : ''
                    ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Store ID', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->storeId) &&
                    !empty($resursPaymentInfo->storeId) ? $resursPaymentInfo->storeId : ''
                    ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Payment method name', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->paymentMethodName) &&
                    !empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodName : ''
                    ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Payment method type', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->paymentMethodType) &&
                    !empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodType : ''
                    ) .
                    '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Payment amount', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->totalAmount) &&
                    !empty($resursPaymentInfo->totalAmount) ? round(
                        $resursPaymentInfo->totalAmount,
                        2
                    ) : ''
                    ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Payment limit', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->limit) &&
                    !empty($resursPaymentInfo->limit) ? round($resursPaymentInfo->limit, 2) : ''
                    ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Fraud', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->fraud) &&
                    !empty($resursPaymentInfo->fraud) ?
                        $resursPaymentInfo->fraud ? __('Yes') : __('No') : __('No')
                    ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Frozen', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (
                    isset($resursPaymentInfo->frozen) &&
                    !empty($resursPaymentInfo->frozen) ?
                        $resursPaymentInfo->frozen ? __('Yes') : __('No') : __('No')
                    ) . '</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __(
                        'Customer name',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (is_object($resursPaymentInfo->customer->address) &&
                    !empty($resursPaymentInfo->customer->address->fullName) ?
                        $resursPaymentInfo->customer->address->fullName : ''
                    ) . '</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Billing address', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (!empty($addressInfo) ? nl2br($addressInfo) : '') . '</span>
                    </span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                    __('Delivery address', 'resurs-bank-payment-gateway-for-woocommerce') .
                    ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                    (!empty($deliveryInfo) ? nl2br($deliveryInfo) : '') . '</span>
                    ';
            }

            $continueView = $resursPaymentInfo;
            $showMeta = getResursProtectedMetaData();

            if (is_array($showMeta)) {
                if ($hasNoResursOrder) {
                    $continueView = new stdClass();
                }
                foreach ($showMeta as $metaKey => $metaValueDescription) {
                    $setValue = getResursPaymentMethodMeta($order->get_id(), $metaKey);
                    if (empty($setValue)) {
                        continue;
                    }
                    if ((strncmp($metaKey, 'hasCallback', 11) === 0) && is_numeric($setValue)) {
                        $setValue = date('Y-m-d H:i:s', $setValue);
                    }
                    $continueView->$metaValueDescription = $setValue;
                }
            }

            foreach ($continueView as $key => $value) {
                if (in_array($key, $unsetKeys) && isset($continueView->$key)) {
                    unset($continueView->$key);
                }
            }
            if (is_object($continueView)) {
                if ($hasNoResursOrder) {
                    $renderedResursData .= '
                            <span style="font-size: 20px !important;" class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">WooCommerce stored metadata</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value"></span>
                        ';
                }

                $dateTimes = [
                    'cached',
                ];

                foreach ($continueView as $key => $value) {
                    // ECom data cache.
                    $handleAsTime = false;
                    if (in_array($key, $dateTimes, true) ||
                        (is_numeric($value) && strlen($value) >= 10)
                    ) {
                        $handleAsTime = true;
                    }
                    if ($handleAsTime) {
                        $value .= ' (' . date('Y-m-d H:i:s', $value) . ')';
                    }
                    if (!is_array($value) && !is_object($value)) {
                        $renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . ':</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (!empty($value) ? nl2br($value) : '') . '</span>
                        ';
                    } else {
                        if ($key == 'metaData') {
                            if (is_array($value)) {
                                foreach ($value as $metaArray) {
                                    $renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($metaArray->key) . ':</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . $metaArray->value . '</span>
                                    ';
                                }
                            } else {
                                $renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($value->key) . ':</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . $value->value . '</span>
                                ';
                            }
                        } else {
                            foreach ($value as $subKey => $subValue) {
                                $renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . ' (' . ucfirst($subKey) . '):</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (!empty($subValue) ? nl2br($subValue) : '') . '</span>
                                ';
                            }
                        }
                    }
                }
            }
        }
        $renderedResursData .= '</fieldset>
                <p class="resurs-read-more" id="resursInfoButton"><a href="#" class="button">' . __(
                'Read more',
                'resurs-bank-payment-gateway-for-woocommerce'
            ) . '</a></p>
                </div>
                </div>
                </div>
            ';
    }
    //}
    echo $renderedResursData;
}

/**
 * @param WC_Order $order
 * @param $resursPaymentRequest
 * @return bool
 */
function updateResursOrderBillingData($order, $resursPaymentRequest)
{
    $return = false;
    $orderId = $order->get_id();
    try {
        $flow = initializeResursFlow();
        $resursPayment = $flow->getPayment($resursPaymentRequest);
    } catch (Exception $e) {
    }

    if (!empty($resursPayment)) {
        // Enable second resynch if anything fails during payment process.
        /*
        $paymentMethod = $order->get_payment_method();
        if ($paymentMethod === 'resurs_bank_omnicheckout') {
            $order->set_payment_method('resurs_bank_nr_' . $resursPayment->paymentMethodId);
        }
        */
        $billingAddress = $order->get_address('billing');
        if ($orderId && isset($resursPayment->customer->address)) {
            $addressTranslation = [
                'first_name' => 'firstName',
                'last_name' => 'lastName',
                'address_1' => 'addressRow1',
                'address_2' => 'addressRow2',
                'city' => 'postalArea',
                'postcode' => 'postalCode',
                'country' => 'country',
            ];
            foreach ($addressTranslation as $item => $value) {
                if (isset($billingAddress[$item]) &&
                    isset($resursPayment->customer->address->{$value}) &&
                    $billingAddress[$item] !== $resursPayment->customer->address->{$value}
                ) {
                    update_post_meta(
                        $orderId,
                        sprintf('_billing_%s', $item),
                        $resursPayment->customer->address->{$value}
                    );
                    $return = true;
                }
            }
        }
    }
    return $return;
}

/**
 * Convert version number to decimals
 *
 * @return string
 */
function rbWcGwVersionToDecimals()
{
    $splitVersion = explode('.', RB_WOO_VERSION);
    $decVersion = '';
    foreach ($splitVersion as $ver) {
        $decVersion .= str_pad(intval($ver), 2, '0', STR_PAD_LEFT);
    }

    return $decVersion;
}

/**
 * @return string
 */
function rbWcGwVersion()
{
    return RB_WOO_VERSION;
}

/**
 * Allows partial hooks from this plugin
 *
 * @param string $type
 * @param string $content
 */
function ThirdPartyHooks($type = '', $content = '', $addonData = [])
{
    $type = strtolower($type);
    $allowedHooks = ['orderinfo', 'callback'];
    $paymentInfoHooks = ['orderinfo', 'callback'];
    // Start with an empty content array
    $sendHookContent = [];

    // Put on any extra that the hook wishes to add
    if (is_array($addonData) && count($addonData)) {
        foreach ($addonData as $addonKey => $addonValue) {
            $sendHookContent[$addonKey] = $addonValue;
        }
    }

    // If the hook is basedon sending payment data info ...
    if (in_array(strtolower($type), $paymentInfoHooks)) {
        // ... then prepare the necessary data without revealing the full getPayment()-object.
        // This is for making data available for any payment bridging needed for external systems to synchronize payment statuses if needed.
        $sendHookContent['id'] = isset($content->id) ? $content->id : '';
        $sendHookContent['fraud'] = isset($content->fraud) ? $content->fraud : '';
        $sendHookContent['frozen'] = isset($content->frozen) ? $content->frozen : '';
        $sendHookContent['status'] = isset($content->status) ? $content->status : '';
        $sendHookContent['booked'] = isset($content->booked) ? strtotime($content->booked) : '';
        $sendHookContent['cached'] = isset($content->cached) ? $content->cached : '';
        $sendHookContent['finalized'] = isset($content->finalized) ? strtotime($content->finalized) : '';
        $sendHookContent['iscallback'] = isset($content->iscallback) ? $content->iscallback : '';
    }
    if (in_array(strtolower($type), $allowedHooks)) {
        do_action('resurs_hook_' . $type, $sendHookContent);
    }
}

/**
 * Hooks that should initiate payment controlling, may be runned through the same function - making sure that we only
 * call for that hook if everything went nicely.
 *
 * @param string $type
 * @param string $paymentId
 * @param null $internalOrderId
 * @param null $callbackType
 *
 * @throws Exception
 */
function ThirdPartyHooksSetPaymentTrigger($type = '', $paymentId = '', $internalOrderId = null, $callbackType = null)
{
    /** @var $flow ResursBank */
    $flow = initializeResursFlow();
    $paymentDataIn = [];
    try {
        $paymentDataIn = $flow->getPayment($paymentId);
        if ($type == 'callback' && !is_null($callbackType)) {
            $paymentDataIn->iscallback = $callbackType;
        } else {
            $paymentDataIn->iscallback = null;
        }
        if (!is_null($internalOrderId)) {
            $paymentDataIn->internalOrderId = $internalOrderId;
        }
        if (is_object($paymentDataIn)) {
            return ThirdPartyHooks($type, $paymentDataIn);
        }
    } catch (Exception $e) {
    }
}


/**
 * Unconditional OrderRowRemover for Resurs Bank. This function will run before the primary remove_order_item() in the
 * WooCommerce-plugin. This function won't remove any product on the woocommerce-side, it will however update the
 * payment at Resurs Bank. If removal at Resurs fails by any reason, this method will stop the removal from WooAdmin,
 * so we won't destroy any synch.
 *
 * @param $item_id
 *
 * @return bool
 * @throws Exception
 */
function resurs_remove_order_item($item_id)
{
    if (!$item_id) {
        return false;
    }

    // Make sure we still keep the former security
    if (!current_user_can('edit_shop_orders')) {
        die(-1);
    }

    /** @var $resursFlow ResursBank */
    $resursFlow = null;
    if (hasEcomPHP()) {
        $resursFlow = initializeResursFlow();
        $resursFlow->resetPayload();
    }
    $clientPaymentSpec = [];
    if (null !== $resursFlow) {
        $productId = wc_get_order_item_meta($item_id, '_product_id');
        $productQty = wc_get_order_item_meta($item_id, '_qty');
        $orderId = r_wc_get_order_id_by_order_item_id($item_id);

        $resursPaymentId = get_post_meta($orderId, 'paymentId', true);
        // No need to continue if Resurs payment id is absent.
        if (!$resursPaymentId) {
            return;
        }
        $order = new WC_Order($orderId);
        $payment_method = $order->get_payment_method();

        if (!(bool)preg_match('/resurs_bank/', $payment_method)) {
            return;
        }

        if (empty($productId)) {
            $testItemType = r_wc_get_order_item_type_by_item_id($item_id);
            $testItemName = r_wc_get_order_item_type_by_item_id($item_id);
            if ($testItemType === 'shipping') {
                $resursFlow->addOrderLine('00_frakt', null, null, null, null, null, $productQty);
            } elseif ($testItemType === 'coupon') {
                $resursFlow->addOrderLine($testItemName . '_kupong', null, null, null, null, null, $productQty);
            } elseif ($testItemType === 'fee') {
                if (function_exists('wc_get_order')) {
                    $current_order = wc_get_order($orderId);
                    $feeName = '00_' . str_replace(' ', '_', $current_order->payment_method_title) . '_fee';
                    $resursFlow->addOrderLine($feeName, null, null, null, null, null, $productQty);
                } else {
                    $order_failover_test = new WC_Order($orderId);
                    $feeName = '00_' . str_replace(
                            ' ',
                            '_',
                            $order_failover_test->payment_method_title
                        ) . '_fee';
                    $resursFlow->addOrderLine($feeName, null, null, null, null, null, $productQty);
                }
            }
        } else {
            $resursFlow->addOrderLine($productId, null, null, null, null, null, $productQty);
        }

        try {
            $order = new WC_Order($orderId);
            $removeResursRow = $resursFlow->paymentCancel($resursPaymentId);
            $order->add_order_note(__(
                'Orderline Removal: Resurs Bank API was called to remove orderlines',
                'resurs-bank-payment-gateway-for-woocommerce'
            ));
        } catch (Exception $e) {
            $resultArray = [
                'success' => false,
                'fail' => utf8_encode($e->getMessage()),
            ];
            echo $e->getMessage();
            die();
        }
        if (!$removeResursRow) {
            echo 'Cancelling payment failed without a proper reason';
            die();
        }
    }
}

/**
 * Get order by current payment id
 *
 * @param string $paymentId
 *
 * @return null|string
 */
function wc_get_order_id_by_payment_id($paymentId = '')
{
    global $wpdb;
    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and meta_value = '%s'",
            $paymentId
        )
    );
    $order_id_last = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentIdLast' and meta_value = '%s'",
            $paymentId
        )
    );

    // If updateOrderReference-setting is enabled, also look for a prior variable, to track down the
    // correct order based on the metadata tag paymentIdLast
    if (getResursOption('postidreference') && !empty($order_id_last) && empty($order_id)) {
        return $order_id_last;
    }

    return $order_id;
}

/**
 * Get payment id by order id
 *
 * @param string $orderId
 *
 * @return null|string
 */
function wc_get_payment_id_by_order_id($orderId = '')
{
    global $wpdb;
    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and post_id = '%s'",
            $orderId
        )
    );

    return $order_id;
}

/**
 * @param string $flagKey
 *
 * @return bool|string
 */
function getResursFlag($flagKey = null)
{
    $allFlags = [];
    $flagRow = getResursOption('devFlags');
    $flagsArray = explode(',', $flagRow);
    $multiArrayFlags = ['AUTO_DEBIT'];

    if (is_array($flagsArray)) {
        foreach ($flagsArray as $flagIndex => $flagParameter) {
            $flagEx = explode('=', $flagParameter, 2);
            if (is_array($flagEx) && isset($flagEx[1])) {
                // Handle as parameter key with values
                if (!is_null($flagKey)) {
                    if (strtolower($flagEx[0]) == strtolower($flagKey)) {
                        return $flagEx[1];
                    }
                } else {
                    if (in_array($flagEx[0], $multiArrayFlags)) {
                        if (!isset($allFlags[$flagEx[0]]) || !is_array($allFlags[$flagEx[0]])) {
                            $allFlags[$flagEx[0]] = [];
                        }
                        $allFlags[$flagEx[0]][] = $flagEx[1];
                    } else {
                        $allFlags[$flagEx[0]] = $flagEx[1];
                    }
                }
            } else {
                if (!is_null($flagKey)) {
                    // Handle as defined true
                    if (strtolower($flagParameter) == strtolower($flagKey)) {
                        return true;
                    }
                } else {
                    $allFlags[$flagParameter] = true;
                }
            }
        }
    }
    if (is_null($flagKey)) {
        return $allFlags;
    }

    return false;
}


if (!function_exists('r_wc_get_order_id_by_order_item_id')) {
    /**
     * Get the order id from where a specific item resides
     *
     * @param $item_id
     *
     * @return null|string
     * @since 2.0.2
     */
    function r_wc_get_order_id_by_order_item_id($item_id)
    {
        global $wpdb;
        $item_id = absint($item_id);
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
            $item_id
        ));

        return $order_id;
    }
}
if (!function_exists('r_wc_get_order_item_type_by_item_id')) {
    /**
     * Get the order item type (or name) by item id
     *
     * @param $item_id
     *
     * @return null|string
     * @since 2.0.2
     */
    function r_wc_get_order_item_type_by_item_id($item_id, $getItemName = false)
    {
        global $wpdb;
        $item_id = absint($item_id);
        if (!$getItemName) {
            $order_item_type = $wpdb->get_var($wpdb->prepare(
                "SELECT order_item_type FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                $item_id
            ));

            return $order_item_type;
        } else {
            $order_item_name = $wpdb->get_var($wpdb->prepare(
                "SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                $item_id
            ));

            return $order_item_name;
        }
    }
}

/**
 * @param $username
 * @param $flow ResursBank
 * @return mixed
 */
function getResursInternalRcoUrl($username, $flow)
{
    $iframeTestUrl = getResursOption('iframeTestUrl', 'woocommerce_resurs_bank_omnicheckout_settings');
    $specialAccounts = apply_filters('resurs_pte_account', []);
    $pteUsers = getResursFlag('PTEUSERS');
    if (!empty($pteUsers)) {
        $pteUsersArray = preg_split('/,|\|/', $pteUsers);
        if (is_array($pteUsersArray)) {
            foreach ($pteUsersArray as $pteUser) {
                if (!empty($pteUser)) {
                    $specialAccounts[] = $pteUser;
                }
            }
        }
    }

    $alwaysPte = (bool)getResursOption('alwaysPte', 'woocommerce_resurs_bank_omnicheckout_settings');
    if ($alwaysPte && isset($_SERVER['HTTP_HOST']) && preg_match('/\.cte\.loc|\.pte\.loc/i', $_SERVER['HTTP_HOST'])) {
        $specialAccounts[] = $username;
    }

    if (!empty($iframeTestUrl)) {
        if (in_array(strtolower($username), array_map('strtolower', $specialAccounts))) {
            $flow->setEnvRcoUrl($iframeTestUrl);
        }
    }

    return $flow;
}

/**
 * Initialize EComPHP, the key of almost everything in this plugin
 *
 * @param string $overrideUser
 * @param string $overridePassword
 * @param int $setEnvironment
 * @param bool $requireNewFlow Do not reuse old ecom-instance on true.
 * @return ResursBank
 * @throws Exception
 */
function initializeResursFlow(
    $overrideUser = '',
    $overridePassword = '',
    $setEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET,
    $requireNewFlow = false
) {
    global $current_user, $hasResursFlow, $resursInstanceCount, $resursSavedInstance, $woocommerce;
    $username = getResursOption('login');
    $password = getResursOption('password');
    $useEnvironment = getServerEnv();
    if ($setEnvironment !== RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET) {
        $useEnvironment = $setEnvironment;
    }
    if (!empty($overrideUser)) {
        $username = $overrideUser;
    }
    if (!empty($overridePassword)) {
        $password = $overridePassword;
    }

    // Defined in seconds how low the connectivity timeout should be on API timeouts.
    $timeoutExceptionLimit = getResursOption('timeout_throttler');

    // Reset and recreate ecom-instance on demand.
    if ($requireNewFlow) {
        $hasResursFlow = false;
    }

    $hasTransientTimeout = (int)get_transient('resurs_connection_timeout');
    if ($hasTransientTimeout && !empty($resursSavedInstance)) {
        $resursSavedInstance->setTimeout($timeoutExceptionLimit);
        $resursSavedInstance->setFlag('CURL_TIMEOUT', $timeoutExceptionLimit);
    }

    $resursInstanceCount++;
    if ($hasResursFlow && !empty($resursSavedInstance)) {
        return $resursSavedInstance;
    }

    /** @var $initFlow ResursBank */
    $initFlow = new ResursBank($username, $password);
    $initFlow->setWsdlCache(true);

    $ecomCacheTime = getResursFlag('ECOM_CACHE_TIME');
    if (!empty($ecomCacheTime) && is_numeric($ecomCacheTime) && $ecomCacheTime > 1) {
        $initFlow->setApiCacheTime($ecomCacheTime);
    }
    getResursInternalRcoUrl($username, $initFlow);
    $cTimeout = (int)getResursFlag('CURL_TIMEOUT');
    if (!$cTimeout) {
        $cTimeout = 12;
    }
    if ($hasTransientTimeout > 0) {
        $cTimeout = $timeoutExceptionLimit;
    }

    $initFlow->setFlag('CURL_TIMEOUT', $cTimeout);
    $initFlow->setTimeout($cTimeout);
    $initFlow->setSimplifiedPsp(true);
    $initFlow->setRealClientName('Woo');

    if (isResursHosted()) {
        $initFlow->setPreferredPaymentFlowService(CheckoutType::HOSTED_FLOW);
    }

    $sslHandler = getResursFlag('DISABLE_SSL_VALIDATION');
    if (isResursTest() && $sslHandler) {
        $initFlow->setDebug(true);
        $initFlow->setSslValidation(false);
    }
    $allFlags = getResursFlag(null);
    foreach ($allFlags as $flagKey => $flagValue) {
        if (!empty($flagKey)) {
            if ($flagKey !== 'AUTO_DEBIT') {
                $initFlow->setFlag($flagKey, $flagValue);
            } else {
                foreach ($flagValue as $autoDebitName) {
                    if (method_exists($initFlow, 'setAutoDebitableType')) {
                        $initFlow->setAutoDebitableType($autoDebitName);
                    }
                }
            }
        }
    }
    $autoDebitMethodList = getResursOption('autoDebitMethods');
    if (is_array($autoDebitMethodList)) {
        foreach ($autoDebitMethodList as $metodType) {
            $initFlow->setAutoDebitableType($metodType);
        }
    }

    $flowType = str_replace('resurs_bank_', '', getResursOption('flowtype'));
    $userAgent = sprintf(
        '%s-%s-flow-%s-WC-%s',
        RB_WOO_CLIENTNAME,
        RB_WOO_VERSION,
        $flowType,
        $woocommerce->version
    );
    $initFlow->setUserAgent($userAgent);
    $initFlow->setEnvironment($useEnvironment);
    $initFlow->setDefaultUnitMeasure();
    if (isset($_REQUEST['testurl'])) {
        $baseUrlTest = $_REQUEST['testurl'];
        // Set this up once
        if ($baseUrlTest == 'unset' || empty($baseUrlTest)) {
            unset($_SESSION['customTestUrl'], $baseUrlTest);
        } else {
            $_SESSION['customTestUrl'] = $baseUrlTest;
        }
    }
    if (isset($_SESSION['customTestUrl'])) {
        $_SESSION['customTestUrl'] = $initFlow->setTestUrl($_SESSION['customTestUrl']);
    }
    try {
        if (function_exists('wp_get_current_user')) {
            wp_get_current_user();
        } else {
            get_currentuserinfo();
        }
        if (isset($current_user->user_login)) {
            // Used for aftershop and is not used for metadata
            $initFlow->setLoggedInUser(getResursWordpressUser('user_login'));
        }
    } catch (Exception $e) {
    }
    $country = getResursOption('country');
    $initFlow->setCountryByCountryCode($country);
    if ($initFlow->getCountry() == 'FI') {
        $initFlow->setDefaultUnitMeasure('kpl');
    }

    $hasResursFlow = true;
    $resursSavedInstance = $initFlow;
    $initFlow->resetPayload();

    return $initFlow;
}

/**
 * @param string $ssn
 * @param string $customerType
 * @param string $ip
 *
 * @return array|mixed|null
 * @throws Exception
 */
function getAddressProd($ssn = '', $customerType = '', $ip = '')
{
    global $current_user;
    $username = resursOption('ga_login');
    $password = resursOption('ga_password');
    if (!empty($username) && !empty($password)) {
        /** @var ResursBank $initFlow */
        $initFlow = new ResursBank($username, $password);
        $initFlow->setUserAgent(RB_WOO_CLIENTNAME . '-' . RB_WOO_VERSION);
        $initFlow->setEnvironment(RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION);
        try {
            $getResponse = $initFlow->getAddress($ssn, $customerType, $ip);

            return $getResponse;
        } catch (Exception $e) {
            echo json_encode(['Unavailable credentials - ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['Unavailable credentials']);
    }
    die();
}

/**
 * Get current Resurs Environment setup (demo/test or production)
 *
 * @return int
 */
function getServerEnv()
{
    $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;

    $serverEnv = getResursOption('serverEnv');

    if ($serverEnv === 'live') {
        $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
    }
    /*
     * Prohibit production mode if this is a demoshop
     */
    if ($serverEnv === 'test') {
        $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;
    }

    return $useEnvironment;
}

/**
 * Returns true if this is a test environment
 *
 * @return bool
 */
function isResursTest()
{
    $currentEnv = getServerEnv();
    if ($currentEnv === RESURS_ENVIRONMENTS::ENVIRONMENT_TEST) {
        return true;
    }

    return false;
}

/**
 * Get current customer id
 *
 * @param WC_Order $order
 * @return int|null
 */
function getResursWooCustomerId($order = null)
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
    if ($order !== null) {
        $return = $order->get_user_id();
    }

    return $return;
}

/**
 * @param string $key
 * @param bool $popOnArray
 * @return mixed
 */
function getResursWordpressUser($key = 'userid', $popOnArray = true)
{
    $wpUserId = get_current_user_id();
    if ($key == 'userid') {
        return $wpUserId;
    }

    if ($wpUserId) {
        if (is_null($key)) {
            $uMeta = get_user_meta($wpUserId);
        } else {
            $uMeta = get_user_meta($wpUserId, $key);
        }
    }

    if (isset($uMeta) && is_array($uMeta) && !count($uMeta) && $wpUserId) {
        /** @var WP_User $wpUserData */
        $wpUserData = get_userdata($wpUserId);
        $wpUserDataReturn = $wpUserData->get($key);
        if (!empty($wpUserDataReturn)) {
            if ($popOnArray) {
                return $wpUserDataReturn;
            } else {
                return [$wpUserDataReturn];
            }
        }
    }

    if ($popOnArray && isset($uMeta) && is_array($uMeta) && count($uMeta)) {
        return array_pop($uMeta);
    }
}

/********************** OMNICHECKOUT RELATED STARTS HERE ******************/

/**
 * isResursOmni.
 * Returns true if RCO is the selected flow. Checks are done with hasResursOmni where the disabled flags
 * are ignored.
 *
 * @param bool $ignoreActiveFlag
 *
 * @return bool
 */
function isResursOmni($ignoreActiveFlag = false)
{
    global $woocommerce;
    $returnValue = false;
    $externalOmniValue = null;
    $currentMethod = '';
    if (isset($woocommerce->session)) {
        $currentMethod = $woocommerce->session->get('chosen_payment_method');
    }
    $flowType = getResursOption('flowtype');
    // hasOmni is used by many.
    $hasOmni = (bool)hasResursOmni($ignoreActiveFlag);
    if ($hasOmni && (!empty($currentMethod) && $flowType === $currentMethod)) {
        $returnValue = true;
    }
    /*
     * If Omni is enabled and the current chosen method is empty, pre-select omni
     */
    if ($hasOmni === true &&
        $flowType === 'resurs_bank_omnicheckout' &&
        empty($currentMethod)
    ) {
        $returnValue = true;
    }
    if ($returnValue) {
        // If the checkout is normally set to be enabled, this gives external plugins a chance to have it disabled
        $externalOmniValue = apply_filters('resursbank_temporary_disable_checkout', null);
        if (!is_null($externalOmniValue)) {
            $returnValue = ($externalOmniValue ? false : true);
        }
    }

    return $returnValue;
}

/**
 * Check if the hosted flow is enabled and chosen
 *
 * @return bool
 */
function isResursHosted()
{
    $hasHosted = hasResursHosted();
    if ($hasHosted == 1 || $hasHosted === true) {
        return true;
    }

    return false;
}

/**
 * Returns list of configured statuses on callbacks
 *
 * @return array|bool
 */
function resurs_payment_status_callbacks()
{
    $callbackStatus = getResursOption('resurs_payment_status_callback');
    if (!is_array($callbackStatus)) {
        $callbackStatus = [];
    }
    return $callbackStatus;
}

/**
 * @return bool
 */
function hasEcomPHP()
{
    if (class_exists('ResursBank') || class_exists('Resursbank\RBEcomPHP\ResursBank')) {
        return true;
    }

    return false;
}

/**
 * hasResursOmni: Is the chosen flow RCO?
 * Default: Returns true IF flow is RCO and plugin is NOT disabled.
 *
 * @param bool $ignoreActiveFlag Check this setting even though the plugin is not active.
 *
 * @return bool
 */
function hasResursOmni($ignoreActiveFlag = false)
{
    $flowType = getResursOption('flowtype');
    if (is_admin()) {
        $omniOption = get_option('woocommerce_resurs_bank_omnicheckout_settings');
        if ($flowType === 'resurs_bank_omnicheckout') {
            $omniOption['enabled'] = 'yes';
        } else {
            $omniOption['enabled'] = 'no';
        }
        update_option('woocommerce_resurs_bank_omnicheckout_settings', $omniOption);
    }
    $resursEnabled = getResursOption('enabled');
    if (!(bool)$resursEnabled && !$ignoreActiveFlag) {
        return false;
    }
    if ($flowType === 'resurs_bank_omnicheckout') {
        return true;
    }

    return false;
}

/**
 * @return bool
 */
function hasResursHosted()
{
    $resursEnabled = resursOption('enabled');
    $flowType = resursOption('flowtype');
    if ($resursEnabled != 'yes') {
        return false;
    }
    if ($flowType == 'resurs_bank_hosted') {
        return true;
    }

    return false;
}

/**
 * @param $classButtonHtml
 */
function resurs_omnicheckout_order_button_html($classButtonHtml)
{
    global $woocommerce;
    if (!isResursOmni()) {
        echo $classButtonHtml;
    }
}

/**
 * Payment methods validator for OmniCheckout
 *
 * @param $paymentGatewaysCheck
 * @return null
 */
function resurs_omnicheckout_payment_gateways_check()
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
    if (is_array($paymentGatewaysCheck)) {
        $paymentGatewaysCheck = [];
    }
    if (!count($paymentGatewaysCheck)) {
        // If there is no active payment gateways except for omniCheckout, the warning of no available payment gateways has to be suppressed
        if (isResursOmni()) {
            return null;
        }

        return __(
            'There are currently no payment methods available',
            'resurs-bank-payment-gateway-for-woocommerce'
        );
    }

    return $paymentGatewaysCheck;
}

/**
 * Check if there are gateways active (Omni related)
 *
 * @return bool
 */
function hasPaymentGateways()
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
    if (is_array($paymentGatewaysCheck)) {
        $paymentGatewaysCheck = [];
    }
    return count($paymentGatewaysCheck) > 1;
}

/********************** OMNICHECKOUT RELATED ENDS HERE ******************/

function resurs_gateway_activation()
{
    set_transient('ResursWooGatewayVersion', rbWcGwVersionToDecimals());
}

if (is_admin()) {
    register_activation_hook(__FILE__, 'resurs_gateway_activation');
}

/**
 * @param string $versionRequest
 * @param string $operator
 *
 * @return bool
 * @throws Exception
 */
function hasWooCommerce($versionRequest = '2.0.0', $operator = '>=')
{
    if (version_compare(WOOCOMMERCE_VERSION, $versionRequest, $operator)) {
        return true;
    }
}

/**
 * @param string $checkVersion
 *
 * @return bool
 * @throws Exception
 */
function isWooCommerce3($checkVersion = '3.0.0')
{
    return hasWooCommerce($checkVersion);
}

function getResursLogActive()
{
    $return = true;
    if (!file_exists(getResursLogDestination())) {
        @mkdir(getResursLogDestination());
    }
    // Not writable (if this is not delivered with the plugin, something went wrong)
    if (!file_exists(getResursLogDestination())) {
        $return = false;
    }
    if (!file_exists(getResursLogDestination() . '/resurs.log')) {
        @file_put_contents(getResursLogDestination() . '/resurs.log', time() . ': ' . "Log initialization.\n");
    }
    if (!file_exists(getResursLogDestination() . '/resurs.log')) {
        $return = false;
    }
    return $return;
}

/**
 * @return string
 */
function getResursLogDestination()
{
    return plugin_dir_path(__FILE__) . '/logs/';
}

/**
 * @param string $dataString
 * @deprecated Use rbSimpleLogging instead.
 */
function resursEventLogger($dataString = '')
{
    if (getResursOption('logResursEvents') && getResursLogActive()) {
        /**
         * Forward all logging to the real logger.
         * @see WOO-605
         */
        rbSimpleLogging($dataString);
    }
}

if (!function_exists('getHadMisplacedIframeLocation')) {
    /**
     * Makes sure that you can reselect a deprecated setting for the iframe location
     * when using RCO if it has been selected once in a time
     *
     * @return bool|mixed|void
     * @since 2.2.13
     */
    function getHadMisplacedIframeLocation()
    {
        $hadIframeInMethods = get_option('rb_iframe_location_was_in_methods');
        // Speed up process
        if ($hadIframeInMethods) {
            return true;
        }
        $currentIframeLocation = omniOption('iFrameLocation');
        if ($currentIframeLocation === 'inMethods' && !$hadIframeInMethods) {
            $hadIframeInMethods = true;
            update_option('rb_iframe_location_was_in_methods', $hadIframeInMethods);
        }
        return $hadIframeInMethods;
    }
}

/**
 * Get path to payment method models. Should work with multisites.
 *
 * @return string
 */
function getResursPaymentMethodModelPath()
{
    global $table_prefix;

    $includesPath = 'includes/';
    if (!empty($table_prefix) && preg_match('/_$/', $table_prefix)) {
        $return = $includesPath . preg_replace('/_$/', '', $table_prefix);
    } else {
        $return = $includesPath;
    }

    $alternativePrefixPath = apply_filters('resurs_bank_model_prefix', $table_prefix);
    if (!empty($alternativePrefixPath) && $alternativePrefixPath !== $table_prefix) {
        $return = $includesPath . $alternativePrefixPath;
    }

    // Reformat trailing slashes.
    $return = preg_replace('/\/$/', '', $return) . '/';
    $modelPath = plugin_dir_path(__FILE__) . $return;

    if (!file_exists($modelPath)) {
        // Silently prepare for sub-includes.
        if (!mkdir($modelPath) && !is_dir($modelPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $modelPath));
        }
        @file_put_contents($modelPath . '.htaccess', 'Options -indexes');
    }

    return $return;
}

/**
 * @param $id
 * @return bool
 */
function getResursUpdatePaymentReferenceResult($id)
{
    return (bool)get_post_meta($id, 'updateResursReferenceSuccess');
}

/**
 * @param $id
 * @param string $methodName
 * @param string $key
 * @param string $value
 */
function setResursPaymentMethodMeta($id, $methodName = '', $key = 'resursBankMetaPaymentMethod', $value = '')
{
    if ($id > 0) {
        $storedVatData = [
            'coupons_include_vat' => getResursOption('coupons_include_vat'),
        ];

        $paymentMethodName = isset($_REQUEST['paymentMethod']) ? $_REQUEST['paymentMethod'] : '';
        if (empty($paymentMethodName) && !empty($methodName)) {
            $paymentMethodName = $methodName;
        }

        try {
            $flow = initializeResursFlow();
            // We also want to set the payment method type at payment levels.
            $method = $flow->getPaymentMethodSpecific($paymentMethodName);
            if (isset($method->type) && !empty($method->type)) {
                update_post_meta(
                    $id,
                    'resursBankMetaPaymentMethodType',
                    $method->type
                );
            }
            if (isset($method->specificType) && !empty($method->specificType)) {
                update_post_meta(
                    $id,
                    'resursBankMetaPaymentMethodSpecificType',
                    $method->specificType
                );
            }
        } catch (Exception $e) {
            // Silently ignore API calls if they don't work at this moment.
        }

        update_post_meta(
            $id,
            'resursBankMetaPaymentMethod',
            $paymentMethodName
        );
        update_post_meta(
            $id,
            'resursBankMetaPaymentStoredVatData',
            serialize($storedVatData)
        );
        update_post_meta(
            $id,
            'resursBankPaymentFlow',
            getResursOption('flowtype')
        );
    }
}

/**
 * @param $id
 * @param $key
 * @param $value
 */
function setResursOrderMetaData($id, $key, $value)
{
    update_post_meta(
        $id,
        $key,
        $value
    );
}

/**
 * @param $id int Most likely the post id as order id.
 * @param $key string The meta data to retrieve by its key name.
 * @return string
 */
function getResursPaymentMethodMeta($id, $key = 'resursBankMetaPaymentMethod')
{
    $return = '';
    $metaMethodTest = get_post_meta($id, $key);
    if (is_array($metaMethodTest)) {
        // If metadata contains more than one entry, return them all commaseparated.
        if (count($metaMethodTest) > 1) {
            $return = implode(', ', $metaMethodTest);
        } else {
            $returnValue = array_pop($metaMethodTest);
            if (!empty($returnValue)) {
                $return = $returnValue;
            }
        }
    }
    return (string)$return;
}

function getResursRequireSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * @since 2.2.58
 */
function rb_in_maintenance()
{
    global $pagenow, $current_tab;
    $isApiCall = isset($_REQUEST['wc-api']) && $_REQUEST['wc-api'] === 'WC_Resurs_Bank';

    if ((is_admin() || is_ajax()) && $pagenow !== 'wp-login.php') {
        return;
    }
    if ($isApiCall || $current_tab === 'tab_resursbank') {
        return;
    }

    $transientPeriod = get_transient('rb_credentials_update');
    if ($transientPeriod) {
        printf(
            '<html><head><title>%s</title></head><body>%s</body></html>',
            __('System maintenance in progress!', 'resurs-bank-payment-gateway-for-woocommerce'),
            __(
                'This store is currently in a temporary maintenance mode. Please wait a minute and try again.',
                'resurs-bank-payment-gateway-for-woocommerce'
            )
        );

        exit;
    }
}

add_action('wp_loaded', 'rb_in_maintenance');

<?php

require_once(__DIR__ . '/vendor/autoload.php');
include('functions_settings.php');

use Resursbank\RBEcomPHP\RESURS_CALLBACK_TYPES;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;
use Resursbank\RBEcomPHP\RESURS_PAYMENT_STATUS_RETURNCODES;

$resurs_obsolete_coexistence_disable = (bool)apply_filters('resurs_obsolete_coexistence_disable', null);
if ($resurs_obsolete_coexistence_disable) {
    return;
}

$resursGlobalNotice = false;

// Initialize Resurs Bank Plugin when plugins is finally loaded
/**
 *
 */
function woocommerce_gateway_resurs_bank_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    if (class_exists('WC_Resurs_Bank')) {
        return;
    }

    // (Very) Simplified locale and country enforcer. Do not use unless necessary, since it may break something.
    if (isset($_GET['forcelanguage']) && isset($_SERVER['HTTP_REFERER'])) {
        $languages = array(
            'sv_SE' => 'SE',
            'nb_NO' => 'NO',
            'da_DK' => 'DK',
            'fi' => 'FI'
        );
        $setLanguage = $_GET['forcelanguage'];
        if (isset($languages[$setLanguage])) {
            $sellTo = array($languages[$setLanguage]);
            $wooSpecific = get_option('woocommerce_specific_allowed_countries');
            // Follow woocommerce options. A little.
            if (is_array($wooSpecific) && count($wooSpecific)) {
                update_option('woocommerce_specific_allowed_countries', $sellTo);
            } else {
                update_option('woocommerce_specific_allowed_countries', array());
            }
            setResursOption('country', $languages[$setLanguage]);
            update_option('WPLANG', $setLanguage);
            update_option('woocommerce_default_country', $languages[$setLanguage]);
        }
        wp_safe_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }

    // Localization
    load_plugin_textdomain(
        'resurs-bank-payment-gateway-for-woocommerce',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    /**
     * Class WC_Resurs_Bank
     */
    class WC_Resurs_Bank extends WC_Payment_Gateway
    {

        /** @var \Resursbank\RBEcomPHP\ResursBank */
        protected $flow;
        protected $rates;
        private $callback_types;
        private $baseLiveURL;
        private $baseTestURL;
        private $serverEnv;

        /**
         * Constructor method for Resurs Bank plugin
         *
         * This method initializes various properties and fetches payment methods, either from the tranient API or from Resurs Bank API.
         * It is also responsible for calling generate_payment_gateways, if these need to be refreshed.
         */
        public function __construct()
        {
            add_action('woocommerce_api_wc_resurs_bank', array($this, 'check_callback_response'));

            hasResursOmni();
            isResursSimulation(); // Make sure settings are properly set each round

            //$this->title = "Resurs Bank";
            $this->id = "resurs-bank";
            $this->method_title = "Resurs Bank Administration";
            $this->has_fields = false;
            $this->callback_types = array(
                'UNFREEZE' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
                ),
                'AUTOMATIC_FRAUD_CONTROL' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                        'result' => 'result',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
                ),
                'ANNULMENT' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
                ),
                'FINALIZATION' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
                ),
                'BOOKED' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
                ),
                'UPDATE' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
                ),
                'TEST' => array(
                    'uri_components' => array(
                        'prm1' => 'param1',
                        'prm2' => 'param2',
                        'prm3' => 'param3',
                        'prm4' => 'param4',
                        'prm5' => 'param5'
                    ),
                    'digest_parameters' => array(
                        'parameter1' => 'param1',
                        'parameter2' => 'param2',
                        'parameter3' => 'param3',
                        'parameter4' => 'param4',
                        'parameter5' => 'param5'
                    )
                )
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->login = $this->get_option('login');
            $this->password = $this->get_option('password');
            $this->baseLiveURL = $this->get_option('baseLiveURL');
            $this->baseTestURL = $this->get_option('baseTestURL');
            $this->serverEnv = $this->get_option('serverEnv');

            /*
             * The flow configurator is only available in demo mode.
             * 170203: Do not remove this since it is internally used (not only i demoshop).
             */
            if (isset($_REQUEST['flowconfig'])) {
                if (isResursDemo()) {
                    $updatedFlow = false;
                    $currentFlowType = getResursOption('flowtype');
                    $availableFlowTypes = array(
                        'simplifiedshopflow' => 'Simplified Flow',
                        'resurs_bank_hosted' => 'Resurs Bank Hosted Flow',
                        'resurs_bank_omnicheckout' => 'Resurs Bank Omni Checkout'
                    );
                    if (isset($_REQUEST['setflow']) && $availableFlowTypes[$_REQUEST['setflow']]) {
                        $updatedFlow = true;
                        $currentFlowType = $_REQUEST['setflow'];
                        setResursOption("flowtype", $currentFlowType);
                        $omniOption = get_option('woocommerce_resurs_bank_omnicheckout_settings');
                        if ($currentFlowType == "resurs_bank_omnicheckout") {
                            $omniOption['enabled'] = 'yes';
                        } else {
                            $omniOption['enabled'] = 'no';
                        }
                        update_option('woocommerce_resurs_bank_omnicheckout_settings', $omniOption);
                        if (isset($_REQUEST['liveflow'])) {
                            wp_safe_redirect(wc_get_checkout_url());
                            die();
                        }
                    }

                    $methodUpdateMessage = "";
                    if (isset($this->login) && !empty($this->login) && $updatedFlow) {
                        try {
                            $this->paymentMethods = $this->get_payment_methods();
                            $methodUpdateMessage = __('Payment method gateways are updated',
                                    'resurs-bank-payment-gateway-for-woocommerce') . "...\n";
                        } catch (Exception $e) {
                            $methodUpdateMessage = $e->getMessage();
                        }
                    }

                    echo '
                <form method="post" action="?flowconfig">
                <select name="setflow">
                ';
                    $selectedFlowType = "";
                    foreach ($availableFlowTypes as $selectFlowType => $flowTypeDescription) {
                        if ($selectFlowType == $currentFlowType) {
                            $selectedFlowType = "selected";
                        } else {
                            $selectedFlowType = "";
                        }
                        echo '<option value="' . $selectFlowType . '" ' . $selectedFlowType . '>' . $flowTypeDescription . '</option>' . "\n";
                    };
                    echo '
                <input type="submit" value="' . __('Change the flow type',
                            'resurs-bank-payment-gateway-for-woocommerce') . '"><br>
                </select>
                </form>
                <a href="' . get_home_url() . '">' . __('Back to shop', 'resurs-bank-payment-gateway-for-woocommerce') . '</a><br>
                <a href="' . wc_get_checkout_url() . '">' . __('Back to checkout',
                            'resurs-bank-payment-gateway-for-woocommerce') . '</a><br>
                <br>
                ' . $methodUpdateMessage;
                } else {
                    echo __('Changing flows when the plugin is not in demo mode is not possible',
                        'resurs-bank-payment-gateway-for-woocommerce');
                }
                exit;
            }

            $this->flowOptions = null;

            if (hasEcomPHP()) {
                if (!empty($this->login) && !empty($this->password)) {
                    /** @var \Resursbank\RBEcomPHP\ResursBank */
                    $this->flow = initializeResursFlow();
                    $setSessionEnable = true;
                    $setSession = isset($_REQUEST['set-no-session']) ? $_REQUEST['set-no-session'] : null;
                    if ($setSession == 1) {
                        $setSessionEnable = false;
                    } else {
                        $setSessionEnable = true;
                    }

                    /*
                     * Not using is_checkout() since themes may not work the same work.
                     *
                     * In some cases, there won't be any session set if this is done. So we'll look for
                     * the session instead.
                     */
                    if (isset(WC()->session) && $setSessionEnable) {
                        $omniRef = $this->flow->getPreferredPaymentId(25, "RC");
                        $newOmniRef = $omniRef;
                        $currentOmniRef = WC()->session->get('omniRef');
                        $omniId = WC()->session->get("omniid");
                        if (isset($_REQUEST['event-type']) && $_REQUEST['event-type'] == "prepare-omni-order" && isset($_REQUEST['orderRef']) && !empty($_REQUEST['orderRef'])) {
                            $omniRef = $_REQUEST['orderRef'];
                            $currentOmniRefAge = 0;
                            $omniRefCreated = time();
                        }

                        $omniRefCreated = WC()->session->get('omniRefCreated');
                        $currentOmniRefAge = time() - $omniRefCreated;
                        if (empty($currentOmniRef)) {
                            /*
                             * Empty references, create
                             */
                            WC()->session->set('omniRef', $omniRef);
                            WC()->session->set('omniRefCreated', time());
                            WC()->session->set('omniRefAge', $currentOmniRefAge);
                        }
                    } else {
                        if (isset($_REQUEST['omnicheckout_nonce']) && wp_verify_nonce($_REQUEST['omnicheckout_nonce'],
                                "omnicheckout")) {
                            if (isset($_REQUEST['purchaseFail']) && $_REQUEST['purchaseFail'] == 1) {
                                $returnResult = array(
                                    'success' => false,
                                    'errorString' => "",
                                    'errorCode' => "",
                                    'verified' => false,
                                    'hasOrder' => false,
                                    'resursData' => array()
                                );
                                if (isset($_GET['pRef'])) {
                                    $purchaseFailOrderId = wc_get_order_id_by_payment_id($_GET['pRef']);
                                    $purchareFailOrder = new WC_Order($purchaseFailOrderId);
                                    $purchareFailOrder->update_status('failed',
                                        __('Resurs Bank denied purchase',
                                            'resurs-bank-payment-gateway-for-woocommerce'));
                                    update_post_meta($purchaseFailOrderId, 'soft_purchase_fail', true);
                                    WC()->session->set("resursCreatePass", 0);
                                    $returnResult['success'] = true;
                                    $returnResult['errorString'] = 'Denied by Resurs';
                                    $returnResult['errorCode'] = "200";
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

            if (hasWooCommerce("2.0.0", ">=")) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    $this,
                    'process_admin_options'
                ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Are we in omni mode?
         *
         * @return bool
         */
        function isResursOmni()
        {
            // Returned from somewhere else
            return isResursOmni();
        }

        /**
         * Initialize the form fields for the plugin
         */
        function init_form_fields()
        {
            $this->form_fields = getResursWooFormFields();

            /*
             * In case of upgrades where defaults are not yet set, automatically set them up.
             */
            if (!hasResursOptionValue("getAddress")) {
                setResursOption("getAddress", "true");
            }
            if (!hasResursOptionValue("getAddressUseProduction")) {
                setResursOption("getAddressUseProduction", "false");
            }
            if (!hasResursOptionValue("streamlineBehaviour")) {
                setResursOption("streamlineBehaviour", "true");
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
            $url_arr = parse_url($_SERVER["REQUEST_URI"]);
            $url_arr['query'] = str_replace('amp;', '', $url_arr['query']);
            parse_str($url_arr['query'], $request);
            if (!is_array($request)) {
                $request = array();
            }
            if (!count($request) && isset($_GET['event-type'])) {
                $request = $_GET;
            }
            $event_type = $request['event-type'];

            if ($event_type == "TEST") {
                set_transient('resurs_callbacks_received', time());
                set_transient('resurs_callbacks_content', $_REQUEST);
                header('HTTP/1.0 204 CallbackWithoutDigestTriggerOK');
                die();
            }

            if ($event_type == "noevent") {
                $myResponse = null;
                $myBool = false;
                $errorMessage = "";

                $setType = isset($_REQUEST['puts']) ? $_REQUEST['puts'] : "";
                $setValue = isset($_REQUEST['value']) ? $_REQUEST['value'] : "";
                $reqNamespace = isset($_REQUEST['ns']) ? $_REQUEST['ns'] : "";
                $reqType = isset($_REQUEST['wants']) ? $_REQUEST['wants'] : "";
                $reqNonce = isset($_REQUEST['ran']) ? $_REQUEST['ran'] : "";

                $newPaymentMethodsList = null;
                $envVal = null;
                if (!empty($reqType) || !empty($setType)) {
                    if (wp_verify_nonce($reqNonce, "requestResursAdmin") && $reqType) {
                        $mySession = true;
                        $reqType = str_replace($reqNamespace . "_", '', $reqType);
                        $myBool = true;
                        $myResponse = getResursOption($reqType);
                        if (empty($myResponse)) {
                            // Make sure this returns a string and not a bool.
                            $myResponse = '';
                        }
                    } elseif (wp_verify_nonce($reqNonce, "requestResursAdmin") && $setType) {
                        $mySession = true;
                        $failSetup = false;
                        $subVal = isset($_REQUEST['s']) ? $_REQUEST['s'] : "";
                        $envVal = isset($_REQUEST['e']) ? $_REQUEST['e'] : "";
                        if ($setType == "woocommerce_resurs-bank_password") {
                            $testUser = $subVal;
                            $testPass = $setValue;
                            $flowEnv = getServerEnv();
                            if (!empty($envVal)) {
                                if ($envVal == "test") {
                                    $flowEnv = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;
                                } elseif ($envVal == "live") {
                                    $flowEnv = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
                                } elseif ($envVal == "production") {
                                    $flowEnv = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
                                }
                                $newFlow = initializeResursFlow($testUser, $testPass, $flowEnv);
                            } else {
                                $newFlow = initializeResursFlow($testUser, $testPass);
                            }
                            try {
                                $newPaymentMethodsList = $newFlow->getPaymentMethods(array(), true);
                                $myBool = true;
                            } catch (Exception $e) {
                                $myBool = false;
                                $failSetup = true;
                                /** @var $errorMessage */
                                $errorMessage = $e->getMessage();
                                /** @var $prevError \Exception */
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
                        $setType = str_replace($reqNamespace . "_", '', $setType);
                        if (!$failSetup) {
                            $myBool = true;
                            setResursOption($setType, $setValue);
                            setResursOption("login", $subVal);
                            if (!empty($envVal)) {
                                setResursOption("serverEnv", $envVal);
                            }
                            $myResponse['element'] = array("currentResursPaymentMethods", "callbackContent");
                            set_transient('resurs_bank_last_callback_setup', 0);
                            $myResponse['html'] = '<br><div class="labelBoot labelBoot-success labelBoot-big labelBoot-nofat labelBoot-center">' . __('Please reload or save this page to have this list updated',
                                    'resurs-bank-payment-gateway-for-woocommerce') . '</div><br><br>';
                        }
                    }
                } else {
                    if (isset($_REQUEST['run'])) {
                        // Since our tests with WP 4.7.5, the nonce control seems to not work properly even if the nonce is actually
                        // are calculated correctly. This is a very temporary fix for that problem.
                        $nonceIsFailing = true;
                        if (wp_verify_nonce($reqNonce, "requestResursAdmin") || $nonceIsFailing) {
                            $mySession = true;
                            $arg = null;
                            if (isset($_REQUEST['arg'])) {
                                $arg = $_REQUEST['arg'];
                            }
                            $responseArray = array();
                            if ($_REQUEST['run'] == "updateResursPaymentMethods") {
                                try {
                                    $responseArray = true;
                                } catch (Exception $e) {
                                    $errorMessage = $e->getMessage();
                                }
                            } elseif ($_REQUEST['run'] == 'annuityDuration') {
                                $data = isset($_REQUEST['data']) ? $_REQUEST['data'] : null;
                                if (!empty($data)) {
                                    setResursOption("resursAnnuityDuration", $data);
                                }
                            } elseif ($_REQUEST['run'] == 'annuityToggle') {
                                $priorAnnuity = getResursOption("resursAnnuityMethod");
                                $annuityFactors = $this->flow->getAnnuityFactors($arg);
                                setResursOption("resursCurrentAnnuityFactors", $annuityFactors);
                                $selectorOptions = "";
                                // Also kill self
                                $scriptit = 'resursRemoveAnnuityElements(\'' . $arg . '\')';
                                if ($priorAnnuity == $arg) {
                                    $selector = "";
                                    $responseHtml = '<span id="annuityClick_' . $arg . '" class="status-disabled tips" data-tip="' . __('Disabled',
                                            'woocommerce') . '" onclick="runResursAdminCallback(\'annuityToggle\', \'' . $arg . '\');' . $scriptit . ';">-</span>' . "\n" . $selector;
                                    setResursOption("resursAnnuityMethod", "");
                                    setResursOption("resursAnnuityDuration", "");
                                    $isEnabled = "no";
                                } else {
                                    if (is_array($annuityFactors) && count($annuityFactors)) {
                                        $firstDuration = "";
                                        foreach ($annuityFactors as $factor) {
                                            if (!$firstDuration) {
                                                $firstDuration = $factor->duration;
                                            }
                                            $selectorOptions .= '<option value="' . $factor->duration . '">' . $factor->paymentPlanName . '</option>';
                                        }
                                        setResursOption("resursAnnuityMethod", $arg);
                                        setResursOption("resursAnnuityDuration", $firstDuration);
                                    }
                                    $isEnabled = "yes";
                                    $selector = '<select class="resursConfigSelectShort" id="annuitySelector_' . $arg . '" onchange="runResursAdminCallback(\'annuityDuration\', \'' . $arg . '\', this.value)">' . $selectorOptions . '</select>';
                                    $responseHtml = '<span id="annuityClick_' . $arg . '" class="status-enabled tips" data-tip="' . __('Enabled',
                                            'woocommerce') . '" onclick="runResursAdminCallback(\'annuityToggle\', \'' . $arg . '\');' . $scriptit . ';">-</span>' . "\n" . $selector;
                                }
                                $responseArray['valueSet'] = $isEnabled;
                                $responseArray['element'] = "annuity_" . $arg;
                                $responseArray['html'] = $responseHtml;
                            } elseif ($_REQUEST['run'] == "methodToggle") {
                                $dbMethodName = "woocommerce_resurs_bank_nr_" . $arg . "_settings";
                                $responseMethod = get_option($dbMethodName);
                                if (is_array($responseMethod) && count($responseMethod)) {
                                    $myBool = true;
                                    $isEnabled = $responseMethod['enabled'];
                                    if ($isEnabled == "yes" || $isEnabled == "true" || $isEnabled == "1") {
                                        $isEnabled = "no";
                                        $responseHtml = '<span class="status-disabled tips" data-tip="' . __('Disabled',
                                                'woocommerce') . '">-</span>';
                                    } else {
                                        $isEnabled = "yes";
                                        $responseHtml = '<span class="status-enabled tips" data-tip="' . __('Enabled',
                                                'woocommerce') . '">-</span>';
                                    }
                                    setResursOption("enabled", $isEnabled, $dbMethodName);
                                    $responseArray['valueSet'] = $isEnabled;
                                    $responseArray['element'] = "status_" . $arg;
                                    $responseArray['html'] = $responseHtml;
                                } else {
                                    $errorMessage = __("Configuration has not yet been initiated.",
                                        'resurs-bank-payment-gateway-for-woocommerce');
                                }
                            } elseif ($_REQUEST['run'] == "getMyCallbacks") {
                                $responseArray = array(
                                    'callbacks' => array()
                                );
                                $login = getResursOption("login");
                                $password = getResursOption("password");

                                if (!empty($login) && !empty($password)) {
                                    $lastFetchedCacheTime = time() - get_transient("resurs_callback_templates_cache_last");
                                    $lastFetchedCache = get_transient("resurs_callback_templates_cache");
                                    $_REQUEST['force'] = true;
                                    if ($lastFetchedCacheTime >= 86400 || empty($lastFetchedCache) || isset($_REQUEST['force'])) {
                                        try {
                                            $responseArray['callbacks'] = $this->flow->getCallBacksByRest(true);
                                            set_transient("resurs_callback_templates_cache_last", time());
                                            $myBool = true;
                                        } catch (Exception $e) {
                                            $errorMessage = $e->getMessage();
                                        }
                                        set_transient("resurs_callback_templates_cache", $responseArray['callbacks']);
                                        $responseArray['cached'] = false;
                                    } else {
                                        $myBool = true;
                                        $responseArray['callbacks'] = $lastFetchedCache;
                                        $responseArray['cached'] = true;
                                    }
                                }
                            } elseif ($_REQUEST['run'] == "setMyCallbacks") {
                                $responseArray = array();
                                $login = getResursOption("login");
                                $password = getResursOption("password");
                                if (!empty($login) && !empty($password)) {
                                    set_transient('resurs_bank_last_callback_setup', time());
                                    try {
                                        $salt = uniqid(mt_rand(), true);
                                        // Deprecation of transient storage.
                                        //set_transient('resurs_bank_digest_salt', $salt);
                                        setResursOption('resurs_bank_digest_salt', $salt, 'wc_resurs2_salt');
                                        $regCount = 0;
                                        $responseArray['registeredCallbacks'] = 0;
                                        $rList = array();
                                        set_transient("resurs_callback_templates_cache_last", 0);
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
                                        $responseArray['testTriggerTimestamp'] = strftime(
                                            '%Y-%m-%d (%H:%M:%S)',
                                            time()
                                        );
                                    } catch (Exception $e) {
                                        $responseArray['errorstring'] = $e->getMessage();
                                    }
                                }
                            } elseif ($_REQUEST['run'] == 'getNetCurlTag') {
                                $NET = new \TorneLIB\MODULE_NETWORK();
                                $curlTags = $NET->getGitTagsByUrl('https://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git');
                                $responseArray['netCurlTag'] = is_array($curlTags) && count($curlTags) ? array_pop($curlTags) : array();
                            } elseif ($_REQUEST['run'] == 'getEcomTag') {
                                $NET = new \TorneLIB\MODULE_NETWORK();
                                $ecomTags = $NET->getGitTagsByUrl('https://bitbucket.org/resursbankplugins/resurs-ecomphp.git');
                                $responseArray['ecomTag'] = is_array($ecomTags) && count($ecomTags) ? array_pop($ecomTags) : array();
                            } elseif ($_REQUEST['run'] == 'getNextInvoiceSequence') {
                                try {
                                    $nextInvoice = $this->flow->getNextInvoiceNumberByDebits(5);
                                    $responseArray['nextInvoice'] = $nextInvoice;
                                } catch (Exception $e) {
                                    $responseArray['nextInvoice'] = $e->getMessage() . ' [' . $e->getCode() . ']';
                                }
                            } elseif ($_REQUEST['run'] == 'getLastCallbackTimestamp') {
                                $lastRecv = get_transient('resurs_callbacks_received');
                                $myBool = true;
                                $responseArray['element'] = "lastCbRec";
                                if ($lastRecv > 0) {
                                    $responseArray['html'] = '<div style="margin-bottom:5px; margin-top: 5px;"><span id="receivedCallbackConfirm" class="labelBoot labelBoot-success">' . __('Test callback received',
                                            'resurs-bank-payment-gateway-for-woocommerce') . '</span></div>';
                                } else {
                                    $responseArray['html'] = __('Never', 'resurs-bank-payment-gateway-for-woocommerce');
                                }
                            } elseif ($_REQUEST['run'] == 'cleanRbSettings') {
                                $numDel = $wpdb->query("DELETE FROM " . $wpdb->options . " WHERE option_name LIKE '%resurs%bank%'");
                                $responseArray['deleteOptions'] = $numDel;
                                $responseArray['element'] = "process_cleanResursSettings";
                                if ($numDel > 0) {
                                    $myBool = true;
                                    $responseArray['html'] = "OK";
                                } else {
                                    $responseArray['html'] = "";
                                }
                            } elseif ($_REQUEST['run'] == 'cleanRbCache') {
                                try {
                                    $wpdb->query("DELETE FROM " . $wpdb->options . " WHERE option_name LIKE '%resursTemporary%'");
                                } catch (\Exception $dbException) {

                                }
                                $myBool = true;
                                $responseArray['html'] = "OK";
                                $responseArray['element'] = "process_cleanResursMethods";
                            } elseif ($_REQUEST['run'] == 'cleanRbMethods') {
                                $numDel = 0;
                                $numConfirm = 0;
                                try {
                                    $wpdb->query("DELETE FROM " . $wpdb->options . " WHERE option_name LIKE '%resursTemporaryPaymentMethods%'");
                                } catch (\Exception $dbException) {

                                }
                                // Make sure that the globs does not return anything else than an array.
                                $globIncludes = glob(plugin_dir_path(__FILE__) . 'includes/*.php');
                                if (is_array($globIncludes)) {
                                    foreach ($globIncludes as $filename) {
                                        @unlink($filename);
                                        $numDel++;
                                    }
                                    $globIncludes = glob(plugin_dir_path(__FILE__) . 'includes/*.php');
                                    if (is_array($globIncludes)) {
                                        foreach ($globIncludes as $filename) {
                                            $numConfirm++;
                                        }
                                    }
                                }
                                $responseArray['deleteFiles'] = 0;
                                $responseArray['element'] = "process_cleanResursMethods";
                                if ($numConfirm != $numDel) {
                                    $responseArray['deleteFiles'] = $numDel;
                                    $responseArray['html'] = "OK";
                                    $myBool = true;
                                } else {
                                    $responseArray['html'] = "";
                                }
                            } elseif ($_REQUEST['run'] == 'setNewPaymentFee') {
                                $responseArray['update'] = 0;
                                if (isset($_REQUEST['data']) && count($_REQUEST['data'])) {
                                    $paymentFeeData = $_REQUEST['data'];
                                    if (isset($paymentFeeData['feeId']) && isset($paymentFeeData['feeValue'])) {
                                        $feeId = preg_replace('/^[a-z0-9]$/i', '',
                                            $paymentFeeData['feeId']);
                                        $feeValue = doubleval($paymentFeeData['feeValue']);
                                        $methodNameSpace = "woocommerce_resurs_bank_nr_" . $feeId . "_settings";
                                        $responseArray['feeId'] = $feeId;
                                        $responseArray['oldValue'] = getResursOption('price', $methodNameSpace);
                                        $responseArray['update'] = setResursOption('price', $feeValue,
                                            $methodNameSpace) === true ? 1 : 0;
                                    }
                                }
                            }
                            $myResponse = array(
                                $_REQUEST['run'] . 'Response' => $responseArray
                            );
                        }
                    }
                }
                $response = array(
                    'response' => $myResponse,
                    'success' => $myBool,
                    'session' => $mySession === true ? 1 : 0,
                    'errorMessage' => nl2br($errorMessage)
                );
                $this->returnJsonResponse($response);
                exit;
            }
            if ($event_type === 'check_signing_response') {
                $this->check_signing_response();

                return;
            }
            if ($event_type === "prepare-omni-order") {
                $this->prepare_omni_order();

                return;
            }

            $orderId = wc_get_order_id_by_payment_id($request['paymentId']);
            $order = new WC_Order($orderId);

            $currentValidation = $this->validateCallback($request);
            // SKIP_DIGEST_VALIDATION is for test purposes only.
            if (empty($currentValidation) && !getResursFlag('SKIP_DIGEST_VALIDATION')) {
                $order->add_order_note(
                    sprintf(
                        __(
                            '[Resurs Bank] The event %s was rejected by the plugin when the digest was processed. The salt key may need to be updated, by re-registering the callbacks again.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $event_type
                    )
                );
                header('HTTP/1.1 406 Digest rejected by plugin', true, 406);
                echo '406: Callback digest validation failed, rejected by plugin';
                exit;
            }

            $currentValidationString = sprintf(
                __(
                    'By OrderID %s',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ), $currentValidation
            );

            if (getResursFlag('SKIP_DIGEST_VALIDATION')) {
                $order->add_order_note(
                    __('[Resurs Bank] Experimental setting SKIP_DIGEST_VALIDATION is active and therefore saltkey-digest validation is disabled on this callback.',
                        'resurs-bank-payment-gateway-for-woocommerce')
                );
            }

            $currentStatus = $order->get_status();

            $order->add_order_note(
                sprintf(
                    __(
                        '[Resurs Bank] The event %s received. Additional result flag: %s.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $event_type,
                    isset($request['result']) ? $request['result'] : __('No extra flags.',
                        'resurs-bank-payment-gateway-for-woocommerce')
                )
            );

            switch ($event_type) {
                case 'UNFREEZE':
                    update_post_meta($orderId, 'hasCallback' . $event_type, time());
                    $statusValue = $this->updateOrderByResursPaymentStatus(
                        $order,
                        $currentStatus,
                        $request['paymentId'],
                        RESURS_CALLBACK_TYPES::UNFREEZE
                    );
                    if (!(bool)$statusValue & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
                        $order->add_order_note(
                            sprintf(
                                __(
                                    '[Resurs Bank] The event %s updated the order to %s [%s].',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $event_type,
                                $this->getOrderStatusByResursReturnCode($statusValue),
                                $currentValidationString
                            )
                        );
                    }
                    ThirdPartyHooksSetPaymentTrigger("callback", $request['paymentId'], $orderId, $event_type);
                    break;
                case 'AUTOMATIC_FRAUD_CONTROL':
                    update_post_meta($orderId, 'hasCallback' . $event_type, time());
                    $statusValue = $this->updateOrderByResursPaymentStatus(
                        $order,
                        $currentStatus,
                        $request['paymentId'],
                        RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL,
                        $request['result']
                    );

                    switch ($request['result']) {
                        case 'THAWED':
                            $order->add_order_note(
                                sprintf(
                                    __(
                                        '[Resurs Bank] The event %s updated the order to %s by its value %s [%s].',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $event_type,
                                    $this->getOrderStatusByResursReturnCode($statusValue),
                                    $request['result'],
                                    $currentValidationString
                                )
                            );
                            ThirdPartyHooksSetPaymentTrigger("callback", $request['paymentId'], $orderId, $event_type);
                            break;
                        case 'FROZEN':
                            $order->add_order_note(
                                sprintf(
                                    __(
                                        '[Resurs Bank] The event %s updated the order to %s by its value %s [%s].',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $event_type,
                                    $this->getOrderStatusByResursReturnCode($statusValue),
                                    $request['result'],
                                    $currentValidationString
                                )
                            );
                            ThirdPartyHooksSetPaymentTrigger("callback", $request['paymentId'], $orderId, $event_type);
                            break;
                        default:
                            break;
                    }
                    break;
                case 'TEST':
                    break;
                case 'ANNULMENT':
                    update_post_meta($orderId, 'hasCallback' . $event_type, time());
                    update_post_meta($order->get_id(), 'hasAnnulment', 1);
                    $order->update_status('cancelled');

                    $order->add_order_note(
                        sprintf(
                            __(
                                '[Resurs Bank] The event %s cancelled the order.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ),
                            $event_type
                        )
                    );

                    // Not running suggestedMethod here as we have anoter procedure to cancel orders
                    ThirdPartyHooksSetPaymentTrigger("callback", $request['paymentId'], $orderId, $event_type);
                    break;
                case 'FINALIZATION':
                    update_post_meta($orderId, 'hasCallback' . $event_type, time());
                    $finalizationStatus = $this->updateOrderByResursPaymentStatus(
                        $order,
                        $currentStatus,
                        $request['paymentId'],
                        RESURS_CALLBACK_TYPES::FINALIZATION
                    );

                    if ($finalizationStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED)) {
                        if (!(bool)$finalizationStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
                            $order->add_order_note(
                                sprintf(
                                    __(
                                        '[Resurs Bank] The event %s received but the used payment method indicated that instant finalization is available for it. If it\'s not already completed you might have to update the order manually (%s) [%s].',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $event_type,
                                    $this->getOrderStatusByResursReturnCode($finalizationStatus),
                                    $currentValidationString
                                )
                            );
                        }
                    } else {
                        if (!(bool)$finalizationStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
                            $order->add_order_note(
                                sprintf(
                                    __(
                                        '[Resurs Bank] The event %s completed the order (%s) [%s].',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $event_type,
                                    $this->getOrderStatusByResursReturnCode($finalizationStatus),
                                    $currentValidationString
                                )
                            );
                        }
                        $order->payment_complete();
                    }

                    ThirdPartyHooksSetPaymentTrigger("callback", $request['paymentId'], $orderId, $event_type);
                    break;
                case 'BOOKED':
                    update_post_meta($orderId, 'hasCallback' . $event_type, time());
                    if ($currentStatus != "cancelled") {
                        $optionReduceOrderStock = getResursOption('reduceOrderStock');
                        $hasReduceStock = get_post_meta($orderId, 'hasReduceStock');
                        if ($optionReduceOrderStock && empty($hasReduceStock)) {
                            update_post_meta($orderId, 'hasReduceStock', time());
                            if (isWooCommerce3()) {
                                wc_reduce_stock_levels($order->get_id());
                            } else {
                                $order->reduce_order_stock();
                            }
                        }
                        $statusValue = $this->updateOrderByResursPaymentStatus(
                            $order,
                            $currentStatus,
                            $request['paymentId'],
                            RESURS_CALLBACK_TYPES::BOOKED
                        );

                        if (!(bool)$statusValue & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
                            $order->add_order_note(
                                sprintf(
                                    __(
                                        '[Resurs Bank] The event %s updated the order to %s [%s].',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ),
                                    $event_type,
                                    $this->getOrderStatusByResursReturnCode($statusValue),
                                    $currentValidationString
                                )
                            );
                        }

                        ThirdPartyHooksSetPaymentTrigger("callback", $request['paymentId'], $orderId, $event_type);
                    }
                    break;
                /*
				 * The below code belongs to the BOOKED event.
				 * In the future, injecting order lines as the BOOKED callback is running may be supported, but since
				 * WooCommerce itself offers a bunch of extra fees, we are currently excluding this, since we missing too much
				 * important values to inject a proper payment spec into woocommerce orderadmin. Besides, by only injecting data
				 * like this, may prevent other systems to catch summaries of a correct order.
				 */
                /*
				$dataPOST = null;
				if ($_SERVER['CONTENT_LENGTH'] > 0) {
					$dataPOST = @json_decode(trim(file_get_contents('php://input')));
				}
				if (isset($dataPOST->addedPaymentSpecificationLines)) {
					foreach ($dataPOST->addedPaymentSpecificationLines as $addedArticle) {
						// artNo, description, quantity, unitAmountWithoutVat, vatPct, totalVatAmount
						$item = array(
							'order_item_name' => $addedArticle->artNo,
							'order_item_type' => 'line_item'
						);
						$item_id = wc_add_order_item($orderId, $item);
						wc_add_order_item_meta( $item_id, '_qty', $addedArticle->quantity);
						wc_add_order_item_meta( $item_id, '_line_subtotal', $addedArticle->unitAmountWithoutVat*$addedArticle->quantity);
						wc_add_order_item_meta( $item_id, '_line_total', $addedArticle->unitAmountWithoutVat);
						wc_add_order_item_meta( $item_id, '_line_subtotal_tax', $addedArticle->totalVatAmount);
						wc_add_order_item_meta( $item_id, '_line_tax', $addedArticle->totalVatAmount);
						$tax_data             = array();
						$tax_data['total']    = wc_format_decimal($addedArticle->totalVatAmount);
						$tax_data['subtotal'] = wc_format_decimal($addedArticle->totalVatAmount);
						$postMeta = get_post_meta($orderId);
						$orderTotal = $postMeta['_order_total'][0] + $addedArticle->unitAmountWithoutVat + $addedArticle->totalVatAmount;
						wc_add_order_item_meta( $item_id, '_line_tax_data', $tax_data );
						update_post_meta( $orderId, '_order_total', $orderTotal);
					}
				}*/
                case 'UPDATE':
                    $callbackUpdateStatus = $this->updateOrderByResursPaymentStatus(
                        $order,
                        $currentStatus,
                        $request['paymentId'],
                        RESURS_CALLBACK_TYPES::CALLBACK_TYPE_UPDATE
                    );

                    if (!(bool)$callbackUpdateStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET) {
                        $order->add_order_note(
                            sprintf(
                                __(
                                    '[Resurs Bank] The event %s updated the order to %s [%s].',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ),
                                $event_type,
                                $this->getOrderStatusByResursReturnCode($callbackUpdateStatus),
                                $currentValidationString
                            )
                        );

                        if ($callbackUpdateStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED)) {
                            $order->add_order_note(
                                __(
                                    '[Resurs Bank] Additional Note: The order seem to be FINALIZED and the payment method this order uses, indicates that it supports instant finalization. If it\'s not already completed you might have to update the order manually.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                        }
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

            $testDigestArray = array($paymentId);

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
                    $result)
                ) {
                    $success = $testId;
                    break;
                }
            }

            return (string)$success;
        }

        /**
         * @param string $currentStatus
         * @param string $newStatus
         * @param WC_Order $woocommerceOrder
         * @param RESURS_PAYMENT_STATUS_RETURNCODES $suggestedStatusCode
         *
         * @return bool
         */
        private function synchronizeResursOrderStatus(
            $currentStatus,
            $newStatus,
            $woocommerceOrder,
            $suggestedStatusCode,
            $resursOrderObject = null
        ) {
            resursEventLogger("SynchronizeResursOrderStatus $currentStatus -> $newStatus");

            $updateStatus = true;
            if (empty($currentStatus) && empty($newStatus)) {
                resursEventLogger("One status is empty, so I won't touch it.");
                return false;
            }

            if ($currentStatus === $newStatus) {
                resursEventLogger('Changing status from $currentStatus to $newStatus is not necessary.');
            }

            $suggestedString = $this->flow->getOrderStatusStringByReturnCode($suggestedStatusCode);
            if (empty($suggestedString)) {
                $suggestedString = 'Suggested status code string could not be defined';
            }

            if ($updateStatus && $currentStatus !== $newStatus) {
                $woocommerceOrder->update_status($newStatus);

                if (
                    !is_null($resursOrderObject) &&
                    $suggestedStatusCode === RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET &&
                    $this->flow->isFrozen($resursOrderObject)) {
                    $suggestedString = __(
                        '[Resurs Bank] On-Hold: Detected frozen order',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    );
                }

                $woocommerceOrder->add_order_note(
                    sprintf(
                        __('[Resurs Bank] Updated order status (%s/%s).',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $suggestedString,
                        $suggestedStatusCode
                    )
                );

                return true;
            }

            $woocommerceOrder->add_order_note(
                sprintf(
                    __('[Resurs Bank] Update order request (%s/%s) skipped since the status is already set.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $suggestedString,
                    $suggestedStatusCode
                )
            );

            return false;
        }

        /**
         * @return array
         */
        public function getResursOrderStatusArray()
        {
            return array(
                RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING => 'processing',
                RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED => 'refunded',
                RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED => 'completed',
                RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING => 'on-hold',
                RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED => 'cancelled',
                RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET => 'on-hold',
            );
        }

        /**
         * @param $code
         * @return mixed|string
         */
        public function getOrderStatusByResursReturnCode($code)
        {
            $return = 'Unknown';
            $arrayList = $this->getResursOrderStatusArray();

            if (isset($arrayList[$code])) {
                $return = $arrayList[$code];
            }

            return $return;
        }

        /**
         * @param $woocommerceOrder
         * @param string $currentWcStatus
         * @param string $paymentIdOrPaymentObject
         * @param int $byCallbackEvent
         * @param array $callbackEventDataArrayOrString
         *
         * @return int|RESURS_PAYMENT_STATUS_RETURNCODES
         * @throws Exception
         */
        private function updateOrderByResursPaymentStatus(
            $woocommerceOrder,
            $currentWcStatus = '',
            $paymentIdOrPaymentObject = '',
            $byCallbackEvent = RESURS_CALLBACK_TYPES::NOT_SET,
            $callbackEventDataArrayOrString = array()
        ) {
            try {
                /** @var $suggestedStatus RESURS_PAYMENT_STATUS_RETURNCODES */
                $suggestedStatus = $this->flow->getOrderStatusByPayment(
                    $paymentIdOrPaymentObject,
                    $byCallbackEvent,
                    $callbackEventDataArrayOrString
                );

                // Developers and merchants should normally not need to touch this section unless they really know what they're doing.

                $paymentStatus = $this->getResursOrderStatusArray();

                resursEventLogger('Callback Event ' . $this->flow->getCallbackTypeString($byCallbackEvent) . '.');
                resursEventLogger(print_r($paymentIdOrPaymentObject, true));
                resursEventLogger('Current Status: ' . $currentWcStatus);
                if (isset($paymentStatus[$suggestedStatus])) {
                    resursEventLogger('Suggested status: ' . $suggestedStatus . ' (' . $paymentStatus[$suggestedStatus] . ')');
                } else {
                    resursEventLogger('Suggested status: ' . $suggestedStatus . ' (bitwise setup defines dynamically chosen status)');
                }

                resursEventLogger('Stored statuses listed.');
                resursEventLogger(print_r($paymentStatus, true));
                resursEventLogger('Callback EVENT Information End');

                $return = RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET;

                switch (true) {
                    case $suggestedStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING):
                        if ($this->synchronizeResursOrderStatus(
                            $currentWcStatus,
                            $paymentStatus[RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING],
                            $woocommerceOrder,
                            $suggestedStatus,
                            $paymentIdOrPaymentObject
                        )) {
                            $return = $suggestedStatus;
                        }

                        break;
                    case $suggestedStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED): // PAYMENT_REFUND
                        if ($this->synchronizeResursOrderStatus(
                            $currentWcStatus,
                            $paymentStatus[RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED],
                            $woocommerceOrder,
                            $suggestedStatus,
                            $paymentIdOrPaymentObject
                        )) {
                            $return = $suggestedStatus;
                        }
                        break;
                    case $suggestedStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED | RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED):

                        if ($suggestedStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED)) {
                            $autoDebitStatus = getResursOption('autoDebitStatus');
                            if ($autoDebitStatus === 'default' || empty($autoDebitStatus)) {
                                if ($this->synchronizeResursOrderStatus(
                                    $currentWcStatus,
                                    $paymentStatus[RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED],
                                    $woocommerceOrder,
                                    $suggestedStatus,
                                    $paymentIdOrPaymentObject
                                )) {
                                    $return = $suggestedStatus;
                                }
                            } else {
                                $woocommerceOrder->update_status($autoDebitStatus);
                            }
                        } else {
                            if ($this->synchronizeResursOrderStatus(
                                $currentWcStatus,
                                $paymentStatus[RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED],
                                $woocommerceOrder,
                                $suggestedStatus,
                                $paymentIdOrPaymentObject
                            )) {
                                $return = $suggestedStatus;
                            }
                        }

                        break;
                    case $suggestedStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING):
                        if ($this->synchronizeResursOrderStatus(
                            $currentWcStatus,
                            $paymentStatus[RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING],
                            $woocommerceOrder,
                            $suggestedStatus,
                            $paymentIdOrPaymentObject
                        )) {
                            $return = $suggestedStatus;
                        }

                        break;
                    case $suggestedStatus & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED): // PAYMENT_CANCELLED
                        $woocommerceOrder->update_status($paymentStatus[RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED]);
                        if (!isWooCommerce3()) {
                            $woocommerceOrder->cancel_order(__('Resurs Bank annulled the order',
                                'resurs-bank-payment-gateway-for-woocommerce'));
                        }

                        $return = $suggestedStatus;
                        break;
                    default:
                        break;
                }
            } catch (\Exception $e) {
                // Ignore errors
            }

            return $return;
        }

        /**
         * Register a callback event (EComPHP)
         *
         * @param  string $type The callback type to be registered
         * @param  array $options The parameters for the SOAP request
         *
         * @return bool|mixed|string
         * @throws Exception
         */
        public function register_callback($type, $options)
        {
            $uriTemplate = null;
            if (false === is_object($this->flow)) {
                /** @var \Resursbank\RBEcomPHP\ResursBank */
                $this->flow = initializeResursFlow();
            }
            try {
                $testTemplate = home_url('/');
                $useTemplate = $testTemplate;
                $customCallbackUri = resursOption("customCallbackUri");
                if (!empty($customCallbackUri) && $testTemplate != $customCallbackUri) {
                    $useTemplate = $customCallbackUri;
                }
                $uriTemplate = $useTemplate;
                $uriTemplate = add_query_arg('wc-api', 'WC_Resurs_Bank', $uriTemplate);
                $uriTemplate .= '&event-type=' . $type;
                foreach ($options['uri_components'] as $key => $value) {
                    $uriTemplate .= '&' . $key . '=' . '{' . $value . '}';
                }
                if ($type == "TEST") {
                    $uriTemplate .= '&thisRandomValue=' . rand(10000, 32000);
                } else {
                    $uriTemplate .= '&digest={digest}';
                }
                $uriTemplate .= '&env=' . getServerEnv();
                $uriTemplate .= '&ts=' . strftime("%y%m%d%H%M", time());
                $xDebugTest = getResursFlag('XDEBUG_SESSION_START');
                if (!empty($xDebugTest)) {
                    $uriTemplate .= "&XDEBUG_SESSION_START=" . $xDebugTest;
                }
                $callbackType = $this->flow->getCallbackTypeByString($type);
                $this->flow->setCallbackDigestSalt($this->getCurrentSalt());
                $this->flow->setRegisterCallback($callbackType, $uriTemplate);
            } catch (Exception $e) {
                throw new Exception($e);
            }

            return $uriTemplate;
        }

        /**
         * Get digest parameters for register callback
         *
         * @param  array $params The parameters
         *
         * @return array         The parameters reordered
         */
        public function get_digest_parameters($params)
        {
            $arr = array();
            foreach ($params as $key => $value) {
                $arr[] = $value;
            }

            return $arr;
        }


        /**
         * Initialize the web services through EComPHP-Simplified
         *
         * @param  string $username The username for the API, is fetched from options if not specified
         * @param  string $password The password for the API, is fetched from options if not specified
         *
         * @return boolean          Return whether or not the action succeeded
         */
        public function init_webservice($username = '', $password = '')
        {
            try {
                /** @var \Resursbank\RBEcomPHP\ResursBank */
                $this->flow = initializeResursFlow();
            } catch (Exception $initFlowException) {
                return false;
            }

            return true;
        }

        // Payment spec functions is a part of the bookPayment functions

        /**
         * Get specLines for initiated payment session
         *
         * @param WC_Cart $cart WooCommerce cart containing order items
         *
         * @return array       The specLines for startPaymentSession
         */
        protected static function get_spec_lines($cart)
        {
            $spec_lines = array();
            foreach ($cart as $item) {
                /** @var WC_Product $data */
                $data = $item['data'];
                /** @var WC_Tax $_tax */
                $_tax = new WC_Tax();  //looking for appropriate vat for specific product
                $rates = array();
                $taxClass = $data->get_tax_class();
                $ratesArray = $_tax->get_rates($taxClass);
                $rates = @array_shift($ratesArray);
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
                $optionUseSku = getResursOption("useSku");
                if ($optionUseSku && !empty($setSku)) {
                    $bookArtId = $setSku;
                }
                $artDescription = (empty($postTitle) ? __('Article description missing',
                    'resurs-bank-payment-gateway-for-woocommerce') : $postTitle);
                $spec_lines[] = array(
                    'id' => $bookArtId,
                    'artNo' => $bookArtId,
                    'description' => $artDescription,
                    'quantity' => $item['quantity'],
                    'unitMeasure' => '',
                    'unitAmountWithoutVat' => $priceExTax,
                    'vatPct' => $vatPct,
                    'totalVatAmount' => ($priceExTax * ($vatPct / 100)),
                    'totalAmount' => (($priceExTax * $item['quantity']) + ($totalVatAmount * $item['quantity'])),
                    'type' => 'ORDER_LINE'
                );
            }

            return $spec_lines;
        }

        /**
         * Get and convert payment spec from cart, convert it to Resurs Specrows
         *
         * @param WC_Cart $cart Order items
         * @param bool $specLinesOnly Return only the array of speclines
         *
         * @return array The paymentSpec for startPaymentSession
         * @throws Exception
         */
        protected static function get_payment_spec($cart, $specLinesOnly = false)
        {
            global $woocommerce;

            //$payment_fee_tax_pct = (float) getResursOption( 'pricePct' );
            /** @var WC_Cart $currentCart */
            $currentCart = $cart->get_cart();
            if (!count($currentCart)) {
                // If there is no articles in the cart, there's no use to add
                // shipping.
                return array();
            }
            $spec_lines = self::get_spec_lines($currentCart);
            $shipping = (float)$cart->shipping_total;
            $shipping_tax = (float)$cart->shipping_tax_total;
            $shipping_total = (float)($shipping + $shipping_tax);
            /*
             * Compatibility (Discovered in PHP7)
			 */
            $shipping_tax_pct = (
            !is_nan(
                @round(
                    $shipping_tax / $shipping,
                    2
                ) * 100
            ) ? @round($shipping_tax / $shipping, 2) * 100 : 0
            );

            $spec_lines[] = array(
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
            );
            $payment_method = $woocommerce->session->chosen_payment_method;
            $payment_fee = getResursOption('price', 'woocommerce_' . $payment_method . '_settings');
            $payment_fee = (float)(isset($payment_fee) ? $payment_fee : '0');
            $payment_fee_tax_class = getResursOption('priceTaxClass');
            if (!hasWooCommerce("2.3", ">=")) {
                $payment_fee_tax_class_rates = $cart->tax->get_rates($payment_fee_tax_class);
                $payment_fee_tax = $cart->tax->calc_tax($payment_fee, $payment_fee_tax_class_rates, false,
                    true);
            } else {
                // ->tax has been deprecated since WC 2.3
                $payment_fee_tax_class_rates = WC_Tax::get_rates($payment_fee_tax_class);
                $payment_fee_tax = WC_Tax::calc_tax($payment_fee, $payment_fee_tax_class_rates, false,
                    true);
            }

            $payment_fee_total_tax = 0;
            foreach ($payment_fee_tax as $value) {
                $payment_fee_total_tax = $payment_fee_total_tax + $value;
            }
            $tax_rates_pct_total = 0;
            foreach ($payment_fee_tax_class_rates as $key => $rate) {
                $tax_rates_pct_total = $tax_rates_pct_total + (float)$rate['rate'];
            }

            $ResursFeeName = "";
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
                        $spec_lines[] = array(
                            'id' => $fee->id,
                            'artNo' => $fee->id,
                            'description' => $fee->name,
                            'quantity' => 1,
                            'unitMeasure' => '',
                            'unitAmountWithoutVat' => $fee->amount,
                            'vatPct' => !is_nan($rate) ? $rate : 0,
                            'totalVatAmount' => $fee->tax,
                            'totalAmount' => $fee->amount + $fee->tax,
                        );
                    }
                }
            }
            if ($cart->coupons_enabled()) {
                $coupons = $cart->get_coupons();
                if (is_array($coupons) && count($coupons) > 0) {
                    // TODO: Deprecated variables
                    $coupon_values = $cart->coupon_discount_amounts;
                    $coupon_tax_values = $cart->coupon_discount_tax_amounts;

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
                            $couponDescription = $couponCode . '_' . __('coupon',
                                    'resurs-bank-payment-gateway-for-woocommerce');
                        }
                        $spec_lines[] = array(
                            'id' => $couponId,
                            'artNo' => $couponCode . '_' . 'kupong',
                            'description' => $couponDescription,
                            'quantity' => 1,
                            'unitMeasure' => '',
                            'unitAmountWithoutVat' => (0 - (float)$coupon_values[$code]) + (0 - (float)$coupon_tax_values[$code]),
                            'vatPct' => 0,
                            'totalVatAmount' => 0,
                            'totalAmount' => (0 - (float)$coupon_values[$code]) + (0 - (float)$coupon_tax_values[$code]),
                            'type' => 'DISCOUNT',
                        );
                    }
                }
            }
            $ourPaymentSpecCalc = self::calculateSpecLineAmount($spec_lines);
            if (!$specLinesOnly) {
                $payment_spec = array(
                    'specLines' => $spec_lines,
                    'totalAmount' => $ourPaymentSpecCalc['totalAmount'],
                    'totalVatAmount' => $ourPaymentSpecCalc['totalVatAmount'],
                );
            } else {
                return $spec_lines;
            }

            return $payment_spec;
        }

        /**
         * @param array $specLine
         *
         * @return array
         */
        protected static function calculateSpecLineAmount($specLine = array())
        {
            $setPaymentSpec = array('totalAmount' => 0, 'totalVatAmount' => 0); // defaults
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
            $return = array();

            preg_match_all("/(.*?)\&/", $dataContent, $extraction);
            if (isset($extraction[1])) {
                foreach ($extraction[1] as $postDataVars) {
                    $exVars = explode("=", $postDataVars, 2);
                    $return[$exVars[0]] = $exVars[1];
                }
            }

            return $return;
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
            $labels = array(
                'contact-government-id' => __('Contact government id', 'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-government-id' => __('Applicant government ID',
                    'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-full-name' => __('Applicant full name', 'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-email-address' => __('Applicant email address',
                    'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-telephone-number' => __('Applicant telephone number',
                    'resurs-bank-payment-gateway-for-woocommerce'),
                'applicant-mobile-number' => __('Applicant mobile number',
                    'resurs-bank-payment-gateway-for-woocommerce'),
                'card-number' => __('Card number', 'resurs-bank-payment-gateway-for-woocommerce'),
            );
            $labelsLegal = array(
                'applicant-government-id' => __('Company government ID', 'resurs-bank-payment-gateway-for-woocommerce'),
            );

            $setLabel = $labels[$fieldName];
            if (isset($labelsLegal[$fieldName]) && !empty($labelsLegal[$fieldName]) && $customerType != "NATURAL") {
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
            $post_data = isset($_REQUEST['post_data']) ? $this->splitPostData($_REQUEST['post_data']) : array();
            // Get the read more from internal translation if not set
            $read_more = (!empty($translation) && isset($translation['read_more']) && !empty($translation['read_more'])) ? $translation['read_more'] : __('Read more',
                'resurs-bank-payment-gateway-for-woocommerce');

            $id = $method->id;
            $type = $method->type;
            $specificType = $method->specificType;

            if (!isset($_REQUEST['ssnCustomerType'])) {
                $_REQUEST['ssnCustomerType'] = "NATURAL";
            }
            if (isset($post_data['ssnCustomerType'])) {
                $_REQUEST['ssnCustomerType'] = $post_data['ssnCustomerType'];
            }

            $customerType = in_array($_REQUEST['ssnCustomerType'],
                (array)$method->customerType) ? $_REQUEST['ssnCustomerType'] : 'NATURAL';
            if ($type === 'PAYMENT_PROVIDER') {
                $requiredFormFields = $this->flow->getTemplateFieldsByMethodType($method, $customerType,
                    'PAYMENT_PROVIDER');
            } else {
                $requiredFormFields = $this->flow->getTemplateFieldsByMethodType($method, $customerType, $specificType);
            }


            if ($this->getMinMax($paymentSpec['totalAmount'], $method->minLimit, $method->maxLimit)) {
                $buttonCssClasses = "btn btn-info active";
                $ajaxUrl = admin_url('admin-ajax.php');

                // SIMPLIFIED
                if (!isResursHosted()) {
                    $fieldGenHtml .= '<div>' . $method_class->description . '</div>';
                    foreach ($requiredFormFields['fields'] as $fieldName) {
                        $doDisplay = 'block';
                        $streamLineBehaviour = getResursOption("streamlineBehaviour");
                        if ($streamLineBehaviour) {
                            if ($this->flow->canHideFormField($fieldName)) {
                                $doDisplay = 'none';
                            }
                            // When applicant government id and getAddress is enabled so that data can be collected
                            // from that point, the requrest field is not necessary to be shown
                            if ($fieldName == 'applicant-government-id') {
                                $optionGetAddress = getResursOption("getAddress");
                                if ($optionGetAddress) {
                                    $doDisplay = "none";
                                }
                            }
                        }

                        $setLabel = $this->get_payment_method_form_label($fieldName, $customerType);
                        $fieldGenHtml .= '<div style="display:' . $doDisplay . ';width:100%;" class="resurs_bank_payment_field_container">';
                        $fieldGenHtml .= '<label for="' . $fieldName . '" style="width:100%;display:block;">' . $setLabel . '</label>';
                        $fieldGenHtml .= '<input onkeyup="rbFormChange(\'' . $fieldName . '\', this)" id="' . $fieldName . '" type="text" name="' . $fieldName . '">';
                        $fieldGenHtml .= '</div>';
                    }

                    /*
                     * MarGul Change
                     * Use translations for the Read More Button. Also added a fixed width and height on the onClick button.
                     */
                    if (class_exists("CountryHandler")) {
                        $translation = CountryHandler::getDictionary();
                    } else {
                        $translation = array();
                    }
                    $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
                    if ($specificType != "CARD" && $type != 'PAYMENT_PROVIDER') {
                        $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' . __($read_more,
                                'resurs-bank-payment-gateway-for-woocommerce') . '</button>';
                    }
                    $fieldGenHtml .= '<input type="hidden" value="' . $id . '" class="resurs-bank-payment-method">';
                } else {
                    // HOSTED
                    $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
                    $fieldGenHtml = $this->description . "<br><br>";
                    if ($specificType != "CARD") {
                        $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px\')">' . __($read_more,
                                'resurs-bank-payment-gateway-for-woocommerce') . '</button>';
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
            $return = false;
            if ($totalAmount >= $min && $totalAmount <= $max) {
                $return = true;
            }

            return $return;
        }


        /**
         * Function formerly known as the forms session, where forms was created from a response from Resurs.
         * From now on, we won't get any returned values from this function. Instead, we'll create the form at this
         * level.
         *
         * @param  int $payment_id The chosen payment method
         * @param null $method_class
         *
         * @throws Exception
         */
        public function start_payment_session($payment_id, $method_class = null)
        {
            global $woocommerce;
            $this->flow = initializeResursFlow();
            $currentCountry = getResursOption('country');
            $minMaxError = null;
            $methodList = null;
            $fieldGenHtml = null;

            $cart = $woocommerce->cart;
            $paymentSpec = $this->get_payment_spec($cart);
            $sessionHasErrors = false;

            $resursTemporaryPaymentMethodsTime = get_transient("resursTemporaryPaymentMethodsTime");
            $timeDiff = time() - $resursTemporaryPaymentMethodsTime;

            $countryCredentialArray = array();
            $hasCountry = false;
            if (isResursDemo() && isset($_SESSION['rb_country']) && class_exists("CountryHandler")) {
                if (isset($_SESSION['rb_country'])) {
                    $methodList = get_transient('resursMethods' . $_SESSION['rb_country']);
                    $hasCountry = true;
                }
            }
            if (!$hasCountry) {
                try {
                    if ($timeDiff >= 3600) {
                        $methodList = $this->flow->getPaymentMethods(array(), true);
                        set_transient("resursTemporaryPaymentMethodsTime", time());
                        set_transient("resursTemporaryPaymentMethods", serialize($methodList));
                    } else {
                        $methodList = unserialize(get_transient("resursTemporaryPaymentMethods"));
                        // When transient fetching fails.
                        if (!is_array($methodList) || (is_array($methodList) && !count($methodList))) {
                            $methodList = $this->flow->getPaymentMethods(array(), true);
                            set_transient("resursTemporaryPaymentMethods", serialize($methodList));
                            set_transient("resursTemporaryPaymentMethodsTime", time());
                        }
                    }
                } catch (Exception $e) {
                    $sessionHasErrors = true;
                    $sessionErrorMessage = $e->getMessage();
                }
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
            }
            if (!empty($fieldGenHtml)) {
                echo $fieldGenHtml;
            }
        }

        /**
         * @param $order_id
         *
         * @since 2.2.7
         * @return string
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
        function process_payment_prepare_customer()
        {
            $this->flow->setBillingAddress($_REQUEST['billing_last_name'] . ' ' . $_REQUEST['billing_first_name'],
                $_REQUEST['billing_first_name'], $_REQUEST['billing_last_name'], $_REQUEST['billing_address_1'],
                (empty($_REQUEST['billing_address_2']) ? '' : $_REQUEST['billing_address_2']),
                $_REQUEST['billing_city'], $_REQUEST['billing_postcode'], $_REQUEST['billing_country']);
            if (isset($_REQUEST['ship_to_different_address'])) {
                $this->flow->setDeliveryAddress($_REQUEST['shipping_last_name'] . ' ' . $_REQUEST['shipping_first_name'],
                    $_REQUEST['shipping_first_name'], $_REQUEST['shipping_last_name'], $_REQUEST['shipping_address_1'],
                    (empty($_REQUEST['shipping_address_2']) ? '' : $_REQUEST['shipping_address_2']),
                    $_REQUEST['shipping_city'], $_REQUEST['shipping_postcode'], $_REQUEST['shipping_country']);
            }
        }

        /**
         * @param $order_id
         *
         * @return string
         * @since 2.2.7
         */
        function process_payment_get_payment_id($order_id)
        {
            if (getResursOption("postidreference")) {
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
        function process_payment_get_backurl($order)
        {
            $backurl = html_entity_decode($order->get_cancel_order_url());
            if (isResursHosted()) {
                $backurl .= "&isBack=1";
            } else {
                $backurl .= "&isSimplified=1";
            }

            return $backurl;
        }

        /**
         * @param $paymentMethodData
         *
         * @return string
         * @since 2.2.7
         */
        function process_payment_get_customer_type($paymentMethodData)
        {
            $useCustomerType = "";
            if (!is_array($paymentMethodData->customerType)) {
                if ($paymentMethodData->customerType == "NATURAL") {
                    $useCustomerType = "NATURAL";
                } elseif ($paymentMethodData->customerType == "LEGAL") {
                    $useCustomerType = "LEGAL";
                }
            } else {
                $useCustomerType = "NATURAL";
            }

            return $useCustomerType;
        }

        /**
         * @param $paymentMethodInformation
         *
         * @since 2.2.7
         */
        function process_payment_set_card_info($paymentMethodInformation)
        {
            if (isset($paymentMethodInformation->specificType) && ($paymentMethodInformation->specificType == "REVOLVING_CREDIT" || $paymentMethodInformation->specificType == "CARD")) {
                if ($paymentMethodInformation->specificType == "REVOLVING_CREDIT") {
                    $this->flow->setCardData();
                } else {
                    if (isset($_REQUEST['card-number'])) {
                        $this->flow->setCardData($_REQUEST['card-number']);
                    }
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
        function process_payment_hosted(
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

            if ($paymentMethodInformation->type == "PAYMENT_PROVIDER" && !$supportProviderMethods) {
                wc_add_notice(__('The payment method is not available for the selected payment flow',
                    'resurs-bank-payment-gateway-for-woocommerce'), 'error');

                return;
            } else {
                try {
                    // Going payload-arrays in ECOMPHP is deprecated so we'll do it right
                    $hostedFlowUrl = $this->flow->createPayment($shortMethodName, $bookDataArray);
                } catch (\Exception $hostedException) {
                    $hostedFlowBookingFailure = true;
                    wc_add_notice($hostedException->getMessage(), 'error');
                }
            }

            if (!$hostedFlowBookingFailure && !empty($hostedFlowUrl)) {
                $order->update_status('pending');
                update_post_meta($order_id, 'paymentId', $preferredId);

                return array(
                    'result' => 'success',
                    'redirect' => $hostedFlowUrl
                );
            } else {
                $order->update_status('failed',
                    __('An error occured during the update of the booked payment (hostedFlow) - the payment id which was never received properly',
                        'resurs-bank-payment-gateway-for-woocommerce'));

                return array(
                    'result' => 'failure',
                    'redirect' => $urlFail
                );
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
        function process_payment_simplified(
            $order_id,
            $shortMethodName,
            $paymentMethodInformation,
            $supportProviderMethods,
            $bookDataArray,
            $order
        ) {
            if ($paymentMethodInformation->type == "PAYMENT_PROVIDER" && !$supportProviderMethods) {
                wc_add_notice(__('The payment method is not available for the selected payment flow',
                    'resurs-bank-payment-gateway-for-woocommerce'), 'error');

                return;
            } else {
                $storeId = apply_filters("resursbank_set_storeid", null);
                if (!empty($storeId)) {
                    $this->flow->setStoreId($storeId);
                    update_post_meta($order_id, 'resursStoreId', $storeId);
                }

                $customerId = getResursWooCustomerId($order);
                if (!is_null($customerId)) {
                    $this->flow->setMetaData('CustomerId', $customerId);
                }

                // If woocommerce forms do offer phone and email, while our own don't, use them (moved to the section of setCustomer)
                $bookPaymentResult = $this->flow->createPayment($shortMethodName, $bookDataArray);
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
        function process_payment_handle_payment_result($order, $order_id, $bookPaymentResult, $preferredId)
        {
            $bookedStatus = trim(isset($bookPaymentResult->bookPaymentStatus) ? $bookPaymentResult->bookPaymentStatus : null);
            $bookedPaymentId = isset($bookPaymentResult->paymentId) ? $bookPaymentResult->paymentId : null;
            if (empty($bookedPaymentId)) {
                $bookedStatus = "FAILED";
            } else {
                update_post_meta($order_id, 'paymentId', $bookedPaymentId);
            }

            $return = array();

            switch ($bookedStatus) {
                case 'FINALIZED':
                    define('RB_SYNCHRONOUS_MODE', true);
                    WC()->session->set("order_awaiting_payment", true);
                    //$order->update_status( 'completed' );
                    try {
                        $order->set_status('completed',
                            __('Order is debited and completed', 'resurs-bank-payment-gateway-for-woocommerce'),
                            true);
                        $order->save();
                    } catch (\Exception $e) {
                        wc_add_notice($e->getMessage(), 'error');

                        return;
                    }
                    WC()->cart->empty_cart();

                    $return = array('result' => 'success', 'redirect' => $this->get_return_url($order));
                    break;
                case 'BOOKED':
                    $order->update_status('processing');
                    $optionReduceOrderStock = getResursOption('reduceOrderStock');
                    $hasReduceStock = get_post_meta($order_id, 'hasReduceStock');
                    if ($optionReduceOrderStock && empty($hasReduceStock)) {
                        update_post_meta($order_id, 'hasReduceStock', time());
                        if (isWooCommerce3()) {
                            wc_reduce_stock_levels($order_id);
                        } else {
                            $order->reduce_order_stock();
                        }
                    }
                    WC()->cart->empty_cart();

                    $return = array('result' => 'success', 'redirect' => $this->get_return_url($order));
                    break;
                case 'FROZEN':
                    $order->update_status('on-hold');
                    WC()->cart->empty_cart();

                    $return = array('result' => 'success', 'redirect' => $this->get_return_url($order));
                    break;
                case 'SIGNING':
                    $signingUrl = isset($bookPaymentResult->signingUrl) ? $bookPaymentResult->signingUrl : null;
                    if (!is_null($signingUrl)) {
                        return array(
                            'result' => 'success',
                            'redirect' => $signingUrl
                        );
                    }
                    $order->update_status('failed');
                    wc_add_notice(__('Payment can not complete. A problem with the signing url occurred. Contact customer services for more information.',
                        'resurs-bank-payment-gateway-for-woocommerce'), 'error');
                    break;
                case 'DENIED':
                    $order->update_status('failed');
                    update_post_meta($order_id, 'orderDenied', true);
                    wc_add_notice(__('The payment can not complete. Contact customer services for more information.',
                        'resurs-bank-payment-gateway-for-woocommerce'), 'error');

                    break;
                case 'FAILED':
                    $order->update_status('failed',
                        __('An error occured during the update of the booked payment. The payment ID was never received properly in the payment process',
                            'resurs-bank-payment-gateway-for-woocommerce'));
                    wc_add_notice(__('An unknown error occured. Please, try again later',
                        'resurs-bank-payment-gateway-for-woocommerce'),
                        'error');

                    break;
                default:
                    wc_add_notice(__('An unknown error occured. Please, try again later',
                        'resurs-bank-payment-gateway-for-woocommerce'),
                        'error');

                    break;
            }

            return $return;
        }

        /**
         * Proccess the payment
         *
         * @param  int $order_id WooCommerce order ID
         *
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
            $bookDataArray = array();
            $className = isset($_REQUEST['payment_method']) ? $_REQUEST['payment_method'] : null;
            $shortMethodName = str_replace('resurs_bank_nr_', '', $className);
            $paymentMethodInformation = $this->getTransientMethod($shortMethodName);
            /** @var \Resursbank\RBEcomPHP\ResursBank */
            $this->flow = initializeResursFlow();
            $this->process_payment_prepare_customer();
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
            $this->flow->setWaitForFraudControl(resursOption('waitForFraudControl'));
            $this->flow->setAnnulIfFrozen(resursOption('annulIfFrozen'));
            $this->flow->setFinalizeIfBooked(resursOption('finalizeIfBooked'));
            $this->flow->setPreferredId($preferredId);
            $cart = $woocommerce->cart;
            // TODO: Old style payment spec generator should be fixed
            $paymentSpec = $this->get_payment_spec($cart, true);
            $bookDataArray['specLine'] = $paymentSpec;

            $fetchedGovernmentId = (isset($_REQUEST['applicant-government-id']) ? trim($_REQUEST['applicant-government-id']) : "");
            if (empty($fetchedGovernmentId) && isset($_REQUEST['ssn_field']) && !empty($_REQUEST['ssn_field'])) {
                $fetchedGovernmentId = $_REQUEST['ssn_field'];
                $_REQUEST['applicant-government-id'] = $fetchedGovernmentId;
            }
            $ssnCustomerType = (isset($_REQUEST['ssnCustomerType']) ? trim($_REQUEST['ssnCustomerType']) : $this->process_payment_get_customer_type($paymentMethodInformation));
            if ($ssnCustomerType === 'LEGAL' && $paymentMethodInformation->type === 'PAYMENT_PROVIDER') {
                $fetchedGovernmentId = null;
            }

            // Special cases
            // * If applicant phone is missing, try use billing phone instead
            // * If applicant mail is missing, try use billing email instead
            $this->flow->setCustomer(
                $fetchedGovernmentId,
                (isset($_REQUEST['applicant-telephone-number']) ? trim($_REQUEST['applicant-telephone-number']) : (isset($_REQUEST['billing_phone']) ? trim($_REQUEST['billing_phone']) : "")),
                (isset($_REQUEST['applicant-mobile-number']) && !empty($_REQUEST['applicant-mobile-number']) ? trim($_REQUEST['applicant-mobile-number']) : null),
                (isset($_REQUEST['applicant-email-address']) ? trim($_REQUEST['applicant-email-address']) : (isset($_REQUEST['billing_email']) ? trim($_REQUEST['billing_email']) : "")),
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
                wc_add_notice(__($bookPaymentException->getMessage(), 'resurs-bank-payment-gateway-for-woocommerce'),
                    'error');

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
         * @throws \Exception
         */
        public function getTransientMethod($methodId = '')
        {
            //$methodList = get_transient('resurs_bank_payment_methods');
            if (empty($this->flow)) {
                /** @var \Resursbank\RBEcomPHP\ResursBank */
                $this->flow = initializeResursFlow();
            }
            $methodList = $this->flow->getPaymentMethods(array(), true);
            if (is_array($methodList)) {
                foreach ($methodList as $methodArray) {
                    if (strtolower($methodArray->id) == strtolower($methodId)) {
                        return $methodArray;
                    }
                }
            }

            return array();
        }

        /**
         * @param $error
         *
         * @return mixed
         */
        public function error_prepare_omni_order($error)
        {
            return $error;
        }

        /**
         * Secure update of correct orderlines (when payment reference updates are activated).
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
            } catch (\Exception $e) {
                $returnResult['success'] = false;
                $code = $e->getCode();
                if (!intval($code)) {
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
         * Prepare the order for the checkout
         */
        public function prepare_omni_order()
        {
            /** @var WC_Checkout $resursOrder What will be created if successful, and what will report undefined variable if unsuccessful */
            $resursOrder = null;
            $updatePaymentReference = false;

            // Get incoming request
            $url_arr = parse_url($_SERVER["REQUEST_URI"]);
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
            $customerData = isset($_POST['customerData']) && is_array($_POST['customerData']) ? $_POST['customerData'] : array();

            /*
             * Get, if exists, the payment method and use it
             */
            $omniPaymentMethod = isset($_REQUEST['paymentMethod']) && !empty($_REQUEST['paymentMethod']) ? $_REQUEST['paymentMethod'] : "resurs_bank_omnicheckout";

            $errorString = "";
            $errorCode = "";
            // Default json data response
            $returnResult = array(
                'success' => false,
                'errorString' => "",
                'errorCode' => "",
                'verified' => false,
                'hasOrder' => false,
                'resursData' => array()
            );

            $returnResult['resursData']['reqId'] = $requestedPaymentId;
            $returnResult['resursData']['reqLocId'] = $requestedUpdateOrder;
            $returnResult['success'] = false;

            $flow = initializeResursFlow();
            $paymentSpec = self::get_payment_spec(WC()->cart);
            if (is_array($paymentSpec['specLines'])) {
                $returnResult = $this->updateOrderLines($requestedPaymentId, $paymentSpec, $returnResult, $flow);
            }

            if (isset($_REQUEST['updateReference'])) {
                if (isset($_REQUEST['omnicheckout_nonce'])) {
                    if (wp_verify_nonce($_REQUEST['omnicheckout_nonce'], "omnicheckout")) {
                        if (isset($_REQUEST['orderRef']) && isset($_REQUEST['orderId'])) {
                            try {
                                $flow->updatePaymentReference($_REQUEST['orderRef'], $_REQUEST['orderId']);
                                update_post_meta($_REQUEST['orderId'], 'paymentId', $_REQUEST['orderId']);
                                update_post_meta($_REQUEST['orderId'], 'paymentIdLast', $_REQUEST['orderRef']);
                                update_post_meta($_REQUEST['orderId'], 'referenceWasUpdated', true);
                                $returnResult['success'] = true;
                                $this->returnJsonResponse($returnResult, 200);
                            } catch (\Exception $e) {
                                update_post_meta($_REQUEST['orderId'], 'referenceWasUpdated', false);
                                $returnResult['success'] = false;
                                $returnResult['errorString'] = $e->getMessage();
                                $returnResult['errorCode'] = 500;
                                $this->returnJsonResponse($returnResult, $returnResult['errorCode']);

                            }
                        } else {
                            $returnResult['success'] = false;
                            $returnResult['errorString'] = "Order reference or orderId not set";
                            $returnResult['errorCode'] = 404;
                            $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                        }
                        die;
                    }
                }
            }

            if (!is_array($customerData)) {
                $customerData = array();
            }
            if (!count($customerData)) {
                $returnResult['errorString'] = "No customer data set";
                $returnResult['errorCode'] = "404";
                $this->returnJsonResponse($returnResult);
            }

            $responseCode = 0;
            $allowOrderCreation = false;

            // Without the nonce, no background order can prepare
            if (isset($_REQUEST['omnicheckout_nonce'])) {
                // Debugging only.
                $debugWithoutNonceProblems = false;
                if (wp_verify_nonce($_REQUEST['omnicheckout_nonce'], "omnicheckout") || $debugWithoutNonceProblems) {
                    $hasInternalErrors = false;
                    $returnResult['verified'] = true;

                    // This procedure normally works.
                    $testLocalOrder = wc_get_order_id_by_payment_id($requestedPaymentId);
                    if ((empty($testLocalOrder) && $requestedUpdateOrder) || (!is_numeric($testLocalOrder) && is_numeric($testLocalOrder) && $testLocalOrder != $requestedUpdateOrder)) {
                        $testLocalOrder = $requestedUpdateOrder;
                    }

                    $returnResult['resursData']['locId'] = $requestedPaymentId;

                    // If the order has already been created, the user may have been clicking more than one time in the frame, eventually due to payment method changes.
                    $wooBillingAddress = array();
                    $wooDeliveryAddress = array();
                    $resursBillingAddress = isset($customerData['address']) && is_array($customerData['address']) ? $customerData['address'] : array();
                    $resursDeliveryAddress = isset($customerData['delivery']) && is_array($customerData['delivery']) ? $customerData['delivery'] : array();
                    $failBilling = true;
                    $customerEmail = !empty($resursBillingAddress['email']) ? $resursBillingAddress['email'] : "";
                    if (count($resursBillingAddress)) {
                        $wooBillingAddress = array(
                            'first_name' => !empty($resursBillingAddress['firstname']) ? $resursBillingAddress['firstname'] : "",
                            'last_name' => !empty($resursBillingAddress['surname']) ? $resursBillingAddress['surname'] : "",
                            'address_1' => !empty($resursBillingAddress['address']) ? $resursBillingAddress['address'] : "",
                            'address_2' => !empty($resursBillingAddress['addressExtra']) ? $resursBillingAddress['addressExtra'] : "",
                            'city' => !empty($resursBillingAddress['city']) ? $resursBillingAddress['city'] : "",
                            'postcode' => !empty($resursBillingAddress['postal']) ? $resursBillingAddress['postal'] : "",
                            'country' => !empty($resursBillingAddress['countryCode']) ? $resursBillingAddress['countryCode'] : "",
                            'email' => !empty($resursBillingAddress['email']) ? $resursBillingAddress['email'] : "",
                            'phone' => !empty($resursBillingAddress['telephone']) ? $resursBillingAddress['telephone'] : "",
                        );
                        $failBilling = false;
                    }
                    if ($failBilling) {
                        $returnResult['errorString'] = "Billing address update failed";
                        $returnResult['errorCode'] = "404";
                        $this->returnJsonResponse($returnResult, $returnResult['errorCode']);
                    }
                    if (count($resursDeliveryAddress)) {
                        $_POST['ship_to_different_address'] = true;
                        $wooDeliveryAddress = array(
                            'first_name' => !empty($resursDeliveryAddress['firstname']) ? $resursDeliveryAddress['firstname'] : "",
                            'last_name' => !empty($resursDeliveryAddress['surname']) ? $resursDeliveryAddress['surname'] : "",
                            'address_1' => !empty($resursDeliveryAddress['address']) ? $resursDeliveryAddress['address'] : "",
                            'address_2' => !empty($resursDeliveryAddress['addressExtra']) ? $resursDeliveryAddress['addressExtra'] : "",
                            'city' => !empty($resursDeliveryAddress['city']) ? $resursDeliveryAddress['city'] : "",
                            'postcode' => !empty($resursDeliveryAddress['postal']) ? $resursDeliveryAddress['postal'] : "",
                            'country' => !empty($resursDeliveryAddress['countryCode']) ? $resursDeliveryAddress['countryCode'] : "",
                            'email' => !empty($resursDeliveryAddress['email']) ? $resursDeliveryAddress['email'] : "",
                            'phone' => !empty($resursDeliveryAddress['telephone']) ? $resursDeliveryAddress['telephone'] : "",
                        );
                    } else {
                        // Helper for "sameAddress"-cases.
                        $_POST['ship_to_different_address'] = false;
                        $wooDeliveryAddress = $wooBillingAddress;
                    }

                    define('OMNICHECKOUT_PROCESSPAYMENT', true);
                    if (!$testLocalOrder) {
                        /*
                         * WooCommerce POST-helper. Since we force removal of required fields in woocommerce, we need to help wooCommerce
                         * adding the correct fields at this level to possibly pass through the internal field validation.
                         */
                        foreach ($wooBillingAddress as $billingKey => $billingValue) {
                            if (!isset($_POST[$billingKey])) {
                                $_POST["billing_" . $billingKey] = $billingValue;
                                $_REQUEST["billing_" . $billingKey] = $billingValue;
                            }
                        }
                        foreach ($wooDeliveryAddress as $deliveryKey => $deliveryValue) {
                            if (!isset($_POST[$deliveryKey])) {
                                $_POST["shipping_" . $deliveryKey] = $deliveryValue;
                                $_REQUEST["shipping_" . $deliveryKey] = $deliveryValue;
                            }
                        }
                        $resursOrder = new WC_Checkout();
                        try {
                            // As we work with the session, we'd try to get the current order that way.
                            // process_checkout() does a lot of background work for this.

                            $internalErrorMessage = "";
                            $internalErrorCode = 0;
                            try {
                                $resursOrder->process_checkout();
                                $wcNotices = wc_get_notices();
                                if (isset($wcNotices['error'])) {
                                    $hasInternalErrors = true;
                                    $internalErrorMessage = implode("<br>\n", $wcNotices['error']);
                                    $internalErrorCode = 200;
                                    $returnResult['success'] = false;
                                    $returnResult['errorString'] = !empty($internalErrorMessage) ? $internalErrorMessage : "OrderId missing";
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
                                $orderId = WC()->session->get("order_awaiting_payment");
                                $order = new WC_Order($orderId);
                            } catch (Exception $e) {
                                $hasInternalErrors = true;
                                $internalErrorMessage = $e->getMessage();
                                $internalErrorCode = $e->getCode();
                            }
                            WC()->session->set('omniId', $orderId);
                            $returnResult['orderId'] = $orderId;
                            $returnResult['session'] = WC()->session;
                            $returnResult['hasInternalErrors'] = $hasInternalErrors;
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
                            } else {
                                $returnResult['success'] = false;
                                $returnResult['errorString'] = !empty($internalErrorMessage) ? $internalErrorMessage : "OrderId missing";
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
                        WC()->session->set("resursCreatePass", "1");
                    } else {
                        // If the order already exists, continue without errors (if we reached this code, it has been because of the nonce which should be considered safe enough)
                        $order = new WC_Order($testLocalOrder);
                        $currentOrderStatus = $order->get_status();
                        // Going generic response, to make it possible to updateOrderReference on fly
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
                            //$returnResult['success'] = false;
                            //$returnResult['errorCode'] = 499;
                        }

                        $updatePaymentReferenceStatus = null;
                        if (getResursOption("postidreference")) {
                            if (!empty($requestedPaymentId) && !empty($requestedUpdateOrder)) {
                                // Blindly try this once again.
                                try {
                                    $updatePaymentReferenceStatus = $flow->updatePaymentReference(
                                        $requestedPaymentId,
                                        $requestedUpdateOrder
                                    );
                                    $order->add_order_note(
                                        __(
                                            '[Resurs Bank] Rerunning updatePaymentReference got a successful update.',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        )
                                    );
                                } catch (\Exception $e) {
                                    if ($e->getCode() == 404) {
                                        //$returnResult['errorCode'] = 200;
                                        $updatePaymentReferenceStatus = "4xx ()" . $e->getCode() . " indicates already updated.";
                                    } else {
                                        $updatePaymentReferenceStatus = $e->getMessage();
                                    }
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

                        $responseCode = $returnResult['errorCode'];
                        $order->set_address($wooBillingAddress, 'billing');
                        $order->set_address($wooDeliveryAddress, 'shipping');
                        $order->save();
                        $returnResult['hasOrder'] = true;
                        $returnResult['usingOrder'] = $testLocalOrder;
                        $returnResult['errorString'] = "Order already exists";
                        $returnResult['updatePaymentReferenceStatus'] = $updatePaymentReferenceStatus;
                    }

                } else {
                    $returnResult['errorString'] = "nonce mismatch";
                    $returnResult['errorCode'] = 403;
                    $responseCode = 403;
                }
            } else {
                $returnResult['errorString'] = "nonce missing";
                $returnResult['errorCode'] = 403;
                $responseCode = 403;
            }

            $this->returnJsonResponse($returnResult, $responseCode, $resursOrder);
        }

        /**
         * @param array $jsonArray
         * @param int $responseCode
         * @param null $resursOrder
         */
        private function returnJsonResponse($jsonArray = array(), $responseCode = 200, $resursOrder = null)
        {
            header("Content-Type: application/json", true, $responseCode);
            echo json_encode($jsonArray);
            die();
        }

        /**
         * Check result of signing, book the payment and complete the order
         */
        public function check_signing_response()
        {
            global $woocommerce;

            $url_arr = parse_url($_SERVER["REQUEST_URI"]);
            $url_arr['query'] = str_replace('amp;', '', $url_arr['query']);
            parse_str($url_arr['query'], $request);
            $order_id = isset($request['order_id']) && !empty($request['order_id']) ? $request['order_id'] : null;
            /** @var $order WC_Order */
            $order = new WC_Order($order_id);
            $getRedirectUrl = $this->get_return_url($order);
            $currentStatus = $order->get_status();

            $paymentId = wc_get_payment_id_by_order_id($order_id);
            $isHostedFlow = false;
            $requestedPaymentId = isset($request['payment_id']) ? $request['payment_id'] : "";
            $hasBookedHostedPayment = false;
            $bookedPaymentId = 0;
            $bookedStatus = null;
            $paymentInfo = null;

            $flowType = isset($request['flow-type']) ? $request['flow-type'] : "";

            if (isset($_REQUEST['flow-type']) && empty($flowType)) {
                $flowType = $_REQUEST['flow-type'];
            }
            $eventType = isset($request['event-type']) ? $request['event-type'] : "";
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

                    $storeId = apply_filters("resursbank_set_storeid", null);
                    if (!empty($storeId)) {
                        update_post_meta($order_id, 'resursStoreId', $storeId);
                    }

                    if ($request['failInProgress'] == "1" || isset($_REQUEST['failInProgress']) && $_REQUEST['failInProgress'] == "1") {
                        $order->update_status('cancelled',
                            __('The payment failed during purchase', 'resurs-bank-payment-gateway-for-woocommerce'));
                        wc_add_notice(__("The purchase from Resurs Bank was by some reason not accepted. Please contact customer services, or try again with another payment method.",
                            'resurs-bank-payment-gateway-for-woocommerce'), 'error');
                        update_post_meta($order_id, 'rcoOrderFailed', true);

                        WC()->session->set("order_awaiting_payment", true);
                        $getRedirectUrl = wc_get_cart_url();
                    } else {
                        $optionReduceOrderStock = getResursOption('reduceOrderStock');
                        $hasReduceStock = get_post_meta($order_id, 'hasReduceStock');
                        // While waiting for the order confirmation from Resurs Bank, reducing stock may be necessary, anyway.
                        if ($optionReduceOrderStock && empty($hasReduceStock)) {
                            update_post_meta($order_id, 'hasReduceStock', time());
                            if (isWooCommerce3()) {
                                wc_reduce_stock_levels($order_id);
                            } else {
                                $order->reduce_order_stock();
                            }
                        }
                        $getRedirectUrl = $this->get_return_url($order);

                        $order->add_order_note('[Resurs Bank] ' .
                            __('The payment are signed and booked. Waiting for further statuses.',
                                'resurs-bank-payment-gateway-for-woocommerce')
                        );

                        $current = $order->get_status();
                        try {
                            $this->updateOrderByResursPaymentStatus($order, $current, $paymentId);
                        } catch (Exception $e) {
                            $order > add_order_note($e->getMessage());
                        }
                        WC()->cart->empty_cart();
                    }
                    wp_safe_redirect($getRedirectUrl);

                    return;
                }
            }

            if ($paymentId != $requestedPaymentId && !$isHostedFlow) {
                $order->update_status('failed');
                wc_add_notice(__('The payment can not complete. Contact customer services for more information.',
                    'resurs-bank-payment-gateway-for-woocommerce'), 'error');
            }

            $signedResult = null;
            $bookSigned = false;

            if (!$isHostedFlow) {
                try {
                    $signedResult = $this->flow->bookSignedPayment($paymentId);
                    $bookSigned = true;
                } catch (Exception $bookSignedException) {
                    // Do nothing
                }
                if ($bookSigned) {
                    $bookedStatus = isset($signedResult->bookPaymentStatus) ? $signedResult->bookPaymentStatus : null;
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
                    wc_add_notice(__('The payment already exists', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'error');
                } else {
                    /* If not, something went wrong further into the processing */
                    if ($hasGetPaymentErrors) {
                        if (isset($getPaymentException) && !empty($getPaymentException)) {
                            //$exceptionMessage = $getPaymentException->getMessage();
                            wc_add_notice(__('We could not finish your order. Please, contact support for more information.',
                                'resurs-bank-payment-gateway-for-woocommerce'), 'error');
                        }
                        wc_add_notice($exceptionMessage, 'error');
                    } else {
                        wc_add_notice(__('An unknown error occured in signing method. Please, try again later',
                            'resurs-bank-payment-gateway-for-woocommerce'), 'error');
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
                /* Continue. */
                if ($bookedStatus == 'FROZEN') {
                    $order->update_status('on-hold',
                        __('The payment are frozen, while waiting for manual control',
                            'resurs-bank-payment-gateway-for-woocommerce'));
                } elseif ($bookedStatus == 'BOOKED') {
                    $order->update_status('processing',
                        __('The payment are signed and booked', 'resurs-bank-payment-gateway-for-woocommerce'));
                } elseif ($bookedStatus == 'FINALIZED') {
                    WC()->session->set("order_awaiting_payment", true);
                    try {
                        $order->set_status('completed',
                            __('Order is debited and completed', 'resurs-bank-payment-gateway-for-woocommerce'),
                            true);
                        $order->save();
                    } catch (\Exception $e) {
                        wc_add_notice($e->getMessage(), 'error');

                        return;
                    }

                    $order->update_status('completed',
                        __('The payment are signed and debited', 'resurs-bank-payment-gateway-for-woocommerce'));
                } elseif ($bookedStatus == 'DENIED') {
                    $order->update_status('failed');
                    update_post_meta($order_id, 'orderDenied', true);
                    wc_add_notice(__('The payment can not complete. Contact customer services for more information.',
                        'resurs-bank-payment-gateway-for-woocommerce'), 'error');
                    $getRedirectUrl = wc_get_cart_url();
                } elseif ($bookedStatus == 'FAILED') {
                    $order->update_status('failed',
                        __('An error occured during the update of the booked payment. The payment id was never received properly in signing response',
                            'resurs-bank-payment-gateway-for-woocommerce'));
                    wc_add_notice(__('An unknown error occured. Please, try again later',
                        'resurs-bank-payment-gateway-for-woocommerce'),
                        'error');
                    $getRedirectUrl = wc_get_cart_url();
                }
            } catch (Exception $e) {
                wc_add_notice(__('Something went wrong during the signing process.',
                    'resurs-bank-payment-gateway-for-woocommerce'), 'error');
                $getRedirectUrl = wc_get_cart_url();
            }

            $hasAnnulment = get_post_meta($order->get_id(), "hasAnnulment", true);
            if (!$getRedirectUrl || $hasAnnulment == "1") {
                $getRedirectUrl = wc_get_cart_url();
            }

            wp_safe_redirect($getRedirectUrl);

            return;
        }

        /**
         * Generate the payment methods that were returned from Resurs Bank API
         *
         * @param  array $payment_methods The payment methods
         */
        public function generate_payment_gateways($payment_methods)
        {
            $methods = array();
            $class_files = array();
            foreach ($payment_methods as $payment_method) {
                $methods[] = 'resurs-bank-id-' . $payment_method->id;
                $class_files[] = 'resurs_bank_nr_' . $payment_method->id . '.php';
                $this->write_class_to_file($payment_method);
            }
            $this->UnusedPaymentClassesCleanup($class_files);
            set_transient('resurs_bank_class_files', $class_files);
        }

        /**
         * Generates and writes a class for a specified payment methods to file
         *
         * @param  stdClass $payment_method A payment method return from Resurs Bank API
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
            $className = $_REQUEST['payment_method'];

            $methodName = str_replace('resurs_bank_nr_', '', $className);
            $transientMethod = $this->getTransientMethod($methodName);
            $countryCode = isset($_REQUEST['billing_country']) ? $_REQUEST['billing_country'] : "";
            $customerType = isset($_REQUEST['ssnCustomerType']) ? $_REQUEST['ssnCustomerType'] : "NATURAL";

            /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
            $flow = initializeResursFlow();
            $regEx = $flow->getRegEx(null, $countryCode, $customerType);
            // TODO: Leave the oldFlowSimulator/regex behind and replace with own field generators.
            $methodFieldsRequest = $flow->getTemplateFieldsByMethodType($transientMethod, $customerType);
            $methodFields = $methodFieldsRequest['fields'];

            $fetchedGovernmentId = (isset($_REQUEST['applicant-government-id']) ? trim($_REQUEST['applicant-government-id']) : "");
            if (empty($fetchedGovernmentId) && isset($_REQUEST['ssn_field']) && !empty($_REQUEST['ssn_field'])) {
                $_REQUEST['applicant-government-id'] = $_REQUEST['ssn_field'];
            }

            $validationFail = false;
            foreach ($methodFields as $fieldName) {
                if (isset($_REQUEST[$fieldName]) && isset($regEx[$fieldName])) {
                    if ($fieldName == 'applicant-government-id' && empty($_REQUEST[$fieldName]) && $flow->getCanSkipGovernmentIdValidation()) {
                        continue;
                    }
                    $regExString = $regEx[$fieldName];
                    $regExString = str_replace('\\\\', '\\', $regExString);
                    $fieldData = isset($_REQUEST[$fieldName]) ? trim($_REQUEST[$fieldName]) : "";
                    $invalidFieldError = __('The field',
                            'resurs-bank-payment-gateway-for-woocommerce') . " " . $fieldName . " " . __('has invalid information',
                            'resurs-bank-payment-gateway-for-woocommerce') . " (" . (!empty($fieldData) ? $fieldData : __("It can't be empty",
                            'resurs-bank-payment-gateway-for-woocommerce')) . ")";
                    if ($fieldName == "card-number" && empty($fieldData)) {
                        continue;
                    }
                    if (preg_match("/email/", $fieldName)) {
                        if (!filter_var($_REQUEST[$fieldName], FILTER_VALIDATE_EMAIL)) {
                            wc_add_notice($invalidFieldError, 'error');
                        }
                    } else {
                        if (!preg_match('/' . $regExString . '/', $_REQUEST[$fieldName])) {
                            wc_add_notice($invalidFieldError, 'error');
                            $validationFail = true;
                        }
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
        function is_valid_for_use()
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
            $handleNatConnections = getResursOption('handleNatConnections');
            if ($handleNatConnections) {
                // check for shared internet/ISP IP
                if (!empty($_SERVER['HTTP_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
                    return $_SERVER['HTTP_CLIENT_IP'];
                }
                // check for IPs passing through proxies
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    // check if multiple ips exist in var
                    $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    foreach ($iplist as $ip) {
                        if (self::validate_ip($ip)) {
                            return $ip;
                        }
                    }
                }
                if (!empty($_SERVER['HTTP_X_FORWARDED']) && self::validate_ip($_SERVER['HTTP_X_FORWARDED'])) {
                    return $_SERVER['HTTP_X_FORWARDED'];
                }
                if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
                    return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
                }
                if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && self::validate_ip($_SERVER['HTTP_FORWARDED_FOR'])) {
                    return $_SERVER['HTTP_FORWARDED_FOR'];
                }
                if (!empty($_SERVER['HTTP_FORWARDED']) && self::validate_ip($_SERVER['HTTP_FORWARDED'])) {
                    return $_SERVER['HTTP_FORWARDED'];
                }
            }

            // return unreliable ip since all else failed
            return $_SERVER['REMOTE_ADDR'];
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
            if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 |
                    FILTER_FLAG_IPV6 |
                    FILTER_FLAG_NO_PRIV_RANGE |
                    FILTER_FLAG_NO_RES_RANGE) === false
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
            $_REQUEST['tab'] = "tab_resursbank";
            $_REQUEST['section'] = "";
            $url = admin_url('admin.php');
            $url = add_query_arg('page', $_REQUEST['page'], $url);
            $url = add_query_arg('tab', $_REQUEST['tab'], $url);
            $url = add_query_arg('section', $_REQUEST['section'], $url);
            wp_safe_redirect($url);
            die("Deprecated space");
        }

        /**
         * @param $temp_class_files
         */
        private function UnusedPaymentClassesCleanup($temp_class_files)
        {
            $allIncludes = array();
            $path = plugin_dir_path(__FILE__) . 'includes/';
            $globIncludes = glob(plugin_dir_path(__FILE__) . 'includes/*.php');
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
            $returnArr = array();
            try {
                $paymentMethods = $this->flow->getPaymentMethods(array(), true);
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
         * Get address for a specific government ID
         *
         * @return void Prints the address data as JSON
         * @throws Exception
         */
        public static function get_address_ajax()
        {
            $results = array();
            if (isset($_REQUEST) && 'SE' == getResursOption('country')) {
                $customerType = isset($_REQUEST['customerType']) ? ($_REQUEST['customerType'] != 'LEGAL' ? 'NATURAL' : 'LEGAL') : 'NATURAL';

                $serverEnv = getResursOption("serverEnv");
                /*
                 * Overriding settings here, if we want getAddress picked from production instead of test.
                 * The only requirement for this to work is that we are running in test and credentials for production is set.
                 */
                $userProd = getResursOption("ga_login");
                $passProd = getResursOption("ga_password");
                $getAddressUseProduction = getResursOption("getAddressUseProduction");
                $disabledProdTests = true;      // TODO: Set this to false in future, when we're ready again (https://resursbankplugins.atlassian.net/browse/WOO-44)
                if ($getAddressUseProduction && isResursDemo() && $serverEnv == "test" && !empty($userProd) && !empty($passProd) && !$disabledProdTests) {
                    $results = getAddressProd($_REQUEST['ssn'], $customerType, self::get_ip_address());
                } else {
                    /** @var \Resursbank\RBEcomPHP\ResursBank */
                    $flow = initializeResursFlow();
                    try {
                        $results = $flow->getAddress($_REQUEST['ssn'], $customerType, self::get_ip_address());
                    } catch (Exception $e) {
                        $results = array(
                            "error" => __('Can not get the address from current government ID',
                                'resurs-bank-payment-gateway-for-woocommerce')
                        );
                    }
                }
            }
            header("Content-type: application/json; charset=utf-8");
            echo json_encode($results);
            die();
        }

        public static function get_cost_ajax()
        {
            global $styles;
            require_once('resursbankgateway.php');
            $costOfPurchaseHtml = "";
            /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
            $flow = initializeResursFlow();
            $method = $_REQUEST['method'];
            $amount = floatval($_REQUEST['amount']);

            $wooCommerceStyle = realpath(get_stylesheet_directory()) . "/css/woocommerce.css";
            $styles = array();

            $costOfPurchaseCss = getResursOption('costOfPurchaseCss');
            if (empty($costOfPurchaseCss)) {
                if (file_exists($wooCommerceStyle)) {
                    $styles[] = get_stylesheet_directory_uri() . "/css/woocommerce.css";
                }
                /**
                 * Try to find out if there is a costofpurchase-file defaulting to our plugin
                 */
                $cssPathFile = dirname(__FILE__) . '/css/costofpurchase.css';
                $costOfPurchaseCssDefault = plugin_dir_url(__FILE__) . 'css/costofpurchase.css';
                /**
                 * Make sure it exists and if so, add it to the styles and the viewport.
                 */
                if (file_exists($cssPathFile)) {
                    $styles[] = plugin_dir_url(__FILE__) . 'css/costofpurchase.css';
                    $costOfPurchaseCss = $costOfPurchaseCssDefault;
                }
            }

            try {
                $htmlBefore = '<div class="cost-of-purchase-box"><a class="woocommerce button" onclick="window.close()" href="javascript:void(0);">' . __('Close',
                        'resurs-bank-payment-gateway-for-woocommerce') . '</a>';
                $htmlAfter = '</div>';

                $flow->setCostOfPurcaseHtmlBefore($htmlBefore);
                $flow->setCostOfPurcaseHtmlAfter($htmlAfter);

                /**
                 * Fix for issue #66520, where the CSS pointer has not been added properly to our default location.
                 */
                $costOfPurchaseHtml = $flow->getCostOfPurchase($method, $amount, true, $costOfPurchaseCss, "_blank");
            } catch (Exception $e) {
            }
            echo $costOfPurchaseHtml;
            die();
        }

        /**
         * Get information about selected payment method in checkout, to control the method listing
         */
        public static function get_address_customertype($return = false)
        {
            /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
            $flow = initializeResursFlow();
            $methodsHasErrors = false;
            $methodsErrorMessage = null;
            $paymentMethods = null;

            $resursTemporaryPaymentMethodsTime = get_transient("resursTemporaryPaymentMethodsTime");
            $timeDiff = time() - $resursTemporaryPaymentMethodsTime;
            try {
                if ($timeDiff >= 3600) {
                    $paymentMethods = $flow->getPaymentMethods(array(), true);
                    set_transient("resursTemporaryPaymentMethodsTime", time(), 3600);
                    set_transient("resursTemporaryPaymentMethods", serialize($paymentMethods), 3600);
                } else {
                    $paymentMethods = unserialize(get_transient("resursTemporaryPaymentMethods"));
                }
            } catch (Exception $e) {
                $methodsHasErrors = true;
                $methodsErrorMessage = $e->getMessage();
            }
            $requestedCustomerType = isset($_REQUEST['customerType']) ? $_REQUEST['customerType'] : "NATURAL";
            $responseArray = array(
                'natural' => array(),
                'legal' => array()
            );

            if (is_array($paymentMethods)) {
                foreach ($paymentMethods as $objId) {
                    if (isset($objId->id) && isset($objId->customerType)) {
                        $nr = "resurs_bank_nr_" . $objId->id;
                        if (!is_array($objId->customerType)) {
                            $responseArray[strtolower($objId->customerType)][] = $nr;
                        } else {
                            foreach ($objId->customerType as $customerType) {
                                $responseArray[strtolower($customerType)][] = $nr;
                            }
                        }
                    }
                }
            }

            if ($methodsHasErrors) {
                $responseArray = array(
                    'errorstring' => $methodsErrorMessage
                );
            }

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
         * Called when the status of an order is changed
         *
         * @param  int $order_id The order id
         * @param  string $old_status_slug The old status
         * @param  string $new_status_slug The new stauts
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
            if (false === (boolean)preg_match('/resurs_bank/', $payment_method)) {
                return;
            }

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
            $old_status = get_term_by('slug', sanitize_title($old_status_slug), 'shop_order_status');

            /** @var $resursFlow \Resursbank\RBEcomPHP\ResursBank */
            $resursFlow = initializeResursFlow();
            $flowErrorMessage = null;

            if ($payment_id) {
                try {
                    $payment = getPaymentInfo($order, $payment_id);
                    if (isset($payment->id) && $payment_id !== $payment->id) {
                        // If something went wrong during the order processing at customer level
                        // we can still prevent wrong id's to be fixed at this point.
                        $payment_id = $payment->id;
                    }
                } catch (\Exception $getPaymentException) {
                    return;
                }
                if (isset($payment)) {
                    if (false === is_array($payment->status)) {
                        $status = array($payment->status);
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
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => 'Denna order är annulerad och går därmed ej att ändra status på',
                        );

                        wp_set_object_terms($order_id, array($old_status->slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                        exit;
                    }
                    break;
                case 'refunded':
                    if (in_array('IS_CREDITED', $status)) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => 'Denna order är krediterad och går därmed ej att ändra status på',
                        );

                        wp_set_object_terms($order_id, array($old_status->slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                        exit;
                    }
                    break;
                default:
                    break;
            }

            switch ($new_status_slug) {
                case 'pending':
                    break;
                case 'failed':
                    break;
                case 'processing':
                    break;
                case 'completed':
                    $flowErrorMessage = "";
                    if ($resursFlow->canDebit($payment)) {
                        try {
                            $successFinalize = $resursFlow->paymentFinalize($payment_id);
                            resursEventLogger($payment_id . ': Finalization - Payment Content');
                            resursEventLogger(print_r($payment, true));
                            resursEventLogger($payment_id . ': Finalization ' . $successFinalize ? 'OK' : 'NOT OK');
                            wp_set_object_terms($order_id, array($old_status_slug), 'shop_order_status', false);
                        } catch (Exception $e) {
                            // Checking code 29 is not necessary since this is automated in EComPHP
                            $flowErrorMessage = "[" . __('Error',
                                    'resurs-bank-payment-gateway-for-woocommerce') . " " . $e->getCode() . "] " . $e->getMessage();
                            $order->update_status($old_status_slug);
                            resursEventLogger($payment_id . ': FinalizationException ' . $e->getCode() . ' - ' . $e->getMessage() . '. Old status (' . $old_status_slug . ') restored.');
                            $order->add_order_note(__('Finalization failed',
                                    'resurs-bank-payment-gateway-for-woocommerce') . ": " . $flowErrorMessage);
                        }
                    } else {
                        // Generate a notice if the order has been debited from for example payment admin.
                        // This notice requires that an order is not debitable (if it is, there's more to debit anyway, so in that case the above finalization event will occur)
                        if ($resursFlow->getIsDebited()) {
                            if ($resursFlow->getInstantFinalizationStatus($payment) & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED)) {
                                resursEventLogger($payment_id . ': InstantFinalization/IsDebited detected.');
                                $order->add_order_note(__('This order is now marked completed as a result of the payment method behaviour (automatic finalization).',
                                    'resurs-bank-payment-gateway-for-woocommerce'));
                            } else {
                                resursEventLogger($payment_id . ': Already finalized.');
                                $order->add_order_note(__('This order has already been finalized externally',
                                    'resurs-bank-payment-gateway-for-woocommerce'));
                            }
                        } else {
                            if ($resursFlow->getInstantFinalizationStatus($payment) & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED)) {
                                resursEventLogger($payment_id . ': InstantFinalization/DebitedNotDetected detected for.');
                                $orderNote = __('The payment method for this order indicates that the payment has been automatically finalized.',
                                    'resurs-bank-payment-gateway-for-woocommerce');
                            } else {
                                resursEventLogger($payment_id . ': Can not finalize due to the current remote order status.');
                                // Generate error message if the order is something else than debited and debitable
                                //$orderNote = __('This order is in a state at Resurs Bank where it can not be finalized', 'resurs-bank-payment-gateway-for-woocommerce');
                            }
                            if (!empty($orderNote)) {
                                $order->add_order_note($orderNote);
                                $flowErrorMessage = $orderNote;
                            }
                        }
                    }
                    if (!empty($flowErrorMessage)) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => $flowErrorMessage
                        );
                    }
                    wp_safe_redirect($url);
                    break;
                case 'on-hold':
                    break;
                case 'cancelled':
                    try {
                        $resursFlow->paymentCancel($payment_id);
                        $order->add_order_note(__('Cancelled status set: Resurs Bank API was called for cancellation',
                            'resurs-bank-payment-gateway-for-woocommerce'));
                    } catch (Exception $e) {
                        $flowErrorMessage = $e->getMessage();
                    }
                    if (null !== $flowErrorMessage) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => $flowErrorMessage
                        );
                        wp_set_object_terms($order_id, array($old_status_slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                    }
                    break;
                case 'refunded':
                    try {
                        $resursFlow->paymentCancel($payment_id);
                        $order->add_order_note(__('Refunded status set: Resurs Bank API was called for cancellation',
                            'resurs-bank-payment-gateway-for-woocommerce'));
                    } catch (Exception $e) {
                        $flowErrorMessage = $e->getMessage();
                    }
                    if (null !== $flowErrorMessage) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => $flowErrorMessage
                        );
                        wp_set_object_terms($order_id, array($old_status_slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                    }
                    break;
                default:
                    break;
            }

            return;
        }
        // Class ends here
    }

    /**
     * Adds the SSN field to the checkout form for fetching a address
     *
     * @param  WC_Checkout $checkout The WooCommerce checkout object
     *
     * @return WC_Checkout           The WooCommerce checkout object
     */
    function add_ssn_checkout_field($checkout)
    {
        if (!getResursOption('enabled')) {
            return $checkout;
        }

        $selectedCountry = getResursOption("country");
        $optionGetAddress = getResursOption("getAddress");
        $private = __('Private', 'resurs-bank-payment-gateway-for-woocommerce');
        $company = __('Company', 'resurs-bank-payment-gateway-for-woocommerce');
        if ($optionGetAddress && !isResursOmni()) {
            /*
             * MarGul change
             * If it's demoshop get the translation.
             */
            if (isResursDemo() && class_exists('CountryHandler')) {
                $translation = CountryHandler::getDictionary();
                $private = $translation['private'];
                $company = $translation['company'];
            }
            // Here we use the translated or not translated values for Private and Company radiobuttons
            $resursTemporaryPaymentMethodsTime = get_transient("resursTemporaryPaymentMethodsTime");
            $timeDiff = time() - $resursTemporaryPaymentMethodsTime;
            if ($timeDiff >= 3600) {
                /** @var $theFlow \Resursbank\RBEcomPHP\ResursBank */
                $theFlow = initializeResursFlow();
                $methodList = $theFlow->getPaymentMethods(array(), true);
                set_transient("resursTemporaryPaymentMethodsTime", time(), 3600);
                set_transient("resursTemporaryPaymentMethods", serialize($methodList), 3600);
            } else {
                $methodList = unserialize(get_transient("resursTemporaryPaymentMethods"));
            }
            $naturalCount = 0;
            $legalCount = 0;
            if (is_array($methodList)) {
                foreach ($methodList as $method) {
                    $customerType = $method->customerType;
                    if (is_array($customerType)) {
                        if (in_array("NATURAL", $customerType)) {
                            $naturalCount++;
                        }
                        if (in_array("LEGAL", $customerType)) {
                            $legalCount++;
                        }
                    } else {
                        if ($customerType == "NATURAL") {
                            $naturalCount++;
                        }
                        if ($customerType == "LEGAL") {
                            $legalCount++;
                        }
                    }
                }
            }

            $viewNatural = "display:;";
            $viewLegal = "display:;";
            if ($naturalCount > 0 && !$legalCount) {
                $viewNatural = "display: none;";
            }
            if (!$naturalCount && $legalCount) {
                $viewLegal = "display: none;";
            }

            if ($naturalCount) {
                // [DOM] Found 2 elements with non-unique id #ssnCustomerType
                // onchange="$RB('body').trigger('update_checkout')"
                echo '<span id="ssnCustomerRadioNATURAL" style="' . $viewNatural . '"><input type="radio" id="ssnCustomerTypeNATURAL" onclick="getMethodType(\'natural\')" checked="checked" name="ssnCustomerType" value="NATURAL"> ' . $private . "</span> ";
            }
            if ($legalCount) {
                echo '<span id="ssnCustomerRadioLEGAL" style="' . $viewLegal . '"><input type="radio" id="ssnCustomerTypeLEGAL" onclick="getMethodType(\'legal\')" name="ssnCustomerType" value="LEGAL"> ' . $company . "</span>";
            }
            echo '<input type="hidden" id="resursSelectedCountry" value="' . $selectedCountry . '">';
            woocommerce_form_field('ssn_field', array(
                'type' => 'text',
                'class' => array('ssn form-row-wide resurs_ssn_field'),
                'label' => __('Government ID', 'resurs-bank-payment-gateway-for-woocommerce'),
                'placeholder' => __('Enter your government id (social security number)',
                    'resurs-bank-payment-gateway-for-woocommerce'),
            ), $checkout->get_value('ssn_field'));
            if ('SE' == $selectedCountry) {
                /*
                 * MarGul change
                 * Take the translation for Get Address.
                 */
                if (class_exists('CountryHandler')) {
                    $translation = CountryHandler::getDictionary();
                } else {
                    $translation = array();
                }
                $get_address = (!empty($translation)) ? $translation['get_address'] : __('Get address',
                    'resurs-bank-payment-gateway-for-woocommerce');
                echo '<a href="#" class="button" id="fetch_address">' . $get_address . '</a> <span id="fetch_address_status" style="display: none;"><img src="' . plugin_dir_url(__FILE__) . "loader.gif" . '" border="0"></span>
                <br>';
            }
        }

        return $checkout;
    }

    /**
     * Adds Resurs Bank javascript file
     *
     * @return null Returns null if Resurs Bank plugin is not enabled
     */
    function enqueue_script()
    {
        if (!getResursOption('enabled')) {
            return;
        }
        $OmniVars = array();
        if (isResursOmni()) {
            $omniRefAge = null;
            wp_enqueue_script('resursomni', plugin_dir_url(__FILE__) . 'js/omnicheckout.js', array(),
                RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : ""));
            $omniBookUrl = home_url('/');
            $omniBookUrl = add_query_arg('wc-api', 'WC_Resurs_Bank', $omniBookUrl);
            $omniBookUrl = add_query_arg('event-type', 'prepare-omni-order', $omniBookUrl);
            $omniBookUrl = add_query_arg('set-no-session', '1', $omniBookUrl);
            $omniBookNonce = wp_nonce_url($omniBookUrl, "omnicheckout", "omnicheckout_nonce");

            /** @var $flow Resursbank\RBEcomPHP\ResursBank */
            $flow = initializeResursFlow();
            $sEnv = getServerEnv();
            $OmniUrl = $flow->getCheckoutUrl($sEnv);

            $isWooSession = false;
            if (isset(WC()->session)) {
                $isWooSession = true;
            }
            if ($isWooSession) {
                $omniRef = WC()->session->get('omniRef');
                $omniRefCreated = WC()->session->get('omniRefCreated');
                $omniRefAge = intval(WC()->session->get('omniRefAge'));
            }

            $gateways = WC()->payment_gateways()->get_available_payment_gateways();

            $OmniVars = array(
                'RESURSCHECKOUT_IFRAME_URL' => $OmniUrl,
                'RESURSCHECKOUT' => home_url(),
                'OmniPreBookUrl' => $omniBookNonce,
                'OmniRef' => isset($omniRef) && !empty($omniRef) ? $omniRef : null,
                'OmniRefCreated' => isset($omniRefCreated) && !empty($omniRefCreated) ? $omniRefCreated : null,
                'OmniRefAge' => $omniRefAge,
                'isResursTest' => isResursTest(),
                'iframeShape' => getResursOption("iframeShape",
                    "woocommerce_resurs_bank_omnicheckout_settings"),
                'useStandardFieldsForShipping' => getResursOption("useStandardFieldsForShipping",
                    "woocommerce_resurs_bank_omnicheckout_settings"),
                'showResursCheckoutStandardFieldsTest' => getResursOption("showResursCheckoutStandardFieldsTest"),
                'gatewayCount' => (is_array($gateways) ? count($gateways) : 0),
                'postidreference' => getResursOption("postidreference")
            );
            $setSessionEnable = true;
            $setSession = isset($_REQUEST['set-no-session']) ? $_REQUEST['set-no-session'] : null;
            if ($setSession == 1) {
                $setSessionEnable = false;
            } else {
                $setSessionEnable = true;
            }

            // During the creation of new omnivars, make sure they are not duplicates from older orders.
            if ($setSessionEnable && function_exists('WC') && $isWooSession) {
                $currentOmniRef = WC()->session->get('omniRef');
                // The resursCreatePass variable is only set when everything was successful.
                $resursCreatePass = WC()->session->get('resursCreatePass');
                $orderControl = wc_get_order_id_by_payment_id($currentOmniRef);
                if (!empty($orderControl) && !empty($currentOmniRef)) {
                    $checkOrder = new WC_Order($orderControl);
                    // currentOrderStatus checks what status the order had when created
                    $currentOrderStatus = $checkOrder->get_status();
                    $preventCleanup = array(
                        'pending',
                        'failed'
                    );
                    $allowCleanupSession = false;
                    if (!in_array($currentOrderStatus, $preventCleanup)) {
                        $allowCleanupSession = true;
                    }
                    if (($resursCreatePass && $currentOmniRef) || ($allowCleanupSession)) {
                        $refreshUrl = wc_get_cart_url();
                        $thisSession = new WC_Session_Handler();
                        $thisSession->destroy_session();
                        $thisSession->cleanup_sessions();
                        wp_destroy_all_sessions();
                        wp_safe_redirect($refreshUrl);
                    }
                }
            }
        }

        $resursLanguageLocalization = array(
            'getAddressEnterGovernmentId' => __('Enter social security number',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'getAddressEnterCompany' => __('Enter corporate government identity',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'labelGovernmentId' => __('Government id', 'resurs-bank-payment-gateway-for-woocommerce'),
            'labelCompanyId' => __('Corporate government id', 'resurs-bank-payment-gateway-for-woocommerce'),
        );

        // Country language overrider - MarGul
        if (isResursDemo() && class_exists('CountryHandler')) {
            $translation = CountryHandler::getDictionary();
            $resursLanguageLocalization = [
                'getAddressEnterGovernmentId' => __($translation['enter_ssn_num'],
                    'resurs-bank-payment-gateway-for-woocommerce'),
                'getAddressEnterCompany' => __($translation['enter_gov_id'],
                    'resurs-bank-payment-gateway-for-woocommerce'),
                'labelGovernmentId' => __($translation['gov_id'], 'resurs-bank-payment-gateway-for-woocommerce'),
                'labelCompanyId' => __($translation['corp_gov_id'], 'resurs-bank-payment-gateway-for-woocommerce'),
            ];
        }

        $generalJsTranslations = array(
            'deliveryRequiresSigning' => __("Changing delivery address requires signing",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'ssnElementMissing' => __("I can not show errors since the element is missing",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'purchaseAjaxInternalFailure' => __("The purchase has failed, due to an internal server error: The shop could not properly update the order.",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'updatePaymentReferenceFailure' => __("The purchase was processed, but the payment reference failed to update",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'resursPurchaseNotAccepted' => __("The purchase was rejected by Resurs Bank. Please contact customer services, or try again with another payment method.",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'theAjaxWasNotAccepted' => __("Something went wrong when we tried to book your order. Please contact customer support for more information.",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'theAjaxWentWrong' => __("An internal error occured while trying to book the order. Please contact customer support for more information.",
                'resurs-bank-payment-gateway-for-woocommerce'),
            'theAjaxWentWrongWithThisMessage' => __("An internal error occured while trying to book the order:",
                    'resurs-bank-payment-gateway-for-woocommerce') . " ",
            'contactSupport' => __("Please contact customer support for more information.",
                'resurs-bank-payment-gateway-for-woocommerce')
        );

        $customerTypes = WC_Resurs_Bank::get_address_customertype(true);

        $resursVars = array(
            'ResursBankAB' => true,
            'customerTypes' => $customerTypes,
            'resursSpinnerLocal' => plugin_dir_url(__FILE__) . 'spinnerLocal.gif',
            'resursCheckoutMultipleMethods' => omniOption('resursCheckoutMultipleMethods'),
        );

        $oneRandomValue = null;
        $randomizeJsLoaders = getResursOption("randomizeJsLoaders");
        if ($randomizeJsLoaders) {
            $oneRandomValue = "?randomizeMe=" . rand(1024, 65535);
        }
        $ajaxObject = array('ajax_url' => admin_url('admin-ajax.php'));
        wp_enqueue_style('resursInternal', plugin_dir_url(__FILE__) . 'css/resursinternal.css', array(),
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : ""));
        wp_enqueue_script('resursbankmain', plugin_dir_url(__FILE__) . 'js/resursbank.js' . $oneRandomValue,
            array('jquery'),
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : ""));
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
     * @return null        Returns null current page is not correct
     */
    function admin_enqueue_script($hook)
    {
        $images = plugin_dir_url(__FILE__) . "img/";
        $resursLogo = $images . "resurs-standard.png";

        wp_enqueue_style('resursInternal', plugin_dir_url(__FILE__) . 'css/resursinternal.css', array(),
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : ""));
        wp_enqueue_script('resursBankAdminScript', plugin_dir_url(__FILE__) . 'js/resursbankadmin.js', array(),
            RB_WOO_VERSION . (defined('RB_ALWAYS_RELOAD_JS') && RB_ALWAYS_RELOAD_JS === true ? "-" . time() : ""));

        $requestForCallbacks = callbackUpdateRequest();

        $callbackUriCacheTime = get_transient("resurs_callback_templates_cache_last");
        $lastFetchedCacheTime = $callbackUriCacheTime > 0 ? strftime("%Y-%m-%d, %H:%M", $callbackUriCacheTime) : "";

        $adminJs = array(
            'resursSpinner' => plugin_dir_url(__FILE__) . 'loader.gif',
            'resursSpinnerLocal' => plugin_dir_url(__FILE__) . 'loaderLocal.gif',
            'resursFeePen' => plugin_dir_url(__FILE__) . 'img/pen16x.png',
            'callbackUrisCache' => __('The list of urls below is cached from an earlier response from Resurs Bank',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'callbackUrisCacheTime' => $lastFetchedCacheTime,
            'callbacks_registered' => __('callbacks has been registered',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'update_callbacks' => __('Update callbacks again', 'resurs-bank-payment-gateway-for-woocommerce'),
            'useZeroToReset' => __('To remove the fee properly, set the value to 0',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'notAllowedValue' => __('The entered value is not allowed here',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'requestForCallbacks' => $requestForCallbacks,
            'noCallbacksSet' => __('No registered callbacks could be found',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'annulCantBeAlone' => __('This setting requires waitForFraudControl to be active',
                'resurs-bank-payment-gateway-for-woocommerce'),
            'couldNotSetNewFee' => __('Unable to set new fee', 'resurs-bank-payment-gateway-for-woocommerce'),
            'newFeeHasBeenSet' => __('Fee has been updated', 'resurs-bank-payment-gateway-for-woocommerce'),
            'callbacks_pending' => __('Waiting for callback', 'resurs-bank-payment-gateway-for-woocommerce'),
            'callbacks_not_received' => __('Callback not yet received', 'resurs-bank-payment-gateway-for-woocommerce'),
            'callbacks_slow' => nl2br(__('It seems that your site has not received any callbacks yet.\nEither your site are unreachable, or the callback tester is for the moment slow.',
                'resurs-bank-payment-gateway-for-woocommerce')),
            'resursBankTabLogo' => $resursLogo,
        );

        $addAdminJs = apply_filters('resursAdminJs', null);
        if (is_array($addAdminJs)) {
            foreach ($addAdminJs as $key => $adminJsValue) {
                if (!empty($key) && !isset($adminJs[$key]) && !empty($adminJsValue)) {
                    $adminJs[$key] = $adminJsValue;
                }
            }
        }

        wp_localize_script('resursBankAdminScript', 'adminJs', $adminJs);
        $configUrl = home_url("/");
        $configUrl = add_query_arg('event-type', 'noevent', $configUrl);
        $configUrl = add_query_arg('wc-api', 'WC_Resurs_Bank', $configUrl);
        $adminAjax = array(
            'ran' => wp_nonce_url($configUrl, "requestResursAdmin", 'ran')
        );
        wp_localize_script('resursBankAdminScript', 'rbAjaxSetup', $adminAjax);

        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $_SESSION['resurs_bank_admin_notice']['message'] = __('The Resurs Bank Addon for WooCommerce may not work properly in PHP 5.3 or older. You should consider upgrading to 5.4 or higher.',
                'resurs-bank-payment-gateway-for-woocommerce');
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
        /** @var bool $do_not_start_session Using this filter and setting return value to true will be the same as disabling the session during this phase */
        $do_not_start_session = (bool)apply_filters("resursbank_start_session_before", null);

        /** @var bool $session_outside_admin Disable session creation when in admin if true (will make a !is_admin()-check) - https://resursbankplugins.atlassian.net/browse/WOO-247 */
        $session_outside_admin = (bool)apply_filters("resursbank_start_session_outside_admin_only", null);

        if (!(bool)$do_not_start_session) {
            if ((bool)$session_outside_admin) {
                if (!is_admin() && !session_id()) {
                    session_start();
                }
            } else {
                if (!session_id()) {
                    session_start();
                }
            }
        }
    }

    /**
     * End session on Wordpress login and logout
     */
    function end_session()
    {
        session_destroy();
    }

    /**
     * Used to enable wp_safe_redirect in ceratin situations
     */
    function app_output_buffer()
    {
        if (isset($_REQUEST['woocommerce_resurs-bank_refreshPaymentMethods']) || isset($_REQUEST['second_update_status']) || isset($_REQUEST['save']) || isset($_SESSION)) {
            ob_start();
        }
    }

    /**
     * Used to output a notice to the admin interface
     */
    function resurs_bank_admin_notice()
    {
        global $resursGlobalNotice, $resursSelfSession;
        if (isset($_SESSION['resurs_bank_admin_notice']) || $resursGlobalNotice === true) {
            if (is_array($_SESSION)) {
                if (!count($_SESSION) && count($resursSelfSession)) {
                    $_SESSION = $resursSelfSession;
                }
                $notice = '<div class=' . $_SESSION['resurs_bank_admin_notice']['type'] . '>';
                $notice .= '<p>' . $_SESSION['resurs_bank_admin_notice']['message'] . '</p>';
                $notice .= '</div>';
                echo $notice;
                unset($_SESSION['resurs_bank_admin_notice']);
            }
        }
    }

    function test_before_shipping()
    {
    }

    // If glob returns null (error) nothing should run
    $incGlob = glob(plugin_dir_path(__FILE__) . '/includes/*.php');
    if (is_array($incGlob)) {
        foreach ($incGlob as $filename) {
            if (!in_array($filename, get_included_files())) {
                include $filename;
            }
        }
    }
    $staticGlob = glob(plugin_dir_path(__FILE__) . '/staticflows/*.php');
    if (is_array($staticGlob)) {
        foreach ($staticGlob as $filename) {
            if (!in_array($filename, get_included_files())) {
                include $filename;
            }
        }
    }

    function rb_settings_pages($settings)
    {
        $settings[] = include(plugin_dir_path(__FILE__) . "/resursbank_settings.php");

        return $settings;
    }


    /* Payment gateway stuff */

    /**
     * Add the Gateway to WooCommerce
     *
     * @param  array $methods The available payment methods
     *
     * @return array          The available payment methods
     */
    function woocommerce_add_resurs_bank_gateway($methods)
    {
        $methods[] = 'WC_Resurs_Bank';
        if (is_admin() && is_array($methods)) {
            foreach ($methods as $id => $m) {
                if (is_string($m) && preg_match("/^resurs_bank_/i", $m)) {
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
     * @param  array $gateways The array of payment gateways
     *
     * @return array           The array of payment gateways
     */
    function woocommerce_resurs_bank_available_payment_gateways($gateways)
    {
        unset($gateways['resurs-bank']);

        return $gateways;
    }

    /**
     * @param $columns
     *
     * @return array
     */
    function resurs_order_column_header($columns)
    {
        $new_columns = array();
        $hasColumnOnce = false;
        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;
            if (!$hasColumnOnce && ($column_name == 'order_number' || $column_name == 'order_title')) {
                $new_columns['resurs_order_id'] = __('Resurs Reference', 'resurs-bank-payment-gateway-for-woocommerce');
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
        if ($column == 'resurs_order_id') {
            $resursId = wc_get_payment_id_by_order_id($post->ID);
            echo $resursId;
        }
    }

    function resurs_annuity_factors()
    {
        /** @var $product WC_Product_Simple */
        global $product;
        $displayAnnuity = "";
        if (is_object($product)) {

            /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
            $flow = initializeResursFlow();
            $annuityMethod = trim(getResursOption("resursAnnuityMethod"));
            $annuityFactorsOverride = null;
            $annuityDurationOverride = null;

            if (isResursDemo() && isset($_SESSION['rb_country']) && class_exists("CountryHandler")) {
                $countryHandler = new \CountryHandler();
                $annuityFactorsOverride = $countryHandler->getAnnuityFactors();
                $annuityDurationOverride = $countryHandler->getAnnuityFactorsDuration();
            }

            if (!empty($annuityMethod)) {
                $annuityFactorPrice = $product->get_price();

                try {
                    $methodList = null;
                    if (empty($annuityFactorsOverride)) {
                        $methodList = $flow->getPaymentMethodSpecific($annuityMethod);
                    }

                    if (!is_array($methodList) && !is_object($methodList)) {
                        $methodList = array();
                    }
                    $allowAnnuity = false;
                    if ((is_array($methodList) && count($methodList)) || is_object($methodList)) {
                        $allowAnnuity = true;
                    }
                    // Make sure the payment method exists. If there is overriders from the demoshop here, we'd know exists on the hard coded values.
                    if ($allowAnnuity || !empty($annuityFactorsOverride)) {
                        if (!empty($annuityFactorsOverride)) {
                            $annuityFactors = $annuityFactorsOverride;
                        } else {
                            $annuityFactors = getResursOption("resursCurrentAnnuityFactors");
                        }
                        if (!empty($annuityFactorsOverride)) {
                            $annuityDuration = $annuityDurationOverride;
                        } else {
                            $annuityDuration = getResursOption("resursAnnuityDuration");
                        }
                        $payFrom = $flow->getAnnuityPriceByDuration($annuityFactorPrice, $annuityFactors,
                            $annuityDuration);
                        $currentCountry = getResursOption('country');
                        if ($currentCountry != "FI") {
                            $paymentLimit = 150;
                        } else {
                            $paymentLimit = 15;
                        }
                        $realPaymentLimit = $paymentLimit;
                        if (isResursTest()) {
                            $paymentLimit = 1;
                        }
                        if ($payFrom >= $paymentLimit) {
                            $payFromAnnuity = wc_price($payFrom);
                            $costOfPurchase = admin_url('admin-ajax.php') . "?action=get_cost_ajax&method=$annuityMethod&amount=" . $annuityFactorPrice;
                            $onclick = 'window.open(\'' . $costOfPurchase . '\')';
                            $displayAnnuity .= '<div class="resursPartPaymentInfo">';
                            if (isResursTest()) {
                                $displayAnnuity .= '<div style="font-size: 11px !important; font-color:#990000 !important; font-style: italic; padding:0px !important; margin: 0px !important;">' . __('Test enabled: In production, this information is shown when the minimum amount is above',
                                        'resurs-bank-payment-gateway-for-woocommerce') . " <b>" . $realPaymentLimit . "</b></div>";
                            }

                            $displayAnnuity .= '<span>' . sprintf(__(
                                    'Part pay from %s per month',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ), $payFromAnnuity) . '</span> | ';

                            $displayAnnuity .= '<span class="resursPartPayInfoLink" onclick="' . $onclick . '">' . __('Info',
                                    'resurs-bank-payment-gateway-for-woocommerce') . '</span>';
                            $displayAnnuity .= '</div>';
                        }
                    }
                } catch (\Exception $annuityException) {
                    // In the multilingual demoshop there might be exceptions when the session is lost.
                    // Exceptions may also occur there, when the wrong payment method is checked and wrong language is chosen.
                    $displayAnnuity .= __('Annuity factors can not be displayed for the moment',
                            'resurs-bank-payment-gateway-for-woocommerce') . ": " . $annuityException->getMessage();
                }
            }
        }
        echo $displayAnnuity;
    }

    /**
     * This function allows partial refunding based on amount rather than article numbers.
     *
     * Written experimental for the future - eventually - since the logcis allows a lot more than we have time to fix right now.
     * For exampel, in this function we also need to figure out how much that is actually left to annul or credit before sending the actions.
     * If we try to credit more than is authorized or credit a part of the payment that is already annulled, the credit will fail.
     *
     * @param string $refundId
     * @param array $refundArgs
     *
     * @return bool
     */
    function resurs_order_refund($refundId = '', $refundArgs = array())
    {
        $refundStatus = false;
        $refundObject = new WC_Order_Refund($refundId);
        $amount = $refundObject->get_amount();
        $reason = $refundObject->get_reason();
        $itemCount = $refundObject->get_item_count();
        $parent = $refundObject->get_parent_id();;
        $resursId = wc_get_payment_id_by_order_id($parent);

        /** @var $refundFlow Resursbank\RBEcomPHP\ResursBank */
        $refundFlow = initializeResursFlow();

        if (!$itemCount && $amount >= 0 && $parent > 0 && !empty($resursId)) {
            try {
                $refundFlow->addOrderLine($reason, __('Refund', 'resurs-bank-payment-gateway-for-woocommerce'),
                    $amount);
                // totalAmount / limit
                if ($refundFlow->getIsDebited($resursId)) {
                    $refundStatus = $refundFlow->paymentCredit($resursId);
                } else {
                    $refundStatus = $refundFlow->paymentAnnul($resursId);
                }
            } catch (\Exception $refundException) {
            }
        }

        return $refundStatus;
    }

    //add_action( 'woocommerce_refund_created', 'resurs_order_refund' );

    add_filter('woocommerce_get_settings_pages', 'rb_settings_pages');
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_gateway');
    add_filter('woocommerce_available_payment_gateways',
        'woocommerce_resurs_bank_available_payment_gateways'); // Had prio 1
    add_filter('woocommerce_before_checkout_billing_form', 'add_ssn_checkout_field');
    add_action('woocommerce_order_status_changed', 'WC_Resurs_Bank::order_status_changed', 10, 3);
    add_action('wp_enqueue_scripts', 'enqueue_script', 0);
    add_action('admin_enqueue_scripts', 'admin_enqueue_script');
    add_action('wp_ajax_get_address_ajax', 'WC_Resurs_Bank::get_address_ajax');
    add_action('wp_ajax_nopriv_get_address_ajax', 'WC_Resurs_Bank::get_address_ajax');
    add_action('wp_ajax_get_cost_ajax', 'WC_Resurs_Bank::get_cost_ajax');
    add_action('wp_ajax_nopriv_get_cost_ajax', 'WC_Resurs_Bank::get_cost_ajax');
    add_action('wp_ajax_get_address_customertype', 'WC_Resurs_Bank::get_address_customertype');
    add_action('wp_ajax_nopriv_get_address_customertype', 'WC_Resurs_Bank::get_address_customertype');
    add_action('init', 'start_session', 1);
    add_action('wp_logout', 'end_session');
    add_action('wp_login', 'end_session');
    add_action('init', 'app_output_buffer', 2);
    add_action('admin_notices', 'resurs_bank_admin_notice');

    add_action('woocommerce_before_checkout_shipping_form', 'test_before_shipping');
    add_action('woocommerce_before_delete_order_item', 'resurs_remove_order_item');
    add_action('woocommerce_admin_order_data_after_order_details', 'resurs_order_data_info_after_order');
    add_action('woocommerce_admin_order_data_after_billing_address', 'resurs_order_data_info_after_billing');
    add_action('woocommerce_admin_order_data_after_shipping_address', 'resurs_order_data_info_after_shipping');
    add_filter('woocommerce_order_button_html', 'resurs_omnicheckout_order_button_html'); // Omni
    add_filter('woocommerce_no_available_payment_methods_message', 'resurs_omnicheckout_payment_gateways_check');
    add_action('woocommerce_single_product_summary', 'resurs_annuity_factors');

    if (getResursOption("showPaymentIdInOrderList")) {
        add_filter('manage_edit-shop_order_columns', 'resurs_order_column_header');
        add_action('manage_shop_order_posts_custom_column', 'resurs_order_column_info');
    }
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

function resurs_no_debit_debited($instant = false)
{
    if (!$instant) {
        $message = __(
            'It seems this order has already been finalized from an external system - if your order is finished you may update it here aswell',
            'resurs-bank-payment-gateway-for-woocommerce'
        );
    } else {
        $message = __(
            'It seems this order has been instantly finalized due to the payment method type. This means that you probably must handle it manually.',
            'resurs-bank-payment-gateway-for-woocommerce'
        );
    }

    ?>
    <div class="notice notice-error">
        <p><?php echo $message; ?></p>
    </div>
    <?php
}

function getPaymentInfo($order, $getPaymentId = '', $fallback = false)
{
    $resursPaymentIdLast = get_post_meta($order->get_id(), 'paymentIdLast', true);

    $rb = initializeResursFlow();
    $rb->setFlag('GET_PAYMENT_BY_SOAP');
    $resursPaymentInfo = null;
    try {
        $resursPaymentInfo = $rb->getPayment($getPaymentId);
    } catch (\Exception $e) {
        if (resursOption('postidreference')) {
            if ($e->getCode() === 8 && $getPaymentId !== $resursPaymentIdLast) {
                $resursPaymentInfo = getPaymentInfo($order, $resursPaymentIdLast, $fallback);
                $fallback = true;
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
 * This hook are tested from WooCommerce 2.1.5 up to WooCommcer 2.5.2
 *
 * @param WC_Order $order
 * @param null $orderDataInfoAfter
 *
 * @throws Exception
 */
function resurs_order_data_info($order = null, $orderDataInfoAfter = null)
{
    global $orderInfoShown;
    $resursPaymentInfo = null;
    $showOrderInfoAfterOption = getResursOption("showOrderInfoAfter", "woocommerce_resurs-bank_settings");
    $showOrderInfoAfter = !empty($showOrderInfoAfterOption) ? $showOrderInfoAfterOption : "AO";
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
    $orderId = $order->get_id();
    if (!empty($resursPaymentId)) {
        $hasError = "";
        try {
            /** @var $rb \Resursbank\RBEcomPHP\ResursBank */
            $rb = initializeResursFlow();
            try {
                $rb->setFlag('GET_PAYMENT_BY_SOAP');
                $resursPaymentInfo = getPaymentInfo($order, $resursPaymentId);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                if ($e->getCode() === 8) {
                    // REFERENCED_DATA_DONT_EXISTS
                    $errorMessage = __("Referenced data don't exist",
                            'resurs-bank-payment-gateway-for-woocommerce') . "<br>\n<br>\n";
                    $errorMessage .= __("This error might occur when for example a payment doesn't exist at Resurs Bank. Normally this happens when payments have failed or aborted before it can be completed",
                        'resurs-bank-payment-gateway-for-woocommerce');
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
                    $errorMessage = __('This order failed or was cancelled by customer during external actions.',
                        'resurs-bank-payment-gateway-for-woocommerce');
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
                        <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url(__FILE__) . '/img/rb_logo.png' . '"></span>
                        <fieldset>
                        <b>' . __('Following error ocurred when we tried to fetch information about the payment',
                        'resurs-bank-payment-gateway-for-woocommerce') . '</b><br>
                        <br>
                        ' . $errorMessage . '<br>
                    </fieldset>
                    </div>
                </div>
			    ';

                return;
            }

            $currentWcStatus = $order->get_status();
            $notIn = array("completed", "cancelled", "refunded");
            if (!$rb->canDebit($resursPaymentInfo) && $rb->getIsDebited($resursPaymentInfo) && !in_array($currentWcStatus,
                    $notIn)) {
                if ($rb->getInstantFinalizationStatus($resursPaymentInfo) & (RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED)) {
                    resurs_no_debit_debited(true);
                } else {
                    resurs_no_debit_debited();
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

            $renderedResursData .=
                '<div style="border: 1px solid #990000; margin-bottom: 5px; color:#000099; margin-top: 0px; background-color: #ffffff; padding: 3px; font-style: italic;">' . sprintf(__(
                    'The payment reference was never updated during the order creating, so this payment is using its fallback reference: %s.',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ), $resursPaymentIdLast) . '</div>';
        }

        $invoices = array();
        if (empty($hasError)) {

            // We no longer use WooCommerce paymentdiffs to decide what's happened to the order as - for example - a
            // partially debited and annulled order may give a falsely annulled status in the end. Instead,
            // we ask EComPHP for the most proper, current, status.
            $currentOrderStatus = ucfirst($rb->getOrderStatusStringByReturnCode($rb->getOrderStatusByPayment($resursPaymentInfo)));

            if (empty($currentOrderStatus)) {
                $currentOrderStatus = __('Not set', 'resurs-bank-payment-gateway-for-woocommerce');
                if ($rb->isFrozen($resursPaymentInfo)) {
                    $currentOrderStatus = __('Frozen', 'resurs-bank-payment-gateway-for-woocommerce');
                }
            }

            $renderedResursData .= '<div class="resurs_orderinfo_text paymentInfoWrapStatus paymentInfoHead">';
            $renderedResursData .= sprintf(
                __('Status from Resurs Bank: %s.',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ),
                $currentOrderStatus
            );
            $renderedResursData .= '</div>
                     <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url(__FILE__) . '/img/rb_logo.png' . '"></span>
                ';

            $addressInfo = "";
            if (is_object($resursPaymentInfo->customer->address)) {
                $addressInfo .= isset($resursPaymentInfo->customer->address->addressRow1) && !empty($resursPaymentInfo->customer->address->addressRow1) ? $resursPaymentInfo->customer->address->addressRow1 . "\n" : "";
                $addressInfo .= isset($resursPaymentInfo->customer->address->addressRow2) && !empty($resursPaymentInfo->customer->address->addressRow2) ? $resursPaymentInfo->customer->address->addressRow2 . "\n" : "";
                $addressInfo .= isset($resursPaymentInfo->customer->address->postalArea) && !empty($resursPaymentInfo->customer->address->postalArea) ? $resursPaymentInfo->customer->address->postalArea . "\n" : "";
                $addressInfo .= (isset($resursPaymentInfo->customer->address->country) && !empty($resursPaymentInfo->customer->address->country) ? $resursPaymentInfo->customer->address->country : "") . " " . (isset($resursPaymentInfo->customer->address->postalCode) && !empty($resursPaymentInfo->customer->address->postalCode) ? $resursPaymentInfo->customer->address->postalCode : "") . "\n";
            }
            ThirdPartyHooksSetPaymentTrigger('orderinfo', $resursPaymentId, $order->get_id());

            $unsetKeys = array(
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
                'paymentDiffs'
            );

            $renderedResursData .= '
                <br>
                <fieldset>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Payment ID', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (isset($resursPaymentInfo->id) && !empty($resursPaymentInfo->id) ? $resursPaymentInfo->id : "") .
                '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Payment method ID', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                isset($resursPaymentInfo->paymentMethodId) &&
                !empty($resursPaymentInfo->paymentMethodId) ? $resursPaymentInfo->paymentMethodId : ""
                ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Store ID', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                isset($resursPaymentInfo->storeId) &&
                !empty($resursPaymentInfo->storeId) ? $resursPaymentInfo->storeId : ""
                ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Payment method name', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                isset($resursPaymentInfo->paymentMethodName) &&
                !empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodName : ""
                ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Payment method type', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                isset($resursPaymentInfo->paymentMethodType) &&
                !empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodType : ""
                ) .
                '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Payment amount', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                isset($resursPaymentInfo->totalAmount) &&
                !empty($resursPaymentInfo->totalAmount) ? round($resursPaymentInfo->totalAmount,
                    2) : ""
                ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Payment limit', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                isset($resursPaymentInfo->limit) &&
                !empty($resursPaymentInfo->limit) ? round($resursPaymentInfo->limit, 2) : ""
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
                (isset($resursPaymentInfo->frozen) &&
                !empty($resursPaymentInfo->frozen) ?
                    $resursPaymentInfo->frozen ? __('Yes') : __('No') : __('No')
                ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Customer name',
                    'resurs-bank-payment-gateway-for-woocommerce') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (
                is_object($resursPaymentInfo->customer->address) &&
                !empty($resursPaymentInfo->customer->address->fullName) ?
                    $resursPaymentInfo->customer->address->fullName : ""
                ) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' .
                __('Delivery address', 'resurs-bank-payment-gateway-for-woocommerce') .
                ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' .
                (!empty($addressInfo) ? nl2br($addressInfo) : "") . '</span>
            ';

            if (is_array($invoices) && count($invoices)) {
                $renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">Invoices:</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . implode(", ",
                        $invoices) . '</span>
                        ';
            }

            $continueView = $resursPaymentInfo;
            foreach ($continueView as $key => $value) {
                if (in_array($key, $unsetKeys)) {
                    unset($continueView->$key);
                }
            }
            if (is_object($continueView)) {
                foreach ($continueView as $key => $value) {
                    if (!is_array($value) && !is_object($value)) {
                        $renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . ':</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (!empty($value) ? nl2br($value) : "") . '</span>
                        ';
                    } else {
                        if ($key == "metaData") {
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
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . " (" . ucfirst($subKey) . '):</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (!empty($subValue) ? nl2br($subValue) : "") . '</span>
                                ';
                            }
                        }
                    }
                }
            }
        }
        $renderedResursData .= '</fieldset>
                <p class="resurs-read-more" id="resursInfoButton"><a href="#" class="button">' . __('Read more',
                'resurs-bank-payment-gateway-for-woocommerce') . '</a></p>
                </div>
                </div>
                </div>
            ';
    }
    //}
    echo $renderedResursData;
}

/**
 * Convert version number to decimals
 *
 * @return string
 */
function rbWcGwVersionToDecimals()
{
    $splitVersion = explode(".", RB_WOO_VERSION);
    $decVersion = "";
    foreach ($splitVersion as $ver) {
        $decVersion .= str_pad(intval($ver), 2, "0", STR_PAD_LEFT);
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
function ThirdPartyHooks($type = '', $content = '', $addonData = array())
{
    $type = strtolower($type);
    $allowedHooks = array('orderinfo', 'callback');
    $paymentInfoHooks = array('orderinfo', 'callback');
    // Start with an empty content array
    $sendHookContent = array();

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
        $sendHookContent['finalized'] = isset($content->finalized) ? strtotime($content->finalized) : '';
        $sendHookContent['iscallback'] = isset($content->iscallback) ? $content->iscallback : '';
    }
    if (in_array(strtolower($type), $allowedHooks)) {
        do_action("resurs_hook_" . $type, $sendHookContent);
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
    /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
    $flow = initializeResursFlow();
    $paymentDataIn = array();
    try {
        $paymentDataIn = $flow->getPayment($paymentId);
        if ($type == "callback" && !is_null($callbackType)) {
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

    /** @var $resursFlow \Resursbank\RBEcomPHP\ResursBank */
    $resursFlow = null;
    if (hasEcomPHP()) {
        $resursFlow = initializeResursFlow();
    }
    $clientPaymentSpec = array();
    if (null !== $resursFlow) {
        $productId = wc_get_order_item_meta($item_id, '_product_id');
        $productQty = wc_get_order_item_meta($item_id, '_qty');
        $orderId = r_wc_get_order_id_by_order_item_id($item_id);

        $resursPaymentId = get_post_meta($orderId, 'paymentId', true);

        if (empty($productId)) {
            $testItemType = r_wc_get_order_item_type_by_item_id($item_id);
            $testItemName = r_wc_get_order_item_type_by_item_id($item_id);
            if ($testItemType === 'shipping') {
                $clientPaymentSpec[] = array(
                    'artNo' => '00_frakt',
                    'quantity' => 1
                );
            } elseif ($testItemType === 'coupon') {
                $clientPaymentSpec[] = array(
                    'artNo' => $testItemName . "_kupong",
                    'quantity' => 1
                );
            } elseif ($testItemType === 'fee') {
                if (function_exists('wc_get_order')) {
                    $current_order = wc_get_order($orderId);
                    $feeName = '00_' . str_replace(' ', '_', $current_order->payment_method_title) . "_fee";
                    $clientPaymentSpec[] = array(
                        'artNo' => $feeName,
                        'quantity' => 1
                    );
                } else {
                    $order_failover_test = new WC_Order($orderId);
                    $feeName = '00_' . str_replace(' ', '_',
                            $order_failover_test->payment_method_title) . "_fee";
                    $clientPaymentSpec[] = array(
                        'artNo' => $feeName,
                        'quantity' => 1
                    );
                    //die("Can not fetch order information from WooCommerce (Function wc_get_order() not found)");
                }
            }
        } else {
            $clientPaymentSpec[] = array(
                'artNo' => $productId,
                'quantity' => $productQty
            );
        }

        try {
            $order = new WC_Order($orderId);
            $removeResursRow = $resursFlow->paymentCancel($resursPaymentId, $clientPaymentSpec);
            $order->add_order_note(__('Orderline Removal: Resurs Bank API was called to remove orderlines',
                'resurs-bank-payment-gateway-for-woocommerce'));
        } catch (Exception $e) {
            $resultArray = array(
                'success' => false,
                'fail' => utf8_encode($e->getMessage())
            );
            echo $e->getMessage();
            die();
        }
        if (!$removeResursRow) {
            echo "Cancelling payment failed without a proper reason";
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

    // If updateOrderReference-setting is enabled, also look for a prior variable, to track down the correct order based on the metadata tag paymentIdLast
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
    $allFlags = array();
    $flagRow = getResursOption("devFlags");
    $flagsArray = explode(",", $flagRow);
    $multiArrayFlags = array('AUTO_DEBIT');

    if (is_array($flagsArray)) {
        foreach ($flagsArray as $flagIndex => $flagParameter) {
            $flagEx = explode("=", $flagParameter, 2);
            if (is_array($flagEx) && isset($flagEx[1])) {
                // Handle as parameter key with values
                if (!is_null($flagKey)) {
                    if (strtolower($flagEx[0]) == strtolower($flagKey)) {
                        return $flagEx[1];
                    }
                } else {
                    if (in_array($flagEx[0], $multiArrayFlags)) {
                        if (!isset($allFlags[$flagEx[0]]) || !is_array($allFlags[$flagEx[0]])) {
                            $allFlags[$flagEx[0]] = array();
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

/**
 * Get specific options from the Resurs configuration set
 *
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function resursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    /*
     * MarGul change
     * If it's demoshop it will take the config from sessions instead of db
     */
    if (isResursDemo()) {
        // Override database setting with the theme (demoshops) flowtype SESSION setting if it's set.
        if ($key == "flowtype") {
            if (!empty($_SESSION['rb_checkout_flow'])) {
                $accepted = ['simplifiedshopflow', 'resurs_bank_hosted', 'resurs_bank_omnicheckout'];
                if (in_array(strtolower($_SESSION['rb_checkout_flow']), $accepted)) {
                    return $_SESSION['rb_checkout_flow'];
                }
            }
        }

        // Override database setting with the theme (demoshops) country SESSION setting if it's set.
        if ($key == "country") {
            if (!empty($_SESSION['rb_country'])) {
                $accepted = ['se', 'dk', 'no', 'fi'];
                if (in_array(strtolower($_SESSION['rb_country']), $accepted)) {
                    return strtoupper($_SESSION['rb_country']);
                }
            }
        }

        if ($key == 'login') {
            if (!empty($_SESSION['rb_country_data'])) {
                return $_SESSION['rb_country_data']['account']['login'];
            }
        }

        if ($key == 'password') {
            if (!empty($_SESSION['rb_country_data'])) {
                return $_SESSION['rb_country_data']['account']['password'];
            }
        }
    }

    $getOptionsNamespace = get_option($namespace);
    // Going back to support PHP 5.3 instead of 5.4+
    if (isset($getOptionsNamespace[$key])) {
        $response = $getOptionsNamespace[$key];
    } else {
        // No value set
        $response = null;

        $notsetGetDefaultValue = resursFormFieldArray($namespace);
        if (isset($notsetGetDefaultValue[$key]) && isset($notsetGetDefaultValue[$key]['default'])) {
            $response = $notsetGetDefaultValue[$key]['default'];
        }
    }

    if (empty($response)) {
        $response = get_option($key);
    }
    if ($response === "true") {
        return true;
    }
    if ($response === "false") {
        return false;
    }
    if ($response === "yes") {
        return true;
    }
    if ($response === "no") {
        return false;
    }

    $filteredResponse = apply_filters('resurs_option', $response, $key);
    if (!is_null($filteredResponse) && $response !== $filteredResponse) {
        $response = $filteredResponse;
    }

    return $response;
}

/**
 * Returns true or false depending if the key exists in the resursOption-array
 *
 * @param string $key
 *
 * @return bool
 */
function issetResursOption($key = "", $namespace = 'woocommerce_resurs-bank_settings')
{
    $response = get_option($namespace);
    if (isset($response[$key])) {
        return true;
    } else {
        return false;
    }
}

/**
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function getResursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    return resursOption($key, $namespace);
}

/**
 * Function used to figure out whether values are set or not
 *
 * @param string $key
 *
 * @return bool
 */
function hasResursOptionValue($key = "", $namespace = 'woocommerce_resurs-bank_settings')
{
    $optionValues = get_option($namespace);
    if (isset($optionValues[$key])) {
        return true;
    }

    return false;
}

/**
 * Set a new value in resursoptions
 *
 * @param string $key
 * @param string $value
 * @param string $configurationSpace
 *
 * @return bool
 */
function setResursOption($key = "", $value = "", $configurationSpace = "woocommerce_resurs-bank_settings")
{
    $allOptions = get_option($configurationSpace);
    if (!empty($key)) {
        $allOptions[$key] = $value;
        update_option($configurationSpace, $allOptions);

        return true;
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
        $order_id = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
            $item_id));

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
            $order_item_type = $wpdb->get_var($wpdb->prepare("SELECT order_item_type FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                $item_id));

            return $order_item_type;
        } else {
            $order_item_name = $wpdb->get_var($wpdb->prepare("SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                $item_id));

            return $order_item_name;
        }
    }
}

/**
 * Initialize EComPHP, the key of almost everything in this plugin
 *
 * @param string $overrideUser
 * @param string $overridePassword
 * @param int $setEnvironment
 *
 * @return \Resursbank\RBEcomPHP\ResursBank
 * @throws Exception
 */
function initializeResursFlow(
    $overrideUser = "",
    $overridePassword = "",
    $setEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET
) {
    global $current_user;
    $username = resursOption("login");
    $password = resursOption("password");
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

    /** @var $initFlow \Resursbank\RBEcomPHP\ResursBank */
    $initFlow = new \Resursbank\RBEcomPHP\ResursBank($username, $password);
    $initFlow->setSimplifiedPsp(true);

    if (isResursHosted()) {
        $initFlow->setPreferredPaymentFlowService(RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW);
    }

    $sslHandler = getResursFlag("DISABLE_SSL_VALIDATION");
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

    $initFlow->setUserAgent(RB_WOO_CLIENTNAME . "-" . RB_WOO_VERSION);
    $initFlow->setEnvironment($useEnvironment);
    $initFlow->setDefaultUnitMeasure();
    if (isset($_REQUEST['testurl'])) {
        $baseUrlTest = $_REQUEST['testurl'];
        // Set this up once
        if ($baseUrlTest == "unset" || empty($baseUrlTest)) {
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
            $initFlow->setLoggedInUser(getResursWooCustomerId());
        }
    } catch (Exception $e) {
    }
    $country = getResursOption("country");
    $initFlow->setCountryByCountryCode($country);
    if ($initFlow->getCountry() == "FI") {
        $initFlow->setDefaultUnitMeasure("kpl");
    }

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
    $username = resursOption("ga_login");
    $password = resursOption("ga_password");
    if (!empty($username) && !empty($password)) {
        /** @var \Resursbank\RBEcomPHP\ResursBank $initFlow */
        $initFlow = new ResursBank($username, $password);
        $initFlow->setUserAgent(RB_WOO_CLIENTNAME . "-" . RB_WOO_VERSION);
        //$initFlow->setUserAgent( "ResursBankPaymentGatewayForWoocommerce" . RB_WOO_VERSION );
        //$initFlow->setUserAgent( "WooCommerce ResursBank Payment Gateway " . ( defined( 'RB_WOO_VERSION' ) ? RB_WOO_VERSION : "Unknown version" ) );
        $initFlow->setEnvironment(RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION);
        try {
            $getResponse = $initFlow->getAddress($ssn, $customerType, $ip);

            return $getResponse;
        } catch (Exception $e) {
            echo json_encode(array("Unavailable credentials - " . $e->getMessage()));
        }
    } else {
        echo json_encode(array("Unavailable credentials"));
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
    $demoshopMode = getResursOption('demoshopMode');

    if ($serverEnv == 'live') {
        $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
    }
    /*
     * Prohibit production mode if this is a demoshop
     */
    if ($serverEnv == 'test' || $demoshopMode == "true") {
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
 * Payment gateway destroyer.
 *
 * Only enabled in very specific environments.
 *
 * @return bool
 */
function isResursSimulation()
{
    if (!isResursTest()) {
        return repairResursSimulation();
    }
    $devResursSimulation = getResursOption("devResursSimulation");
    if ($devResursSimulation) {
        if (isset($_SERVER['HTTP_HOST'])) {
            $mustContain = array('.loc$', '.local$', '^localhost$', '.localhost$');
            $hasRequiredEnvironment = false;
            foreach ($mustContain as $hostContainer) {
                if (preg_match("/$hostContainer/", $_SERVER['HTTP_HOST'])) {
                    return true;
                }
            }
            /*
             * If you really want to force this, use one of the following variables from a define or, if in .htaccess:
             * SetEnv FORCE_RESURS_SIMULATION "true"
             * As this is invoked, only if really set to test mode, this should not be able to destroy anything in production.
             */
            if ((defined('FORCE_RESURS_SIMULATION') && FORCE_RESURS_SIMULATION === true) || (isset($_SERVER['FORCE_RESURS_SIMULATION']) && $_SERVER['FORCE_RESURS_SIMULATION'] == "true")) {
                return true;
            }
        }
    }

    return repairResursSimulation();
}

/**
 * Get current customer id
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
    if (!is_null($order)) {
        $return = $order->get_user_id();
    }

    return $return;
}

/**
 * @param bool $returnRepairState
 *
 * @return bool
 */
function repairResursSimulation($returnRepairState = false)
{
    setResursOption("devSimulateErrors", $returnRepairState);

    return $returnRepairState;
}

/********************** OMNICHECKOUT RELATED STARTS HERE ******************/

/**
 * Check if the current payment method is currently enabled and selected
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
    $currentMethod = "";
    if (isset($woocommerce->session)) {
        $currentMethod = $woocommerce->session->get('chosen_payment_method');
    }
    $flowType = resursOption("flowtype");
    $hasOmni = hasResursOmni($ignoreActiveFlag);
    if (($hasOmni == 1 || $hasOmni === true) && (!empty($currentMethod) && $flowType === $currentMethod)) {
        $returnValue = true;
    }
    /*
	 * If Omni is enabled and the current chosen method is empty, pre-select omni
	 */
    if (($hasOmni == 1 || $hasOmni === true) && $flowType === "resurs_bank_omnicheckout" && empty($currentMethod)) {
        $returnValue = true;
    }
    if ($returnValue) {
        // If the checkout is normally set to be enabled, this gives external plugins a chance to have it disabled
        $externalOmniValue = apply_filters("resursbank_temporary_disable_checkout", null);
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
 * @return array|bool
 */
function resurs_payment_status_callbacks()
{
    $callbackStatus = getResursOption('resurs_payment_status_callback');
    if (!is_array($callbackStatus)) {
        $callbackStatus = array();
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
 * Check if the omniFlow is enabled at all (through flowType)
 *
 * @param bool $ignoreActiveFlag Check this setting even though the plugin is not active
 *
 * @return bool
 */
function hasResursOmni($ignoreActiveFlag = false)
{
    $resursEnabled = resursOption("enabled");
    $flowType = resursOption("flowtype");
    if (is_admin()) {
        $omniOption = get_option('woocommerce_resurs_bank_omnicheckout_settings');
        if ($flowType == "resurs_bank_omnicheckout") {
            $omniOption['enabled'] = 'yes';
        } else {
            $omniOption['enabled'] = 'no';
        }
        update_option('woocommerce_resurs_bank_omnicheckout_settings', $omniOption);
    }
    if ($resursEnabled != "yes" && !$ignoreActiveFlag) {
        return false;
    }
    if ($flowType == "resurs_bank_omnicheckout") {
        return true;
    }

    return false;
}

/**
 * @return bool
 */
function hasResursHosted()
{
    $resursEnabled = resursOption("enabled");
    $flowType = resursOption("flowtype");
    if ($resursEnabled != "yes") {
        return false;
    }
    if ($flowType == "resurs_bank_hosted") {
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
 *
 * @return null
 */
function resurs_omnicheckout_payment_gateways_check($paymentGatewaysCheck)
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
    if (is_array($paymentGatewaysCheck)) {
        $paymentGatewaysCheck = array();
    }
    if (!count($paymentGatewaysCheck)) {
        // If there is no active payment gateways except for omniCheckout, the warning of no available payment gateways has to be suppressed
        if (isResursOmni()) {
            return null;
        }

        return __('There are currently no payment methods available', 'resurs-bank-payment-gateway-for-woocommerce');
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
        $paymentGatewaysCheck = array();
    }
    if (count($paymentGatewaysCheck) > 1) {
        return true;
    }

    return false;
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
 * Returns true if demoshop-mode is enabled.
 *
 * @return bool
 */
function isResursDemo()
{
    $resursSettings = get_option('woocommerce_resurs-bank_settings');
    $demoshopMode = isset($resursSettings['demoshopMode']) ? $resursSettings['demoshopMode'] : false;
    if ($demoshopMode === "true") {
        return true;
    }
    if ($demoshopMode === "yes") {
        return true;
    }
    if ($demoshopMode === "false") {
        return false;
    }
    if ($demoshopMode === "no") {
        return false;
    }

    return false;
}

/**
 * @param string $versionRequest
 * @param string $operator
 *
 * @return bool
 * @throws \Exception
 */
function hasWooCommerce($versionRequest = "2.0.0", $operator = ">=")
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
    if (!file_exists(getResursLogDestination() . "/resurs.log")) {
        @file_put_contents(getResursLogDestination() . "/resurs.log", time() . ": " . "Log initialization\n");
    }
    if (!file_exists(getResursLogDestination() . "/resurs.log")) {
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
 * @return bool
 */
function resursEventLogger($dataString = '')
{
    if (getResursOption('logResursEvents') && getResursLogActive()) {
        $writeFile = getResursLogDestination() . '/resurs.log';
        @file_put_contents(
            $writeFile, '[' . strftime('%Y-%m-%d %H:%M:%S', time()) . '] ' . $dataString . "\n",
            FILE_APPEND
        );
        return true;
    }
    return false;
}

if (!function_exists('getHadMisplacedIframeLocation')) {
    /**
     * Makes sure that you can reselect a deprecated setting for the iframe location
     * when using RCO if it has been selected once in a time
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

isResursSimulation();

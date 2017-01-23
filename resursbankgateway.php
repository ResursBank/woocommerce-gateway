<?php
/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Extends WooCommerce with a Resurs Bank gateway
 * Version: 1.2.7.15
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 */

define('RB_WOO_VERSION', "1.2.7.15");
define('RB_API_PATH', dirname(__FILE__) . "/rbwsdl");
define('INCLUDE_RESURS_OMNI', true);    /* Enable Resurs Bank OmniCheckout as static flow */
require_once('classes/rbapiloader.php');

if (function_exists('add_action')) {
    add_action('plugins_loaded', 'woocommerce_gateway_resurs_bank_init');
    add_action('admin_notices', 'resurs_bank_admin_notice');
}

$resursGlobalNotice = false;

/**
 * Initialize Resurs Bank Plugin
 */
function woocommerce_gateway_resurs_bank_init()
{

    if (!class_exists('WC_Payment_Gateway')) return;
    if (class_exists('WC_Resurs_Bank')) return;

    /*
     * (Very) Simplified locale and country enforcer. Do not use unless necessary, since it may break something.
     */
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
            /*
             * Follow woocommerce options. A little.
             */
            if (count($wooSpecific)) {
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


    /**
     * Localisation
     */
    load_plugin_textdomain('WC_Payment_Gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');

    /**
     * Resurs Bank Gateway class
     */
    class WC_Resurs_Bank extends WC_Payment_Gateway
    {

        protected $flow = null;

        /**
         * Constructor method for Resurs Bank plugin
         *
         * This method initializes various properties and fetches payment methods, either from the tranient API or from Resurs Bank API.
         * It is also responsible for calling generate_payment_gateways, if these need to be refreshed.
         */
        public function __construct()
        {
            global $current_user, $wpdb, $woocommerce;
            get_currentuserinfo();

            $rates = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates
				ORDER BY tax_rate_order
				LIMIT %d
				",
                1000
            ));

            hasResursOmni();

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
                    )
                ),
                'BOOKED' => array(
                    'uri_components' => array(
                        'paymentId' => 'paymentId',
                    ),
                    'digest_parameters' => array(
                        'paymentId' => 'paymentId',
                    ),
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
             * The flow configurator is only available in demo mode
             */
            if (isset($_REQUEST['flowconfig'])) {
                if (isResursDemo()) {
                    $updatedFlow = false;
                    $currentFlowType = get_option('woocommerce_resurs-bank_settings')['flowtype'];
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
                            $this->generate_payment_gateways($this->paymentMethods['methods']);
                            $methodUpdateMessage = __('Payment method gateways are updated', 'WC_Payment_Gateway') . "...\n";
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
                <input type="submit" value="' . __('Change the flow type', 'WC_Payment_Gateway') . '"><br>
                </select>
                </form>
                <a href="' . get_home_url() . '">' . __('Back to shop', 'WC_Payment_Gateway') . '</a><br>
                <a href="' . wc_get_checkout_url() . '">' . __('Back to checkout', 'WC_Payment_Gateway') . '</a><br>
                <br>
                ' . $methodUpdateMessage;
                } else {
                    echo __('Changing flows when the plugin is not in demo mode is not possible', 'WC_Payment_Gateway');
                }
                exit;
            }

            $this->flowOptions = null;

            /**
             * Load the workflow client
             */
            if (class_exists('ResursBank')) {
                if (!empty($this->login) && !empty($this->password)) {
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
                        $omniRef = $this->flow->generatePreferredId(25, "Omni");
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
                        if (wp_verify_nonce($_REQUEST['omnicheckout_nonce'], "omnicheckout")) {
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
                                    $purchareFailOrder->update_status('failed', __('Resurs Bank denied purchase', 'WC_Payment_Gateway'));
                                    WC()->session->set("resursCreatePass", 0);
                                    $returnResult['success'] = true;
                                    $returnResult['errorString'] = "Denied by Resurs";
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

            if (!empty($this->login) && !empty($this->password)) {
                $this->paymentMethods = $this->get_payment_methods();
                $pluginPaymentMethodsPath = plugin_dir_path(__FILE__) . '/includes/';

                /**
                 * Make sure all files are still there (i.e. when upgrading the plugin) - based on issue #66524
                 * Can only be run by admins.
                 */
                if (resursOption("enabled") && is_admin() && isset($this->paymentMethods['methods']) && is_array($this->paymentMethods['methods']) && count($this->paymentMethods['methods'])) {
                    foreach ($this->paymentMethods['methods'] as $methodArray) {
                        if (isset($methodArray->id)) {
                            $id = $methodArray->id;
                            if (!file_exists($pluginPaymentMethodsPath . "/resurs_bank_nr_" . $id)) {
                                $this->paymentMethods['generate_new_files'] = true;
                                break;
                            }
                        }
                    }
                }
                if (empty($this->paymentMethods['error'])) {
                    if (true === $this->paymentMethods['generate_new_files']) {
                        $this->generate_payment_gateways($this->paymentMethods['methods']);
                    }
                }
            }
            add_action('woocommerce_api_wc_resurs_bank', array($this, 'check_callback_response'));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        function isResursOmni()
        {
            return isResursOmni();
        }

        /**
         * Initialize the form fields for the plugin
         */
        function init_form_fields()
        {
            global $wpdb, $woocommerce;

            $rates = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates
				ORDER BY tax_rate_order
				LIMIT %d
				",
                1000
            ));

            $rate_select = array();

            foreach ($rates as $rate) {
                $rate_name = $rate->tax_rate_class;
                if ('' === $rate_name) {
                    $rate_name = 'standard';
                }
                $rate_name = str_replace('-', ' ', $rate_name);
                $rate_name = ucwords($rate_name);
                $rate_select[$rate->tax_rate_class] = $rate_name;
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => 'Activate Resurs Bank',
                ),
                'country' => array(
                    'title' => __('Country', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'SE' => __('Sweden', 'woocommerce'),
                        'DK' => __('Denmark', 'woocommerce'),
                        'FI' => __('Finland', 'woocommerce'),
                        'NO' => __('Norway', 'woocommerce'),
                    ),
                    'default' => 'SE',
                    'description' => __('The country for which the payment services should be used', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'flowtype' => array(
                    'title' => __('Flow type', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'simplifiedshopflow' => __('Simplified Shop Flow: Payments goes through Resurs Bank API (Default)', 'WC_Payment_Gateway'),
                        'resurs_bank_hosted' => __('Hosted Shop Flow: Customers are redirected to Resurs Bank to finalize payment', 'WC_Payment_Gateway'),
                        'resurs_bank_omnicheckout' => __('Omni Checkout: Fully integrated payment solutions based on iframes (as much as possible including initial customer data are handled by Resurs Bank without leaving the checkout page)', 'WC_Payment_Gateway'),
                    ),
                    'default' => 'simplifiedshopflow',
                    'description' => __('What kind of shop flow you want to use', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'Resurs Bank',
                    'description' => __('This controls the payment method title, which the user sees during checkout.', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => 'Betala med Resurs Bank',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'login' => array(
                    'title' => __('Web services username', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'description' => __('Resurs Bank web services username', 'WC_Payment_Gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'password' => array(
                    'title' => __('Web services password', 'WC_Payment_Gateway'),
                    'type' => 'password',
                    'default' => '',
                    'description' => __('Resurs Bank web services password', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'baseLiveURL' => array(
                    'title' => __('BaseURL Webservices Live-Environment', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => 'https://ecommerce.resurs.com/ws/V4/',
                ),
                'baseTestURL' => array(
                    'title' => __('BaseURL Webservices Test-Environment', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => 'https://test.resurs.com/ecommerce-test/ws/V4/'
                ),
                'serverEnv' => array(
                    'title' => __('Server environment', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'live' => 'Live',
                        'test' => 'Test',
                    ),
                    'default' => 'test',
                ),
                'customCallbackUri' => array(
                    'title' => __('Replacement URL for callbacks if different from default homeurl settings', 'WC_Payment_Gateway'),
                    'description' => __('If your callback URL has another URL than the defaults, you may enter the URL here. Default value is your site-URL. If this value is empty, the URL will be automatically generated.', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => '',
                ),
                'registerCallbacksButton' => array(
                    'title' => __('Register Callbacks', 'WC_Payment_Gateway'),
                    'class' => 'btn btn-primary',
                    'type' => 'submit',
                    'value' => __('Register Callbacks', 'WC_Payment_Gateway'),
                ),
                'refreshPaymentMethods' => array(
                    'title' => __('Update available payment methods', 'WC_Payment_Gateway'),
                    'class' => 'btn btn-primary',
                    'type' => 'submit',
                    'value' => __('Update available payment methods', 'WC_Payment_Gateway'),
                ),
                'priceTaxClass' => array(
                    'title' => 'Moms',
                    'type' => 'select',
                    'options' => $rate_select,
                    'description' => __('The tax rate that will be added to the payment methods', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'useSku' => array(
                    'title' => __('Use articles real id (SKU instead of the WordPress-id) whenever it is possible', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false',
                    'description' => __('This feature enables article numbers instead of the regular WordPress post ids that is usually used during payment booking. Note: If there is no article number (SKU) set, this function will fall back to the regular ID', 'WC_Payment_Gateway'),
                    'desc_tip' => false,
                ),
                'reduceOrderStock' => array(
                    'title' => __('During payment process, also handle order by reducing order stock', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false',
                    'description' => __('Defines whether the plugin should wait for the fraud control when booking payments, or not', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'waitForFraudControl' => array(
                    'title' => 'waitForFraudControl',
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false',
                    'description' => __('Defines whether the plugin should wait for the fraud control when booking payments, or not', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'annulIfFrozen' => array(
                    'title' => 'annulIfFrozen',
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false',
                    'description' => __('Defines if a payment should be annulled immediately if Resurs Bank returns a FROZEN state', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'finalizeIfBooked' => array(
                    'title' => 'finalizeIfBooked',
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false',
                    'description' => __('Defines if a payment should be debited immediately on a booked payment', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'adminRestoreGatewaysWhenMissing' => array(
                    'title' => __('Restoring Payment Method gateway files', 'woocommerce'),
                    'description' => __('If a payment gateway file (in the includes folder) is missing, they will be restored automatically if they disappear (e.g. when upgrading the plugin). Checking this box limits automatic restorations, so they only gets activates when administrators are logged in', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'label' => __('Only administrators may restore gateway files'),
                ),
                'costOfPurchaseCss' => array(
                    'title' => __('URL to custom CSS for costOfPurchase', 'WC_Payment_Gateway'),
                    'description' => __('Define your custom CSS for the cost of purchase example (if empty, a default file will be used)', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => home_url("/") . "wp-content/plugins/resurs-bank-payment-gateway-for-woocommerce/css/costofpurchase.css",
                ),
                'handleNatConnections' => array(
                    'title' => __('Handle NAT connections', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false',
                    'description' => __('Defines if the plugin should perform a simple check against proxies on customer ip addresses (Not recommended to activate since it opens up for exploits, but if you have many connecting customers that seem to be on NATed networks, this may help a bit)', 'WC_Payment_Gateway'),
                    'desc_tip' => false,
                ),
                'getAddress' => array(
                    'title' => __('getAddressBox Enabled', 'WC_Payment_Gateway'),
                    'description' => __('If enabled, a box for social security numbers will be shown on the checkout. For Sweden, there will also be a capability to retrieve the customer home address, while active.', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'true'
                ),
                'streamlineBehaviour' => array(
                    'title' => __('Streamlined customer field behaviour', 'WC_Payment_Gateway'),
                    'description' => __('Fields that are required to complete an order from Resurs Bank, are hidden when active, since the fields required for Resurs Bank are inherited from WooCommerce fields by default.', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'true'
                ),
                'uglifyResursAdmin' => array(
                    'title' => __('Bootstrap buttons in Resurs Configuration', 'WC_Payment_Gateway'),
                    'description' => __('Using bootstrap in Resurs configuration will change the look of Resurs Bank administration interface', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false'
                ),
                'demoshopMode' => array(
                    'title' => __('Demoshopläge', 'WC_Payment_Gateway'),
                    'description' => __('Define if this shop is a demo store or not, which opens for more functionality (This option also forces the use of test environment)', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false'
                ),
                'getAddressUseProduction' => array(
                    'title' => __('Make getAddress fetch live data while in test mode', 'WC_Payment_Gateway'),
                    'description' => __('If enabled, live data will be available on getAddress-requests while in demo shop. Credentials for production are required. Feature does not work for Omni Checkout.', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false'
                ),
                'ga_login' => array(
                    'title' => __('Web services username (getAddress/Production)', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'description' => __('Resurs Bank web services username (getaddress/Production)', 'WC_Payment_Gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'ga_password' => array(
                    'title' => __('Web services password (getAddress/Production)', 'WC_Payment_Gateway'),
                    'type' => 'password',
                    'default' => '',
                    'description' => __('Resurs Bank web services password (getAddress/Production)', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'randomizeJsLoaders' => array(
                    'title' => __('Prevent caching of included javascripts', 'WC_Payment_Gateway'),
                    'description' => __('Enable this feature, if resursbank.js tend to cache older versions even after the codebase are updated', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false'
                ),
            );

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

            if (defined('INCLUDE_RESURS_OMNI') && INCLUDE_RESURS_OMNI !== true && isset($this->form_fields['flowtype']) && isset($this->form_fields['flowtype']['options']) && is_array($this->form_fields['flowtype']['options']) && isset($this->form_fields['flowtype']['options']['resurs_bank_omnicheckout'])) {
                unset($this->form_fields['flowtype']['options']['resurs_bank_omnicheckout']);
            }

        }

        /**
         * Check the callback event received and perform the appropriate action
         */
        public function check_callback_response()
        {
            global $woocommerce, $wpdb;

            $url_arr = parse_url($_SERVER["REQUEST_URI"]);
            $url_arr['query'] = str_replace('amp;', '', $url_arr['query']);
            parse_str($url_arr['query'], $request);

            if (!count($request) && isset($_GET['event-type'])) {
                $request = $_GET;
            }
            $event_type = $request['event-type'];
            if ($event_type === 'check_signing_response') {
                $this->check_signing_response();
                return;
            }
            if ($event_type === "prepare-omni-order") {
                $this->prepare_omni_order();
                return;
            }

            $currentSalt = get_transient('resurs_bank_digest_salt');
            if ($event_type == 'AUTOMATIC_FRAUD_CONTROL') {
                $check_digest = $request['paymentId'] . $request['result'] . $currentSalt;
            } else {
                $check_digest = $request['paymentId'] . get_transient('resurs_bank_digest_salt');
            }
            $check_digest = sha1($check_digest);
            $check_digest = strtoupper($check_digest);
            if ($request['digest'] !== $check_digest) {
                header('HTTP/1.1 406 Digest not accepted', true, 406);
                exit;
            }
            $args = array(
                'post_type' => 'shop_order',
                'meta_key' => 'paymentId',
                'meta_value' => $request['paymentId'],
            );
            $my_query = new WP_Query($args);
            $orderId = $my_query->posts[0]->ID;
            if (!$orderId) {
                $orderId = wc_get_order_id_by_payment_id($request['paymentId']);
            }
            $order = new WC_Order($orderId);

            switch ($event_type) {
                case 'UNFREEZE':
                    $order->update_status('processing');
                    break;
                case 'AUTOMATIC_FRAUD_CONTROL':
                    switch ($request['result']) {
                        case 'THAWED':
                            $order->update_status('processing', __('The Resurs Bank event AUTOMATIC_FRAUD_CONTROL returned THAWED', 'WC_Payment_Gateway'));
                            break;
                        case 'FROZEN':
                            $order->update_status('on-hold', __('The Resurs Bank event AUTOMATIC_FRAUD_CONTROL returned FROZEN', 'WC_Payment_Gateway'));
                            break;
                        default:
                            break;
                    }
                    break;
                case 'TEST':
                    break;
                case 'ANNULMENT':
                    $order->update_status('cancelled');
                    $order->cancel_order(__('ANNULMENT event received from Resurs Bank', 'WC_Payment_Gateway'));
                    break;
                case 'FINALIZATION':
                    $order->update_status('completed', __('FINALIZATION event received from Resurs Bank', 'WC_Payment_Gateway'));
                    $order->payment_complete();
                    break;
                case 'BOOKED':
                    $order->update_status('processing', __('BOOKED event received from Resurs Bank', 'WC_Payment_Gateway'));
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
                    // Currently unsupported
                default:
                    break;
            }
            $path = plugin_dir_path(__FILE__) . '/dump/WC_Resurs_Bank_callback_response_' . $event_type . '_.html';
            $path = str_replace('//', '/', $path);
            header('HTTP/1.0 204 No Response');
        }

        /**
         * Register a callback event (EComPHP)
         *
         * @param  string $type The callback type to be registered
         * @param  array $options The parameters for the SOAP request
         * @throws Exception
         */
        public function register_callback($type, $options)
        {
            if (false === is_object($this->flow)) {
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
                $uriTemplate .= '&digest={digest}';
                $uriTemplate = str_replace('/&/', '&', $uriTemplate);
                $callbackType = $this->flow->getCallbackTypeByString($type);
                $this->flow->setCallbackDigest(get_transient('resurs_bank_digest_salt'));
                $this->flow->setCallback($callbackType, $uriTemplate);
            } catch (Exception $e) {
                throw $e;
            }
        }

        /**
         * Get digest parameters for register callback
         *
         * @param  array $params The parameters
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
         * @return boolean          Return whether or not the action succeeded
         */
        public function init_webservice($username = '', $password = '')
        {
            try {
                $this->flow = initializeResursFlow();
            } catch (Exception $initFlowException) {
                return false;
            }
            return true;
        }

        /**
         * Payment spec functions is a part of the bookPayment functions
         */

        /**
         * Get specLines for startPaymentSession
         *
         * @param  array $cart WooCommerce cart containing order items
         * @return array       The specLines for startPaymentSession
         */
        protected static function get_spec_lines($cart)
        {
            $spec_lines = array();
            foreach ($cart as $item) {
                $data = $item['data'];
                $_tax = new WC_Tax();//looking for appropriate vat for specific product
                $rates = array_shift($_tax->get_rates($data->get_tax_class()));
                $vatPct = (double)$rates['rate'];
                $totalVatAmount = ($data->get_price_excluding_tax() * ($vatPct / 100));
                $setSku = $data->get_sku();
                $bookArtId = $data->id;
                if (resursOption("useSku") && !empty($setSku)) {
                    $bookArtId = $setSku;
                }
                $spec_lines[] = array(
                    'id' => $data->id,
                    'artNo' => $bookArtId,
                    'description' => (empty($data->post->post_title) ? 'Beskrivning' : $data->post->post_title),
                    'quantity' => $item['quantity'],
                    'unitMeasure' => 'st',
                    'unitAmountWithoutVat' => $data->get_price_excluding_tax(),
                    'vatPct' => $vatPct,
                    'totalVatAmount' => ($data->get_price_excluding_tax() * ($vatPct / 100)),
                    'totalAmount' => (($data->get_price_excluding_tax() * $item['quantity']) + ($totalVatAmount * $item['quantity'])),
                );
            }
            return $spec_lines;
        }

        /**
         * Get and convert payment spec from cart, convert it to Resurs Specrows
         * @param $cart WooCommerce Cart containing order items
         * @param bool $specLinesOnly Return only the array of speclines
         * @return array The paymentSpec for startPaymentSession
         */
        protected static function get_payment_spec($cart, $specLinesOnly = false)
        {
            global $woocommerce;

            //$payment_fee_tax_pct = 0;   // TODO: Figure out this legacy variable, that was never initialized.
            $spec_lines = self::get_spec_lines($cart->get_cart());
            $shipping = (float)$cart->shipping_total;
            $shipping_tax = (float)$cart->shipping_tax_total;
            $shipping_total = (float)($shipping + $shipping_tax);
            /*
             * Compatibility (Discovered in PHP7)
             */
            $shipping_tax_pct = (!is_nan(@round($shipping_tax / $shipping, 2) * 100) ? @round($shipping_tax / $shipping, 2) * 100 : 0);

            if (false === empty($shipping)) {
            }
            $spec_lines[] = array(
                'id' => 'frakt',
                'artNo' => '00_frakt',
                'description' => 'Frakt',
                'quantity' => '1',
                'unitMeasure' => 'st',
                'unitAmountWithoutVat' => $shipping,
                'vatPct' => $shipping_tax_pct,
                'totalVatAmount' => $shipping_tax,
                'totalAmount' => $shipping_total,
            );
            $payment_method = $woocommerce->session->chosen_payment_method;
            $payment_options = get_option('woocommerce_' . $payment_method . '_settings');
            $payment_fee = get_option('woocommerce_' . $payment_method . '_settings')['price'];
            $payment_fee = (float)(isset($payment_fee) ? $payment_fee : '0');
            $payment_fee_tax_class = get_option('woocommerce_resurs-bank_settings')['priceTaxClass'];
            $payment_fee_tax_class_rates = $cart->tax->get_rates($payment_fee_tax_class);
            $payment_fee_tax = $cart->tax->calc_tax($payment_fee, $payment_fee_tax_class_rates, false, true);
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
                //$resursPriceDescription = sanitize_title($payment_options['priceDescription']);
                foreach ($fees as $fee) {
                    /*
                     * Ignore this fee if it matches the Resurs description.
                     */
                    //if ($fee == $resursPriceDescription) { continue; }
                    $rate = ($fee->tax / $fee->amount) * 100;
                    $spec_lines[] = array(
                        'id' => $fee->id,
                        'artNo' => $fee->id,
                        'description' => $fee->name,
                        'quantity' => 1,
                        'unitMeasure' => 'st',
                        'unitAmountWithoutVat' => $fee->amount,
                        'vatPct' => !is_nan($rate) ? $rate: 0,
                        'totalVatAmount' => $fee->tax,
                        'totalAmount' => $fee->amount + $fee->tax,
                    );
                }
            }
            if ($cart->coupons_enabled()) {
                $coupons = $cart->get_coupons();
                if (count($coupons) > 0) {
                    $coupon_values = $cart->coupon_discount_amounts;
                    $coupon_tax_values = $cart->coupon_discount_tax_amounts;
                    foreach ($coupons as $code => $coupon) {
                        $post = get_post($coupon->id);
                        $spec_lines[] = array(
                            'id' => $coupon->id,
                            'artNo' => $coupon->code . '_' . 'kupong',
                            'description' => $post->post_excerpt,
                            'quantity' => 1,
                            'unitMeasure' => 'st',
                            'unitAmountWithoutVat' => (0 - (float)$coupon_values[$code]) + (0 - (float)$coupon_tax_values[$code]),
                            'vatPct' => 0,
                            'totalVatAmount' => 0,
                            'totalAmount' => (0 - (float)$coupon_values[$code]) + (0 - (float)$coupon_tax_values[$code]),
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
         * @return array
         */
        protected static function calculateSpecLineAmount($specLine = array())
        {
            $setPaymentSpec = array();
            if (is_array($specLine) && count($specLine)) {
                foreach ($specLine as $row) {
                    $setPaymentSpec['totalAmount'] += $row['totalAmount'];
                    $setPaymentSpec['totalVatAmount'] += $row['totalVatAmount'];
                }
            }
            return $setPaymentSpec;
        }

        protected static function resurs_hostedflow_create_payment()
        {
            global $woocommerce;
            $flow = initializeResursFlow();
            $flow->setPreferredPaymentService(ResursMethodTypes::METHOD_HOSTED);
            $flow->Include = array();
        }

        /**
         * Function formerly known as the forms session, where forms was created from a response from Resurs.
         * From now on, we won't get any returned values from this function. Instead, we'll create the form at this
         * level.
         *
         * @param  int $payment_id The chosen payment method
         */
        public function start_payment_session($payment_id, $method_class = null)
        {
            global $woocommerce;
            $this->flow = initializeResursFlow();
            $currentCountry = resursOption('country');
            $regExRules = array();

            $cart = $woocommerce->cart;
            $paymentSpec = $this->get_payment_spec($cart);
            $totalAmount = $paymentSpec['totalAmount'];
            $methodList = get_transient("resurs_bank_payment_methods");

            $fieldGenHtml = "";
            foreach ($methodList as $methodIndex => $method) {
                $id = $method->id;
                $min = $method->minLimit;
                $max = $method->maxLimit;
                $customerType = $method->customerType;
                $specificType = $method->specificType;
                //$description = $method->description;

                $inheritFields = array(
                    'applicant-email-address' => 'billing_email',
                    'applicant-mobile-number' => 'billing_phone_field',
                    'applicant-telephone-number' => 'billing_phone_field'
                );
                $labels = array(
                    'contact-government-id' => __('Contact government id', 'WC_Payment_Gateway'),
                    'applicant-government-id' => __('Applicant government ID', 'WC_Payment_Gateway'),
                    'applicant-full-name' => __('Applicant full name', 'WC_Payment_Gateway'),
                    'applicant-email-address' => __('Applicant email address', 'WC_Payment_Gateway'),
                    'applicant-telephone-number' => __('Applicant telephone number', 'WC_Payment_Gateway'),
                    'applicant-mobile-number' => __('Applicant mobile number', 'WC_Payment_Gateway'),
                    'card-number' => __('Card number', 'WC_Payment_Gateway'),
                );
                $minMaxError = false;
                if ($totalAmount >= $min && $totalAmount <= $max) {
                    try {
                        $regExRules = $this->flow->getRegEx('', $currentCountry, $customerType);
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                    if (strtolower($id) == strtolower($payment_id)) {
                        $requiredFormFields = $this->flow->getTemplateFieldsByMethodType($method, $customerType, $specificType);
                        $buttonCssClasses = "btn btn-info active";
                        $ajaxUrl = admin_url('admin-ajax.php');
                        if (!isResursHosted()) {
                            $fieldGenHtml .= '<div>' . $method_class->description . '</div>';
                            foreach ($requiredFormFields['fields'] as $fieldName) {
                                $doDisplay = "block";
                                if (resursOption("streamlineBehaviour")) {
                                    if ($this->flow->canHideFormField($fieldName)) {
                                        $doDisplay = "none";
                                    }
                                    /*
                                     * As we do get the applicant government id from the getaddress field, we don't have to show that here.
                                     */
                                    if ($fieldName == "applicant-government-id") {
                                        /*
                                         * But only if it is enabled
                                         */
                                        if (resursOption("getAddress")) {
                                            $doDisplay = "none";
                                        }
                                    }
                                }
                                $fieldGenHtml .= '<div style="display:' . $doDisplay . ';width:100%;" class="resurs_bank_payment_field_container">';
                                $fieldGenHtml .= '<label for="' . $fieldName . '" style="width:100%;display:block;">' . $labels[$fieldName] . '</label>';
                                $fieldGenHtml .= '<input id="' . $fieldName . '" type="text" name="' . $fieldName . '">';
                                $fieldGenHtml .= '</div>';
                            }

                            $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
                            if ($specificType != "CARD") {
                                $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes\')">' . __('Read more', 'WC_Payment_Gateway') . '</button>';
                            }
                            // Fix: There has been an echo here, instead of a fieldGenHtml
                            $fieldGenHtml .= '<input type="hidden" value="' . $id . '" class="resurs-bank-payment-method">';
                        } else {
                            $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
                            $fieldGenHtml = $this->description . "<br><br>";
                            if ($specificType != "CARD") {
                                $fieldGenHtml .= '<button type="button" class="' . $buttonCssClasses . '" onClick="window.open(\'' . $costOfPurchase . '&method=' . $method->id . '&amount=' . $cart->total . '\', \'costOfPurchasePopup\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes\')">' . __('Read more', 'WC_Payment_Gateway') . '</button>';
                            }
                        }
                    }
                } else {
                    $minMaxError = true;
                }
            }
            if (!empty($fieldGenHtml)) {
                echo $fieldGenHtml;
            } else {
                if (isResursTest()) {
                    if ($minMaxError) {
                        echo __('Your environment is currently in test mode and the payment amount is lower or higher that the payment method allows.<br>In production mode, this payment method will be hidden.', 'WC_Payment_Gateway');
                    }
                }
            }
        }

        /**
         * Proccess the payment
         *
         * @param  int $order_id WooCommerce order ID
         * @return null|array    Null on failure, array on success
         */
        public function process_payment($order_id)
        {
            global $woocommerce;
            /*
             * Skip procedure of process_payment if the session is based on a finalizing omnicheckout.
             */
            if (defined('OMNICHECKOUT_PROCESSPAYMENT')) {
                return;
            }
            $order = new WC_Order($order_id);
            $customer = $woocommerce->customer;
            $className = $_REQUEST['payment_method'];

            $payment_settings = get_option('woocommerce_' . $className . '_settings');
            $this->flow = initializeResursFlow();
            $bookDataArray = array();

            $bookDataArray['address'] = array(
                'fullName' => $_REQUEST['billing_last_name'] . ' ' . $_REQUEST['billing_first_name'],
                'firstName' => $_REQUEST['billing_first_name'],
                'lastName' => $_REQUEST['billing_last_name'],
                'addressRow1' => $_REQUEST['billing_address_1'],
                'addressRow2' => (empty($_REQUEST['billing_address_2']) ? '' : $_REQUEST['billing_address_2']),
                'postalArea' => $_REQUEST['billing_city'],
                'postalCode' => $_REQUEST['billing_postcode'],
                'country' => $_REQUEST['billing_country'],
            );
            if (isset($_REQUEST['ship_to_different_address'])) {
                $bookDataArray['deliveryAddress'] = array(
                    'fullName' => $_REQUEST['shipping_last_name'] . ' ' . $_REQUEST['shipping_first_name'],
                    'firstName' => $_REQUEST['shipping_first_name'],
                    'lastName' => $_REQUEST['shipping_last_name'],
                    'addressRow1' => $_REQUEST['shipping_address_1'],
                    'addressRow2' => (empty($_REQUEST['shipping_address_2']) ? '' : $_REQUEST['shipping_address_2']),
                    'postalArea' => $_REQUEST['shipping_city'],
                    'postalCode' => $_REQUEST['shipping_postcode'],
                    'country' => $_REQUEST['shipping_country'],
                );
            }
            if (empty($_REQUEST['shipping_address_2'])) {
                unset($bookDataArray['deliveryAddress']['addressRow2']);
            };
            if (empty($_REQUEST['billing_address_2'])) {
                unset($bookDataArray['address']['addressRow2']);
            };

            $preferredId = $this->flow->getPreferredId(25);

            /* Generate successUrl for the signing (Legacy) */
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
            //$success_url = add_query_arg( 'uniq', '$uniqueId', $success_url );

            $bookDataArray['uniqueId'] = sha1(uniqid(microtime(true), true));
            $bookDataArray['signing'] = array(
                'successUrl' => $success_url,
                'failUrl' => html_entity_decode($order->get_cancel_order_url()),
                'forceSigning' => false
            );

            /*
             * Payment defaults
             */
            $bookDataArray['paymentData'] = array(
                'waitForFraudControl' => resursOption('waitForFraudControl'),
                'annulIfFrozen' => resursOption('annulIfFrozen'),
                'finalizeIfBooked' => resursOption('finalizeIfBooked'),
                'preferredId' => $preferredId
            );
            $shortMethodName = str_replace('resurs_bank_nr_', '', $className);
            $cart = $woocommerce->cart;
            $paymentSpec = $this->get_payment_spec($cart, true);

            /* Since we need to figure out */
            $methodSpecification = $this->getTransientMethod($shortMethodName);

            $bookDataArray['specLine'] = $paymentSpec;
            $bookDataArray['customer'] = array(
                'governmentId' => $_REQUEST['applicant-government-id'],
                'phone' => $_REQUEST['applicant-telephone-number'],
                'email' => $_REQUEST['applicant-email-address'],
                'type' => $_REQUEST['ssnCustomerType']
            );

            if (isset($methodSpecification->specificType) && ($methodSpecification->specificType == "REVOLVING_CREDIT" || $methodSpecification->specificType == "CARD")) {
                $bookDataArray['customer']['governmentId'] = isset($_REQUEST['applicant-government-id']) ? $_REQUEST['applicant-government-id'] : "";
                $bookDataArray['customer']['type'] = isset($_REQUEST['ssnCustomerType']) ? $_REQUEST['ssnCustomerType'] : "";

                if ($methodSpecification->specificType == "REVOLVING_CREDIT") {
                    $this->flow->prepareCardData(null, true);
                } else {
                    $cardNumber = $_REQUEST['card-number'];
                    $this->flow->prepareCardData($cardNumber, false);
                }
            }
            if ($methodSpecification->customerType == "LEGAL") {
                $bookDataArray['customer']['contactGovernmentId'] = $_REQUEST['contactGovernmentId'];
            }
            if (isset($_REQUEST['applicant-mobile-number']) && !empty($_REQUEST['applicant-mobile-number'])) {
                $bookDataArray['customer']['cellPhone'] = $_REQUEST['applicant-mobile-number'];
            }

            try {
                if (isResursHosted()) {
                    /**
                     * Inherit some data from request
                     */
                    if (isset($_REQUEST['ssn_field']) && !empty($_REQUEST['ssn_field'])) {
                        $bookDataArray['customer']['governmentId'] = $_REQUEST['ssn_field'];
                    }
                    if (isset($_REQUEST['billing_phone'])) {
                        $bookDataArray['customer']['phone'] = $_REQUEST['billing_phone'];
                    }
                    if (isset($_REQUEST['billing_email'])) {
                        $bookDataArray['customer']['email'] = $_REQUEST['billing_email'];
                    }
                    if (isset($_REQUEST['ssnCustomerType'])) {
                        $bookDataArray['customer']['type'] = $_REQUEST['ssnCustomerType'];
                    }
                    $bookDataArray['paymentData']['preferredId'] = $preferredId;
                    $this->flow->setPreferredPaymentService(ResursMethodTypes::METHOD_HOSTED);
                    $failBooking = false;
                    $hostedFlowUrl = null;
                    try {
                        $hostedBookPayment = $this->flow->bookPayment($shortMethodName, $bookDataArray, true, false);
                        $hostedFlowUrl = $hostedBookPayment;
                    } catch (ResursException $hostedException) {
                        $failBooking = true;
                    }
                    $jsonObject = $this->flow->getBookedJsonObject(ResursMethodTypes::METHOD_HOSTED);
                    $successUrl = null;
                    $failUrl = null;
                    if (isset($jsonObject->successUrl)) {
                        $successUrl = $jsonObject->successUrl;
                    }
                    if (isset($jsonObject->failUrl)) {
                        $failUrl = $jsonObject->failUrl;
                    }
                    if (!$failBooking && !empty($hostedFlowUrl)) {
                        $order->update_status('pending');
                        $bookedStatus = 'FROZEN';
                        update_post_meta($order_id, 'paymentId', $preferredId);
                        return array(
                            'result' => 'success',
                            'redirect' => $hostedFlowUrl
                        );
                    } else {
                        $order->update_status('failed', __('An error occured during the update of the booked payment (hostedFlow) - the payment id which was never received properly', 'WC_Payment_Gateway'));
                        return array(
                            'result' => 'failure',
                            'redirect' => $failUrl
                        );
                    }
                } else {
                    $bookPaymentResult = $this->flow->bookPayment($shortMethodName, $bookDataArray, true, true);
                }
            } catch (Exception $bookPaymentException) {
                wc_add_notice(__($bookPaymentException->getMessage(), 'WC_Payment_Gateway'), 'error');
                return;
            }

            $bookedStatus = $this->flow->getBookedStatus($bookPaymentResult);
            $bookedPaymentId = $this->flow->getBookedPaymentId($bookPaymentResult);
            /* Make sure that we have a confirmed paymentId-link to the booked payment */
            if ($bookedPaymentId) {
                update_post_meta($order_id, 'paymentId', $bookedPaymentId);
            } else {
                /* When things fail */
                $bookedStatus = "FAILED";
            }
            /* Simplified responses */
            switch ($bookedStatus) {
                case 'FINALIZED':
                    $order->update_status('completed');
                    if (resursOption('reduceOrderStock')) {
                        $order->reduce_order_stock();
                    }
                    WC()->cart->empty_cart();
                    return array('result' => 'success', 'redirect' => $this->get_return_url($order));
                    break;
                case 'BOOKED':
                    $order->update_status('processing');
                    if (resursOption('reduceOrderStock')) {
                        $order->reduce_order_stock();
                    }
                    WC()->cart->empty_cart();
                    return array('result' => 'success', 'redirect' => $this->get_return_url($order));
                    break;
                case 'FROZEN':
                    $order->update_status('on-hold');
                    if (resursOption('reduceOrderStock')) {
                        $order->reduce_order_stock();
                    }
                    WC()->cart->empty_cart();
                    return array('result' => 'success', 'redirect' => $this->get_return_url($order));
                    break;
                case 'SIGNING':
                    return array('result' => 'success', 'redirect' => $this->flow->getBookedSigningUrl($bookPaymentResult));
                    break;
                case 'DENIED':
                    $order->update_status('failed');
                    wc_add_notice(__('The payment can not complete. Contact customer services for more information.', 'WC_Payment_Gateway'), 'error');
                    return;
                    break;
                case 'FAILED':
                    $order->update_status('failed', __('An error occured during the update of the booked payment. The payment ID was never received properly in the payment process', 'WC_Payment_Gateway'));
                    wc_add_notice(__('An unknown error occured. Please, try again later', 'WC_Payment_Gateway'), 'error');
                    return;
                    break;
                default:
                    wc_add_notice(__('An unknown error occured. Please, try again later', 'WC_Payment_Gateway'), 'error');
                    return;
                    break;
            }
        }

        /**
         * Get specific payment method object, from transient
         * @param string $methodId
         * @return array
         */
        public function getTransientMethod($methodId = '')
        {
            $methodList = get_transient("resurs_bank_payment_methods");
            if (is_array($methodList)) {
                foreach ($methodList as $methodArray) {
                    if (strtolower($methodArray->id) == strtolower($methodId)) {
                        return $methodArray;
                    }
                }
            }
            return array();
        }

        public function error_prepare_omni_order($error)
        {
            return $error;
        }

        public function prepare_omni_order()
        {
            /*
             * Get incoming request.
             */
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
            /*
             * Generate the json-data
             */
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

            if (!count($customerData)) {
                $returnResult['errorString'] = "No customer data set";
                $returnResult['errorCode'] = "404";
                $this->returnJsonResponse($returnResult);
            }

            $responseCode = 0;
            $allowOrderCreation = false;

            /*
             * Without the nonce, no background order can prepare
             */
            if (isset($_REQUEST['omnicheckout_nonce'])) {
                if (wp_verify_nonce($_REQUEST['omnicheckout_nonce'], "omnicheckout")) {
                    $hasInternalErrors = false;
                    $returnResult['verified'] = true;

                    /*
                     * This procedure normally works.
                     */
                    $testLocalOrder = wc_get_order_id_by_payment_id($requestedPaymentId);
                    if ((empty($testLocalOrder) && $requestedUpdateOrder) || (!is_numeric($testLocalOrder) && is_numeric($testLocalOrder) && $testLocalOrder != $requestedUpdateOrder)) {
                        $testLocalOrder = $requestedUpdateOrder;
                    }

                    $returnResult['resursData']['locId'] = $requestedPaymentId;

                    /*
                     * If the order has already been created, the user may have been clicking more than one time in the frame,
                     * eventually due to payment method changes.
                     */
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
                        /*
                         * Helper for "sameAddress"-cases.
                         */
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
                            /*
                             * As we work with the session, we'd try to get the current order that way.
                             * process_checkout() does a lot of background work for this.
                             */
                            $internalErrorMessage = "";
                            $internalErrorCode = 0;
                            try {
                                //$resursOrder->must_create_account = false;
                                $processCheckout = $resursOrder->process_checkout();
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
                                /*
                                 * Pick up the class for Omni to set up a proper payment method in the order.
                                 */
                                $omniClass = new WC_Gateway_ResursBank_Omni();
                                $order->set_payment_method($omniClass);
                                $order->set_address($wooBillingAddress, 'billing');
                                $order->set_address($wooDeliveryAddress, 'shipping');
                                // This creates extra confirmation mails during the order process, which may cause probles on denied checked
                                //$order->update_status('on-hold', __('The payment are waiting for confirmation from Resurs Bank', 'WC_Payment_Gateway'));
                                update_post_meta($orderId, 'paymentId', $requestedPaymentId);
                                update_post_meta($orderId, 'omniPaymentMethod', $omniPaymentMethod);
                                $hasInternalErrors = false;
                                $internalErrorMessage = null;
                                //WC()->session->set('omniRef', null);
                                // Running through process_payment fixes the empty-cart. And that's a better way, since if
                                // errors occurs on this level, we won't empty the cart on errors.
                                //WC()->cart->empty_cart();
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
                        /*
                         * If the order already exists, continue without errors (if we reached this code, it has been because of the nonce which should be considered safe enough)
                         */
                        $order = new WC_Order($testLocalOrder);
                        $order->set_address($wooBillingAddress, 'billing');
                        $order->set_address($wooDeliveryAddress, 'shipping');
                        $returnResult['success'] = true;
                        $returnResult['hasOrder'] = true;
                        $returnResult['usingOrder'] = $testLocalOrder;
                        $returnResult['errorString'] = "Order already exists";
                        $returnResult['errorCode'] = 200;
                        $responseCode = 200;
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
            $order = new WC_Order($order_id);
            $paymentId = wc_get_payment_id_by_order_id($order_id);
            $isHostedFlow = false;
            $requestedPaymentId = $request['payment_id'];
            $hasBookedHostedPayment = false;
            $bookedPaymentId = 0;
            $bookStatus = null;
            if (isset($request['flow-type'])) {
                if ($request['flow-type'] == "check_hosted_response") {
                    if (isResursHosted()) {
                        $isHostedFlow = true;
                        $bookedPaymentId = $requestedPaymentId;
                        try {
                            $paymentInfo = $this->flow->getPayment($requestedPaymentId);
                        } catch (Exception $e) {
                        }
                        $bookStatus = "BOOKED";
                        /* If we can't credit nor debig the order it may have been annulled, and therefore we should fail this */
                        if (!$this->flow->canCredit($paymentInfo) && !$this->flow->canDebit($paymentInfo)) {
                            $bookStatus = "FAILED";
                        }
                        /* If we still can credit but not credit the order, it may be finalized (if we can both credit and debit then it's not completely finalized) */
                        if ($this->flow->canCredit($paymentInfo) && !$this->flow->canDebit($paymentInfo)) {
                            $bookStatus = "FINALIZED";
                        }
                        if (isset($paymentInfo->frozen)) {
                            $bookStatus = 'FROZEN';
                        }
                    }
                } else if (($_REQUEST['flow-type'] == "check_omni_response" || $request['flow-type'] == "check_omni_response")) {
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

                    if ($request['failInProgress'] == "1" || isset($_REQUEST['failInProgress']) && $_REQUEST['failInProgress'] == "1") {
                        $order->update_status('failed', __('The payment failed during purchase', 'WC_Payment_Gateway'));
                        wc_add_notice( __("The purchase from Resurs Bank was by some reason not accepted. Please contact customer services, or try again with another payment method.", 'WC_Payment_Gateway'), 'error' );
                        WC()->session->set("order_awaiting_payment");
                        $getRedirectUrl = $woocommerce->cart->get_cart_url();
                    } else {
                        if (resursOption('reduceOrderStock')) {
                            /*
                             * While waiting for the order confirmation from Resurs Bank, reducing stock may be necessary, anyway.
                             */
                            $order->reduce_order_stock();
                        }
                        $order->update_status('processing', __('The payment are signed and booked', 'WC_Payment_Gateway'));
                        $getRedirectUrl = $this->get_return_url($order);
                        WC()->cart->empty_cart();
                    }

                    wp_safe_redirect($getRedirectUrl);
                    return;
                }
            }

            if ($paymentId != $requestedPaymentId && !$isHostedFlow) {
                $order->update_status('failed');
                wc_add_notice(__('The payment can not complete. Contact customer services for more information.', 'WC_Payment_Gateway'), 'error');
            }

            $bookSigned = false;
            if (!$isHostedFlow) {
                try {
                    /* try book a signed payment */
                    $signedResult = $this->flow->bookSignedPayment($paymentId);
                    $bookSigned = true;
                } catch (Exception $bookSignedException) {
                }
                if ($bookSigned) {
                    /* get the status */
                    $bookStatus = $this->flow->getBookedStatus($signedResult);
                    $bookedPaymentId = $this->flow->getBookedPaymentId($signedResult);
                }
            }

            if ((empty($bookedPaymentId) && !$bookSigned) && !$isHostedFlow) {
                /* This is where we land where $bookSigned gets false, normally when there is an exception at the bookSignedPayment level */
                /* Before leaving this process, we'll check if something went wrong and the booking is already there */
                $hasGetPaymentErrors = false;
                $exceptionMessage = null;
                try {
                    $paymentCheck = $this->flow->getPayment($paymentId);
                } catch (Exception $getPaymentException) {
                    $hasGetPaymentErrors = true;
                }
                $paymentIdCheck = $this->flow->getBookedPaymentId($paymentCheck);
                /* If there is a payment, this order has been already got booked */
                if (!empty($paymentIdCheck)) {
                    wc_add_notice(__('The payment already exists', 'WC_Payment_Gateway'), 'error');
                } else {
                    /* If not, something went wrong further into the processing */
                    if ($hasGetPaymentErrors) {
                        if (isset($getPaymentException) && !empty($getPaymentException)) {
                            //$exceptionMessage = $getPaymentException->getMessage();
                            wc_add_notice(__('We could not finish your order. Please, contact support for more information.', 'WC_Payment_Gateway'), 'error');
                        }
                        wc_add_notice($exceptionMessage, 'error');
                    } else {
                        wc_add_notice(__('An unknown error occured in signing method. Please, try again later', 'WC_Payment_Gateway'), 'error');
                    }
                }
                /* We should however not return with a success */
                wp_safe_redirect($this->get_return_url($order));
            }

            try {
                /* So, if we passed through the above control, it's time to check out the status */
                if ($bookedPaymentId) {
                    update_post_meta($order_id, 'paymentId', $bookedPaymentId);
                } else {
                    /* When things fail, and there is no id available (we should hopefully never get here, since we're making other controls above) */
                    $bookStatus = "DENIED";
                }
                /* Continue. */
                if ($bookStatus == 'FROZEN') {
                    $order->update_status('on-hold', __('The payment are frozen, while waiting for manual control', 'WC_Payment_Gateway'));
                } elseif ($bookStatus == 'BOOKED') {
                    $order->update_status('processing', __('The payment are signed and booked', 'WC_Payment_Gateway'));
                } elseif ($bookStatus == 'FINALIZED') {
                    $order->update_status('processing', __('The payment are signed and debited', 'WC_Payment_Gateway'));
                } elseif ($bookStatus == 'DENIED') {
                    $order->update_status('failed');
                    wc_add_notice(__('The payment can not complete. Contact customer services for more information.', 'WC_Payment_Gateway'), 'error');
                    return;
                } elseif ($bookStatus == 'FAILED') {
                    $order->update_status('failed', __('An error occured during the update of the booked payment. The payment id was never received properly in signing response', 'WC_Payment_Gateway'));
                    wc_add_notice(__('An unknown error occured. Please, try again later', 'WC_Payment_Gateway'), 'error');
                    return;
                }
            } catch (Exception $e) {
                wc_add_notice(__('Something went wrong during the signing process.', 'WC_Payment_Gateway'), 'error');
                return;
            }

            wp_safe_redirect($this->get_return_url($order));
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
                $class = $this->write_class_to_file($payment_method);
            }

            set_transient('resurs_bank_class_files', $class_files);
        }

        /**
         * Generates and writes a class for a specified payment methods to file
         *
         * @param  stdClass $payment_method A payment method return from Resurs Bank API
         */
        public function write_class_to_file($payment_method)
        {
            $class_name = 'resurs_bank_nr_' . $payment_method->id;
            if (!file_exists(plugin_dir_path(__FILE__) . '/includes/' . $class_name)) {

            } else {
                if (!in_array(plugin_dir_path(__FILE__) . '/includes/' . $class_name, get_included_files())) {
                    include(plugin_dir_path(__FILE__) . '/includes/' . $class_name);
                }
            }

            $initName = 'woocommerce_gateway_resurs_bank_nr_' . $payment_method->id . '_init';
            $class_name = 'resurs_bank_nr_' . $payment_method->id;
            $methodId = 'resurs-bank-method-nr-' . $payment_method->id;
            $method_name = $payment_method->description;
            $type = strtolower($payment_method->type);
            $customerType = $payment_method->customerType;
            $minLimit = $payment_method->minLimit;
            $maxLimit = $payment_method->maxLimit;

            //$icon_name = strtolower($method_name);
            $icon_name = "resurs-standard";
            //$icon_name = str_replace(array('å', 'ä', 'ö', ' '), array('a', 'a', 'o', '_'), $icon_name);
            $path_to_icon = $this->icon = apply_filters('woocommerce_resurs_bank_' . $type . '_checkout_icon', $this->plugin_url() . '/img/' . $icon_name . '.png');
            $temp_icon = plugin_dir_path(__FILE__) . 'img/' . $icon_name . '.png';
            $has_icon = (string)file_exists($temp_icon);

            $ajaxUrl = admin_url('admin-ajax.php');
            $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
            $class = <<<EOT
<?php
	class {$class_name} extends WC_Resurs_Bank {
		public function __construct()
		{
			\$this->id           = '{$class_name}';
			\$this->has_icon();
			\$this->method_title = '{$method_name}';
			if (!isResursHosted()) {
				\$this->has_fields   = true;
			} else {
				\$this->has_fields   = false;
			}

			\$this->init_form_fields();
			\$this->init_settings();

            \$this->minLimit = '{$minLimit}';
            \$this->maxLimit = '{$maxLimit}';
			\$this->title       = \$this->get_option( 'title' );
			\$this->description = \$this->get_option( 'description' );

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . \$this->id, array( \$this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( \$this, 'process_admin_options' ) );
			}
			//add_action( 'woocommerce_calculate_totals', array( \$this, 'calculate_totals' ), 10, 1 );
			// Payment listener/API hook

			add_action( 'woocommerce_api_{$class_name}', array( \$this, 'check_signing_response' ) );
		}

		function init_form_fields() {
			\$this->form_fields = array(
				'enabled' => array(
						'title' => __('Enable/Disable', 'woocommerce'),
						'type'  => 'checkbox',
						'label' => 'Aktivera Resurs Bank {$method_name}',
					),
				'title' => array(
						'title'       => 'Title',
						'type'        => 'text',
						'default'     => 'Resurs Bank {$method_name}',
						'description' => __( 'This controls the payment method title which the user sees during checkout.', 'woocommerce' ),
						'desc_tip'    => true,
					),
				'icon' => array(
						'title'   => __('Custom payment method icon', 'WC_Payment_Gateway'),
						'type'    => 'text',
						'default' => \$this->icon,
						'description' => __('Used for branded logotypes as icons for the specific payment method. The image type must be a http/https-link. Suggested link is local, uploaded to WordPress own media storage.', 'WC_Payment_Gateway'),
					),
				'enableMethodIcon' => array(
						'title' => __('Enable/Disable payment method icon', 'woocommerce'),
						'type'  => 'checkbox',
						'label' => 'Enables displaying of logotype at payment method choice',
					),
				'description' => array(
						'title'       => 'Description',
						'type'        => 'textarea',
						'default'     => 'Betala med Resurs Bank {$method_name}',
						'description' => __( 'This controls the payment method description which the user sees during checkout.', 'woocommerce' ),
						'desc_tip'    => true,
					),
				'price' => array(
						'title'       => 'Avgift',
						'type'        => 'number',
						'default'     => 0,
						'description' => __('Payment fee for this payment method', 'WC_Payment_Gateway'),
						'desc_tip'    => false,
					),
				'priceDescription' => array(
						'title'   => __('Description of this payment method fee', 'WC_Payment_Gateway'),
						'type'    => 'textarea',
						'default' => '',
					),
			);
		}

		public function calculate_totals( \$totals )
		{
			global \$woocommerce;
		    \$available_gateways = \$woocommerce->payment_gateways->get_available_payment_gateways();
		    \$current_gateway = '';
		    if ( ! empty( \$available_gateways ) ) {
		        // Chosen Method
		        if ( isset( \$woocommerce->session->chosen_payment_method ) && isset( \$available_gateways[ \$woocommerce->session->chosen_payment_method ] ) ) {
		            \$current_gateway = \$available_gateways[ \$woocommerce->session->chosen_payment_method ];
		        } elseif ( isset( \$available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
		            \$current_gateway = \$available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
		        } else {
		            \$current_gateway =  current( \$available_gateways );

		        }
		    }
		    if(\$current_gateway!=''){
		        \$current_gateway_id = \$current_gateway->id;
		        \$extra_charges_id = 'woocommerce_' . \$current_gateway_id . '_settings';
		        \$extra_charges = (float)get_option( \$extra_charges_id)['price'];
		        //var_dump(\$extra_charges, \$extra_charges_id);
		        if(\$extra_charges){
		            \$totals->cart_contents_total = \$totals->cart_contents_total + \$extra_charges;
		            \$this->current_gateway_title = \$current_gateway -> title;
		            \$this->current_gateway_extra_charges = \$extra_charges;
		            add_action( 'woocommerce_review_order_before_order_total',  array( \$this, 'add_payment_gateway_extra_charges_row'));
		        }

		    }
		    return \$totals;
		}

		public function add_payment_gateway_extra_charges_row()
		{
			?>
		    <tr class="payment-extra-charge">
		        <th><?php echo \$this->current_gateway_title?> Extra Charges</th>
		        <td><?php echo woocommerce_price(\$this->current_gateway_extra_charges); ?></td>
			 </tr>
			 <?php
		}

		public function get_current_gateway()
		{
			global \$woocommerce;
			\$available_gateways = \$woocommerce->payment_gateways->get_available_payment_gateways();
			\$current_gateway = null;
			\$default_gateway = get_option( 'woocommerce_default_gateway' );
			if ( ! empty( \$available_gateways ) ) {
				
			   // Chosen Method
				if ( isset( \$woocommerce->session->chosen_payment_method ) && isset( \$available_gateways[ \$woocommerce->session->chosen_payment_method ] ) ) {
					\$current_gateway = \$available_gateways[ \$woocommerce->session->chosen_payment_method ];
				} elseif ( isset( \$available_gateways[ \$default_gateway ] ) ) {
					\$current_gateway = \$available_gateways[ \$default_gateway ];
				} else {
					\$current_gateway = current( \$available_gateways );
				}
			}
			if ( ! is_null( \$current_gateway ) )
				return \$current_gateway;
			else 
				return false;
		}

		public function has_icon()
		{
		    \$this->iconEnabled = \$this->get_option('enableMethodIcon');
		    if (\$this->iconEnabled == "true" || \$this->iconEnabled == "1" || \$this->iconEnabled == "yes") {
    			if ( file_exists( '{$temp_icon}' ) ) {
    				\$this->icon = '{$path_to_icon}';
    			}
    			\$storedIcon = \$this->get_option('icon');
    			if (\$storedIcon !== \$this->icon && !empty(\$storedIcon)) {
    			    \$this->icon = \$storedIcon;
    			}
		    }
		}

		public function payment_fields()
		{
			global \$woocommerce;
			\$cart = \$woocommerce->cart;
			//var_dump(\$woocommerce->session->chosen_payment_method, \$_REQUEST);
			if ( isset( \$_COOKIE['{$class_name}_denied'] ) ) {
				echo '<p>Denna betalningsmetod är inte tillgänglig för dig, vänligen välj en annan</p>';
				return;
			}
			if (isset(\$_REQUEST) && isset(\$_REQUEST['payment_method'])) {
				if (\$_REQUEST['payment_method'] === '{$class_name}') {
                   /*
				    * Start payment session are used even if we're in hosted or simplified mode.
				    * There is a read more button that is created here.
				    */
        		    \$payment_session = \$this->start_payment_session( '{$payment_method->id}', \$this );
				}
			}
		}

		public function admin_options()
		{
			?>
			<h3><?php echo \$this->method_title; ?></h3>
			<p>På denna sida kan du ändra inställningar för Resurs Bank {$method_name}</p>

				<table class="form-table">

                    <span id="paymentMethodName" style="display:none">{$class_name}</span>
					<?php \$this->generate_settings_html(); ?>

				</table>

			<?php
		}

		public static function interfere_checkout()
		{
		}

		public static function interfere_checkout_review( \$value )
		{
		}

		public static function interfere_update_order_review( \$posted )
		{
			global \$woocommerce;
			if (isset(\$_REQUEST)) {
				if (\$_REQUEST['payment_method'] === '{$class_name}') {
					\$payment_method = \$_REQUEST['payment_method'];
					\$payment_fee = get_option( 'woocommerce_' . \$payment_method . '_settings' )['price'];
					\$payment_fee = (float)( isset( \$payment_fee ) ? \$payment_fee : '0' );
					//\$payment_fee_tax_pct = (float)get_option( 'woocommerce_resurs-bank_settings' )['pricePct'];
					//\$payment_fee_total = (float)\$payment_fee * ( ( \$payment_fee_tax_pct / 100 ) + 1 );

					\$payment_fee_tax_class = get_option( 'woocommerce_resurs-bank_settings' )['priceTaxClass'];

					\$payment_fee_tax_class_rates = \$woocommerce->cart->tax->get_rates( \$payment_fee_tax_class );

					\$payment_fee_tax = \$woocommerce->cart->tax->calc_tax(\$payment_fee, \$payment_fee_tax_class_rates);

		        	if ( false === empty( get_option( 'woocommerce_{$class_name}_settings' )['priceDescription'] ) ) {
						\$fee_title = get_option( 'woocommerce_{$class_name}_settings' )['priceDescription'];
					} else {
						\$fee_title = get_option( 'woocommerce_{$class_name}_settings' )['title'];
					}
					\$woocommerce->cart->add_fee( \$fee_title, \$payment_fee, true, \$payment_fee_tax_class );
				}
			}
		}

		public static function interfere_checkout_process( \$posted )
		{
			global \$woocommerce;
			if (isset(\$_REQUEST)) {
				if (\$_REQUEST['payment_method'] === '{$class_name}') {
					\$payment_method = \$_REQUEST['payment_method'];
					\$payment_fee = get_option( 'woocommerce_' . \$payment_method . '_settings' )['price'];
					\$payment_fee = (float)( isset( \$payment_fee ) ? \$payment_fee : '0' );
					//\$payment_fee_tax_pct = (float)get_option( 'woocommerce_resurs-bank_settings' )['pricePct'];
					\$payment_fee_total = (float)\$payment_fee * ( ( \$payment_fee_tax_pct / 100 ) + 1 );
					\$payment_fee_tax = (float)\$payment_fee * ( \$payment_fee_tax_pct / 100 );

					\$payment_fee_tax_class = get_option( 'woocommerce_resurs-bank_settings' )['priceTaxClass'];

					if ( false === empty( get_option( 'woocommerce_{$class_name}_settings' )['priceDescription'] ) ) {
						\$fee_title = get_option( 'woocommerce_{$class_name}_settings' )['priceDescription'];
					} else {
						\$fee_title = get_option( 'woocommerce_{$class_name}_settings' )['title'];
					}

					\$woocommerce->cart->add_fee( \$fee_title, \$payment_fee, true, \$payment_fee_tax_class );
				}
			}
		}
	}

    if (!hasResursOmni()) {
	    function woocommerce_add_resurs_bank_gateway_{$class_name}( \$methods ) {
    		if ( 'no' == get_option( 'woocommerce_resurs-bank_settings' )['enabled']) {
    			return \$methods;
    		}
    		global \$woocommerce;
            \$cart = \$woocommerce->cart;
            \$total = \$cart->total;

            \$minLimit = '{$minLimit}';
            \$maxLimit = '{$maxLimit}';
            if (\$total > 0) {
                    if (\$total >= \$maxLimit || \$total <= \$minLimit)
                    {
                        if (!isResursTest()) {
                            return \$methods;
                        }
                    }
            }
    		if ( isset( \$_COOKIE['{$class_name}_denied'] ) ) {
    			return \$methods;
    		}
    		\$methods[] = '{$class_name}';
    		return \$methods;
    	}
    	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_gateway_{$class_name}' );
    	add_action( 'woocommerce_checkout_process', '{$class_name}::interfere_checkout',0 );
    	add_action( 'woocommerce_checkout_order_review', '{$class_name}::interfere_checkout_review', 1 );
    	add_action( 'woocommerce_checkout_update_order_review', '{$class_name}::interfere_update_order_review', 1 );
    	add_action( 'woocommerce_checkout_process', '{$class_name}::interfere_checkout_process', 1 );
    	add_action( 'woocommerce_cart_calculate_fees', '{$class_name}::interfere_update_order_review', 1 ); /* For WooCommerce updated after 1.5.x */
	}
EOT;

            $path = plugin_dir_path(__FILE__) . '/includes/' . $class_name . '.php';
            $path = str_replace('//', '/', $path);

            file_put_contents($path, $class);
        }

        /**
         * Validate the payment fields
         *
         * Never called from within this class, only by those that extends from this class and that are created in write_class_to_file
         *
         * @return boolean Whether or not the validation passed
         */
        public function validate_fields()
        {
            global $woocommerce;
            $className = $_REQUEST['payment_method'];

            $methodName = str_replace('resurs_bank_nr_', '', $className);
            $transientMethod = $this->getTransientMethod($methodName);
            $countryCode = isset($_REQUEST['billing_country']) ? $_REQUEST['billing_country'] : "";
            $customerType = isset($_REQUEST['ssnCustomerType']) ? $_REQUEST['ssnCustomerType'] : "NATURAL";

            $flow = initializeResursFlow();
            $regEx = $flow->getRegEx(null, $countryCode, $customerType);
            $methodFieldsRequest = $flow->getTemplateFieldsByMethodType($transientMethod, $customerType);
            $methodFields = $methodFieldsRequest['fields'];

            $validationFail = false;
            foreach ($methodFields as $fieldName) {
                if (isset($_REQUEST[$fieldName])) {
                    $regExString = $regEx[$fieldName];
                    $regExString = str_replace('\\\\', '\\', $regExString);
                    $fieldData = $_REQUEST[$fieldName];
                    $invalidFieldError = __('The field', 'WC_Payment_Gateway') . " " . $fieldName . " " . __('has invalid information', 'WC_Payment_Gateway') . " (" . (!empty($fieldData) ? $fieldData : __("It can't be empty", 'WC_Payment_Gateway')) . ")";
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
            $handleNatConnections = resursOption('handleNatConnections');
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

        /** @var Access to undeclared static property fix */
        private static $ip;

        /**
         * Ensures an ip address is both a valid IP and does not fall within
         * a private network range.
         * @access public
         * @param $ip
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
            $url = admin_url('admin.php');
            $url = add_query_arg('page', $_REQUEST['page'], $url);
            $url = add_query_arg('tab', $_REQUEST['tab'], $url);
            $url = add_query_arg('section', $_REQUEST['section'], $url);

            if (isset($_REQUEST['woocommerce_resurs-bank_registerCallbacksButton'])) {
                $salt = uniqid(mt_rand(), true);
                set_transient('resurs_bank_digest_salt', $salt);

                /* Make sure we do not use UPDATEs yet */
                $this->flow->unSetCallback(ResursCallbackTypes::UPDATE);
                foreach ($this->callback_types as $callback => $options) {
                    $this->register_callback($callback, $options);
                }
            }
            if (isset($_REQUEST['woocommerce_resurs-bank_refreshPaymentMethods'])) {
                $this->paymentMethods = $this->get_payment_methods(true);
                if (empty($this->paymentMethods['error'])) {
                    if (true === $this->paymentMethods['generate_new_files']) {
                        $this->generate_payment_gateways($this->paymentMethods['methods']);
                        wp_safe_redirect($url);
                    }
                }
            }
            if (isset($_REQUEST['save'])) {
                wp_safe_redirect($url);
            }

            ?>
            <h3><?php echo $this->method_title; ?></h3>
            <?php
            $currentVersion = rbWcGwVersionToDecimals();
            echo "Version " . rbWcGwVersion() . (!empty($currentVersion) ? " (" . $currentVersion . ")" : "");
            ?>
            <p><?php echo __('Resurs Bank API Configuration', 'WC_Payment_Gateway'); ?></p><br>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
        }

        /**
         * Get available payment methods. Either from Resurs Bank API or transient cache
         *
         * @param bool $force_file_refresh If new files should be forced or not
         * @return array Array containing an error message, if any errors occurred, and the payment methods, if any available and no errors occurred.
         * @throws Exception
         */
        public function get_payment_methods($force_file_refresh = false)
        {
            $returnArr = array();
            $paymentMethods = get_transient('resurs_bank_payment_methods');

            $returnArr = array();
            if (false === ($paymentMethods = get_transient('resurs_bank_payment_methods')) || $force_file_refresh) {

                $temp_class_files = get_transient('resurs_bank_class_files');
                if (is_array($temp_class_files)) {
                    foreach ($temp_class_files as $class_name) {
                        $path = plugin_dir_path(__FILE__) . '/includes/' . $class_name;
                        $path = str_replace('//', '/', $path);

                        if (file_exists($path)) {
                            @unlink($path);
                            if (file_exists($path)) {
                                throw new Exception("File permission error for $path");
                            }
                        }
                    }
                    delete_transient('resurs_bank_class_files');
                }

                try {
                    if (is_object($this->flow)) {
                        try {
                            $paymentMethods = $this->flow->getPaymentMethods();
                            set_transient('resurs_bank_payment_methods', $paymentMethods, 24 * HOUR_IN_SECONDS);
                            $returnArr['error'] = '';
                            $returnArr['methods'] = $paymentMethods;
                            $returnArr['generate_new_files'] = true;
                        } catch (Exception $e) {
                            $returnArr['error'] = $e->getMessage();
                            $returnArr['methods'] = '';
                            $returnArr['generate_new_files'] = false;
                        }
                    }
                } catch (Exception $e) {
                    throw $e;
                }
            } else {
                $returnArr['error'] = '';
                $returnArr['methods'] = $paymentMethods;
                $returnArr['generate_new_files'] = false;
            }
            return $returnArr;
        }

        /**
         * Get address for a specific government ID
         *
         * @return  JSON Prints the address data as JSON
         */
        public static function get_address_ajax()
        {
            if (isset($_REQUEST) && 'SE' == get_option('woocommerce_resurs-bank_settings')['country']) {
                $customerType = isset($_REQUEST['customerType']) ? ($_REQUEST['customerType'] != 'LEGAL' ? 'NATURAL' : 'LEGAL') : 'NATURAL';

                $serverEnv = get_option('woocommerce_resurs-bank_settings')['serverEnv'];
                /*
                 * Overriding settings here, if we want getAddress picked from production instead of test.
                 * The only requirement for this to work is that we are running in test and credentials for production is set.
                 */
                $userProd = resursOption("ga_login");
                $passProd = resursOption("ga_password");
                if (resursOption("getAddressUseProduction") && isResursDemo() && $serverEnv == "test" && !empty($userProd) && !empty($passProd)) {
                    $results = getAddressProd($_REQUEST['ssn'], $customerType, self::get_ip_address());
                } else {
                    $flow = initializeResursFlow();
                    try {
                        $results = $flow->getAddress($_REQUEST['ssn'], $customerType, self::get_ip_address());
                    } catch (Exception $e) {
                        print (json_encode(array("error" => __('Can not get the address from current government ID', 'WC_Payment_Gateway'))));
                    }
                }
                print(json_encode($results));
            }
            die();
        }

        public static function get_cost_ajax()
        {
            global $styles;
            require_once('resursbankgateway.php');
            $costOfPurchaseHtml = "";
            $flow = initializeResursFlow();
            $method = $_REQUEST['method'];
            $amount = floatval($_REQUEST['amount']);

            $wooCommerceStyle = realpath(get_stylesheet_directory()) . "/css/woocommerce.css";
            $styles = array();

            $costOfPurchaseCss = resursOption('costOfPurchaseCss');
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
                $htmlBefore = '<div class="cost-of-purchase-box"><a class="woocommerce button" onclick="window.close()" href="javascript:void(0);">' . __('Close', 'WC_Payment_Gateway') . '</a>';
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
        public static function get_address_customertype()
        {
            $paymentMethods = get_transient('resurs_bank_payment_methods');
            $requestedCustomerType = $_REQUEST['customerType'];
            $responseArray = array(
                'natural' => array(),
                'legal' => array()
            );
            if (is_array($paymentMethods)) {
                foreach ($paymentMethods as $objId) {
                    if (isset($objId->id) && isset($objId->customerType)) {
                        $nr = "resurs_bank_nr_" . $objId->id;
                        $responseArray[strtolower($objId->customerType)][] = $nr;
                    }
                }
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
         * @return null                    Returns null on success, redirects and exits on failure
         */
        public static function order_status_changed($order_id, $old_status_slug, $new_status_slug)
        {
            global $woocommerce, $current_user;

            $order = new WC_Order($order_id);
            $payment_method = $order->payment_method;

            $payment_id = get_post_meta($order->id, 'paymentId', true);
            if (false === (boolean)preg_match('/resurs_bank/', $payment_method)) {
                return;
            }

            if (isset($_REQUEST['wc-api']) || isset($_REQUEST['cancel_order'])) {
                return;
            }

            $url = admin_url('post.php');
            $url = add_query_arg('post', $order_id, $url);
            $url = add_query_arg('action', 'edit', $url);
            $old_status = get_term_by('slug', sanitize_title($old_status_slug), 'shop_order_status');
            $new_status = get_term_by('slug', sanitize_title($new_status_slug), 'shop_order_status');
            $order_total = $order->get_total();
            $order_fees = $order->get_fees();
            $resursFlow = initializeResursFlow();
            $flowErrorMessage = null;
            if ($payment_id) {
                try {
                    $payment = $resursFlow->getPayment($payment_id);
                } catch (Exception $getPaymentException) {
                }

                if (false === is_array($payment->status)) {
                    $status = array($payment->status);
                } else {
                    $status = $payment->status;
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
                    $flowCode = 0;
                    $flowErrorMessage = "";
                    if ($resursFlow->canDebit($payment)) {
                        try {
                            $resursFlow->finalizePayment($payment_id);
                        } catch (Exception $e) {
                            $flowErrorMessage = $e->getMessage();
                            $flowCode = $e->getCode();
                        }
                    } else {
                        $flowErrorMessage = __('Can not finalize the payment', 'WC_Payment_Gateway');
                    }
                    if (!empty($flowErrorMessage)) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => $flowErrorMessage
                        );
                    } else {
                        wp_set_object_terms($order_id, array($old_status->slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                    }
                    break;
                case 'on-hold':
                    break;
                case 'cancelled':
                    try {
                        $resursFlow->cancelPayment($payment_id);
                    } catch (Exception $e) {
                        $flowErrorMessage = $e->getMessage();
                    }
                    if (null !== $flowErrorMessage) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => $flowErrorMessage
                        );
                        wp_set_object_terms($order_id, array($old_status->slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                    }
                    break;
                case 'refunded':
                    try {
                        $resursFlow->cancelPayment($payment_id);
                    } catch (Exception $e) {
                        $flowErrorMessage = $e->getMessage();
                    }
                    if (null !== $flowErrorMessage) {
                        $_SESSION['resurs_bank_admin_notice'] = array(
                            'type' => 'error',
                            'message' => $flowErrorMessage
                        );
                        wp_set_object_terms($order_id, array($old_status->slug), 'shop_order_status', false);
                        wp_safe_redirect($url);
                    }
                    break;
                default:
                    break;
            }
            return;
        }
    }

    /**
     * Add the Gateway to WooCommerce
     *
     * @param  array $methods The available payment methods
     * @return array          The available payment methods
     */
    function woocommerce_add_resurs_bank_gateway($methods)
    {
        $methods[] = 'WC_Resurs_Bank';
        return $methods;
    }

    /**
     * Remove the gateway from the available payment options at checkout
     *
     * @param  array $gateways The array of payment gateways
     * @return array           The array of payment gateways
     */
    function woocommerce_resurs_bank_available_payment_gateways($gateways)
    {
        unset($gateways['resurs-bank']);
        return $gateways;
    }

    /**
     * Adds the SSN field to the checkout form for fetching a address
     *
     * @param  WC_Checkout $checkout The WooCommerce checkout object
     * @return WC_Checkout           The WooCommerce checkout object
     */
    function add_ssn_checkout_field($checkout)
    {
        if ('no' == get_option('woocommerce_resurs-bank_settings')['enabled']) {
            return $checkout;
        }

        $selectedCountry = resursOption("country");
        if (resursOption("getAddress") && !isResursOmni()) {
            echo '<input type="radio" id="ssnCustomerType" onclick="getMethodType(\'natural\')" checked="checked" name="ssnCustomerType" value="NATURAL"> ' . __('Private', 'WC_Payment_Gateway') . " ";
            echo '<input type="radio" id="ssnCustomerType" onclick="getMethodType(\'legal\')" name="ssnCustomerType" value="LEGAL"> ' . __('Company', 'WC_Payment_Gateway');
            echo '<input type="hidden" id="resursSelectedCountry" value="' . $selectedCountry . '">';
            woocommerce_form_field('ssn_field', array(
                'type' => 'text',
                'class' => array('ssn form-row-wide resurs_ssn_field'),
                'label' => __('Government ID', 'WC_Payment_Gateway'),
                'placeholder' => __('Enter your government id (social security number)', 'WC_Payment_Gateway'),
            ), $checkout->get_value('ssn_field'));
            if ('SE' == $selectedCountry) {
                echo '<a href="#" class="button" id="fetch_address">' . __('Get address', 'WC_Payment_Gateway') . '</a><br>';
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
        if ('no' == get_option('woocommerce_resurs-bank_settings')['enabled']) {
            return;
        }
        if (isResursOmni()) {
			wp_enqueue_script('resursomni', plugin_dir_url(__FILE__) . 'js/omnicheckout.js');
            $omniBookUrl = home_url('/');
            $omniBookUrl = add_query_arg('wc-api', 'WC_Resurs_Bank', $omniBookUrl);
            $omniBookUrl = add_query_arg('event-type', 'prepare-omni-order', $omniBookUrl);
            $omniBookUrl = add_query_arg('set-no-session', '1', $omniBookUrl);
            $omniBookNonce = wp_nonce_url($omniBookUrl, "omnicheckout", "omnicheckout_nonce");

            $flow = initializeResursFlow();
            $sEnv = getServerEnv();
            $OmniUrl = $flow->getOmniUrl($sEnv);
            $omniRef = WC()->session->get('omniRef');
            $omniRefCreated = WC()->session->get('omniRefCreated');
            $omniRefAge = intval(WC()->session->get('omniRefAge'));
            $OmniVars = array(
                'OMNICHECKOUT_IFRAME_URL' => $OmniUrl,
                'OMNICHECKOUT' => home_url(),
                'OmniPreBookUrl' => $omniBookNonce,
                'OmniRef' => isset($omniRef) && !empty($omniRef) ? $omniRef : null,
                'OmniRefCreated' => isset($omniRefCreated) && !empty($omniRefCreated) ? $omniRefCreated : null,
                'OmniRefAge' => $omniRefAge
            );
            $setSessionEnable = true;
            $setSession = isset($_REQUEST['set-no-session']) ? $_REQUEST['set-no-session'] : null;
            if ($setSession == 1) { $setSessionEnable = false; } else { $setSessionEnable = true; }
            /*
             * During the creation of new omnivars, make sure they are not duplicates from older orders.
             */
            if ($setSessionEnable && function_exists('WC') && isset(WC()->session)) {
                $currentOmniRef = WC()->session->get('omniRef');
                // The resursCreatePass variable is only set when everything was successful.
                $resursCreatePass = WC()->session->get('resursCreatePass');
                $orderControl = wc_get_order_id_by_payment_id($currentOmniRef);
                if (!empty($orderControl) && !empty($currentOmniRef)) {
                    $checkOrder = new WC_Order($orderControl);
                    // currentOrderStatus checks what status the order had when created
                    $currentOrderStatus = $checkOrder->get_status();
                    $preventCleanup = array(
                        'pending', 'failed'
                    );
                    $allowCleanupSession = false;
                    if (!in_array($currentOrderStatus, $preventCleanup)) {$allowCleanupSession = true;}
                    if (($resursCreatePass && !empty($currentOmniRef)) || ($allowCleanupSession)) {
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
            'getAddressEnterGovernmentId' => __('Enter social security number', 'WC_Payment_Gateway'),
            'getAddressEnterCompany' => __('Enter corporate government identity', 'WC_Payment_Gateway'),
            'labelGovernmentId' => __('Government id', 'WC_Payment_Gateway'),
            'labelCompanyId' => __('Corporate government id', 'WC_Payment_Gateway'),
        );
        $generalJsTranslations = array(
            'deliveryRequiresSigning' => __("Changing delivery address requires signing", 'WC_Payment_Gateway'),
            'ssnElementMissing' => __("I can not show errors since the element is missing", 'WC_Payment_Gateway'),
            'purchaseAjaxInternalFailure' => __("The purchase has failed, due to an internal server error: The shop could not properly update the order.", 'WC_Payment_Gateway'),
            'resursPurchaseNotAccepted' => __("The purchase was rejected by Resurs Bank. Please contact customer services, or try again with another payment method.", 'WC_Payment_Gateway'),
            'theAjaxWasNotAccepted' => __("Something went wrong when we tried to book your order. Please contact customer support for more information.", 'WC_Payment_Gateway'),
            'theAjaxWentWrong' => __("An internal error occured while trying to book the order. Please contact customer support for more information.", 'WC_Payment_Gateway'),
            'theAjaxWentWrongWithThisMessage' => __("An internal error occured while trying to book the order:", 'WC_Payment_Gateway') . " ",
            'contactSupport' => __("Please contact customer support for more information.", 'WC_Payment_Gateway')
        );

        $oneRandomValue = null;
        if (getResursOption("randomizeJsLoaders")) {
            $oneRandomValue = "?randomizeMe=" . rand(1024, 65535);
        }
        $ajaxObject = array('ajax_url' => admin_url('admin-ajax.php'));
        wp_enqueue_style('resursInternal', plugin_dir_url(__FILE__) . 'css/resursinternal.css');
        wp_enqueue_script('resursbankmain', plugin_dir_url(__FILE__) . 'js/resursbank.js' . $oneRandomValue, array('jquery'));
        wp_localize_script('resursbankmain', 'rb_getaddress_fields', $resursLanguageLocalization);
        wp_localize_script('resursbankmain', 'rb_general_translations', $generalJsTranslations);
        wp_localize_script('resursbankmain', 'ajax_object', $ajaxObject);
        wp_localize_script('resursbankmain', 'omnivars', $OmniVars);
    }

    /**
     * Adds Javascript to the Resurs Bank Payment Gateway settings panel
     *
     * @param string $hook The current page
     * @return null        Returns null current page is not correct
     */
    function admin_enqueue_script($hook)
    {
        wp_enqueue_style('resursInternal', plugin_dir_url(__FILE__) . 'css/resursinternal.css');
        wp_enqueue_script('resursBankAdminScript', plugin_dir_url(__FILE__) . '/js/resursbankadmin.js');

        if (isset($_REQUEST['section']) && preg_match("/resurs-bank|resurs_bank/i", $_REQUEST['section'])) {
            /*
             * Let's not use bootstrap on this page
             */
            if (resursOption("uglifyResursAdmin")) {
                wp_enqueue_style("resursAdminBootstrap", "//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css");
            }
        }
        $specialAdminButtons = array(
            'registerCallbacksButton' => __('Register Callbacks', 'WC_Payment_Gateway'),
            'refreshPaymentMethods' => __('Update available payment methods', 'WC_Payment_Gateway')
        );
        wp_localize_script('resursBankAdminScript', 'rb_buttons', $specialAdminButtons);

        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $_SESSION['resurs_bank_admin_notice']['message'] = __('The Resurs Bank Addon for WooCommerce may not work properly in PHP 5.3 or older. You should consider upgrading to 5.4 or higher.', 'WC_Payment_Gateway');
            $_SESSION['resurs_bank_admin_notice']['type'] = 'resurswoo_phpversion_deprecated';
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
        if (!session_id()) {
            session_start();
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

    function test_before_shipping()
    {
    }

    foreach (glob(plugin_dir_path(__FILE__) . '/includes/*.php') as $filename) {
        if (!in_array($filename, get_included_files())) {
            include $filename;
        }
    }
    foreach (glob(plugin_dir_path(__FILE__) . '/staticflows/*.php') as $filename) {
        if (!in_array($filename, get_included_files())) {
            include $filename;
        }
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_gateway');
    add_filter('woocommerce_available_payment_gateways', 'woocommerce_resurs_bank_available_payment_gateways', 1);
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

    /* OmniCheckout */
    //add_action( 'woocommerce_after_checkout_form' , 'resurs_omnicheckout_after_checkout_form' );
    add_filter('woocommerce_order_button_html', 'resurs_omnicheckout_order_button_html');
    add_filter('woocommerce_no_available_payment_methods_message', 'resurs_omnicheckout_payment_gateways_check');
}

/**
 * @param null $order
 */
function resurs_order_data_info_after_order($order = null)
{
    resurs_order_data_info($order, 'AO');
}

/**
 * @param null $order
 */
function resurs_order_data_info_after_billing($order = null)
{
    resurs_order_data_info($order, 'AB');
}

/**
 * @param null $order
 */
function resurs_order_data_info_after_shipping($order = null)
{
    resurs_order_data_info($order, 'AS');
}

/**
 * Hook into WooCommerce OrderAdmin fetch payment data from Resurs Bank.
 * This hook are tested from WooCommerce 2.1.5 up to WooCommcer 2.5.2
 *
 * @param null $order
 * @param null $orderDataInfoAfter
 */
function resurs_order_data_info($order = null, $orderDataInfoAfter = null)
{
    global $orderInfoShown;

    $showOrderInfoAfter = (get_option('woocommerce_resurs-bank_settings')['showOrderInfoAfter'] ? get_option('woocommerce_resurs-bank_settings')['showOrderInfoAfter'] : "AO");
    if ($showOrderInfoAfter != $orderDataInfoAfter) {
        return;
    }
    if ($orderInfoShown) {
        return;
    }

    $orderInfoShown = true;
    $renderedResursData = '';
    $resursPaymentId = get_post_meta($order->id, 'paymentId', true);
    //if (is_object($order) && preg_match("/^resurs_bank/i", $order->payment_method)) {
    if (!empty($resursPaymentId)) {
        $hasError = "";
        try {
            $rb = initializeResursFlow();
            $resursPaymentInfo = $rb->getPayment($resursPaymentId);
        } catch (Exception $e) {
            $hasError = $e->getMessage();
            $hasErrorNonStack = $hasError;
            if (preg_match("/soapfault/i", $hasError)) {
                /* Trying to handle errors based on content, showing stack traces if errors could not be identified */
                if (preg_match("/Stack trace/is", $hasError) && preg_match("/Do you find this error strange\?/is", $hasError)) {
                    $hasErrorNonStack = preg_replace("/(.*?)Stack trace:(.*)/is", '$1', $hasError);
                    $soapFault = preg_replace("/(.*?)Do you find this error strange\?(.*)/is", "$1", $hasErrorNonStack);
                    $strangeMessage = preg_replace("/(.*?)Do you find this error strange\?(.*?)/is", "\nDo you find this error strange? $2 ", $hasErrorNonStack);
                    $strangeMessage = preg_replace("/(.*?) in \/(.*?)$/is", ' $1 ', $strangeMessage);
                    $hasErrorNonStack = '<div style="font-weight: bold;color:#990000;">' . $soapFault . '</div><div style="color:#000099;font-weight:bold;">' . $strangeMessage . '</div>';
                } else {
                    $hasErrorNonStack = '<div style="font-weight: bold;color:#990000;">' . $hasError . '</div>';
                }
            }
        }
        $renderedResursData .= '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                <div class="resurs-read-more-box">
                <div style="padding: 30px;border:none;" id="resursInfo">
                ';

        if (empty($hasError)) {
            $status = "AUTHORIZE";
            if (is_array($resursPaymentInfo->paymentDiffs)) {
                foreach ($resursPaymentInfo->paymentDiffs as $paymentDiff) {
                    if ($paymentDiff->type === "DEBIT") {
                        $status = "DEBIT";
                    }
                    if ($paymentDiff->type === "ANNUL") {
                        $status = "ANNUL";
                    }
                    if ($paymentDiff->type === "CREDIT") {
                        $status = "CREDIT";
                    }
                }
            } else {
                if ($resursPaymentInfo->paymentDiffs->type === "DEBIT") {
                    $status = "DEBIT";
                }
                if ($resursPaymentInfo->paymentDiffs->type === "ANNUL") {
                    $status = "ANNUL";
                }
                if ($resursPaymentInfo->paymentDiffs->type === "CREDIT") {
                    $status = "CREDIT";
                }
            }
            $renderedResursData .= '<div class="resurs_orderinfo_text paymentInfoWrapStatus paymentInfoHead">';
            if ($status === "AUTHORIZE") {
                $renderedResursData .= __('The order is booked', 'WC_Payment_Gateway');
            } elseif ($status === "DEBIT") {
                $renderedResursData .= __('The order is debited', 'WC_Payment_Gateway');
            } elseif ($status === "CREDIT") {
                $renderedResursData .= __('The order is credited', 'WC_Payment_Gateway');
            } elseif ($status === "ANNUL") {
                $renderedResursData .= __('The order is annulled', 'WC_Payment_Gateway');
            } else {
                //$renderedResursData .= '<p>' . __('Confirm the invoice to be sent before changes can be made to order. <br> Changes of the invoice must be made in resurs bank management.') . '</p>';
            }
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

            $renderedResursData .= '
                <br>
                <fieldset>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment ID', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->id) && !empty($resursPaymentInfo->id) ? $resursPaymentInfo->id : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment method ID', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->paymentMethodId) && !empty($resursPaymentInfo->paymentMethodId) ? $resursPaymentInfo->paymentMethodId : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment method name', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->paymentMethodName) && !empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodName : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment method type', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->paymentMethodType) && !empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodType : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment amount', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->totalAmount) && !empty($resursPaymentInfo->totalAmount) ? round($resursPaymentInfo->totalAmount, 2) : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment limit', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->limit) && !empty($resursPaymentInfo->limit) ? round($resursPaymentInfo->limit, 2) : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Fraud', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->fraud) && !empty($resursPaymentInfo->fraud) ? $resursPaymentInfo->fraud ? __('Yes') : __('No') : __('No')) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Frozen', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->frozen) && !empty($resursPaymentInfo->frozen) ? $resursPaymentInfo->frozen ? __('Yes') : __('No') : __('No')) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Customer name', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (is_object($resursPaymentInfo->customer->address) && !empty($resursPaymentInfo->customer->address->fullName) ? $resursPaymentInfo->customer->address->fullName : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Delivery address', 'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (!empty($addressInfo) ? nl2br($addressInfo) : "") . '</span>
            ';
        } else {
            $renderedResursData .= '<div>' . nl2br($hasErrorNonStack) . '</div>';
        }
        $renderedResursData .= '</fieldset>
                <p class="resurs-read-more" id="resursInfoButton"><a href="#" class="button">' . __('Read more') . '</a></p>
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
 * Unconditional OrderRowRemover for Resurs Bank. This function will run before the primary remove_order_item() in the WooCommerce-plugin.
 * This function won't remove any product on the woocommerce-side, it will however update the payment at Resurs Bank.
 * If removal at Resurs fails by any reason, this method will stop the removal from WooAdmin, so we won't destroy any synch.
 *
 * @param $item_id
 * @return bool
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

    $resursFlow = null;
    if (class_exists('ResursBank')) {
        $resursFlow = initializeResursFlow();
    }
    $clientPaymentSpec = array();
    if (null !== $resursFlow) {
        $productId = wc_get_order_item_meta($item_id, '_product_id');
        $productQty = wc_get_order_item_meta($item_id, '_qty');
        $orderId = wc_get_order_id_by_order_item_id($item_id);

        $resursPaymentId = get_post_meta($orderId, 'paymentId', true);

        if (empty($productId)) {
            $testItemType = wc_get_order_item_type_by_item_id($item_id);
            $testItemName = wc_get_order_item_type_by_item_id($item_id);
            if ($testItemType === "shipping") {
                $clientPaymentSpec[] = array(
                    'artNo' => "00_frakt",
                    'quantity' => 1
                );
            } elseif ($testItemType === "coupon") {
                $clientPaymentSpec[] = array(
                    'artNo' => $testItemName . "_kupong",
                    'quantity' => 1
                );
            } elseif ($testItemType === "fee") {
                if (function_exists('wc_get_order')) {
                    $current_order = wc_get_order($orderId);
                    $feeName = '00_' . str_replace(' ', '_', $current_order->payment_method_title) . "_fee";
                    $clientPaymentSpec[] = array(
                        'artNo' => $feeName,
                        'quantity' => 1
                    );
                } else {
                    $order_failover_test = new WC_Order($orderId);
                    ///$payment_fee = array_values($order->get_items('fee'))[0];
                    $feeName = '00_' . str_replace(' ', '_', $order_failover_test->payment_method_title) . "_fee";
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
            //$removeResursRow = $resursFlow->annulPayment($resursPaymentId, $clientPaymentSpec);
            $removeResursRow = $resursFlow->cancelPayment($resursPaymentId, $clientPaymentSpec, array(), false, true);
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
 * @param string $paymentId
 * @return null|string
 */
function wc_get_order_id_by_payment_id($paymentId = '')
{
    global $wpdb;
    $order_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and meta_value = '%s'", $paymentId));
    return $order_id;
}

/**
 * Get payment id by order id
 * @param string $orderId
 * @return null|string
 */
function wc_get_payment_id_by_order_id($orderId = '')
{
    global $wpdb;
    $order_id = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and post_id = '%s'", $orderId));
    return $order_id;
}

/**
 * Get specific options from the Resurs configuration set
 * @param string $key
 * @return bool
 */
function resursOption($key = "", $checkParentOption = false)
{
    $response = get_option('woocommerce_resurs-bank_settings')[$key];
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
    return $response;
}

function getResursOption($key = "", $checkParentOption = false)
{
    return resursOption($key, $checkParentOption);
}

/**
 * Function used to figure out whether values are set or not
 *
 * @param string $key
 * @return bool
 */
function hasResursOptionValue($key = "")
{
    $optionValues = get_option('woocommerce_resurs-bank_settings');
    if (isset($optionValues[$key])) {
        return true;
    }
    return false;
}

/**
 * Set a new value in resursoptions
 * @param string $key
 * @param string $value
 * @return bool
 */
function setResursOption($key = "", $value = "")
{
    $allOptions = get_option('woocommerce_resurs-bank_settings');
    if (!empty($key)) {
        $allOptions[$key] = $value;
        update_option('woocommerce_resurs-bank_settings', $allOptions);
        return true;
    }
    return false;
}

/**
 * Get the order id from where a specific item resides
 * @param $item_id
 * @return null|string
 */
function wc_get_order_id_by_order_item_id($item_id)
{
    global $wpdb;
    $item_id = absint($item_id);
    $order_id = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'", $item_id));
    return $order_id;
}

/**
 * Get the order item type (or name) by item id
 * @param $item_id
 * @return null|string
 */
function wc_get_order_item_type_by_item_id($item_id, $getItemName = false)
{
    global $wpdb;
    $item_id = absint($item_id);
    if (!$getItemName) {
        $order_item_type = $wpdb->get_var($wpdb->prepare("SELECT order_item_type FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'", $item_id));
        return $order_item_type;
    } else {
        $order_item_name = $wpdb->get_var($wpdb->prepare("SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'", $item_id));
        return $order_item_name;
    }
}

/**
 * Initialize EComPHP, the key of almost everything in this plugin
 *
 * @return ResursBank
 */
function initializeResursFlow()
{
    global $current_user;
    $username = resursOption("login");
    $password = resursOption("password");
    $useEnvironment = getServerEnv();
    $initFlow = new ResursBank($username, $password);
    $initFlow->convertObjects = true;
    $initFlow->convertObjectsOnGet = true;
    $initFlow->setClientName("WooCommerce ResursBank Payment Gateway " . (defined('RB_WOO_VERSION') ? RB_WOO_VERSION : "Unknown version"));
    $initFlow->setEnvironment($useEnvironment);

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
        get_currentuserinfo();
        if (isset($current_user->user_login)) {
            $initFlow->setLoggedInUser($current_user->user_login);
        }
    } catch (Exception $e) {
    }
    return $initFlow;
}

function getAddressProd($ssn = '', $customerType = '', $ip = '')
{
    global $current_user;
    $username = resursOption("ga_login");
    $password = resursOption("ga_password");
    if (!empty($username) && !empty($password)) {
        $initFlow = new ResursBank($username, $password);
        $initFlow->convertObjects = true;
        $initFlow->convertObjectsOnGet = true;
        $initFlow->setClientName("WooCommerce ResursBank Payment Gateway " . (defined('RB_WOO_VERSION') ? RB_WOO_VERSION : "Unknown version"));
        $initFlow->setEnvironment(ResursEnvironments::ENVIRONMENT_PRODUCTION);
        try {
            return $initFlow->getAddress($ssn, $customerType, $ip);
        } catch (Exception $e) {
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
    $useEnvironment = ResursEnvironments::ENVIRONMENT_TEST;

    $serverEnv = get_option('woocommerce_resurs-bank_settings')['serverEnv'];
    $demoshopMode = get_option('woocommerce_resurs-bank_settings')['demoshopMode'];

    if ($serverEnv == 'live') {
        $useEnvironment = ResursEnvironments::ENVIRONMENT_PRODUCTION;
    }
    /*
     * Prohibit production mode if this is a demoshop
     */
    if ($serverEnv == 'test' || $demoshopMode == "true") {
        $useEnvironment = ResursEnvironments::ENVIRONMENT_TEST;
    }
    return $useEnvironment;
}

/**
 * Returns true if this is a test environment
 * @return bool
 */
function isResursTest()
{
    $currentEnv = getServerEnv();
    if ($currentEnv === ResursEnvironments::ENVIRONMENT_TEST) {
        return true;
    }
    return false;
}

/********************** OMNICHECKOUT RELATED STARTS HERE ******************/

/**
 * Check if the current payment method is currently enabled and selected
 *
 * @return bool
 */
function isResursOmni()
{
    global $woocommerce;
    if (isset($woocommerce->session)) {
        $currentMethod = $woocommerce->session->get('chosen_payment_method');
    }
    $flowType = resursOption("flowtype");
    $hasOmni = hasResursOmni();
    if (($hasOmni == 1 || $hasOmni === true) && $flowType === $currentMethod) {
        return true;
    }
    /*
     * If Omni is enabled and the current chosen method is empty, pre-select omni
     */
    if (($hasOmni == 1 || $hasOmni === true) && $flowType === "resurs_bank_omnicheckout" && empty($currentMethod)) {
        return true;
    }
    return false;
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
 * Check if the omniFlow is enabled at all (through flowType)
 *
 * @return bool
 */
function hasResursOmni()
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
    if ($resursEnabled != "yes") {
        return false;
    }
    if ($flowType == "resurs_bank_omnicheckout") {
        return true;
    }
    return false;
}

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


function resurs_omnicheckout_order_button_html($classButtonHtml)
{
    global $woocommerce;
    if (!isResursOmni()) {
        echo $classButtonHtml;
    }
}

/**
 * Payment methods validator for OmniCheckout
 * @param $paymentGatewaysCheck
 * @return null
 */
function resurs_omnicheckout_payment_gateways_check($paymentGatewaysCheck)
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
    if (!count($paymentGatewaysCheck)) {
        /* If there is no active payment gateways except for omniCheckout, the warning of no available payment gateways has to be suppressed */
        if (isResursOmni()) {
            return null;
        }
        return __('There are currently no payment methods available', 'WC_Payment_Gateway');
        //return null;
    }
    return $paymentGatewaysCheck;
}

/**
 * Check if there are gateways active (Omni related)
 * @return bool
 */
function hasPaymentGateways()
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
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
 * @return bool
 */
function isResursDemo()
{
    $demoshopMode = get_option('woocommerce_resurs-bank_settings')['demoshopMode'];
    if ($demoshopMode == "true") {
        return true;
    }
    return false;
}


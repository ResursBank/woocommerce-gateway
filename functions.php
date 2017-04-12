<?php

if (!defined('ABSPATH')) {
    exit;
}

load_plugin_textdomain('WC_Payment_Gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');

if (!function_exists('getResursWooFormFields')) {
    function getResursWooFormFields($addId = null, $namespace = "")
    {
        $resursAdminForm = resursFormFieldArray($namespace);
        if ($addId) {
            foreach ($resursAdminForm as $formKey => $formArray) {
                $resursAdminForm[$formKey]['id'] = $addId . "_" . $formKey;
            }
        }
        return $resursAdminForm;
    }

    /**
     * Get the plugin url
     *
     * @return string
     */
    function plugin_url()
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    function resursFormFieldArray($formSectionName = '')
    {
        global $wpdb, $woocommerce;

        $returnArray = array();
        $hasForcedSection = false;
        $forcedSection = "";
        /*
         * Currently used by omni_flow, to override the section as it comes from a variable and not a GET-parameter.
         */
        if (!empty($formSectionName)) {
            $hasForcedSection = true;
            $forcedSection = $formSectionName;
        }

        $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : "";
        if (empty($section)) {
            $formSectionName = "defaults";
        } else if ($section == "shopflow") {
            $formSectionName = "defaults";
        } else if ($section == "advanced") {
            $formSectionName = "defaults";
        } else if (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
            $formSectionName = "paymentmethods";
        } else {
            $formSectionName = $section;
        }
        if ($hasForcedSection && !empty($forcedSection)) {
            $formSectionName = $forcedSection;
        }

        /*
         *  $formFieldName is actually the section name
         */

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

        if ($formSectionName == "defaults") {
            $returnArray = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __('This is the major plugin switch. If not checked, it will be competely disabled, except for that you can still edit this administration control.', 'WC_Payment_Gateway')
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
                        'resurs_bank_omnicheckout' => __('Resurs Checkout: Fully integrated payment solutions based on iframes (as much as possible including initial customer data are handled by Resurs Bank without leaving the checkout page)', 'WC_Payment_Gateway'),
                        'simplifiedshopflow' => __('Simplified Shop Flow: Payments goes through Resurs Bank API (Default)', 'WC_Payment_Gateway'),
                        'resurs_bank_hosted' => __('Hosted Shop Flow: Customers are redirected to Resurs Bank to finalize payment', 'WC_Payment_Gateway'),
                    ),
                    'default' => 'resurs_bank_omnicheckout',
                    'description' => __('What kind of shop flow you want to use', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'Resurs Bank',
                    'description' => __('This controls the payment method title, which the user sees during checkout.', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => '',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'WC_Payment_Gateway'),
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
                        'live' => __('Production', 'WC_Payment_Gateway'),
                        'test' => __('Test', 'WC_Payment_Gateway'),
                    ),
                    'default' => 'test',
                    'description' => __('Set which server environment you are working with (Test/production)', 'WC_Payment_Gateway'),
                ),
                // Replacement URL for callbacks if different from default homeurl settings
                'customCallbackUri' => array(
                    'title' => __('Custom callback URL', 'WC_Payment_Gateway'),
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
                'priceTaxClass' => array(
                    'title' => __('Tax', 'woocommerce'),
                    'type' => 'select',
                    'options' => $rate_select,
                    'description' => __('The tax rate that will be added to the payment methods', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'reduceOrderStock' => array(
                    'title' => __('During payment process, also handle order by reducing order stock', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('Defines whether the plugin should wait for the fraud control when booking payments, or not', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'waitForFraudControl' => array(
                    'title' => 'waitForFraudControl',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                    'description' => __('Defines whether the plugin should wait for the fraud control when booking payments, or not', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'annulIfFrozen' => array(
                    'title' => 'annulIfFrozen',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                    'description' => __('Defines if a payment should be annulled immediately if Resurs Bank returns a FROZEN state', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                    'info' => __('If you don\'t want to wait for manual handling of orders that has been FROZEN during the payment, this feature enables the ability to immediately annul failing payments in the system. If this feature is enabled, you also need to have waitForFraudControl enabled. This behaviour is commonly used by shops that sells tickets and alike.', 'WC_Payment_Gateway'),
                ),
                'finalizeIfBooked' => array(
                    'title' => 'finalizeIfBooked',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                    'description' => __('Defines if a payment should be debited immediately on a booked payment (Not available for Resurs Checkout)', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                    'info' => __('You can only activate this feature if you have goods that can be delivered immediately, like electronic tickets and downloads', 'WC_Payment_Gateway'),
                ),
                'adminRestoreGatewaysWhenMissing' => array(
                    'title' => __('Restoring Payment Method gateway files', 'WC_Payment_Gateway'),
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
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('Defines if the plugin should perform a simple check against proxies on customer ip addresses (Not recommended to activate since it opens up for exploits, but if you have many connecting customers that seem to be on NATed networks, this may help a bit)', 'WC_Payment_Gateway'),
                    'desc_tip' => false,
                ),
                'getAddress' => array(
                    'title' => __('getAddressBox Enabled', 'WC_Payment_Gateway'),
                    'description' => __('If enabled, a box for social security numbers will be shown on the checkout. For Sweden, there will also be a capability to retrieve the customer home address, while active.', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'true'
                ),
                'streamlineBehaviour' => array(
                    'title' => __('Streamlined customer field behaviour', 'WC_Payment_Gateway'),
                    'description' => __('Fields that are required to complete an order from Resurs Bank, are hidden when active, since the fields required for Resurs Bank are inherited from WooCommerce fields by default.', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'true'
                ),
                'demoshopMode' => array(
                    'title' => __('Demoshopläge', 'WC_Payment_Gateway'),
                    'description' => __('Define if this shop is a demo store or not, which opens for more functionality (This option also forces the use of test environment)', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false'
                ),
                'getAddressUseProduction' => array(
                    'title' => __('Make getAddress fetch live data while in test mode', 'WC_Payment_Gateway'),
                    'description' => __('If enabled, live data will be available on getAddress-requests while in demo shop. Credentials for production - and enabling of demoshop mode - are required! Feature does not work for Omni Checkout.', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'label' => __('Enabled', 'woocommerce'),
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
                    'type' => 'checkbox',
                    'default' => 'false'
                ),
                'devResursSimulation' => array(
                    'title' => __('Resurs developer mode for simulations', 'WC_Payment_Gateway'),
                    'description' => __('Enable this feature and things may go wrong (this is automatically disabled in production)', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false'
                ),
                'includeEmptyTaxClasses' => array(
                    'title' => __('Include empty tax classes in admin config', 'WC_Payment_Gateway'),
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __('If your needs requires all tax classes selectable in this administration panel, enable this option to reach them', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false'
                ),
                'devSimulateSuccessUrl' => array(
                    'title' => __('SuccessUrl-simulation', 'WC_Payment_Gateway'),
                    'description' => __('If you are in simulation mode, you can enter your own successurl here, for which Resurs Checkout is sending you to, during a purchase', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => 'https://google.com/?test+landingpage'
                ),
                'callbackUpdateInterval' => array(
                    'title' => __('Callback update interval', 'WC_Payment_Gateway'),
                    'description' => __('Sets an interval for which the callback urls and salt key will update against Resurs Bank next time entering the administration control panel. This function has to be enabled above, to have any effect. Interval is set in days.', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => '7'
                ),
                'callbackUpdateAutomation' => array(
                    'title' => __('Enable automatic callback updates', 'WC_Payment_Gateway'),
                    'description' => __('Enabling this, the plugin will update callback urls and salt key, each time entering the administration control panel after a specific time', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'WC_Payment_Gateway'),
                    'default' => 'false'
                ),
                'showResursCheckoutStandardFieldsTest' => array(
                    'title' => __('Keep standard customer fields open for Resurs Checkout in test', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('Resurs Checkout Feature: If your plugin is running in test mode, you might want to study the behaviour of the standard customer form fields when communicating with the iframe', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
            );
        } else if ($formSectionName == "paymentmethods") {
            //$icon = apply_filters('woocommerce_resurs_bank_' . $type . '_checkout_icon', $this->plugin_url() . '/img/' . $icon_name . '.png');
            $icon = "";
            $returnArray = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'woocommerce'),
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => '',
                    'description' => __('If you are leaving this field empty, the default title will be used in the checkout', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'icon' => array(
                    'title' => __('Custom payment method icon', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => $icon,
                    'description' => __('Used for branded logotypes as icons for the specific payment method. The image type must be a http/https-link. Suggested link is local, uploaded to WordPress own media storage.', 'WC_Payment_Gateway'),
                ),
                'enableMethodIcon' => array(
                    'title' => __('Enable/Disable payment method icon', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'label' => 'Enable displaying of logotype at payment method choice',
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => '',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'price' => array(
                    'title' => 'Avgift',
                    'type' => 'number',
                    'default' => 0,
                    'description' => __('Payment fee for this payment method', 'WC_Payment_Gateway'),
                    'desc_tip' => false,
                ),
                'priceDescription' => array(
                    'title' => __('Description of this payment method fee', 'WC_Payment_Gateway'),
                    'type' => 'textarea',
                    'default' => '',
                ),
            );
        } else if ($formSectionName == "resurs_bank_omnicheckout") {
            $returnArray = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => 'Aktivera Resurs Checkout',
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'Resurs Checkout',
                    'description' => __('This controls the title of Resurs Checkout as a payment method in the checkout', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => 'Betala med Resurs Checkout',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'iFrameLocation' => array(
                    'title' => __('iFrame location', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'afterCheckoutForm' => __('After checkout form (Default)', 'WC_Payment_Gateway'),
                        'beforeReview' => __('Before order review', 'WC_Payment_Gateway'),
                        'inMethods' => __('In payment method list', 'WC_Payment_Gateway'),
                    ),
                    'default' => 'afterCheckoutForm',
                    'description' => __('The country for which the payment services should be used', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'omniFrameNotReloading' => array(
                    'title' => __('Reload checkout on cart changes', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('If you experience problems during the checkout (the iframe does not reload properly when the cart is updated), activating will reload the checkout page completely instead of just the iframe', 'WC_Payment_Gateway'),
                    'desc_tip' => false,
                ),
                'cleanOmniCustomerFields' => array(
                    'title' => __('Remove all default customer fields when loading Omni Checkout iframe', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('Normally, OmniCheckout has all necessary customer fields located in the iFrame. The plugin removes those fields automatically from the checkout. However, templates may not always clean up the fields properly. This option fixes this, but may affect the checkout in other ways than expected.', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'useStandardFieldsForShipping' => array(
                    'title' => __('Use standard customer fields to update shipping methods when postal code changes (Experimental)', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('Normally, this plugin removes all customer data fields from the checkout as it gets the information from the iframe. In this case, however, we will try to use those fields (in hidden mode) to update available shipping methods when the postal code changes. This is a beta function.', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
                'showResursCheckoutStandardFieldsTest' => array(
                    'title' => __('Keep standard customer fields open for Resurs Checkout in test', 'WC_Payment_Gateway'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __('If your plugin is running in test, enable this settings, to not hide the standard form fields', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
                ),
            );
        }
        return $returnArray;
    }
}
if (is_admin()) {
    if (!function_exists('write_resurs_class_to_file')) {

        function write_resurs_class_to_file($payment_method)
        {
            // No id - no file.
            if (!isset($payment_method->id) || (isset($payment_method->id) && empty($payment_method->id))) {
                return;
            }
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

            $plugin_url = untrailingslashit(plugins_url('/', __FILE__));

            $path_to_icon = $icon = apply_filters('woocommerce_resurs_bank_' . $type . '_checkout_icon', $plugin_url . '/img/' . $icon_name . '.png');
            $temp_icon = plugin_dir_path(__FILE__) . 'img/' . $icon_name . '.png';
            $has_icon = (string)file_exists($temp_icon);
            $ajaxUrl = admin_url('admin-ajax.php');
            $costOfPurchase = $ajaxUrl . "?action=get_cost_ajax";
            $class = <<<EOT
<?php
	class {$class_name} extends WC_Resurs_Bank {
		public function __construct()
		{
		    \$this->hasErrors = false;
			\$this->id           = '{$class_name}';
			\$this->id_short           = '{$payment_method->id}';
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

			if (empty(\$this->title) || strtolower(\$this->title) == "resurs bank") {
    			\$this->flow = initializeResursFlow();
    			try {
    			    // Fetch this data if there is no errors during controls (this could for example, if credentials are wrong, generate errors that makes the site unreachable)
    			    \$realTimePaymentMethod = \$this->flow->getPaymentMethodSpecific(\$this->id_short);
    			} catch (Exception \$realTimeException) {
    			    \$this->hasErrors = true;
    			}
			    \$this->title = \$realTimePaymentMethod->description;
			}

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
						'description' => __( 'This controls the payment method title which the user sees during checkout.', 'WC_Payment_Gateway' ),
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
						'description' => __( 'This controls the payment method description which the user sees during checkout.', 'WC_Payment_Gateway' ),
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
		    if (\$_REQUEST['tab'] !== "tab_resursbank") {
                \$_REQUEST['tab'] = "tab_resursbank";
                \$url = admin_url('admin.php');
                \$url = add_query_arg('page', \$_REQUEST['page'], \$url);
                \$url = add_query_arg('tab', \$_REQUEST['tab'], \$url);
                \$url = add_query_arg('section', \$_REQUEST['section'], \$url);
                wp_safe_redirect(\$url);
                die("Deprecated space");
		    }
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
				if (isset(\$_REQUEST['payment_method']) && \$_REQUEST['payment_method'] === '{$class_name}') {
					\$payment_method = \$_REQUEST['payment_method'];
					\$payment_fee = getResursOption('price', 'woocommerce_' . \$payment_method . '_settings');
					\$payment_fee = (float)( isset( \$payment_fee ) ? \$payment_fee : '0' );

					//\$payment_fee = get_option( 'woocommerce_' . \$payment_method . '_settings' )['price'];
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
    		// If the cart exists, we are probably located in the checkout. If that is so, check if the method
    		// are allowed to show. If the cart don't exist, this will generate undefined objects and show up errors
    		// on screen, if screen logging is enabled.
    		if (isset(\$woocommerce->cart)) {
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
    }

    if (!function_exists('generatePaymentMethodHtml')) {
        function generatePaymentMethodHtml($methodArray = array(), $returnAs = "html")
        {
            $methodTable = "";
            if ($returnAs != "html") {
                @ob_start();
            }
            ?>
            <table class="wc_gateways widefat" cellspacing="0px" cellpadding="0px" style="width: inherit;">
                <thead>
                <tr>
                    <th class="sort"></th>
                    <th class="name"><?php echo __('Method', 'WC_Payment_Gateway') ?></th>
                    <th class="title"><?php echo __('Title', 'WC_Payment_Gateway') ?></th>
                    <th class="id"><?php echo __('ID', 'WC_Payment_Gateway') ?></th>
                    <th class="status"><?php echo __('Status', 'WC_Payment_Gateway') ?></th>
                    <th class="process"><?php echo __('Process', 'WC_Payment_Gateway') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php

                $sortByDescription = array();
                foreach ($methodArray as $methodArray) {
                    $description = $methodArray->description;
                    $sortByDescription[$description] = $methodArray;
                }
                ksort($sortByDescription);
                $url = admin_url('admin.php');
                $url = add_query_arg('page', $_REQUEST['page'], $url);
                $url = add_query_arg('tab', $_REQUEST['tab'], $url);
                foreach ($sortByDescription as $methodArray) {
                    $curId = isset($methodArray->id) ? $methodArray->id : "";
                    $optionNamespace = "woocommerce_resurs_bank_nr_" . $curId . "_settings";
                    /*if (!hasResursOptionValue('enabled', $optionNamespace)) {
                        $this->resurs_settings_save("woocommerce_resurs_bank_nr_" . $curId);
                    }*/
                    write_resurs_class_to_file($methodArray);
                    $settingsControl = get_option($optionNamespace);
                    $isEnabled = false;
                    if (is_array($settingsControl) && count($settingsControl)) {
                        if ($settingsControl['enabled'] == "yes" || $settingsControl == "true" || $settingsControl == "1") {
                            $isEnabled = true;
                        }
                    }
                    $maTitle = $methodArray->description;
                    if (isset($settingsControl['title']) && !empty($settingsControl['title'])) {
                        $maTitle = $settingsControl['title'];
                    }
                    ?>
                    <tr>
                        <td width="1%">&nbsp;</td>
                        <td class="name" width="300px"><a
                                    href="<?php echo $url; ?>&section=resurs_bank_nr_<?php echo $curId ?>"><?php echo $methodArray->description ?></a>
                        </td>
                        <td class="title" width="300px"><?php echo $maTitle ?></td>
                        <td class="id"><?php echo $methodArray->id ?></td>
                        <?php if (!$isEnabled) { ?>
                            <td id="status_<?php echo $curId; ?>" class="status"
                                style="cursor: pointer;"
                                onclick="runResursAdminCallback('methodToggle', '<?php echo $curId; ?>')">
                                                <span class="status-disabled tips"
                                                      data-tip="<?php echo __('Disabled', 'woocommerce') ?>">-</span>
                            </td>
                        <?php } else {
                            ?>
                            <td id="status_<?php echo $curId; ?>" class="status"
                                style="cursor: pointer;"
                                onclick="runResursAdminCallback('methodToggle', '<?php echo $curId; ?>')">
                                                <span class="status-enabled tips"
                                                      data-tip="<?php echo __('Enabled', 'woocommerce') ?>">-</span>
                            </td>
                            <?php
                        } ?>
                        <td id="process_<?php echo $curId; ?>"></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <?php
            if ($returnAs != "html") {
                $methodTable = @ob_get_contents();
                @ob_end_clean();
            }
            return $methodTable;
        }
    }
}
if (!function_exists("callbackUpdateRequest")) {
    /**
     * Checks, in adminUI if there is need for callback requests.
     *
     * @return bool
     */
    function callbackUpdateRequest()
    {
        /*
         * Prepare callback checker, if logged into admin interface.
         *
         * This function enabled background updates of callback updating, instead of running on load in foreground, which probably makes the
         * administration GUI feel slower than necessary.
         */
        $requestForCallbacks = false;
        $callbackUpdateInterval = "";
        if (!empty(getResursOption("login")) && !empty(getResursOption("password")) && is_admin()) {
            /*
             * Make sure callbacks are up to date with an interval
             */
            $callbackUpdateInterval = !empty(getResursOption("callbackUpdateInterval")) ? intval(getResursOption("callbackUpdateInterval")) : 7;
            if ($callbackUpdateInterval > 7 || $callbackUpdateInterval < 0) {
                $callbackUpdateInterval = 7;
            }
            $lastCallbackRequest = get_transient('resurs_bank_last_callback_setup');
            $lastCallbackRequestDiff = time() - $lastCallbackRequest;
            $dayInterval = $callbackUpdateInterval * 86400;
            if ((getResursOption("callbackUpdateAutomation") && $lastCallbackRequestDiff >= $dayInterval) || empty($lastCallbackRequest)) {
                $requestForCallbacks = true;
            }
        }
        return $requestForCallbacks;
    }
}

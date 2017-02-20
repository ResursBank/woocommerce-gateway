<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Admin functions only
 */
load_plugin_textdomain('WC_Payment_Gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');

if (!function_exists('getResursWooFormFields')) {
    function getResursWooFormFields($addId = null)
    {
        $resursAdminForm = resursFormFieldArray();
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

    function resursFormFieldArray($formFieldName = 'defaults')
    {
        global $wpdb, $woocommerce;

        $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : "";
        if (empty($section)) {
            $formFieldName = "defaults";
        } else if ($section == "advanced") {
            $formFieldName = "defaults";
        } else if (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
            $formFieldName = "paymentmethods";
        }

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

        if ($formFieldName == "defaults") {
            $returnArray = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => 'Activate Resurs Bank',
                    'label' => __('Enable/Disable', 'woocommerce'),
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
                'priceTaxClass' => array(
                    'title' => 'Moms',
                    'type' => 'select',
                    'options' => $rate_select,
                    'description' => __('The tax rate that will be added to the payment methods', 'WC_Payment_Gateway'),
                    'desc_tip' => true,
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
                'devResursSimulation' => array(
                    'title' => __('Resurs developer mode for simulations', 'WC_Payment_Gateway'),
                    'description' => __('Enable this feature and things may go wrong (this is automatically disabled in production)', 'WC_Payment_Gateway'),
                    'type' => 'select',
                    'options' => array(
                        'true' => 'true',
                        'false' => 'false',
                    ),
                    'default' => 'false'
                ),
                'devSimulateSuccessUrl' => array(
                    'title' => __('SuccessUrl-simulation', 'WC_Payment_Gateway'),
                    'description' => __('If you are in simulation mode, you can enter your own successurl here, for which Resurs Checkout is sending you to, during a purchase', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => 'https://google.com/?test+landingpage'
                ),
            );
        } else if ($formFieldName == "paymentmethods") {
            //$icon = apply_filters('woocommerce_resurs_bank_' . $type . '_checkout_icon', $this->plugin_url() . '/img/' . $icon_name . '.png');
            $icon = "";
            $returnArray = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'woocommerce'),
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'default' => '',
                    'description' => __('If you are leaving this field empty, the default title will be used in the checkout', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'icon' => array(
                    'title' => __('Custom payment method icon', 'WC_Payment_Gateway'),
                    'type' => 'text',
                    'default' => $icon,
                    'description' => __('Used for branded logotypes as icons for the specific payment method. The image type must be a http/https-link. Suggested link is local, uploaded to WordPress own media storage.', 'WC_Payment_Gateway'),
                ),
                'enableMethodIcon' => array(
                    'title' => __('Enable/Disable payment method icon', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => 'Enables displaying of logotype at payment method choice',
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'default' => '',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'woocommerce'),
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
        }
        return $returnArray;
    }
}

if (is_admin()) {
    if (!function_exists('write_resurs_class_to_file')) {

        function write_resurs_class_to_file($payment_method)
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

			if (empty(\$this->title)) {
    			\$this->flow = initializeResursFlow();
    			\$realTimePaymentMethod = \$this->flow->getPaymentMethodSpecific(\$this->id_short);
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
				if (isset(\$_REQUEST['payment_method']) && \$_REQUEST['payment_method'] === '{$class_name}') {
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
                return $methodTable;
            }
        }
    }
}
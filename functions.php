<?php

load_plugin_textdomain('WC_Payment_Gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');

if (!function_exists('getResursWooFormFields')) {
    function getResursWooFormFields($addId = null)
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


        $resursAdminForm = array(
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
            /*'uglifyResursAdmin' => array(
                'title' => __('Bootstrap buttons in Resurs Configuration', 'WC_Payment_Gateway'),
                'description' => __('Using bootstrap in Resurs configuration will change the look of Resurs Bank administration interface', 'WC_Payment_Gateway'),
                'type' => 'select',
                'options' => array(
                    'true' => 'true',
                    'false' => 'false',
                ),
                'default' => 'false'
            ),*/
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
                'title' => __('Developers Resurs Simulation', 'WC_Payment_Gateway'),
                'description' => __('Enable this feature and things may go wrong (this is automatically disabled in production)', 'WC_Payment_Gateway'),
                'type' => 'select',
                'options' => array(
                    'true' => 'true',
                    'false' => 'false',
                ),
                'default' => 'false'
            ),
            'devSimulateSuccessUrl' => array(
                'title' => __('If in simulation mode, set another successurl than intended to this value', 'WC_Payment_Gateway'),
                'type' => 'text',
                'default' => 'https://google.com/?test+landingpage'
            ),
        );

        if ($addId) {
            foreach ($resursAdminForm as $formKey => $formArray) {
                $resursAdminForm[$formKey]['id'] = $addId . "_" . $formKey;
            }
        }
        return $resursAdminForm;
    }
}
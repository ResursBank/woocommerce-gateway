<?php

/**
 * Most of those functions are related to the configuration in wp-admin
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(__DIR__ . "/vendor/autoload.php");

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
        global $wpdb;

        $returnArray = [];
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
            $formSectionName = 'defaults';
        } elseif ($section === 'fraudcontrol') {
            $formSectionName = 'defaults';
        } elseif ($section === 'shortcodes') {
            $formSectionName = 'defaults';
        } elseif ($section === 'advanced') {
            $formSectionName = 'defaults';
        } elseif (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
            $formSectionName = 'paymentmethods';
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
        $rate_select = [];
        foreach ($rates as $rate) {
            $rate_name = $rate->tax_rate_class;
            if ('' === $rate_name) {
                $rate_name = 'standard';
            }
            $rate_name = str_replace('-', ' ', $rate_name);
            $rate_name = ucwords($rate_name);
            $rate_select[$rate->tax_rate_class] = $rate_name;
        }
        if ($formSectionName === 'defaults' || $formSectionName === 'woocommerce_resurs-bank_settings') {
            $returnArray = [
                'enabled' => [
                    'title' => __('Enable checkout functions', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __(
                        'To make the checkout features function properly, this setting has to be enabled. When ' .
                        'disabled, the plugin will still function but limited to features that covers order ' .
                        'handling, callbacks and other after shop features. This features does not shut down ' .
                        'the entire plugin.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'instant_migrations' => [
                    'title' => __('Enable instant migrations', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __(
                        'With this feature enabled, account migrations will be instant when/if upgrades to a ' .
                        'new major release are done.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => 'yes',
                ],
                'resursbank_start_session_before' => [
                    'title' => __('Disable session handling by plugin', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'label' => __('Session handling disabled', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'description' => __(
                        'Disable the way this plugin handles the session. In active state, the plugin will no longer handle the session. Default: Unchecked.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'resursbank_start_session_outside_admin_only' => [
                    'title' => __(
                        'Handle sessions but only outside admin',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'label' => __(
                        'Session handling is limited to outside the admin panel.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => 'false',
                    'description' => __(
                        'While the above setting still allows handling session, you can explicitly set the handler ' .
                        'to only work outside the admin interface. Default: Unchecked.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'preventGlobalInterference' => [
                    'title' => __(
                        'Prevent performance interferences in wp-admin',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __(
                        'If you experience very high pressure from the plugin when it is enabled, this setting tries ' .
                        'to prevent its own precense on pages where it normally should not interfere with the ' .
                        'platform.<br><b>This setting is experimental!</b>',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'postidreference' => [
                    'title' => __(
                        'Use woocommerce order ids (postid) as references',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __(
                        'This function tries to use the internal post id as orderid instead of the ' .
                        'references created by the plugin.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'country' => [
                    'title' => __('Country', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'SE' => __('Sweden', 'woocommerce'),
                        'DK' => __('Denmark', 'woocommerce'),
                        'FI' => __('Finland', 'woocommerce'),
                        'NO' => __('Norway', 'woocommerce'),
                    ],
                    'default' => 'SE',
                    'description' => __(
                        'The country for which the payment services should be used',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'flowtype' => [
                    'title' => __('Set checkout type', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'resurs_bank_omnicheckout' => __(
                            'Resurs Bank Checkout (RCO).',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'simplifiedshopflow' => __(
                            'Resurs Bank payment gateway (Simplified Flow API).',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'resurs_bank_hosted' => __(
                            'Resurs Bank Hosted Checkout (Hosted flow).',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ],
                    'default' => 'resurs_bank_omnicheckout',
                    'description' => __(
                        'What kind of shop flow you want to use.<br>' .
                        '<b>Caution: If you change and save this data, all ongoing customer sessions in the checkout ' .
                        'will be cleaned up, to prevent broken orders due to the switchover.</b>',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'title' => [
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'Resurs Bank',
                    'description' => __(
                        'This controls the payment method title, which the user sees during checkout.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => '',
                    'description' => __(
                        'This controls the payment method description which the user sees during checkout.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'login' => [
                    'title' => __('Web services username', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'text',
                    'description' => __(
                        'Web services username, received from Resurs Bank',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'timeout_throttler' => [
                    'title' => __(
                        'Lowest timeout during connectivity problems',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'description' => __(
                        'If WooCommerce experiences connectivity problems in checkout, based on timeouts, the plugin ' .
                        'can drop the waiting time with this value in seconds when trying to connect to Resurs Bank. ' .
                        'Since the plugin is depending on responses, this can speed up things a bit when the plugin ' .
                        'can not communicate with the API.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '3',
                    'desc_tip' => true,
                ],
                'password' => [
                    'title' => __('Web services password', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'password',
                    'default' => '',
                    'description' => __(
                        'Web services password, received from Resurs Bank',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'baseLiveURL' => [
                    'title' => __(
                        'BaseURL Webservices Live-Environment',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => 'https://ecommerce.resurs.com/ws/V4/',
                ],
                'baseTestURL' => [
                    'title' => __(
                        'BaseURL Webservices Test-Environment',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => 'https://test.resurs.com/ecommerce-test/ws/V4/',
                ],
                'serverEnv' => [
                    'title' => __('Server environment', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'live' => __('Production', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'test' => __('Test', 'resurs-bank-payment-gateway-for-woocommerce'),
                    ],
                    'default' => 'test',
                    'description' => __(
                        'Set which server environment you are working with (Test/production)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                // Replacement URL for callbacks if different from default homeurl settings
                'customCallbackUri' => [
                    'title' => __(
                        'Custom callback URL',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'If your callback URL has another URL than the defaults, you may enter the URL here. ' .
                        'Default value is your site-URL. If this value is empty, the URL will be automatically ' .
                        'generated.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '',
                ],
                'registerCallbacksButton' => [
                    'title' => __('Register Callbacks', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'class' => 'btn btn-primary',
                    'type' => 'submit',
                    'value' => __('Register Callbacks', 'resurs-bank-payment-gateway-for-woocommerce'),
                ],
                'priceTaxClass' => [
                    'title' => __('Tax', 'woocommerce'),
                    'type' => 'select',
                    'options' => $rate_select,
                    'description' => __(
                        'The tax rate that will be added to the payment methods.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'coupons_include_vat' => [
                    'title' => __(
                        'Coupons should be handled with vat',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __('Yes', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'default' => 'false',
                    'description' => __(
                        'When adding coupons/discounts in Resurs Bank orders, the VAT is normally - in this plugin - ' .
                        'not included in the discount. If you want to handle discount with VAT (meaning the discount' .
                        'will be handled based on the price excluding tax plus the vat, instead of adding the discount' .
                        'to the price with tax included), you should enable this setting.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'reduceOrderStock' => [
                    'title' => __(
                        'Handle order stock on payments',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'During payment process, also handle order by reducing order stock.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'disableAftershopFunctions' => [
                    'title' => __('Disable Aftershop', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'label' => __('Disable aftershop capabilities', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Defines whether the plugin should use aftershop functions or not. Adds the ability to disable aftershop completely if you want to implement your own aftershop flow. Default: Aftershop capabilities are enabled.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'forceGovIdField' => [
                    'title' => __(
                        'Always show govId in the last checkout form in checkout (simplified only)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'label' => __('Enabled', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Always show government id field in the checkout forms at payment methods level, regardless ' .
                        'of the getAddress settings.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'waitForFraudControl' => [
                    'title' => 'waitForFraudControl',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                    'description' => __(
                        'Defines whether the plugin should wait for the fraud control when booking payments, or not',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'annulIfFrozen' => [
                    'title' => 'annulIfFrozen',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                    'description' => __(
                        'Defines if a payment should be annulled immediately if Resurs Bank returns a FROZEN state',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                    'info' => __(
                        'If you don\'t want to wait for manual handling of orders that has been FROZEN during the payment, this feature enables the ability to immediately annul failing payments in the system. If this feature is enabled, you also need to have waitForFraudControl enabled. This behaviour is commonly used by shops that sells tickets and alike.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'finalizeIfBooked' => [
                    'title' => 'finalizeIfBooked',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                    'description' => __(
                        'Defines if a payment should be debited immediately on a booked payment (Not available for Resurs Checkout)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                    'info' => __(
                        'You can only activate this feature if you have goods that can be delivered immediately, like electronic tickets and downloads',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'adminRestoreGatewaysWhenMissing' => [
                    'title' => __(
                        'Restoring Payment Method gateway files',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'If a payment gateway file (in the includes folder) is missing, they will be restored automatically if they disappear (e.g. when upgrading the plugin). Checking this box limits automatic restorations, so they only gets activates when administrators are logged in',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'label' => __('Only administrators may restore gateway files'),
                ],
                'costOfPurchaseCss' => [
                    'title' => __(
                        'URL to custom CSS for costOfPurchase',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Define your custom CSS for the cost of purchase example (if empty, a default file will be used)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => home_url("/") . "wp-content/plugins/resurs-bank-payment-gateway-for-woocommerce/css/costofpurchase.css",
                ],
                'handleNatConnections' => [
                    'title' => __('Handle NAT connections', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Defines if the plugin should perform a simple check against proxies on customer ip addresses (Not recommended to activate since it opens up for exploits, but if you have many connecting customers that seem to be on NATed networks, this may help a bit)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => false,
                ],
                'resursOrdersEditable' => [
                    'title' => __('Allow editing of orders in progress', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Make orders editable even if they are set to in progress. Note: This setting is experimental and has limited edit capabilites. It should normally not be used!',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => false,
                ],
                'getAddress' => [
                    'title' => __('Address retrieval at checkout', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'description' => __(
                        'This function is available in Sweden only.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'options' => [
                        'true' => 'true',
                        'false' => 'false',
                    ],
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'true',
                ],
                'resursvalidate' => [
                    'title' => __(
                        'Let Resurs validate customer data fields',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'If enabled, customer forms fields required by Resurs Bank will be validated by Resurs Bank instead of the plugin.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'options' => [
                        'true' => 'true',
                        'false' => 'false',
                    ],
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                ],
                'streamlineBehaviour' => [
                    'title' => __(
                        'Streamlined customer field behaviour',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Fields that are required to complete an order from Resurs Bank, are hidden when active, since the fields required for Resurs Bank are inherited from WooCommerce fields by default.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'options' => [
                        'true' => 'true',
                        'false' => 'false',
                    ],
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'true',
                ],
                'showPaymentIdInOrderList' => [
                    'title' => __(
                        'Show Resurs Bank payment ids in order view',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Do you need to show order references in the order list view? This makes it happen!',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'options' => [
                        'true' => 'true',
                        'false' => 'false',
                    ],
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                ],
                'getAddressUseProduction' => [
                    'title' => __(
                        'Make getAddress fetch live data while in test mode',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'If enabled, live data will be available on getAddress-requests while in demo shop. Credentials for production - and enabling of demoshop mode - are required! Feature does not work for Omni Checkout.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'options' => [
                        'true' => 'true',
                        'false' => 'false',
                    ],
                    'label' => __('Enabled', 'woocommerce'),
                    'default' => 'false',
                ],
                'ga_login' => [
                    'title' => __(
                        'Web services username (getAddress/Production)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'description' => __(
                        'Resurs Bank web services username (getaddress/Production)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'ga_password' => [
                    'title' => __(
                        'Web services password (getAddress/Production)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'password',
                    'default' => '',
                    'description' => __(
                        'Resurs Bank web services password (getAddress/Production)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'randomizeJsLoaders' => [
                    'title' => __(
                        'Prevent caching of included javascripts',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Enable this feature, if resursbank.js tend to cache older versions even after the codebase are updated',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                ],
                'includeEmptyTaxClasses' => [
                    'title' => __(
                        'Include empty tax classes in admin config',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __(
                        'If your needs requires all tax classes selectable in this administration panel, enable this option to reach them',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                ],
                'devFlags' => [
                    'title' => __(
                        'Special flags',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Set up flags here (comma separated) to test, reveal or activate parts of the plugin that is normally not available. To set up variables with comma separated content, try use | or another characters instead.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '',
                ],
                'callbackUpdateInterval' => [
                    'title' => __('Callback update interval', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'description' => __(
                        'Sets an interval for which the callback urls and salt key will update against Resurs Bank next time entering the administration control panel. This function has to be enabled above, to have any effect. Interval is set in days.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '7',
                ],
                'credentialsMaintenanceTimeout' => [
                    'title' => __(
                        'Maintenance timeout for hard changes',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'When switching payment flow or webservice credentials, we normally want to be sure that we ' .
                        'do not break anything for checkout customers. This sets a grace period of how long the site ' .
                        'will be unavailable due to local maintenance. Default is 20 seconds and occurs when data ' .
                        'referring to credentials are changed. This is a transient setting so basically, wordpress ' .
                        'will handle the grace period. Set 0 to disable this feature.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '20',
                ],
                'callbackUpdateAutomation' => [
                    'title' => __(
                        'Enable automatic callback updates',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                            'Enabling this, the plugin will update callback urls and salt key, each time entering ' .
                            'the administration control panel after a specific time',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '<br><b>' . __(
                            '(It is no longer recommended to actively use this setting)',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '</b>',
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'default' => 'false',
                ],
                'logResursEvents' => [
                    'title' => __('Log Resurs Bank events', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'description' => __(
                        'Log events like callbacks, status updates on received callbacks, etc',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'default' => 'false',
                ],
                'showResursCheckoutStandardFieldsTest' => [
                    'title' => __(
                        'Keep standard customer fields open for Resurs Checkout in test',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Resurs Checkout Feature: If your plugin is running in test mode, you might want to study the ' .
                        'behaviour of the standard customer form fields when communicating with the iframe.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'showCheckoutOverlay' => [
                    'title' => __(
                        'Show checkout overlay on purchase',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'When clicking on the purchase button and being redirected to success or signing page, ' .
                        'this feature adds an extra overlay in the checkout telling the customer that payment ' .
                        'is in progress.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'checkoutOverlayMessage' => [
                    'title' => __(
                        'Custom checkout overlay message',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'If this value is empty, we will use the default notification text in the checkout overlay. ' .
                        'If not, we will use your customization.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '',
                ],
                'resursCurrentAnnuityFactors' => [
                    'title' => __('Annuity factor config', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'description' => __('Annuity factor config', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'text',
                    'default' => '',
                ],
                'resursAnnuityDuration' => [
                    'title' => __('Annuity factor duration', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'description' => __('Annuity factor duration', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'text',
                    'default' => '',
                ],
                'resursAnnuityMethod' => [
                    'title' => __(
                        'Current chosen payment method for annuity factors',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Current chosen payment method for annuity factors',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '',
                ],
                'autoDebitStatus' => [
                    'title' => __(
                        'Order status on instant finalizations',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Payment methods like SWISH, Vips, direct bank transfers, and so on tend to be followed ' .
                        'by direct debiting which finalizes orders before they are shipped. To prevent this, ' .
                        'you can set up a specific status for such payment methods when Resurs ' .
                        'callback event FINALIZATION occurs. Default status is "completed".',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'select',
                    'options' => [
                        'default' => __('Use default (Completed)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'processing' => __('Processing', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'credited' => __('Credited (refunded)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'completed' => __('Completed', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'pending' => __('Pending (on-hold)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'annulled' => __('Annulled (cancelled)', 'resurs-bank-payment-gateway-for-woocommerce'),
                    ],
                    'default' => 'default',
                    'desc_tip' => true,
                ],
                'autoDebitMethods' => [
                    'title' => __(
                        'Instant debitable payment methods',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'Payment methods that are considered instantly debitable during a payment process. If you choose the top alternative the plugin will choose for you if the correct default method (swish) are available.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'select',
                    'options' => [
                        'NONE' => __(
                            'Chosen by plugin',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ],
                    'default' => ['SWISH' => 'SWISH'],
                    'desc_tip' => true,
                ],
                'partPayWidgetPage' => [
                    'title' => __(
                        'Widget for custom part pay views',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'If you choose a page here, this page will be primary set for a customized part payment widget.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'select',
                    'options' => [],
                    'default' => 0,
                    'desc_tip' => true,
                    'fieldtype' => 'string',
                ],
                'enforceMethodList' => [
                    'title' => __(
                        'Force displaying payment method list in checkout',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'This feature enforces payment methods to be listed in checkout regardless of rules and ' .
                        'settings that have been applied to it except for country- and price limitations.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => 'false',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                ],
                'protectMethodList' => [
                    'title' => __(
                        'Force rewriting payment methods on the fly, if they are lost',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'description' => __(
                        'In some deployment scenarios, the classes for where payment methods are stored, may be ' .
                        'deleted as they do not belong to the code base itself and are dynamically created in ' .
                        'the system. This feature will force the plugin to rewrite them if they are considered lost.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => 'false',
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                ],
            ];
        } elseif ($formSectionName === 'paymentmethods') {
            $icon = "";
            $returnArray = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'woocommerce'),
                ],
                'title' => [
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => '',
                    'description' => __(
                        'If you are leaving this field empty, the default title will be used in the checkout',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'icon' => [
                    'title' => __(
                        'Custom payment method icon',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => $icon,
                    'description' => __(
                        'Used for branded logotypes as icons for the specific payment method. The image type must be a http/https-link. Suggested link is local, uploaded to WordPress own media storage.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'enableMethodIcon' => [
                    'title' => __(
                        'Enable/Disable payment method icon',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'label' => 'Enable displaying of logotype at payment method choice',
                ],
                'description' => [
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => '',
                    'description' => __(
                        'This controls the payment method description which the user sees during checkout.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'price' => [
                    'title' => 'Avgift',
                    'type' => 'number',
                    'default' => 0,
                    'description' => __(
                        'Payment fee for this payment method',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => false,
                ],
                'priceDescription' => [
                    'title' => __(
                        'Description of this payment method fee',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'textarea',
                    'default' => '',
                ],
            ];
        } elseif ($formSectionName === 'resurs_bank_omnicheckout') {
            $returnArray = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => 'Aktivera Resurs Checkout',
                ],
                'title' => [
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'Resurs Checkout',
                    'description' => __(
                        'This controls the title of Resurs Checkout as a payment method in the checkout',
                        'WC_Payment_Gateway'
                    ),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => 'Betala med Resurs Checkout',
                    'description' => __(
                        'This controls the payment method description which the user sees during checkout.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'iframeShape' => [
                    'title' => __(
                        'Change the (CSS)-shape of the iframe',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '',
                    'description' => __(
                        'This controls the shape of the iframe CSS (meaning you may use CSS code here to change the background and shape of the iframe)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'iframeTestUrl' => [
                    'title' => __(
                        'Internal Test URL for special accounts',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'text',
                    'default' => '',
                    'description' => __(
                        'If there is an URL here, it will be enforced when special accounts are used. The URL should point at the service where you send all your inital POST-data',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'alwaysPte' => [
                    'title' => __('Permanent PTE', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'woocommerce'),
                    'description' => __(
                        'Special feature. Only visible when in correct domain.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ],
                'iFrameLocation' => [
                    'title' => __('iFrame location', 'resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'afterCheckoutForm' => __(
                            'After checkout form (Default)',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'beforeReview' => __(
                            'Before order review',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ],
                    'default' => 'afterCheckoutForm',
                    'description' => __(
                        'Sets up where the iframe for Resurs Checkout should appear. The first versions of this plugin automatically rendered the checkout in the payment method list. Do not do this as things might break.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'removeGatewayListOnOmni' => [
                    'title' => __(
                        'No methods on Checkout (Experimental)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Remove payment method list if Resurs Checkout is the only gateway',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => false,
                ],
                'omniFrameNotReloading' => [
                    'title' => __(
                        'Reload checkout on cart changes',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'If you experience problems during the checkout (the iframe does not reload properly when the cart is updated), activating will reload the checkout page completely instead of just the iframe',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => false,
                ],
                'cleanOmniCustomerFields' => [
                    'title' => __(
                        'Remove all default customer fields when loading Omni Checkout iframe',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Normally, OmniCheckout has all necessary customer fields located in the iFrame. The plugin removes those fields automatically from the checkout. However, templates may not always clean up the fields properly. This option fixes this, but may affect the checkout in other ways than expected.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'disableStandardFieldsForShipping' => [
                    'title' => __(
                        'Disable standard customer fields',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'As of WC 7.5.0, this plugin keeps all customer data fields in the checkout, but invisible to make sure shipping etc is handled properly. If you have a theme where this is breaking customer fields, this feature can be enabled to make the fields removed entirely.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'secureFieldsNotNull' => [
                    'title' => __(
                        'Checkout form fields must not be empty',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'This setting secures that this plugin is returning an array, if WooCommerce for some passes over completely empty data (null) during the checkout process',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'showResursCheckoutStandardFieldsTest' => [
                    'title' => __(
                        'Keep standard customer fields open for Resurs Checkout in test',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'If your plugin is running in test, enable this settings, to not hide the standard form fields',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
                'resursCheckoutMultipleMethods' => [
                    'title' => __(
                        'Bypass problems with multiple payment methods (experimental)',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'false',
                    'description' => __(
                        'Resurs Checkout is normally not compatible with other payments as the checkout fields are handled differently when an iframe is active. This setting enables an experimental way to bypass such problems by make a reload each time a switch to or from the Resurs Checkout is being made.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc_tip' => true,
                ],
            ];

            // If this store ever had the setting for iframe location in payment method list (or have)
            // this will be continuosly readded to the above configuration.
            if (!isset($returnArray['iFrameLocation']['options']['inMethods']) && getHadMisplacedIframeLocation()) {
                $returnArray['iFrameLocation']['options']['inMethods'] = __(
                    'In payment method list (Deprecated, not recommended to use)',
                    'resurs-bank-payment-gateway-for-woocommerce'
                );
            }
        }

        return apply_filters('resurs_bank_form_fields', $returnArray, $formSectionName);
    }
}

if (!function_exists('getResursIconByType')) {
    /**
     * In the future release, this will be entirely automated.
     *
     * @param $payment_method
     * @return string
     * @throws Exception
     */
    function getResursIconByType($payment_method)
    {
        $ecom = initializeResursFlow();

        $return = "resurs-standard.png";
        $imgPath = plugin_dir_path(__FILE__) . 'img/';
        $specialImageName = sprintf('method_%s.png', strtolower($payment_method->specificType));
        $specialImagePath = sprintf('%s%s', $imgPath, $specialImageName);

        if ($ecom->isPspCard($payment_method->specificType, $payment_method->type)) {
            $return = 'method_pspcard.svg';
        } elseif ($payment_method->type === 'PAYMENT_PROVIDER' && $payment_method->specificType === 'INTERNET') {
            $return = 'trustly.svg';
        } elseif (file_exists($specialImagePath)) {
            $return = $specialImageName;
        }

        return $return;
    }
}

if (!function_exists('write_resurs_class_to_file')) {
    /**
     * Write class files on the fly - normally from wp-admin, when payment methods needs to be rewritten,
     * but also, from the glob-function where the main dependency for checkout function resides.
     *
     * Note: We usually limit this feature to is_admin() but since the simplified/hosted flow are highly dependent
     * on the class files, we might want to do this on the fly, in case there are deployment processes
     * or anything else that breaks the site by simply removing the files from where they were stored.
     *
     * Returns boolean if file exists after write to indicate when files are missing.
     *
     * @param $payment_method
     * @param $idMerchant
     * @return bool
     */
    function write_resurs_class_to_file($payment_method, $idMerchant)
    {
        $return = false;

        $idMerchantPrio = 10 + $idMerchant;
        // Rewriting class names should also include cleaning up static transients.
        if (isset($payment_method->id)) {
            delete_transient(
                sprintf(
                    'resursTemporaryMethod_%s',
                    $payment_method->id
                )
            );
        }
        // No id - no file.
        if (!isset($payment_method->id) || (isset($payment_method->id) && empty($payment_method->id))) {
            return;
        }
        $class_name = 'resurs_bank_nr_' . $payment_method->id;
        if (!file_exists(plugin_dir_path(__FILE__) . '/' . getResursPaymentMethodModelPath() . $class_name)) {
            $classFilePresent = false;
        } else {
            $classFilePresent = true;
            if (!in_array(
                plugin_dir_path(__FILE__) . '/' . getResursPaymentMethodModelPath() . $class_name,
                get_included_files()
            )) {
                include(plugin_dir_path(__FILE__) . '/' . getResursPaymentMethodModelPath() . $class_name);
            }
        }

        $initName = 'woocommerce_gateway_resurs_bank_nr_' . $payment_method->id . '_init';
        $class_name = 'resurs_bank_nr_' . $payment_method->id;
        $classFileName = 'resurs_bank_nr_' . $idMerchant . '_' . $payment_method->id;
        if (isset($payment_method->country)) {
            $classFileName .= '_' . $payment_method->country;
        }
        $classFileName .= '.php';

        $methodId = 'resurs-bank-method-nr-' . $payment_method->id;
        $method_name = addslashes($payment_method->description);
        $type = strtolower($payment_method->type);
        $customerType = $payment_method->customerType;
        $minLimit = $payment_method->minLimit;
        $maxLimit = $payment_method->maxLimit;

        $isPsp = "false";
        if ($payment_method->customerType === "PAYMENT_PROVIDER" ||
            $payment_method->type === "PAYMENT_PROVIDER"
        ) {
            $isPsp = 'true';
        }
        $allowPsp = 'true';
        $plugin_url = untrailingslashit(plugins_url('/', __FILE__));
        $icon_name = getResursIconByType($payment_method);
        $temp_icon = plugin_dir_path(__FILE__) . 'img/' . $icon_name;

        $icon = apply_filters(
            'woocommerce_resurs_bank_' . $type . '_checkout_icon',
            $plugin_url . '/img/' . $icon_name
        );
        // Do not remove....
        $path_to_icon = $icon;
        // Do not remove this either...
        $has_icon = (string)file_exists($temp_icon);

        $ajaxUrl = admin_url('admin-ajax.php');
        $costOfPurchase = $ajaxUrl . "?action=get_priceinfo_ajax";

        $customerTypeArray = [];
        foreach ((array)$customerType as $item) {
            $customerTypeArray[] = sprintf("'%s'", $item);
        }
        // Convert customer type arrays to strings so it can be injected into the writer.
        $customerTypeAsString = sprintf('[%s]', implode(',', $customerTypeArray));

        $writeDate = date('Y-m-d H:i');
        $class = <<<EOT
<?php
    /*
     * Written {$writeDate}.
     */
    if (!class_exists('{$class_name}')) {
        class {$class_name} extends WC_Resurs_Bank {
            public \$type;
            public \$specificType;
            public \$customerType;
            public \$currentCustomerType;
        
            public function __construct()
            {
                global \$woocommerce, \$globalCustomerType;

                \$post_data = isset(\$_REQUEST['post_data']) ? rbSplitPostData(\$_REQUEST['post_data']) : [];
                if (isset(WC()->session) && isset(\$post_data['ssnCustomerType'])) {
                    \$cType = \$post_data['ssnCustomerType'];
                    //rbSimpleLogging('CustomerType set from session: ' . \$cType);
                    WC()->session->set('ssnCustomerType', \$cType);
                    \$globalCustomerType = \$cType;
                }

                // isResursDemo is no longer in use.
                \$this->isDemo = isResursTest();
                \$this->overRideIsAvailable = true;
                \$this->overRideDescription = false;
                \$this->sortOrder = '{$idMerchant}';
                \$this->hasErrors = false;
                \$this->forceDisable = false;
                \$this->id           = '{$class_name}';
                \$this->id_short           = '{$payment_method->id}';
                \$this->has_icon();
                \$this->isPsp = {$isPsp};
                \$this->allowPsp = {$allowPsp};
                // Customer type from payment method.
                \$this->customerType = {$customerTypeAsString};
                \$this->method_title = '{$method_name}';
                if (!isResursHosted()) {
                    \$this->has_fields   = true;
                } else {
                    \$this->has_fields   = false;
                }
                
                \$this->type = '{$payment_method->type}';
                \$this->specificType = '{$payment_method->specificType}';
    
                \$this->init_form_fields();
                \$this->init_settings();
                \$this->minLimit = '{$minLimit}';
                \$this->maxLimit = '{$maxLimit}';
    
                \$resursTemporaryMethodTime = get_transient("resursTemporaryMethodTime_" . \$this->id_short);
                \$resursTemporaryMethod = unserialize(get_transient("resursTemporaryMethod_" . \$this->id_short));
                \$realTimePaymentMethod = \$resursTemporaryMethod;

                \$storedTitle = getResursOption('title', 'woocommerce_{$class_name}_settings');
                \$resursTitle = null;
                if (isset(\$resursTemporaryMethod->description)) {
                    \$resursTitle = \$resursTemporaryMethod->description;
                }
                \$this->title = !empty(\$storedTitle) ? \$storedTitle : \$resursTitle;

                \$timeDiff = time() - \$resursTemporaryMethodTime;
                \$maxWaitTimeDiff = 3600;
    
                if (empty(\$this->title) || strtolower(\$this->title) == "resurs bank") {
                    \$this->flow = initializeResursFlow();
                    if (\$this->allowPsp) {
                        \$this->flow->setSimplifiedPsp(true);
                        \$timeDiff = \$maxWaitTimeDiff + 1;
                    }
                    try {
                        // Refetch after timelimit end
                        if (\$timeDiff >= \$maxWaitTimeDiff) {
                            \$realTimePaymentMethod = \$this->flow->getPaymentMethodSpecific(\$this->id_short);
                            set_transient("resursTemporaryMethodTime_" . \$this->id_short, time());
                            set_transient("resursTemporaryMethod_" . \$this->id_short, serialize(\$realTimePaymentMethod));
                            \$this->title = isset(\$realTimePaymentMethod->description) ? \$realTimePaymentMethod->description : '';
                        } else {
                            \$realTimePaymentMethod = unserialize(get_transient("resursTemporaryMethod_" . \$this->id_short));
                        }

                        // Fetch this data if there is no errors during controls (this could for example, if credentials are wrong, generate errors that makes the site unreachable)
                    } catch (Exception \$realTimeException) {
                        \$this->hasErrors = true;
                    }
                }

                \$this->customerTypes = isset(\$realTimePaymentMethod->customerType) ? (array)\$realTimePaymentMethod->customerType : [];

                if (empty(\$this->title) || strtolower(\$this->title) == "resurs bank") {
                    if (!isset(\$realTimePaymentMethod->id)) {
                        \$this->hasErrors = true;
                        \$this->forceDisable = true;
                    }
                    if (isset(\$realTimePaymentMethod->description)) {
                        \$this->title = \$realTimePaymentMethod->description;
                    }
                }

                \$this->description = \$this->get_option( 'description' );
                if (empty(\$this->description) || \$this->overRideDescription) {
                    \$this->description = \$this->title;
                }
    
  	            if (isset(\$woocommerce->cart)) {
                    \$cart = \$woocommerce->cart;
		            \$total = \$cart->total;
		            if (\$total > 0) {
			            if ((\$total >= \$this->maxLimit || \$total <= \$this->minLimit) && isResursDemo())
			            {
			                \$isDemo = ' <span class="resursPaymentDemoRestrictionView" title="'.__('In demo mode, this payment method is shown regardless of the total payment amount and the payment method limits', 'resurs-bank-payment-gateway-for-woocommerce').'">['.__('Restricted', 'resurs-bank-payment-gateway-for-woocommerce').']</span>';
			                \$this->description .= \$isDemo;
			                \$this->title .= \$isDemo;
			            }
		            }
	            }

                if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . \$this->id, array( \$this, 'process_admin_options' ) );
                } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( \$this, 'process_admin_options' ) );
                }
                //add_action( 'woocommerce_calculate_totals', array( \$this, 'calculate_totals' ), 10, 1 );
                // Payment listener/API hook
    
                add_action( 'woocommerce_api_{$class_name}', array( \$this, 'check_signing_response' ) );
            }
            
            /**
             * Making sure that the payment method acts properly
             * @return bool
             */
            public function is_available()
            {
                global \$globalCustomerType;
                if (isset(WC()->session)) {
                    \$this->currentCustomerType = WC()->session->get('ssnCustomerType');
                }

                // No title means no activity
                if (empty(\$this->title)) {
                    return false;
                }
                if (\$this->allowPsp === false) {
                    if (!isResursOmni() && \$this->isPsp === true) {
                        return false;
                    }
                }
                if (!\$this->overRideIsAvailable) {
                    return false;
                }
                \$curEnableState = getResursOption('enabled', 'woocommerce_{$class_name}_settings');
                \$enforceMethodList = getResursOption('enforceMethodList');
                \$isEnabled = apply_filters(
                  'resurs_method_is_enabled',
                   \$curEnableState,
                   \$this
                );
                
                // Log currentCustomerType only when set - or logs will get spammed.
                if (empty(\$globalCustomerType) && !empty(\$this->currentCustomerType)) {
                    // Borrow empty answers from non empty locations.
                    \$globalCustomerType = \$this->currentCustomerType;
                    rbSimpleLogging(
                        sprintf(
                            'CustomerType from session was empty. Using globalCustomerType from %s: %s', __FUNCTION__, \$globalCustomerType
                        )
                    );
                }
                
                // enforceMethodList is used for when merchants discovers that something is really wrong
                // and nothing else works.
                if (!\$isEnabled && !\$enforceMethodList) {
                    return false;
                }
                if (!\$enforceMethodList &&
                    hasDualCustomerTypes() &&
                    !empty(\$this->currentCustomerType) &&
                    !in_array(\$globalCustomerType, {$customerTypeAsString})
                ) {
                    return false;
                }

                return true;
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
                            'description' => __( 'This controls the payment method title which the user sees during checkout.', 'resurs-bank-payment-gateway-for-woocommerce' ),
                            'desc_tip'    => true,
                        ),
                    'icon' => array(
                            'title'   => __('Custom payment method icon', 'resurs-bank-payment-gateway-for-woocommerce'),
                            'type'    => 'text',
                            'default' => \$this->icon,
                            'description' => __('Used for branded logotypes as icons for the specific payment method. The image type must be a http/https-link. Suggested link is local, uploaded to WordPress own media storage.', 'resurs-bank-payment-gateway-for-woocommerce'),
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
                            'description' => __( 'This controls the payment method description which the user sees during checkout.', 'resurs-bank-payment-gateway-for-woocommerce' ),
                            'desc_tip'    => true,
                        ),
                    'price' => array(
                            'title'       => 'Avgift',
                            'type'        => 'number',
                            'default'     => 0,
                            'description' => __('Payment fee for this payment method', 'resurs-bank-payment-gateway-for-woocommerce'),
                            'desc_tip'    => false,
                        ),
                    'priceDescription' => array(
                            'title'   => __('Description of this payment method fee', 'resurs-bank-payment-gateway-for-woocommerce'),
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
                        \$payment_fee_tax_pct = (float)getResursOption('pricePct');
                        \$payment_fee_total = (float)\$payment_fee * ( ( \$payment_fee_tax_pct / 100 ) + 1 );
                        \$payment_fee_tax_class = get_option( 'woocommerce_resurs-bank_settings' )['priceTaxClass'];
                        \$payment_fee_tax_class_rates = WC_Tax::get_rates(\$payment_fee_tax_class);
                        \$payment_fee_tax = WC_Tax::calc_tax(\$payment_fee, \$payment_fee_tax_class_rates);

                        // TODO: WC_Cart->tax is deprecated since 2.3 - REMOVE THOSE LINES!
                        //\$payment_fee_tax_class_rates = \$woocommerce->cart->tax->get_rates( \$payment_fee_tax_class );
                        //\$payment_fee_tax = \$woocommerce->cart->tax->calc_tax(\$payment_fee, \$payment_fee_tax_class_rates);

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
                        \$payment_fee = getResursOption('price', 'woocommerce_' . \$payment_method . '_settings');
                        \$payment_fee = (float)( isset( \$payment_fee ) ? \$payment_fee : '0' );
                        \$payment_fee_tax_pct = (float)getResursOption('pricePct');
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
                        if (\$total > \$maxLimit || \$total < \$minLimit)
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
            add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_gateway_{$class_name}', {$idMerchantPrio} );
            add_action( 'woocommerce_checkout_process', '{$class_name}::interfere_checkout',0 );
            add_action( 'woocommerce_checkout_order_review', '{$class_name}::interfere_checkout_review', 1 );
            add_action( 'woocommerce_checkout_update_order_review', '{$class_name}::interfere_update_order_review', 1 );
            add_action( 'woocommerce_checkout_process', '{$class_name}::interfere_checkout_process', 1 );
            add_action( 'woocommerce_cart_calculate_fees', '{$class_name}::interfere_update_order_review', 1 ); /* For WooCommerce updated after 1.5.x */
        }
    }
EOT;

        $path = plugin_dir_path(__FILE__) . '/' . getResursPaymentMethodModelPath() . $classFileName;
        $path = str_replace('//', '/', $path);

        @file_put_contents($path, $class);
        if (file_exists($path)) {
            $return = true;
        }
        return $return;
    }
}

if (is_admin()) {
    if (!function_exists('generatePaymentMethodHtml')) {
        function generatePaymentMethodHtml($methodArray = [], $returnAs = "html")
        {
            $methodTable = "";
            if ($returnAs != "html") {
                @ob_start();
            } ?>
            <table class="wc_gateways widefat" cellspacing="0px" cellpadding="0px" style="width: inherit;">
                <thead>
                <tr>
                    <th class="sort"></th>
                    <th class="name"><?php echo __('Method', 'resurs-bank-payment-gateway-for-woocommerce') ?></th>
                    <th class="title"><?php echo __('Title', 'resurs-bank-payment-gateway-for-woocommerce') ?></th>
                    <th class="id"><?php echo __('ID', 'resurs-bank-payment-gateway-for-woocommerce') ?></th>
                    <th class="status"><?php echo __('Status', 'resurs-bank-payment-gateway-for-woocommerce') ?></th>
                    <th class="process"><?php echo __('Process', 'resurs-bank-payment-gateway-for-woocommerce') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php

                $sortByDescription = [];
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
                    } ?>
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
                } ?>
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
    if (!function_exists('callbackUpdateRequest')) {
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
            $callbackUpdateInterval = '';
            $login = getResursOption('login');
            $password = getResursOption('password');
            if (!empty($login) && !empty($password) && is_admin()) {
                // Make sure callbacks are up to date with an interval
                $cbuInterval = getResursOption('callbackUpdateInterval');
                $callbackUpdateInterval = !empty($cbuInterval) ? intval($cbuInterval) : 7;
                if ($callbackUpdateInterval > 7 || $callbackUpdateInterval < 0) {
                    $callbackUpdateInterval = 7;
                }
                $lastCallbackRequest = get_transient('resurs_bank_last_callback_setup');
                $lastCallbackRequestDiff = time() - $lastCallbackRequest;
                $dayInterval = $callbackUpdateInterval * 86400;
                $cbuAutomation = getResursOption("callbackUpdateAutomation");
                if (($cbuAutomation && $lastCallbackRequestDiff >= $dayInterval) || empty($lastCallbackRequest)) {
                    $requestForCallbacks = true;
                }
            }

            return $requestForCallbacks;
        }
    }
}

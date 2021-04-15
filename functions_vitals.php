<?php

/**
 * Get specific options from the Resurs configuration set
 *
 * @param string $key
 * @param string $namespace
 * @return bool
 * @deprecated Use getResursOption
 */
function resursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    return getResursOption($key, $namespace);
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

function getResursDemoOption($key)
{
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

/**
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function getResursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    if (($demoReturn = isResursDemo())) {
        return $demoReturn;
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

/**
 * Decide where and what Resurs plugin are allowed to interfere with.
 * This control is connected to the initial function of the plugin after plugins_loaded and controls where in
 * the is_admin structure the plugin should be working or prevented to be present:
 *      if (allowPluginToRun()) {
 *
 * @return bool|mixed|void
 */
function allowPluginToRun()
{
    // Always allow this plugin to be alive.
    $return = true;

    if (is_admin() && getResursOption('preventGlobalInterference')) {
        // At this point, we know that we're in wp-admin, so from here we can decide whether the plugin should
        // be present, regardless of what WooCommerce thinks (mind the edit-theme-plugin-file parts).
        $info = [
            'action' => isset($_REQUEST['action']) ? $_REQUEST['action'] : '',
            'page' => isset($_REQUEST['page']) ? $_REQUEST['page'] : '',
            'post_type' => isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : '',
        ];

        // From here apply necessary filters, and tell the developer where we are so that presence can be
        // freely limited by anyone.
        $return = apply_filters('allow_resurs_run', $return, $info);
    }

    return $return;
}

/**
 * @return array
 */
function getResursAllowedLocations()
{
    return [
        'wc-settings',
        'shop_order',
        'edit',
        'get_cost_ajax',
        'get_address_customertype',
    ];
}

/**
 * @return array|WP_Post|null
 */
function getResursAllowedEditor()
{
    $post = null;
    if (isset($_REQUEST['post']) && is_numeric($_REQUEST['post'])) {
        $post = get_post($_REQUEST['post']);
    }
    return $post;
}

/**
 * wp-admin interference checks.
 *
 * This method tells the plugin when it can skip its own presence in wp-admin. This method is only called with
 * the setting preventGlobalInterference is active.
 *
 * @param bool $allow Current inbound allow state.
 * @param array $informationSet Very basic requests from _REQUEST and _POST parameters that could easily be analyzed.
 * @return bool If true, the plugin is allowed to proceed.
 * @noinspection ParameterDefaultValueIsNotNullInspection
 */
function allowResursRun($allow = false, $informationSet = [])
{
    if (in_array($informationSet['action'], getResursAllowedLocations(), true) ||
        in_array($informationSet['page'], getResursAllowedLocations(), true) ||
        in_array($informationSet['post_type'], getResursAllowedLocations(), true)
    ) {
        $allow = true;
    }
    // Normally accept ajax actions. Discovered that "forgotten actions" could fail during this run.
    if (!empty($informationSet['action'])) {
        $allow = true;
    }
    if (stripos($informationSet['action'], 'woocommerce') !== false) {
        $allow = true;
    }

    $post = getResursAllowedEditor();
    if (is_object($post) && method_exists($post, 'post_type')) {
        // Executes our internal filter method, setting $allow to false if in "product"-editor.
        $allow = apply_filters('prevent_resurs_run_on_post_type', $allow, $post->post_type);
    }
    // Allow everything during usage of our own backend, if they ever reach this method.
    if (isset($_REQUEST['wc-api']) && !$allow) {
        $allow = true;
    }

    return $allow;
}

/**
 * @param $allow
 * @param $postType
 * @return false|mixed
 */
function resursPreventPostType($allow, $postType)
{
    if ($postType === 'product') {
        $allow = false;
    }

    return $allow;
}

/**
 * Returns true if demoshop-mode is enabled.
 *
 * @return bool
 */
function isResursDemo()
{
    $return = false;

    $resursSettings = get_option('woocommerce_resurs-bank_settings');
    $demoshopMode = isset($resursSettings['demoshopMode']) ? $resursSettings['demoshopMode'] : false;
    if ($demoshopMode === "true") {
        $return = true;
    }
    if ($demoshopMode === "yes") {
        $return = true;
    }
    if ($demoshopMode === "false") {
        $return = false;
    }
    if ($demoshopMode === "no") {
        $return = false;
    }

    return $return;
}

/**
 * @param string $key
 * @return bool|mixed|void
 */
function omniOption($key = '')
{
    $response = getResursOption($key, 'woocommerce_resurs_bank_omnicheckout_settings');

    return $response;
}

/**
 * Add notification on misconfigured filters.
 */
function notify_resurs_admin_parts_disabled()
{
    // Payment methods for simplified/hosted/RCO is normally true initially.
    $simplifiedEnabled = apply_filters('resurs_bank_simplified_checkout_methods', true);
    $omniEnabled = apply_filters('resurs_bank_simplified_checkout_methods', true);
    if ((!$simplifiedEnabled || !$omniEnabled)) {
        // Warn about remotely disabled methods.
        echo '<div class="error notice resursAdminPartsDisabled">
            ' .
            __(
                'An external plugin has partially disabled information about payment methods in your Resurs ' .
                'Bank admin console. If this is correct, ignore this message.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ) .
            '</div>';
    }
}

/**
 * Defines what metadata in the order data section that is protected (= not visible).
 * @param $protected
 * @param $metaKey
 * @param $metaType
 * @return bool
 * @since 2.2.41
 */
function resurs_protected_meta_data($protected, $metaKey, $metaType)
{
    // You may like to fetch this data and display it elsewhere.
    $protectMeta = getResursProtectedMetaData();

    if (isset($protectMeta[$metaKey])) {
        $protected = true;
    }

    return $protected;
}

/**
 * What we hide in metadata section.
 *
 * @return array
 * @since 2.2.41
 */
function getResursProtectedMetaData()
{
    return [
        'hasCallbackAUTOMATIC_FRAUD_CONTROL' => 'Callback AUTOMATIC_FRAUD_CONTROL',
        'hasCallbackBOOKED' => 'Callback BOOKED',
        'hasCallbackUNFREEZE' => 'Callback UNFREEZE',
        'hasCallbackUPDATE' => 'Callback UPDATE',
        'hasCallbackANNULMENT' => 'Callback ANNULMENT',
        'hasCallbackFINALIZATION' => 'Callback FINALIZATION',
        'orderBookStatus' => __(
            'Initial status retrieved from bookPayment',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'paymentId' => 'paymentId',
        'resursBankMetaPaymentMethod' => 'Payment Method',
        'resursBankMetaPaymentMethodSpecificType' => 'SpecificType',
        'resursBankMetaPaymentMethodType' => 'Type',
        'resursBankMetaPaymentStoredVatData' => 'VAT Data',
        'resursBankPaymentFlow' => __(
            'Payment flow',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'orderDenied' => 'orderDenied',
        'omniPaymentMethod' => __(
            'Resurs Checkout Payment Method',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'paymentIdLast' => __(
            'Last used payment method',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'RcoProcessPaymentStart' => __(
            'Start time for RCO purchase',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
        'RcoProcessPaymentEnd' => __(
            'End time for RCO purchase',
            'resurs-bank-payment-gateway-for-woocommerce'
        ),
    ];
}

/**
 * VAT data storage returns information about how a recent order has been handled.
 * @param $id
 * @return array|mixed|string
 * @since 2.2.41
 */
function getResursStoredPaymentVatData($id, $key = '')
{
    $storedVatData = [
        'coupons_include_vat' => getResursOption('coupons_include_vat'),
        'meta_available' => false,
    ];

    try {
        $metaConfiguration = unserialize(getResursPaymentMethodMeta($id, 'resursBankMetaPaymentStoredVatData'));
    } catch (\Exception $e) {
        // In case the passed string is not unserializeable, FALSE is returned and E_NOTICE is issued.
        // And if possible, make silence out of that.
        $metaConfiguration = null;
    }
    if (is_array($metaConfiguration)) {
        $metaConfiguration['meta_available'] = true;
        $return = $metaConfiguration;
    } else {
        $return = $storedVatData;
    }

    if (!empty($key) && isset($return[$key])) {
        return $return[$key];
    }

    return $return;
}

/**
 * Extends number of calculated decimals in a price on demand.
 * Filter is disabled for the moment, but can be activated if we need to round up differently.
 * @param $currentValue
 * @return mixed
 */
function resurs_order_price_decimals($currentValue)
{
    global $resurs_is_payment_spec;

    if ((bool)$resurs_is_payment_spec === true) {
        $currentValue = 5;
    }

    return $currentValue;
}

add_action('admin_notices', 'notify_resurs_admin_parts_disabled');

/**
 * @return bool|null
 */
function resurs20StartSession()
{
    return getResursOption('resursbank_start_session_before');
}

/**
 * @return bool|null
 */
function resurs20StartSessionAdmin()
{
    return getResursOption('resursbank_start_session_outside_admin_only');
}

add_filter('resursbank_start_session_before', 'resurs20StartSession');
add_filter('resursbank_start_session_outside_admin_only', 'resurs20StartSessionAdmin');

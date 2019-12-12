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

function allowPluginToRun()
{
    if (!getResursOption('preventGlobalInterference')) {
        return true;
    }

    // Initially always allow runs.
    $allowed = true;
    if (is_admin()) {
        // edit-theme-plugin-file has been a problem, however - at this moment we know we're located
        // somewhere in wp-admin, so from here, everything should be disallowed.

        $info = [
            'action' => isset($_REQUEST['action']) ? $_REQUEST['action'] : '',
            'page' => isset($_REQUEST['page']) ? $_REQUEST['page'] : '',
            'post_type' => isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : '',
        ];

        $allowed = apply_filters('allow_resurs_run', $allowed, $info);
    }
    return $allowed;
}

/**
 * @param $allow Current inbound allow state.
 * @param $info Very basic requests from _REQUEST and _POST parameters that could easily be analyzed.
 * @return bool If true, the plugin is allowed to proceed.
 */
function allowResursRun($allow, $info)
{
    // For this method, $allow above is ignored and considered always off.
    // Since we're in admin this is an option that won't harm very much in frontend and store views.
    $allow = false;

    // Heartbeats are known to pass here. In our case we choose to ignore the heartbeats.
    $allowFrom = [
        'wc-settings',
        'shop_order',
        'edit',
    ];

    // Refunds passing wp-remove-post-lock (ignored).

    if (in_array($info['action'], $allowFrom) ||
        in_array($info['page'], $allowFrom) ||
        in_array($info['post_type'], $allowFrom)
    ) {
        $allow = true;
    }

    if (preg_match('/woocommerce/i', $info['action'])) {
        $allow = true;
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
 *
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
            ' . __(
                'An external plugin has partially disabled information about payment methods in your Resurs ' .
                'Bank admin console. If this is correct, ignore this message.',
                'resurs-bank-payment-gateway-for-woocommerce'
            ) .
            '</div>';
    }
}

add_action('admin_notices', 'notify_resurs_admin_parts_disabled');

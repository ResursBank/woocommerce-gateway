<?php

/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Connects Resurs Bank as a payment gateway for WooCommerce
 * WC Tested up to: 3.8.0
 * Version: 2.2.24
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 */

define('RB_WOO_VERSION', '2.2.24');
define('RB_ALWAYS_RELOAD_JS', true);
define('RB_WOO_CLIENTNAME', 'resurs-bank-payment-gateway-for-woocommerce');

$resurs_obsolete_coexistence_disable = false;

function activateResursGatewayScripts()
{
    global $resurs_obsolete_coexistence_disable;
    if (!preventResursRuns()) {
        require_once('resursbankmain.php');
        if (!$resurs_obsolete_coexistence_disable) {
            add_action('admin_notices', 'resurs_bank_admin_notice');
            woocommerce_gateway_resurs_bank_init();
        }
    }
}

function preventResursRuns()
{
    $allowed = true;
    if (is_admin()) {
        // edit-theme-plugin-file
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $allowed = apply_filters('prevent_resurs_run_on', $allowed, $action);
    }
    return $allowed;
}

function preventResursRunRequest($allow, $action)
{
    $preventOn = [
        'edit-theme-plugin-file'
    ];
    foreach ($preventOn as $key) {
        if ($action === $key) {
            $allow = false;
            break;
        }
    }

    return $allow;
}

add_filter('prevent_resurs_run_on', 'preventResursRunRequest', 10, 2);
add_filter('prevent_resurs_run_on', 'preventResursRunRequestA', 10, 2);
add_action('plugins_loaded', 'activateResursGatewayScripts');

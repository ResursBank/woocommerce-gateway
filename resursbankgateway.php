<?php

/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Connects Resurs Bank as a payment gateway for WooCommerce
 * WC Tested up to: 4.8.0
 * Version: 2.2.44
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 */

define('RB_WOO_VERSION', '2.2.43');
define('RB_ALWAYS_RELOAD_JS', true);
define('RB_WOO_CLIENTNAME', 'resurs-bank-payment-gateway-for-woocommerce');

require_once(__DIR__ . '/functions_settings.php');
require_once(__DIR__ . '/functions_vitals.php');

$resurs_obsolete_coexistence_disable = false;

function activateResursGatewayScripts()
{
    global $resurs_obsolete_coexistence_disable;
    if (allowPluginToRun()) {
        require_once(__DIR__ . '/resursbankmain.php');
        if (!$resurs_obsolete_coexistence_disable) {
            add_action('admin_notices', 'resurs_bank_admin_notice');
            woocommerce_gateway_resurs_bank_init();
        }
    }
}

add_filter('allow_resurs_run', 'allowResursRun', 10, 2);
add_action('plugins_loaded', 'activateResursGatewayScripts');
add_filter('prevent_resurs_run_on_post_type', 'resursPreventPostType', 10, 2);
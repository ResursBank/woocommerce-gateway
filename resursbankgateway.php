<?php

/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Connects Resurs Bank as a payment gateway for WooCommerce
 * WC Tested up to: 5.1.0
 * Version: 2.2.47
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 */

define('RB_WOO_VERSION', '2.2.47');
define('RB_ALWAYS_RELOAD_JS', true);
define('RB_WOO_CLIENTNAME', 'resurs-bank-payment-gateway-for-woocommerce');

require_once(__DIR__ . '/functions_settings.php');
require_once(__DIR__ . '/functions_vitals.php');

//$resurs_obsolete_coexistence_disable = false;
/**
 * @return bool
 * @since 2.2.47
 */
function getOldRbVersionAppearance()
{
    return true;
}

function activateResursGatewayScripts()
{
    add_filter('resurs_bank_v22_woo_appearance', 'getOldRbVersionAppearance');
    if (allowPluginToRun()) {
        require_once(__DIR__ . '/resursbankmain.php');
        // Allow or disallow plugins to exist side by side with similar.
        if (!(bool)apply_filters('resurs_obsolete_coexistence_disable', null)) {
            add_action('admin_notices', 'resurs_bank_admin_notice');
            woocommerce_gateway_resurs_bank_init();
        }
    }
}

// Interference filters activated from wp-admin.
add_filter('allow_resurs_run', 'allowResursRun', 10, 2);
add_filter('prevent_resurs_run_on_post_type', 'resursPreventPostType', 10, 2);
add_action('plugins_loaded', 'activateResursGatewayScripts');

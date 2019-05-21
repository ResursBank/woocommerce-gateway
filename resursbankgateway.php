<?php

/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Connects Resurs Bank as a payment gateway for WooCommerce
 * WC Tested up to: 3.6.3
 * Version: 2.2.19
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 */

define('RB_WOO_VERSION', '2.2.19');
define('RB_ALWAYS_RELOAD_JS', true);
define('RB_WOO_CLIENTNAME', 'resus-bank-payment-gateway-for-woocommerce');

$resurs_obsolete_coexistence_disable= false;

function activateResursGatewayScripts() {
    global $resurs_obsolete_coexistence_disable;
    //add_action('plugins_loaded', 'woocommerce_gateway_resurs_bank_init');
    require_once('resursbankmain.php');
    if (!$resurs_obsolete_coexistence_disable) {
        add_action('admin_notices', 'resurs_bank_admin_notice');
        woocommerce_gateway_resurs_bank_init();
    }
}

add_action('plugins_loaded', 'activateResursGatewayScripts');

<?php

/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Connects Resurs Bank as a payment gateway for WooCommerce
 * WC Tested up to: 3.5.1
 * Version: 2.2.14
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: resurs-bank-payment-gateway-for-woocommerce
 */

// Introducing more hooks and filters as of 2.2.15

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
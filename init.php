<?php
/**
 * Plugin Name: Tornevall Networks Resurs Bank payment gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway
 * WC Tested up to: 3.5.0
 * Version: 0.0.0
 * Author: Tomas Tornevall
 * Author URI:
 * Text Domain: tornevall-networks-resurs-bank-payment-gateway-for-woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Plans:
 *
 * - Make plugin hookable for as many addons as possible
 * - Make the plugin look good
 * - Make the plugin as modular as possible, to simplify development without code collisions
 * - Only run plugin in sections where WooCommerce is involved
 *
 */


// This is where it all begins.

define('_RESURSBANK_GATEWAY_PATH', plugin_dir_path(__FILE__));
define('_RESURSBANK_GATEWAY_URL', plugin_dir_url(__FILE__));
define('_RESURSBANK_GATEWAY_BACKEND', admin_url('admin-ajax.php'));
define('_RESURSBANK_GATEWAY_VERSION', '0.0.0');
define('_RESURSBANK_DEVELOPER_MODE', true);
define('_RESURSBANK_LOWEST_WOOCOMMERCE', '3.0');

require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Core.php');
if (!Resursbank_Core::getInternalEcomEngine()) {
    require_once(_RESURSBANK_GATEWAY_PATH . 'vendor/autoload.php');
}
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Ajax.php');
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Gateway.php');
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Config.php');
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Helpers/Confighooks.php');
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Helpers/Functions.php');
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Helpers/Adminforms.php');
require_once(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');

if (function_exists('add_action')) {
    setResursbankGatewayFilters();
    setResursbankGatewayHeader();
    add_action('plugins_loaded', 'resursbank_payment_gateway_initialize');
}



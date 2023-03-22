<?php
/**
 * Plugin Name: Resurs Bank Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/
 * Description: Connects Resurs as a payment gateway for WooCommerce
 * WC Tested up to: 7.5.0
 * Version: 2.2.105
 * Author: Resurs Bank AB
 * Author URI: https://test.resurs.com/docs/display/ecom/WooCommerce
 * Text Domain: resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /languages/
 */

use TorneLIB\Utils\WordPress as WPUtils;

if (!defined('ABSPATH')) {
    exit;
}

require_once(__DIR__ . '/functions_settings.php');
require_once(__DIR__ . '/functions_vitals.php');

$wpUtils = new WPUtils();
$wpUtils->setPluginBaseFile(__FILE__);

define('RB_WOO_VERSION', $wpUtils->getCurrentVersion());
define('RB_ALWAYS_RELOAD_JS', true);
define('RB_WOO_CLIENTNAME', 'resurs-bank-payment-gateway-for-woocommerce');

load_plugin_textdomain(
    'resurs-bank-payment-gateway-for-woocommerce',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages/'
);

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
    // Making sure this default is saved.
    $defaults = get_option('woocommerce_resurs-bank_settings');
    $current = getResursOption('instant_migrations');
    if (!isset($defaults['instant_migrations'])) {
        setResursOption('instant_migrations', $current);
    }

    //resursExpectVersions();
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

/**
 * @since 2.2.58
 */
function rb_option_update_watch($option, $old, $new)
{
    if ($option === 'woocommerce_resurs-bank_settings') {
        $oldFlow = $old['flowtype'];
        $newFlow = $new['flowtype'];
        if ($oldFlow !== $newFlow) {
            // This option is unexistent in the configuration as it is currently more safe to not clean up
            // paying sessions than doing it - since there may be customers in the middle of a signing procedure
            // that may loose their sessions on their way back to the landing page.
            global $wpdb;
            $wpdb->query("TRUNCATE {$wpdb->prefix}woocommerce_sessions");
        }
    }
}

// Interference filters activated from wp-admin.
add_filter('allow_resurs_run', 'allowResursRun', 10, 2);
add_filter('prevent_resurs_run_on_post_type', 'resursPreventPostType', 10, 2);
add_action('plugins_loaded', 'activateResursGatewayScripts');
add_action('admin_notices', 'getRbAdminNotices');
add_action('updated_option', 'rb_option_update_watch', 10, 3);

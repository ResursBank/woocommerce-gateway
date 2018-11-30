<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper functions without class definitions
 */
function setResursbankGatewayFilters()
{
    // Legacy method.
    //add_filter('woocommerce_payment_gateways', 'Resursbank_Core::getResursGateways');

    // Fetch Resurs payment methods for checkout
    add_filter(
        'woocommerce_available_payment_gateways',
        'Resursbank_Core::getAvailableGateways'
    );
    add_filter(
        'woocommerce_get_settings_pages', 'resursbank_gateway_settings'
    );

    add_filter('resurs_obsolete_coexistence_disable', 'Resursbank_Core::resurs_obsolete_coexistence_disable');
}

/**
 * Prepare header scripts and styles
 */
function setResursbankGatewayHeader()
{
    add_action('wp_enqueue_scripts', 'Resursbank_Core::setResursBankScripts');
    add_action('admin_enqueue_scripts', 'Resursbank_Core::setResursBankScripts');
}

/**
 * @param $settings
 * @return array
 */
function resursbank_gateway_settings($settings)
{
    if (is_admin()) {
       $settings[] = include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Admin.php');
    }

    return $settings;
}

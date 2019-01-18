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
    add_filter('woocommerce_payment_gateways', 'Resursbank_Core::getMethodsFromGateway');

    // Fetch Resurs payment methods for checkout
    add_filter(
        'woocommerce_available_payment_gateways',
        'Resursbank_Core::getAvailableGateways'
    );

    // Settings page handling.
    add_filter('woocommerce_get_settings_pages', 'resursbank_gateway_settings');

    // Ability to disable v2.x on fly
    add_filter('resurs_obsolete_coexistence_disable', 'Resursbank_Core::resursObsoleteCoexistenceDisable');

    // Trigger precense from checkout and store historically in a session.
    add_action('woocommerce_before_checkout_form', 'Resursbank_Core::setCustomerIsInCheckout');

    // Trigger absence from checkout and store historically in a session.
    add_action('woocommerce_add_to_cart', 'Resursbank_Core::setCustomerIsOutsideCheckout');
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

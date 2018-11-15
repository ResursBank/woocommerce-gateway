<?php

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
}

function setResursbankGatewayHeader() {
    add_action('wp_enqueue_scripts', 'Resursbank_Core::setResursBankScripts');
    add_action('admin_enqueue_scripts', 'Resursbank_Core::setResursBankScripts');
}
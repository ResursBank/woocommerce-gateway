<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field script loading filter. Used to extend aftershop flow settings.
 *
 * @param $scriptLoader
 * @param string $configKey
 * @return string
 */
function resursbank_configrow_internal_scriptloader($scriptLoader, $configKey = '')
{
    if ($configKey === 'test_function_field') {
        $scriptLoader = 'onclick="testFunctionFieldJs()"';
    }
    if ($configKey === 'environment') {
        $scriptLoader = 'onchange="resursBankCredentialsUpdate()"';
    }

    return $scriptLoader;
}

function resursbank_get_credential_fields()
{
    $AdminForms = new Resursbank_Adminforms();
    return $AdminForms->getCredentialFields();
}

$dataInfoFilters = Resursbank_Core::resursbank_get_plugin_data_array();
foreach ($dataInfoFilters as $key) {
    add_filter('resursbank_data_info_' . $key, 'Resursbank_Core::get_data_info');
}

add_filter('resursbank_configrow_scriptloader', 'resursbank_configrow_internal_scriptloader', 10, 2);
add_filter('resursbank_dropdown_option_method_get_tax_classes', 'Resursbank_Core::getTaxRateList');
add_filter('resursbank_config_element_get_credentials_html', 'resursbank_get_credential_fields');
add_filter('resursbank_config_element_get_plugin_data', 'Resursbank_Core::resursbank_get_plugin_data');
add_filter('resursbank_config_disable_coexist_warnings', 'Resursbank_Core::resursbank_get_coexist_dismissed');
add_filter('resursbank_config_array', 'Resursbank_Core::resursbank_get_dismissed_elements', 10, 1);

add_filter('resursbank_admin_backend_get_shopflow_options', 'Resursbank_Adminforms::get_shopflow_options');
add_filter('resursbank_admin_backend_get_payment_methods', 'Resursbank_Core::get_payment_methods');
add_filter('resursbank_config_save_data_paymentMethodListTimer', 'Resursbank_Core::get_payment_list_timer');
add_filter('woocommerce_before_checkout_billing_form', 'Resursbank_Core::resursBankGetAddress');

// Customer form fields generator
add_filter('resursbank_get_customer_field_html_generic', 'Resursbank_Forms::get_customer_field_html_generic', 10, 3);
add_filter('resursbank_get_customer_field_html_read_more', 'Resursbank_Forms::get_customer_field_html_read_more', 10,2);
//add_filter('resursbank_get_customer_field_html_card', 'Resursbank_Forms::get_customer_field_html_card', 10,2);

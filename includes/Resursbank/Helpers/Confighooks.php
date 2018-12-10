<?php

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

    return $scriptLoader;
}

function resursbank_get_credential_fields()
{
    $AdminForms = new Resursbank_Adminforms();
    return $AdminForms->getCredentialFields();
}

add_filter('resursbank_configrow_scriptloader', 'resursbank_configrow_internal_scriptloader', 10, 2);
add_filter('resursbank_dropdown_option_method_get_tax_classes', 'Resursbank_Core::getTaxRateList');
add_filter('resursbank_config_element_get_credentials_html', 'resursbank_get_credential_fields');
add_filter('resursbank_config_disable_coexist_warnings', 'Resursbank_Core::resursbank_get_coexist_dismissed');
add_filter('resursbank_admin_backend_dismiss_element', 'Resursbank_Core::resursbank_set_dismissed_element', 10, 2);
add_filter('resursbank_config_array', 'Resursbank_Core::resursbank_get_dismissed_elements', 10, 1);
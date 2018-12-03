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

/**
 * @param $optionArray
 * @param string $configKey
 * @return array
 */
function resursbank_configrow_internal_dropdown_options($optionArray = array(), $configKey = '')
{
    if ($configKey === 'dynamic_test') {
        $optionArray[] = rand(1024, 2048);
    }

    return (array)$optionArray;
}

add_filter('resursbank_configrow_scriptloader', 'resursbank_configrow_internal_scriptloader', 10, 2);
add_filter('resursbank_configrow_dropdown_options', 'resursbank_configrow_internal_dropdown_options', 10, 2);
add_filter('resursbank_dropdown_option_method_get_tax_classes', 'Resursbank_Core::getTaxRateList');

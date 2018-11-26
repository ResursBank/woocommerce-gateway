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

add_filter('resursbank_configrow_scriptloader', 'resursbank_configrow_internal_scriptloader', 10, 2);

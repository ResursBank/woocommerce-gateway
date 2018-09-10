<?php

global $ecomEvents;

if (!is_array($ecomEvents)) {
    $ecomEvents = array();
}

if (!function_exists('ecom_event_register')) {
    function ecom_event_register($eventName, $callback = null, $priority = 1)
    {
        global $ecomEvents;

        if (!isset($ecomEvents[$eventName])) {
            $ecomEvents[$eventName] = array();
        }
        if (!isset($ecomEvents[$eventName][$priority])) {
            $ecomEvents[$eventName][$priority] = array();
        }

        $ecomEvents[$eventName][$priority][] = $callback;
    }
}

if (!function_exists('ecom_event_run')) {
    function ecom_event_run($eventName, $args)
    {
        global $ecomEvents;

        $returns = null;
        if (isset($args[0]) && $args[0] == $eventName) {
            unset($args[0]);
        }
        if (isset($ecomEvents[$eventName]) && is_array($ecomEvents[$eventName])) {
            foreach ($ecomEvents[$eventName] as $eventCollection => $eventList) {
                if (is_array($eventList)) {
                    foreach ($eventList as $eventFunction) {
                        if (is_string($eventFunction) && function_exists($eventFunction)) {
                            $returns = call_user_func_array($eventFunction, $args);
                        }
                    }
                }
            }
        }

        return $returns;
    }
}

<?php

global $ecomEvents;

if (!is_array($ecomEvents)) {
    $ecomEvents = [];
}

if (!function_exists('ecom_event_register')) {
    /**
     * Simple hook generator.
     *
     * @param $eventName
     * @param null $callback
     * @param int $priority
     */
    function ecom_event_register($eventName, $callback = null, $priority = 1)
    {
        global $ecomEvents;

        if (!isset($ecomEvents[$eventName])) {
            $ecomEvents[$eventName] = [];
        }
        if (!isset($ecomEvents[$eventName][$priority])) {
            $ecomEvents[$eventName][$priority] = [];
        }

        $ecomEvents[$eventName][$priority][] = $callback;
    }

    /**
     * Simple event-unregisterer.
     *
     * @param $eventName
     */
    function ecom_event_unregister($eventName)
    {
        global $ecomEvents;
        if (!isset($ecomEvents[$eventName])) {
            unset($ecomEvents[$eventName]);
        }
    }

    /**
     * Reset to original state.
     */
    function ecom_event_reset()
    {
        $ecomEvents = [];
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

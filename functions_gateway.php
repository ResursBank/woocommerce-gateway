<?php

if (!function_exists('setResursNoAutoCancellation')) {
    /**
     * Prevent cancelation on automation.
     *
     * @param $order
     * @return string|void
     */
    function setResursNoAutoCancellation($order)
    {
        $flowErrorMessage = __(
            'Cancelling orders in automation mode prohibited',
            'resurs-bank-payment-gateway-for-woocommerce'
        );

        $order->add_order_note(
            __(
                'Automated cancellation detected (reserved orderstock timeout may cause this). Someone has to be logged in to cancel orders from Resurs Bank.',
                'resurs-bank-payment-gateway-for-woocommerce'
            )
        );

        return $flowErrorMessage;
    }
}

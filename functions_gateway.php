<?php

if (!function_exists('resurs_get_proper_article_number')) {

    /**
     * @param $product WC_Product
     * @return string
     * @since 2.2.21
     */
    function resurs_get_proper_article_number($product)
    {
        $setSku = $product->get_sku();
        $articleId = $product->get_id();
        $optionUseSku = getResursOption("useSku");
        if ($optionUseSku && !empty($setSku)) {
            $articleId = $setSku;
        }

        return $articleId;
    }
}

if (!function_exists('resurs_refund_shipping')) {
    /**
     * @param $orderData
     * @param $resursFlow
     * @return bool
     * @since 2.2.21
     */
    function resurs_refund_shipping($orderData, $resursFlow)
    {
        $return = false;
        $shippingTax = $orderData->get_shipping_tax();
        $shippingTotal = $orderData->get_shipping_total();
        if ($shippingTotal > 0) {
            $return = true;
            $shippingTax = preg_replace('/^-/', '', $shippingTax);
            $shippingTotal = preg_replace('/^-/', '', $shippingTotal);

            $shipping_tax_pct = (
            !is_nan(
                @round(
                    $shippingTax / $shippingTotal,
                    2
                ) * 100
            ) ? @round($shippingTax / $shippingTotal, 2) * 100 : 0
            );

            $resursFlow->addOrderLine(
                '00_frakt',
                __('Shipping', 'resurs-bank-payment-gateway-for-woocommerce'),
                preg_replace('/^-/', '', $shippingTotal),
                $shipping_tax_pct,
                'st',
                'SHIPPING_FEE',
                1
            );
        }

        return $return;
    }
}
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

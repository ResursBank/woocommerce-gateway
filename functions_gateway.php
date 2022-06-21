<?php

use Resursbank\RBEcomPHP\ResursBank;

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
     * @param WC_Order $orderData
     * @param ResursBank $resursFlow
     * @param bool $dryRun Run through shipping procedure without adding orderlines in ecom on dryRun=true
     * @return bool
     * @throws Exception
     * @since 2.2.41
     */
    function resurs_refund_shipping($orderData, $resursFlow, $dryRun = false)
    {
        $return = false;
        $shippingTax = $orderData->get_shipping_tax();
        $shippingTotal = $orderData->get_shipping_total();

        if (is_object($orderData) !== null && method_exists($orderData, 'get_total_shipping_refunded')) {
            $shippingRefunded = (float)$orderData->get_total_shipping_refunded();

            // Check if shipping has been refunded already.
            if ((float)$shippingTotal === $shippingRefunded) {
                return $return;
            }
        }

        // Resurs Bank does not like negative values when adding orderrows, so
        // we make them positive. When partially refunding and shipping is left out
        // those values will be 0 anyway.
        $shippingTax = preg_replace('/^-/', '', $shippingTax);
        $shippingTotal = preg_replace('/^-/', '', $shippingTotal);
        if ($shippingTotal > 0) {
            $return = true;

            $shipping_tax_pct = (
            !is_nan(
                @round(
                    $shippingTax / $shippingTotal,
                    2
                ) * 100
            ) ? @round($shippingTax / $shippingTotal, 2) * 100 : 0
            );

            if (!$dryRun) {
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
     * @since 2.2.21
     */
    function setResursNoAutoCancellation($order)
    {
        $flowErrorMessage = __(
            'Cancelling orders in automation mode prohibited (i.e. stock reservation expires, etc).',
            'resurs-bank-payment-gateway-for-woocommerce'
        );

        $order->add_order_note(
            __(
                '[Resurs Bank] Automated cancellation detected (reserved orderstock timeout may cause this). Someone has to be logged in to cancel orders for Resurs Bank.',
                'resurs-bank-payment-gateway-for-woocommerce'
            )
        );

        return $flowErrorMessage;
    }
}

if (!function_exists('canResursRefund')) {
    /**
     * @param $resursOrderId
     * @throws \Exception Throws exception if not refundable.
     * @since 2.2.21
     */
    function canResursRefund($resursOrderId)
    {
        $resursFlow = initializeResursFlow();
        if ($resursFlow->canCredit($resursOrderId)) {
            $paymentMethodType = getResursPaymentMethodMeta($resursOrderId, 'resursBankMetaPaymentMethodType');
            if ($paymentMethodType === 'PAYMENT_PROVIDER') {
                throw new \Exception('Not refundable', 1234);
            }
        }
    }
}

if (!function_exists('resurs_bank_admin_notice')) {
    /**
     * Used to output a notice to the admin interface
     */
    function resurs_bank_admin_notice()
    {
        global $resursGlobalNotice, $resursSelfSession;

        if (isset($_REQUEST['hasSessionMessage'])) {
            getResursRequireSession();
        }
        if (!is_array($resursSelfSession)) {
            $resursSelfSession = [];
        }

        if (isset($_SESSION) || $resursGlobalNotice === true) {
            if (is_array($_SESSION) && isset($_SESSION['resurs_bank_admin_notice'])) {
                if (!count($_SESSION) && count($resursSelfSession)) {
                    $_SESSION = $resursSelfSession;
                }
                $notice = '<div class=' . $_SESSION['resurs_bank_admin_notice']['type'] . '>';
                $notice .= '<p>' . $_SESSION['resurs_bank_admin_notice']['message'] . '</p>';
                $notice .= '</div>';
                echo $notice;
                if (isset($_SESSION['resurs_bank_admin_notice'])) {
                    unset($_SESSION['resurs_bank_admin_notice']);
                }
            }
        }
    }
}

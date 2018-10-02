<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once(__DIR__ . '/vendor/autoload.php');

use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;

/**
 * First extraction of functions not included in the primary WooCommerce caller class.
 *
 * Functions in this file is independent of the major gateway setup. Make sure this is loaded before other scripts
 * to maintain function in the plugin..
 */


/**
 * @param null $order
 * @throws Exception
 */
function resurs_order_data_info_after_order($order = null)
{
    resurs_order_data_info($order, 'AO');
}

/**
 * @param null $order
 * @throws Exception
 */
function resurs_order_data_info_after_billing($order = null)
{
    resurs_order_data_info($order, 'AB');
}

/**
 * @param null $order
 * @throws Exception
 */
function resurs_order_data_info_after_shipping($order = null)
{
    resurs_order_data_info($order, 'AS');
}

function resurs_no_debit_debited()
{
    ?>
    <div class="notice notice-error">
        <p><?php _e('It seems this order has already been finalized from an external system - if your order is finished you may update it here aswell',
                'WC_Payment_Gateway'); ?></p>
    </div>
    <?php
}

/**
 * Hook into WooCommerce OrderAdmin fetch payment data from Resurs Bank.
 * This hook are tested from WooCommerce 2.1.5 up to WooCommcer 2.5.2
 *
 * @param WC_Order $order
 * @param null     $orderDataInfoAfter
 *
 * @throws Exception
 */
function resurs_order_data_info($order = null, $orderDataInfoAfter = null)
{
    global $orderInfoShown;
    $resursPaymentInfo        = null;
    $showOrderInfoAfterOption = getResursOption("showOrderInfoAfter", "woocommerce_resurs-bank_settings");
    $showOrderInfoAfter       = ! empty($showOrderInfoAfterOption) ? $showOrderInfoAfterOption : "AO";
    if ($showOrderInfoAfter != $orderDataInfoAfter) {
        return;
    }
    if ($orderInfoShown) {
        return;
    }

    $orderInfoShown     = true;
    $renderedResursData = '';
    $orderId            = null;
    if ( ! isWooCommerce3()) {
        $resursPaymentId = get_post_meta($order->id, 'paymentId', true);
        $orderId         = $order->id;
    } else {
        $resursPaymentId = get_post_meta($order->get_id(), 'paymentId', true);
        $orderId         = $order->get_id();
    }
    if ( ! empty($resursPaymentId)) {
        $hasError = "";
        try {
            /** @var $rb \Resursbank\RBEcomPHP\ResursBank */
            $rb = initializeResursFlow();
            try {
                $rb->setFlag('GET_PAYMENT_BY_SOAP');
                $resursPaymentInfo = $rb->getPayment($resursPaymentId);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                if ($e->getCode() == 8) {
                    // REFERENCED_DATA_DONT_EXISTS
                    $errorMessage = __("Referenced data don't exist", 'WC_Payment_Gateway') . "<br>\n<br>\n";
                    $errorMessage .= __("This error might occur when for example a payment doesn't exist at Resurs Bank. Normally this happens when payments have failed or aborted before it can be completed",
                        'WC_Payment_Gateway');
                }

                $checkoutPurchaseFailTest = get_post_meta($orderId, 'soft_purchase_fail', true);
                if ($checkoutPurchaseFailTest == "1") {
                    $errorMessage = __('The order was denied at Resurs Bank and therefore has not been created',
                        'WC_Payment_Gateway');
                }

                echo '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                    <div style="padding: 30px;border:none;" id="resursInfo">
                        <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url(__FILE__) . '/img/rb_logo.png' . '"></span>
                        <fieldset>
                        <b>' . __('Following error ocurred when we tried to fetch information about the payment',
                        'WC_Payment_Gateway') . '</b><br>
                        <br>
                        ' . $errorMessage . '<br>
                    </fieldset>
                    </div>
                </div>
			    ';

                return;
            }
            $currentWcStatus = $order->get_status();
            $notIn           = array("completed", "cancelled", "refunded");
            if ( ! $rb->canDebit($resursPaymentInfo) && $rb->getIsDebited($resursPaymentInfo) && ! in_array($currentWcStatus,
                    $notIn)) {
                resurs_no_debit_debited();
            }
        } catch (Exception $e) {
            $hasError = $e->getMessage();
        }
        $renderedResursData .= '
                <div class="clear">&nbsp;</div>
                <div class="order_data_column_container resurs_orderinfo_container resurs_orderinfo_text">
                <div class="resurs-read-more-box">
                <div style="padding: 30px;border:none;" id="resursInfo">
                ';

        $invoices = array();
        if (empty($hasError)) {
            $status = "AUTHORIZE";
            if (is_array($resursPaymentInfo->paymentDiffs)) {
                $invoices = $rb->getPaymentInvoices($resursPaymentInfo);
                foreach ($resursPaymentInfo->paymentDiffs as $paymentDiff) {
                    if ($paymentDiff->type === "DEBIT") {
                        $status = "DEBIT";
                    }
                    if ($paymentDiff->type === "ANNUL") {
                        $status = "ANNUL";
                    }
                    if ($paymentDiff->type === "CREDIT") {
                        $status = "CREDIT";
                    }
                }
            } else {
                if ($resursPaymentInfo->paymentDiffs->type === "DEBIT") {
                    $status = "DEBIT";
                }
                if ($resursPaymentInfo->paymentDiffs->type === "ANNUL") {
                    $status = "ANNUL";
                }
                if ($resursPaymentInfo->paymentDiffs->type === "CREDIT") {
                    $status = "CREDIT";
                }
            }
            $renderedResursData .= '<div class="resurs_orderinfo_text paymentInfoWrapStatus paymentInfoHead">';
            if ($status === "AUTHORIZE") {
                $renderedResursData .= __('The order is booked', 'WC_Payment_Gateway');
            } elseif ($status === "DEBIT") {
                if ( ! $rb->canDebit($resursPaymentInfo)) {
                    $renderedResursData .= __('The order is debited', 'WC_Payment_Gateway');
                } else {
                    $renderedResursData .= __('The order is partially debited', 'WC_Payment_Gateway');
                }
            } elseif ($status === "CREDIT") {
                $renderedResursData .= __('The order is credited', 'WC_Payment_Gateway');
            } elseif ($status === "ANNUL") {
                $renderedResursData .= __('The order is annulled', 'WC_Payment_Gateway');
            } else {
                //$renderedResursData .= '<p>' . __('Confirm the invoice to be sent before changes can be made to order. <br> Changes of the invoice must be made in resurs bank management.') . '</p>';
            }
            $renderedResursData .= '</div>
                     <span class="paymentInfoWrapLogo"><img src="' . plugin_dir_url(__FILE__) . '/img/rb_logo.png' . '"></span>
                ';

            $addressInfo = "";
            if (is_object($resursPaymentInfo->customer->address)) {
                $addressInfo .= isset($resursPaymentInfo->customer->address->addressRow1) && ! empty($resursPaymentInfo->customer->address->addressRow1) ? $resursPaymentInfo->customer->address->addressRow1 . "\n" : "";
                $addressInfo .= isset($resursPaymentInfo->customer->address->addressRow2) && ! empty($resursPaymentInfo->customer->address->addressRow2) ? $resursPaymentInfo->customer->address->addressRow2 . "\n" : "";
                $addressInfo .= isset($resursPaymentInfo->customer->address->postalArea) && ! empty($resursPaymentInfo->customer->address->postalArea) ? $resursPaymentInfo->customer->address->postalArea . "\n" : "";
                $addressInfo .= (isset($resursPaymentInfo->customer->address->country) && ! empty($resursPaymentInfo->customer->address->country) ? $resursPaymentInfo->customer->address->country : "") . " " . (isset($resursPaymentInfo->customer->address->postalCode) && ! empty($resursPaymentInfo->customer->address->postalCode) ? $resursPaymentInfo->customer->address->postalCode : "") . "\n";
            }
            ThirdPartyHooksSetPaymentTrigger('orderinfo', $resursPaymentId,
                ! isWooCommerce3() ? $order->id : $order->get_id());

            $unsetKeys = array(
                'id',
                'paymentMethodId',
                'storeId',
                'paymentMethodName',
                'paymentMethodType',
                'totalAmount',
                'limit',
                'fraud',
                'frozen',
                'customer',
                'paymentDiffs'
            );

            $renderedResursData .= '
                <br>
                <fieldset>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment ID',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->id) && ! empty($resursPaymentInfo->id) ? $resursPaymentInfo->id : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment method ID',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->paymentMethodId) && ! empty($resursPaymentInfo->paymentMethodId) ? $resursPaymentInfo->paymentMethodId : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Store ID',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->storeId) && ! empty($resursPaymentInfo->storeId) ? $resursPaymentInfo->storeId : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment method name',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->paymentMethodName) && ! empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodName : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment method type',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->paymentMethodType) && ! empty($resursPaymentInfo->paymentMethodName) ? $resursPaymentInfo->paymentMethodType : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment amount',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->totalAmount) && ! empty($resursPaymentInfo->totalAmount) ? round($resursPaymentInfo->totalAmount,
                    2) : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Payment limit',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->limit) && ! empty($resursPaymentInfo->limit) ? round($resursPaymentInfo->limit,
                    2) : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Fraud',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->fraud) && ! empty($resursPaymentInfo->fraud) ? $resursPaymentInfo->fraud ? __('Yes') : __('No') : __('No')) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Frozen',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (isset($resursPaymentInfo->frozen) && ! empty($resursPaymentInfo->frozen) ? $resursPaymentInfo->frozen ? __('Yes') : __('No') : __('No')) . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Customer name',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (is_object($resursPaymentInfo->customer->address) && ! empty($resursPaymentInfo->customer->address->fullName) ? $resursPaymentInfo->customer->address->fullName : "") . '</span>

                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . __('Delivery address',
                    'WC_Payment_Gateway') . ':</span>
                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (! empty($addressInfo) ? nl2br($addressInfo) : "") . '</span>
            ';

            if (is_array($invoices) && count($invoices)) {
                $renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">Invoices:</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . implode(", ",
                        $invoices) . '</span>
                        ';
            }

            $continueView = $resursPaymentInfo;
            foreach ($continueView as $key => $value) {
                if (in_array($key, $unsetKeys)) {
                    unset($continueView->$key);
                }
            }
            if (is_object($continueView)) {
                foreach ($continueView as $key => $value) {
                    if ( ! is_array($value) && ! is_object($value)) {
                        $renderedResursData .= '
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . ':</span>
                            <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (! empty($value) ? nl2br($value) : "") . '</span>
                        ';
                    } else {
                        if ($key == "metaData") {
                            if (is_array($value)) {
                                foreach ($value as $metaArray) {
                                    $renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($metaArray->key) . ':</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . $metaArray->value . '</span>
                                    ';
                                }
                            } else {
                                $renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($value->key) . ':</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . $value->value . '</span>
                                ';
                            }
                        } else {
                            foreach ($value as $subKey => $subValue) {
                                $renderedResursData .= '
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_label">' . ucfirst($key) . " (" . ucfirst($subKey) . '):</span>
                                    <span class="wc-order-status label resurs_orderinfo_text resurs_orderinfo_text_value">' . (! empty($subValue) ? nl2br($subValue) : "") . '</span>
                                ';
                            }
                        }
                    }
                }
            }
        }
        $renderedResursData .= '</fieldset>
                <p class="resurs-read-more" id="resursInfoButton"><a href="#" class="button">' . __('Read more',
                'WC_Payment_Gateway') . '</a></p>
                </div>
                </div>
                </div>
            ';
    }
    //}
    echo $renderedResursData;
}

/**
 * Convert version number to decimals
 *
 * @return string
 */
function rbWcGwVersionToDecimals()
{
    $splitVersion = explode(".", RB_WOO_VERSION);
    $decVersion   = "";
    foreach ($splitVersion as $ver) {
        $decVersion .= str_pad(intval($ver), 2, "0", STR_PAD_LEFT);
    }

    return $decVersion;
}

/**
 * @return string
 */
function rbWcGwVersion()
{
    return RB_WOO_VERSION;
}

/**
 * Allows partial hooks from this plugin
 *
 * @param string $type
 * @param string $content
 */
function ThirdPartyHooks($type = '', $content = '', $addonData = array())
{
    $type             = strtolower($type);
    $allowedHooks     = array('orderinfo', 'callback');
    $paymentInfoHooks = array('orderinfo', 'callback');
    // Start with an empty content array
    $sendHookContent = array();

    // Put on any extra that the hook wishes to add
    if (is_array($addonData) && count($addonData)) {
        foreach ($addonData as $addonKey => $addonValue) {
            $sendHookContent[$addonKey] = $addonValue;
        }
    }

    // If the hook is basedon sending payment data info ...
    if (in_array(strtolower($type), $paymentInfoHooks)) {
        // ... then prepare the necessary data without revealing the full getPayment()-object.
        // This is for making data available for any payment bridging needed for external systems to synchronize payment statuses if needed.
        $sendHookContent['id']         = isset($content->id) ? $content->id : '';
        $sendHookContent['fraud']      = isset($content->fraud) ? $content->fraud : '';
        $sendHookContent['frozen']     = isset($content->frozen) ? $content->frozen : '';
        $sendHookContent['status']     = isset($content->status) ? $content->status : '';
        $sendHookContent['booked']     = isset($content->booked) ? strtotime($content->booked) : '';
        $sendHookContent['finalized']  = isset($content->finalized) ? strtotime($content->finalized) : '';
        $sendHookContent['iscallback'] = isset($content->iscallback) ? $content->iscallback : '';
    }
    if (in_array(strtolower($type), $allowedHooks)) {
        do_action("resurs_hook_" . $type, $sendHookContent);
    }
}

/**
 * Hooks that should initiate payment controlling, may be runned through the same function - making sure that we only
 * call for that hook if everything went nicely.
 *
 * @param string $type
 * @param string $paymentId
 * @param null   $internalOrderId
 * @param null   $callbackType
 *
 * @throws Exception
 */
function ThirdPartyHooksSetPaymentTrigger($type = '', $paymentId = '', $internalOrderId = null, $callbackType = null)
{
    /** @var $flow \Resursbank\RBEcomPHP\ResursBank */
    $flow          = initializeResursFlow();
    $paymentDataIn = array();
    try {
        $paymentDataIn = $flow->getPayment($paymentId);
        if ($type == "callback" && ! is_null($callbackType)) {
            $paymentDataIn->iscallback = $callbackType;
        } else {
            $paymentDataIn->iscallback = null;
        }
        if ( ! is_null($internalOrderId)) {
            $paymentDataIn->internalOrderId = $internalOrderId;
        }
        if (is_object($paymentDataIn)) {
            return ThirdPartyHooks($type, $paymentDataIn);
        }
    } catch (Exception $e) {
    }
}


/**
 * Unconditional OrderRowRemover for Resurs Bank. This function will run before the primary remove_order_item() in the
 * WooCommerce-plugin. This function won't remove any product on the woocommerce-side, it will however update the
 * payment at Resurs Bank. If removal at Resurs fails by any reason, this method will stop the removal from WooAdmin,
 * so we won't destroy any synch.
 *
 * @param $item_id
 *
 * @return bool
 * @throws Exception
 */
function resurs_remove_order_item($item_id)
{
    if ( ! $item_id) {
        return false;
    }
    // Make sure we still keep the former security
    if ( ! current_user_can('edit_shop_orders')) {
        die(-1);
    }

    /** @var $resursFlow \Resursbank\RBEcomPHP\ResursBank */
    $resursFlow = null;
    if (hasEcomPHP()) {
        $resursFlow = initializeResursFlow();
    }
    $clientPaymentSpec = array();
    if (null !== $resursFlow) {
        $productId  = wc_get_order_item_meta($item_id, '_product_id');
        $productQty = wc_get_order_item_meta($item_id, '_qty');
        $orderId    = r_wc_get_order_id_by_order_item_id($item_id);

        $resursPaymentId = get_post_meta($orderId, 'paymentId', true);

        if (empty($productId)) {
            $testItemType = r_wc_get_order_item_type_by_item_id($item_id);
            $testItemName = r_wc_get_order_item_type_by_item_id($item_id);
            if ($testItemType === "shipping") {
                $clientPaymentSpec[] = array(
                    'artNo'    => "00_frakt",
                    'quantity' => 1
                );
            } elseif ($testItemType === "coupon") {
                $clientPaymentSpec[] = array(
                    'artNo'    => $testItemName . "_kupong",
                    'quantity' => 1
                );
            } elseif ($testItemType === "fee") {
                if (function_exists('wc_get_order')) {
                    $current_order       = wc_get_order($orderId);
                    $feeName             = '00_' . str_replace(' ', '_', $current_order->payment_method_title) . "_fee";
                    $clientPaymentSpec[] = array(
                        'artNo'    => $feeName,
                        'quantity' => 1
                    );
                } else {
                    $order_failover_test = new WC_Order($orderId);
                    ///$payment_fee = array_values($order->get_items('fee'))[0];
                    $feeName             = '00_' . str_replace(' ', '_',
                            $order_failover_test->payment_method_title) . "_fee";
                    $clientPaymentSpec[] = array(
                        'artNo'    => $feeName,
                        'quantity' => 1
                    );
                    //die("Can not fetch order information from WooCommerce (Function wc_get_order() not found)");
                }
            }
        } else {
            $clientPaymentSpec[] = array(
                'artNo'    => $productId,
                'quantity' => $productQty
            );
        }

        try {
            $order           = new WC_Order($orderId);
            $removeResursRow = $resursFlow->paymentCancel($resursPaymentId, $clientPaymentSpec);
            $order->add_order_note(__('Orderline Removal: Resurs Bank API was called to remove orderlines',
                'WC_Payment_Gateway'));
        } catch (Exception $e) {
            $resultArray = array(
                'success' => false,
                'fail'    => utf8_encode($e->getMessage())
            );
            echo $e->getMessage();
            die();
        }
        if ( ! $removeResursRow) {
            echo "Cancelling payment failed without a proper reason";
            die();
        }
    }
}

/**
 * Get order by current payment id
 *
 * @param string $paymentId
 *
 * @return null|string
 */
function wc_get_order_id_by_payment_id($paymentId = '')
{
    global $wpdb;
    $order_id      = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and meta_value = '%s'",
        $paymentId));
    $order_id_last = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentIdLast' and meta_value = '%s'",
        $paymentId));

    // If updateOrderReference-setting is enabled, also look for a prior variable, to track down the correct order based on the metadata tag paymentIdLast
    if (getResursOption('postidreference') && ! empty($order_id_last) && empty($order_id)) {
        return $order_id_last;
    }

    return $order_id;
}

/**
 * Get payment id by order id
 *
 * @param string $orderId
 *
 * @return null|string
 */
function wc_get_payment_id_by_order_id($orderId = '')
{
    global $wpdb;
    $order_id = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = 'paymentId' and post_id = '%s'",
        $orderId));

    return $order_id;
}

/**
 * @param string $flagKey
 *
 * @return bool|string
 */
function getResursFlag($flagKey = null)
{
    $allFlags   = array();
    $flagRow    = getResursOption("devFlags");
    $flagsArray = explode(",", $flagRow);
    if (is_array($flagsArray)) {
        foreach ($flagsArray as $flagIndex => $flagParameter) {
            $flagEx = explode("=", $flagParameter, 2);
            if (is_array($flagEx) && isset($flagEx[1])) {
                // Handle as parameter key with values
                if ( ! is_null($flagKey)) {
                    if (strtolower($flagEx[0]) == strtolower($flagKey)) {
                        return $flagEx[1];
                    }
                } else {
                    $allFlags[$flagEx[0]] = $flagEx[1];
                }
            } else {
                if ( ! is_null($flagKey)) {
                    // Handle as defined true
                    if (strtolower($flagParameter) == strtolower($flagKey)) {
                        return true;
                    }
                } else {
                    $allFlags[$flagParameter] = true;
                }
            }
        }
    }
    if (is_null($flagKey)) {
        return $allFlags;
    }

    return false;
}

/**
 * Get specific options from the Resurs configuration set
 *
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function resursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    /*
     * MarGul change
     * If it's demoshop it will take the config from sessions instead of db
     */
    if (isResursDemo()) {
        // Override database setting with the theme (demoshops) flowtype SESSION setting if it's set.
        if ($key == "flowtype") {
            if ( ! empty($_SESSION['rb_checkout_flow'])) {
                $accepted = ['simplifiedshopflow', 'resurs_bank_hosted', 'resurs_bank_omnicheckout'];
                if (in_array(strtolower($_SESSION['rb_checkout_flow']), $accepted)) {
                    return $_SESSION['rb_checkout_flow'];
                }
            }
        }

        // Override database setting with the theme (demoshops) country SESSION setting if it's set.
        if ($key == "country") {
            if ( ! empty($_SESSION['rb_country'])) {
                $accepted = ['se', 'dk', 'no', 'fi'];
                if (in_array(strtolower($_SESSION['rb_country']), $accepted)) {
                    return strtoupper($_SESSION['rb_country']);
                }
            }
        }

        if ($key == 'login') {
            if ( ! empty($_SESSION['rb_country_data'])) {
                return $_SESSION['rb_country_data']['account']['login'];
            }
        }

        if ($key == 'password') {
            if ( ! empty($_SESSION['rb_country_data'])) {
                return $_SESSION['rb_country_data']['account']['password'];
            }
        }
    }

    $getOptionsNamespace = get_option($namespace);
    // Going back to support PHP 5.3 instead of 5.4+
    if (isset($getOptionsNamespace[$key])) {
        $response = $getOptionsNamespace[$key];
    } else {
        // No value set
        $response = null;
    }

    if (empty($response)) {
        $response = get_option($key);
    }
    if ($response === "true") {
        return true;
    }
    if ($response === "false") {
        return false;
    }
    if ($response === "yes") {
        return true;
    }
    if ($response === "no") {
        return false;
    }

    return $response;
}

/**
 * Returns true or false depending if the key exists in the resursOption-array
 *
 * @param string $key
 *
 * @return bool
 */
function issetResursOption($key = "", $namespace = 'woocommerce_resurs-bank_settings')
{
    $response = get_option($namespace);
    if (isset($response[$key])) {
        return true;
    } else {
        return false;
    }
}

/**
 * @param string $key
 * @param string $namespace
 *
 * @return bool
 */
function getResursOption($key = "", $namespace = "woocommerce_resurs-bank_settings")
{
    return resursOption($key, $namespace);
}

/**
 * Function used to figure out whether values are set or not
 *
 * @param string $key
 *
 * @return bool
 */
function hasResursOptionValue($key = "", $namespace = 'woocommerce_resurs-bank_settings')
{
    $optionValues = get_option($namespace);
    if (isset($optionValues[$key])) {
        return true;
    }

    return false;
}

/**
 * Set a new value in resursoptions
 *
 * @param string $key
 * @param string $value
 * @param string $configurationSpace
 *
 * @return bool
 */
function setResursOption($key = "", $value = "", $configurationSpace = "woocommerce_resurs-bank_settings")
{
    $allOptions = get_option($configurationSpace);
    if ( ! empty($key)) {
        $allOptions[$key] = $value;
        update_option($configurationSpace, $allOptions);

        return true;
    }

    return false;
}

if ( ! function_exists('r_wc_get_order_id_by_order_item_id')) {
    /**
     * Get the order id from where a specific item resides
     *
     * @param $item_id
     *
     * @return null|string
     * @since 2.0.2
     */
    function r_wc_get_order_id_by_order_item_id($item_id)
    {
        global $wpdb;
        $item_id  = absint($item_id);
        $order_id = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
            $item_id));

        return $order_id;
    }
}
if ( ! function_exists('r_wc_get_order_item_type_by_item_id')) {
    /**
     * Get the order item type (or name) by item id
     *
     * @param $item_id
     *
     * @return null|string
     * @since 2.0.2
     */
    function r_wc_get_order_item_type_by_item_id($item_id, $getItemName = false)
    {
        global $wpdb;
        $item_id = absint($item_id);
        if ( ! $getItemName) {
            $order_item_type = $wpdb->get_var($wpdb->prepare("SELECT order_item_type FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                $item_id));

            return $order_item_type;
        } else {
            $order_item_name = $wpdb->get_var($wpdb->prepare("SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                $item_id));

            return $order_item_name;
        }
    }
}

/**
 * Initialize EComPHP, the key of almost everything in this plugin
 *
 * @param string $overrideUser
 * @param string $overridePassword
 * @param int    $setEnvironment
 *
 * @return \Resursbank\RBEcomPHP\ResursBank
 * @throws Exception
 */
function initializeResursFlow(
    $overrideUser = "",
    $overridePassword = "",
    $setEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET
) {
    global $current_user;
    $username       = resursOption("login");
    $password       = resursOption("password");
    $useEnvironment = getServerEnv();
    if ($setEnvironment !== RESURS_ENVIRONMENTS::ENVIRONMENT_NOT_SET) {
        $useEnvironment = $setEnvironment;
    }
    if ( ! empty($overrideUser)) {
        $username = $overrideUser;
    }
    if ( ! empty($overridePassword)) {
        $password = $overridePassword;
    }

    /** @var $initFlow \Resursbank\RBEcomPHP\ResursBank */
    $initFlow = new \Resursbank\RBEcomPHP\ResursBank($username, $password);
    $initFlow->setSimplifiedPsp(true);

    if (isResursHosted()) {
        $initFlow->setPreferredPaymentFlowService(RESURS_FLOW_TYPES::FLOW_HOSTED_FLOW);
    }

    $sslHandler = getResursFlag("DISABLE_SSL_VALIDATION");
    if (isResursTest() && $sslHandler) {
        $initFlow->setDebug(true);
        $initFlow->setSslValidation(false);
    }
    $allFlags = getResursFlag(null);
    foreach ($allFlags as $flagKey => $flagValue) {
        if ( ! empty($flagKey)) {
            $initFlow->setFlag($flagKey, $flagValue);
        }
    }

    $initFlow->setUserAgent(RB_WOO_CLIENTNAME . "-" . RB_WOO_VERSION);
    $initFlow->setEnvironment($useEnvironment);
    $initFlow->setDefaultUnitMeasure();
    if (isset($_REQUEST['testurl'])) {
        $baseUrlTest = $_REQUEST['testurl'];
        // Set this up once
        if ($baseUrlTest == "unset" || empty($baseUrlTest)) {
            unset($_SESSION['customTestUrl'], $baseUrlTest);
        } else {
            $_SESSION['customTestUrl'] = $baseUrlTest;
        }
    }
    if (isset($_SESSION['customTestUrl'])) {
        $_SESSION['customTestUrl'] = $initFlow->setTestUrl($_SESSION['customTestUrl']);
    }
    try {
        if (function_exists('wp_get_current_user')) {
            wp_get_current_user();
        } else {
            get_currentuserinfo();
        }
        if (isset($current_user->user_login)) {
            $initFlow->setLoggedInUser($current_user->user_login);
        }
    } catch (Exception $e) {
    }
    $country = getResursOption("country");
    $initFlow->setCountryByCountryCode($country);
    if ($initFlow->getCountry() == "FI") {
        $initFlow->setDefaultUnitMeasure("kpl");
    }

    return $initFlow;
}

/**
 * @param string $ssn
 * @param string $customerType
 * @param string $ip
 *
 * @return array|mixed|null
 * @throws Exception
 */
function getAddressProd($ssn = '', $customerType = '', $ip = '')
{
    global $current_user;
    $username = resursOption("ga_login");
    $password = resursOption("ga_password");
    if ( ! empty($username) && ! empty($password)) {
        /** @var \Resursbank\RBEcomPHP\ResursBank $initFlow */
        $initFlow = new ResursBank($username, $password);
        $initFlow->setUserAgent(RB_WOO_CLIENTNAME . "-" . RB_WOO_VERSION);
        //$initFlow->setUserAgent( "ResursBankPaymentGatewayForWoocommerce" . RB_WOO_VERSION );
        //$initFlow->setUserAgent( "WooCommerce ResursBank Payment Gateway " . ( defined( 'RB_WOO_VERSION' ) ? RB_WOO_VERSION : "Unknown version" ) );
        $initFlow->setEnvironment(RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION);
        try {
            $getResponse = $initFlow->getAddress($ssn, $customerType, $ip);

            return $getResponse;
        } catch (Exception $e) {
            echo json_encode(array("Unavailable credentials - " . $e->getMessage()));
        }
    } else {
        echo json_encode(array("Unavailable credentials"));
    }
    die();
}

/**
 * Get current Resurs Environment setup (demo/test or production)
 *
 * @return int
 */
function getServerEnv()
{
    $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;

    $serverEnv    = getResursOption('serverEnv');
    $demoshopMode = getResursOption('demoshopMode');

    if ($serverEnv == 'live') {
        $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_PRODUCTION;
    }
    /*
     * Prohibit production mode if this is a demoshop
     */
    if ($serverEnv == 'test' || $demoshopMode == "true") {
        $useEnvironment = RESURS_ENVIRONMENTS::ENVIRONMENT_TEST;
    }

    return $useEnvironment;
}

/**
 * Returns true if this is a test environment
 *
 * @return bool
 */
function isResursTest()
{
    $currentEnv = getServerEnv();
    if ($currentEnv === RESURS_ENVIRONMENTS::ENVIRONMENT_TEST) {
        return true;
    }

    return false;
}

/**
 * Payment gateway destroyer.
 *
 * Only enabled in very specific environments.
 *
 * @return bool
 */
function isResursSimulation()
{
    if ( ! isResursTest()) {
        return repairResursSimulation();
    }
    $devResursSimulation = getResursOption("devResursSimulation");
    if ($devResursSimulation) {
        if (isset($_SERVER['HTTP_HOST'])) {
            $mustContain            = array('.loc$', '.local$', '^localhost$', '.localhost$');
            $hasRequiredEnvironment = false;
            foreach ($mustContain as $hostContainer) {
                if (preg_match("/$hostContainer/", $_SERVER['HTTP_HOST'])) {
                    return true;
                }
            }
            /*
             * If you really want to force this, use one of the following variables from a define or, if in .htaccess:
             * SetEnv FORCE_RESURS_SIMULATION "true"
             * As this is invoked, only if really set to test mode, this should not be able to destroy anything in production.
             */
            if ((defined('FORCE_RESURS_SIMULATION') && FORCE_RESURS_SIMULATION === true) || (isset($_SERVER['FORCE_RESURS_SIMULATION']) && $_SERVER['FORCE_RESURS_SIMULATION'] == "true")) {
                return true;
            }
        }
    }

    return repairResursSimulation();
}

/**
 * @param bool $returnRepairState
 *
 * @return bool
 */
function repairResursSimulation($returnRepairState = false)
{
    setResursOption("devSimulateErrors", $returnRepairState);

    return $returnRepairState;
}

/********************** OMNICHECKOUT RELATED STARTS HERE ******************/

/**
 * Check if the current payment method is currently enabled and selected
 *
 * @param bool $ignoreActiveFlag
 *
 * @return bool
 */
function isResursOmni($ignoreActiveFlag = false)
{
    global $woocommerce;
    $returnValue       = false;
    $externalOmniValue = null;
    $currentMethod     = "";
    if (isset($woocommerce->session)) {
        $currentMethod = $woocommerce->session->get('chosen_payment_method');
    }
    $flowType = resursOption("flowtype");
    $hasOmni  = hasResursOmni($ignoreActiveFlag);
    if (($hasOmni == 1 || $hasOmni === true) && ( ! empty($currentMethod) && $flowType === $currentMethod)) {
        $returnValue = true;
    }
    /*
	 * If Omni is enabled and the current chosen method is empty, pre-select omni
	 */
    if (($hasOmni == 1 || $hasOmni === true) && $flowType === "resurs_bank_omnicheckout" && empty($currentMethod)) {
        $returnValue = true;
    }
    if ($returnValue) {
        // If the checkout is normally set to be enabled, this gives external plugins a chance to have it disabled
        $externalOmniValue = apply_filters("resursbank_temporary_disable_checkout", null);
        if ( ! is_null($externalOmniValue)) {
            $returnValue = ($externalOmniValue ? false : true);
        }
    }

    return $returnValue;
}

/**
 * Check if the hosted flow is enabled and chosen
 *
 * @return bool
 */
function isResursHosted()
{
    $hasHosted = hasResursHosted();
    if ($hasHosted == 1 || $hasHosted === true) {
        return true;
    }

    return false;
}

/**
 * @return bool
 */
function hasEcomPHP()
{
    if (class_exists('ResursBank') || class_exists('Resursbank\RBEcomPHP\ResursBank')) {
        return true;
    }

    return false;
}

/**
 * Check if the omniFlow is enabled at all (through flowType)
 *
 * @param bool $ignoreActiveFlag Check this setting even though the plugin is not active
 *
 * @return bool
 */
function hasResursOmni($ignoreActiveFlag = false)
{
    $resursEnabled = resursOption("enabled");
    $flowType      = resursOption("flowtype");
    if (is_admin()) {
        $omniOption = get_option('woocommerce_resurs_bank_omnicheckout_settings');
        if ($flowType == "resurs_bank_omnicheckout") {
            $omniOption['enabled'] = 'yes';
        } else {
            $omniOption['enabled'] = 'no';
        }
        update_option('woocommerce_resurs_bank_omnicheckout_settings', $omniOption);
    }
    if ($resursEnabled != "yes" && ! $ignoreActiveFlag) {
        return false;
    }
    if ($flowType == "resurs_bank_omnicheckout") {
        return true;
    }

    return false;
}

/**
 * @return bool
 */
function hasResursHosted()
{
    $resursEnabled = resursOption("enabled");
    $flowType      = resursOption("flowtype");
    if ($resursEnabled != "yes") {
        return false;
    }
    if ($flowType == "resurs_bank_hosted") {
        return true;
    }

    return false;
}

/**
 * @param $classButtonHtml
 */
function resurs_omnicheckout_order_button_html($classButtonHtml)
{
    global $woocommerce;
    if ( ! isResursOmni()) {
        echo $classButtonHtml;
    }
}

/**
 * Payment methods validator for OmniCheckout
 *
 * @param $paymentGatewaysCheck
 *
 * @return null
 */
function resurs_omnicheckout_payment_gateways_check($paymentGatewaysCheck)
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
    if (is_array($paymentGatewaysCheck)) {
        $paymentGatewaysCheck = array();
    }
    if ( ! count($paymentGatewaysCheck)) {
        // If there is no active payment gateways except for omniCheckout, the warning of no available payment gateways has to be suppressed
        if (isResursOmni()) {
            return null;
        }

        return __('There are currently no payment methods available', 'WC_Payment_Gateway');
    }

    return $paymentGatewaysCheck;
}

/**
 * Check if there are gateways active (Omni related)
 *
 * @return bool
 */
function hasPaymentGateways()
{
    global $woocommerce;
    $paymentGatewaysCheck = $woocommerce->payment_gateways->get_available_payment_gateways();
    if (is_array($paymentGatewaysCheck)) {
        $paymentGatewaysCheck = array();
    }
    if (count($paymentGatewaysCheck) > 1) {
        return true;
    }

    return false;
}

/********************** OMNICHECKOUT RELATED ENDS HERE ******************/

function resurs_gateway_activation()
{
    set_transient('ResursWooGatewayVersion', rbWcGwVersionToDecimals());
}

if (is_admin()) {
    register_activation_hook(__FILE__, 'resurs_gateway_activation');
}

/**
 * Returns true if demoshop-mode is enabled.
 *
 * @return bool
 */
function isResursDemo()
{
    $resursSettings = get_option('woocommerce_resurs-bank_settings');
    $demoshopMode   = isset($resursSettings['demoshopMode']) ? $resursSettings['demoshopMode'] : false;
    if ($demoshopMode === "true") {
        return true;
    }
    if ($demoshopMode === "yes") {
        return true;
    }
    if ($demoshopMode === "false") {
        return false;
    }
    if ($demoshopMode === "no") {
        return false;
    }

    return false;
}

/**
 * @param string $versionRequest
 * @param string $operator
 *
 * @return bool
 * @throws \Exception
 */
function hasWooCommerce($versionRequest = "2.0.0", $operator = ">=")
{
    if (version_compare(WOOCOMMERCE_VERSION, $versionRequest, $operator)) {
        return true;
    }
}

/**
 * @param string $checkVersion
 *
 * @return bool
 * @throws Exception
 */
function isWooCommerce3($checkVersion = '3.0.0')
{
    return hasWooCommerce($checkVersion);
}


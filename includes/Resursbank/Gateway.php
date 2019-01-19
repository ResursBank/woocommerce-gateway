<?php

// Gateway related files, should be written to not conflict with neighbourhood.

/** @noinspection PhpCSValidationInspection */

if (!defined('ABSPATH')) {
    exit;
}

use Resursbank\RBEcomPHP\RESURS_FLOW_TYPES;

/**
 *
 */
function resursbank_payment_gateway_initialize()
{
    // Make sure this gateway is not already there
    if (class_exists('WC_Resursbank_Gateway')) {
        return;
    }

    // Make sure WooCommerce is there.
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Class Resursbank_Gateway
     */
    class WC_Gateway_ResursBank extends WC_Payment_Gateway
    {
        /** @var Resursbank_Core */
        protected $CORE;
        protected $METHOD;
        protected $CHECKOUT;

        /** @var \Resursbank\RBEcomPHP\ResursBank */
        protected $RESURSBANK;

        /** @var array $REQUEST _REQUEST and REQUEST_URI merged */
        protected $REQUEST;

        /** @var RESURS_FLOW_TYPES */
        protected $FLOW;

        protected $COUNTRY;

        /**
         * Resursbank_Gateway constructor.
         */
        public function __construct()
        {
            $this->setup();
        }

        private function setup()
        {
            if (is_null($this->CORE)) {
                // Not inherited from Method class.
                $this->CORE = new Resursbank_Core();
            }
            $this->id = 'resurs_bank_payment_gateway';
            $this->title = 'Resurs Bank Payment Gateway';
            $this->method_description = __(
                'Complete payment solution for Resurs Bank, with support for multiple countries.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );

            $this->REQUEST = Resursbank_Core::getQueryRequest();

            add_action('woocommerce_api_' . strtolower(__CLASS__), array($this, 'resursbankPaymentHandler'));
            add_filter('woocommerce_get_terms_page_id', array($this, 'getTermsOnRco'), 1);
            add_filter('woocommerce_order_button_html', array($this, 'getOrderButtonByRco'));
            add_filter('woocommerce_checkout_fields', array($this, 'resursBankCheckoutFields'));
        }

        /**
         * @param $fieldArray
         * @return mixed
         */
        private function addClassToFields($fieldArray)
        {
            foreach ($fieldArray as $fieldKey => $fieldData) {
                if (!isset($fieldArray[$fieldKey]['class'])) {
                    // Define before use.
                    $fieldArray[$fieldKey]['class'] = array();
                }
                $fieldArray[$fieldKey]['class'][] = 'resursCheckoutOrderFormField';
            }
            return $fieldArray;
        }

        /**
         * @param $fields
         * @return mixed
         */
        public function resursBankCheckoutFields($fields)
        {
            $currentFlow = $this->CORE->getFlowByEcom($this->CORE->getFlowByCountry(Resursbank_Core::getCustomerCountry()));

            if ($currentFlow === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                if (isset($fields['billing'])) {
                    $fields['billing'] = $this->addClassToFields($fields['billing']);
                }
                if (isset($fields['shipping'])) {
                    $fields['shipping'] = $this->addClassToFields($fields['shipping']);
                }
            }

            return $fields;
        }

        /**
         * Is this method enabled?
         *
         * @return bool
         * @TODO Configurable option on flows not matching with RCO
         */
        protected function getEnabled()
        {
            return true;
        }

        private function getPaymentFormFieldHtml($filterName, $PAYMENT_METHOD)
        {
            $hasFilteredCustomHtml = apply_filters(
                'resursbank_get_customer_field_html_' . $filterName,
                '',
                $PAYMENT_METHOD
            );
            if (empty($hasFilteredCustomHtml)) {
                $hasFilteredCustomHtml = apply_filters(
                    'resursbank_get_customer_field_html_generic',
                    '',
                    $PAYMENT_METHOD,
                    $filterName
                );
            }

            return $hasFilteredCustomHtml;
        }

        protected function getPaymentFormFields($PAYMENT_METHOD)
        {
            $resursPaymentFormField = '';

            $formFieldHtml = array();

            // Former version: onkeyup was used (for inherit fields?)

            if ($this->FLOW !== RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                $isLegal = false;
                if (in_array('LEGAL', (array)$PAYMENT_METHOD->customerType)) {
                    if (Resursbank_Core::getIsLegal()) {
                        $isLegal = true;
                    }
                }

                // We consider payment providers not requiring government id's.
                if ($PAYMENT_METHOD->type !== 'PAYMENT_PROVIDER') {
                    $formFieldHtml['applicant_natural_government_id'] = $this->getPaymentFormFieldHtml(
                        'government_id',
                        $PAYMENT_METHOD
                    );
                }

                // Not in hosted flow.
                if ($isLegal && $this->FLOW === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                    $formFieldHtml['contact_government_id'] = $this->getPaymentFormFieldHtml(
                        'contact_government_id',
                        $PAYMENT_METHOD
                    );
                }

                // On "NATURAL"-cases, please use the billing fields that WooCommerce provides, so we don't need
                // to duplicate them in our own. This field should not be present on hosted flow.
                if ($this->METHOD->type === 'CARD' && $this->FLOW === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                    // Card requirements - government id + card number.
                    $formFieldHtml['applicant_natural_card'] = $this->getPaymentFormFieldHtml('card', $PAYMENT_METHOD);
                }
            }

            // If method is befintligt kort, do not run this.
            if ($this->METHOD->specificType !== 'CARD') {
                $formFieldHtml['readmore'] = apply_filters(
                    'resursbank_get_customer_field_html_read_more',
                    '',
                    $PAYMENT_METHOD
                );
            }

            $formFieldHtml = apply_filters('resursbank_custom_payment_field', $formFieldHtml, $PAYMENT_METHOD);
            $resursPaymentFormField = implode("\n", $formFieldHtml);

            return $resursPaymentFormField;
        }

        /**
         * @param WC_Cart $cart
         */
        protected function setResursCartShipping($cart)
        {
            $shipping = (float)$cart->get_shipping_total();
            $shipping_tax = (float)$cart->get_shipping_tax();
            $roundedVat = 0;
            // Make sure that division by zero do not occur.
            if ($shipping_tax > 0) {
                $roundedVat = @round($shipping_tax / $shipping, 2) * 100;
            }
            $vat = (!is_nan($roundedVat) ? $roundedVat : 0);

            $this->RESURSBANK->addOrderLine(
                'art_' . __('shipping', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'),
                ucfirst(__('shipping', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce')),
                $shipping,
                $vat,
                '',
                'SHIPPING_FEE',
                1
            );
        }

        /**
         * @param WC_Cart $cart
         */
        protected function setResursCartFees($cart)
        {
            /** @var WC_Cart_Fees $fees */
            $fees = $cart->get_fees();

            if (is_array($fees)) {
                foreach ($fees as $fee) {
                    if (!empty($fee->id) && ($fee->amount > 0 || $fee->amount < 0)) {
                        // Check if $fee->tax exists before using it, or undefined propery will occur.
                        // This may indicate that something went terribly wrong.
                        if (isset($fee->tax) && $fee->tax > 0) {
                            $vat = ($fee->tax / $fee->amount) * 100;
                        } else {
                            $vat = 0;
                        }

                        $this->RESURSBANK->addOrderLine(
                            $fee->id,
                            $fee->name,
                            $fee->amount,
                            $vat,
                            '',
                            'ORDER_LINE',
                            1
                        );
                    }
                }
            }
        }

        /**
         * @param WC_Coupon $couponItem
         * @return string
         */
        private function getCouponDescription($couponItem)
        {
            // Fetch the coupon item to get information about the coupon.
            $couponEntry = get_post($couponItem->get_id());
            $couponDescription = $couponEntry->post_excerpt;
            if (empty($couponDescription)) {
                $couponDescription = sprintf(
                    '%s_%s',
                    $couponItem->get_code(),
                    __('coupon', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce')
                );
            }

            return $couponDescription;
        }

        /**
         * @param WC_Cart $cart
         */
        protected function setResursCartDiscount($cart)
        {
            if ($cart->coupons_enabled()) {

                /** @var WC_Coupon $coupons */
                $coupons = $cart->get_coupons();

                if (is_array($coupons) && count($coupons)) {
                    /**
                     * @var string $couponCode
                     * @var WC_Coupon $couponItem
                     */
                    foreach ($coupons as $couponCode => $couponItem) {
                        $couponDescription = $this->getCouponDescription($couponItem);
                        $unitAmountWithoutVat = (
                                0 - (float)$cart->get_coupon_discount_amount($couponCode)
                            ) + (
                                0 - (float)$cart->get_coupon_discount_tax_amount($couponCode)
                            );

                        $this->RESURSBANK->addOrderLine(
                            $couponItem->get_id(),
                            $couponDescription,
                            $unitAmountWithoutVat,
                            0,
                            '',
                            'DISCOUNT',
                            1
                        );
                    }
                }
            }
        }

        /**
         * Get a proper article number for a product, to use with Resurs Bank.
         *
         * setFlag(SKU) activates usages of SKU instead of WooCommerce default id.
         *
         * @param WC_Product $cartItem
         * @return int|string
         */
        protected function getArticleNumber($cartItem)
        {
            $skuString = $cartItem->get_sku();
            $artIdString = $cartItem->get_id();

            $return = $artIdString;

            if (!empty($skuString) && (bool)Resursbank_Core::getFlag('SKU')) {
                $return = $skuString;
            }

            $hasOwnString = apply_filters('resursbank_cart_article_number', $return, $cartItem);
            if (!empty($hasOwnString)) {
                $return = $hasOwnString;
            }

            return $return;
        }

        /**
         * Set the article description for Resurs payload.
         *
         * @param WC_Product $cartItem
         * @return mixed
         */
        protected function getArticleDescription($cartItem)
        {
            $return = $cartItem->get_title();

            $hasOwnString = apply_filters('resursbank_cart_article_description', $return, $cartItem);
            if (!empty($hasOwnString)) {
                $return = $hasOwnString;
            }

            return $return;
        }

        /**
         * @param $taxClass
         * @return float|int
         */
        protected function getProductTax($taxClass)
        {
            $taxRate = 0;
            $ratesOfTaxClass = WC_Tax::get_rates($taxClass);
            $taxRates = @array_shift($ratesOfTaxClass);

            if (isset($taxRates['rate'])) {
                $taxRate = (double)$taxRates['rate'];
            }

            return $taxRate;
        }

        /**
         * @param WC_Cart $theCart
         */
        protected function setResursCartItems($theCart)
        {
            if (is_array($theCart) && count($theCart)) {
                /** @var WC_Product $cartItem */
                foreach ($theCart as $cartItem) {
                    /** @var WC_Product $cartItemData */
                    $cartItemData = $cartItem['data'];
                    $vat = $this->getProductTax($cartItemData->get_tax_class());
                    $articleNumberOrId = $this->getArticleNumber($cartItemData);

                    $this->RESURSBANK->addOrderLine(
                        $articleNumberOrId,
                        $this->getArticleDescription($cartItemData),
                        wc_get_price_excluding_tax($cartItemData),
                        $vat,
                        '',
                        'ORDER_LINE',
                        $cartItem['quantity']
                    );
                }
            }
        }

        /**
         * @param $type
         * @param $key
         * @param null $postArray
         * @return string|null
         */
        protected function getPostDataCustomer($type, $key, $postArray = null)
        {
            $return = null;
            if (is_array($postArray)) {
                $customerPaymentFields = $postArray;
            } else {
                $customerPaymentFields = Resursbank_Core::getDefaultPostDataParsed(true);
            }

            if (isset($customerPaymentFields[$type]) && isset($customerPaymentFields[$type][$key])) {
                $return = (string)$customerPaymentFields[$type][$key];
            }

            return $return;
        }

        /**
         * Compile customer full name from postdata.
         *
         * @param $paymentFields
         * @return string
         */
        private function getCustomerFullName($paymentFields)
        {
            return sprintf(
                '%s %s',
                $this->getPostDataCustomer(
                    'billing',
                    'first_name',
                    $paymentFields
                ),
                $this->getPostDataCustomer(
                    'billing',
                    'last_name',
                    $paymentFields
                )
            );
        }

        /**
         * @param string $type
         */
        protected function setResursCustomerData($type = 'billing')
        {
            $customerPaymentFields = Resursbank_Core::getDefaultPostDataParsed(true);
            if ($type === 'billing') {
                $this->RESURSBANK->setBillingAddress(
                    $this->getCustomerFullName($customerPaymentFields),
                    $this->getPostDataCustomer($type, 'first_name', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'last_name', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'address_1', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'address_2', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'city', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'postcode', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'country', $customerPaymentFields)
                );
            } elseif ($type === 'shipping') {
                $this->RESURSBANK->setDeliveryAddress(
                    $this->getCustomerFullName($customerPaymentFields),
                    $this->getPostDataCustomer($type, 'first_name', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'last_name', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'address_1', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'address_2', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'city', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'postcode', $customerPaymentFields),
                    $this->getPostDataCustomer($type, 'country', $customerPaymentFields)
                );
            }
        }

        /**
         * Set up main customer data for EComPHP.
         * @param $order
         */
        protected function setResursCustomerBasicData($order)
        {
            $paymentMethod = md5(str_replace('resursbank_', '', $this->getPostDataCustomer('payment', 'method')));
            $customerType = !Resursbank_Core::getIsLegal() ? 'NATURAL' : 'LEGAL';

            $customerId = Resursbank_Core::getCustomerId($order);
            if (!is_null($customerId)) {
                $this->RESURSBANK->setMetaData('CustomerId', $customerId);
            }

            // We no longer use "special forms" to catch basic data.
            $this->RESURSBANK->setCustomer(
                $this->getPostDataCustomer('resursbankcustom', 'government_id_' . $paymentMethod),
                $this->getPostDataCustomer('billing', 'phone'),
                $this->getPostDataCustomer('billing', 'phone'),
                $this->getPostDataCustomer('billing', 'email'),
                $customerType,
                $this->getPostDataCustomer('resursbankcustom', 'contact_government_id' . $paymentMethod)
            );
        }

        /**
         * EComPHP OrderLine Modernizer
         *
         * @param WC_Cart $cart
         */
        protected function setResursCart($cart)
        {
            /** @var WC_Cart $theCart */
            $theCart = $cart->get_cart();

            $this->setResursCartShipping($cart);
            $this->setResursCartFees($cart);
            $this->setResursCartDiscount($cart);
            $this->setResursCartItems($theCart);
        }

        /**
         * @param $order_id
         * @return string
         */
        protected function getResursOrderId($order_id)
        {
            if (Resursbank_Core::getResursOption('postidreference')) {
                $preferredId = $order_id;
            } else {
                $preferredId = $this->RESURSBANK->getPreferredPaymentId();
            }
            update_post_meta($order_id, 'paymentId', $preferredId);
            update_post_meta($order_id, 'flow', $this->FLOW);

            return $preferredId;
        }

        /**
         * @param $woocommerceOrderId
         * @param $resursOrderId
         * @param $order
         * @return array
         */
        public function createResursOrder($woocommerceOrderId, $resursOrderId, $order, $PAYMENT_METHOD)
        {
            try {
                if ($this->FLOW !== RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                    $return = $this->RESURSBANK->createPayment($PAYMENT_METHOD->id);
                } else {
                    $return = $this->RESURSBANK->createPayment($resursOrderId);
                }
                //update_post_meta($woocommerceOrderId, 'resursPaymentCountry', $this->COUNTRY);
            } catch (\Exception $e) {
                return $this->setPaymentError($order, $e->getMessage(), $e->getCode());
            }

            return (array)$return;
        }

        /**
         * @param $woocommerceOrderId
         * @return bool
         */
        protected function setReducedStock($woocommerceOrderId)
        {
            $return = false;

            $hasReduceStock = get_post_meta($woocommerceOrderId, 'hasReduceStock');
            if ((bool)Resursbank_Core::getResursOption('reduceOrderStock') && empty($hasReduceStock)) {
                update_post_meta($woocommerceOrderId, 'hasReduceStock', time());
                wc_reduce_stock_levels($woocommerceOrderId);
                $return = true;
            }

            return $return;
        }

        /**
         * @param $order
         * @param $bookPaymentResult
         */
        private function testBookPaymentStatus($order, $bookPaymentResult)
        {
            if (is_array($bookPaymentResult) && !isset($bookPaymentResult['bookPaymentStatus'])) {
                $this->setPaymentError(
                    $order,
                    __(
                        'BookPaymentStatus could not be determined',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ), 400
                );
            }
        }

        /**
         * @param $woocommerceOrderId
         * @param $resursOrderId
         * @param WC_Order $order
         * @return array
         */
        public function setOrderDetails($woocommerceOrderId, $resursOrderId, $order, $bookPaymentResult)
        {
            $this->testBookPaymentStatus($order, $bookPaymentResult);

            switch ($bookPaymentResult['bookPaymentStatus']) {
                case 'BOOKED':
                    $order->update_status(
                        'processing',
                        __(
                            'Order was instantly booked.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    // Run stock level checking if enabled.
                    $this->setReducedStock($woocommerceOrderId);
                    $return = $this->setResultArray($order, 'success');
                    break;
                case 'SIGNING':
                    $order->add_order_note(
                        __(
                            'Customer went to signing process.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    $return = $this->setResultArray($order, 'success', $bookPaymentResult['signingUrl']);
                    break;
                default:
                    $return = $this->setResultArray($order, 'failure');
                    break;
            }

            return $return;
        }

        /**
         * @param $order
         * @param string $result
         * @param string $redirect
         * @return array
         */
        protected function setResultArray($order, $result = 'success', $redirect = '')
        {
            if ($result === 'failure' && empty($redirect)) {
                $redirect = html_entity_decode($order->get_cancel_order_url());
            }
            if ($result === 'success' && empty($redirect)) {
                $redirect = $this->get_return_url($order);
            }

            return array(
                'result' => $result,
                'redirect' => $redirect
            );
        }

        /**
         * @param $exceptionMessage
         * @param $exceptionCode
         * @return array
         */
        public function setPaymentError($order, $exceptionMessage, $exceptionCode)
        {
            wc_add_notice(sprintf('%s (%s)', $exceptionMessage, $exceptionCode), 'error');

            return $this->setResultArray($order, 'failure');
        }


        /**
         * @return string
         */
        protected function getApiUrl()
        {
            $urlString = home_url('/');
            $urlString = add_query_arg('wc-api', strtolower(__CLASS__), $urlString);

            return (string)$urlString;
        }

        /**
         * @param $woocommerceOrderId
         * @param $resursOrderId
         * @param WC_Order $order
         */
        protected function setCustomerSigningData($woocommerceOrderId, $resursOrderId, $order)
        {
            $this->RESURSBANK->setSigning(
                $this->getSuccessUrl(
                    $woocommerceOrderId,
                    $resursOrderId
                ),
                html_entity_decode(
                    $order->get_cancel_order_url()
                ),
                (bool)Resursbank_Core::getResursOption('forcePaymentSigning'),
                $this->getBackUrl($order)
            );
        }

        /**
         * Setup of sync/async payment behaviour.
         */
        protected function setCustomerPaymentAsync()
        {
            $this->RESURSBANK->setWaitForFraudControl(Resursbank_Core::getResursOption('waitForFraudControl'));
            $this->RESURSBANK->setAnnulIfFrozen(Resursbank_Core::getResursOption('annulIfFrozen'));
            $this->RESURSBANK->setFinalizeIfBooked(Resursbank_Core::getResursOption('finalizeIfBooked'));
        }

        /**
         * Get URL where eventually signing takes place after a successful order.
         *
         * @param $woocommerceOrderId
         * @param $resursOrderId
         * @return string
         */
        protected function getSuccessUrl($woocommerceOrderId, $resursOrderId)
        {
            $successUrlString = $this->getApiUrl();
            if ($this->FLOW === RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {
                $successUrlString = add_query_arg('request', 'signing', $successUrlString);
            } elseif ($this->FLOW === RESURS_FLOW_TYPES::HOSTED_FLOW) {
                $successUrlString = add_query_arg('request', 'hosted', $successUrlString);
            }
            // Ensure that the transactions appear under the correct trafic source.
            $successUrlString = add_query_arg('utm_nooverride', '1', $successUrlString);
            $successUrlString = add_query_arg('w_id', $woocommerceOrderId, $successUrlString);
            $successUrlString = add_query_arg('r_id', $resursOrderId, $successUrlString);

            return (string)$successUrlString;
        }

        /**
         * @param $order
         * @return string
         */
        protected function getBackUrl($order)
        {
            $backurl = html_entity_decode($order->get_cancel_order_url());
            $backurl .= ($this->FLOW === RESURS_FLOW_TYPES::HOSTED_FLOW ? "&isBack=1" : "");
            return $backurl;
        }

        /**
         * @return bool
         */
        public function is_available()
        {
            return $this->getEnabled();
        }

        /**
         * @param $key
         * @return mixed|null
         */
        protected function getRequestKey($key)
        {
            $return = null;

            if (isset($this->REQUEST[$key])) {
                $return = $this->REQUEST[$key];
            }

            return $return;
        }

        /**
         * @param $woocommerceOrderId
         * @param $orderPaymentId
         * @return bool
         */
        private function setSignedPayment($woocommerceOrderId, $orderPaymentId, $order)
        {
            $return = false;
            try {
                $signingResponse = $this->RESURSBANK->bookSignedPayment($orderPaymentId);
                if ($signingResponse->bookPaymentStatus === 'BOOKED') {
                    update_post_meta($woocommerceOrderId, 'resursOrderSigned', true);
                    $order->update_status(
                        'processing', __(
                            'Customer signed payment - order is booked.',
                            'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    $return = true;
                }
            } catch (\Exception $e) {
            }

            return $return;
        }

        /**
         * @param $page_id
         * @return int
         */
        public function getTermsOnRco($page_id)
        {
            if ($this->FLOW === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                return 0;
            }
            return $page_id;
        }

        /**
         * Hides button i RCO mode.
         *
         * @param $classButtonHtml
         * @return string|string[]|null
         */
        public function getOrderButtonByRco($classButtonHtml)
        {
            if ($this->FLOW === RESURS_FLOW_TYPES::RESURS_CHECKOUT) {
                return preg_replace(
                    '/class=\"/',
                    'class="resursCheckoutWoocommerceOrderButtonHtml ',
                    $classButtonHtml
                );
            }

            return $classButtonHtml;
        }

        /**
         * Extract shopUrl by the home domain of shop. In prior versions, we used to let
         * EComPHP extract the domain from HTTP_HOST instead.
         *
         * @return string
         */
        public function getProperShopUrl()
        {
            $MODULE_NETWORK = new \TorneLIB\MODULE_NETWORK();
            $validateHostByDns = (bool)Resursbank_Core::getResursOption('validateShopUrlHost');
            $decodedShopUrl = $MODULE_NETWORK->getUrlDomain(
                home_url('/'),
                $validateHostByDns
            );

            return (string)apply_filters('resursbank_set_shopurl', $decodedShopUrl[1] . "://" . $decodedShopUrl[0]);
        }

        /**
         * WooCommerce Payment API calls.
         *
         * <url>?wc-api=wc_gateway_resursbank
         */
        public function resursbankPaymentHandler()
        {
            $requestType = $this->getRequestKey('request');

            $woocommerceOrderId = $this->getRequestKey('w_id');
            $order = new WC_Order($woocommerceOrderId);
            $resursOrderId = $this->CORE->getPostMeta($woocommerceOrderId, 'paymentId');
            $this->RESURSBANK = $this->CORE->getConnectionByCountry($order->get_billing_country());

            switch ($requestType) {
                case 'signing':
                    if ($this->setSignedPayment($woocommerceOrderId, $resursOrderId, $order)) {
                        $redirectUrl = $this->get_return_url($order);
                    } else {
                        $redirectUrl = $order->get_cancel_order_url();
                    }
                    break;
                case 'hosted':
                    // Prepare for a failure.
                    $redirectUrl = $order->get_cancel_order_url();
                    try {
                        $resursPaymentInformation = $this->RESURSBANK->getPayment($resursOrderId);
                    } catch (Exception $e) {
                        // This is where the cancel order url has been if something goes wrong in the hosted process.
                    }
                    $bookedTimeTest = @strtotime($resursPaymentInformation->booked);

                    // Consider successful if there is a booking time in order.
                    if ($bookedTimeTest) {
                        $order->update_status(
                            'processing',
                            __(
                                'The payment are signed and booked via hosted flow.',
                                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                        $redirectUrl = $this->get_return_url($order);
                    }
                    break;
                default:
                    $redirectUrl = $order->get_cancel_order_url();
                    break;
            }

            wp_safe_redirect($redirectUrl);
            die;
        }
    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}

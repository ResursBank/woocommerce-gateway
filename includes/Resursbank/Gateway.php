<?php

// Gateway related files, should be written to not conflict with neighbourhood.

if (!defined('ABSPATH')) {
    exit;
}

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
        protected $CORE;
        protected $RB;
        protected $METHOD;
        protected $CHECKOUT;

        /** @var \Resursbank\RBEcomPHP\ResursBank */
        protected $RESURSBANK;

        /** @var array $REQUEST _REQUEST and REQUEST_URI merged */
        protected $REQUEST;

        /**
         * Resursbank_Gateway constructor.
         */
        function __construct()
        {
            $this->setup();
        }

        private function setup()
        {
            $this->id = 'resurs_bank_payment_gateway';
            $this->title = 'Resurs Bank Payment Gateway';
            $this->method_description = __(
                'Complete payment solution for Resurs Bank, with support for multiple countries.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );

            $this->REQUEST = Resursbank_Core::getQueryRequest();

            add_action('woocommerce_api_' . strtolower(__CLASS__), array($this, 'resursbankPaymentHandler'));
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
                $PAYMENT_METHOD);
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

            // Former version: onkeyup was used (for inherit fields?)

            if ($this->FLOW === \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::SIMPLIFIED_FLOW) {

                $isLegal = false;
                if (in_array('LEGAL', (array)$PAYMENT_METHOD->customerType)) {
                    if (Resursbank_Core::getIsLegal()) {
                        $isLegal = true;
                    }
                }

                $formFieldHtml['applicant_natural_government_id'] = $this->getPaymentFormFieldHtml(
                    'government_id',
                    $PAYMENT_METHOD
                );

                if ($isLegal) {
                    $formFieldHtml['contact_government_id'] = $this->getPaymentFormFieldHtml(
                        'contact_government_id',
                        $PAYMENT_METHOD
                    );
                }

                // Card requirements - government id + card number
                if ($this->METHOD->type === 'CARD') {
                    $formFieldHtml['applicant_natural_card'] = $this->getPaymentFormFieldHtml('card', $PAYMENT_METHOD);
                } else {

                    // Natural cases, globally - gov, phone, mobile, email
                    $formFieldHtml['applicant_natural_phone'] = $this->getPaymentFormFieldHtml(
                        'applicant_phone',
                        $PAYMENT_METHOD
                    );
                    $formFieldHtml['applicant_natural_mobile'] = $this->getPaymentFormFieldHtml(
                        'applicant_mobile',
                        $PAYMENT_METHOD
                    );
                    $formFieldHtml['applicant_natural_email'] = $this->getPaymentFormFieldHtml(
                        'applicant_email',
                        $PAYMENT_METHOD
                    );

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

            $resursPaymentFormField = implode("\n", $formFieldHtml);

            return $resursPaymentFormField;
        }

        /**
         * @param WC_Cart $cart
         * @return bool
         */
        protected function setResursCartShipping($cart)
        {
            $shipping = (float)$cart->get_shipping_total();
            $shipping_tax = (float)$cart->get_shipping_tax();
            $roundedVat = @round($shipping_tax / $shipping, 2) * 100;
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
            $cart->add_fee('test', 100, true);

            /** @var WC_Cart_Fees $fees */
            $fees = $cart->get_fees();

            if (is_array($fees)) {
                foreach ($fees as $fee) {
                    if (!empty($fee->id) && ($fee->amount > 0 || $fee->amount < 0)) {

                        if ($fee->tax > 0) {
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
         * @param $couponItem
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
         * @param $cartItem
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
            $taxRates = @array_shift(WC_Tax::get_rates($taxClass));

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

            if (isset($customerPaymentFields[$type]) && $customerPaymentFields[$type][$key]) {
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
            return $this->getPostDataCustomer(
                    'billing',
                    'first_name',
                    $paymentFields
                ) . ' ' . $this->getPostDataCustomer(
                    'billing',
                    'last_name',
                    $paymentFields
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
         * @return string|void
         */
        protected function getApiUrl()
        {
            $urlString = home_url('/');
            $urlString = add_query_arg('wc-api', strtolower(__CLASS__), $urlString);

            return $urlString;
        }

        /**
         * @param $woocommerceOrderId
         * @param $resursOrderId
         * @param WC_Order $order
         */
        protected function setCustomerSigningData($woocommerceOrderId, $resursOrderId, $order)
        {
            $this->RESURSBANK->setSigning(
                $this->getSuccessUrl($woocommerceOrderId, $resursOrderId),
                html_entity_decode($order->get_cancel_order_url()),
                (bool)Resursbank_Core::getResursOption('forcePaymentSigning'),
                $this->getBackUrl($order)
            );
        }

        /**
         * Setup of sync/async payment behaviour.
         */
        protected function setCustomerPaymentAsync() {
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
            $successUrlString = add_query_arg('request', 'signing', $successUrlString);
            // Ensure that the transactions appear under the correct trafic source.
            $successUrlString = add_query_arg('utm_nooverride', '1', $successUrlString);

            return $successUrlString;
        }

        protected function getBackUrl($order)
        {
            $backurl = html_entity_decode($order->get_cancel_order_url());
            $backurl .= (
            $this->FLOW === \Resursbank\RBEcomPHP\RESURS_FLOW_TYPES::HOSTED_FLOW ? "&isBack=1" : ""
            );
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
         * WooCommerce Payment API calls.
         *
         * <url>?wc-api=wc_gateway_resursbank
         */
        public function resursbankPaymentHandler()
        {
            $redirectUrl = '';
            $requestType = $this->getRequestKey('request');


            wp_safe_redirect($redirectUrl);
            die;
        }
    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}



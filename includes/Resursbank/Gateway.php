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

                        // TODO: 0% vat?
                        $this->RESURSBANK->addOrderLine(
                            $couponItem->get_id(),
                            $couponDescription,
                            $cart->get_coupon_discount_amount($couponCode) - $cart->get_coupon_discount_tax_amount($couponCode),
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
         * @param WC_Cart $cart
         */
        protected function setResursCart($cart)
        {
            // EComPHP OrderLine Modernizer

            /** @var WC_Cart $theCart */
            $theCart = $cart->get_cart();

            $this->setResursCartShipping($cart);
            $this->setResursCartFees($cart);
            $this->setResursCartDiscount($cart);
            $this->setResursCartItems($theCart);

            // $this->RESURSBANK->addOrderLine('art', 'description', 1000, 25, 'st', 'ORDER_LINE', 1);
        }

        public function is_available()
        {
            return $this->getEnabled();
        }
    }

    // One method rules them all
    include(_RESURSBANK_GATEWAY_PATH . 'includes/Resursbank/Method.php');
}



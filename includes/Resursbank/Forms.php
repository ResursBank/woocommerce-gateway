<?php

class Resursbank_Forms
{
    /**
     * @param $fieldName
     * @return string|void
     */
    private static function getTranslationByFieldName($fieldName)
    {
        $return = '';
        switch (self::getFieldNameByFunctionCall($fieldName)) {
            case 'contact_government_id': // Company
                $return = __('Contact government ID', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'applicant_full_name': // Company
                $return = __('Applicant full name', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'government_id': // Company
                $return = __(
                    'Applicant government ID',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                );
                break;
            case 'applicant_phone':
                $return = __(
                    'Applicant phone number',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                );
                break;
            case 'applicant_mobile':
                $return = __(
                    'Applicant mobile number',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                );
                break;
            case 'applicant_email':
                $return = __(
                    'Applicant email address',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                );
                break;
            case 'card':
                $return = __('Card', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'card_number':
                $return = __('Card number', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'read_more':
                $return = __('Read more', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            default:
                break;
        }

        return $return;
    }

    /**
     * @param $func
     * @return mixed|void
     */
    private static function disableInternalFieldHtml($func)
    {
        $filterName = str_replace('resursbank_get_customer_field_html_', '', $func);

        return apply_filters('resursbank_disable_internal_get_customer_field_html_' . $filterName, '');
    }

    /**
     * @param $func
     * @return mixed
     */
    private static function getFieldNameByFunctionCall($func)
    {
        return str_replace('get_customer_field_html_', '', $func);
    }

    /**
     * @param $fieldName
     * @return string
     */
    private static function getInputField($fieldName, $PAYMENT_METHOD)
    {
        /**
         * Using resursbankcustom_<fieldName> will make it easier to pick up Resurs fields in a later moment
         * rather than if we is using dual underscored fields (like resurs_bank_) since the postData-parser
         * is splitting up field data as [arrayKey1][arrayKey2]=value.
         *
         * @link https://resursbankplugins.atlassian.net/browse/WOO-330 Bug WOO-330
         */
        return sprintf(
            '<div style="display: block;" id="resursbankcustom_div_%s_%s" class="resursPaymentFieldContainer">
            <label for="resurs_custom_%s">%s</label><br>
            <input type="text" id="resursbankcustom_%s_%s" name="resursbankcustom_%s_%s" onkeyup="resursBankFormFieldChange(this)">
            </div>
        ',
            $PAYMENT_METHOD->id,
            $fieldName,
            $fieldName,
            self::getTranslationByFieldName($fieldName),
            $fieldName,
            md5($PAYMENT_METHOD->id),
            $fieldName,
            md5($PAYMENT_METHOD->id)
        );
    }

    /**
     * @param $html
     * @param $PAYMENT_METHOD
     * @return string
     */
    public static function getCustomerFieldHtmlGeneric($html, $PAYMENT_METHOD, $fieldName)
    {
        if (self::disableInternalFieldHtml(__FUNCTION__)) {
            return (string)$html;
        }

        if (!empty($fieldName)) {
            $html = self::getInputField(self::getFieldNameByFunctionCall($fieldName), $PAYMENT_METHOD);
        } else {
            $html = self::getInputField(self::getFieldNameByFunctionCall(__FUNCTION__), $PAYMENT_METHOD);
        }

        return (string)$html;
    }

    /**
     * @param $html
     * @param $PAYMENT_METHOD
     * @return string
     */
    public static function getCustomerFieldHtmlReadMore($html, $PAYMENT_METHOD)
    {
        global $woocommerce;
        $cart = $woocommerce->cart;

        if (self::disableInternalFieldHtml(__FUNCTION__)) {
            return (string)$html;
        }

        $buttonCssClasses = apply_filters('resursbank_readmore_button_css_class', 'btn btn-info active');

        $html = sprintf(
            '<button class="%s" type="button" id="resursCostOfPurchaseButton" onclick="getResursCostOfPurchase(\'%s\', \'%s\')">%s</button>',
            $buttonCssClasses,
            $PAYMENT_METHOD->id,
            $cart->total,
            __('Read more', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce')
        );

        return (string)$html;
    }

}
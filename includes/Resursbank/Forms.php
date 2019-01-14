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
                $return = __('Applicant government ID', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'applicant_phone':
                $return = __('Applicant phone number',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'applicant_mobile':
                $return = __('Applicant mobile number',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'applicant_email':
                $return = __('Applicant email address',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
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
    private static function getInputField($fieldName)
    {
        return sprintf('
        <div style="display: block;" id="resurs_custom_div_%s" class="resursPaymentFieldContainer">
            <label for="resurs_custom_%s">%s</label><br>
            <input type="text" id="resurs_custom_%s" name="resurs_custom_%s" onkeyup="resursBankFormFieldChange(this)">
            </div>
        ', $fieldName,
            $fieldName,
            self::getTranslationByFieldName($fieldName),
            $fieldName,
            $fieldName);
    }

    /**
     * @param $html
     * @param $PAYMENT_METHOD
     * @return string
     */
    public static function get_customer_field_html_generic($html, $PAYMENT_METHOD, $fieldName)
    {
        if (self::disableInternalFieldHtml(__FUNCTION__)) {
            return (string)$html;
        }

        if (!empty($fieldName)) {
            $html = self::getInputField(self::getFieldNameByFunctionCall($fieldName));
        } else {
            $html = self::getInputField(self::getFieldNameByFunctionCall(__FUNCTION__));
        }

        return (string)$html;
    }

    /**
     * @param $html
     * @param $PAYMENT_METHOD
     * @return string
     */
    public static function get_customer_field_html_card($html, $PAYMENT_METHOD)
    {
        if (self::disableInternalFieldHtml(__FUNCTION__)) {
            return (string)$html;
        }

        $html = self::getInputField(self::getFieldNameByFunctionCall(__FUNCTION__));

        return (string)$html;
    }

    /**
     * @param $html
     * @param $PAYMENT_METHOD
     * @return string
     */
    public static function get_customer_field_html_read_more($html, $PAYMENT_METHOD)
    {
        if (self::disableInternalFieldHtml(__FUNCTION__)) {
            return (string)$html;
        }


        return (string)$html;
    }

}
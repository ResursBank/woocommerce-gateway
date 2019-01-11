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
            case 'government_id':
                $return = __('Government ID', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'card':
                $return = __('Card', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'card':
                $return = __('Card number', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
                break;
            case 'card':
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
        <label for="%s">
            %s
        </label>
            <input type="text" id="%s" name="%s" onkeyup="resursBankFormFieldChange(this)">
        ', $fieldName,
            self::getTranslationByFieldName($fieldName),
            $fieldName,
            $fieldName);
    }

    /**
     * @param $html
     * @param $PAYMENT_METHOD
     * @return string
     */
    public static function get_customer_field_html_government_id($html, $PAYMENT_METHOD)
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
    public static function get_customer_field_html_card($html, $PAYMENT_METHOD)
    {
        if (self::disableInternalFieldHtml(__FUNCTION__)) {
            return (string)$html;
        }

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
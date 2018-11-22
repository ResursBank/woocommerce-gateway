<?php

class Adminforms
{

    private $html = '';
    private $configurationArray = array();

    public function __construct()
    {
        $this->getConfigurationArray();
    }

    /**
     * Return prepare configuration content array.
     *
     * The array is based on WooCommerce configuration array, however as we wish to [try] using dynamic
     * forms differently the base configuration will be rendered by Adminforms itself. Primary goal is to
     * make it easier to create configuration and just having one place to edit.
     *
     * @return array
     */
    public function getConfigurationArray()
    {
        $this->configurationArray = array(
            'configuration' => array(
                'title' => __('Merchant Configuration'),
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enabled', 'woocommerce'),
                'description' => __('This is the major plugin switch. If not checked, it will be competely disabled, except for that you can still edit this administration control.',
                    'resurs-bank-payment-gateway-for-woocommerce')
            ),
        );

        return $this->configurationArray;
    }

    /**
     * @return string
     */
    public function setRenderedHtml()
    {
        $this->configurationArray = $this->getConfigurationArray();

        $this->html = '
            <div class="resursGatewayConfigArea" style="border-bottom:1px solid gray;border-left: 1px solid gray;border-right: 1px solid gray;">
            <table class="form-table" style="table-layout: auto !important;">
            ';

        foreach ($this->configurationArray as $settingKey => $item) {
            $this->html .= $this->getRenderedHtml($settingKey, $item);
        }

        $this->html .= '</table></div>';
    }

    public function getRenderedHtml($settingKey, $configItem = array())
    {
        $html = '';

        if (isset($configItem['type']) && method_exists($this, 'getConfig' . $configItem['type'])) {
            $html .= $this->{'getConfig' . $configItem['type']}($settingKey, $configItem);
        }

        return $html;
    }

    private function renderFormRow(
        $settingKey,
        $leftColumnString,
        $rightColumnValue,
        $tdThClassName = '',
        $tdLeftClass = '',
        $tdRightClass = '',
        $isHead = false
    ) {
        // Set absolute defaults
        if (empty($tdThClassName)) {
            $tdThClassName = 'resursGatewayConfigTr';
        }
        if (empty($tdLeftClass)) {
            $tdLeftClass = 'resursGatewayConfigTdLeft';
        }
        if (empty($tdRightClass)) {
            $tdRightClass = 'resursGatewayConfigTdRight';
        }

        if (!$isHead) {
            $return = '
                <tr class="' . $tdThClassName . '">
                <th scope="row" id="columnLeft' . $settingKey . '" class="' . $tdLeftClass . '">' .
                $leftColumnString .
                '</td>
                <td class="' . $tdRightClass . '">' . $rightColumnValue . '</td>
                </tr>
        ';
        } else {
            $return = '
                <tr class="' . $tdThClassName . '">
                <th class="' . $tdLeftClass . '" colspan="2" scope="row" id="columnLeft' . $settingKey . '">' .
                $leftColumnString .
                '</th></tr>
        ';
        }

        return trim($return);
    }

    private function getKeyValue($key, $item) {
        if (is_array($item) && isset($item[$key])) {
            return $item[$key];
        } else if (is_object($item) && isset($item->$key)) {
            return $item->$key;
        }
        return null;
    }

    /**
     * @param $settingKey
     * @param $configItem
     * @return string
     */
    private function getConfigTitle($settingKey, $configItem)
    {
        $return = $this->renderFormRow(
            $settingKey,
            $this->getKeyValue('title', $configItem),
            '',
            null,
            'resursGatewayConfigTitleHeadRow',
            null,
            true
        );

        return $return;
    }

    private function getConfigCheckbox($settingKey, $configItem)
    {
        $return = $this->renderFormRow($settingKey, $this->getKeyValue('title', $configItem));
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }


}
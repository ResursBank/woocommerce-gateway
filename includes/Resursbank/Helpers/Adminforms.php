<?php

class Resursbank_Adminforms
{

    /** @var array */
    private $configurationArray;

    /** @var string */
    private $html = '';

    /**
     * Resursbank_Adminforms constructor.
     */
    public function __construct()
    {
        $this->configurationArray = $this->getConfigurationArray();
    }

    /**
     * Get configuration table as an array
     *
     * @return array
     */
    public function getConfigurationArray()
    {
        return Resursbank_Config::getConfigurationArray();
    }

    /**
     * Prepare forms
     *
     * @return string
     */
    public function setRenderedHtml()
    {
        $this->html = '
            <div class="resursGatewayConfigArea" style="border-bottom:1px solid gray;border-left: 1px solid gray;border-right: 1px solid gray;">
            <table class="form-table" style="table-layout: auto !important;">
            ';

        foreach ($this->configurationArray as $settingKey => $item) {
            $this->html .= $this->getRenderedHtml($settingKey, $item);
        }

        $this->html .= '</table></div>';
    }

    /**
     * Get rendered html
     *
     * @param $settingKey
     * @param array $configItem
     * @return string
     */
    public function getRenderedHtml($settingKey, $configItem = array())
    {
        $html = '';

        // If visibily is false, this configuration row will not be active
        if (isset($configItem['display']) && !(bool)$configItem['display']) {
            return $html;
        }

        if (isset($configItem['type'])) {
            if (method_exists($this, 'getConfig' . $configItem['type'])) {
                $html .= $this->{'getConfig' . $configItem['type']}($settingKey, $configItem);
            } else {
                if (method_exists($this, 'getConfig')) {
                    // Failover on unknown element types
                    $html .= $this->{'getConfig'}($settingKey, $configItem);
                }
            }
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

    /**
     * Get key value (content) from configuration item
     *
     * @param $key
     * @param $item
     * @return mixed|null
     */
    private function getItemKeyValue($key, $item)
    {
        if (is_array($item) && isset($item[$key])) {
            return $item[$key];
        } elseif (is_object($item) && isset($item->$key)) {
            return $item->$key;
        }
        return null;
    }

    /**
     * @param $settingKey
     * @param string $namespace
     * @return bool|mixed|null
     */
    private function getConfigValue($settingKey, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        return Resursbank_Core::getResursOption($settingKey, $namespace);
    }

    /**
     * Create text based form field (not textarea)
     *
     * @param $configItem
     * @param $configType
     * @param $settingKey
     * @param $storedValue
     * @param $scriptLoader
     * @return string
     */
    private function getFieldInputText($configItem, $configType, $settingKey, $storedValue, $scriptLoader)
    {
        $label = $this->getItemKeyValue(
            'label',
            $configItem
        );

        // Labels will be pushed out above text fields - if there are tips, they are pushed below

        return (!empty($label) ? '<div class="resursGatewayConfigLabelTextField">' . $label . '</div>' : '') . '<input type="' . $configType .
            '" name="resursbank_' . $settingKey .
            '" id=resursbank_' . $settingKey .
            '" value="' . $storedValue . '" ' . $scriptLoader . '>';
    }

    /**
     * Create a checkbox form field
     *
     * @param $configItem
     * @param $configType
     * @param $settingKey
     * @param $storedValue
     * @param $scriptLoader
     * @return string
     */
    private function getFieldInputCheckbox($configItem, $configType, $settingKey, $storedValue, $scriptLoader)
    {
        $isChecked = Resursbank_Core::getTrue($settingKey);

        return '<input type="' . $configType .
            '" name="resursbank_' . $settingKey .
            '" id=resursbank_' . $settingKey . '" ' .
            ($isChecked ? 'checked="checked"' : "") .
            ' value="yes" ' . $scriptLoader . '>' .
            $this->getItemKeyValue(
                'label',
                $configItem
            );
    }


    /**
     * @param $configItem
     * @param $settingKey
     * @return string
     */
    private function getTableConfigRow($configItem, $settingKey)
    {
        // Add dynamic javascript actions to a specific form field
        $scriptLoader = apply_filters('resursbank_configrow_scriptloader', '', $settingKey);

        $configType = $this->getItemKeyValue('type', $configItem);
        $storedValue = $this->getConfigValue($settingKey);
        $inputRow = '';

        if (method_exists($this, 'getFieldInput' . ucfirst($configType))) {
            $inputRow = $this->{'getFieldInput' . ucfirst($configType)}(
                    $configItem,
                    $configType,
                    $settingKey,
                    $storedValue,
                    $scriptLoader
                ) . "\n";
        }

        if ($tip = $this->getItemKeyValue('tip', $configItem)) {
            $inputRow .= '<div class="resursGatewayConfigTipText">' . $tip . '</div>';
        }

        return $inputRow;
    }

    /**
     * Render table row for key 'title'
     *
     * @param $settingKey
     * @param $configItem
     * @return string
     */
    private function getConfigTitle($settingKey, $configItem)
    {
        $return = $this->renderFormRow(
            $settingKey,
            $this->getItemKeyValue('title', $configItem),
            '',
            '',
            'resursGatewayConfigTitleHeadRow',
            '',
            true
        );

        return $return;
    }

    /**
     * @param $settingKey
     * @param $configItem
     * @return string
     */
    private function getConfig($settingKey, $configItem)
    {
        return $this->renderFormRow(
            $settingKey,
            $this->getItemKeyValue('title', $configItem),
            $this->getTableConfigRow($configItem, $settingKey)
        );
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }

}

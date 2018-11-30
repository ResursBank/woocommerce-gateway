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
     * @param bool $asArray
     * @return int|mixed|string
     */
    private function getFirstSection($asArray = false)
    {
        $return = !$asArray ? '' : array();
        if (is_array($this->configurationArray)) {
            foreach ($this->configurationArray as $settingKey => $item) {
                if (!$asArray) {
                    $return = $settingKey;
                } else {
                    $return = $item;
                }
                break;
            }
        }
        return $return;
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

        $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : $this->getFirstSection();
        // Make sure everything follows a standard layout before rendering.
        // If the array key 'settings' is missing, do not run this.
        if (isset($this->configurationArray[$section]) && $this->configurationArray[$section]['settings']) {
            foreach ($this->configurationArray[$section]['settings'] as $settingKey => $item) {
                $this->html .= $this->getRenderedHtml($settingKey, $item);
            }
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
     * Create options list for configuration (dropdown/select)
     *
     * @param $configItem
     * @return string
     */
    private function getFieldInputOptions($configItem)
    {
        $optionString = '';
        if (isset($configItem['options']) && is_array($configItem['options'])) {
            foreach ($configItem['options'] as $optionKey => $optionValue) {
                $optionString .= '<option value="' . $optionKey . '">' . htmlentities($optionValue) . '</option>' . "\n";
            }
        }
        return $optionString;
    }

    /**
     * @param $configItem
     * @param $settingKey
     * @return mixed|string|void|null
     */
    private function getFieldInputSelectMulti($configItem, $settingKey)
    {
        $return = null;
        if (isset($configItem['multi']) || isset($configItem['multiple'])) {
            $return = 'multiple';
        }

        $returnCustom = apply_filters('resursbank_configrow_dropdown_multiple', $return, $settingKey);

        if (!empty($returnCustom)) {
            $return = $returnCustom;
        }

        return $return;
    }

    private function getFieldInputSelectSize($configItem, $settingKey)
    {
        $return = null;
        if (isset($configItem['size'])) {
            $return = 'size="' . intval($configItem['size']) . '"';
        }

        $returnCustom = apply_filters('resursbank_configrow_dropdown_size', $return, $settingKey);

        if (!empty($returnCustom)) {
            $return = $returnCustom;
        }

        return $return;
    }

    /**
     * Create a select/dropdown option
     *
     * @param $configItem
     * @param $configType
     * @param $settingKey
     * @param $storedValue
     * @param $scriptLoader
     * @return string
     */
    private function getFieldInputSelect($configItem, $configType, $settingKey, $storedValue, $scriptLoader)
    {
        $selectBox = '
            <select class="resursGatewayConfigSelect" ' . $this->getFieldInputSelectMulti(
                $configItem,
                $settingKey
            ) . ' ' . $this->getFieldInputSelectSize(
                $configItem,
                $settingKey
            ) . '>
        ';

        if (isset($configItem['options']) &&
            (
                $configItem['options'] == 'dynamic' || is_array($configItem['options']
                ) &&
                in_array('dynamic',
                    $configItem['options']))) {
            $configItem['options'] = array();
            $configItem['options'] = apply_filters('resursbank_configrow_dropdown_options',
                (array)$configItem['options'],
                $settingKey
            );
        }

        $selectBox .= $this->getFieldInputOptions($configItem);

        $selectBox .= '</select>
        ';
        return $selectBox;
    }


    /**
     * Render configuration row
     *
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

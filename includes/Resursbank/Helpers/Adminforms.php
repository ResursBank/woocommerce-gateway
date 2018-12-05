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
     * Get configuration table as an array.
     *
     * @return array
     */
    public function getConfigurationArray()
    {
        return Resursbank_Config::getConfigurationArray();
    }

    /**
     * Get the first section of the confiuration array.
     *
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
     * Prepare forms.
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
     * Get rendered html.
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

    /**
     * Render the form field row.
     *
     * @param $settingKey
     * @param $leftColumnString
     * @param $rightColumnValue
     * @param string $tdThClassName
     * @param string $tdLeftClass
     * @param string $tdRightClass
     * @param bool $isHead
     * @return string
     */
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
     * Get key value (content) from configuration item.
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
     * Get configured value for specific option.
     *
     * @param $settingKey
     * @param string $namespace
     * @return bool|mixed|null
     */
    private function getConfigValue($settingKey, $namespace = 'Resurs_Bank_Payment_Gateway')
    {
        return Resursbank_Core::getResursOption($settingKey, $namespace);
    }

    public function getCredentialFields($data = '', $settingKey = '')
    {
        $instance = new Resursbank_Adminforms();

        $credentials = Resursbank_Core::getResursOption('credentials');
        $return = '<div id="resurs_bank_credential_set">
            <table width="100%" style="border:1px solid gray; min-height: 5px;" id="resurs_bank_credential_table">
        ';
        if (is_admin() && is_array($credentials)) {
            foreach ($credentials as $credentialId => $credentialData) {

                $paymentMethods = Resursbank_Core::getPaymentMethods($credentialData['country']);
                $return .= '<tr>
                    <td><b>Username</b><br><input name="resursbank_credentials[' . $credentialId . '][username]" value="' . $credentialData['username'] . '"></td>
                    <td><b>Password</b><br><input name="resursbank_credentials[' . $credentialId . '][password]" value="' . $credentialData['username'] . '"></td>
                    <td><b>Country</b><br><select name="resursbank_credentials[' . $credentialId . '][country]">
                    <option value="SE" ' . ($credentialData['country'] === 'SE' ? 'selected' : '') . '>Sverige</option>
                    <option value="DK" ' . ($credentialData['country'] === 'DK' ? 'selected' : '') . '>Danmark</option>
                    <option value="NO" ' . ($credentialData['country'] === 'NO' ? 'selected' : '') . '>Norge</option>
                    <option value="FI" ' . ($credentialData['country'] === 'FI' ? 'selected' : '') . '>Suomi</option>
                    </select></td>
                    </tr>
                    <tr><td colspan="3" id="method_list_' . $credentialData['country'] . '"></td></tr>
                    <tr><td colspan="3" id="callback_list_' . $credentialData['country'] . '"></td></tr>
                ';

            }
        }
        $return .= '</table>
        </div>';

        $return .= '<div>
            <img src="' .
            Resursbank_Core::getGraphics('add') .
            '" onclick="resursBankCredentialField()" style="cursor: pointer">
            </div>';


        return $return;
    }

    /**
     * Create text based form field (not textarea).
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
     * Create a checkbox form field.
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
     * Returns true if config option allows multiple choices.
     *
     * @param $configItem
     * @return bool
     */
    private function getIsMultiSelection($configItem)
    {
        $return = false;

        if (isset($configItem['multi']) && $configItem['multi']) {
            $return = true;
        }

        return $return;
    }

    /**
     * Create options list for configuration (dropdown/select).
     *
     * @param $configItem
     * @return string
     */
    private function getFieldInputOptions($configItem, $settingKey, $storedValue)
    {
        $optionString = '';

        if (isset($configItem['options']) && is_array($configItem['options'])) {
            foreach ($configItem['options'] as $optionKey => $optionValue) {
                if (!$this->getIsMultiSelection($configItem)) {
                    $selected = ($storedValue == $optionKey ? 'selected' : '');
                } else {
                    // TODO: Multiple selections might be available.
                    // TODO: This is blocked by the "update_option"-parts.
                }
                $optionString .= '<option value="' . $optionKey . '" ' . $selected . '>' . htmlentities($optionValue) . '</option>' . "\n";
            }
        }
        return $optionString;
    }

    /**
     * Get the value for a "mutiselect" dropdown box.
     *
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

    /**
     * Get the size of configurable dropdown.
     *
     * @param $configItem
     * @param $settingKey
     * @return mixed|string|void|null
     */
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
     * Generate an array with all options available.
     *
     * @param $configItemOptionList
     * @param $settingKey
     * @return mixed|void
     */
    private function getDynamicOptions($configItemOptionList, $settingKey)
    {
        if (
            is_array($configItemOptionList) && in_array('dynamic', $configItemOptionList) ||
            is_string($configItemOptionList) && $configItemOptionList === 'dynamic'
        ) {
            $configItemOptionList = apply_filters('resursbank_configrow_dropdown_options',
                (array)$configItemOptionList['options'],
                $settingKey
            );
        }

        if (is_string($configItemOptionList) && preg_match('/^dynamic_/i', $configItemOptionList)) {
            $exDyn = explode('_', $configItemOptionList, 2);
            $filterName = isset($exDyn[1]) ? $exDyn[1] : '';
            if ($filterName) {
                $configItemOptionList = apply_filters('resursbank_dropdown_option_method_' . $filterName,
                    array()
                );
            }
        }

        return $configItemOptionList;
    }

    /**
     * Create a select/dropdown option.
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
        $selectBox = '<select " name="resursbank_' . $settingKey .
            '" id="resursbank_' . $settingKey .
            '" class="resursGatewayConfigSelect" ' .
            $this->getFieldInputSelectMulti(
                $configItem,
                $settingKey
            ) . ' ' . $this->getFieldInputSelectSize(
                $configItem,
                $settingKey
            ) . '>
        ';

        if (isset($configItem['options'])) {
            $configItem['options'] = $this->getDynamicOptions($configItem['options'], $settingKey);
        }

        $selectBox .= $this->getFieldInputOptions($configItem, $settingKey, $storedValue);
        $selectBox .= '</select>';

        return $selectBox;
    }

    /**
     * @param $configItem
     * @param $configType
     * @param $settingKey
     * @param $storedValue
     * @param $scriptLoader
     * @return string|null
     */
    private function getFieldInputFilter($configItem, $configType, $settingKey, $storedValue, $scriptLoader)
    {
        if (isset($configItem['filter']) && !empty($configItem['filter'])) {
            return apply_filters('resursbank_config_element_' . $configItem['filter'], $configItem, $settingKey);
        }
        return null;
    }

    /**
     * Render configuration row.
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
     * Render table row for key 'title'.
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
     * Render the form field row with the current stored value (or default).
     *
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
     * Get the html.
     *
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }

}

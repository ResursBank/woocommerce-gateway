<?php

/**
 * Running this configuration engine before it has been completed may break the former version of the plugin.
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

include('functions.php');

/**
 * Class WC_Settings_Tab_ResursBank
 */
class WC_Settings_Tab_ResursBank extends WC_Settings_Page
{
    public $id = "tab_resursbank";
    private $current_section;
    private $CONFIG_NAMESPACE = "woocommerce_resurs-bank";
    private $oldFormFields;
    private $flow;

    function __construct()
    {
        $this->flow = initializeResursFlow();
        $this->label = __('Resurs Bank', 'woocommerce');
        $this->oldFormFields = getResursWooFormFields();
        add_filter('woocommerce_settings_tabs_array', array($this, "resurs_settings_tab"), 50);
        add_action('woocommerce_settings_' . $this->id, array($this, 'resursbank_settings_show'), 10);
        add_action('woocommerce_update_options_' . $this->id, array($this, 'resurs_settings_save'));
        parent::__construct();
    }

    public function get_sections()
    {
        $sections = array(
            '' => 'Basic Configuration',
            'advanced' => 'Advanced Configuration'
        );
        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    /**
     * Settings tab initializer
     *
     * @param $settings_tabs
     *
     * @return mixed
     */
    public function resurs_settings_tab($settings_tabs)
    {
        $settings_tabs[$this->id] = __('Resurs Bank Administration', 'WC_Payment_Gateway');
        return $settings_tabs;
    }

    /**
     * Another way to save our incoming data.
     *
     * woocommerce_update_options will in this case save settings per row instead of the old proper way, as a serialized string with our settings.
     */
    public function resurs_settings_save()
    {
        $this->oldFormFields = getResursWooFormFields($this->CONFIG_NAMESPACE);
        /** @var $saveAll Sets a saveAll function to active, so that we can save all settings in the same times for which the parameters can be found in oldFormFields */
        $saveAll = true;
        $saveArray = array();
        if (count($_POST) && !$saveAll) {
            /*
             * In the past, we looped through the form field to save everything in the same time, but since we're changing the way how the saves
             * are being made (by moving settings into different settings) we'll use postvars instead, so there is no risk of overwriting settings
             * with empty values.
             */
            foreach ($_POST as $postKey => $postVal) {
                $postKeyNameSpace = str_replace($this->CONFIG_NAMESPACE . "_", '', $postKey);
                if (isset($this->oldFormFields[$postKeyNameSpace])) {
                    $saveArray[$postKeyNameSpace] = $postVal;
                }
            }
        } else {
            foreach ($this->oldFormFields as $fieldKey => $fieldData) {
                if (isset($_POST[$this->CONFIG_NAMESPACE . "_" . $fieldKey])) {
                    $saveArray[$fieldKey] = $_POST[$this->CONFIG_NAMESPACE . "_" . $fieldKey];
                } else {
                    if (!empty(getResursOption($fieldKey))) {
                        $saveArray[$fieldKey] = getResursOption($fieldKey);
                    } else {
                        $saveArray[$fieldKey] = $fieldData['default'];
                    }
                }
            }
        }
        update_option("woocommerce_resurs-bank_settings", $saveArray);
        //woocommerce_update_options($this->oldFormFields);
    }

    private function getFormSettings($settingKey = '')
    {
        if (isset($this->oldFormFields[$settingKey])) {
            return $this->oldFormFields[$settingKey];
        }
    }


    private function setCheckBox($settingKey = '', $namespace = '', $scriptLoader = "")
    {
        $returnCheckbox = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td>
                        <input type="checkbox"
                            name="' . $namespace . '_' . $settingKey . '"
                            id="' . $namespace . '_' . $settingKey . '"
                            ' . (getResursOption($settingKey) === true || getResursOption($settingKey) == "1" ? 'checked="checked"' : "") . '
                               value="true">' . $this->oldFormFields[$settingKey]['label'] . '
                    </td>
                </tr>
        ';
        return $returnCheckbox;
    }

    private function setTextBox($settingKey = '', $namespace = '', $scriptLoader = "")
    {
        $formSettings = $this->getFormSettings($settingKey);
        $textFieldType = isset($formSettings['type']) ? $formSettings['type'] : "text";
        $isPassword = false;
        if ($textFieldType == "password") {
            $isPassword = true;
        }

        $returnTextBox = '
                <tr>
                    <th scope="row" ' . $scriptLoader . '>' . $this->oldFormFields[$settingKey]['title'] . '</th>
        ';

        if (!$isPassword) {
            $returnTextBox .= '
                    <td>
                        <input ' . $scriptLoader . ' type="text"
                            name="' . $namespace . '_' . $settingKey . '"
                            id="' . $namespace . '_' . $settingKey . '"
                            size="64"
                            ' . $scriptLoader . '
                            value="' . getResursOption($settingKey) . '"> ' . $this->oldFormFields[$settingKey]['label'] . '
                            ' . $isPassword . '
                            </td>
        ';
        } else {
            /*
             * The scriptloader in this section will be set up as a callback for "afterclicks"
             */
            $returnTextBox .= '
                <td style="cursor: pointer;">
                <span onclick="resursEditProtectedField(this, \'' . $namespace . '\')" id="' . $namespace . '_' . $settingKey . '">' . __('Click to edit', 'WC_Payment_Gateway') . '</span>
                <span id="' . $namespace . '_' . $settingKey . '_hidden" style="display:none;">
                    <input ' . $scriptLoader . ' type="text"
                            id="' . $namespace . '_' . $settingKey . '_value"
                            size="64"
                            value=""> ' . $this->oldFormFields[$settingKey]['label'] . '
                            <input type="button" onclick="resursSaveProtectedField(\'' . $namespace . '_' . $settingKey . '\', \'' . $namespace . '\', \'' . $scriptLoader . '\')" value="' . __("Save") . '">
                </span>
                </td>
            ';
        }
        $returnTextBox .= '
                            </td>
                </tr>
        ';

        return $returnTextBox;
    }

    private function setDropDown($settingKey = '', $namespace = '', $optionsList = array(), $scriptLoader = "", $listCount = 1)
    {
        $returnDropDown = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td>
                    <select ' . $scriptLoader . '
                    ' . ($listCount > 1 ? "size=\"" . $listCount . "\" multiple " : "") . '
                        name="' . $namespace . '_' . $settingKey . '"
                        id="' . $namespace . '_' . $settingKey . '">
                    ';
        $savedValue = getResursOption($settingKey);
        foreach ($optionsList as $optionKey => $optionValue) {
            $returnDropDown .= '<option value="' . $optionKey . '" ' . ($optionKey == $savedValue ? "selected" : "") . '>' . $optionValue . '</option>';
        }
        $returnDropDown .= '
                    </select>
                    </td>
                </tr>
        ';
        return $returnDropDown;
    }

    /**
     * Primary configuration tab
     */
    public function resursbank_settings_show()
    {
        if (isset($_REQUEST['section']) && !empty($_REQUEST['section'])) {
            $this->current_section = $_REQUEST['section'];
        }

        $namespace = $this->CONFIG_NAMESPACE;

        $url = admin_url('admin.php');
        $url = add_query_arg('page', $_REQUEST['page'], $url);
        $url = add_query_arg('tab', $_REQUEST['tab'], $url);
        $url = add_query_arg('section', $_REQUEST['section'], $url);

        if (isset($_REQUEST['save'])) {
            /*
            foreach ($this->oldFormFields as $fieldKey => $fieldArray) {
                $postKeyName = $namespace . "_" . $fieldKey;
                if (!isset($_POST[$postKeyName])) {
                    setResursOption($fieldKey, '');
                }
            }
            */
            wp_safe_redirect($url);
        }
        $longSimplified = __('Simplified Shop Flow: Payments goes through Resurs Bank API (Default)', 'WC_Payment_Gateway');
        $longHosted = __('Hosted Shop Flow: Customers are redirected to Resurs Bank to finalize payment', 'WC_Payment_Gateway');
        $longOmni = __('Omni Checkout: Fully integrated payment solutions based on iframes (as much as possible including initial customer data are handled by Resurs Bank without leaving the checkout page)', 'WC_Payment_Gateway');

        ?>
        <div class="wrap">
            <h1><?php echo __('Resurs Bank payment gateway configuration', 'WC_Payment_Gateway') ?></h1>
            Plugin version <?php echo rbWcGwVersion() . (!empty($currentVersion) ? " (" . $currentVersion . ")" : "") ?>
            <table class="form-table">
                <?php
                echo $this->setCheckBox('enabled', $namespace);
                echo $this->setDropDown('country', $namespace, array('SE' => 'Sweden', 'DK' => 'Denmark', 'NO' => 'Norway', 'FI' => 'Finland'), "onchange=adminResursChangeFlowByCountry(this)");
                echo $this->setDropDown('flowtype', $namespace, array('simplifiedshopflow' => $longSimplified, 'resurs_bank_hosted' => $longHosted, 'resurs_bank_omnicheckout' => $longOmni), null);
                echo $this->setTextBox('login', $namespace);
                echo $this->setTextBox('password', $namespace, "updateResursPaymentMethods");

                try {
                    $this->paymentMethods = $this->flow->getPaymentMethods();
                } catch (Exception $e) {

                }

                if (count($this->paymentMethods)) {

                    ?>
                    <table class="wc_gateways widefat" cellspacing="0" style="width: 800px;">
                        <thead>
                        <tr>
                            <th class="sort"></th>
                            <th class="name">Betalmetod</th>
                            <th class="id">ID</th>
                            <th class="status">Aktiverad</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        $sortByDescription = array();
                        foreach ($this->paymentMethods as $methodArray) {
                            $description = $methodArray->description;
                            $sortByDescription[$description] = $methodArray;
                        }
                        ksort($sortByDescription);

                        $url = admin_url('admin.php');
                        $url = add_query_arg('page', $_REQUEST['page'], $url);
                        $url = add_query_arg('tab', $_REQUEST['tab'], $url);

                        foreach ($sortByDescription as $methodArray) {
                            ?>
                            <tr>
                                <td width="1%">&nbsp;</td>
                                <td class="name">
                                    <a href="<?php echo $url;?>&section=resurs_bank_nr_<?php echo $methodArray->id ?>"><?php echo $methodArray->description ?></a>
                                </td>
                                <td class="id"><?php echo $methodArray->id ?></td>
                                <td class="status">-</td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>

                    <?php
                }
                ?>


            </table>
        </div>
        <?php

        //echo "<pre>";
        //print_R(getResursWooFormFields());
    }
}

return new WC_Settings_Tab_ResursBank();

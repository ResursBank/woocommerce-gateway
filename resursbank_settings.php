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

    function __construct()
    {
        $this->label = __( 'Resurs Bank', 'woocommerce' );
        $this->oldFormFields = getResursWooFormFields();
        add_filter('woocommerce_settings_tabs_array', array($this, "resurs_settings_tab"), 50);
        add_action('woocommerce_settings_' . $this->id, array($this, 'resursbank_settings_show'), 10);
        add_action('woocommerce_update_options_'. $this->id, array($this, 'resurs_settings_save'));
        parent::__construct();
    }

    public function get_sections() {
        $sections = array(
            '' => 'Basic Configuration',
            'credentials' => 'Credentials',
            'advanced' => 'Advanced Configuration'
        );
        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
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
        $saveArray = array();
        foreach ($this->oldFormFields as $fieldKey => $fieldData) {

            if (isset($_POST[$this->CONFIG_NAMESPACE . "_" . $fieldKey])) {
                $saveArray[$fieldKey] = $_POST[$this->CONFIG_NAMESPACE . "_" . $fieldKey];
            } else {
                if (isset($fieldData['default'])) {
                    $saveArray[$fieldKey] = $fieldData['default'];
                }
            }
        }
        update_option("woocommerce_resurs-bank_settings", $saveArray);
        //woocommerce_update_options($this->oldFormFields);
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

    private function setTextBox($settingKey = '', $namespace = '', $defaultValue = "", $scriptLoader = "")
    {
        $returnCheckbox = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td>
                        <input '.$scriptLoader.' type="checkbox"
                            name="' . $namespace . '_' . $settingKey . '"
                            id="' . $namespace . '_' . $settingKey . '"
                            ' . (getResursOption($settingKey) === true || getResursOption($settingKey) == "1" ? 'checked="checked"' : "") . '
                               value="' . $defaultValue . '">' . $this->oldFormFields[$settingKey]['label'] . '
                    </td>
                </tr>
        ';
        return $returnCheckbox;
    }

    private function setDropDown($settingKey = '', $namespace = '', $optionsList = array(), $scriptLoader = "", $listCount = 1)
    {
        $returnDropDown = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td>
                    <select '.$scriptLoader.'
                    '. ($listCount > 1 ? "size=\"" . $listCount . "\" multiple ": "") .'
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
                <?php echo $this->setCheckBox('enabled', $namespace) ?>
                <?php echo $this->setDropDown('country', $namespace, array('SE' => 'Sweden', 'DK' => 'Denmark', 'NO' => 'Norway', 'FI' => 'Finland'), "onchange=adminResursChangeFlowByCountry(this)"); ?>
                <?php echo $this->setDropDown('flowtype', $namespace, array('simplifiedshopflow' => $longSimplified, 'resurs_bank_hosted' => $longHosted, 'resurs_bank_omnicheckout' => $longOmni), null); ?>
            </table>
        </div>
        <?php

        //echo "<pre>";
        //print_R(getResursWooFormFields());
    }
}

return new WC_Settings_Tab_ResursBank();

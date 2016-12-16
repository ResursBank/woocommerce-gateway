<?php

if (!defined('ABSPATH')) {
    exit;
}

include('functions.php');

class WC_Settings_Tab_ResursBank
{

    private $id = "settings_tab_resursbank";
    private $CONFIG_NAMESPACE = "woocommerce_resurs-bank";
    private $oldFormFields;

    /**
     * Initialize tabs
     */
    public function init()
    {
        $this->oldFormFields = getResursWooFormFields();
        $current_section = "";
        add_filter('woocommerce_settings_tabs_array', array($this, "resurs_settings_tab"), 50);
        add_action('woocommerce_settings_tab_resursbank_primary', array($this, 'resursbank_tab_primary'));
        add_action('woocommerce_update_options_tab_resursbank_primary', array($this, 'resurs_settings_save_primary'));
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
        $settings_tabs['tab_resursbank_primary'] = __('Resurs Bank Administration', 'WC_Payment_Gateway');
        return $settings_tabs;
    }

    public function resurs_settings_save_primary() {
        $this->oldFormFields = getResursWooFormFields($this->CONFIG_NAMESPACE);
        woocommerce_update_options( $this->oldFormFields );
    }

    private function setCheckBox($settingKey = '', $namespace = '')
    {
        $returnCheckbox = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td><input type="checkbox"
                    name="' . $namespace . '_' . $settingKey . '"
                    id="' . $namespace . '_' . $settingKey . '"
                    ' . (getResursOption($settingKey) === true || getResursOption($settingKey) == "1" ? 'checked="checked"' : "") . '
                               value="' . getResursOption($settingKey) . '">' . $this->oldFormFields[$settingKey]['label'] . '
                               
                    </td>
                </tr>
        ';
        return $returnCheckbox;
    }

    /**
     * Primary configuration tab
     */
    public function resursbank_tab_primary()
    {
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

        ?>
        <div class="wrap">
            <h1><?php echo __('Resurs Bank payment gateway configuration', 'WC_Payment_Gateway') ?></h1>
            Plugin version <?php echo rbWcGwVersion() . (!empty($currentVersion) ? " (" . $currentVersion . ")" : "") ?>
                <table class="form-table">
                    <?php echo $this->setCheckBox('enabled', $namespace) ?>
                </table>
        </div>
        <?php

        //echo "<pre>";
        //print_R(getResursWooFormFields());
    }
}

if (is_admin()) {
    $resursSettingsTab = new WC_Settings_Tab_ResursBank();
    $resursSettingsTab->init();
}
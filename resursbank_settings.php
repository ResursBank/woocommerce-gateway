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
    //private $current_section;
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
        $getOpt = get_option('woocommerce_resurs-bank_settings');

        if (!hasResursOptionValue('enabled')) {
            $this->resurs_settings_save();
        }
        parent::__construct();
    }

    public function get_sections()
    {
        $sections = array(
            '' => __('Basic settings', 'WC_Payment_Gateway'),
            'shopflow' => __('Shop flow behaviour ', 'WC_Payment_Gateway'),
            'advanced' => __('Advanced settings', 'WC_Payment_Gateway')
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
    public function resurs_settings_save($setSection = "")
    {
        $section = "woocommerce_resurs-bank";

        if (isset($_REQUEST['section']) && !empty($_REQUEST['section'])) {
            $section = $_REQUEST['section'];
            if (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
                $section = "woocommerce_" . $section;
            } else {
                /*
                 * As we only have two sections (excluding payment methods) we will fall back to this name again...
                 */
                $section = "woocommerce_resurs-bank";
            }
        }

        if (!empty($setSection)) {
            $section = $setSection;
        }

        $this->CONFIG_NAMESPACE = $section;
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
                    $curOption = $this->getOptionByNamespace($fieldKey, $this->CONFIG_NAMESPACE);
                    if ($fieldData['type'] == "checkbox") {
                        $saveArray[$fieldKey] = "no";
                    } else {
                        if (!empty($curOption)) {
                            $saveArray[$fieldKey] = $curOption;
                        } else {
                            $saveArray[$fieldKey] = $fieldData['default'];
                        }
                    }
                }
            }
        }
        //woocommerce_update_options($this->oldFormFields);
        update_option($section . "_settings", $saveArray);
    }

    private function getOptionByNamespace($optionKey, $namespace)
    {
        $useNamespace = $namespace;
        if (!preg_match("/_settings$/i", $namespace)) {
            $useNamespace .= "_settings";
        }
        $this->oldFormFields = resursFormFieldArray();
        $returnedOption = null;
        if (hasResursOptionValue($optionKey, $useNamespace)) {
            $returnedOption = getResursOption($optionKey, $useNamespace);
        } else {
            $fetchOption = $this->oldFormFields[$optionKey];
            if (isset($fetchOption['default'])) {
                $returnedOption = $fetchOption['default'];
            }
        }
        return $returnedOption;
    }

    private function getFormSettings($settingKey = '')
    {
        if (isset($this->oldFormFields[$settingKey])) {
            return $this->oldFormFields[$settingKey];
        }
    }


    private function setCheckBox($settingKey = '', $namespace = '', $scriptLoader = "")
    {
        $isChecked = $this->getOptionByNamespace($settingKey, $namespace);
        $returnCheckbox = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td>
                        <input type="checkbox"
                            name="' . $namespace . '_' . $settingKey . '"
                            id="' . $namespace . '_' . $settingKey . '"
                            ' . ($isChecked ? 'checked="checked"' : "") . '
                               value="yes">' . $this->oldFormFields[$settingKey]['label'] . '<br>
                               <br>
                            <i>' . $this->oldFormFields[$settingKey]['description'] . '</i>

                    </td>
                </tr>
        ';
        return $returnCheckbox;
    }

    private function setTextBox($settingKey = '', $namespace = '', $scriptLoader = "")
    {
        $UseValue = $this->getOptionByNamespace($settingKey, $namespace);
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
                            value="' . $UseValue . '"> <i>' . $this->oldFormFields[$settingKey]['label'] . '</i><br>
                            <i>' . $this->oldFormFields[$settingKey]['description'] . '</i>
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
                            <br><i>' . $this->oldFormFields[$settingKey]['description'] . '</i>
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
        // TODO: Value (multi+simple)
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
                    </select><br>
                            <i>' . $this->oldFormFields[$settingKey]['description'] . '</i>
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
        $url = admin_url('admin.php');
        $url = add_query_arg('page', $_REQUEST['page'], $url);
        $url = add_query_arg('tab', $_REQUEST['tab'], $url);
        $url = add_query_arg('section', $_REQUEST['section'], $url);

        $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : "";
        $namespace = $this->CONFIG_NAMESPACE;

        if (isset($_REQUEST['save'])) {
            $isResursSave = true;
            wp_safe_redirect($url);
        }
        $longSimplified = __('Simplified Shop Flow: Payments goes through Resurs Bank API (Default)', 'WC_Payment_Gateway');
        $longHosted = __('Hosted Shop Flow: Customers are redirected to Resurs Bank to finalize payment', 'WC_Payment_Gateway');
        $longOmni = __('Omni Checkout: Fully integrated payment solutions based on iframes (as much as possible including initial customer data are handled by Resurs Bank without leaving the checkout page)', 'WC_Payment_Gateway');

        $methodDescription = "";

        try {
            if (!preg_match("/^resurs_bank_nr/i", $section)) {
                $this->paymentMethods = $this->flow->getPaymentMethods();
            } else {
                $theMethod = preg_replace("/^resurs_bank_nr_(.*?)/", '$1', $section);
                $this->paymentMethods = $this->flow->getPaymentMethodSpecific($theMethod);
                $methodDescription = $this->paymentMethods->description;
            }

        } catch (Exception $e) {
        }

        ?>
        <div class="wrap">
            <?php
            if ($section == "shopflow") {
                echo '<h1>' . __('Resurs Bank Configuration - Shop flow', 'WC_Payment_Gateway') . '</h1>';
            } else if ($section == "advanced") {
                echo '<h1>' . __('Resurs Bank Configuration - Advanced settings', 'WC_Payment_Gateway') . '</h1>';
            } else if (preg_match("/^resurs_bank_nr_/i", $section)) {
                echo '<h1>' . __('Resurs Bank Configuration', 'WC_Payment_Gateway') . ' - ' . $methodDescription . ' (' . $theMethod . ')</h1>';
            } else {
                echo '<h1>' . __('Resurs Bank payment gateway configuration', 'WC_Payment_Gateway') . '</h1>
                    Plugin version ' . rbWcGwVersion() . ' ' . (!empty($currentVersion) ? $currentVersion : "");
            }

            ?>
            <table class="form-table">
                <?php
                if (empty($section)) {
                    echo $this->setCheckBox('enabled', $namespace);
                    echo $this->setDropDown('serverEnv', $namespace, array('live' => 'Live', 'test' => 'Test'));
                    echo $this->setDropDown('country', $namespace, array('SE' => 'Sweden', 'DK' => 'Denmark', 'NO' => 'Norway', 'FI' => 'Finland'), "onchange=adminResursChangeFlowByCountry(this)");
                    echo $this->setDropDown('priceTaxClass', $namespace, $this->getTaxRatesArray());
                    echo $this->setDropDown('flowtype', $namespace, array('simplifiedshopflow' => $longSimplified, 'resurs_bank_hosted' => $longHosted, 'resurs_bank_omnicheckout' => $longOmni), null);
                    echo $this->setTextBox('login', $namespace);
                    echo $this->setTextBox('password', $namespace, "updateResursPaymentMethods");
                    if (count($this->paymentMethods)) {
                        ?>
                        <table class="wc_gateways widefat" cellspacing="0" style="width: 800px;">
                            <thead>
                            <tr>
                                <th class="sort"></th>
                                <th class="name"><?php echo __('Method', 'WC_Payment_Gateway') ?></th>
                                <th class="title"><?php echo __('Title', 'WC_Payment_Gateway') ?></th>
                                <th class="id"><?php echo __('ID', 'WC_Payment_Gateway') ?></th>
                                <th class="status"><?php echo __('Status', 'WC_Payment_Gateway') ?></th>
                                <th class="process"><?php echo __('Process', 'WC_Payment_Gateway') ?></th>
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
                                $curId = isset($methodArray->id) ? $methodArray->id : "";

                                $optionNamespace = "woocommerce_resurs_bank_nr_" . $curId . "_settings";
                                if (!hasResursOptionValue('enabled', $optionNamespace)) {
                                    $this->resurs_settings_save("woocommerce_resurs_bank_nr_" . $curId);
                                }
                                write_resurs_class_to_file($methodArray);

                                $settingsControl = get_option($optionNamespace);
                                $isEnabled = false;
                                if (is_array($settingsControl) && count($settingsControl)) {
                                    if ($settingsControl['enabled'] == "yes" || $settingsControl == "true" || $settingsControl == "1") {
                                        $isEnabled = true;
                                    }
                                }
                                $maTitle = $methodArray->description;
                                if (isset($settingsControl['title']) && !empty($settingsControl['title'])) {
                                    $maTitle = $settingsControl['title'];
                                }
                                ?>
                                <tr>
                                    <td width="1%">&nbsp;</td>
                                    <td class="name"><a
                                                href="<?php echo $url; ?>&section=resurs_bank_nr_<?php echo $curId ?>"><?php echo $methodArray->description ?></a>
                                    </td>
                                    <td class="title"><?php echo $maTitle ?></td>
                                    <td class="id"><?php echo $methodArray->id ?></td>
                                    <?php if (!$isEnabled) { ?>
                                        <td id="status_<?php echo $curId; ?>" class="status" style="cursor: pointer;"
                                            onclick="runResursAdminCallback('methodToggle', '<?php echo $curId; ?>')">
                                            <span class="status-disabled tips">-</span>
                                        </td>
                                    <?php } else {
                                        ?>
                                        <td id="status_<?php echo $curId; ?>" class="status" style="cursor: pointer;"
                                            onclick="runResursAdminCallback('methodToggle', '<?php echo $curId; ?>')">
                                            <span class="status-enabled tips">-</span>
                                        </td>
                                        <?php
                                    } ?>
                                    <td id="process_<?php echo $curId; ?>"></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>

                        <?php
                    }
                } else if ($section == "shopflow") {
                    echo $this->setTextBox('customCallbackUri', $namespace);
                    echo $this->setCheckBox('waitForFraudControl', $namespace);
                    echo $this->setCheckBox('annulIfFrozen', $namespace);
                    echo $this->setCheckBox('finalizeIfBooked', $namespace);
                } else if ($section == "advanced") {
                    //echo $this->setTextBox('baseLiveURL', $namespace);
                    //echo $this->setTextBox('baseTestURL', $namespace);
                    echo $this->setCheckBox('demoshopMode', $namespace);
                    echo $this->setCheckBox('streamlineBehaviour', $namespace);
                    echo $this->setTextBox('costOfPurchaseCss', $namespace);
                    echo $this->setCheckBox('getAddress', $namespace);
                    echo $this->setCheckBox('handleNatConnections', $namespace);
                } else if (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
                    $namespace = "woocommerce_" . $section;
                    $this->CONFIG_NAMESPACE = $namespace;
                    echo $this->setCheckBox('enabled', $namespace);
                    echo $this->setTextBox('title', $namespace);
                    echo $this->setTextBox('description', $namespace);
                }
                ?>


            </table>
        </div>
        <?php
    }

    private function getTaxRatesArray()
    {
        global $wpdb;
        $rate_select = array();
        $rates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates
				ORDER BY tax_rate_order
				LIMIT %d
				",
            1000
        ));
        foreach ($rates as $rate) {
            $rate_name = $rate->tax_rate_class;
            if ('' === $rate_name) {
                $rate_name = 'standard';
            }
            $rate_name = str_replace('-', ' ', $rate_name);
            $rate_name = ucwords($rate_name);
            $rate_select[$rate->tax_rate_class] = $rate_name;
        }
        return $rate_select;
    }
}

return new WC_Settings_Tab_ResursBank();

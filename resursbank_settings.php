<?php

// Running this configuration engine before it has been completed may break the former version of the plugin.

if ( ! defined('ABSPATH')) {
    exit;
}

include('functions_settings.php');

/**
 * Class WC_Settings_Tab_ResursBank
 */
class WC_Settings_Tab_ResursBank extends WC_Settings_Page
{
    private $spinner;
    private $spinnerLocal;
    private $methodLabel;
    private $curlInDebug = false;
    private $curlHandle = null;
    public $id = "tab_resursbank";
    //private $current_section;
    private $CONFIG_NAMESPACE = "woocommerce_resurs-bank";
    /** @var array THe oldFormFields are not so old actually. They are still in use! */
    private $oldFormFields;
    /** @var $flow Resursbank\RBEcomPHP\ResursBank */
    private $flow;
    private $paymentMethods = array();
    private $NET;

    function __construct()
    {
        /** @var $flow Resursbank\RBEcomPHP\ResursBank */
        $this->flow          = initializeResursFlow();
        $this->label         = __('Resurs Bank', 'woocommerce');
        $this->oldFormFields = getResursWooFormFields();
        add_filter('woocommerce_settings_tabs_array', array($this, "resurs_settings_tab"), 50);
        add_action('woocommerce_settings_' . $this->id, array($this, 'resursbank_settings_show'), 10);
        add_action('woocommerce_update_options_' . $this->id, array($this, 'resurs_settings_save'));
        $getOpt = get_option('woocommerce_resurs-bank_settings');

        $this->NET = new \TorneLIB\MODULE_NETWORK();

        if ( ! hasResursOptionValue('enabled', 'woocommerce_resurs_bank_omnicheckout_settings')) {
            $this->resurs_settings_save('resurs_bank_omnicheckout');
        }
        if ( ! hasResursOptionValue('enabled')) {
            $this->resurs_settings_save();
        }
        parent::__construct();
        if (getResursFlag('DEBUG')) {
            $this->flow->setDebug(true);
        }
    }

    public function get_sections()
    {
        $sections           = array();    // Adaptive array.
        $this->spinner      = plugin_dir_url(__FILE__) . "loader.gif";
        $this->spinnerLocal = plugin_dir_url(__FILE__) . "spinnerLocal.gif";

        $sections[''] = __('Basic settings', 'WC_Payment_Gateway');
        if (isResursOmni()) {
            $sections['resurs_bank_omnicheckout'] = __('Resurs Checkout', 'WC_Payment_Gateway');
        } else {
            $sections['shopflow'] = __('Shop flow settings ', 'WC_Payment_Gateway');
        }
        $sections['advanced'] = __('Advanced settings', 'WC_Payment_Gateway');

        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    /**
     * Settings tab initializer
     *
     * @param $settings_tabs
     *
     * @return mixed
     * @throws Exception
     */
    public function resurs_settings_tab($settings_tabs)
    {
        //$settings_tabs[$this->id] = __('Resurs Bank Administration', 'WC_Payment_Gateway');
        $images = plugin_dir_url(__FILE__) . "img/";
        if (hasWooCommerce('3.2.2', '<')) {
            $settings_tabs[$this->id] = '<img src="' . $images . 'resurs-standard.png">';
        } else {
            // From v3.2.2 and up, all tabs are html-escaped and can not contain images anymore
            $settings_tabs[$this->id] = 'Resurs Bank';
        }

        return $settings_tabs;
    }

    /**
     * Another way to save our incoming data.
     *
     * woocommerce_update_options will in this case save settings per row instead of the old proper way, as a
     * serialized string with our settings.
     *
     * @param string $setSection
     */
    public function resurs_settings_save($setSection = "")
    {
        $section = "woocommerce_resurs-bank";
        if (isset($_REQUEST['section']) && ! empty($_REQUEST['section'])) {
            $section = $_REQUEST['section'];
            if (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
                $section                = "woocommerce_" . $section;
                $this->CONFIG_NAMESPACE = $section;
            } elseif (preg_match("/^woocommerce_resurs_bank_nr_(.*?)$/i", $section)) {
                $this->CONFIG_NAMESPACE = $section;
            } elseif ($section == "resurs_bank_omnicheckout") {
                $section                = "woocommerce_" . $section;
                $this->CONFIG_NAMESPACE = $section;
            } else {
                $section = "woocommerce_resurs-bank";
            }
        }
        if ( ! empty($setSection)) {
            $section = $setSection;
        }

        $this->oldFormFields = getResursWooFormFields($this->CONFIG_NAMESPACE);

        /** @var $saveAll Sets a saveAll function to active, so that we can save all settings in the same times for which the parameters can be found in oldFormFields */
        $saveAll   = true;
        $saveArray = array();
        if (is_array($_POST) && count($_POST) && ! $saveAll) {
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
                    $setFieldValue = $_POST[$this->CONFIG_NAMESPACE . "_" . $fieldKey];
                    if (preg_match("/woocommerce_resurs_bank_nr_/i", $this->CONFIG_NAMESPACE) && $fieldKey == 'price') {
                        // Fees should always be considered properly converted values
                        $setFieldValue = doubleval(preg_replace("/,/", '.', $setFieldValue));
                    }
                    $saveArray[$fieldKey] = $setFieldValue;
                } else {
                    /*
                     * Handling of checkboxes that is located on different pages
                     */
                    if ($fieldData['type'] == "checkbox") {
                        if (isset($_POST['has_' . $fieldKey])) {
                            $curOption = "no";
                        } else {
                            $curOption = $this->getOptionByNamespace($fieldKey, $this->CONFIG_NAMESPACE);
                            if ($curOption == 1) {
                                $curOption = "yes";
                            } else {
                                $curOption = "no";
                            }
                        }
                        $saveArray[$fieldKey] = $curOption;
                    } else {
                        $curOption = $this->getOptionByNamespace($fieldKey, $this->CONFIG_NAMESPACE);
                        if ( ! empty($curOption)) {
                            $saveArray[$fieldKey] = $curOption;
                        } else {
                            $saveArray[$fieldKey] = isset($fieldData['default']) ? $fieldData['default'] : "";
                        }
                    }
                }
            }
        }
        update_option($section . "_settings", $saveArray);
    }

    /**
     * @param $optionKey
     * @param $namespace
     *
     * @return bool|null
     */
    private function getOptionByNamespace($optionKey, $namespace)
    {
        $useNamespace = $namespace;
        if ( ! preg_match("/_settings$/i", $namespace)) {
            $useNamespace .= "_settings";
        }
        $this->oldFormFields = resursFormFieldArray();
        $returnedOption      = null;
        if (hasResursOptionValue($optionKey, $useNamespace)) {
            $returnedOption = getResursOption($optionKey, $useNamespace);
        } else {
            $fetchOption = isset($this->oldFormFields[$optionKey]) ? $this->oldFormFields[$optionKey] : null;
            if (isset($fetchOption['default'])) {
                $returnedOption = $fetchOption['default'];
            }
        }

        return $returnedOption;
    }

    /**
     * @param string $settingKey
     *
     * @return mixed
     */
    private function getFormSettings($settingKey = '')
    {
        if (isset($this->oldFormFields[$settingKey])) {
            return $this->oldFormFields[$settingKey];
        }
    }

    /**
     * @param string $settingKey
     * @param string $namespace
     * @param string $scriptLoader
     *
     * @return string
     */
    private function setCheckBox($settingKey = '', $namespace = 'woocommerce_resurs-bank_settings', $scriptLoader = "")
    {
        $properNameSpace = $namespace;
        if ( ! preg_match("/_settings$/", $namespace)) {
            $properNameSpace = $namespace . "_settings";
        }
        $isChecked    = $this->getOptionByNamespace($settingKey, $namespace);
        $formSettings = $this->getFormSettings($settingKey);

        $issetResursOption = issetResursOption($settingKey, $properNameSpace);
        if ( ! $issetResursOption) {
            if (isset($formSettings['default'])) {
                if ($formSettings['default'] == "false") {
                    $isChecked = false;
                }
            }
        }

        $extraInfoMark = "";
        if (isset($formSettings['info']) && ! empty($formSettings['info'])) {
            $extraInfoMark = '<span class="dashicons resurs-help-tip" onmouseover="$RB(\'#extraInfo' . $settingKey . '\').show(\'medium\')" onmouseout="$RB(\'#extraInfo' . $settingKey . '\').hide(\'medium\')"></span>';
        }
        $returnCheckbox = '
                <tr>
                    <th scope="row" id="columnLeft' . $settingKey . '">' . $this->oldFormFields[$settingKey]['title'] . " " . $extraInfoMark . '</th>
                    <td id="columnRight' . $settingKey . '">
                    <input type="hidden" name="has_' . $settingKey . '">
                    <input type="checkbox" name="' . $namespace . '_' . $settingKey . '" id="' . $namespace . '_' . $settingKey . '" ' . ($isChecked ? 'checked="checked"' : "") . ' value="yes" ' . $scriptLoader . '>' . (isset($this->oldFormFields[$settingKey]['label']) ? $this->oldFormFields[$settingKey]['label'] : "") . '<br>
                    <br>
                       <i>' . (isset($this->oldFormFields[$settingKey]['description']) && ! empty($this->oldFormFields[$settingKey]['description']) ? $this->oldFormFields[$settingKey]['description'] : "") . '</i>
                    </td>
                </tr>
        ';

        if ( ! empty($extraInfoMark)) {
            $returnCheckbox .= '
            <tr id="extraInfo' . $settingKey . '" style="display: none;">
                <td></td>
                <td class="rbAdminExtraInfo">' . $formSettings['info'] . '</td>
            </tr>
            ';
        }

        return $returnCheckbox;
    }

    /**
     * @param string $settingKey
     * @param string $namespace
     * @param string $scriptLoader
     *
     * @return string
     */
    private function setHidden($settingKey = '', $namespace = '', $scriptLoader = "")
    {
        $UseValue     = $this->getOptionByNamespace($settingKey, $namespace);
        $formSettings = $this->getFormSettings($settingKey);
        if (empty($UseValue) && isset($formSettings['default'])) {
            $UseValue = $formSettings['default'];
        }
        $returnHiddenValue = '<input type="hidden"
                            name="' . $namespace . '_' . $settingKey . '"
                            id="' . $namespace . '_' . $settingKey . '"
                            ' . $scriptLoader . '
                            value="' . $UseValue . '">';

        return $returnHiddenValue;
    }

    /**
     * @param string $settingKey
     * @param string $namespace
     * @param string $scriptLoader
     *
     * @return string
     */
    private function setTextBox($settingKey = '', $namespace = '', $scriptLoader = "")
    {
        $UseValue      = $this->getOptionByNamespace($settingKey, $namespace);
        $formSettings  = $this->getFormSettings($settingKey);
        $textFieldType = isset($formSettings['type']) ? $formSettings['type'] : "text";
        $isPassword    = false;
        if ($textFieldType == "password") {
            $isPassword = true;
        }

        $returnTextBox = '
                <tr>
                    <th scope="row" ' . $scriptLoader . '>' . $this->oldFormFields[$settingKey]['title'] . '</th>
        ';

        $setLabel = isset($this->oldFormFields[$settingKey]['label']) ? $this->oldFormFields[$settingKey]['label'] : "";
        if ( ! empty($this->methodLabel)) {
            $setLabel          = $this->methodLabel;
            $this->methodLabel = null;
        }

        if ( ! $isPassword) {
            $returnTextBox .= '
                    <td>
                        <input ' . $scriptLoader . ' type="text"
                            name="' . $namespace . '_' . $settingKey . '"
                            id="' . $namespace . '_' . $settingKey . '"
                            size="64"
                            ' . $scriptLoader . '
                            value="' . $UseValue . '"> <i>' . $setLabel . '</i><br>
                            <i>' . (isset($this->oldFormFields[$settingKey]['description']) ? $this->oldFormFields[$settingKey]['description'] : "") . '</i>
                            ' . $isPassword . '
                            </td>
        ';
        } else {
            // The scriptloader in this section will be set up as a callback for "afterclicks"
            $returnTextBox .= '
                <td style="cursor: pointer;">
                    <span onclick="resursEditProtectedField(this, \'' . $namespace . '\')" id="' . $namespace . '_' . $settingKey . '">' . __('Click to edit',
                    'WC_Payment_Gateway') . '</span>
                    <span id="' . $namespace . '_' . $settingKey . '_spinner" style="display:none;"></span>
                    <span id="' . $namespace . '_' . $settingKey . '_hidden" style="display:none;">
                        <input ' . $scriptLoader . ' type="text" id="' . $namespace . '_' . $settingKey . '_value" size="64" value=""> ' . $setLabel . '
                        <input type="button" onclick="resursSaveProtectedField(\'' . $namespace . '_' . $settingKey . '\', \'' . $namespace . '\', \'' . $scriptLoader . '\')" value="' . __("Save") . '">
                        <br><i>' . $this->oldFormFields[$settingKey]['description'] . '</i>
                    </span><br>
                    <span id="process_' . $namespace . '_' . $settingKey . '"></span>
                </td>
            ';
        }
        $returnTextBox .= '
                            </td>
                </tr>
        ';

        return $returnTextBox;
    }

    /**
     * @param string $settingKey
     * @param string $namespace
     * @param array  $optionsList
     * @param string $scriptLoader
     * @param int    $listCount
     *
     * @return string
     */
    private function setDropDown(
        $settingKey = '',
        $namespace = '',
        $optionsList = array(),
        $scriptLoader = "",
        $listCount = 1
    ) {
        $formSettings = $this->getFormSettings($settingKey);
        if ( ! is_array($optionsList)) {
            $optionsList = array();
        }
        /*
         * Failover to prior forms.
         */
        if (is_array($optionsList) && isset($formSettings['options']) && count($formSettings['options']) && ! count($optionsList)) {
            $optionsList = $formSettings['options'];
        }
        $returnDropDown = '
                <tr>
                    <th scope="row">' . $this->oldFormFields[$settingKey]['title'] . '</th>
                    <td>
                    ';
        if (count($optionsList) > 0) {
            $returnDropDown .= '
                    <select class="resursConfigSelect" ' . $scriptLoader . '
                    ' . ($listCount > 1 ? "size=\"" . $listCount . "\" multiple " : "") . '
                        name="' . $namespace . '_' . $settingKey . '"
                        id="' . $namespace . '_' . $settingKey . '">
                    ';
            $savedValue     = $this->getOptionByNamespace($settingKey, $namespace);
            foreach ($optionsList as $optionKey => $optionValue) {
                $returnDropDown .= '<option value="' . $optionKey . '" ' . ($optionKey == $savedValue ? "selected" : "") . '>' . $optionValue . '</option>';
            }
            $returnDropDown .= '
                    </select><br>
                    ';
        } else {
            $returnDropDown .= '<div style="font-color:#990000 !important;font-weight: bold;">' . __('No selectable options are available for this option',
                    'WC_Payment_Gateway') . '</div>
            <br>';
        }

        $returnDropDown .= '
                            <i>' . $this->oldFormFields[$settingKey]['description'] . '</i>
                            </td>
                </tr>
        ';

        return $returnDropDown;
    }

    /**
     * @param string $separatorTitle
     * @param string $setClass
     *
     * @return string
     */
    private function setSeparator($separatorTitle = "", $setClass = "configSeparateTitle")
    {
        return '<tr><th colspan="2" class="resursConfigSeparator"><div class=" ' . $setClass . '">' . $separatorTitle . '</div></th></tr>';
    }

    /**
     * @param $temp_class_files
     */
    private function UnusedPaymentClassesCleanup($temp_class_files)
    {
        $allIncludes = array();
        $path        = plugin_dir_path(__FILE__) . 'includes/';
        $globInclude = glob(plugin_dir_path(__FILE__) . 'includes/*.php');
        if (is_array($globInclude)) {
            foreach ($globInclude as $filename) {
                $allIncludes[] = str_replace($path, '', $filename);
            }
        }
        // Prevent the plugin from sending legacy data to this controller
        foreach ($temp_class_files as $fileRow) {
            $newFileRow         = "resurs_bank_nr_" . $fileRow . ".php";
            $temp_class_files[] = $newFileRow;
        }
        if (is_array($temp_class_files)) {
            foreach ($allIncludes as $exclude) {
                if ( ! in_array($exclude, $temp_class_files)) {
                    @unlink($path . $exclude);
                }
            }
        }
    }

    /**
     * Make sure our settings can be written in the file system
     *
     * @return bool
     */
    private function canWrite()
    {
        $path     = plugin_dir_path(__FILE__) . 'includes/';
        $filename = $path . "this" . rand(1000, 9999);
        @file_put_contents($filename, null);
        if (file_exists($filename)) {
            @unlink($filename);

            return true;
        }

        return false;
    }

    /**
     * @param string $definedConstantName
     *
     * @return mixed|null
     */
    function getDefined($definedConstantName = '')
    {
        if (defined($definedConstantName)) {
            return constant($definedConstantName);
        }

        return null;
    }

    /**
     * @param string $key
     *
     * @return null
     */
    function getCurlInformation($key = '')
    {
        $curlV = curl_version();
        if (isset($curlV[$key])) {
            return trim($curlV[$key]);
        }

        return null;
    }

    /**
     * @return string
     */
    function getPluginInformation()
    {

        $hasSoap     = class_exists("\SoapClient") ? true : false;
        $hasCurlInit = true;
        $hasSsl      = true;
        if ( ! function_exists('curl_init')) {
            $hasCurlInit = false;
        }
        $streamWrappers = @stream_get_wrappers();
        if ( ! in_array('https', $streamWrappers)) {
            $hasSsl = false;
        }

        $pluginInfo = $this->setSeparator(__('Plugin information', 'WC_Payment_Gateway'));
        $topCss     = 'style="vertical-align: top !important;" valign="top"';
        //$topCursor  = 'style="vertical-align: top !important;cursor:pointer;" valign="top"';
        $pluginInfo .= '<tr><td ' . $topCss . '>Plugin/Gateway</td><td ' . $topCss . '>v' . rbWcGwVersion() . '</td></tr>';
        $pluginInfo .= '<tr><td ' . $topCss . '>PHP</td><td ' . $topCss . '>' . (defined('PHP_VERSION') ? "v" . PHP_VERSION : "") . '</td></tr>';
        $pluginInfo .= '<tr><td onclick="doGetRWecomTags()" ' . $topCss . '>EComPHP</td><td ' . $topCss . '>' . $this->flow->getVersionFull() . '<br>
            <div id="rwoecomtag" style="display:none;"></div>
        </td></tr>';
        $pluginInfo .= '<tr><td ' . $topCss . '>curl driver</td><td ' . $topCss . '>' . $this->displayAvail($hasCurlInit) . ($hasCurlInit ? "v" . $this->getCurlInformation('version') : "") . '</td></tr>';
        $pluginInfo .= '<tr><td ' . $topCss . '>SoapClient</td><td ' . $topCss . '>' . $this->displayAvail($hasSoap) . '</td></tr>';
        $pluginInfo .= '<tr><td ' . $topCss . '>SSL/https (wrapper)</td><td ' . $topCss . '>' . $this->displayAvail($hasSsl) . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : "") . '</td></tr>';
        $pluginInfo .= '<tr><td onclick="doGetRWcurlTags()" ' . $topCss . '>Communication</td><td ' . $topCss . '> NETCURL-v' . $this->getDefined('NETCURL_RELEASE') . ' / MODULE_CURL-v' . $this->getDefined('NETCURL_CURL_RELEASE') . ' / MODULE_SOAP-v' . $this->getDefined('NETCURL_SIMPLESOAP_RELEASE') . '<br>
            <div id="rwocurltag" style="display:none;"></div>
        </td></tr>';

        return $pluginInfo;
    }

    function displayAvail($boolValue)
    {
        if ($boolValue == true) {
            return '<div style="color:#009900; font-weight: bold;">' . __('Available', 'WC_Payment_Gateway') . '</div>';
        }

        return '<div style="color:#990000; font-weight: bold;">' . __('Not available', 'WC_Payment_Gateway') . '</div>';
    }

    /**
     * Primary configuration tab
     */
    public function resursbank_settings_show()
    {
        if ( ! $this->canWrite()) {
            echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __('This plugin needs read/write access to the includes directory located in the path of the plugin or it will not be able to save the payment method configuration.',
                    'WC_Payment_Gateway') . '</div>';

            return;
        }
        $debugSet = $this->flow->getDebug();
        if (isset($debugSet['debug'])) {
            $this->curlInDebug = $debugSet['debug'] == 1 ? true : false;
            if ($this->curlInDebug) {
                try {
                    $this->curlHandle = $this->flow->getCurlHandle();
                } catch (\Exception $curlHandleException) {
                    // If this was triggered, it should be no more
                    $this->curlInDebug = false;
                }
            }
        }
        $url       = admin_url('admin.php');
        $url       = add_query_arg('page', isset($_REQUEST['page']) ? $_REQUEST['page'] : "", $url);
        $url       = add_query_arg('tab', isset($_REQUEST['tab']) ? $_REQUEST['tab'] : "", $url);
        $url       = add_query_arg('section', isset($_REQUEST['section']) ? $_REQUEST['section'] : "", $url);
        $section   = isset($_REQUEST['section']) ? $_REQUEST['section'] : "";
        $namespace = $this->CONFIG_NAMESPACE;

        if (isset($_REQUEST['save'])) {
            $isResursSave = true;
            wp_safe_redirect($url);
        }
        $longSimplified = __('Simplified Shop Flow: Payments goes through Resurs Bank API (Default)',
            'WC_Payment_Gateway');
        $longHosted     = __('Hosted Shop Flow: Customers are redirected to Resurs Bank to finalize payment',
            'WC_Payment_Gateway');
        $longOmni       = __('Omni Checkout: Fully integrated payment solutions based on iframes (as much as possible including initial customer data are handled by Resurs Bank without leaving the checkout page)',
            'WC_Payment_Gateway');

        $methodDescription      = "";
        $paymentMethodsError    = null;
        $class_files            = array();
        $countryCredentialArray = array();
        if (isResursDemo() && class_exists("CountryHandler")) {
            $countryHandler = new CountryHandler();
            $countryList    = array('se', 'no', 'dk', 'fi');
            $countryConfig  = $countryHandler->getCountryConfig();
            foreach ($countryList as $countryId) {
                if (isset($countryConfig[$countryId]) && isset($countryConfig[$countryId]['account']) && ! empty($countryConfig[$countryId]['account'])) {
                    $countryCredentialArray[$countryId] = $countryConfig[$countryId]['account'];
                }
            }
        }

        $hasCountries = false;
        try {
            if ( ! preg_match("/^resurs_bank_nr/i", $section)) {
                // If we're in demoshop mode go another direction
                if (isResursDemo() && is_array($countryCredentialArray) && count($countryCredentialArray)) {
                    try {
                        /** @var $demoShopFlow \Resursbank\RBEcomPHP\ResursBank */
                        $demoShopFlow = initializeResursFlow();
                        $demoShopFlow->setSimplifiedPsp(true);
                        $countryBasedPaymentMethods = array();
                        if (isset($_REQUEST['reset'])) {
                            foreach ($countryCredentialArray as $countryId => $countryCredentials) {
                                delete_transient("resursMethods" . $countryId);
                            }
                        }
                        foreach ($countryCredentialArray as $countryId => $countryCredentials) {
                            if (isset($countryCredentials['login']) && isset($countryCredentials['password'])) {
                                // To unslow this part of the plugin, we'd run transient methods storage
                                if ( ! isset($_REQUEST['reset'])) {
                                    $countryBasedPaymentMethods[$countryId] = get_transient('resursMethods' . $countryId);
                                } else {
                                    // Let's make sure that we can clean up mistakes.
                                    $countryBasedPaymentMethods[$countryId] = array();
                                }

                                $transientTest = get_transient('resursMethods' . $countryId,
                                    $countryBasedPaymentMethods[$countryId]);

                                if (empty($countryBasedPaymentMethods[$countryId]) || (is_array($countryBasedPaymentMethods[$countryId]) && ! count($countryBasedPaymentMethods[$countryId])) || empty($transientTest)) {
                                    $demoShopFlow->setAuthentication($countryCredentials['login'],
                                        $countryCredentials['password']);
                                    $countryBasedPaymentMethods[$countryId] = $demoShopFlow->getPaymentMethods(array(), true);
                                    foreach ($countryBasedPaymentMethods[$countryId] as $countryObject) {
                                        $countryObject->country = $countryId;
                                    }
                                    set_transient('resursMethods' . $countryId,
                                        $countryBasedPaymentMethods[$countryId]);
                                }
                                $this->paymentMethods = array_merge($this->paymentMethods,
                                    $countryBasedPaymentMethods[$countryId]);
                                //foreach ($countryBasedPaymentMethods[ $countryId ] as $oArr) {
                                //      $this->paymentMethods[] = $oArr;
                                //}

                                set_transient("resursAllMethods", $this->paymentMethods);
                            }
                        }
                        $hasCountries = true;
                    } catch (\Exception $countryException) {
                        // Ignore and go on
                    }
                } else {
                    $this->paymentMethods = $this->flow->getPaymentMethods(array(), true);
                }
                if (is_array($this->paymentMethods)) {
                    foreach ($this->paymentMethods as $methodLoop) {
                        $class_files[] = $methodLoop->id;
                    }
                }
                $this->UnusedPaymentClassesCleanup($class_files);
            } else {
                $theMethod = preg_replace("/^resurs_bank_nr_(.*?)/", '$1', $section);
                // Make sure there is an overrider on PSP
                $this->flow->setSimplifiedPsp(true);
                $this->paymentMethods = $this->flow->getPaymentMethodSpecific($theMethod);
                $methodDescription    = isset($this->paymentMethods->description) ? $this->paymentMethods->description : "";
            }
        } catch (Exception $e) {
            $errorCode    = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode == 401) {
                $paymentMethodsError = __('Authentication error', 'WC_Payment_Gateway');
            } elseif ($errorCode >= 400 && $errorCode <= 499) {
                $paymentMethodsError = __('The service can not be reached for the moment (HTTP Error ' . $errorCode . '). Please try again later.',
                    'WC_Payment_Gateway');
            } elseif ($errorCode >= 500) {
                $paymentMethodsError = __("Unreachable service, code ",
                        'WC_Payment_Gateway') . $errorCode . " (" . trim($errorMessage) . ")";
            } else {
                $paymentMethodsError = "Unhandled exception from Resurs: [" . $errorCode . "] - " . $e->getMessage();
            }
        }

        ?>
        <div class="wrap">
            <?php
            if ($section == "shopflow") {
                echo '<h1>' . __('Resurs Bank Configuration - Shop flow', 'WC_Payment_Gateway') . '</h1>';
            } elseif ($section == "advanced") {
                echo '<h1>' . __('Resurs Bank Configuration - Advanced settings', 'WC_Payment_Gateway') . '</h1>';
            } elseif (preg_match("/^resurs_bank_nr_/i", $section)) {
                echo '<h1>' . __('Resurs Bank Configuration',
                        'WC_Payment_Gateway') . ' - ' . $methodDescription . ' (' . $theMethod . ')</h1>';
            } else {
                echo '<h1>' . __('Resurs Bank payment gateway configuration', 'WC_Payment_Gateway') . '</h1>
                    v' . rbWcGwVersion() . (defined('PHP_VERSION') ? "/PHP v" . PHP_VERSION : "") . ' ' . (! empty($currentVersion) ? $currentVersion : "");
            }
            ?>
            <!-- Table layout auto fixes issues for woocom 3.4.0 as it has added a fixed value to it in this version -->
            <table class="form-table" style="table-layout: auto !important;">
                <?php

                if (empty($section)) {
                    echo $this->setSeparator(__('Plugin and checkout', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('enabled', $namespace);
                    echo $this->setHidden('title', $namespace);
                    echo $this->setDropDown('priceTaxClass', $namespace, $this->getTaxRatesArray());
                    echo $this->setCheckBox('postidreference', $namespace);
                    echo $this->setSeparator(__('API Settings', 'WC_Payment_Gateway'));
                    echo $this->setDropDown('flowtype', $namespace);
                    echo $this->setDropDown('country', $namespace, null,
                        "onchange=adminResursChangeFlowByCountry(this)");
                    echo $this->setDropDown('serverEnv', $namespace);
                    echo $this->setTextBox('login', $namespace,
                        'onfocus="jQuery(\'#woocommerce_resurs-bank_password\').click();"');
                    echo $this->setTextBox('password', $namespace); // Former callback "updateResursPaymentMethods"
                    echo $this->setSeparator(__('Callbacks', 'WC_Payment_Gateway')); // , "configSeparateTitleSmall"

                    $callSent = get_transient("resurs_callbacks_sent");
                    $callRecv = get_transient("resurs_callbacks_received");

                    echo '<tr>
                    <th></th>
                    <td>
                    ';
                    if (callbackUpdateRequest()) {
                        echo '<div id="callbacksRequireUpdate" style="margin-top: 8px;" class="labelBoot labelBoot-warning labelBoot-big labelBoot-nofat labelBoot-center">' . __('Your callbacks requires an update. The plugin will do this for you as soon as this page has is done loading...',
                                'WC_Payment_Gateway') . '</div><br><br>';
                    }

                    echo '
                            <div class="labelBoot labelBoot-info labelBoot-big labelBoot-nofat labelBoot-center">' . __('Callback URLs registered at Resurs Bank',
                            'WC_Payment_Gateway') . ' ' . ($this->curlInDebug ? " [" . __('curl module is set to enter debug mode',
                                'WC_Payment_Gateway') . "]" : "") . '</div>
                            <div id="callbackContent" style="margin-top: 8px;">
                    ';
                    $login    = getResursOption("login");
                    $password = getResursOption("password");
                    if ( ! empty($login) && ! empty($password)) {
                        $callbackUriCacheTime = time() - get_transient("resurs_callback_templates_cache_last");
                        if ($callbackUriCacheTime >= 86400) {
                            echo '<img src="' . $this->spinner . '" border="0">';
                        } else {
                            echo '<img src="' . $this->spinnerLocal . '" border="0">';
                        }
                    }
                    echo '

                    </div>
                    <b>' . __('Callback health', 'WC_Payment_Gateway') . '</b><br>
                    <table cellpadding="0" cellpadding="0" style="margin-bottom: 5px;" width="100%">
                    <tr>
                        <td style="padding: 0px;" width="20%" valign="top">' . __('Last test run',
                            'WC_Payment_Gateway') . '</td>
                        <td style="padding: 0px;" id="lastCbRun" width="80%" valign="top">' . ($callSent > 0 ? strftime('%Y-%m-%d (%H:%M:%S)',
                            $callSent) : __('Never', 'WC_Payment_Gateway')) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 0px;" width="20%" valign="top">' . __('Last test received',
                            'WC_Payment_Gateway') . '</td>
                        <td style="padding: 0px;" id="lastCbRec" width="80%" valign="top">' . ($callRecv > 0 ? strftime('%Y-%m-%d (%H:%M:%S)',
                            $callRecv) : __('Never', 'WC_Payment_Gateway')) . '</td>
                    </tr>
                    </table>
                    <br>
                    
                    </td>
                    </tr>
                    ';

                    echo $this->setSeparator(__('Payment methods',
                        'WC_Payment_Gateway')); // , "configSeparateTitleSmall"
                    echo '<tr>
                    <th scope="row">
                    </th>
                    <td id="currentResursPaymentMethods">
                    ';
                    if (empty($paymentMethodsError)) {
                        if ( ! count($this->paymentMethods)) {
                            echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __('The list of available payment methods will appear, when credentials has been entered',
                                    'WC_Payment_Gateway') . '</div><br><br>';
                        } else {
                            if (isResursOmni(true)) {
                                echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center labelBoot-border">' . __('Payment method titles/descriptions are not editable when using Resurs Checkout as they are handled by Resurs Bank, server side. Contact support if you want to do any changes',
                                        'WC_Payment_Gateway') . '</div><br><br>';
                            }
                        }
                    } else {
                        echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __('The list of available payment methods is not available due to an error at Resurs Bank! See the error message below.',
                                'WC_Payment_Gateway') . '</div><br><br><div class="labelBoot labelBoot-warning labelBoot-big labelBoot-nofat labelBoot-center">' . nl2br($paymentMethodsError) . '</div>';
                    }

                    if (isset($this->paymentMethods['error']) && ! empty($this->paymentMethods['error'])) {
                        $this->paymentMethods = array();
                    }
                    if (count($this->paymentMethods)) {
                        foreach ($this->paymentMethods as $methodArray) {
                            $curId           = isset($methodArray->id) ? $methodArray->id : "";
                            $optionNamespace = "woocommerce_resurs_bank_nr_" . $curId . "_settings";
                            if ( ! hasResursOptionValue('enabled', $optionNamespace)) {
                                $this->resurs_settings_save("woocommerce_resurs_bank_nr_" . $curId);
                            }
                        }
                        //generatePaymentMethodHtml($this->paymentMethods);
                        ?>
                        <table class="wc_gateways widefat" cellspacing="0px" cellpadding="0px"
                               style="width: inherit;">
                            <thead>
                            <tr>
                                <th class="sort"></th>
                                <th class="name"><?php echo __('Method', 'WC_Payment_Gateway') ?></th>
                                <th class="title"><?php echo __('Title', 'WC_Payment_Gateway') ?></th>
                                <th class="annuityfactor"><?php echo __('AnnuityFactor', 'WC_Payment_Gateway') ?>
                                    <br><span
                                            style="font-weight:normal !important;font-style: italic; font-size:11px; padding: 0px; margin: 0px;"><?php echo __('Activate/disable by clicking the X-boxes',
                                            'WC_Payment_Gateway'); ?></span></th>

                                <?php
                                // Having special configured contries?
                                if ($hasCountries) {
                                    ?>
                                    <th class="country"><?php echo __('Country', 'WC_Payment_Gateway') ?></th>
                                    <?php
                                }
                                ?>

                                <?php if ( ! isResursOmni(true)) {
                                    ?>
                                    <th class="id"><?php echo __('ID', 'WC_Payment_Gateway') ?></th>
                                    <th class="fee"><?php echo __('Fee', 'WC_Payment_Gateway') ?></th>
                                    <th class="status"><?php echo __('Enable/Disable', 'WC_Payment_Gateway') ?></th>
                                <?php } ?>
                                <th class="process"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            $sortByDescription = array();
                            foreach ($this->paymentMethods as $methodArray) {
                                $description = $methodArray->description;
                                if (isResursDemo() && isset($methodArray->country)) {
                                    $description .= " [" . $methodArray->country . "]";
                                }
                                $sortByDescription[$description] = $methodArray;
                            }
                            ksort($sortByDescription);
                            $url = admin_url('admin.php');
                            $url = add_query_arg('page', $_REQUEST['page'], $url);
                            $url = add_query_arg('tab', $_REQUEST['tab'], $url);

                            foreach ($sortByDescription as $methodArray) {
                                $curId           = isset($methodArray->id) ? $methodArray->id : "";
                                $optionNamespace = "woocommerce_resurs_bank_nr_" . $curId . "_settings";
                                if ( ! hasResursOptionValue('enabled', $optionNamespace)) {
                                    $this->resurs_settings_save("woocommerce_resurs_bank_nr_" . $curId);
                                }
                                write_resurs_class_to_file($methodArray);
                                $settingsControl = get_option($optionNamespace);
                                $isEnabled       = false;
                                if (is_array($settingsControl) && count($settingsControl)) {
                                    if ($settingsControl['enabled'] == "yes" || $settingsControl == "true" || $settingsControl == "1") {
                                        $isEnabled = true;
                                    }
                                }
                                $annuityMethod   = resursOption("resursAnnuityMethod");
                                $annuityDuration = resursOption("resursAnnuityDuration");

                                $maTitle = $methodArray->description;
                                // Unacceptable title set (WOO-96)
                                if (isset($settingsControl['title']) && strtolower($settingsControl['title']) == "resurs bank") {
                                    // Make sure this will be unset as it's detected
                                    setResursOption("title", '', $optionNamespace);
                                    $settingsControl['title'] = "";
                                }
                                if (isset($settingsControl['title']) && ! empty($settingsControl['title']) && ! isResursOmni(true)) {
                                    $maTitle = $settingsControl['title'];
                                }

                                ?>
                                <tr>
                                    <td width="1%">&nbsp;</td>
                                    <td class="name" width="300px">
                                        <?php if ( ! isResursOmni(true)) { ?>
                                            <a href="<?php echo $url; ?>&section=resurs_bank_nr_<?php echo $curId ?>"><?php echo $methodArray->description ?></a>
                                        <?php } else {
                                            echo $methodArray->description;
                                        }
                                        ?>
                                    </td>
                                    <td class="title" width="300px"><?php echo $maTitle ?></td>

                                    <td id="annuity_<?php echo $curId; ?>">
                                        <?php
                                        // Future safe if
                                        if ($methodArray->type == "REVOLVING_CREDIT" || $methodArray->specificType == "REVOLVING_CREDIT") {
                                            $scriptit = 'resursRemoveAnnuityElements(\'' . $curId . '\')';

                                            ?>
                                            <?php if (strtolower($annuityMethod) == strtolower($curId)) {
                                                // Clickables must be separated as the selector needs to be editable
                                                ?>
                                                <span class="status-enabled tips"
                                                      id="annuityClick_<?php echo $curId; ?>"
                                                      data-tip="<?php echo __('Enabled', 'woocommerce') ?>"
                                                      onclick="runResursAdminCallback('annuityToggle', '<?php echo $curId; ?>');<?php echo $scriptit; ?>">-</span>
                                                <?php
                                                $annuityFactors = null;
                                                $selector       = null;
                                                try {
                                                    $annuityFactors = $this->flow->getAnnuityFactors($methodArray->id);
                                                } catch (\Exception $annuityException) {
                                                    $selector = $annuityException->getMessage();
                                                }
                                                $selectorOptions = "";
                                                if (is_array($annuityFactors) && count($annuityFactors)) {
                                                    foreach ($annuityFactors as $factor) {
                                                        $selected = "";
                                                        if ($annuityDuration == $factor->duration) {
                                                            $selected = "selected";
                                                        }
                                                        $selectorOptions .= '<option value="' . $factor->duration . '" ' . $selected . '>' . $factor->paymentPlanName . '</option>';
                                                    }
                                                }
                                                if (is_null($selector)) {
                                                    $selector = '<select class="resursConfigSelectShort" id="annuitySelector_' . $curId . '" onchange="runResursAdminCallback(\'annuityDuration\', \'' . $curId . '\', this.value)">' . $selectorOptions . '</select>';
                                                }
                                                ?>
                                                <?php echo $selector; ?>
                                                <?php
                                            } else {
                                                ?>
                                                <span class="status-disabled tips"
                                                      id="annuityClick_<?php echo $curId; ?>"
                                                      data-tip="<?php echo __('Disabled', 'woocommerce') ?>"
                                                      onclick="runResursAdminCallback('annuityToggle', '<?php echo $curId; ?>');<?php echo $scriptit; ?>">-</span>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </td>

                                    <?php
                                    // Having special configured contries?
                                    if ($hasCountries) {
                                        ?>
                                        <th class="country"><?php echo $methodArray->country ?></th>
                                        <?php
                                    }

                                    if ( ! isResursOmni(true)) { ?>
                                        <td class="id"><?php echo $methodArray->id; ?></td>
                                        <td class="fee" id="fee_<?php echo $methodArray->id; ?>"
                                            onclick="changeResursFee(this)">
                                            <?php
                                            $priceValue = $this->getOptionByNamespace(
                                                "price",
                                                "woocommerce_resurs_bank_nr_" . $curId
                                            );
                                            if (empty($priceValue)) {
                                                // Injecting ourselves in the current structure
                                                $priceValue = '<img id="fim_'.$methodArray->id.'" onclick="changeResursFee(this)" src="' . plugin_dir_url(__FILE__) . 'img/pen16x.png' . '">';
                                            } else {
                                                // Make sure that noone sets wrong values
                                                $priceValue = preg_replace("/,/", '.', $priceValue);
                                                $priceValue = doubleval($priceValue);
                                            }
                                            echo $priceValue;
                                            ?></td>

                                        <?php if ( ! $isEnabled) { ?>
                                            <td id="status_<?php echo $curId; ?>" class="status"
                                                style="cursor: pointer;"
                                                onclick="runResursAdminCallback('methodToggle', '<?php echo $curId; ?>')">
                                                <span class="status-disabled tips"
                                                      data-tip="<?php echo __('Disabled', 'woocommerce') ?>">-</span>
                                            </td>
                                        <?php } else {
                                            ?>
                                            <td id="status_<?php echo $curId; ?>" class="status"
                                                style="cursor: pointer;"
                                                onclick="runResursAdminCallback('methodToggle', '<?php echo $curId; ?>')">
                                                <span class="status-enabled tips"
                                                      data-tip="<?php echo __('Enabled', 'woocommerce') ?>">-</span>
                                            </td>
                                            <?php
                                        }

                                        ?>
                                    <?php } ?>
                                    <td id="process_<?php echo $curId; ?>"></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                        <?php
                    }
                    echo '</td></tr>';

                    echo $this->getPluginInformation();

                } elseif ($section == "shopflow") {
                    if (isResursOmni(true)) {
                        echo '<br><div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center labelBoot-border">' . __('Shop flow settings are not editable when using Resurs Checkout - Contact support if you want to do any changes',
                                'WC_Payment_Gateway') . '</div><br><br>';
                    } else {

                        $nextInvoice = null;
                        try {
                            $nextInvoice = $this->flow->getNextInvoiceNumberByDebits(5);
                        } catch (\Exception $e) {
                            $nextInvoice = $e->getMessage();
                        }

                        $styleRecommended = "display: none";
                        $waitForFraud     = getResursOption("waitForFraudControl");
                        $annulIfFrozen    = getResursOption("annulIfFrozen");
                        $finalizeIfBooked = getResursOption("finalizeIfBooked");
                        if ( ! $waitForFraud && ! $annulIfFrozen && ! $finalizeIfBooked) {
                            $styleRecommended = "";
                        }
                        echo '<div id="shopwFlowRecommendedSettings" style="' . $styleRecommended . '">' . __('When all the below settings are unchecked, you are running the plugin with a Resurs Bank preferred configuration',
                                'WC_Payment_Gateway') . '</div>';
                        echo $this->setCheckBox('waitForFraudControl', $namespace, 'onchange="wfcComboControl(this)"');
                        echo $this->setCheckBox('annulIfFrozen', $namespace, 'onchange="wfcComboControl(this)"');
                        echo $this->setCheckBox('finalizeIfBooked', $namespace, 'onchange="wfcComboControl(this)"');
                        echo $this->setSeparator("Invoice numbering");
                        echo '<tr><td colspan="2">' . __('Next invoice number to use',
                                'WC_Payment_Gateway') . ": " . $nextInvoice . '</td></tr>';
                    }
                } elseif ($section == "resurs_bank_omnicheckout") {
                    $namespace              = "woocommerce_" . $section;
                    $this->CONFIG_NAMESPACE = $namespace;
                    echo $this->setCheckBox('enabled', $namespace);
                    echo $this->setSeparator(__('Visuals', 'WC_Payment_Gateway'));
                    echo $this->setTextBox('title', $namespace);
                    echo $this->setTextBox('description', $namespace);
                    echo $this->setSeparator(__('Checkout', 'WC_Payment_Gateway'));
                    echo $this->setDropDown('iFrameLocation', $namespace);
                    echo $this->setTextBox('iframeShape', $namespace);
                    // This is reserved for future use, so we won't touch this for now
                    //echo $this->setCheckBox( 'useStandardFieldsForShipping', $namespace );
                    echo $this->setSeparator(__('Advanced', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('omniFrameNotReloading', $namespace);
                    echo $this->setCheckBox('cleanOmniCustomerFields', $namespace);
                    //echo $this->setCheckBox( 'secureFieldsNotNull', $namespace );
                } elseif ($section == "advanced") {
                    //echo $this->setCheckBox('includeEmptyTaxClasses', $namespace);

                    echo $this->setSeparator(__('URL Settings', 'WC_Payment_Gateway'));
                    echo $this->setTextBox('customCallbackUri', $namespace);
                    echo $this->setTextBox('costOfPurchaseCss', $namespace);
                    echo $this->setSeparator(__('Callbacks', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('callbackUpdateAutomation', $namespace);
                    echo $this->setTextBox('callbackUpdateInterval', $namespace);
                    echo $this->setSeparator(__('Customer and store', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('getAddress', $namespace);
                    echo $this->setCheckBox('reduceOrderStock', $namespace);
                    //echo $this->setSeparator( __( 'Special aftershopFlow Settings', 'WC_Payment_Gateway' ) );
                    //echo $this->setCheckBox( 'disableAftershopFunctions', $namespace );
                    echo $this->setSeparator(__('Testing and development', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('devResursSimulation', $namespace);
                    echo $this->setTextBox('devSimulateSuccessUrl', $namespace);
                    echo $this->setCheckBox('showResursCheckoutStandardFieldsTest', $namespace);
                    echo $this->setSeparator(__('Miscellaneous', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('streamlineBehaviour', $namespace);
                    echo $this->setCheckBox('showPaymentIdInOrderList', $namespace);
                    echo $this->setSeparator(__('Special test occasions', 'WC_Payment_Gateway'),
                        'configSeparateTitleSmall');
                    echo $this->setTextBox('devFlags', $namespace, 'onkeyup="devFlagsControl(this)"');
                    echo $this->setCheckBox('demoshopMode', $namespace);

                    /*
                    echo $this->setCheckBox('getAddressUseProduction', $namespace);
                    echo $this->setTextBox('ga_login', $namespace);
                    echo $this->setTextBox('ga_password', $namespace);
                    */

                    echo $this->setSeparator(__('Network', 'WC_Payment_Gateway'));
                    echo $this->setCheckBox('handleNatConnections', $namespace);
                    echo $this->setSeparator(__('Maintenance', 'WC_Payment_Gateway'));
                    echo '<tr><th>' . __('Clean up ', 'WC_Payment_Gateway') . '</th><td>';
                    echo '<input id="cleanResursSettings" type="button" value="' . __('Resurs settings',
                            'WC_Payment_Gateway') . '" onclick="runResursAdminCallback(\'cleanRbSettings\', \'cleanResursSettings\')"> <span id="process_cleanResursSettings"></span><br>';
                    echo '<input id="cleanResursMethods" type="button" value="' . __('Payment methods',
                            'WC_Payment_Gateway') . '" onclick="runResursAdminCallback(\'cleanRbMethods\', \'cleanResursMethods\')"> <span id="process_cleanResursMethods"><span><br>';
                    echo '<input id="cleanResursCache" type="button" value="' . __('Cached data',
                            'WC_Payment_Gateway') . '" onclick="runResursAdminCallback(\'cleanRbCache\', \'cleanResursCache\')"> <span id="process_cleanResursCache"><span>';
                    echo '</td></tr>';

                    echo $this->getPluginInformation();

                } elseif (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
                    if ( ! isResursOmni(true)) {
                        $namespace              = "woocommerce_" . $section;
                        $this->CONFIG_NAMESPACE = $namespace;

                        echo $this->setCheckBox('enabled', $namespace);

                        $this->methodLabel = '<br>' . __('Default title set by Resurs Bank is ',
                                'WC_Payment_Gateway') . '<b> ' . $methodDescription . '</b>';
                        //$curSet            = getResursOption('title', $namespace);
                        echo $this->setTextBox('title', $namespace);
                        echo $this->setTextBox('description', $namespace);
                        echo $this->setTextBox('price', $namespace);
                        echo $this->setTextBox('priceDescription', $namespace);
                        echo $this->setCheckBox('enableMethodIcon', $namespace);
                        echo $this->setTextBox('icon', $namespace);

                    } else {
                        echo "<br>";
                        echo '<div id="listUnavailable" class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __('The payment method editor is not availabe while Resurs Checkout is active',
                                'WC_Payment_Gateway') . '</div>';
                    }
                }
                echo $this->setSeparator(__('Save above configuration with the button below', 'WC_Payment_Gateway'));

                if ($this->curlInDebug) {
                    $getDebugData = $this->flow->getDebug();
                    echo '<tr><td colspan="2">';
                    echo '<b>curlmodule debug data</b><br>';
                    echo '<pre>';
                    print_r($getDebugData);
                    if (method_exists($this->flow, 'getAutoDebitableTypes')) {
                        echo "<b>Payment methods that tend to automatically DEBIT orders as soon as money are transferred to merchant</b>\n";
                        print_r($this->flow->getAutoDebitableTypes());
                    }
                    $sslUnsafe = $this->flow->getSslIsUnsafe();
                    echo __("During the URL calls, SSL certificate validation has been disabled",
                            'WC_Payment_Gateway') . ": " . ($sslUnsafe ? __("Yes") : __("No")) . "\n";
                    echo '</pre>';
                    echo '</td></tr>';
                }

                ?>


            </table>
        </div>
        <?php
    }

    /**
     * @return array
     */
    private function getTaxRatesArray()
    {
        global $wpdb;
        $rate_select = array();
        $rates       = $wpdb->get_results($wpdb->prepare(
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
            $rate_name                          = str_replace('-', ' ', $rate_name);
            $rate_name                          = ucwords($rate_name);
            $rate_select[$rate->tax_rate_class] = $rate_name;
        }

        $includeEmptyTaxClasses = getResursOption('includeEmptyTaxClasses');
        if ($includeEmptyTaxClasses) {
            $validTaxClasses = WC_Tax::get_tax_classes();
            foreach ($validTaxClasses as $className) {
                if ($className != "standard" && $className != "") {
                    $rate_select[$className] = $className;
                }
            }
        }

        return $rate_select;
    }
}

return new WC_Settings_Tab_ResursBank();

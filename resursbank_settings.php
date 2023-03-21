<?php

// Running this configuration engine before it has been completed may break the former version of the plugin.

if (!defined('ABSPATH')) {
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
    /** @var array The oldFormFields are not so old actually. They are still in use! */
    private $oldFormFields;
    /** @var $flow Resursbank\RBEcomPHP\ResursBank */
    private $flow;
    private $paymentMethods = [];

    public function __construct()
    {
        /** @var $flow Resursbank\RBEcomPHP\ResursBank */
        $this->flow = initializeResursFlow();
        $this->label = __('Resurs Bank', 'woocommerce');
        $this->oldFormFields = getResursWooFormFields();
        add_filter('woocommerce_settings_tabs_array', [$this, "resurs_settings_tab"], 50);
        add_action('woocommerce_settings_' . $this->id, [$this, 'resursbank_settings_show'], 10);
        add_action('woocommerce_update_options_' . $this->id, [$this, 'resurs_settings_save']);
        $getOpt = get_option('woocommerce_resurs-bank_settings');

        if (!hasResursOptionValue('enabled', 'woocommerce_resurs_bank_omnicheckout_settings')) {
            $this->resurs_settings_save('resurs_bank_omnicheckout');
        }
        if (!hasResursOptionValue('enabled')) {
            $this->resurs_settings_save();
        }
        parent::__construct();
        if (getResursFlag('DEBUG')) {
            $this->flow->setDebug(true);
        }
    }

    /**
     * Configurable sections.
     * @return array|mixed|void
     */
    public function get_sections()
    {
        $sections = [];    // Adaptive array.
        $this->spinner = plugin_dir_url(__FILE__) . "loader.gif";
        $this->spinnerLocal = plugin_dir_url(__FILE__) . "spinnerLocal.gif";

        $sections[''] = __('Basic settings', 'resurs-bank-payment-gateway-for-woocommerce');
        if (isResursOmni()) {
            $sections['resurs_bank_omnicheckout'] = __(
                'Resurs Checkout',
                'resurs-bank-payment-gateway-for-woocommerce'
            );
        } else {
            $sections['fraudcontrol'] = __('Fraud control', 'resurs-bank-payment-gateway-for-woocommerce');
        }
        $sections['advanced'] = __('Advanced settings', 'resurs-bank-payment-gateway-for-woocommerce');
        $sections['shortcodes'] = __('Shortcodes', 'resurs-bank-payment-gateway-for-woocommerce');

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
        //$settings_tabs[$this->id] = __('Resurs Bank Administration', 'resurs-bank-payment-gateway-for-woocommerce');
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
        if (isset($_REQUEST['section']) && !empty($_REQUEST['section'])) {
            $section = $_REQUEST['section'];
            if (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
                $section = "woocommerce_" . $section;
                $this->CONFIG_NAMESPACE = $section;
            } elseif (preg_match("/^woocommerce_resurs_bank_nr_(.*?)$/i", $section)) {
                $this->CONFIG_NAMESPACE = $section;
            } elseif ($section == "resurs_bank_omnicheckout") {
                $section = "woocommerce_" . $section;
                $this->CONFIG_NAMESPACE = $section;
            } else {
                $section = "woocommerce_resurs-bank";
            }
        }
        if (!empty($setSection)) {
            $section = $setSection;
        }

        $this->oldFormFields = getResursWooFormFields($this->CONFIG_NAMESPACE);

        /** @var $saveAll Sets a saveAll function to active, so that we can save all settings in the same times for which the parameters can be found in oldFormFields */
        $saveAll = true;
        $saveArray = [];
        if (is_array($_POST) && count($_POST) && !$saveAll) {
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
                        if (!empty($curOption)) {
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
        if (!preg_match("/_settings$/i", $namespace)) {
            $useNamespace .= "_settings";
        }
        $this->oldFormFields = resursFormFieldArray();
        $returnedOption = null;
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
        if (!preg_match("/_settings$/", $namespace)) {
            $properNameSpace = $namespace . "_settings";
        }
        $isChecked = $this->getOptionByNamespace($settingKey, $namespace);
        $formSettings = $this->getFormSettings($settingKey);

        $issetResursOption = issetResursOption($settingKey, $properNameSpace);
        if (!$issetResursOption) {
            if (isset($formSettings['default'])) {
                if ($formSettings['default'] == "false") {
                    $isChecked = false;
                }
            }
        }

        $extraInfoMark = "";
        if (isset($formSettings['info']) && !empty($formSettings['info'])) {
            $extraInfoMark = '<span class="dashicons resurs-help-tip" onmouseover="$RB(\'#extraInfo' . $settingKey . '\').show(\'medium\')" onmouseout="$RB(\'#extraInfo' . $settingKey . '\').hide(\'medium\')"></span>';
        }
        $returnCheckbox = '
                <tr>
                    <th scope="row" id="columnLeft' . $settingKey . '">' . $this->oldFormFields[$settingKey]['title'] . " " . $extraInfoMark . '</th>
                    <td id="columnRight' . $settingKey . '">
                    <input type="hidden" name="has_' . $settingKey . '">
                    <input type="checkbox" name="' . $namespace . '_' . $settingKey . '" id="' . $namespace . '_' . $settingKey . '" ' . ($isChecked ? 'checked="checked"' : "") . ' value="yes" ' . $scriptLoader . '>' . (isset($this->oldFormFields[$settingKey]['label']) ? $this->oldFormFields[$settingKey]['label'] : "") . '<br>
                    <br>
                       <i>' . (isset($this->oldFormFields[$settingKey]['description']) && !empty($this->oldFormFields[$settingKey]['description']) ? $this->oldFormFields[$settingKey]['description'] : "") . '</i>
                    </td>
                </tr>
        ';

        if (!empty($extraInfoMark)) {
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
        $UseValue = $this->getOptionByNamespace($settingKey, $namespace);
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

        $setLabel = isset($this->oldFormFields[$settingKey]['label']) ? $this->oldFormFields[$settingKey]['label'] : "";
        if (!empty($this->methodLabel)) {
            $setLabel = $this->methodLabel;
            $this->methodLabel = null;
        }

        if (!$isPassword) {
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
                    <span onclick="resursEditProtectedField(this, \'' . $namespace . '\')" id="' . $namespace . '_' . $settingKey . '">' . __(
                    'Click to edit',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ) . '</span>
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
     * @param array $optionsList
     * @param string $scriptLoader
     * @param int $listCount
     *
     * @return string
     */
    private function setDropDown(
        $settingKey = '',
        $namespace = '',
        $optionsList = [],
        $scriptLoader = "",
        $listCount = 1
    ) {
        $formSettings = $this->getFormSettings($settingKey);
        if (!is_array($optionsList)) {
            $optionsList = [];
        }

        // Failover to prior forms.
        if (is_array($optionsList) && isset($formSettings['options']) && count($formSettings['options']) && !count($optionsList)) {
            $optionsList = $formSettings['options'];
        }
        $returnDropDown = '
                <tr>
                    <th scope="row">' .
            (isset($this->oldFormFields[$settingKey]['title']) &&
            !empty($this->oldFormFields[$settingKey]['title']) ? $this->oldFormFields[$settingKey]['title'] : $settingKey) . '</th>
                    <td>
                    ';

        $className = 'resursConfigSelect';
        $multiVar = '';
        if ($listCount > 1) {
            $className = 'resursConfigSelectMulti';
            $multiVar = '[]';
        }
        if (count($optionsList) > 0) {
            $returnDropDown .= '
                    <select class="' . $className . '" ' . $scriptLoader . '
                    ' . ($listCount > 1 ? "size=\"" . $listCount . "\" multiple " : "") . '
                        name="' . $namespace . '_' . $settingKey . $multiVar . '"
                        id="' . $namespace . '_' . $settingKey . '">
                    ';
            $savedValue = $this->getOptionByNamespace($settingKey, $namespace);
            foreach ($optionsList as $optionKey => $optionValue) {
                $matchingSavedValue = false;
                if (is_array($savedValue) && in_array($optionValue, $savedValue)) {
                    $matchingSavedValue = true;
                } else {
                    if (is_array($savedValue) && count($savedValue) == 1) {
                        $savedValue = array_pop($savedValue);
                    }
                    if (is_string($savedValue) && $optionKey == $savedValue) {
                        $matchingSavedValue = true;
                    }
                }
                $returnDropDown .= '<option value="' . $optionKey . '" ' . ($matchingSavedValue ? "selected" : "") . '>' . $optionValue . '</option>';
            }
            $returnDropDown .= '
                    </select><br>
                    ';
        } else {
            $returnDropDown .= '<div style="font-color:#990000 !important;font-weight: bold;">' . __(
                    'No selectable options are available for this option',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ) . '</div>
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
        $allIncludes = [];
        $path = plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath();
        $globInclude = glob(plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath() . '*.php');
        if (is_array($globInclude)) {
            foreach ($globInclude as $filename) {
                $allIncludes[] = str_replace($path, '', $filename);
            }
        }
        // Prevent the plugin from sending legacy data to this controller
        foreach ($temp_class_files as $fileRow) {
            $newFileRow = "resurs_bank_nr_" . $fileRow . ".php";
            $temp_class_files[] = $newFileRow;
        }
        if (is_array($temp_class_files)) {
            foreach ($allIncludes as $exclude) {
                if (!in_array($exclude, $temp_class_files)) {
                    @unlink($path . $exclude);
                }
            }
        }

        return true;
    }

    /**
     * Make sure our settings can be written in the file system
     *
     * @return bool
     */
    private function canWrite()
    {
        $path = plugin_dir_path(__FILE__) . getResursPaymentMethodModelPath();
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
    public function getDefined($definedConstantName = '')
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
    public function getCurlInformation($key = '')
    {
        $curlV = curl_version();
        if (isset($curlV[$key])) {
            return trim($curlV[$key]);
        }

        return null;
    }

    /**
     * @return bool
     */
    public function hasCurlConstants()
    {
        $const = get_defined_constants();
        $curlConstants = 0;
        foreach ($const as $constantKey => $constantValue) {
            if (preg_match('/^curl/i', $constantKey)) {
                $curlConstants++;
                if ($curlConstants >= 50) {
                    break;
                }
            }
        }
        if ($curlConstants >= 50) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getPluginInformation()
    {
        $hasSoap = class_exists("\SoapClient") ? true : false;
        $getEnabledCurl = true;
        $hasSsl = true;
        $curlIsHereButDisabled = $this->hasCurlConstants();
        $curlWarning = '';
        if (!function_exists('curl_init') || !function_exists('curl_exec')) {
            $getEnabledCurl = false;
            if ($curlIsHereButDisabled) {
                $curlWarning = '<br><b><i>' . __(
                        'Curl seems to be installed but some vital functions may be disabled in php.ini',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</i></b>';
            }
        }
        $streamWrappers = @stream_get_wrappers();
        if (!in_array('https', $streamWrappers)) {
            $hasSsl = false;
        }

        $pluginIsGit = false;
        if (@file_exists(__DIR__ . '/.git') && !defined('RESURS_BANK_PAYMENT_GATEWAY_FOR_WOOCOMMERCE_HAS_GIT')) {
            $pluginIsGit = true;
        }

        $pluginInfo = '';
        $topCss = 'style="vertical-align: top !important;" valign="top"';
        if ((int)($lastTransientTimeout = get_transient('resurs_connection_timeout'))) {
            $timeoutCss = 'style="vertical-align: top !important; color: #990000 !important; font-size: 16px !important;" valign="top"';

            $pluginInfo .= sprintf(
                '<tr><td %s><b>%s</b></td><td %s><b>%s</b></td></tr>',
                $timeoutCss,
                __('ResursAPI Timeout', 'resurs-bank-payment-gateway-for-woocommerce'),
                $timeoutCss,
                sprintf(
                    __(
                        'Timeout detected %s. Wait time changed to %s seconds temporarily.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    date(
                        'Y-m-d H:i:s',
                        $lastTransientTimeout
                    ),
                    getResursOption('timeout_throttler')
                )
            );
        }

        $pluginInfo .= $this->setSeparator(__('Plugin information', 'resurs-bank-payment-gateway-for-woocommerce'));
        //$topCursor  = 'style="vertical-align: top !important;cursor:pointer;" valign="top"';
        $pluginInfo .= '<tr><td ' . $topCss . '>Plugin/Gateway</td><td ' . $topCss . '>v' . rbWcGwVersion() . '</td></tr>';
        $pluginInfo .= '<tr><td ' . $topCss . '>PHP</td><td ' . $topCss . '>' . (defined('PHP_VERSION') ? "v" . PHP_VERSION : "") . '</td></tr>';
        $pluginInfo .= '<tr><td style="cursor:pointer;" onclick="doGetRWecomTags()" ' . $topCss . '><i>EComPHP</i></td><td ' . $topCss . '>' . $this->flow->getVersionFull() . '<br>
            <div id="rwoecomtag" style="display:none;"></div>
        </td></tr>';
        $pluginInfo .= '<tr><td ' . $topCss . '>curl driver</td><td ' . $topCss . '>' .
            $this->displayAvail($getEnabledCurl) . ($getEnabledCurl ? "v" . $this->getCurlInformation('version') : "") .
            $curlWarning .
            '</td></tr>';

        $netcurlRelease = $this->getDefined('NETCURL_RELEASE');
        $nc = sprintf(
            'NETCURL-v%s, MODULE_CURL-v%s, MODULE_SOAP-v%s',
            $this->getDefined('NETCURL_RELEASE'),
            $this->getDefined('NETCURL_CURL_RELEASE'),
            $this->getDefined('NETCURL_SIMPLESOAP_RELEASE')
        );

        if (empty($netcurlRelease)) {
            $newRelease = $this->getDefined('NETCURL_VERSION');
            $nc = sprintf(
                'NETCURL-v%s',
                $newRelease
            );
        }
        $pluginInfo .= sprintf(
            '<tr><td %s>SoapClient</td><td %s>%s</td></tr>',
            $topCss,
            $topCss,
            $this->displayAvail($hasSoap)
        );
        $pluginInfo .= sprintf(
            '<tr><td %s>SSL/https (wrapper)</td><td %s>%s</td></tr>',
            $topCss,
            $topCss,
            $this->displayAvail($hasSsl) . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : "")
        );
        $pluginInfo .= sprintf(
            '<tr><td style="cursor:pointer;" onclick="doGetRWcurlTags()" %s><i>Communication</i></td><td %s>%s<br>
            <div id="rwocurltag" style="display:none;"></div></td></tr>',
            $topCss,
            $topCss,
            $nc
        );
        $pluginInfo .= sprintf(
            '<tr><td %s>External ip and information</td><td %s><button type="button" onclick="rbGetIpInfo()">Request Information</button><br><div id="externalIpInfo"></div></td></tr>',
            $topCss,
            $topCss
        );

        if ($pluginIsGit) {
            $pluginInfo .= $this->getGitInfo($topCss);
        }

        return $pluginInfo;
    }

    /**
     * Active developer view.
     *
     * @param $topCss
     * @return string
     */
    public function getGitInfo($topCss)
    {
        $pluginInfo = "";
        try {
            $gitbin = (string)getResursFlag('GIT_BIN');
            if (empty($gitbin) && @file_exists('/usr/bin/git')) {
                $gitbin = '/usr/bin/git';
            }
            if (empty($gitbin) || !@file_exists($gitbin)) {
                $pluginInfo .= '<tr><td ' . $topCss . '>gitinfo</td><td ' . $topCss . '>' .
                    __(
                        'The plugin is part of a git repo. Status is unknown; need proper /path/to/git to activate feature.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</td></tr>';
            } else {
                if (file_exists($gitbin)) {
                    $gitbin .= ' --git-dir=' . __DIR__ . '/.git';
                    @exec($gitbin . " rev-parse --short HEAD 2>&1", $shortRev);
                    @exec($gitbin . " rev-parse --abbrev-ref HEAD 2>&1", $abbrev);
                    if (is_array($shortRev)) {
                        $pluginInfo .= '<tr><td ' . $topCss . '>gitinfo</td><td ' . $topCss . '>' .
                            array_pop($abbrev) . '<br>' .
                            array_pop($shortRev) .
                            '</td></tr>';
                    }
                }
            }
        } catch (\Exception $e) {
            // As silent as possible.
        }

        return $pluginInfo;
    }

    public function displayAvail($boolValue)
    {
        if ($boolValue == true) {
            return '<div style="color:#009900; font-weight: bold;">' .
                __('Available', 'resurs-bank-payment-gateway-for-woocommerce') . '</div>';
        }

        return '<div style="color:#990000; font-weight: bold;">' .
            __('Not available', 'resurs-bank-payment-gateway-for-woocommerce') .
            '</div>';
    }

    /**
     * Primary configuration tab
     */
    public function resursbank_settings_show()
    {
        if (!$this->canWrite()) {
            echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __(
                    'This plugin needs read/write access to the includes directory located in the path of the plugin or it will not be able to save the payment method configuration.',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ) . '</div>';

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
        $url = admin_url('admin.php');
        $url = add_query_arg('page', isset($_REQUEST['page']) ? $_REQUEST['page'] : "", $url);
        $url = add_query_arg('tab', isset($_REQUEST['tab']) ? $_REQUEST['tab'] : "", $url);
        $url = add_query_arg('section', isset($_REQUEST['section']) ? $_REQUEST['section'] : "", $url);
        $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : "";
        $namespace = $this->CONFIG_NAMESPACE;

        if (isset($_REQUEST['save'])) {
            $isResursSave = true;
            wp_safe_redirect($url);
        }

        $methodDescription = "";
        $paymentMethodsError = null;
        $class_files = [];
        $countryCredentialArray = [];
        if (isResursDemo() && class_exists("CountryHandler")) {
            $countryHandler = new CountryHandler();
            $countryList = ['se', 'no', 'dk', 'fi'];
            $countryConfig = $countryHandler->getCountryConfig();
            foreach ($countryList as $countryId) {
                if (isset($countryConfig[$countryId]) && isset($countryConfig[$countryId]['account']) && !empty($countryConfig[$countryId]['account'])) {
                    $countryCredentialArray[$countryId] = $countryConfig[$countryId]['account'];
                }
            }
        }

        $hasCountries = false;
        $loginInfo = getResursOption('login');

        try {
            if (!preg_match("/^resurs_bank_nr/i", $section) && !empty($loginInfo)) {
                // If we're in demoshop mode go another direction
                if (isResursDemo() && is_array($countryCredentialArray) && count($countryCredentialArray)) {
                    try {
                        /** @var $demoShopFlow \Resursbank\RBEcomPHP\ResursBank */
                        $demoShopFlow = initializeResursFlow();
                        $demoShopFlow->setSimplifiedPsp(true);
                        $countryBasedPaymentMethods = [];
                        if (isset($_REQUEST['reset'])) {
                            foreach ($countryCredentialArray as $countryId => $countryCredentials) {
                                delete_transient("resursMethods" . $countryId);
                            }
                        }
                        foreach ($countryCredentialArray as $countryId => $countryCredentials) {
                            if (isset($countryCredentials['login']) && isset($countryCredentials['password'])) {
                                // To unslow this part of the plugin, we'd run transient methods storage
                                if (!isset($_REQUEST['reset'])) {
                                    $countryBasedPaymentMethods[$countryId] = get_transient('resursMethods' . $countryId);
                                } else {
                                    // Let's make sure that we can clean up mistakes.
                                    $countryBasedPaymentMethods[$countryId] = [];
                                }

                                $transientTest = get_transient(
                                    'resursMethods' . $countryId,
                                    $countryBasedPaymentMethods[$countryId]
                                );

                                if (empty($countryBasedPaymentMethods[$countryId]) ||
                                    (is_array($countryBasedPaymentMethods[$countryId]) &&
                                        !count($countryBasedPaymentMethods[$countryId])) ||
                                    empty($transientTest)
                                ) {
                                    $demoShopFlow->setAuthentication(
                                        $countryCredentials['login'],
                                        $countryCredentials['password']
                                    );
                                    $countryBasedPaymentMethods[$countryId] = $demoShopFlow->getPaymentMethods(
                                        [],
                                        true
                                    );
                                    foreach ($countryBasedPaymentMethods[$countryId] as $countryObject) {
                                        $countryObject->country = $countryId;
                                    }
                                    set_transient(
                                        'resursMethods' . $countryId,
                                        $countryBasedPaymentMethods[$countryId]
                                    );
                                }
                                $this->paymentMethods = array_merge(
                                    $this->paymentMethods,
                                    $countryBasedPaymentMethods[$countryId]
                                );

                                set_transient("resursAllMethods", $this->paymentMethods);
                            }
                        }
                        $hasCountries = true;
                    } catch (\Exception $countryException) {
                        // Ignore and go on
                    }
                } else {
                    $this->paymentMethods = $this->flow->getPaymentMethods([], true);
                    rbSimpleLogging(
                        sprintf(
                            'Updated payment methods from Resurs Bank. Storing new transient. Count: %d.',
                            count($this->paymentMethods)
                        )
                    );
                    set_transient('resursTemporaryPaymentMethods', serialize($this->paymentMethods));
                }
                if (is_array($this->paymentMethods)) {
                    $idMerchant = 0;
                    foreach ($this->paymentMethods as $methodLoop) {
                        $class_files[] = sprintf('%d_%s', $idMerchant, $methodLoop->id);
                        $idMerchant++;
                    }
                }
                $this->UnusedPaymentClassesCleanup($class_files);
            } else {
                if (!empty($loginInfo)) {
                    $theMethod = preg_replace("/^resurs_bank_nr_\d_(.*?)/", '$1', $section);
                    // Make sure there is an overrider on PSP
                    $this->flow->setSimplifiedPsp(true);
                    $this->paymentMethods = $this->flow->getPaymentMethodSpecific($theMethod);
                    $methodDescription = isset($this->paymentMethods->description) ? $this->paymentMethods->description : '';
                }
            }
        } catch (Exception $e) {
            if ($this->flow->hasTimeoutException()) {
                set_transient('resurs_connection_timeout', time(), 60);
            }
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode == 401) {
                $paymentMethodsError = __('Authentication error', 'resurs-bank-payment-gateway-for-woocommerce');
            } elseif ($errorCode >= 400 && $errorCode <= 499) {
                $paymentMethodsError = __(
                    'The service can not be reached for the moment (HTTP Error ' . $errorCode . '). Please try again later.',
                    'resurs-bank-payment-gateway-for-woocommerce'
                );
            } elseif ($errorCode >= 500) {
                $paymentMethodsError = __(
                        "Unreachable service, code ",
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . $errorCode . " (" . trim($errorMessage) . ")";
            } else {
                $paymentMethodsError = "Unhandled exception from Resurs: [" . $errorCode . "] - " . $e->getMessage();
            }
        }

        $paymentMethodTypes = [
            'NONE' => __(
                'Chosen by plugin',
                'resurs-bank-payment-gateway-for-woocommerce'
            ),
        ];
        if (is_array($this->paymentMethods)) {
            foreach ($this->paymentMethods as $pMethod) {
                if ($pMethod->type === 'PAYMENT_PROVIDER') {
                    if (!isset($paymentMethodTypes[$pMethod->specificType])) {
                        $paymentMethodTypes[$pMethod->specificType] = $pMethod->specificType;
                    }
                }
            }
        } ?>
        <div class="wrap">
            <?php
            if ($section == "shopflow") {
                echo '<h1>' . __(
                        'Resurs Bank Configuration - Shop flow',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</h1>';
            } elseif ($section == "advanced") {
                echo '<h1>' . __(
                        'Resurs Bank Configuration - Advanced settings',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</h1>';
            } elseif (preg_match("/^resurs_bank_nr_/i", $section)) {
                echo '<h1>' . __(
                        'Resurs Bank Configuration',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . ' - ' . $methodDescription . ' (' . $theMethod . ')</h1>';
            } elseif ($section == "shortcodes") {
                echo '<h1>' . __(
                        'Resurs Bank Configuration - Shortcodes',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</h1>';
            } else {
                echo '<h1>' . __(
                        'Resurs Bank payment gateway configuration',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ) . '</h1>
                    v' . rbWcGwVersion() . (defined('PHP_VERSION') ? "/PHP v" . PHP_VERSION : "") . ' ' . (!empty($currentVersion) ? $currentVersion : "");
            } ?>
            <!-- Table layout auto fixes issues for woocom 3.4.0 as it has added a fixed value to it in this version -->
            <table class="form-table" style="table-layout: auto !important;">
                <?php
                if (empty($section)) {
                    echo $this->setSeparator(__('Plugin and checkout', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setCheckBox('enabled', $namespace);
                    echo $this->setHidden('title', $namespace);
                    echo $this->setDropDown('priceTaxClass', $namespace, $this->getTaxRatesArray());
                    echo $this->setCheckBox('postidreference', $namespace);
                    echo $this->setCheckBox('instant_migrations', $namespace);
                    echo $this->setSeparator(__('API Settings', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setDropDown('flowtype', $namespace);
                    echo $this->setDropDown(
                        'country',
                        $namespace,
                        null,
                        "onchange=adminResursChangeFlowByCountry(this)"
                    );
                    echo $this->setDropDown('serverEnv', $namespace);

                    echo $this->setTextBox(
                        'login',
                        $namespace,
                        'onfocus="resursClickUsername()"'
                    );
                    echo $this->setTextBox('password', $namespace); // Former callback "updateResursPaymentMethods"
                    echo $this->setSeparator(
                        __('Callbacks', 'resurs-bank-payment-gateway-for-woocommerce')
                    ); // , "configSeparateTitleSmall"

                    $callSent = get_transient("resurs_callbacks_sent");
                    $callRecv = get_transient("resurs_callbacks_received");

                    echo '<tr>
                    <th></th>
                    <td>
                    ';

                    if (callbackUpdateRequest()) {
                        echo '<div id="callbacksRequireUpdate" style="margin-top: 8px;" class="labelBoot labelBoot-warning labelBoot-big labelBoot-nofat labelBoot-center">' .
                            __(
                                'Your callbacks requires an update. The plugin will do this for you as soon as this page has is done loading...',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) .
                            '</div><br><br>';
                    }

                    echo '
                            <div class="labelBoot labelBoot-info labelBoot-big labelBoot-nofat labelBoot-center">' .
                        __(
                            'Callback URLs that is registered at Resurs Bank',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) .
                        ' ' . (
                        $this->curlInDebug ? " [" . __(
                                'Curl module is set to enter debug mode',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . "]" : ""
                        ) . '</div>
                            <div id="callbackContent" style="margin-top: 8px;">
                    ';
                    $login = getResursOption("login");
                    $password = getResursOption("password");
                    if (!empty($login) && !empty($password)) {
                        $callbackUriCacheTime = time() - get_transient("resurs_callback_templates_cache_last");
                        if ($callbackUriCacheTime >= 86400) {
                            echo '<img src="' . $this->spinner . '" border="0">';
                        } else {
                            echo '<img src="' . $this->spinnerLocal . '" border="0">';
                        }
                    }
                    echo '

                    </div>
                    <div id="callbackHealth">
                    <table cellpadding="0" cellpadding="0" style="margin-bottom: 10px;padding:0px; !important" width="100%">
                    <tr>
                    <td colspan="2" style="font-weight: bold; border-top:1px dashed gray;padding:0px !important;">
                    ' . __('Callback health', 'resurs-bank-payment-gateway-for-woocommerce') . '
                    </td>
                    </tr>
                    <tr style="vertical-align: top; padding:0px;" valign="top">
                        <td class="lastCbTableStyling" valign="top" width="20%">' . __(
                            'Last test run',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '</td>
                        <td class="lastCbTableStyling" valign="top" id="lastCbRun" width="80%">' . (
                        $callSent > 0 ? date(
                            'Y-m-d (H:i:s)',
                            $callSent
                        ) : __('Never', 'resurs-bank-payment-gateway-for-woocommerce')
                        ) . '</td>
                    </tr>
                    <tr style="vertical-align: top;padding: 0px; padding-bottom: 10px !important; margin-bottom: 5px; !important;" valign="top">
                        <td class="lastCbTableStyling" style="border-bottom: 1px dashed gray; margin-bottom: 5px; padding-bottom: 10px;" valign="top" width="20%">' . __(
                            'Responses/last successful test date+time',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '</td>
                        <td class="lastCbTableStyling" style="border-bottom: 1px dashed gray; margin-bottom: 5px; padding-bottom: 10px;" valign="top" id="lastCbRec" width="80%">' . (
                        $callRecv > 0 ? date(
                            'Y-m-d (H:i:s)',
                            $callRecv
                        ) : ''
                        ) . '</td>
                    </tr>
                    <!-- Prepared for extra tests if there are any filter based requests. -->
                    <tr id="externalCbTestBox" style="display: none;">
                    <td id="externalCbTitle">&nbsp;</td>
                    <td id="externalCbInfo">&nbsp;</td>
                    </tr>
                    </table>
                    </div>
                    <br>
                    
                    </td>
                    </tr>
                    ';

                    echo $this->setSeparator(__(
                        'Payment methods',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    )); // , "configSeparateTitleSmall"
                    echo '<tr>
                    <th scope="row">
                    </th>
                    <td id="currentResursPaymentMethods">
                    ';
                    if (!empty($loginInfo)) {
                        if (empty($paymentMethodsError)) {
                            if (!count($this->paymentMethods)) {
                                echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __(
                                        'The list of available payment methods will appear, when credentials has been entered',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ) . '</div><br><br>';
                            } else {
                                if (isResursOmni(true)) {
                                    echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center labelBoot-border">' . __(
                                            'Payment method titles/descriptions are not editable when using Resurs Checkout as they are handled by Resurs Bank, server side. Contact support if you want to do any changes',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ) . '</div><br><br>';
                                }
                            }
                        } else {
                            echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __(
                                    'The list of available payment methods is not available due to an error at Resurs Bank! See the error message below.',
                                    'resurs-bank-payment-gateway-for-woocommerce'
                                ) . '</div><br><br><div class="labelBoot labelBoot-warning labelBoot-big labelBoot-nofat labelBoot-center">' . nl2br($paymentMethodsError) . '</div>';
                        }
                    } else {
                        echo '<div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __(
                                'To activate this part of the plugin, your credentials to the web services must be entered above',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . '</div><br><br><div class="labelBoot labelBoot-warning labelBoot-big labelBoot-nofat labelBoot-center">' . nl2br($paymentMethodsError) . '</div>';
                    }

                    if (isset($this->paymentMethods['error']) && !empty($this->paymentMethods['error'])) {
                        $this->paymentMethods = [];
                    }
                    if (count($this->paymentMethods)) {
                        foreach ($this->paymentMethods as $methodArray) {
                            $curId = isset($methodArray->id) ? $methodArray->id : "";
                            $optionNamespace = "woocommerce_resurs_bank_nr_" . $curId . "_settings";
                            if (!hasResursOptionValue('enabled', $optionNamespace)) {
                                $this->resurs_settings_save("woocommerce_resurs_bank_nr_" . $curId);
                            }
                        }
                        ?>
                        <table class="wc_gateways widefat" cellspacing="0px" cellpadding="0px"
                               style="width: inherit;">
                            <thead>
                            <tr>
                                <th class="id"><?php echo __(
                                        'ID',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ) ?></th>
                                <th class="name"><?php echo __(
                                        'Name',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ) ?></th>
                                <th class="title"><?php echo __(
                                        'Checkout title',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ) ?></th>
                                <th class="annuityfactor"><?php echo __(
                                        'AnnuityFactor',
                                        'resurs-bank-payment-gateway-for-woocommerce'
                                    ) ?>
                                    <br><span
                                            style="font-weight:normal !important;font-style: italic; font-size:11px; padding: 0px; margin: 0px;"><?php echo __(
                                            'Activate/disable by clicking the X-boxes',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ); ?></span></th>

                                <?php
                                // Having special configured contries?
                                if ($hasCountries) {
                                    ?>
                                    <th class="country"><?php echo __(
                                            'Country',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ) ?></th>
                                    <?php
                                } ?>

                                <?php if (!isResursOmni(true)) {
                                    ?>
                                    <?php
                                    if (getResursFlag('FEE_EDITOR')) {
                                        ?>
                                        <th class="fee"><?php echo __(
                                                'Fee',
                                                'resurs-bank-payment-gateway-for-woocommerce'
                                            ) ?></th>
                                        <?php
                                    } ?>
                                    <th class="status"><?php echo __(
                                            'Enable/Disable',
                                            'resurs-bank-payment-gateway-for-woocommerce'
                                        ) ?></th>
                                    <?php
                                } ?>
                                <th class="process"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            $sortByDescription = [];
                            // Sort by description but make all array values unique so that descriptions
                            // do not collide.
                            foreach ($this->paymentMethods as $methodArray) {
                                // Description at this location is only used as an assoc array
                                // and should not interfere with the listview itself since the listview
                                // are showing the assoc value.
                                $description = (string)$methodArray->description . uniqid(microtime(true), true);

                                // Choose another sort order by the content.
                                $anotherValue = apply_filters(
                                    'resurs_admin_sort_methods_by_value',
                                    $description,
                                    $methodArray
                                );

                                if (!empty($anotherValue)) {
                                    $description = $anotherValue;
                                }

                                if (isResursDemo() && isset($methodArray->country)) {
                                    $description .= " [" . $methodArray->country . "]";
                                }
                                $sortByDescription[$description] = $methodArray;
                            }
                            //ksort($sortByDescription);
                            $url = admin_url('admin.php');
                            $url = add_query_arg('page', $_REQUEST['page'], $url);
                            $url = add_query_arg('tab', $_REQUEST['tab'], $url);

                            $idMerchant = 0;
                            foreach ($sortByDescription as $methodArray) {
                                $curId = isset($methodArray->id) ? $methodArray->id : "";
                                $optionNamespace = "woocommerce_resurs_bank_nr_" . $curId . "_settings";
                                if (!hasResursOptionValue('enabled', $optionNamespace)) {
                                    $this->resurs_settings_save("woocommerce_resurs_bank_nr_" . $curId);
                                }
                                write_resurs_class_to_file($methodArray, $idMerchant);
                                $idMerchant++;
                                $settingsControl = get_option($optionNamespace);
                                $isEnabled = false;
                                if (is_array($settingsControl) && count($settingsControl)) {
                                    if ($settingsControl['enabled'] == "yes" || $settingsControl == "true" || $settingsControl == "1") {
                                        $isEnabled = true;
                                    }
                                }
                                $annuityMethod = resursOption("resursAnnuityMethod");
                                $annuityDuration = resursOption("resursAnnuityDuration");

                                $maTitle = $methodArray->description;
                                // Unacceptable title set (WOO-96)
                                if (isset($settingsControl['title']) &&
                                    strtolower($settingsControl['title']) == "resurs bank"
                                ) {
                                    // Make sure this will be unset as it's detected
                                    setResursOption('title', '', $optionNamespace);
                                    $settingsControl['title'] = "";
                                }
                                if (isset($settingsControl['title']) &&
                                    !empty($settingsControl['title']) && !isResursOmni(true)
                                ) {
                                    $maTitle = $settingsControl['title'];
                                } ?>
                                <tr>
                                    <td class="id"><?php echo $methodArray->id; ?></td>
                                    <td class="name" width="300px">
                                        <?php if (!isResursOmni(true)) { ?>
                                            <a href="<?php echo $url; ?>&section=resurs_bank_nr_<?php echo $curId ?>"><?php echo $methodArray->description ?></a>
                                        <?php } else {
                                            echo $methodArray->description;
                                        } ?>
                                    </td>
                                    <td class="title" width="300px">
                                        <div style="font-size:15px;"><?php echo $maTitle ?></div>
                                        <?php
                                        if (isset($methodArray->description) && $maTitle !== $methodArray->description) {
                                            $originalTitle = '<div style="font-size: 12px; font-weight: bold;">' .
                                                __(
                                                    'Original description as stated at Resurs Bank',
                                                    'resurs-bank-payment-gateway-for-woocommerce'
                                                ) . ':' .
                                                '</div><div style="font-style: italic; font-size:11px;">' . $methodArray->description . '</div>';
                                            echo $originalTitle;
                                        } ?>
                                    </td>

                                    <td id="annuity_<?php echo $curId; ?>">
                                        <?php
                                        // Future safe if
                                        if ($methodArray->type == "REVOLVING_CREDIT" || $methodArray->specificType == "REVOLVING_CREDIT") {
                                            $scriptit = 'resursRemoveAnnuityElements(\'' . $curId . '\')'; ?>
                                            <?php if (strtolower($annuityMethod) == strtolower($curId)) {
                                                // Clickables must be separated as the selector needs to be editable?>
                                                <span class="status-enabled tips"
                                                      id="annuityClick_<?php echo $curId; ?>"
                                                      data-tip="<?php echo __('Enabled', 'woocommerce') ?>"
                                                      onclick="runResursAdminCallback('annuityToggle', '<?php echo $curId; ?>');<?php echo $scriptit; ?>">-</span>
                                                <?php
                                                $annuityFactors = null;
                                                $selector = null;
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
                                                } ?>
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
                                        } ?>
                                    </td>

                                    <?php
                                    // Having special configured contries?
                                    if ($hasCountries) {
                                        ?>
                                        <th class="country"><?php echo $methodArray->country ?></th>
                                        <?php
                                    }

                                    if (!isResursOmni(true)) { ?>
                                        <?php
                                        if (getResursFlag('FEE_EDITOR')) {
                                            ?>

                                            <td class="fee" id="fee_<?php echo $methodArray->id; ?>"
                                                onclick="changeResursFee(this)">
                                                <?php
                                                $priceValue = $this->getOptionByNamespace(
                                                    "price",
                                                    "woocommerce_resurs_bank_nr_" . $curId
                                                );
                                                if (empty($priceValue)) {
                                                    // Injecting ourselves in the current structure
                                                    $priceValue = '<img id="fim_' . $methodArray->id . '" onclick="changeResursFee(this)" src="' . plugin_dir_url(__FILE__) . 'img/pen16x.png' . '">';
                                                } else {
                                                    // Make sure that noone sets wrong values
                                                    $priceValue = preg_replace("/,/", '.', $priceValue);
                                                    $priceValue = doubleval($priceValue);
                                                }
                                                echo $priceValue; ?></td>

                                            <?php
                                        }
                                        ?>
                                        <?php if (!$isEnabled) { ?>
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
                            } ?>
                            </tbody>
                        </table>
                        <?php
                    }
                    echo '</td></tr>';

                    echo $this->getPluginInformation();
                } elseif ($section == "fraudcontrol") {
                    if (isResursOmni(true)) {
                        echo '<br><div class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center labelBoot-border">' . __(
                                'Shop flow settings are not editable when using Resurs Checkout - Contact support if you want to do any changes',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . '</div><br><br>';
                    } else {
                        $styleRecommended = "display: none";
                        $waitForFraud = getResursOption("waitForFraudControl");
                        $annulIfFrozen = getResursOption("annulIfFrozen");
                        $finalizeIfBooked = getResursOption("finalizeIfBooked");
                        if (!$waitForFraud && !$annulIfFrozen && !$finalizeIfBooked) {
                            $styleRecommended = "";
                        }
                        echo '<div id="shopwFlowRecommendedSettings" style="' . $styleRecommended . '">' . __(
                                'This section is restricted to the simplified shop flow and hosted flow only. To run the ' .
                                'best practice-configuration, you should keep your settings unchecked.',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . '</div>';
                        echo $this->setCheckBox('waitForFraudControl', $namespace, 'onchange="wfcComboControl(this)"');
                        echo $this->setCheckBox('annulIfFrozen', $namespace, 'onchange="wfcComboControl(this)"');
                        echo $this->setCheckBox('finalizeIfBooked', $namespace, 'onchange="wfcComboControl(this)"');
                    }
                } elseif ($section == "resurs_bank_omnicheckout") {
                    $namespace = "woocommerce_" . $section;
                    $this->CONFIG_NAMESPACE = $namespace;
                    echo $this->setCheckBox('enabled', $namespace);
                    echo $this->setSeparator(__('Visuals', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setTextBox('title', $namespace);
                    echo $this->setTextBox('description', $namespace);
                    echo $this->setSeparator(__('Checkout', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setDropDown('iFrameLocation', $namespace);
                    echo $this->setTextBox('iframeShape', $namespace);
                    echo $this->setTextBox('iframeTestUrl', $namespace);
                    // Setting only visible on special hostnames.
                    if (isset($_SERVER['HTTP_HOST']) && preg_match('/\.cte\.loc|\.pte\.loc/i', $_SERVER['HTTP_HOST'])) {
                        echo $this->setCheckBox('alwaysPte', $namespace);
                    }
                    echo $this->setSeparator(__('Advanced', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setCheckBox('omniFrameNotReloading', $namespace);
                    echo $this->setCheckBox('cleanOmniCustomerFields', $namespace);
                    echo $this->setCheckBox('disableStandardFieldsForShipping', $namespace);
                    echo $this->setCheckBox('resursCheckoutMultipleMethods', $namespace);
                } elseif ($section == "shortcodes") {
                    echo $this->setSeparator(
                        __(
                            'Part payment widget settings',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        )
                    );
                    $pagelist = get_pages();
                    $widgetPages = [
                        '0' => __('None (default)', 'resurs-bank-payment-gateway-for-woocommerce'),
                    ];
                    /** @var WP_Post $pages */
                    foreach ($pagelist as $page) {
                        $widgetPages[$page->ID] = $page->post_title;
                    }
                    echo $this->setDropDown(
                        'partPayWidgetPage',
                        $namespace,
                        $widgetPages
                    );

                    $shortCodeCollection = [
                        '[payFromAnnuity]' => __(
                            'Final price to pay including the currency.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        '[payFrom]' => __(
                            'Final price excluding the currency.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        '[paymentLimit]' => __(
                            'The minimum price configured from where annuities are shown (Note: When running in test, this is always set to 1 for debugging).',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        '[annuityDuration]' => __(
                            'Chosen annuity period.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        '[costOfPurchase]' => __(
                            'URL to which the cost example are shown.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        '[defaultAnnuityString]' => __(
                            'The default text that is usually shown when annuity factors are available.',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        '[annuityFactors]' => __(
                            'Printable version of available annuity factors (for debugging).',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ];

                    $shortCodeDescriptions = '<table style="padding:0px;" width="50%" cellpadding="0" cellspacing="0">';
                    foreach ($shortCodeCollection as $tag => $description) {
                        $shortCodeDescriptions .= sprintf(
                            '<tr><td style="font-weight: bold;" valign="top">%s</td><td>%s</td></tr>',
                            $tag,
                            $description
                        );
                    }
                    $shortCodeDescriptions .= '</table>';

                    printf(
                        '
                    <tr>
                    <td style="font-size:16px;">&nbsp;</td>
                    <td><b>%s</b><br>%s</td>
                    </tr>
                    ',
                        __(
                            'Available shortcodes for above view',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $shortCodeDescriptions
                    );
                } elseif ($section === "advanced") {
                    echo $this->setSeparator(__('URL Settings', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setTextBox('customCallbackUri', $namespace);
                    echo $this->setTextBox('costOfPurchaseCss', $namespace);

                    echo $this->setSeparator(__('Callbacks', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setDropDown('autoDebitStatus', $namespace);
                    echo $this->setDropDown(
                        'autoDebitMethods',
                        $namespace,
                        $paymentMethodTypes,
                        '',
                        count($paymentMethodTypes)
                    );

                    echo $this->setSeparator(
                        __('Miscellaneous callback configuration', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'configSeparateTitleSmall'
                    );
                    echo $this->setCheckBox('callbackUpdateAutomation', $namespace);
                    echo $this->setTextBox('callbackUpdateInterval', $namespace);

                    echo $this->setSeparator(
                        __('Customer and store', 'resurs-bank-payment-gateway-for-woocommerce')
                    );
                    //echo $this->setCheckBox('protectMethodList', $namespace);
                    echo $this->setCheckBox('enforceMethodList', $namespace);
                    echo $this->setTextBox('timeout_throttler', $namespace);
                    echo $this->setCheckBox('getAddress', $namespace);
                    echo $this->setCheckBox('resursvalidate', $namespace);
                    echo $this->setCheckBox('forceGovIdField', $namespace);
                    echo $this->setCheckBox('reduceOrderStock', $namespace);

                    echo $this->setSeparator(
                        __('Coupons/discount and VAT', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'configSeparateTitleSmall'
                    );
                    echo $this->setCheckBox('coupons_include_vat', $namespace);

                    echo $this->setSeparator(
                        __('Other Customer Settings', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'configSeparateTitleSmall'
                    );
                    echo $this->setCheckBox('resursOrdersEditable', $namespace);
                    echo $this->setCheckBox('showCheckoutOverlay', $namespace);
                    echo $this->setTextBox('checkoutOverlayMessage', $namespace);

                    echo $this->setSeparator(
                        __('Session handling', 'resurs-bank-payment-gateway-for-woocommerce')
                    );
                    echo $this->setCheckBox('resursbank_start_session_before', $namespace);
                    echo $this->setCheckBox('resursbank_start_session_outside_admin_only', $namespace);
                    echo $this->setSeparator(
                        __('Testing and development', 'resurs-bank-payment-gateway-for-woocommerce')
                    );
                    echo $this->setCheckBox('logResursEvents', $namespace);
                    echo $this->setCheckBox('showResursCheckoutStandardFieldsTest', $namespace);
                    echo $this->setSeparator(__('Miscellaneous', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setTextBox('credentialsMaintenanceTimeout', $namespace);
                    echo $this->setCheckBox('preventGlobalInterference', $namespace);
                    echo $this->setCheckBox('streamlineBehaviour', $namespace);
                    echo $this->setCheckBox('showPaymentIdInOrderList', $namespace);
                    echo $this->setSeparator(
                        __(
                            'Dynamic Configurables',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'configSeparateTitleSmall'
                    );
                    echo $this->setTextBox('devFlags', $namespace, 'onkeyup="devFlagsControl(this)"');
                    echo $this->setSeparator(__('Network', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo $this->setCheckBox('handleNatConnections', $namespace);
                    echo $this->setSeparator(__('Maintenance', 'resurs-bank-payment-gateway-for-woocommerce'));
                    echo '<tr><th>' . __('Clean up ', 'resurs-bank-payment-gateway-for-woocommerce') . '</th><td>';
                    echo '<input id="cleanResursSettings" type="button" value="' . __(
                            'Resurs settings',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '" onclick="runResursAdminCallback(\'cleanRbSettings\', \'cleanResursSettings\')"> <span id="process_cleanResursSettings"></span><br>';
                    echo '<input id="cleanResursMethods" type="button" value="' . __(
                            'Payment methods',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '" onclick="runResursAdminCallback(\'cleanRbMethods\', \'cleanResursMethods\')"> <span id="process_cleanResursMethods"><span><br>';
                    echo '<input id="cleanResursCache" type="button" value="' . __(
                            'Cached data',
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . '" onclick="runResursAdminCallback(\'cleanRbCache\', \'cleanResursCache\')"> <span id="process_cleanResursCache"><span>';
                    echo '</td></tr>';

                    echo $this->getPluginInformation();
                } elseif (preg_match("/^resurs_bank_nr_(.*?)$/i", $section)) {
                    if (!isResursOmni(true)) {
                        $namespace = "woocommerce_" . $section;
                        $this->CONFIG_NAMESPACE = $namespace;

                        echo $this->setCheckBox('enabled', $namespace);

                        $this->methodLabel = '<br>' . __(
                                'Default title set by Resurs Bank is ',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . '<b> ' . $methodDescription . '</b>';
                        //$curSet            = getResursOption('title', $namespace);
                        echo $this->setTextBox('title', $namespace);
                        echo $this->setTextBox('description', $namespace);
                        echo $this->setTextBox('price', $namespace);
                        echo $this->setTextBox('priceDescription', $namespace);
                        echo $this->setCheckBox('enableMethodIcon', $namespace);
                        echo $this->setTextBox('icon', $namespace);
                    } else {
                        echo "<br>";
                        echo '<div id="listUnavailable" class="labelBoot labelBoot-danger labelBoot-big labelBoot-nofat labelBoot-center">' . __(
                                'The payment method editor is not availabe while Resurs Checkout is active',
                                'resurs-bank-payment-gateway-for-woocommerce'
                            ) . '</div>';
                    }
                }
                echo $this->setSeparator(__(
                    'Save above configuration with the button below',
                    'resurs-bank-payment-gateway-for-woocommerce'
                ));

                if ($this->curlInDebug) {
                    $getDebugData = $this->flow->getDebug();
                    echo '<tr><td colspan="2">';
                    echo '<pre>';
                    if (method_exists($this->flow, 'getAutoDebitableTypes')) {
                        echo "<b>Payment methods that tend to automatically DEBIT orders as soon as money are transferred to merchant</b>\n";
                        print_r($this->flow->getAutoDebitableTypes());
                        echo '<hr>';
                    }

                    $sslUnsafe = $this->flow->getSslIsUnsafe();
                    echo __(
                            "During the URL calls, SSL certificate validation has been disabled",
                            'resurs-bank-payment-gateway-for-woocommerce'
                        ) . ": " . ($sslUnsafe ? __("Yes") : __("No")) . "\n";

                    echo '<hr>';

                    echo "<b>curlmodule debug data</b>\n";
                    print_r($getDebugData);
                    echo '</pre>';
                    echo '</td></tr>';
                } ?>


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
        $rate_select = [];
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

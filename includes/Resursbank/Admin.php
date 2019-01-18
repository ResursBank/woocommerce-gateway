<?php

/** @noinspection PhpCSValidationInspection */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration class for Resurs Bank (A legacy edition)
 *
 * This configurator follows the way how the tabs are built by WooCommerce hooks, but since
 * there has been wishes for more advanced options in the configuration, this is a try to leave
 * the standard methods to create an administrative interface for the plugin. The primary goal
 * is to make a responseive-ish-like configuration, which allows us to dynamically build a
 * configuration that lies outside the WooCommerce-config-capabilities.
 *
 * Example: To make a multilingual credential configuration there's needs to step outside the
 * regular configuration array delivered by WooCommerce. However, the compact but complex admin
 * view that we've seen in the prior Resurs Bank-configurators is an idea to try to reach here.
 *
 * Other developer notes:
 * - Try to avoid file writing in this section. Use the static method to configure payment methods.
 * - Make use of Woocommerce display filters/actions, but do not use their way to save the options
 * - Try to avoid the weird namespaces that was written in the prior Resurs module
 *
 * Class WC_Settings_ResursBank
 */
class WC_Settings_ResursBank extends WC_Settings_Page
{
    /** @var string */
    protected $id = 'resurs_bank_payment_gateway';

    /** @var string|void */
    protected $label = '';

    /** @var string */
    protected $label_image = '';

    /** @var bool */
    private $hasParentConstructor = false;

    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * WC_Settings_ResursBank constructor.
     */
    function __construct()
    {
        // We don't use sections.
        if ($this->getSection() === $this->id) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=resurs_bank_payment_gateway'));
            die;
        }

        // Initial label in cases where use the parent constructor to generate
        // the configuration structures.
        $this->label = __('Resurs Bank Payments', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
        $this->label_image = '<img src="' . _RESURSBANK_GATEWAY_URL . 'images/logo2018.png" border="0">';

        // Rewrite the label before injecting as tab.
        add_action('woocommerce_settings_' . $this->id, array($this, 'resurs_bank_settings_show'));
        add_action('woocommerce_update_options_' . $this->id, array($this, 'resurs_bank_settings_save_legacy'));

        if (Resursbank_Core::getSectionsByConstructor()) {
            // Run parent constructor after ours. This has been used as a last resort in the initial developing
            // since it first destroyed the tab setup as we wanted to have it. This is just a failover that creates
            // the tabs in a normal way, where Resurs Bank logo will be unavailable.
            $this->hasParentConstructor = true;
            parent::__construct();
        } else {
            // Basic behaviour is to call this manually and construct our tabs manually.
            add_action('woocommerce_settings_tabs', array($this, 'resurs_bank_settings_tabs'));
            add_action('woocommerce_sections_' . $this->id, array($this, 'output_sections'));
        }
    }

    /**
     * Generate plugin sections
     *
     * @return array
     */
    public function get_sections()
    {
        $sections = array();
        $tabSections = Resursbank_Core::getConfiguration();
        foreach ($tabSections as $section => $sectionArray) {
            $sections[$section] = isset($sectionArray['title']) ? $sectionArray['title'] : __(
                'Unnamed config section',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );
        }
        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    /**
     * Convert credentials set to proper array defined by country code.
     *
     * @param $shortKey
     * @param $saveValue
     * @return array
     */
    private function getCredentialsSet($shortKey, $saveValue)
    {
        $newValue = $saveValue;
        if ($shortKey === 'credentials') {
            // Reset saved array if credentials block.
            $newValue = array();
            foreach ($saveValue as $saveArray) {
                if (isset($saveArray['country']) && !empty($saveArray['country'])) {
                    if (!isset($saveArray['active'])) {
                        $saveArray['active'] = 0;
                    }
                    $newValue[$saveArray['country']] = $saveArray;
                }
            }
        }
        return $newValue;
    }

    /**
     * @return string
     */
    private function getSection()
    {
        return isset($_REQUEST['section']) ? $_REQUEST['section'] : '';
    }

    /**
     * @param $key
     * @param null $section
     * @return string
     */
    private function getTypeByKey($key, $section = null)
    {
        $return = '';
        $configurationData = Resursbank_Core::getConfiguration();

        if (isset($configurationData[$section])) {
            // Climb recursively
            $configurationData = $configurationData[$section];
        } else {
            // If someone uses this method badly, we'll have to guess.
            foreach ($configurationData as $section => $array) {
                foreach ($array['settings'] as $key => $item) {
                    $itemType = $this->getTypeByKey($key, $section);
                    if (!empty($itemType)) {
                        return $itemType;
                    }
                }
            }
        }

        if (isset($configurationData['settings'][$key]) && isset($configurationData['settings'][$key]['type'])) {
            $return = $configurationData['settings'][$key]['type'];
        }

        return $return;
    }

    /**
     * Restoring dynamic dismissed objects
     *
     * @param $configurationObject
     * @return array
     */
    private function updateUnreachableObjects($configurationObject)
    {
        $section = $this->getSection();

        if (is_array($configurationObject)) {
            foreach ($configurationObject as $item => $value) {
                if ($section === 'dismissed' && preg_match('/^dismiss_/', $item)) {
                    if (!isset($_REQUEST[$item])) {
                        $configurationObject[$item] = false;
                    }
                }

                // Make sure checkboxes are handled properly, when they are no longer enabled.
                $itemType = $this->getTypeByKey($item, $section);
                if ($itemType === 'checkbox' && !isset($_REQUEST['resursbank_' . $item])) {
                    $configurationObject[$item] = false;
                }

            }
        }

        return $configurationObject;
    }

    /**
     * Compiles default configuration values with stored data
     *
     * @return array
     */
    public function getStoredConfiguration()
    {
        $fullConfiguration = Resursbank_Core::getDefaultConfiguration();
        $storedConfiguration = Resursbank_Core::getResursOption();

        // Overwrite full configuration with stored values.
        if (is_array($storedConfiguration)) {
            foreach ($storedConfiguration as $itemKey => $itemValue) {
                $fullConfiguration[$itemKey] = $itemValue;
            }
        }

        return $fullConfiguration;
    }

    /**
     * @param $settings_section
     */
    public function resurs_bank_settings_save_legacy($settings_section)
    {
        global $wp;
        $fullConfiguration = $this->getStoredConfiguration();

        // Loop through request and overwrite with new values.
        if (isset($_REQUEST) && is_array($_REQUEST)) {
            foreach ($_REQUEST as $saveKey => $saveValue) {
                if (preg_match('/^resursbank_/', $saveKey)) {
                    $shortKey = preg_replace('/^resursbank_/', '', $saveKey);
                    $saveValue = apply_filters('resursbank_config_save_data_' . $shortKey, $saveValue);

                    // Pass the saved value through credentials detecting and convert the data if anything found.
                    $saveValue = $this->getCredentialsSet($shortKey, $saveValue);
                    $fullConfiguration[$shortKey] = ($saveValue === 'yes' || $saveValue === 'on') ? true : $saveValue;
                }
            }
        }

        // Sanitize dynamic dismissals (and checkboxes)
        $fullConfiguration = $this->updateUnreachableObjects($fullConfiguration);

        Resursbank_Core::setResursOption($fullConfiguration);
        $this->redirectDismissed($fullConfiguration);
    }

    /**
     * Redirect administrative url to a section that do exist
     * if all dismissed objects are restored.
     */
    private function redirectDismissed($fullConfiguration)
    {
        if ($this->getSection() === 'dismissed' && !$this->hasDismissedOptions($fullConfiguration)) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=resurs_bank_payment_gateway'));
        }
    }

    /**
     * @param $fullConfiguration
     * @return bool
     */
    private function hasDismissedOptions($fullConfiguration)
    {
        $return = false;
        if (is_array($fullConfiguration)) {
            foreach ($fullConfiguration as $itemKey => $itemValue) {
                if (preg_match('/^dismiss_/', $itemKey) && (bool)$itemValue) {
                    $return = true;
                    break;
                }
            }
        }
        return $return;
    }

    /**
     * Prepare tabs
     */
    public function resurs_bank_settings_tabs()
    {
        global $current_tab;

        if (!$this->hasParentConstructor) {
            echo '<a href="' . esc_html(admin_url('admin.php?page=wc-settings&tab=' . $this->id)) .
                '" class="nav-tab ' . ($current_tab === $this->id ? 'nav-tab-active' : '') . '">' .
                $this->label_image . '</a>';
        }

    }

    /**
     * Version varnings indicating obsolete plugin under 3.x (prior release)
     *
     * @return string
     */
    private function resurs_bank_version_low_text()
    {
        return __(
                'Coexisting note: This plugin has discovered a prior version of Resurs Bank Payment Gateway!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ) . ' ' .
            __(
                'This should not be a problem as they are coexistent. However, running them simultaneously is probably not what you want.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );
    }

    /**
     * Version warnings,indicating that this plugin has been merged into the prior branch and the merchant runs
     * the exact same versions side by side.
     *
     * @return string
     */
    private function resurs_bank_version_equal_text()
    {
        return sprintf(
            '%s %s',
            __(
                'Coexisting note: This plugin has discovered a similar version of Resurs Bank Payment Gateway!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ),
            __(
                'This probably means you are running on duplicate software. You should consider disabling one of them!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            )
        );
    }

    /**
     * Version warnings when plugins coexist with prio version of Resurs Bank plugin
     *
     * @return string
     */
    private function resurs_bank_version_obsolete_coexistence()
    {
        return sprintf(
            '%s<br>%s %s',
            __(
                'Coexisting note: This plugin has discovered a similar version of Resurs Bank Payment Gateway!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ),
            __(
                'This plugin has disabled the coexisting prior version of Resurs Bank for WooCommerce on demand.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ),
            __(
                'If you do not know what this is about, you might want to take a look in the configuration, where this feature can be shut off.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            )
        );
    }

    /**
     * Display any version conflicts discovered by this plugin
     */
    private function resurs_bank_version_control()
    {
        $coExDismiss = apply_filters('resursbank_config_disable_coexist_warnings', false);
        if (defined('RB_WOO_VERSION')) {
            $dismissIt = '<div style="cursor:pointer; color:#000099 !important; text-align: right;" onclick="resurs_bank_dismiss(\'#resursbank_coexist_message\')">Dismiss</div>';
            echo '<div id="resursbank_coexist_message">';
            if (!(bool)$coExDismiss) {
                echo '<hr>';
                if (!Resursbank_Core::resursObsoleteCoexistenceDisable()) {
                    if (version_compare(RB_WOO_VERSION, '3', '<')) {
                        echo '<div style="color:#990000 !important;" class="resursGatewayConfigCoexistWarning">' .
                            $this->resurs_bank_version_low_text() .
                            $dismissIt .
                            '</div>';
                    } else {
                        echo '<div style="color:#DD0000 !important;" class="resursGatewayConfigCoexistWarning">' .
                            $this->resurs_bank_version_equal_text() .
                            $dismissIt .
                            '</div>';
                    }
                } else {
                    echo '<div style="color:#DD0000 !important;" class="resursGatewayConfigCoexistWarning">' .
                        $this->resurs_bank_version_obsolete_coexistence() .
                        $dismissIt .
                        '</div>';
                }
            }
            echo '</div>';
        }
    }

    /**
     * Display any conflict with deprecated WooCommerce versions
     */
    private function woocommerce_version_control()
    {
        if (defined('WOOCOMMERCE_VERSION') &&
            version_compare(WOOCOMMERCE_VERSION, _RESURSBANK_LOWEST_WOOCOMMERCE, '<')
        ) {
            echo '<div style="color:#DD0000;background:#FECEAC; border:1px solid gray; padding:5px;">' .
                sprintf(
                    __(
                        'It seems that you run on a WooCommerce version that is lower than %s. It is recommended that you upgrade to the latest version of WooCommerce, to maintain best possible compatibility.',
                        'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                    ), _RESURSBANK_LOWEST_WOOCOMMERCE
                ) .
                '</div><hr>';
        }

    }

    /**
     * Show configuration
     */
    public function resurs_bank_settings_show()
    {
        $this->resurs_bank_version_control();
        $this->woocommerce_version_control();

        $settings = new Resursbank_Adminforms();
        $settings->setRenderedHtml();
        echo $settings->getHtml();

    }

}

return new WC_Settings_ResursBank();

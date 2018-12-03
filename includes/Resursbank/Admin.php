<?php

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
 * Class WC_ResursBank_Config
 */
class WC_Settings_ResursBank extends WC_Settings_Page
{
    protected $id = 'resurs_bank_payment_gateway';
    protected $label = '';
    protected $label_image = '';

    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * WC_ResursBank_Config constructor.
     */
    function __construct()
    {
        $this->label = __('Resurs Bank Payments', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
        $this->label_image = '<img src="' . _RESURSBANK_GATEWAY_URL . 'images/logo2018.png" border="0">';

        // Rewrite the label before injecting as tab.
        //add_action('woocommerce_settings_tabs', array($this, 'resurs_bank_settings_tabs'));
        add_action('woocommerce_settings_' . $this->id, array($this, 'resurs_bank_settings_show'));
        add_action('woocommerce_update_options_' . $this->id, array($this, 'resurs_bank_settings_save_legacy'));

        // Run parent constructor after ours.
        parent::__construct();
    }

    /**
     * Generate plugin sections
     *
     * @return array
     */
    public function get_sections()
    {
        $sections = array();
        $tabSections = Resursbank_Config::getConfigurationArray();
        foreach ($tabSections as $section => $sectionArray) {
            $sections[$section] = isset($sectionArray['title']) ? $sectionArray['title'] : __('Unnamed config section', 'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce');
        }
        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    /**
     * @param $settings_section
     */
    public function resurs_bank_settings_save_legacy($settings_section)
    {
        echo $settings_section;
        echo "<pre>";
        print_R($_REQUEST);
        die;
    }

    /**
     * Prepare tabs
     */
    public function resurs_bank_settings_tabs()
    {
        global $current_tab;
        echo '<a href="' . esc_html(admin_url('admin.php?page=wc-settings&tab=' . $this->id)) .
            '" class="nav-tab ' . ($current_tab === $this->id ? 'nav-tab-active' : '') . '">' .
            $this->label_image . '</a>';
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
        return __(
                'Coexisting note: This plugin has discovered a similar version of Resurs Bank Payment Gateway!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ) . ' ' .
            __(
                'This probably means you are running on duplicate software. You should consider disabling one of them!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            );
    }

    /**
     * Version warnings when plugins coexist with prio version of Resurs Bank plugin
     *
     * @return string
     */
    private function resurs_bank_version_obsolete_coexistence()
    {
        return __(
                'Coexisting note: This plugin has discovered a similar version of Resurs Bank Payment Gateway!',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ) . ' ' . __(
                'This plugin has decided (on demand) to disable the coexisting prior version of Resurs Bank.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
            ) . ' ' .
            __(
                'If you do not know what this is about, you might want to take a look in the configuration, where this feature can be shut off.',
                'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'

            );
    }

    /**
     * Display any version conflicts discovered by this plugin
     */
    private function resurs_bank_version_control()
    {
        if (defined('RB_WOO_VERSION')) {
            if (!Resursbank_Core::resurs_obsolete_coexistence_disable()) {
                if (version_compare(RB_WOO_VERSION, '3', '<')) {
                    echo '<div style="color:#990000;background:#FECEAC; border:1px solid gray; padding:5px;">' .
                        $this->resurs_bank_version_low_text() .
                        '</div><hr>';
                } else {
                    echo '<div style="color:#DD0000;background:#FECEAC; border:1px solid gray; padding:5px;">' .
                        $this->resurs_bank_version_equal_text() .
                        '</div><hr>';
                }
            } else {
                echo '<div style="color:#DD0000;background:#FECEAC; border:1px solid gray; padding:5px;">' .
                    $this->resurs_bank_version_obsolete_coexistence() .
                    '</div><hr>';
            }
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
                __(
                    'It seems that you run on a WooCommerce version that is lower than',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
                ) . ' ' . _RESURSBANK_LOWEST_WOOCOMMERCE . '. ' .
                __(
                    'It is recommended that you upgrade to the latest version of WooCommerce, to maintain compatibility.',
                    'tornevall-networks-resurs-bank-payment-gateway-for-woocommerce'
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

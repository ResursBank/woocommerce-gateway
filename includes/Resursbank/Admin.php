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
class WC_ResursBank_Config extends WC_Settings_Page
{
    protected $id = 'resurs_bank_payment_gateway';
    protected $label = 'Resurs Bank Payment Gateway';

    function __construct()
    {
        // Rewrite the label before injecting as tab
        $this->label = '<img src="' . _RESURSBANK_GATEWAY_URL . 'images/resurs-logo.png" border="0">';

        //add_filter('woocommerce_settings_tabs_array', array($this, "resurs_bank_settings_tabs"), 50);
        add_action('woocommerce_settings_tabs', array($this, 'resurs_bank_settings_tabs'), 10);
        add_action('woocommerce_settings_' . $this->id, array($this, 'resurs_bank_settings_show'), 10);
        add_action('woocommerce_update_options_' . $this->id, array($this, 'resurs_bank_settings_save_legacy'));
    }

    public function get_sections()
    {
        $sections['generic'] = __('Basic settings', 'WC_Payment_Gateway');

        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    public function resurs_bank_settings_save_legacy($settings_section)
    {
        echo $settings_section;
        echo "<pre>";
        print_R($_REQUEST);
        die;
    }

    public function resurs_bank_settings_tabs()
    {
        global $current_tab;
        echo '<a href="' .
            esc_html(
                admin_url('admin.php?page=wc-settings&tab=' . $this->id)
            ) . '" class="nav-tab ' .
            ($current_tab === $this->id ? 'nav-tab-active' : '') . '">' .
            $this->label . '</a>';
    }

    public function resurs_bank_settings_show()
    {
        if (defined('RB_WOO_VERSION')) {
            echo '<div style="color:#990000;background:#FECEAC; border:1px solid gray; padding:5px;">' .
                __('Awareness Note: This plugin has discovered a prior version of Resurs Bank Payment Gateway!') . ' ' .
                __('This should not be a problem as they are coexistent. However, running them simultaneously is probably not what you want.') .
                '</div><hr>';
        }


        echo '
        TEST: <input type="text" id="resurs_bank_ugg" name="resurs_bank[' . $this->id . '][\'decaf\']">
        ';
    }

}

return new WC_ResursBank_Config();

<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration class for Resurs Bank (Legacy)
 *
 * Class WC_ResursBank_Config
 */
class WC_ResursBank_Config extends WC_Settings_Page
{
    function __construct()
    {
        add_filter('woocommerce_settings_tabs_array', array($this, "resurs_settings_tab"), 50);
        //add_action('woocommerce_settings_' . $this->id, array($this, 'resursbank_settings_show'), 10);
        //add_action('woocommerce_update_options_' . $this->id, array($this, 'resurs_settings_save'));
    }

    public function get_sections()
    {
        $sections['generic'] = __('Basic settings', 'WC_Payment_Gateway');

        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    public function resurs_settings_tab($settings_tabs)
    {
        // Legacy
        /*$images = _RESURSBANK_GATEWAY_URL . "images/";
        if (Resursbank_Core::getVersionCompare('3.2.2', '<')) {
            $settings_tabs[$this->id] = '<img src="' . $images . 'resurs-logo.png">';
        } else {
            // From v3.2.2 and up, all tabs are html-escaped and can not contain images anymore
            $settings_tabs[$this->id] = 'Resurs Bank';
        }*/

        $settings_tabs[$this->id] = 'Resurs Bank Gateway';
        return $settings_tabs;
    }

}

return new WC_ResursBank_Config();

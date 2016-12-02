<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Settings_Tab_ResursBank {

	const CONFIG_NAMESPACE = "woocommerce_resurs-bank";

	/**
	 * Initialize tabs
	 */
	public function init() {
		add_filter('woocommerce_settings_tabs_array', array($this, "resurs_settings_tab"), 50);
		add_action( 'woocommerce_settings_tab_resursbank_primary', array($this, 'resursbank_tab_primary') );
	}

	/**
	 * Settings tab initializer
	 *
	 * @param $settings_tabs
	 *
	 * @return mixed
	 */
	public function resurs_settings_tab($settings_tabs) {
		$settings_tabs['tab_resursbank_primary'] = __( 'Resurs Bank Administration', 'WC_Payment_Gateway' );
		return $settings_tabs;
	}

	/**
	 * Primary configuration tab
	 */
	public function resursbank_tab_primary() {

	}
}

if (is_admin()) {
	$resursSettingsTab = new WC_Settings_Tab_ResursBank();
	$resursSettingsTab->init();
}
<?php
/*
 * Plugin Name: WooCommerce Tap Gateway
 * Description: Take payments using Tap mobile wallet service.
 * Version: 1.0.0
 * Author: Shazzad Hossain Khan
 * Requires at least: 5.4.2
 * Tested up to: 5.4.2
 * WC requires at least: 4.0.0
 * WC tested up to: 4.2.0
 * Text Domain: woocommerce-gateway-tap
 * Domain Path: /languages/
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define base file
if ( ! defined( 'WC_TAP_PLUGIN_FILE' ) ) {
	define( 'WC_TAP_PLUGIN_FILE', __FILE__ );
}

/**
 * WooCommerce missing fallback notice.
 *
 * @return string
 */
function wc_tap_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Tap requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-tap' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce version fallback notice.
 *
 * @return string
 */
function wc_tap_version_wc_notice() {
	echo '<div class="error"><p><strong>' . esc_html__( 'Tap requires mimumum WooCommerce 3.0. Please upgrade.', 'woocommerce-gateway-tap' ) . '</strong></p></div>';
}

/**
 * Intialize everything after plugins_loaded action
 */
add_action( 'plugins_loaded', 'wc_tap_init', 5 );
function wc_tap_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_tap_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		add_action( 'admin_notices', 'wc_tap_version_wc_notice' );
		return;
	}

	// Load the main plug class
	if ( ! class_exists( 'WC_Tap' ) ) {
		require dirname( __FILE__ ) . '/includes/class-wc-tap.php';
	}

	wc_tap();
}

/**
 * Plugin instance
 *
 * @return WC_Tap Main class instance.
 */
function wc_tap() {
	return WC_Tap::get_instance();
}

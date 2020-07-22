<?php
/**
 * Tap hooks
 *
 * Handle processes that happens outside of payment gateway.
 *
 * @class       WC_Tap_Hooks
 */
class WC_Tap_Debugger {

	public static function init() {
		# add_action( 'woocommerce_before_settings_checkout', array( __CLASS__, 'admin_chekout_page' ) );
	}

	public static function admin_chekout_page() {
		$client = new WC_Tap_Client();
		$wc_order = wc_get_order( 11492 );
		$create_charge = $client->get_charge( 'chg_TS012020201511x2KR2207534' );
		WC_Tap_Utils::d( $create_charge );
	}
}

<?php
/**
 * Tap hooks
 *
 * Handle processes that happens outside of payment gateway.
 *
 * @class       WC_Tap_Hooks
 */
class WC_Tap_Hooks {

	public static function init() {
		#add_action( 'woocommerce_before_settings_checkout', array( __CLASS__, 'admin_chekout_page' ) );
	}

	public static function admin_chekout_page() {
		$client = new WC_Tap_Client();
		$wc_order = wc_get_order( 11492 );
		$create_charge = $client->create_charge( 'tok_Cx8Mb1311012EgkY527871', $wc_order );
		WC_Tap_Utils::d( $create_charge );
	}
}

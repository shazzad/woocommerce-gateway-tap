<?php
/**
 * WooCommerce Tap Cron Handler.
 *
 * @class       WC_Gateway_Tap_Cron_Handler
 * @extends     WC_Gateway_Tap_Response
**/
class WC_Tap_Cron_Handler extends WC_Tap_Response {

	protected $handler_name = 'Cron';

	public function __construct() {
		add_action( $this->pending_payment_cron_hook_name, array( $this, 'update_pending_payment_status' ) );
	}
}

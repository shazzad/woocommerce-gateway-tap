<?php
/**
 * WooCommerce Tap Response.
 *
 * @class       WC_Gateway_Tap_Response
**/

class WC_Tap_Response {

	protected $handler_name = 'Checkout';

	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_completed( $order, $payment ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			wc_tap()->log(
				sprintf(
					/* translators: %s: Error message, may contain html */
					__( 'Aborting, Order # %d is already complete.', 'woocommerce-gateway-tap' ),
					$order->get_id()
				)
			);
			return true;
		}

		if ( ! $this->validate_amount( $order, $payment['amount'] ) ) {
			return new WP_Error(
				'tap_amount_error',
				__( 'Amount miss-matched', 'woocommerce-gateway-tap' )
			);
		}

		$order->add_order_note(
			sprintf(
				__( 'Payment %1$s (%2$s - %3$s) via %4$s.', 'woocommerce-gateway-tap' ),
				$payment['status'],
				$payment['response']['message'],
				$payment['response']['code'],
				$this->handler_name
			)
		);

		update_post_meta( $order->get_id(), 'Tap Api Version', $payment['api_version'] );
		update_post_meta( $order->get_id(), 'Tap Mode', $payment['live_mode'] ? 'Live' : 'Test' );
		update_post_meta( $order->get_id(), 'Tap Card', $payment['card']['first_six'] . '******' . $payment['card']['last_four'] );
		update_post_meta( $order->get_id(), 'Tap Customer Id', $payment['customer']['id'] );
		update_post_meta( $order->get_id(), 'Tap Receipt Id', $payment['receipt']['id'] );
		update_post_meta( $order->get_id(), 'Tap Payment Id', $payment['reference']['payment'] );
		update_post_meta( $order->get_id(), 'Tap Charge Id', $payment['id'] );

		$order->payment_complete( $payment['id'] );

		if ( ! is_admin() && ! wp_doing_cron() ) {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Handle a failed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_failed( $order, $payment ) {
		$order->set_transaction_id( $payment['id'] );

		$order->set_status(
			'failed',
			sprintf(
				__( 'Payment %1$s (%2$s - %3$s) via %4$s.', 'woocommerce-gateway-tap' ),
				$payment['status'],
				$payment['response']['message'],
				$payment['response']['code'],
				$this->handler_name
			)
		);

		$order->save();
	}

	/**
	 * Handle a failed payment (User input is not accepted by the underlying PG).
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_cancelled( $order, $payment ) {
		$order->set_transaction_id( $payment['id'] );

		$order->set_status(
			'cancelled',
			sprintf(
				__( 'Payment %1$s (%2$s - %3$s) via %4$s.', 'woocommerce-gateway-tap' ),
				$payment['status'],
				$payment['response']['message'],
				$payment['response']['code'],
				$this->handler_name
			)
		);

		$order->save();
	}

	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_captured( $order, $payment ) {
		return $this->tap_status_completed( $order, $payment );
	}

	/**
	 * Check payment amount from IPN matches the order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param int      $amount Amount to validate.
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) !== number_format( $amount, 2, '.', '' ) ) {
			wc_tap()->log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			/* translators: %s: Amount. */
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Tap amounts do not match (amount %s).', 'woocommerce-gateway-tap' ), $amount ) );
			return false;
		}

		return true;
	}

	protected function is_order_processing( $order_id ) {
		if ( get_transient( 'tap_processing_'. $order_id ) ) {
			return true;
		}

		return false;
	}

	protected function lock_order_process( $order_id ) {
		wc_tap()->log( 'Locking order process for ' . $order_id );
		set_transient( 'tap_processing_'. $order_id, true );
	}


	protected function unlock_order_process( $order_id ) {
		wc_tap()->log( 'Unlocking order process for ' . $order_id );
		delete_transient( 'tap_processing_'. $order_id );
	}
}

<?php
/**
 * WooCommerce Tap Response.
 *
 * @class       WC_Gateway_Tap_Response
**/

class WC_Tap_Response {

	protected $handler_name = 'Checkout';

	protected $pending_payment_cron_hook_name = 'tap_update_pending_payment_status';

	protected $pending_payment_cron_delays = array(
		1 => 30,
		2 => 300,
		3 => 300,
		4 => 3000,
		5 => 3000,
	);

	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_completed( $order, $payment ) {
		$this->update_tap_payment_data( $order->get_id(), $payment );

		// Unschedule event.
		$timestamp = wp_next_scheduled( $this->pending_payment_cron_hook_name, array( $order->get_id() ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->pending_payment_cron_hook_name, array( $order->get_id() ) );
		}

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
	public function tap_status_initiated( $order, $payment ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			wc_tap()->log( 'Aborting, Order #' . $order->get_id() . ' is already complete.' );
			return;
		}

		$order_id = $order->get_id();

		// Maximum number of pending status reached.
		if ( count( $this->pending_payment_cron_delays ) === (int) get_post_meta( $order_id, '_tap_pending_checked', true ) ) {
			$this->tap_status_failed(
				$order,
				array(
					'response' => array(
						'code' => 'XXX',
						'message' => __( 'Failed (Maximum number of pending payment response received).' )
					)
				)
			);

			return;
		}

		$this->update_tap_payment_data( $order->get_id(), $payment );

		$order->set_transaction_id( $payment['id'] );

		$order->set_status(
			'on-hold',
			sprintf(
				__( 'Payment %1$s (%2$s - %3$s) via %4$s.', 'woocommerce-gateway-tap' ),
				$payment['status'],
				$payment['response']['message'],
				$payment['response']['code'],
				$this->handler_name
			)
		);

		$order->save();

		if ( ! is_admin() && ! wp_doing_cron() ) {
			WC()->cart->empty_cart();
		}

		$pending_checked = (int) get_post_meta( $order_id, '_tap_pending_checked', true );
		if ( ! $pending_checked ) {
			$pending_checked = 1;
		}
		$cron_delay = $this->pending_payment_cron_delays[ $pending_checked ];

		if ( ! wp_next_scheduled( $this->pending_payment_cron_hook_name, array( $order_id ) ) ) {
			wc_tap()->log( 'Scheduling cronjob to update tap payment status for order # ' . $order_id );
			wp_schedule_single_event( time() + $cron_delay, $this->pending_payment_cron_hook_name, array( $order_id ) );

			update_post_meta( $order_id, '_tap_pending_checked', $pending_checked + 1 );
		} else {
			wc_tap()->log( 'Cronjob already scheduled to update tap payment status for order # ' . $order_id );
		}
	}

	/**
	 * Handle a failed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_failed( $order, $payment ) {
		$this->update_tap_payment_data( $order->get_id(), $payment );

		$order->update_status(
			'failed',
			sprintf(
				__( 'Payment %1$s (%2$s - %3$s) via %4$s.', 'woocommerce-gateway-tap' ),
				$payment['status'],
				$payment['response']['message'],
				$payment['response']['code'],
				$this->handler_name
			)
		);
	}

	/**
	 * Handle a failed payment (User input is not accepted by the underlying PG).
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $payment Tap Order object
	 */
	public function tap_status_cancelled( $order, $payment ) {
		$this->update_tap_payment_data( $order->get_id(), $payment );

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


	public function update_pending_payment_status( $order_id ) {
		wc_tap()->log( 'Updating payment status' );

		$order  = wc_get_order( $order_id );
		if ( ! $order->get_id() || 'tap' !== $order->get_payment_method() ) {
			return false;
		}

		// if already processing through other call.
		if ( $this->is_order_processing( $order_id ) ) {
			return false;
		}

		// put a lock for one minute.
		$this->lock_order_process( $order_id );

		$payment_id = $order->get_transaction_id();
		if ( ! $payment_id && $this->get_tap_payment_data( $order_id ) ) {
			$payment_data = $this->get_tap_payment_data( $order_id );
			$payment_id = isset( $payment_data['id'] ) ? $payment_data['id'] : 0;
		}

		if ( ! $payment_id ) {
			wc_tap()->log(
				sprintf(
					/* translators: %s: Error message. */
					__( 'Payment id not found. Can not processed order # %d .', 'woocommerce-gateway-tap' ),
					$order_id
				)
			);

			$this->unlock_order_process( $order_id );
			return;
		}

		$client = new WC_Tap_Client();
		$payment = $client->get_charge( $order->get_transaction_id() );

		wc_tap()->log( print_r( $payment, true ) );

		if ( is_wp_error( $payment ) ) {
			wc_tap()->log(
				sprintf(
					/* translators: %s: Error message, may contain html */
					__( 'Tap API Error: %s', 'woocommerce-gateway-tap' ),
					$payment->get_error_message()
				)
			);

			$this->unlock_order_process( $order_id );

			return;
		}

		if ( 'INITIATED' === $payment['status'] ) {
			$this->tap_status_initiated( $order, $payment );

		} elseif ( 'CAPTURED' === $payment['status'] ) {
			$this->tap_status_captured( $order, $payment );

		} elseif ( 'CANCELLED' === $payment['status'] ) {
			$this->tap_status_cancelled( $order, $payment );

		} else {
			$this->tap_status_failed( $order, $payment );
		}

		$this->unlock_order_process( $order_id );
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

	public function update_tap_payment_data( $order_id, $data ) {
		if ( isset( $data['id'] ) ) {
			update_post_meta( $order_id, '_tap_payment_data', $data );
		}
	}

	public function get_tap_payment_data( $order_id ) {
		return get_post_meta( $order_id, '_tap_payment_data', true );
	}

	public function is_order_processing( $order_id ) {
		if ( get_transient( 'tap_processing_'. $order_id ) ) {
			return true;
		}

		return false;
	}

	public function lock_order_process( $order_id ) {
		wc_tap()->log( 'Locking order process for ' . $order_id );
		set_transient( 'tap_processing_'. $order_id, true );
	}

	public function unlock_order_process( $order_id ) {
		wc_tap()->log( 'Unlocking order process for ' . $order_id );
		delete_transient( 'tap_processing_'. $order_id );
	}
}

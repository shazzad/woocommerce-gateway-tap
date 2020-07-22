<?php
/**
 * WooCommerce Tap Thankyou Handler.
 *
 * @class       WC_Tap_Thankyou_Handler
 * @extends     WC_Gateway_Tap_Response
**/
class WC_Tap_Thankyou_Handler extends WC_Tap_Response {

	protected $handler_name = 'Thankyou';

	public function __construct() {
		add_action( 'woocommerce_before_thankyou', array( $this, 'before_thankyou' ) );
	}

	public function before_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order->get_id() || 'tap' !== $order->get_payment_method() ) {
			return;
		}

		// Re fetch tap payment data.
		if ( 'on-hold' === $order->get_status() ) {
			$this->update_pending_payment_status( $order_id );
		}

		// Again fetch order data incase something changed
		$order = wc_get_order( $order_id );

		if ( 'on-hold' === $order->get_status() ) {
			$payment_url = get_post_meta( $order_id, '_tap_payment_url', true );
			$payment_url_expires = get_post_meta( $order_id, '_tap_payment_url_expires', true );

			if ( $payment_url && $payment_url_expires && time() < $payment_url_expires ) {
				?>
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
					<?php
					printf(
						__( 'Payment Incomplete. Please click here to %1$sComplete Payment%2$s', 'woocommerce-gateway-tap' ),
						'<a href="' . esc_url( $payment_url ) . '" class="button pay">',
						'</a>'
					)
					?>
				</p>
				<?php
			}

		} elseif ( 'failed' === $order->get_status() ) {
			$payment_data = get_post_meta( $order_id, '_tap_payment_data', true );
			#print_r($payment_data);
			if ( ! empty( $payment_data ) && ! empty( $payment_data['response'] ) && ! empty( $payment_data['response']['message'] ) ) {
				?>
				<p class="woocommerce-notice woocommerce-notice--error tap-thankyou-order-failed-reason">
					<?php
					printf(
						__( 'Payment Failed, Response Received From Tap: <strong>%s</strong>', 'woocommerce-gateway-tap' ),
						$payment_data['response']['message']
					)
					?>
				</p>
				<?php
			}
		}
	}
}

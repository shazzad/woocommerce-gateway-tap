<?php
/**
 * WooCommerce Tap Payment Gateway.
 *
 * @class       WC_Gateway_Tap
 * @extends     WC_Payment_Gateway
**/
class WC_Gateway_Tap extends WC_Payment_Gateway {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the form fields.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );

		// Display a message if staging environment is used.
		if ( 'test' == $this->get_option( 'environment' ) ) {
			$this->description .= '<p style="color:red; font-size: 18px; margin: 20px 0 10px;">';
			$this->description .= __( 'TEST MODE', 'woocommerce-gateway-tap' );
			$this->description .= '</p>';
			$this->description .= '<p style="padding: 10px; border: 1px solid red; font-size: 12px; line-height: 18px;">';
			$this->description .= __( 'Use 5111111111111118, 4012000033330026 or 371449635398431 as card number.', 'woocommerce-gateway-tap' );
			$this->description .= '</p>';
			$this->description  = trim( $this->description );
		}

		add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_footer', array( $this, 'render_modal_card_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
		/*
		if ( ! $this->is_available() ) {
			$this->enabled = 'no';
		}
		*/
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'tap';
		$this->has_fields         = true;
		$this->order_button_text  = __( 'Pay with Tap', 'woocommerce-gateway-tap' );
		$this->method_title       = __( 'Tap', 'woocommerce-gateway-tap');
		$this->method_description = __( 'Tap payment gateway for WooCommerce.', 'woocommerce-gateway-tap' );
		$this->supports           = array( 'products' );
		$this->icon               = WC_TAP_URL . '/assets/images/tap-logo.png';
	}

	/**
	 * Check if the payment gateway is available to be used.
	 *
	 */
	public function is_available() {
		$client = new WC_Tap_Client();
		if ( ! $client->is_available() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Return whether or not this gateway still requires setup to function.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		$client = new WC_Tap_Client();
		return ! $client->is_available();
	}

	/**
	 * Process payment
	 *
	 * @param  $order_id Order id.
	 * @return bool|array
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$client = new WC_Tap_Client();

		if ( empty( $_POST['tap_token_value'] ) ) {
			return array(
				'tap'	   => 'request_payment',
				'result'   => 'success',
				'messages' => '<div class="woocommerce-error">Enter card details</div>',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		wc_tap()->log( 'Tap confirming payment with Token - '. WC()->checkout->get_value( 'tap_token_value' ) );

		$create_charge = $client->create_charge(
			WC()->checkout->get_value( 'tap_token_value' ),
			$order
		);

		if ( is_wp_error( $create_charge ) ) {
			wc_tap()->log(
				sprintf(
					/* translators: %s: Error message, may contain html */
					__( 'Tap API Error: %s', 'woocommerce-gateway-tap' ),
					$create_charge->get_error_message()
				)
			);

			$error = $create_charge->get_error_message();

			if ( ! in_array( $create_charge->get_error_code(), array( 'api_error', 'customer_error', 'internal_error', 'tap_error' ) ) ) {
				$error = __( 'Could not process your request. Please try later, or use other payment gateway.', 'woocommerce-gateway-tap' );
			}

			return array(
				'result'   => 'success',
				'messages' => '<div class="woocommerce-error">' . $error . '</div>',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		update_post_meta( $order->get_id(), 'Tap Api Version', wc_clean( $create_charge['api_version'] ) );
		update_post_meta( $order->get_id(), 'Tap Mode', $create_charge['live_mode'] ? 'Live' : 'Test' );
		update_post_meta( $order->get_id(), 'Tap Status', wc_clean( $create_charge['status'] ) );
		update_post_meta( $order->get_id(), 'Tap Card', wc_clean( $create_charge['card']['first_six'] ) . '******' . wc_clean( $payment['card']['last_four'] ) );
		update_post_meta( $order->get_id(), 'Tap Customer Id', wc_clean( $create_charge['customer']['id'] ) );
		update_post_meta( $order->get_id(), 'Tap Receipt Id', wc_clean( $create_charge['receipt']['id'] ) );
		update_post_meta( $order->get_id(), 'Tap Payment Id', wc_clean( $create_charge['reference']['payment'] ) );
		update_post_meta( $order->get_id(), 'Tap Charge Id', wc_clean( $create_charge['id'] ) );

		$order->payment_complete( $create_charge['id'] );

		return array(
			'tap'	   => 'payment_confirmed',
			'result'   => 'success',
			'messages' => '<div class="woocommerce-info">' . __( 'Payment Completed.', 'woocommerce-gateway-tap' ) . '</div>',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) );
		}

		echo '<input type="hidden" id="tap-token-value" name="tap_token_value" value="" />';
	}

	/**
	 * Intialize form fields
	 *
	 */
	public function init_form_fields() {
		$this->form_fields = include WC_TAP_DIR . 'includes/settings-tap.php';
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs styles/scripts used for tap payment
	 *
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		// If Tap is not available, bail.
		if ( ! $this->is_available() ) {
			return;
		}

		wp_register_script(
			'bluebird',
			'https://cdnjs.cloudflare.com/ajax/libs/bluebird/3.3.4/bluebird.min.js',
			array(),
			'3.3.4',
			true
		);
		wp_register_script(
			'gosell-tap',
			'https://secure.gosell.io/js/sdk/tap.min.js',
			array(),
			false,
			true
		);
		wp_register_script(
			'tap-checkout',
			WC_TAP_URL . 'assets/js/checkout.js',
			array( 'jquery', 'bluebird', 'gosell-tap' ),
			WC_TAP_VERSION,
			true
		);

		wp_register_style(
			'tap-frontend',
			WC_TAP_URL . '/assets/css/frontend.css',
			array(),
			WC_TAP_VERSION
		);

		wp_localize_script(
			'tap-checkout',
			'tap',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'orderKey'          => isset( $_GET['key'] ) ? wp_unslash( $_GET['key'] ) : '',
				'publishableApiKey' => $this->get_option( $this->get_option( 'environment' ) . '_publishable_api_key' ),
				'paymentOptions'    => array(
			        'currencyCode'     => array(
						get_woocommerce_currency()
					),
			        'labels'           => array(
			          'cardNumber'     => __( 'Card Number', 'woocommerce-gateway-tap' ),
			          'expirationDate' => __( 'MM/YY', 'woocommerce-gateway-tap' ),
			          'cvv'            => __( 'CVV', 'woocommerce-gateway-tap' ),
			          'cardHolder'     => __( 'Card Holder Name', 'woocommerce-gateway-tap' )
				  	),
			        'TextDirection'    => is_rtl() ? 'rtl' : 'ltr'
			    ),
				'style' => array(
			        'base' => array(
						'color'         => '#535353',
						'lineHeight'    => '18px',
						'fontFamily'    => 'sans-serif',
						'fontSmoothing' => 'antialiased',
						'fontSize'      => '16px',
						'::placeholder' => array(
							'color'    => 'rgba(0, 0, 0, 0.26)',
							'fontSize' => '15px'
						)
			        ),
			        'invalid' => array(
			          'color' => 'red'
			        )
				)
			)
		);

		wp_enqueue_script( 'tap-checkout' );
		wp_enqueue_style( 'tap-frontend' );
	}

	/**
	 * Admin Panel Options.
	 * - Options for bits like 'title' and availability on a country-by-country basis.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error">
				<p>
					<strong><?php esc_html_e( 'Gateway disabled', 'woocommerce-gateway-tap' ); ?></strong>: <?php esc_html_e( 'Tap does not support your store currency.', 'woocommerce' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check if this gateway is available in the user's country based on currency.
	 *
	 * @see https://tappayments.api-docs.io/2.0/references/currency-codes
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array(
			get_woocommerce_currency(),
			apply_filters(
				'tap_supported_currencies',
				array( 'AED', 'BHD', 'EGP', 'EUR', 'GBP', 'KWD', 'OMR', 'QAR', 'SAR', 'USD' )
			),
			true
		);
	}

	/**
	 * Load admin scripts.
	 *
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( 'woocommerce_page_wc-settings' !== $screen_id ) {
			return;
		}

		wp_enqueue_script(
			'woocommerce_tap_admin',
			WC_TAP_URL . '/assets/js/settings.js',
			array( 'jquery' ),
			WC_TAP_VERSION,
			true
		);
	}

	public function render_modal_card_form() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! isset( $available_gateways[ $this->id ] ) ) {
			return;
		}

		?>
		<div id="tap-card-modal" class="tap-modal">
			<div class="modal-wrap">
				<div class="modal-inner">
					<div class="modal-logo"><img src="<?php echo  WC_TAP_URL . '/assets/images/tap-logo.png'; ?>" /></div>
					<div class="modal-heading"><?php _e( 'Pay With Card' ); ?></div>
					<div class="modal-notice"></div>
					<div id="tap-card-form-container"></div><!-- Tap element will be here -->
					<div class="form-actions">
						<button class="submit-card-btn" type="button" data-text="<?php esc_attr_e( 'Submit', 'woocommerce-gateway-tap' ); ?>" data-loading="<?php esc_attr_e( 'Submitting', 'woocommerce-gateway-tap' ); ?>"><?php esc_html_e( 'Submit', 'woocommerce-gateway-tap' ); ?></button>
						<button class="modal-close-btn" type="button"><?php esc_html_e( 'Cancel', 'woocommerce-gateway-tap' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

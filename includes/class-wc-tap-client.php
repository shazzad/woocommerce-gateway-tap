<?php
/**
 * WooCommerce Tap Api Client.
 *
 * Handle outbound requests to Tap API
 *
 * @class       WC_Tap_Client
 */
class WC_Tap_Client {

	/**
	 * Mock data, ignore sending request to tap.
	 * @var bool
	 */
	const MOCK_DATA = false;

	/**
	 * Available api environments
	 * @var [type]
	 */
	public static $environments = array(
		'test'    => array(
			'name'     => 'Test',
		),
		'production' => array(
			'name'     => 'Production',
		),
	);

	/**
	 * Api errors
	 * @var array
	 */
	protected $errors;

	/**
	 * Current api environment in used.
	 * @var string
	 */
	protected $environment;

	/**
	 * Current api endpoint.
	 * @var string
	 */
	protected $endpoint = 'https://api.tap.company/v2';

	/**
	 * SSL certificate file used for api request.
	 * @var string
	 */
	protected $secret_api_key;

	/**
	 * SSL certificate key file used for api request.
	 * @var string
	 */
	protected $publishable_api_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->environment         = $this->get_option( 'environment', 'test' );
		$this->secret_api_key      = $this->get_option( $this->environment . '_secret_api_key' );
		$this->publishable_api_key = $this->get_option( $this->environment . '_publishable_api_key' );

		# $this->init_error_codes();
	}

	/**
	 * Check if client is available to be used.
	 *
	 * @return boolean True if available.
	 */
	public function is_available() {
		return $this->secret_api_key && $this->publishable_api_key;
	}

	/**
	 * Get option value.
	 *
	 * @return mixed Option value.
	 */
	public function get_option( $name, $default = '' ) {
		$options = get_option( 'woocommerce_tap_settings', array() );
		if ( array_key_exists( $name, $options ) ) {
			return $options[ $name ];
		}

		return $default;
	}

	/**
	 * Get the Tap request URL for an order.
	 *
	 * @param  string $token Charge token generated from tokens endpoint for card.
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function create_charge( $token, $order ) {
		$description = sprintf(
			'Total %s charged for %d items, including shipping cost %s & tax %s',
			$order->get_total(),
			$order->get_item_count(),
			$order->get_shipping_total(),
			$order->get_total_tax()
		);

		$country_code = WC()->countries->get_country_calling_code( $order->get_billing_country() );
		$phone_number = preg_replace( '/[^0-9]/i', '', $order->get_billing_phone() );
		if ( $country_code === substr( $phone_number, 0, strlen( $country_code ) ) ) {
			$phone_number = substr( $phone_number, strlen( $country_code ) );
		}

		$threed_secure = false;
		if ( in_array( $order->get_billing_country(), array( 'SA' ) ) ) {
			$threed_secure = true;
		}

		$customer = array(
			'first_name'  => $order->get_billing_first_name(),
			'last_name'   => $order->get_billing_last_name(),
			'email'       => $order->get_billing_email(),
			'phone'       => array(
				'country_code' => $country_code,
				'number'       => $phone_number
			)
		);

		$data = array(
			'amount'               => $order->get_total(),
			'currency'             => $order->get_currency(),
			'threeDSecure'         => $threed_secure,
			'save_card'            => false,
			'description'          => $description,
			'reference' => array(
				'order' => $order->get_id()
			),
			'receipt' => array(
				'email' => false,
				'sms' => false
			),
			'customer' => $customer,
			'source' => array(
				'id' => $token
			),
			'redirect' => array(
				'url' => $order->get_checkout_order_received_url()
			)
		);

		$response = $this->request( 'charges', 'POST', $data );

		// Tap 3d secutiry is complicated.
		$second_request = false;
		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data( 'api_error' );
			wc_tap()->log( wc_print_r( $error_data, true ) );

			if ( isset( $error_data['code'] ) && 1109 === $error_data['code'] ) {
				$second_request = true;
			}

		} elseif ( isset( $response['response'] ) && isset( $response['response']['code'] ) && 504 === absint( $response['response']['code'] ) ) {
			$second_request = true;
		}

		if ( $second_request ) {
			$data['threeDSecure'] = ! $threed_secure;
			$response = $this->request( 'charges', 'POST', $data );
		}

		return $response;
	}

	/**
	 * Get order information from api
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function get_charge( $charge_id ) {
		return $this->request( "charges/{$charge_id}" );
	}

	/**
	 * Send API request to tap
	 *
	 * @param  string $path Path of the request object
	 * @return string
	 */
	private function request( $path = '', $method = 'get', $data = array() ) {
		$method = strtoupper( $method );

		wc_tap()->log( 'Tap API ' . $method . ' : ' . $path );

		if ( $method === 'POST' ) {
			wc_tap()->log( wc_print_r( $data, true ) );
		}

		$url = $this->endpoint . '/' . $path;

		$args = array(
			'timeout' => 60,
			'sslverify' => false,
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_api_key
			)
		);

		if ( ! empty( $data ) ) {
			$args['body'] = json_encode( $data );
		}
		#WC_Tap_Utils::d( $url );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Body comes as empty when merchant id or ssl certificate mismatch.
		if ( empty( $body ) ) {
			return new WP_Error( 'config_error', __( 'Invalid merchant id or ssl certificates.', 'woocommerce-gateway-tap' ) );
		}

		// Decode json data.
		$data = json_decode( $body, true );

		// Code other than 200 is unacceptable.
		if ( 200 !== $code ) {
			if ( isset( $data['status'] ) && 'fail' === $data['status'] ) {
				return new WP_Error( 'api_error', $data['message'], array( 'type' => (string) $data['type'] ) );

			} elseif ( isset( $data['errors'] ) ) {
				$wp_error = new WP_Error();
				foreach ( $data['errors'] as $error ) {
					$wp_error->add( 'api_error', $error['description'], array( 'code' => (int) $error['code'] ) );
				}

				return $wp_error;
			} else {
				return new WP_Error( 'api_error_unknown', __( 'API Error', 'woocommerce-gateway-tap' ) );
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'unknown_api_response', __( 'Unknown Response.', 'woocommerce-gateway-tap' ) );
		}

		return $data;
	}
}

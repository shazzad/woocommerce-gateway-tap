<?php
/**
 * WooCommerce Tap Plugin.
 *
 * @class       WC_Tap
 **/
final class WC_Tap {
	/**
	 * @var plugin name
	 */
	public $name = 'WooCommerce Gateway Tap';

	/**
	 * @var plugin version
	 */
	public $version = '0.0.1';

	/**
	 * @var Singleton The reference the *Singleton* instance of this class
	 */
	protected static $_instance = null;

	/**
	 * @var plugin settings
	 */
	protected static $settings = null;

	public static $log = false;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup() {}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->initialize();
		$this->register_hooks();
	}

	/**
	 * Define constants
	 */
	private function define_constants() {
		define( 'WC_TAP_DIR', plugin_dir_path( WC_TAP_PLUGIN_FILE ) );
		define( 'WC_TAP_URL', plugin_dir_url( WC_TAP_PLUGIN_FILE ) );
		define( 'WC_TAP_BASENAME', plugin_basename( WC_TAP_PLUGIN_FILE ) );
		define( 'WC_TAP_VERSION', $this->version );
		define( 'WC_TAP_NAME', $this->name );
	}

	/**
	 * Initialize plugin.
	 */
	private function initialize() {
		if ( is_null( self::$settings ) ) {
			self::$settings = get_option( 'woocommerce_tap_settings', array() );
		}

		WC_Tap_Hooks::init();
	}

	/**
	 * Include plugin dependency files
	 */
	private function includes() {
		require WC_TAP_DIR . '/includes/class-wc-tap-utils.php';
		require WC_TAP_DIR . '/includes/class-wc-gateway-tap.php';
		require WC_TAP_DIR . '/includes/class-wc-tap-client.php';
		require WC_TAP_DIR . '/includes/class-wc-tap-hooks.php';
	}

	/**
	 * Register hooks
	 */
	private function register_hooks() {
		add_action( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ), 0 );
		add_action( 'init', array( $this, 'load_plugin_translations' ) );
		add_filter( 'plugin_action_links_' . WC_TAP_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add the gateways to WooCommerce.
	 */
	public function add_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Tap';
		return $methods;
	}

	/**
	 * Load plugin translation file
	 */
	public function load_plugin_translations() {
		load_plugin_textdomain(
			'woocommerce-gateway-tap',
			false,
			basename( dirname( WC_TAP_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Adds plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$links['settings'] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tap' ) . '">' . __( 'Settings', 'tap' ) . '</a>';
		return $links;
	}

	/**
	 * Get option from plugin settings
	 */
	public static function get_option( $name, $default = null ) {
		if ( ! empty( self::$settings ) ) {
			if ( array_key_exists( $name, self::$settings ) ) {
				return self::$settings[ $name ];
			}
		}

		return $default;
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( 'yes' === self::get_option( 'debug', 'yes' ) ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}

			self::$log->log( $level, $message, array( 'source' => 'tap' ) );
		}
	}
}

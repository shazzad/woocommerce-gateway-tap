<?php
/**
 * Settings for Tap Gateway.
 *
**/

defined( 'ABSPATH' ) || exit;

$environments = array();
$environment_fileds = array();

foreach ( WC_Tap_Client::$environments as $environment_id => $environment ) {
	$environments[ $environment_id ] = $environment['name'];

	$environment_fileds[ $environment_id . '_secret_api_key' ] = array(
		'title'       => sprintf(
			/* translators: %s: Gateway Environment - Staging, Production */
			__( '%s Secret API Key:', 'woocommerce-gateway-tap' ),
			$environment['name']
		),
		'type'        => 'text',
		'description' => __( 'secret api key.', 'woocommerce-gateway-tap' ),
		'desc_tip'    => true
	);
	$environment_fileds[ $environment_id . '_publishable_api_key'] = array(
		'title'       => sprintf(
			/* translators: %s: Gateway Environment - Staging, Production */
			__( '%s Publishable API Key:', 'woocommerce-gateway-tap' ),
			$environment['name']
		),
		'type'        => 'text',
		'description' => __( 'public api key.', 'woocommerce-gateway-tap' ),
		'desc_tip'    => true
	);
}

return array_merge(
	array(
		'enabled' => array(
			'title'       => __( 'Enable:', 'woocommerce-gateway-tap' ),
			'type'        => 'checkbox',
			'label'       => ' ',
			'description' => __( 'If you do not already have Tap merchant account, <a href="https://tap.com.sa/" target="_blank">please register one</a>.', 'tap' ),
			'default'     => 'no',
			'desc_tip'    => true
		),
		'title' => array(
			'title'       => __( 'Title:', 'woocommerce-gateway-tap' ),
			'type'        => 'text',
			'description' => __( 'Title of Tap Payment Gateway that users see on Checkout page.', 'woocommerce-gateway-tap' ),
			'default'     => __( 'Tap', 'woocommerce-gateway-tap' ),
			'desc_tip'    => true
		),
		'description' => array(
			'title'       => __( 'Description:', 'woocommerce-gateway-tap' ),
			'type'        => 'textarea',
			'description' => __( 'Description of Tap Payment Gateway that users sees on Checkout page.', 'woocommerce-gateway-tap' ),
			'default'     => __( 'Pay securely by Tap wallet.', 'woocommerce-gateway-tap' ),
			'desc_tip'    => true
		),
		'advanced_settings' => array(
			'title' => __( 'Advanced options', 'woocommerce-gateway-tap' ),
			'type'  => 'title'
		),
		'debug' => array(
			'title'       => __( 'Debug log', 'woocommerce-gateway-tap' ),
			'type'        => 'checkbox',
			'label'       => 'Enable logging',
			'description' => sprintf(
				/* translators: %1$s: Login file path. %2$s: Login file url. */
				__( 'Log Tap events, such as Webhook requests, inside %1$s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished. <a href="%2$s">View logs here</a>', 'woocommerce-gateway-tap' ),
				'<code>' . WC_Log_Handler_File::get_log_file_path( 'tap' ) . '</code>',
				admin_url( 'admin.php?page=wc-status&tab=logs' )
			),
			'default'     => 'no',
		),
		'api_details' => array(
			'title' => __( 'API Settings', 'woocommerce-gateway-tap' ),
			'type'  => 'title',
		),
		'environment' => array(
			'title'   => __( 'Environment', 'woocommerce-gateway-tap' ),
			'type'    => 'select',
			'default' => 'staging',
			'options' => $environments
		)
	),
	$environment_fileds
 );

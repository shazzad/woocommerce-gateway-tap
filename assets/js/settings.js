jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Tap admin functions.
	 */
	var wc_tap_admin = {
		getEnvironment: function() {
			return $( '#woocommerce_tap_environment' ).val();
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_tap_environment', function() {
				var environment = $( '#woocommerce_tap_environment' ).val(),
					test_secret_api_key = $( '#woocommerce_tap_test_secret_api_key' ).parents( 'tr' ).eq( 0 ),
          test_publishable_api_key = $( '#woocommerce_tap_test_publishable_api_key' ).parents( 'tr' ).eq( 0 ),
					production_secret_api_key = $( '#woocommerce_tap_production_secret_api_key' ).parents( 'tr' ).eq( 0 ),
					production_publishable_api_key = $( '#woocommerce_tap_production_publishable_api_key' ).parents( 'tr' ).eq( 0 );

				if ( 'test' === environment ) {
					test_secret_api_key.show();
          test_publishable_api_key.show();

					production_secret_api_key.hide();
          production_publishable_api_key.hide();
				} else {
          production_secret_api_key.show();
          production_publishable_api_key.show();

          test_secret_api_key.hide();
          test_publishable_api_key.hide();
				}
			});

			$( '#woocommerce_tap_environment' ).change();
		}
	};

	wc_tap_admin.init();
});

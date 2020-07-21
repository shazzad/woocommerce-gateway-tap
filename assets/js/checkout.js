(function($){
	'use strict';

  var tap_checkout = {
    $checkout_form: $( 'form.checkout' ),
    $orderpay_form: $( 'form#order_review' ),
    tapjsli: null,
    card: null,
    getSelectedPaymentMethod: function(){
      return $( '.woocommerce input[name="payment_method"]:checked' ).val();
    },
    addCardError: function(error){
      /*
      if (this.isOrderPayPage()) {
        $( '.woocommerce-notices-wrapper' ).html( '<div class="woocommerce-error tap-card-error">' + error + '</div>' );
      } else {
        if ( $( '.woocommerce-NoticeGroup-checkout .tap-card-error' ).length > 0 ) {
          $( '.woocommerce-NoticeGroup-checkout .tap-card-error' ).html( error );
        } else {
          $( '.woocommerce-NoticeGroup-checkout' ).append( '<div class="woocommerce-error tap-card-error">' + error + '</div>' );
        }
      }*/
     $( '#tap-card-notice' ).html('<div class="tap-error">' + error + '</div>').show();
    },
    removeCardError: function(){
      //$( '.woocommerce-NoticeGroup-checkout .tap-card-error' ).remove();
      $( '#tap-card-notice' ).empty().hide();
    },
    isCardFormLoading: function() {
      if (this.isOrderPayPage()) {
        return tap_checkout.$orderpay_form.is( '.tap-loading' );
      } else {
        return tap_checkout.$checkout_form.is( '.tap-loading' );
      }
		},
    hasCardFormLoaded: function() {
      if (this.isOrderPayPage()) {
        return tap_checkout.$orderpay_form.is( '.tap-loaded' );
      } else {
        return tap_checkout.$checkout_form.is( '.tap-loaded' );
      }
		},
    cardFormLoading: function() {
      if (this.isOrderPayPage()) {
        tap_checkout.$orderpay_form.removeClass( 'tap-loaded' ).addClass( 'tap-loading' );
      } else {
        tap_checkout.$checkout_form.removeClass( 'tap-loaded' ).addClass( 'tap-loading' );
      }
		},
		cardFormLoaded: function() {
      if (this.isOrderPayPage()) {
        tap_checkout.$orderpay_form.removeClass( 'tap-loading' ).addClass( 'tap-loaded' );
      } else {
        tap_checkout.$checkout_form.removeClass( 'tap-loading' ).addClass( 'tap-loaded' );
      }
    },

    maybeInitCardForm: function(e){
      console.log( 'maybeInitCardForm' );
      console.log( tap_checkout.isCardFormLoading() );
      console.log( tap_checkout.hasCardFormLoaded() );
      // Already loading/loaded
      if ( tap_checkout.isCardFormLoading() || tap_checkout.hasCardFormLoaded() || 'tap' !== tap_checkout.getSelectedPaymentMethod() ) {
        return false;
      }

      if ( 'tap' === tap_checkout.getSelectedPaymentMethod() ) {
        tap_checkout.initCardForm();
      }
    },
    initCardForm: function(){
      $( '#tap-card-form-container' ).empty();
      tap_checkout.clearCardToken();
      tap_checkout.cardFormLoading();

      //create element, pass style and payment options
      tap_checkout.tapjsli = Tapjsli(tap_checkout_params.publishableApiKey);
      tap_checkout.card = tap_checkout.tapjsli.elements({}).create('card',{style: tap_checkout_params.style},tap_checkout_params.paymentOptions);

      //mount element
      tap_checkout.card.mount('#tap-card-form-container');

      //card change event listener
      tap_checkout.card.addEventListener('change', function(event) {
        if (event.loaded) {
          tap_checkout.cardFormLoaded();
        }
      });
    },

    checkoutMissingRequiredFields: function() {
      return tap_checkout.$checkout_form.find('.woocommerce-invalid-required-field').length > 0
    },

    hasCardToken: function() {
      return $( '#tap-token-value' ).val()
    },

    clearCardToken: function() {
      return $( '#tap-token-value' ).val('')
    },

    onCheckoutPlaceOrder: function(e){
      e.preventDefault();

      console.log( tap_checkout.hasCardToken() );

      // we already have a token or checkout form is missing required fields.
      if ( tap_checkout.hasCardToken() || tap_checkout.isCardFormLoading() || ! tap_checkout.hasCardFormLoaded() ) {
        // unbind restrictor

        tap_checkout.$checkout_form.unbind( 'checkout_place_order_tap', tap_checkout.onCheckoutPlaceOrder );
        $( '#place_order' ).trigger( 'click' );
        return true;
      }

			var form_data = tap_checkout.$checkout_form.data();
			if ( 1 !== form_data['blockUI.isBlocked'] ) {
				tap_checkout.$checkout_form.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
      tap_checkout.$checkout_form.addClass( 'processing' );
      $( '#place_order' ).html( tap_checkout_params.checkoutButtonProcessingText );

      tap_checkout.tapjsli.createToken(tap_checkout.card)
      .then(function(result) {
        tap_checkout.$checkout_form.removeClass( 'processing' ).unblock();

        if (result.error) {
          tap_checkout.addCardError( result.error.message );
          $( '#place_order' ).html( tap_checkout_params.checkoutButtonText );

        } else {
          tap_checkout.removeCardError();

          $( '#tap-token-value' ).val( result.id );

          // unbind restrictor
          tap_checkout.$checkout_form.unbind( 'checkout_place_order_tap', tap_checkout.onCheckoutPlaceOrder );
          $( '#place_order' ).trigger( 'click' );

          return true;
        }
      });

      console.log( 'ending false' );
      return false;
    },

    // Order pay
    isOrderPayPage: function(){
      return $( document.body ).hasClass( 'woocommerce-order-pay' );
    },
    onSubmitOrderPayForm: function(e){
      if ('tap' !== tap_checkout.getSelectedPaymentMethod()) {
        return true;
      }

      // we already have a token or checkout form is missing required fields.
      if ( tap_checkout.hasCardToken() || tap_checkout.isCardFormLoading() || ! tap_checkout.hasCardFormLoaded() ) {
        // unbind restrictor
        return true;
      }

      e.preventDefault();

      tap_checkout.tapjsli.createToken(tap_checkout.card)
      .then(function(result) {
        console.log(result);
        // tap_checkout.cardFormLoaded();

        if (result.error) {
          $('#order_review').unblock();
          tap_checkout.addCardError( result.error.message );
        } else {
          tap_checkout.removeCardError();

          $( '#place_order' ).html( tap_checkout_params.checkoutButtonProcessingText );
          $( '#tap-token-value' ).val( result.id );
          $( '#place_order' ).trigger( 'click' );
        }
      });

      return false;
    },

    init: function(){
      // Order Pay ====
      if ( this.isOrderPayPage() ) {
        this.$orderpay_form.on( 'submit', tap_checkout.onSubmitOrderPayForm );

        this.$orderpay_form.on( 'click', 'input[name="payment_method"]', function(e){
          // human click
          if ( e.hasOwnProperty('originalEvent') ) {
            // tap_checkout.removeCardError();
            tap_checkout.maybeInitCardForm();
          }
        });
      }

      // Checkout ====
      this.$checkout_form.on( 'checkout_place_order_tap', tap_checkout.onCheckoutPlaceOrder );


      this.$checkout_form.on( 'click', 'input[name="payment_method"]', function(e){
        // human click
        if ( e.hasOwnProperty('originalEvent') ) {
          // tap_checkout.removeCardError();
          tap_checkout.maybeInitCardForm();
        }
      });

      // Re-init.
      $( document.body ).on( 'updated_checkout', function(){
        if ( 'tap' === tap_checkout.getSelectedPaymentMethod() ) {
          tap_checkout.initCardForm();
        }
      });

      // Clear token on error.
      $( document.body ).on( 'checkout_error', function(){
        $( '#place_order' ).html( tap_checkout_params.checkoutButtonText );
        tap_checkout.clearCardToken();
        tap_checkout.$checkout_form.on( 'checkout_place_order_tap', tap_checkout.onCheckoutPlaceOrder );
      });
    },
  };

  tap_checkout.init();

})(jQuery);

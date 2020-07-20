(function($){
	'use strict';

  var tap_checkout = {
    $checkout_form: $( 'form.checkout' ),
    tapjsli: null,
    card: null,
    getSelectedPaymentMethod: function(){
      return $( '.woocommerce input[name="payment_method"]:checked' ).val();
    },
    addCardError: function(error){
      $( '#tap-card-notice' ).html('<div class="tap-error">' + error + '</div>').show();
    },
    addCardMessage: function(message){
      $( '#tap-card-notice' ).html('<div class="tap-message">' + message + '</div>').show();
    },
    removeCardNotice: function(message){
      $( '#tap-card-notice' ).hide().empty();
    },
    initCardForm: function(){
      if ('tap' !== tap_checkout.getSelectedPaymentMethod() ) {
        return false;
      }

      //console.log('initCardForm');
      $('#tap-card-form-container').empty();
      // tap_checkout.addCardMessage('Loading card form');

      //create element, pass style and payment options
      tap_checkout.tapjsli = Tapjsli(tap_checkout_params.publishableApiKey);
      tap_checkout.card = tap_checkout.tapjsli.elements({}).create('card',{style: tap_checkout_params.style},tap_checkout_params.paymentOptions);

      //mount element
      tap_checkout.card.mount('#tap-card-form-container');

      var skip_error_keys = [
        'card_number_required',
        'error_invalid_expiry_characters',
        'error_invalid_cvv_characters'
      ];

      //card change event listener
      tap_checkout.card.addEventListener('change', function(event) {
        if (event.loaded) {
          // console.log("UI loaded :" + event.loaded);
          tap_checkout.removeCardNotice();
        }
        // console.log(event);

        /*
        if (event.error && ! skip_error_keys.includes(event.error.key)) {
          $('#tap-card-notice').html('<div class="tap-error">' + event.error.message + '</div>').show();
        } else {
          $('#tap-card-notice').empty().hide();
        }
        */
      });
    },
		blockCheckout: function() {
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
		},

    onCheckoutPlaceOrder: function(e){
      e.preventDefault();

      tap_checkout.addCardError( tap_checkout_params.validatingCardText );

      tap_checkout.$checkout_form.addClass( 'processing' );
      tap_checkout.blockCheckout();

      tap_checkout.tapjsli.createToken(tap_checkout.card)
      .then(function(result) {
        tap_checkout.$checkout_form.removeClass( 'processing' ).unblock();

        if (result.error) {
          tap_checkout.addCardError( result.error.message );
        } else {
          tap_checkout.removeCardNotice();

          $( '#place_order' ).html( tap_checkout_params.checkoutButtonProcessingText );

          $( '#tap-token-value' ).val( result.id );

          // unbind restrictor
          tap_checkout.$checkout_form.unbind( 'checkout_place_order_tap', tap_checkout.onCheckoutPlaceOrder );

          $( '#place_order' ).trigger( 'click' );

          return true;
        }
      });

      return false;
    },

    // Order pay
    isOrderPayPage: function(){
      return $( document.body ).hasClass( 'woocommerce-order-pay' );
    },
    onSubmitOrderPayForm: function(e){
      if ('tap' === tap_checkout.getSelectedPaymentMethod() && ! $( '#order_review' ).hasClass('confirmed') ) {
        e.preventDefault();

        if (! $('#tap-mobile-no').val()) {

          setTimeout(function(){
            $('#order_review').unblock();
          }, 1);

          $('.field-tap-mobile-no .field-notice')
            .html('<div class="woocommerce-error">' + tap_checkout_params.textEnterWalletNumber + '</div>');
        } else {
          var data = {
            action: 'tap_request_otp',
            order_key: tap_checkout_params.orderKey,
            mobile_no: $('#tap-mobile-no').val()
          };

          $.post(tap_checkout_params.ajaxUrl, data)
          .done(function(r){
            if (r.success) {
              tap_checkout.displayModal();
            } else {
              tap_checkout.hideModal();
              $('#order_review').unblock();
            }
          });
        }

        return false;
      }
    },

    init: function(){
      // Order Pay ====
      if ( this.isOrderPayPage() ) {
        $('#order_review').on( 'submit', tap_checkout.onSubmitOrderPayForm);
      }

      // Checkout ====
      this.$checkout_form.on( 'checkout_place_order_tap', tap_checkout.onCheckoutPlaceOrder );

      this.$checkout_form.on( 'click', 'input[name="payment_method"]', tap_checkout.initCardForm);

      tap_checkout.initCardForm();
    },
  };

  tap_checkout.init();

})(jQuery);

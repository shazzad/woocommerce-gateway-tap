


(function($){
	'use strict';

  var tap_checkout = {
    $modal: $('#tap-card-modal'),

    formatData: function(data){
      var parts = data.split("&"), pair, out = {};
      for (var i=0; i<parts.length; i++){
        pair = parts[i].split("=");
        out[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
      }
      return out;
    },
    recentAjax: {
      url: '',
      data: {},
      response: {}
    },
    captureAjaxData: function( event, xhr, settings ) {
      tap_checkout.recentAjax.url = settings.url;
      tap_checkout.recentAjax.xhr = xhr;
      tap_checkout.recentAjax.data = settings.data ? tap_checkout.formatData(settings.data) : {};
    },

    getSelectedPaymentMethod: function(){
      return $( '.woocommerce input[name="payment_method"]:checked' ).val();
    },
    isOrderPayPage: function(){
      return $( document.body ).hasClass( 'woocommerce-order-pay' );
    },

    initCardForm: function(){
      //pass your public key from tap's dashboard
      var tap = Tapjsli('pk_test_alhOrg81RbEcBWdwfp9eHDji');
      var elements = tap.elements({});
      var style = {
        base: {
          color: '#535353',
          lineHeight: '18px',
          fontFamily: 'sans-serif',
          fontSmoothing: 'antialiased',
          fontSize: '16px',
          '::placeholder': {
            color: 'rgba(0, 0, 0, 0.26)',
            fontSize:'15px'
          }
        },
        invalid: {
          color: 'red'
        }
      };
      // input labels/placeholders
      var labels = {
          cardNumber:"Card Number",
          expirationDate:"MM/YY",
          cvv:"CVV",
          cardHolder:"Card Holder Name"
        };
      //payment options
      var paymentOptions = {
        currencyCode:["KWD","USD","SAR"],
        labels : labels,
        TextDirection:'ltr'
      }
      //create element, pass style and payment options
      var card = elements.create('card', {style: style},paymentOptions);
      //mount element
      card.mount('#tap-card-form-container');

      var skip_error_keys = [
        'error_invalid_expiry_characters',
        'error_invalid_cvv_characters'
      ];

      //card change event listener
      card.addEventListener('change', function(event) {
        if (event.loaded) {
          console.log("UI loaded :"+event.loaded);
          console.log("current currency is :" + card.getCurrency())
        }

        console.log(event);

        if (event.error && ! skip_error_keys.includes(event.error.key)) {
          tap_checkout.$modal.find('.modal-notice').html('<div class="modal-error">' + event.error.message + '</div>').show();
        } else {
          tap_checkout.$modal.find('.modal-notice').empty().hide();
        }
      });

      tap_checkout.$modal.find('.submit-card-btn').on('click', function(e) {
        e.preventDefault();
        tap_checkout.$modal.find('.modal-notice').empty().hide();

        tap.createToken(card)
        .then(function(result) {
          console.log(result);
          if (result.error) {
            tap_checkout.$modal.find('.modal-notice').html('<div class="modal-error">' + result.error.message + '</div>').show();
          } else {
            tap_checkout.$modal.find('.modal-notice').html('<div class="modal-message">Processing order...</div>').show();
            $('#tap-token-value').val( result.id );
            $('#place_order').trigger( 'click' );
            // tap_checkout.hideModal();
          }
        });
      });
    },

    displayModal: function() {
      if ( ! tap_checkout.$modal.hasClass('show') ) {
        tap_checkout.$modal.addClass('show');
        tap_checkout.initCardForm();
      }
    },
    hideModal: function() {
      tap_checkout.$modal.removeClass('show');
      $('#tap-mobile-no').removeAttr('readonly').focus();
    },
    onClickModalCloseButton: function(e){
      e.preventDefault();

      tap_checkout.$modal.removeClass('show');
      $('#order_review').unblock();

      return false;
    },

    onCheckoutPlaceOrderSuccess: function(e){
      if ('tap' !== tap_checkout.getSelectedPaymentMethod() || ! tap_checkout.recentAjax.url || tap_checkout.recentAjax.url !== '/?wc-ajax=checkout') {
        return true;
      }

      e.preventDefault();

      tap_checkout.recentAjax.xhr
      .done(function(result){
        if ( 'request_payment' === result.tap ) {
          // Stop woocommerce scroll to notices animation.
          $('html, body').stop();

          tap_checkout.displayModal();

        } else if ( 'payment_confirmed' === result.tap  && result.redirect) {
          // Stop woocommerce scroll to notices animation.
          $('html, body').stop();

          window.location = result.redirect;
       }
      });

      return false;
    },
    onSubmitOrderPayForm: function(e){
      if ('tap' === tap_checkout.getSelectedPaymentMethod() && ! $( '#order_review' ).hasClass('confirmed') ) {
        e.preventDefault();

        if (! $('#tap-mobile-no').val()) {

          setTimeout(function(){
            $('#order_review').unblock();
          }, 1);

          $('.field-tap-mobile-no .field-notice')
            .html('<div class="woocommerce-error">' + tap.textEnterWalletNumber + '</div>');
        } else {
          var data = {
            action: 'tap_request_otp',
            order_key: tap.orderKey,
            mobile_no: $('#tap-mobile-no').val()
          };

          $.post(tap.ajaxUrl, data)
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
      // Close Modal
      $(document.body).on('click', '#tap-card-modal .modal-close-btn', this.onClickModalCloseButton);

      /* Order Pay */
      if ( this.isOrderPayPage() ) {
        $('#order_review').on( 'submit', tap_checkout.onSubmitOrderPayForm);
      }

      // Checkout ====
      // Capture last ajax request.
      $(document).ajaxSend(tap_checkout.captureAjaxData);

      // Checkout page success takeover.
      $( 'form.checkout' ).on( 'checkout_place_order_success', tap_checkout.onCheckoutPlaceOrderSuccess);

      // tap_checkout.displayModal();
    },
  };

  tap_checkout.init();

})(jQuery);

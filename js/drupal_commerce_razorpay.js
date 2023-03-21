(function ($, Drupal) {
  'use strict';

    Drupal.behaviors.commerceRazorpayCheckout = {
        attach: function (context, drupalSettings) {
            once('commerceRazorpayCheckout', 'html').forEach(function (element) {

                var data = drupalSettings.razorpay_checkout_data;

                var setDisabled = function(id, state) {
                    if (typeof state === 'undefined') {
                        state = true;
                    }
                    var elem = document.getElementById(id);
                    if (state === false) {
                        elem.removeAttribute('disabled');
                    } else {
                        elem.setAttribute('disabled', state);
                    }
                };

                data.modal = {
                    ondismiss: function() {
                        setDisabled('btn-razorpay', false);
                    },
                };

                var razorpayCheckout = new Razorpay(data);

                function rzpOpenCheckout() {
                    setDisabled('btn-razorpay');
                    razorpayCheckout.open();
                }

                rzpOpenCheckout();

                $('#btn-razorpay').on('click', function(event) {
                    event.preventDefault();
                    rzpOpenCheckout();
                });

                $('#btn-razorpay-cancel').on('click', function(event) {
                    event.preventDefault();
                });
            })
        }
    };
}(jQuery, Drupal));

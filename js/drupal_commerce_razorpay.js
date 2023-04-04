(function ($, Drupal) {
  'use strict';
    Drupal.behaviors.commerceRazorpayCheckout = {
        attach: function (context, drupalSettings) {
            once('commerceRazorpayCheckout', 'html').forEach(function (element) {

                $("#msg-razorpay-success").css("background-color", "yellow");
                $('#msg-razorpay-success').hide();

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

                data.handler = function(payment) {
                    setDisabled('btn-razorpay-cancel');
                    $('#msg-razorpay-success').show();
                    $('#razorpay_order_id').val(payment.razorpay_order_id);
                    $('#razorpay_payment_id').val(payment.razorpay_payment_id);
                    $('#razorpay_signature').val(payment.razorpay_signature);
                    $('#rzp_submit_button').click();
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

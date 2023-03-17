(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.drupal_commerce_razorpay = {
    attach: function (context) {

      var data = drupalSettings.drupal_commerce_razorpay;

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

      function rzpOpenCheckout() {
        setDisabled('btn-razorpay');
        razorpayCheckout.open();
      }

      var razorpayCheckout = new Razorpay(data);
      rzpOpenCheckout();

      $('#btn-razorpay').on('click', function(event) {
        event.preventDefault();
        rzpOpenCheckout();
      });

      $('#btn-razorpay-cancel').on('click', function(event) {
        event.preventDefault();
      });

    }
  };
}(jQuery, Drupal, drupalSettings));

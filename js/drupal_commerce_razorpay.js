(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.drupal_commerce_razorpay = {
    attach: function (context) {
      var data = drupalSettings.drupal_commerce_razorpay;
      console.log(data);
      var rzp1 = new Razorpay(data);
      rzp1.open();
    }
  };
}(jQuery, Drupal, drupalSettings));
(function ($, Drupal) {
    Drupal.behaviors.myModuleBehavior = {
      attach: function (context, settings) {
        alert('Hello, world!');
      }
    };
  })(jQuery, Drupal);
  
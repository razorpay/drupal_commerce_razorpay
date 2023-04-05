<?php

namespace Drupal\drupal_commerce_razorpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles IPN requests from payment gateways.
 */
class IPNController extends ControllerBase {

  /**
   * Handles the IPN request.
   */
  public function handleIPN(Request $request) {
    // Get the payment gateway plugin ID from the request.
    $plugin_id = $request->request->get('plugin_id');

    // Load the payment gateway plugin.
      $payment_gateway = \Drupal::service('plugin.manager.commerce_payment_gateway')->createInstance($plugin_id);

    // Verify the IPN message.
    $verified = $payment_gateway->onNotify($request);
    if (!$verified) {
      throw new \Exception('IPN message verification failed.');
    }

    // Process the IPN message.
    // ...
  }

}

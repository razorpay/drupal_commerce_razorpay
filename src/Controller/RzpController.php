<?php

namespace Drupal\drupal_commerce_razorpay\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class Controller
 * @package Drupal\drupal_commerce_razorpay\Controller
 */

class RzpController extends ControllerBase
{
    public function capturePayment()
    {
        $url =  Url::fromRoute('commerce_payment.checkout.return',
            [
                'commerce_order' => '1',
                'step' => 'payment',
            ],
            [
                'absolute' => TRUE
            ]
        )->toString();

        return new RedirectResponse($url);
    }
}

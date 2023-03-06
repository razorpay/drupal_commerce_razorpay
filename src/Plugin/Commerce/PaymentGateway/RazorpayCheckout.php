<?php

namespace Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Razorpay\Api\Api;


/**
 * Provides the Razorpay offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "razorpay",
 *   label = @Translation("Razorpay"),
 *   display_label = @Translation("Razorpay"),
 *   forms = {
 *     "offsite-payment" = "Drupal\drupal_commerce_razorpay\PluginForm\RazorpayForm",
 *   }
 * )
 */
class RazorpayCheckout extends OffsitePaymentGatewayBase
{
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
      return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('entity_type.manager'),
        $container->get('plugin.manager.commerce_payment_type'),
        $container->get('plugin.manager.commerce_payment_method_type'),
        $container->get('datetime.time'),
        $container->get('commerce_price.minor_units_converter'),
        $container->get('event_dispatcher'),
        $container->get('extension.list.module'),
        $container->get('uuid')
      );
    }
}

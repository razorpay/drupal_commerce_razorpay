<?php

namespace Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Form\FormStateInterface;
use Razorpay\Api\Api;
use Drupal\drupal_commerce_razorpay\AutoWebhook;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Razorpay\Api\Errors\SignatureVerificationError;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Calculator;
use Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway\RazorpayInterface;

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
class RazorpayCheckout extends OffsitePaymentGatewayBase implements RazorpayInterface
{
    /**
     * Event constants
     */
    const PAYMENT_AUTHORIZED       = 'payment.authorized';
    const PAYMENT_FAILED           = 'payment.failed';
    const REFUNDED_CREATED         = 'refund.created';
    
     /**
     * @var Webhook Notify Wait Time
     */
    protected const WEBHOOK_NOTIFY_WAIT_TIME = (3 * 60);

    /**
     * @var HTTP CONFLICT Request
     */
    protected const HTTP_CONFLICT_STATUS = 409;
  
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return ['key_id' => '',
                'key_secret' => '',
                'payment_action' => [],
            ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('plugin.manager.commerce_payment_type'),
            $container->get('plugin.manager.commerce_payment_method_type'),
            $container->get('datetime.time'),
            $container->get('commerce_price.minor_units_converter')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['display_label']['#prefix'] = 'First <a href="https://easy.razorpay.com/onboarding?recommended_product=payment_gateway&source=drupal" target="_blank">signup</a> for a Razorpay account or
            <a href="https://dashboard.razorpay.com/signin?screen=sign_in&source=drupal" target="_blank">login</a> if you have an existing account.</p>';

        $form['key_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Key ID'),
            '#description' => $this->t('The key Id and key secret can be generated from "API Keys" section of Razorpay Dashboard. Use test or live for test or live mode.'),
            '#default_value' => $this->configuration['key_id'],
            '#required' => TRUE,
        ];

        $form['key_secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Key Secret'),
            '#description' => $this->t('The key Id and key secret can be generated from "API Keys" section of Razorpay Dashboard. Use test or live for test or live mode.'),
            '#default_value' => $this->configuration['key_secret'],
            '#required' => TRUE,
        ];

        $form['payment_action'] = [
            '#type' => 'select',
            '#title' => $this->t('Payment Action'),
            '#options' => [
                'capture' => $this->t('Authorize and Capture'),
                'authorize' => $this->t('Authorize'),
            ],
            '#default_value' => $this->configuration['payment_action'],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        if ($form_state->getErrors())
        {
            return;
        }

        $form_state->setValue('id', 'razorpay');

        $values = $form_state->getValue($form['#parents']);

        if (empty($values['key_id']) || empty($values['key_secret']))
        {
            return;
        }

        if (substr($values['key_id'], 0, 8) !== 'rzp_' . $values['mode'])
        {
            $this->messenger()->addError($this->t('Invalid Key ID or Key Secret for ' . $values['mode'] . ' mode.'));
            $form_state->setError($form['mode']);

            return;
        }

        try
        {
            $api = new Api($values['key_id'], $values['key_secret']);
            $options = [
                'count' => 1
            ];
            $orders = $api->order->all($options);
        }
        catch (\Exception $exception)
        {
            $this->messenger()->addError($this->t('Invalid Key ID or Key Secret.'));
            $form_state->setError($form['key_id']);
            $form_state->setError($form['key_secret']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);

        if ($form_state->getErrors())
        {
            return;
        }

        $values = $form_state->getValue($form['#parents']);

        $this->configuration['key_id'] = $values['key_id'];
        $this->configuration['key_secret'] = $values['key_secret'];
        $this->configuration['payment_action'] = $values['payment_action'];

        $autoWebhook = new AutoWebhook();
        $autoWebhook->autoEnableWebhook($values['key_id'], $values['key_secret']);
    }

    /**
    * {@inheritdoc}
    */
    public function onReturn(OrderInterface $order, Request $request) 
    {
        $keyId = $this->configuration['key_id'];
        $keySecret = $this->configuration['key_secret'];
        $api = new Api($keyId, $keySecret);
    
        //validate Rzp signature
        try
        {  
            $attributes = [
                'razorpay_order_id' => $request->get('razorpay_order_id'),
                'razorpay_payment_id' => $request->get('razorpay_payment_id'),
                'razorpay_signature' => $request->get('razorpay_signature')
            ];
        
            $api->utility->verifyPaymentSignature($attributes);

            // Process payment and update order status
            $orderObject = $api->order->fetch($order->getData('razorpay_order_id'));
            $paymentObject = $orderObject->payments();

            $status = end($paymentObject['items'])->status;
         
            $message = '';
            $remoteStatus = '';

            $requestTime = $this->time->getRequestTime();

            if ($status == "captured")
            {
                // Status is success.
                $remoteStatus = t('Completed');

                $message = $this->t('Your payment was successful with Order id : @orderid has been received at : @date', ['@orderid' => $order->id(), '@date' => date("d-m-Y H:i:s", $requestTime)]);
            
                $status = "completed";
            }
            elseif ($status == "authorized")
            {
                // Batch process - Pending orders.
                $remoteStatus = t('Pending');
                $message = $this->t('Your payment with Order id : @orderid is pending at : @date', ['@orderid' => $order->id(), '@date' => date("d-m-Y H:i:s", $requestTime)]);
                $status = "authorization";
            }
            elseif ($status == "failed")
            {
                // Failed transaction
                $message = $this->t('Your payment with Order id : @orderid failed at : @date', ['@orderid' => $order->id(), '@date' => date("d-m-Y H:i:s", $requestTime)]);
               
                \Drupal::logger('RazorpayOnReturn')->error($message);
                
                throw new PaymentGatewayException();
            }
      
            $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');

            $payment = $paymentStorage->create([
                'state' => $status,
                'amount' => $order->getTotalPrice(),
                'payment_gateway' => $this->entityId,
                'order_id' => $order->id(),
                'test' => $this->getMode() == 'test',
                'remote_id' => end($paymentObject['items'])->id,
                'remote_state' => $remoteStatus ? $remoteStatus : $request->get('payment_status'),
                'authorized' => $requestTime,
                ]
            );
      
            $payment->save();

            \Drupal::messenger()->addMessage($message);

        }
        catch (SignatureVerificationError $exception)
        {
            $message = "Your payment to Razorpay failed " . $exception->getMessage();
            \Drupal::logger('RazorpayOnReturn')->error($exception->getMessage());
            throw new PaymentGatewayException($message);

        }
        catch (\Throwable $exception)
        {
            \Drupal::logger('RazorpayOnReturn')->error($exception->getMessage());
            throw new PaymentGatewayException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function capturePayment(PaymentInterface $payment, Price $amount = NULL)
    {
        $this->assertPaymentState($payment, ['authorization']);

        // If not specified, capture the entire amount.
        $amount = $amount ?: $payment->getAmount();

        try
        {
            $api = $this->getRazorpayApiInstance();

            $razorpayPaymentId = $payment->getRemoteId();
            $razorpayPayment = $api->payment->fetch($razorpayPaymentId);

            $captureParams = [
                'amount' => Calculator::trim($amount) * 100,
                'currency' => $amount->getCurrencyCode()
            ];
            $razorpayPayment->capture($captureParams);
        }
        catch (\Exception $exception)
        {
            \Drupal::logger('RazorpayCapturePayment')->error($exception->getMessage());
            throw new PaymentGatewayException($exception->getMessage());
        }

        $payment->setState('completed');
        $payment->setAmount($amount);
        $payment->save();
    }

    /**
     * {@inheritdoc}
     */
    public function voidPayment(PaymentInterface $payment)
    {
        throw new PaymentGatewayException('void payments are not supported. please click cancel');
    }

    /**
     * {@inheritdoc}
     */
    public function refundPayment(PaymentInterface $payment, Price $amount = NULL)
    {
        $this->assertPaymentState($payment, ['completed', 'partially_refunded']);

        // If not specified, refund the entire amount.
        $amount = $amount ?: $payment->getAmount();
        $this->assertRefundAmount($payment, $amount);

        try
        {
            $api = $this->getRazorpayApiInstance();

            $razorpayPaymentId = $payment->getRemoteId();
            $razorpayPayment = $api->payment->fetch($razorpayPaymentId);
            $razorpayPayment->refund(array('amount' => Calculator::trim($amount) * 100));
        }
        catch (\Exception $exception)
        {
            \Drupal::logger('RazorpayRefund')->error($exception->getMessage());
            throw new PaymentGatewayException($exception->getMessage());
        }

        $oldRefundedAmount = $payment->getRefundedAmount();
        $newRefundedAmount = $oldRefundedAmount->add($amount);

        if ($newRefundedAmount->lessThan($payment->getAmount()))
        {
            $payment->setState('partially_refunded');
        }
        else
        {
            $payment->setState('refunded');
        }

        $payment->setRefundedAmount($newRefundedAmount);
        $payment->save();
    }

    protected function getRazorpayApiInstance($key = null, $secret = null)
    {
        if ($key === null or
            $secret === null)
        {
            $key = $this->configuration['key_id'];
            $secret = $this->configuration['key_secret'];
        }

        return new Api($key, $secret);
    }

    /**
    * {@inheritdoc}
    */
    public function onCancel(OrderInterface $order, Request $request)
    {
        $this->messenger()->addMessage($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.', [
            '@gateway' => $this->getDisplayLabel(),
          ])
        );
    }

    /**
    * {@inheritdoc}
    */
    public function onNotify(Request $request)
    {
        $supportedWebhookEvents = [
            'payment.authorized',
            'refund.created',
            'payment.failed'
        ];

        $data = json_decode($request->getContent(), true);

        // Ignore unsupported events.
        if (isset($data['event']) === false or
            in_array($data['event'], $supportedWebhookEvents) === false) {
            return;
        }

        $orderId = $data['payload']['payment']['entity']['notes']['drupal_order_id'];

        $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($orderId);

        $rzpWebhookNotifiedAt = $order->getData('rzp_webhook_notified_at');

        if (empty($rzpWebhookNotifiedAt) === true)
        {
            $order->setData('rzp_webhook_notified_at', time())->save();
            return new Response('Webhook conflicts due to early execution.', static::HTTP_CONFLICT_STATUS);
        }
        elseif ((time() - $rzpWebhookNotifiedAt) < static::WEBHOOK_NOTIFY_WAIT_TIME)
        {
            return new Response('Webhook conflicts due to early execution.', static::HTTP_CONFLICT_STATUS);
        }

        $api = $this->getRazorpayApiInstance();
     
        // Verify the webhook signature
        $signature = $request->headers->get('X-Razorpay-Signature');

        $config = \Drupal::config('drupal_commerce_razorpay.settings');
        $webhook_secret = $config->get('razorpay_flags.webhook_secret');

        try
        {
            $api->utility->verifyWebhookSignature($request->getContent(), $signature, $webhook_secret);
        }
        catch (\Exception $exception)
        {
            // Handle signature verification error
            \Drupal::logger('RazorpayWebhook')->error($exception->getMessage());
            return new Response($exception->getMessage(), 401);
        }
     
        // Handle the webhook event based on the event type
        $event = $data['event'];
        
        $orderId = $data['payload']['payment']['entity']['notes']['drupal_order_id'];

        $paymentId = $data['payload']['payment']['entity']['id'];

        switch ($event)
        {
            case self::PAYMENT_AUTHORIZED:

                $orderStatus = $order->getState()->getId();

                if ($orderStatus !== 'draft')
                {
                    return new Response('order is in ' . $orderStatus . 'state', 200);
                }

                $paymentStorage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
                $razorpayPaymentId = $data['payload']['payment']['entity']['id'];

                $razorpayPayment = $api->payment->fetch($razorpayPaymentId);

                if ($razorpayPayment['status'] === 'captured')
                {
                    $state = 'completed';
                }
                else if ($razorpayPayment['status'] === 'authorized')
                {
                    $state = 'authorization';
                }

                $amount = Price::fromArray([
                    'number' => ($data['payload']['payment']['entity']['amount'])/100,
                    'currency_code' => $data['payload']['payment']['entity']['currency'],
                ]);

                $payment = $paymentStorage->create([
                    'state' => $state,
                    'amount' => $amount,
                    'payment_gateway' => $this->entityId,
                    'order_id' => $orderId,
                    'remote_id' => $data['payload']['payment']['entity']['id'],
                    'remote_state' => $data['payload']['payment']['entity']['status'],
                    'authorized' => $this->time->getRequestTime(),
                ]);
                $payment->save();

                break;

            case self::PAYMENT_FAILED:
                // Update the order status to "failed"

                $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
                $order = $order_storage->load($orderId);
                if (!$order)
                {
                    \Drupal::logger('RazorpayWebhook')->info("Order not Found : ". $orderId);
           
                    return new Response('Order not found',  404);
                }

                \Drupal::logger('RazorpayWebhook')->info("Payment Failed for order ID: ". $orderId);
                 
                break;

            case self::REFUNDED_CREATED:
                // Update the payment and order statuses to "refunded"
                               
                $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
                $payments = $payment_storage->loadByProperties(['remote_id' => $paymentId]);
                if (count($payments) !== 1)
                {
                    \Drupal::logger('RazorpayWebhook')->info("Payment not Found : ". $paymentId);
                    return new Response('Payment not found or multiple payments found', 404);
                }
                $totalamt= ($data['payload']['payment']['entity']['amount'])/100;

                $amtRefund= ($data['payload']['payment']['entity']['amount_refunded'])/100;
                
                if($totalamt === $amtRefund)
                {
                    $state = 'refunded';
                }
                else
                {
                    $state = 'partially_refunded';
                }
                
                $payment = reset($payments);
                $payment->setState($state);
                $refund_amount = new Price((string) $amtRefund, $payment->getAmount()->getCurrencyCode());
                $payment->setRefundedAmount($refund_amount);
                $payment->save();
                
                break;
         }
     
         \Drupal::logger('RazorpayWebhook')->info("Webhook processed successfully for ". $event);
                     
         return new Response('Webhook processed successfully', 200);
    }
}

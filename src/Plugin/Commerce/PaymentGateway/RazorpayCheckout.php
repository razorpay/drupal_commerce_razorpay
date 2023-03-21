<?php

namespace Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Razorpay\Api\Api;
use Drupal\drupal_commerce_razorpay\AutoWebhook;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Razorpay\Api\Errors\SignatureVerificationError;
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
    public function defaultConfiguration()
    {
        return [
                'key_id' => '',
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
            $attributes = array(
            'razorpay_order_id' => $request->get('razorpay_order_id'),
            'razorpay_payment_id' => $request->get('razorpay_payment_id'),
            'razorpay_signature' => $request->get('razorpay_signature')
             );
        
            $api->utility->verifyPaymentSignature($attributes);

            // Process payment and update order status
            $orderObject = $api->order->fetch($order->getData('razorpay_order_id'));
            $paymentObject = $orderObject->payments();

            $status = $paymentObject['items'][0]->status; 
         
            $message = '';
            $remoteStatus = '';

            $requestTime = $this->time->getRequestTime();

            if ($status == "captured")
            {
                // Status is success.
                $remoteStatus = t('Success');

                $message = $this->t('Your payment was successful with Order id : @orderid has been received at : @date', ['@orderid' => $order->id(), '@date' => date("d-m-Y H:i:s", $requestTime)]);
            
                $status = "success";
            }
            elseif ($status == "authorized")
            {
                // Batch process - Pending orders.
                $remoteStatus = t('Pending');
                $message = $this->t('Your payment with Order id : @orderid is pending at : @date', ['@orderid' => $order->id(), '@date' => date("d-m-Y H:i:s", $requestTime)]);
                $status = "pending";
            }
            elseif ($status == "failed")
            {
                // Failed transaction.
                $remoteStatus = t('Failure');
                $message = $this->t('Your payment with Order id : @orderid failed at : @date', ['@orderid' => $order->id(), '@date' => date("d-m-Y H:i:s", $requestTime)]);
                $status = "fail";
            }
      
            $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');

            $payment = $paymentStorage->create([
                'state' => $status,
                'amount' => $order->getTotalPrice(),
                'payment_gateway' => $this->entityId,
                'order_id' => $order->id(),
                'test' => $this->getMode() == 'test',
                'remote_id' => $paymentObject['items'][0]->id,
                'remote_state' => $remoteStatus ? $remoteStatus : $request->get('payment_status'),
                'authorized' => $requestTime,
                ]
            );
      
            $payment->save();

            \Drupal::messenger()->addMessage($message);

        }
        catch (SignatureVerificationError $e)
        {
            $message = "Your payment to Razorpay failed " . $e->getMessage();
            $this->messenger()->addError($this->t($message));

            \Drupal::logger('RazorpayCheckout')->error($e->getMessage());
          
        }
    }
}

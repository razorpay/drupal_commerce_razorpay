<?php

namespace Drupal\drupal_commerce_razorpay\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\drupal_commerce_razorpay\AutoWebhook;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Razorpay\Api\Api;

/**
 * Provides the Off-site payment form.
 */
class RazorpayForm extends BasePaymentOffsiteForm
{
    protected $payment;
    protected $config;
    protected $messenger;

    protected const TWELVE_HOURS = 86400;

    /**
     * Given drupal order and other required values
     * to find the associated Razorpay Order using
     * rzp order id and validate order amount. If not
     * found (or incorrect), create a new Razorpay Order
     *
     * @param  object $order, array $orderData
     * @return string Razorpay Order Id
     */
    public function createOrGetRazorpayOrderId($order, $orderData)
    {
        $create = false;

        try
        {
            $razorpayOrderId = $order->getData('razorpay_order_id');
            $razorpayOrderAmount = $order->getData('razorpay_order_amount');

            $api = $this->getRazorpayApiInstance();

            if (empty($razorpayOrderId) === true or
                empty($razorpayOrderAmount) === true)
            {
                $create = true;
            }
            elseif (empty($razorpayOrderId) === false)
            {
                $razorpayOrder = $api->order->fetch($razorpayOrderId);

                if ($razorpayOrder['amount'] !== $orderData['amount'])
                {
                    $create = true;
                }
                else
                {
                    return $razorpayOrderId;
                }
            }
        }
        catch (\Exception $exception)
        {
            $create = true;
        }

        if ($create)
        {
            try
            {
                $orderPayload = [
                    'receipt'         => $order->id(),
                    'amount'          => (int) $orderData['amount'],
                    'currency'        => $orderData['currency'],
                    'payment_capture' => ($orderData['payment_action'] === 'authorize') ? 0 : 1,
                    'notes'           => [
                        'drupal_order_id'  => (string) $order->id(),
                    ],
                ];

                $razorpayOrder = $api->order->create($orderPayload);

                $order->setData('razorpay_order_id', $razorpayOrder->id);
                $order->setData('razorpay_order_amount', $razorpayOrder->amount);
                $order->save();

                return $razorpayOrder['id'];
            }
            catch (\Exception $exception)
            {
                \Drupal::logger('RazorpayCheckoutForm')->error($exception->getMessage());

                return "error";
            }
        }
    }

    protected function getRazorpayApiInstance($key = null, $secret = null)
    {
        if ($key === null or
            $secret === null)
        {
            $key = $this->config['key_id'];
            $secret = $this->config['key_secret'];
        }

        return new Api($key, $secret);
    }

    protected function setPaymentConfigAndMessenger()
    {
        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $this->payment = $this->entity;

        $this->config = $this->payment->getPaymentGateway()->getPlugin()->getConfiguration();

        $this->messenger = \Drupal::messenger();
    }

    public function generateCheckoutForm(&$form, $orderId, $orderAmount)
    {
        $html = '<p>ORDER NUMBER: <b>' . $orderId . '</b>
            <br>ORDER TOTAL: <b>' . $orderAmount . '</b>
            </p><hr>
            <p>Thank you for your order, please click the button below to pay with Razorpay.</p>
            <p id="msg-razorpay-success">Please wait while we are processing your payment.</p>';

        $form['pay_now'] = array(
            '#type' => 'button',
            '#value' => $this->t('Pay Now'),
            '#prefix' => $html,
            '#attributes' => [
                'id' => 'btn-razorpay'
            ]
        );

        $form['cancel'] = array(
            '#type' => 'button',
            '#value' => $this->t('cancel'),
            '#attributes' => [
                'id' => 'btn-razorpay-cancel',
                'onclick' => 'window.location.replace("' . $form['#cancel_url'] . '");'
            ]
        );

        return $form;
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $this->setPaymentConfigAndMessenger();

        $orderId = \Drupal::routeMatch()->getParameter('commerce_order')->id();

        $order = $this->payment->getOrder();
        $address = $order->getBillingProfile()->address->first();
        $currency = $this->payment->getAmount()->getCurrencyCode();

        $orderAmount = ($this->payment->getAmount()->getNumber()) * 100;

        $orderData = [
            'amount'            => $orderAmount,
            'currency'          => $currency,
            'payment_action'    => $this->config['payment_action']
        ];

        $razorpayOrderId = $this->createOrGetRazorpayOrderId($order, $orderData);

        if ($razorpayOrderId === 'error')
        {
            $this->messenger->addError($this->t('Unable to create Razorpay Order.'));

            $url =  Url::fromRoute('commerce_checkout.form', [
                'commerce_order' => $orderId,
                'step' => 'review',
            ], ['absolute' => TRUE])->toString();

            $response = new RedirectResponse($url);
            $response->send();
        }

        $callbackUrl = Url::fromRoute('commerce_payment.checkout.return', [
            'commerce_order' => $orderId,
            'step' => 'payment',
        ], ['absolute' => TRUE])->toString();

        $checkoutArgs = [
            'key'           => $this->config['key_id'],
            'amount'        => $orderAmount,
            'image'         => 'https://cdn.razorpay.com/static/assets/logo/payment.svg',
            'order_id'      => $razorpayOrderId,
            'name'          => \Drupal::config('system.site')->get('name'),
            'currency'      => $currency,
            'callback_url'  => $callbackUrl,
            'prefill'       => [
                'name'      => $address->getGivenName() . " " . $address->getFamilyName(),
                'email'     => $order->getEmail(),
                'contact'   => '',
            ],
            'notes'         => [
                'drupal_order_id'   => $orderId
            ]
        ];

        $this->generateCheckoutForm($form, $orderId, $this->payment->getAmount()->getNumber());

        // Attach library.
        $form['#attached']['library'][] = 'drupal_commerce_razorpay/drupal_commerce_razorpay.payment';
        $form['#attached']['drupalSettings']['razorpay_checkout_data'] = $checkoutArgs;

        try
        {
            $configFactory = \Drupal::configFactory();
            $configFlags = $configFactory->getEditable('drupal_commerce_razorpay.settings');
            $settingFlags = $configFlags->get('razorpay_flags');
            $webhookEnableAt = $settingFlags['webhook_enable_at'];

            if (empty($webhookEnableAt) === true or
                ($webhookEnableAt + static::TWELVE_HOURS) < time())
            {
                $autoWebhook = new AutoWebhook();
                $autoWebhook->autoEnableWebhook($this->config['key_id'], $this->config['key_secret']);
            }
        }
        catch (\Exception $exception)
        {
            \Drupal::logger('RazorpayAutoWebhook')->error($exception->getMessage());
        }

        return $form;
    }
}

<?php

namespace Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
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
    }
}

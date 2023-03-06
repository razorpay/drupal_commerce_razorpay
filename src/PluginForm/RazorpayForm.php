<?php

namespace Drupal\drupal_commerce_razorpay\PluginForm;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Razorpay\Api\Api;
use Symfony\Component\ExpressionLanguage\Tests\Node\Obj;

/**
 * Provides the Off-site payment form.
 */
class RazorpayForm extends BasePaymentOffsiteForm
{
    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);

        return $this->buildRedirectForm($form, $form_state,'',[],self::REDIRECT_POST);
    }

    protected function buildRedirectForm(array $form, FormStateInterface $form_state, $redirect_url, array $data, $redirect_method = self::REDIRECT_GET) {

        return $form;
    }
}

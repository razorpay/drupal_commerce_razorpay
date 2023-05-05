<?php

namespace Drupal\drupal_commerce_razorpay;

use Razorpay\Api\Api;
use Drupal\Core\Url;

class AutoWebhook
{
    protected $supportedWebhookEvents = [
        'payment.authorized' => true,
        'payment.failed'     => true,
        'refund.created'     => true
    ];

    protected $defaultWebhookEvents = [
        'payment.authorized' => true,
        'payment.failed'     => true,
        'refund.created'     => true
    ];

    protected function generateWebhookSecret()
    {
        $alphanumericString = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-=~!@#$%^&*()_+,./<>?;:[]{}|abcdefghijklmnopqrstuvwxyz';

        return substr(str_shuffle($alphanumericString), 0, 20);
    }

    public function autoEnableWebhook($key_id, $key_secret)
    {
        try
        {
            $domainIp = gethostbyname(\Drupal::request()->getHost());

            if (!filter_var($domainIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
            {
                \Drupal::messenger()->addError(t('Could not enable webhook for localhost'));

                return;
            }

            drupal_flush_all_caches();

            $config_factory = \Drupal::configFactory();
            $config = $config_factory->getEditable('drupal_commerce_razorpay.settings');

            $settingFlags = $config->get('razorpay_flags');

            $webhookSecret = empty($settingFlags['webhook_secret']) ? $this->generateWebhookSecret() : $settingFlags['webhook_secret'];

            $settingFlags = [
                'webhook_secret' => $webhookSecret,
                'webhook_enable_at' => time()
            ];

            $config->set('razorpay_flags', $settingFlags)->save();

            $skip = 0;
            $count = 10;
            $webhookItems= [];
            $webhookExist = false;
            $webhookUrl = Url::fromRoute(
                'commerce_payment.notify',
                ['commerce_payment_gateway' => 'razorpay'],
                ['absolute' => TRUE]
            )->toString();

            $api = new Api($key_id, $key_secret);

            do {
                $options = [
                    'count' => $count,
                    'skip' => $skip
                ];

                $webhooks = $api->webhook->all($options);
                $skip += 10;

                if ($webhooks['count'] > 0)
                {
                    foreach ($webhooks['items'] as $key => $value)
                    {
                        $webhookItems[] = $value;
                    }
                }
            } while ( $webhooks['count'] === $count);

            $requestBody = [
                'url'    => $webhookUrl,
                'active' => true,
                'events' => $this->defaultWebhookEvents,
                'secret' => $webhookSecret,
            ];

            if (count($webhookItems) > 0)
            {
                foreach ($webhookItems as $key => $value)
                {
                    if ($value['url'] === $webhookUrl)
                    {
                        foreach ($value['events'] as $evntkey => $evntval)
                        {
                            if (($evntval == 1) and
                                (in_array($evntkey, $this->supportedWebhookEvents) === true))
                            {
                                $this->defaultWebhookEvents[$evntkey] =  true;
                            }
                        }
                        $webhookExist  = true;
                        $webhookId     = $value['id'];
                    }
                }
            }
            if ($webhookExist)
            {
                //updating webhook
                \Drupal::logger('RazorpayAutoWebhook')->info('Updating razorpay webhook');
                return $api->webhook->edit($requestBody, $webhookId);
            }
            else
            {
                //creating webhook
                \Drupal::logger('RazorpayAutoWebhook')->info('Creating razorpay webhook');
                return $api->webhook->create($requestBody);
            }
        }
        catch (\Exception $exception)
        {
            \Drupal::messenger()->addError(t('RazorpayAutoWebhook: ' . $exception->getMessage()));
            \Drupal::logger('RazorpayAutoWebhook')->error($exception->getMessage());
        }
    }
}

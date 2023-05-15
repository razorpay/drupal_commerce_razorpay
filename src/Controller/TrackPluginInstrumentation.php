<?php

namespace Drupal\drupal_commerce_razorpay\Controller;

use Razorpay\Api\Api;
use Razorpay\Api\Errors;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use GuzzleHttp\Client;

class TrackPluginInstrumentation extends BasePaymentOffsiteForm
{
    protected $api;
    protected $mode;
    protected $config;

    public function __construct($api = '', $key = '')
    {
        $this->api = $api;
        $this->mode = (substr($key, 0, 8) === 'rzp_live') ? 'live' : 'test';
    }

    function razorpayPluginInstall()
    {
        $activateProperties = [
            'page_url'            => $_SERVER['HTTP_REFERER'],
            'redirect_to_page'    => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
        ];
        $this->rzpTrackDataLake('plugin activate', $activateProperties);

        return 'success';
    }

    function razorpayPluginUninstall()
    {
        $isTransactingUser = false;
        
        $query = \Drupal::database()->query("SELECT order_number FROM commerce_order WHERE payment_gateway = :gateway",[':gateway' => 'razorpay']);
        
        $data = $query->fetchField();

        if (empty($data) === false and
            ($data == null) === false)
        {
            $isTransactingUser = true;
        }

        $deactivateProperties = [
            'page_url'            => $_SERVER['HTTP_REFERER'],
            'is_transacting_user' => $isTransactingUser
        ];

        $this->rzpTrackDataLake('plugin deactivate', $deactivateProperties);

        return 'success';
    }

    function razorpayPaymentDeleted()
    {
        $isTransactingUser = false;
        
        $query = \Drupal::database()->query("SELECT order_number FROM commerce_order WHERE payment_gateway = :gateway",[':gateway' => 'razorpay']);
        
        $data = $query->fetchField();

        if (empty($data) === false and
            ($data == null) === false)
        {
            $isTransactingUser = true;
        }

        $deactivateProperties = [
            'page_url'            => $_SERVER['HTTP_REFERER'],
            'is_transacting_user' => $isTransactingUser
        ];

        $this->rzpTrackDataLake('plugin payment deleted', $deactivateProperties);

        return 'success';
    }

    public function rzpTrackDataLake($event, $properties)
    {
        try
        {
            if (empty($event) === true or
                is_string($event) === false)
            {
                return ['status' => 'error', 'message' => 'event given as input is not valid'];
            }

            if (empty($properties) === true or
                is_array($properties) === false)
            {
                return ['status' => 'error', 'message' => 'properties given as input is not valid'];
            }

            $requestArgs = [
                'timeout'   => 45,
                'headers'   => [
                    'Content-Type'  => 'application/json'
                ],
                'body'      => json_encode(
                    [
                        'mode'   => $this->mode,
                        'key'    => '2Ea4C263F7bb3f3AF7630DC5db9e38ff',
                        'events' => [
                            [
                                'event_type'    => 'plugin-events',
                                'event_version' => 'v1',
                                'timestamp'     => time(),
                                'event'         => str_replace(' ', '.', $event),
                                'properties'    => array_merge($properties, $this->getDefaultProperties(false))
                            ]
                        ]
                    ]
                ),
            ];

            $response = $this->make_drupal_post_request('https://lumberjack.stage.razorpay.in/v1/track', $requestArgs);

            return [
                'payload' => json_decode($response),
                'status'  => 'success',
                'message' => 'Pushed data to lumberjack',
            ];
        }
        catch (\Razorpay\Api\Errors\Error $e)
        {
            \Drupal::logger('error')->error($e->getMessage());
        }
        catch (\Exception $e)
        {
            \Drupal::logger('error')->error($e->getMessage());
        }
    }

    public function getDefaultProperties($timestamp = true)
    {
        $defaultProperties = [
            'platform'            => 'Drupal',
            'platform_version'    => \Drupal::VERSION,
            'plugin_name'         => 'drupal_commerce_razorpay',
            'unique_id'           => $_SERVER['HTTP_HOST']
        ];

        if ($timestamp)
        {
            $defaultProperties['event_timestamp'] = time();
        }

        return $defaultProperties;
    }

    public function make_drupal_post_request($url, $data = array()) 
    {
        $client_factory = \Drupal::service('http_client_factory');

        $client = $client_factory->fromOptions([
            'headers' => $data['headers'],
        ]);

        $response = '';

        try {
            $guzzleResponse = $client->request('POST', 'https://lumberjack.stage.razorpay.in/v1/track', $data);

            if ($guzzleResponse->getStatusCode() == 200) {
                $response = $guzzleResponse->getBody()->getContents();
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Drupal::logger('error')->error($e->getMessage());
        }

        return new Response($response, 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}

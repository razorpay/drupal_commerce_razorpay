<?php

namespace Drupal\drupal_commerce_razorpay\Controller;

use Razorpay\Api\Api;
use Razorpay\Api\Errors;
use Drupal\Core\Http\ClientFactory;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;

class TrackPluginInstrumentation extends BasePaymentOffsiteForm
{
    protected $api;
    protected $mode;
    protected $config;

    public function __construct($api, $key)
    {
        $this->api = $api;
        $this->mode = (substr($key, 0, 8) === 'rzp_live') ? 'live' : 'test';
        if(\Drupal::moduleHandler()->moduleExists('drupal_commerce_razorpay'))
        {
            $this->razorpayPluginActivated($key);
        }
    }

    function razorpayPluginActivated($key)
    {
        $activateProperties = [
            'page_url'            => $_SERVER['HTTP_REFERER'],
            'redirect_to_page'    => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
        ];
        $this->rzpTrackDataLake('plugin activate', $activateProperties);
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
                                'event_type'    => 'drupal',
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

            // if (is_wp_error($response))
            // {
            //     error_log($response->get_error_message());
            // }

            return [
                'payload' => json_decode($response),
                'status'  => 'success',
                'message' => 'Pushed data to lumberjack',
            ];
        }
        catch (\Razorpay\Api\Errors\Error $e)
        {
            error_log($e->getMessage());
        }
        catch (\Exception $e)
        {
            error_log($e->getMessage());
        }
    }

    public function getDefaultProperties($timestamp = true)
    {

        $defaultProperties = [
            'platform'            => 'Drupal',
            'platform_version'    => \Drupal::VERSION,
            // 'drupalcommerce_version' => WOOCOMMERCE_VERSION,
            // 'plugin_name'         => $pluginData['Name'],
            // 'plugin_version'      => $pluginData['Version'],
            'unique_id'           => $_SERVER['HTTP_HOST']
        ];

        if ($timestamp)
        {
            $defaultProperties['event_timestamp'] = time();
        }

        return $defaultProperties;
    }

    // public function make_drupal_post_request($url, $data = array(), $headers = array()) {
    //     // Get the HTTP client service
    //     $client_factory = \Drupal::service('http_client_factory');

    //     $client = $client_factory->fromOptions([
    //       'headers' => $headers,
    //     ]);

    //     // Make the request
    //     // $request = $client->post($url, [
    //     //   'headers' => $headers,
    //     //   'json' => $data,
    //     // ]);
    //     $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
    //     fwrite($myfile, json_encode($client_factory));
    //     fclose($myfile);
    //     return true;
    //     // Get the response
    //     $response = $request->getBody()->getContents();
    //     \Drupal::logger('example')->info($response);
    //     return $response;
    // }

    // public function make_drupal_post_request($url, $data = array(), $headers = array()) 
    // {
    //     $client = HttpClient::create();
    //     $response = $client->request('POST', $url, [
    //     'headers' => $headers,
    //     'json' => $data
    //     ]);
    //     $status_code = $response->getStatusCode();
    //     $content = $response->getContent();

    //     $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
    //     fwrite($myfile, json_encode($response));
    //     fclose($myfile);
    //     return true;
    // }

    public function make_drupal_post_request($url, $data = array(), $headers = array()) {
        // Get the HTTP client service
        $client_factory = \Drupal::service('http_client_factory');
    
        $client = $client_factory->fromOptions([
          'headers' => $headers,
        ]);
    
        $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
        fwrite($myfile, json_encode($client_factory));
        fclose($myfile);
        return true;

        // Make the request
        $request = $client->post($url, [
          'headers' => $headers,
          'json' => $data,
        ]);
    
        

        // Get the response
        $response = $request->getBody()->getContents();
        \Drupal::logger('example')->info($response);
        return $response;
    }
}

<?php

namespace Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway\Unit;

require_once __DIR__ . '/../../../src/Controller/TrackPluginInstrumentation.php';

use PHPUnit\Framework\TestCase;
use Mockery;
use Drupal\drupal_commerce_razorpay\Controller\TrackPluginInstrumentation;

class TestTrackPluginInstrumentation extends TestCase
{
    protected $instance;

    public function setUp():void
    {
        $this->instance = Mockery::mock('TrackPluginInstrumentation')->makePartial()->shouldAllowMockingProtectedMethods();
    }

    public function testRazorpayPluginInstall()
    {
        $this->instance->shouldReceive('rzpTrackDataLake')->with('plugin activate', [])->andReturn('success');
        $response = $this->instance->razorpayPluginInstall();
        $this->assertSame('success', $response);
    }

    /**
     * Once test method has finished running, whether it succeeded or failed, tearDown() will be invoked.
     * Unset the $unit object.
     */
    public function tearDown():void
    {
        unset($this->autoWebhook);
    }
}



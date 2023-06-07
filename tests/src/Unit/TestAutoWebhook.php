<?php

namespace Drupal\drupal_commerce_razorpay\Plugin\Commerce\PaymentGateway\Unit;

require_once __DIR__ . '/../../../src/AutoWebhook.php';

use PHPUnit\Framework\TestCase;
use Mockery;
use Drupal\drupal_commerce_razorpay\AutoWebhook;

class TestAutoWebhook extends TestCase
{
    protected $autoWebhook;
    protected $instance;

    public function setUp():void
    {
        $this->autoWebhook = new AutoWebhook();
        $this->instance = Mockery::mock('AutoWebhook')->makePartial()->shouldAllowMockingProtectedMethods();
    }

    public function testGenerateWebhookSecret()
    {
        $response = $this->autoWebhook->generateWebhookSecret();
        $this->assertSame(20, strlen($response));
    }

    public function testGenerateWebhookSecretMock()
    {
        $this->instance->shouldReceive('generateWebhookSecret')->andReturn('hello');
        $response = $this->instance->generateWebhookSecret();
        $this->assertSame('hello', $response);
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



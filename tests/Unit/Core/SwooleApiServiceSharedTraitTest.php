<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\Controller;
use Gemvc\Core\ControllerTracingProxy;
use Gemvc\Core\SwooleApiService;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use PHPUnit\Framework\TestCase;

class TestSwooleApiService extends SwooleApiService
{
    public function ping(): JsonResponse
    {
        return \Gemvc\Http\Response::success(['ok' => true]);
    }
}

/**
 * Phase 2: SwooleApiService shares callController / requireAuth via ApiServiceSharedTrait.
 */
final class SwooleApiServiceSharedTraitTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/Test';
        $ar = new ApacheRequest();
        $this->request = $ar->request;
    }

    public function testCallControllerReturnsProxy(): void
    {
        $service = new TestSwooleApiService($this->request);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('callController');

        $controller = $this->createMock(Controller::class);
        $proxy = $method->invoke($service, $controller);

        $this->assertInstanceOf(ControllerTracingProxy::class, $proxy);
    }

    public function testCallWithTracingReturnsProxy(): void
    {
        $service = new TestSwooleApiService($this->request);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('callWithTracing');

        $controller = $this->createMock(Controller::class);
        $proxy = $method->invoke($service, $controller);

        $this->assertInstanceOf(ControllerTracingProxy::class, $proxy);
    }

    public function testUsesSharedTrait(): void
    {
        $traits = class_uses(SwooleApiService::class);
        $this->assertIsArray($traits);
        $this->assertArrayHasKey(\Gemvc\Core\ApiServiceSharedTrait::class, $traits);

        $apiTraits = class_uses(\Gemvc\Core\ApiService::class);
        $this->assertIsArray($apiTraits);
        $this->assertArrayHasKey(\Gemvc\Core\ApiServiceSharedTrait::class, $apiTraits);
    }
}

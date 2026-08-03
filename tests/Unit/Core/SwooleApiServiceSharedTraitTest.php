<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\ApiService;
use Gemvc\Core\Controller;
use Gemvc\Core\ControllerTracingProxy;
use Gemvc\Core\ProtectedApiService;
use Gemvc\Core\ProtectedSwooleApiService;
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
 * Phase 2–3: SwooleApiService extends ApiService; shared helpers inherited.
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

    public function testSwooleApiServiceExtendsApiService(): void
    {
        $this->assertTrue(is_subclass_of(SwooleApiService::class, ApiService::class));
        $this->assertTrue(is_subclass_of(ProtectedSwooleApiService::class, ProtectedApiService::class));
        $this->assertTrue(is_subclass_of(ProtectedSwooleApiService::class, ApiService::class));

        $service = new TestSwooleApiService($this->request);
        $this->assertInstanceOf(ApiService::class, $service);
        $this->assertInstanceOf(SwooleApiService::class, $service);
    }
}

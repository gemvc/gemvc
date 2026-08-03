<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\FrankenPhpBootstrap;
use Gemvc\Core\FrankenPhpWorker;
use Gemvc\Core\SecurityManager;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Gemvc\Core\FrankenPhpBootstrap
 * @covers \Gemvc\Core\FrankenPhpWorker
 * @covers \Gemvc\Core\SecurityManager
 */
final class FrankenPhpWorkerTest extends TestCase
{
    public function testBootstrapRoutesApiHopLikeClassic(): void
    {
        $request = new Request();
        $request->requestedUrl = '/api/User/list';
        $bootstrap = new FrankenPhpBootstrap($request);

        $this->assertSame('User', $request->getServiceName());
        $this->assertSame('list', $request->getMethodName());
    }

    public function testBootstrapRoutesRootToIndex(): void
    {
        $request = new Request();
        $request->requestedUrl = '/';
        new FrankenPhpBootstrap($request);

        $this->assertSame('Index', $request->getServiceName());
        $this->assertSame('index', $request->getMethodName());
    }

    public function testBootstrapRejectsNonApiPaths(): void
    {
        $request = new Request();
        $request->requestedUrl = '/Developer/app';
        $bootstrap = new FrankenPhpBootstrap($request);
        $response = $bootstrap->processRequest();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->response_code);
        $this->assertStringContainsString('worker serves /api/', (string) $response->service_message);
    }

    public function testJsonResponseShowDoesNotDieOnEmptyPayload(): void
    {
        $response = new JsonResponse();
        $response->response_code = 500;
        // Force unset/false json body path without infinite recursion
        $response->json_response = false;

        ob_start();
        $response->show();
        $out = (string) ob_get_clean();

        $this->assertNotSame('', $out);
        $this->assertSame(500, $response->response_code);
    }

    public function testSecurityManagerEmitForbidden(): void
    {
        $security = new SecurityManager();
        $this->assertFalse($security->isRequestAllowed('/app/model/UserModel.php'));

        ob_start();
        $security->emitForbidden();
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('Access Denied', $out);
    }

    public function testWorkerRunRequiresFrankenphpFunction(): void
    {
        if (function_exists('frankenphp_handle_request')) {
            $this->markTestSkipped('Running under FrankenPHP — cannot assert missing SAPI helper');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('frankenphp_handle_request()');
        (new FrankenPhpWorker())->run();
    }

    public function testResponseFactoryStillWorksForWorkerEmit(): void
    {
        $json = Response::success(['ok' => true], 1, 'ok');
        $this->assertInstanceOf(JsonResponse::class, $json);
        ob_start();
        $json->show();
        $out = (string) ob_get_clean();
        $this->assertStringContainsString('ok', $out);
    }
}

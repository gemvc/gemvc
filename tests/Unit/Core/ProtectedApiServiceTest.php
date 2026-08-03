<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\AuthException;
use Gemvc\Core\ProtectedApiService;
use Gemvc\Core\ProtectedSwooleApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TestProtectedApiService extends ProtectedApiService
{
    public function ping(): JsonResponse
    {
        return Response::success(['ok' => true]);
    }
}

final class TestProtectedSwooleApiService extends ProtectedSwooleApiService
{
    public function ping(): JsonResponse
    {
        return Response::success(['ok' => true]);
    }
}

final class ProtectedApiServiceTest extends TestCase
{
    public function testProtectedApiServiceConstructSucceedsWhenAuthPasses(): void
    {
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->expects($this->once())
            ->method('auth')
            ->with(['admin'])
            ->willReturn(true);

        $service = new TestProtectedApiService($mockRequest, ['admin']);
        $this->assertInstanceOf(ProtectedApiService::class, $service);
        $this->assertInstanceOf(TestProtectedApiService::class, $service);
    }

    public function testProtectedApiServiceConstructThrowsWhenAuthFails(): void
    {
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->expects($this->once())
            ->method('auth')
            ->with(null)
            ->willReturn(false);
        $mockRequest->expects($this->once())
            ->method('returnResponse')
            ->willReturn(Response::unauthorized('Authentication token not found'));

        $this->expectException(AuthException::class);
        new TestProtectedApiService($mockRequest, null);
    }

    public function testProtectedSwooleApiServiceConstructSucceedsWhenAuthPasses(): void
    {
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        // Shared trait: requireAuth(null) → auth(null) (same as Apache path)
        $mockRequest->expects($this->once())
            ->method('auth')
            ->with(null)
            ->willReturn(true);

        $service = new TestProtectedSwooleApiService($mockRequest, null);
        $this->assertInstanceOf(ProtectedSwooleApiService::class, $service);
    }

    public function testProtectedSwooleApiServiceConstructThrowsWhenWrongRole(): void
    {
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->expects($this->once())
            ->method('auth')
            ->with(['admin'])
            ->willReturn(false);
        $mockRequest->expects($this->once())
            ->method('returnResponse')
            ->willReturn(Response::forbidden('Role user not allowed'));

        try {
            new TestProtectedSwooleApiService($mockRequest, ['admin']);
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }
}

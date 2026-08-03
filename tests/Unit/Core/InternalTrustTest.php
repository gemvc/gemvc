<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\ApiService;
use Gemvc\Core\InternalServiceException;
use Gemvc\Core\InternalTrust;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;
use PHPUnit\Framework\TestCase;

final class InternalGuardedApiService extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->requireInternalService();
    }

    public function ping(): JsonResponse
    {
        return Response::success(['ok' => true]);
    }
}

final class InternalTrustTest extends TestCase
{
    private const SECRET = 'test-family-secret-please-change';

    protected function setUp(): void
    {
        parent::setUp();
        unset($_ENV[InternalTrust::ENV_SECRET], $_ENV[InternalTrust::ENV_SECRET_PREVIOUS], $_ENV[InternalTrust::ENV_SKEW_SECONDS]);
        putenv(InternalTrust::ENV_SECRET);
        putenv(InternalTrust::ENV_SECRET_PREVIOUS);
        putenv(InternalTrust::ENV_SKEW_SECONDS);
    }

    protected function tearDown(): void
    {
        unset($_ENV[InternalTrust::ENV_SECRET], $_ENV[InternalTrust::ENV_SECRET_PREVIOUS], $_ENV[InternalTrust::ENV_SKEW_SECONDS]);
        putenv(InternalTrust::ENV_SECRET);
        putenv(InternalTrust::ENV_SECRET_PREVIOUS);
        putenv(InternalTrust::ENV_SKEW_SECONDS);
        parent::tearDown();
    }

    public function testCanonicalPathStripsQuery(): void
    {
        $this->assertSame('/api/Auth/oauthLogin', InternalTrust::canonicalPath('/api/Auth/oauthLogin?x=1'));
        $this->assertSame('/api/Auth/oauthLogin', InternalTrust::canonicalPath('http://host/api/Auth/oauthLogin?x=1'));
        $this->assertSame('/', InternalTrust::canonicalPath(''));
    }

    public function testSignIsDeterministicAndUsesHashEqualsCompatibleHex(): void
    {
        $sig1 = InternalTrust::sign('POST', '/api/Auth/oauthLogin', 1700000000, '{"a":1}', self::SECRET);
        $sig2 = InternalTrust::sign('post', '/api/Auth/oauthLogin?ignored=1', 1700000000, '{"a":1}', self::SECRET);
        $this->assertSame(64, strlen($sig1));
        $this->assertTrue(hash_equals($sig1, $sig2));
    }

    public function testEnforcePassesWithValidHmac(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        $ts = time();
        $body = '{"email":"a@b.c"}';
        $path = '/api/Auth/oauthLogin';
        $request = $this->makeRequest('POST', $path, $body, [
            InternalTrust::HEADER_TIMESTAMP => (string) $ts,
            InternalTrust::HEADER_SIGNATURE => InternalTrust::sign('POST', $path, $ts, $body, self::SECRET),
        ]);

        InternalTrust::enforce($request);
        $this->assertTrue(true);
    }

    public function testEnforceAcceptsPreviousSecret(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = 'new-secret';
        $_ENV[InternalTrust::ENV_SECRET_PREVIOUS] = self::SECRET;
        $ts = time();
        $body = '';
        $path = '/api/Auth/oauthLogin';
        $request = $this->makeRequest('GET', $path, $body, [
            InternalTrust::HEADER_TIMESTAMP => (string) $ts,
            InternalTrust::HEADER_SIGNATURE => InternalTrust::sign('GET', $path, $ts, $body, self::SECRET),
        ]);

        InternalTrust::enforce($request);
        $this->assertTrue(true);
    }

    public function testEnforceFailsClosedWithoutSecret(): void
    {
        $this->expectException(InternalServiceException::class);
        $this->expectExceptionCode(500);
        try {
            InternalTrust::enforce($this->makeRequest('GET', '/api/X/y', '', []));
        } catch (InternalServiceException $e) {
            $this->assertSame(InternalServiceException::CODE_MISCONFIGURED, $e->errorCode);
            throw $e;
        }
    }

    public function testEnforceRejectsBadSignature(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        $this->expectException(InternalServiceException::class);
        $this->expectExceptionCode(401);
        try {
            InternalTrust::enforce($this->makeRequest('POST', '/api/Auth/oauthLogin', '{}', [
                InternalTrust::HEADER_TIMESTAMP => (string) time(),
                InternalTrust::HEADER_SIGNATURE => str_repeat('0', 64),
            ]));
        } catch (InternalServiceException $e) {
            $this->assertSame(InternalServiceException::CODE_FAILED, $e->errorCode);
            throw $e;
        }
    }

    public function testEnforceRejectsExpiredTimestamp(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        $_ENV[InternalTrust::ENV_SKEW_SECONDS] = '60';
        $ts = time() - 120;
        $body = '';
        $path = '/api/Auth/oauthLogin';
        $this->expectException(InternalServiceException::class);
        $this->expectExceptionCode(401);
        InternalTrust::enforce($this->makeRequest('GET', $path, $body, [
            InternalTrust::HEADER_TIMESTAMP => (string) $ts,
            InternalTrust::HEADER_SIGNATURE => InternalTrust::sign('GET', $path, $ts, $body, self::SECRET),
        ]));
    }

    public function testEndUserJwtHeadersDoNotSatisfyInternalTrust(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        $this->expectException(InternalServiceException::class);
        $this->expectExceptionCode(401);
        InternalTrust::enforce($this->makeRequest('GET', '/api/Auth/oauthLogin', '', [
            'authorization' => 'Bearer eyJhbGciOiJIUzI1NiJ9.e30.signature',
        ]));
    }

    public function testRequireInternalServiceOnApiService(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        $ts = time();
        $path = '/api/InternalGuarded/ping';
        $body = '';
        $request = $this->makeRequest('GET', $path, $body, [
            InternalTrust::HEADER_TIMESTAMP => (string) $ts,
            InternalTrust::HEADER_SIGNATURE => InternalTrust::sign('GET', $path, $ts, $body, self::SECRET),
        ]);

        $service = new InternalGuardedApiService($request);
        $this->assertInstanceOf(ApiService::class, $service);
    }

    public function testCallerHeadersRoundTrip(): void
    {
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        $path = '/api/Auth/oauthLogin';
        $body = '{"x":1}';
        $headers = InternalTrust::callerHeaders('POST', $path, $body);
        $request = $this->makeRequest('POST', $path, $body, [
            InternalTrust::HEADER_TIMESTAMP => $headers['X-Gemvc-Internal-Timestamp'],
            InternalTrust::HEADER_SIGNATURE => $headers['X-Gemvc-Internal-Signature'],
        ]);
        InternalTrust::enforce($request);
        $this->assertTrue(true);
    }

    /**
     * @param array<string, string> $headers
     */
    private function makeRequest(string $method, string $url, string $rawBody, array $headers): Request
    {
        $request = new Request();
        $request->requestMethod = $method;
        $request->requestedUrl = $url;
        $request->rawBody = $rawBody;
        $request->headers = $headers;

        return $request;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gemvc\Core\ApiService;
use Gemvc\Core\RateLimiter;
use Gemvc\Core\RateLimitException;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\ApacheRequest;

class RateLimitGuardedApiService extends ApiService
{
    public function __construct(Request $request, int $perSec = 2, string $scope = 'ip')
    {
        parent::__construct($request);
        $this->requireRateLimit($perSec, $scope, 30);
    }

    public function create(): JsonResponse
    {
        return \Gemvc\Http\Response::success(['created' => true]);
    }
}

/**
 * @outputBuffering enabled
 */
class RateLimiterTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        RateLimiter::useInMemoryStoreForTests(true);

        unset(
            $_ENV['REQUEST_RATE_LIMIT_PER_SEC'],
            $_ENV['REQUEST_RATE_LIMIT_BLOCK_SECONDS'],
            $_ENV['REQUEST_RATE_LIMIT_SCOPE']
        );
        putenv('REQUEST_RATE_LIMIT_PER_SEC');
        putenv('REQUEST_RATE_LIMIT_BLOCK_SECONDS');
        putenv('REQUEST_RATE_LIMIT_SCOPE');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/Test/list';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254);

        $ar = new ApacheRequest();
        $this->request = $ar->request;
        $this->request->remoteAddress = $_SERVER['REMOTE_ADDR'];
        $this->request->requestedUrl = '/api/Test/list';
        $this->request->jwtTokenStringInHeader = null;
        $this->request->authorizationHeader = null;
    }

    protected function tearDown(): void
    {
        RateLimiter::useInMemoryStoreForTests(false);
        parent::tearDown();
    }

    public function testIsAvailableTrueWithInMemoryStore(): void
    {
        $this->assertTrue(RateLimiter::isAvailable());
    }

    public function testHitAllowsUntilLimitThenBlocks(): void
    {
        $key = 'ip:test-' . uniqid('', true);
        $this->assertTrue(RateLimiter::hit($key, 2, 30));
        $this->assertTrue(RateLimiter::hit($key, 2, 30));
        $this->assertFalse(RateLimiter::hit($key, 2, 30));
        $this->assertTrue(RateLimiter::isBlocked($key));
    }

    public function testBlockAndUnblock(): void
    {
        $key = 'ip:block-' . uniqid('', true);
        RateLimiter::block($key, 60);
        $this->assertTrue(RateLimiter::isBlocked($key));
        RateLimiter::unblock($key);
        $this->assertFalse(RateLimiter::isBlocked($key));
    }

    public function testEnforcePassesUnderLimit(): void
    {
        RateLimiter::enforce($this->request, 5, RateLimiter::SCOPE_IP, 30, 'test');
        RateLimiter::enforce($this->request, 5, RateLimiter::SCOPE_IP, 30, 'test');
        $this->assertTrue(true);
    }

    public function testEnforceThrowsWhenLimitExceeded(): void
    {
        $this->expectException(RateLimitException::class);
        $this->expectExceptionCode(429);

        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
    }

    public function testEnforceThrowsWhenAlreadyBlocked(): void
    {
        $ip = $this->request->remoteAddress ?? 'unknown';
        RateLimiter::block('ip:' . $ip, 60);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('temporarily blocked');
        RateLimiter::enforce($this->request, 100, RateLimiter::SCOPE_IP, 30, 'test');
    }

    public function testEnforceScopeTokenUsesJwtHeader(): void
    {
        $this->request->jwtTokenStringInHeader = 'test.jwt.token.' . uniqid('', true);

        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_TOKEN, 30, 'test');

        $this->expectException(RateLimitException::class);
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_TOKEN, 30, 'test');
    }

    public function testEnforceScopeTokenFallsBackToIpWithoutToken(): void
    {
        $this->request->jwtTokenStringInHeader = null;
        $this->request->authorizationHeader = null;

        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_TOKEN, 30, 'test');

        $this->expectException(RateLimitException::class);
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_TOKEN, 30, 'test');
    }

    public function testEnforceZeroLimitIsNoOp(): void
    {
        RateLimiter::enforce($this->request, 0, RateLimiter::SCOPE_IP, 30, 'test');
        RateLimiter::enforce($this->request, 0, RateLimiter::SCOPE_IP, 30, 'test');
        $this->assertTrue(true);
    }

    public function testEnforceFromEnvNoOpWhenUnset(): void
    {
        RateLimiter::enforceFromEnv($this->request);
        RateLimiter::enforceFromEnv($this->request);
        $this->assertTrue(true);
    }

    public function testEnforceFromEnvNoOpWhenZero(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_PER_SEC'] = '0';
        RateLimiter::enforceFromEnv($this->request);
        RateLimiter::enforceFromEnv($this->request);
        $this->assertTrue(true);
    }

    public function testEnforceFromEnvThrowsWhenExceeded(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_PER_SEC'] = '1';
        $_ENV['REQUEST_RATE_LIMIT_BLOCK_SECONDS'] = '30';
        $_ENV['REQUEST_RATE_LIMIT_SCOPE'] = 'ip';

        RateLimiter::enforceFromEnv($this->request);

        $this->expectException(RateLimitException::class);
        RateLimiter::enforceFromEnv($this->request);
    }

    public function testFailOpenWhenStoreDisabled(): void
    {
        RateLimiter::useInMemoryStoreForTests(false);
        // Force "unavailable" path: no memory store and (typically) no APCu in CLI
        if (RateLimiter::isAvailable()) {
            $this->markTestSkipped('APCu is enabled — cannot assert fail-open on this host');
        }

        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        $this->assertTrue(RateLimiter::hit('ip:failopen', 1, 30));
    }

    public function testFullCachePurgesAndRetriesSuccessfully(): void
    {
        $key = 'ip:full-retry-' . uniqid('', true);
        RateLimiter::hit($key, 10, 30);
        RateLimiter::forceWriteFailuresForTests(1);

        // One forced failure → purge → retry succeeds (counter resets after purge)
        $this->assertTrue(RateLimiter::hit($key, 10, 30));
        $this->assertFalse(RateLimiter::isBlocked($key));
    }

    public function testFullCacheFailsClosedAfterPurgeRetryStillFails(): void
    {
        $key = 'ip:full-deny-' . uniqid('', true);
        RateLimiter::forceWriteFailuresForTests(2);

        $this->assertFalse(RateLimiter::hit($key, 10, 30));

        $this->expectException(RateLimitException::class);
        RateLimiter::forceWriteFailuresForTests(2);
        RateLimiter::enforce($this->request, 10, RateLimiter::SCOPE_IP, 30, 'test');
    }

    public function testRecoverFromFullCacheClearsMemoryStore(): void
    {
        $key = 'ip:purge-' . uniqid('', true);
        RateLimiter::hit($key, 10, 30);
        RateLimiter::block($key, 30);
        $this->assertGreaterThan(0, RateLimiter::recoverFromFullCache());
        $this->assertFalse(RateLimiter::isBlocked($key));
    }

    public function testRequireRateLimitOnApiServiceThrowsOnExceed(): void
    {
        $service = new class ($this->request) extends ApiService {
            public function ping(): JsonResponse
            {
                $this->requireRateLimit(1, 'ip', 30);
                return \Gemvc\Http\Response::success(['ok' => true]);
            }
        };

        $service->ping();

        $this->expectException(RateLimitException::class);
        $service->ping();
    }

    public function testRequireRateLimitInConstructorThrowsBeforeMethod(): void
    {
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'warmup');
        // Second construction should hit the limit inside constructor
        $this->expectException(RateLimitException::class);
        new RateLimitGuardedApiService($this->request, 1, 'ip');
    }

    public function testRequireRateLimitInConstructorAllowsMethodUnderLimit(): void
    {
        $service = new RateLimitGuardedApiService($this->request, 5, 'ip');
        $response = $service->create();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(['created' => true], $response->data);
    }
}

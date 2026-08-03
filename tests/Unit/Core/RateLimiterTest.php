<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gemvc\Core\ApiService;
use Gemvc\Core\RateLimiter;
use Gemvc\Core\RateLimitException;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\StandardHttpRequest;

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

class RateLimitMethodGuardedApiService extends ApiService
{
    public function ping(): JsonResponse
    {
        $this->requireRateLimit(1, 'ip', 30);
        return \Gemvc\Http\Response::success(['ok' => true]);
    }
}

class RateLimitApcuOverrideApiService extends ApiService
{
    public function ping(): JsonResponse
    {
        $this->requireRateLimitApcu(1, 'ip', 30);
        return \Gemvc\Http\Response::success(['ok' => true]);
    }
}

class RateLimitRedisOverrideApiService extends ApiService
{
    public function ping(): JsonResponse
    {
        $this->requireRateLimitRedis(1, 'ip', 30);
        return \Gemvc\Http\Response::success(['ok' => true]);
    }
}

class RateLimitGlobalDriverApiService extends ApiService
{
    public function ping(): JsonResponse
    {
        $this->requireRateLimit(1, 'ip', 30);
        return \Gemvc\Http\Response::success(['ok' => true]);
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
        RateLimiter::useRedisMemoryStoreForTests(false);

        unset(
            $_ENV['REQUEST_RATE_LIMIT_PER_SEC'],
            $_ENV['REQUEST_RATE_LIMIT_BLOCK_SECONDS'],
            $_ENV['REQUEST_RATE_LIMIT_SCOPE'],
            $_ENV['REQUEST_RATE_LIMIT_FAIL_MODE'],
            $_ENV['REQUEST_RATE_LIMIT_DRIVER']
        );
        putenv('REQUEST_RATE_LIMIT_PER_SEC');
        putenv('REQUEST_RATE_LIMIT_BLOCK_SECONDS');
        putenv('REQUEST_RATE_LIMIT_SCOPE');
        putenv('REQUEST_RATE_LIMIT_FAIL_MODE');
        putenv('REQUEST_RATE_LIMIT_DRIVER');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/Test/list';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254);

        $ar = new StandardHttpRequest();
        $this->request = $ar->request;
        $this->request->remoteAddress = $_SERVER['REMOTE_ADDR'];
        $this->request->requestedUrl = '/api/Test/list';
        $this->request->jwtTokenStringInHeader = null;
        $this->request->authorizationHeader = null;
    }

    protected function tearDown(): void
    {
        RateLimiter::useInMemoryStoreForTests(false);
        RateLimiter::useRedisMemoryStoreForTests(false);
        RateLimiter::forceApcuAvailabilityForTests(null);
        RateLimiter::forceRedisAvailabilityForTests(null);
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

    public function testFailClosedWhenStoreUnavailableByDefault(): void
    {
        RateLimiter::useInMemoryStoreForTests(false);
        RateLimiter::forceAvailabilityForTests(false);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessage('APCu is not available');
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
    }

    public function testFailOpenWhenFailModeOpen(): void
    {
        RateLimiter::useInMemoryStoreForTests(false);
        RateLimiter::forceAvailabilityForTests(false);
        $_ENV['REQUEST_RATE_LIMIT_FAIL_MODE'] = 'open';

        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        $this->assertTrue(RateLimiter::hit('ip:failopen', 1, 30));
    }

    public function testResolveFailModeDefaultsToClosed(): void
    {
        $this->assertSame(RateLimiter::FAIL_MODE_CLOSED, RateLimiter::resolveFailMode());
        $_ENV['REQUEST_RATE_LIMIT_FAIL_MODE'] = 'open';
        $this->assertSame(RateLimiter::FAIL_MODE_OPEN, RateLimiter::resolveFailMode());
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
        $service = new RateLimitMethodGuardedApiService($this->request);

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

    public function testResolveDriverDefaultsToApcu(): void
    {
        $this->assertSame(RateLimiter::DRIVER_APCU, RateLimiter::resolveDriver());
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'redis';
        $this->assertSame(RateLimiter::DRIVER_REDIS, RateLimiter::resolveDriver());
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'both';
        $this->assertSame(RateLimiter::DRIVER_BOTH, RateLimiter::resolveDriver());
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'none';
        $this->assertSame(RateLimiter::DRIVER_NONE, RateLimiter::resolveDriver());
    }

    public function testDriverNoneNoOpsEvenWhenLimitConfigured(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'none';
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        RateLimiter::enforce($this->request, 1, RateLimiter::SCOPE_IP, 30, 'test');
        $this->assertTrue(true);
    }

    public function testEnforceFromEnvNoOpsWhenDriverNone(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_PER_SEC'] = '1';
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'none';
        RateLimiter::enforceFromEnv($this->request);
        RateLimiter::enforceFromEnv($this->request);
        $this->assertTrue(true);
    }

    public function testRedisDriverEnforcesWithMemoryStore(): void
    {
        RateLimiter::useRedisMemoryStoreForTests(true);
        RateLimiter::enforce(
            $this->request,
            1,
            RateLimiter::SCOPE_IP,
            30,
            'test',
            RateLimiter::DRIVER_REDIS
        );

        $this->expectException(RateLimitException::class);
        RateLimiter::enforce(
            $this->request,
            1,
            RateLimiter::SCOPE_IP,
            30,
            'test',
            RateLimiter::DRIVER_REDIS
        );
    }

    public function testRedisUnavailableFailClosedNoApcuFallback(): void
    {
        RateLimiter::useRedisMemoryStoreForTests(false);
        RateLimiter::forceRedisAvailabilityForTests(false);
        // APCu memory store is still available — must NOT silently use it
        $this->assertTrue(RateLimiter::isAvailable(RateLimiter::DRIVER_APCU));

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Redis is not available');
        RateLimiter::enforce(
            $this->request,
            1,
            RateLimiter::SCOPE_IP,
            30,
            'test',
            RateLimiter::DRIVER_REDIS
        );
    }

    public function testBothDriverDeniesWhenEitherExceeds(): void
    {
        RateLimiter::useRedisMemoryStoreForTests(true);
        RateLimiter::enforce(
            $this->request,
            1,
            RateLimiter::SCOPE_IP,
            30,
            'test',
            RateLimiter::DRIVER_BOTH
        );

        $this->expectException(RateLimitException::class);
        RateLimiter::enforce(
            $this->request,
            1,
            RateLimiter::SCOPE_IP,
            30,
            'test',
            RateLimiter::DRIVER_BOTH
        );
    }

    public function testBothUnavailableWhenRedisDown(): void
    {
        RateLimiter::forceRedisAvailabilityForTests(false);
        $this->assertFalse(RateLimiter::isAvailable(RateLimiter::DRIVER_BOTH));

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('driver=both');
        RateLimiter::enforce(
            $this->request,
            1,
            RateLimiter::SCOPE_IP,
            30,
            'test',
            RateLimiter::DRIVER_BOTH
        );
    }

    public function testExplicitApcuOverrideWorksWhenGlobalDriverNone(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'none';
        $service = new RateLimitApcuOverrideApiService($this->request);

        $service->ping();
        $this->expectException(RateLimitException::class);
        $service->ping();
    }

    public function testExplicitRedisOverrideWorksWhenGlobalDriverApcu(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'apcu';
        RateLimiter::useRedisMemoryStoreForTests(true);

        $service = new RateLimitRedisOverrideApiService($this->request);

        $service->ping();
        $this->expectException(RateLimitException::class);
        $service->ping();
    }

    public function testRequireRateLimitUsesGlobalNoneAsNoOp(): void
    {
        $_ENV['REQUEST_RATE_LIMIT_DRIVER'] = 'none';
        $service = new RateLimitGlobalDriverApiService($this->request);

        $service->ping();
        $service->ping();
        $this->assertTrue(true);
    }
}

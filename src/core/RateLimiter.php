<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;

/**
 * Optional rate limiter with explicit storage drivers (no automatic fallback).
 *
 * Drivers (`REQUEST_RATE_LIMIT_DRIVER`):
 *   apcu  — per PHP instance (default)
 *   redis — cluster-wide via RedisManager
 *   both  — simultaneous APCu + Redis; deny if either over/blocked (not failover)
 *   none  — disable global / default requireRateLimit()
 *
 * Never auto-switches Redis↔APCu. Unavailable chosen backend(s) → FAIL_MODE only.
 *
 * Env (Bootstrap global — only when PER_SEC set):
 *   REQUEST_RATE_LIMIT_DRIVER=apcu|redis|both|none
 *   REQUEST_RATE_LIMIT_PER_SEC=20
 *   REQUEST_RATE_LIMIT_BLOCK_SECONDS=60
 *   REQUEST_RATE_LIMIT_SCOPE=both|ip|token
 *   REQUEST_RATE_LIMIT_FAIL_MODE=closed|open
 *
 * DX (ApiService / SwooleApiService):
 *   $this->requireRateLimit();              // uses global driver
 *   $this->requireRateLimitApcu(30, 'ip');  // force APCu
 *   $this->requireRateLimitRedis(5, 'ip');  // force Redis
 *   $this->requireRateLimitBoth(10);        // force dual check
 */
class RateLimiter
{
    public const DEFAULT_PER_SEC = 20;
    public const DEFAULT_BLOCK_SECONDS = 60;
    public const SCOPE_BOTH = 'both';
    public const SCOPE_IP = 'ip';
    public const SCOPE_TOKEN = 'token';
    public const FAIL_MODE_CLOSED = 'closed';
    public const FAIL_MODE_OPEN = 'open';
    public const DRIVER_APCU = 'apcu';
    public const DRIVER_REDIS = 'redis';
    public const DRIVER_BOTH = 'both';
    public const DRIVER_NONE = 'none';
    public const KEY_PREFIX = 'gemvc:rl:';
    /** Redis keys relative to RedisManager OPT_PREFIX (avoid doubling gemvc:). */
    public const REDIS_KEY_PREFIX = 'rl:';

    private static bool $apcuWarningLogged = false;
    private static bool $redisWarningLogged = false;
    private static bool $fullCacheWarningLogged = false;
    private static ?bool $apcuAvailable = null;
    private static ?bool $redisAvailable = null;

    /** @internal Override APCu availability (unit tests). */
    private static ?bool $forcedApcuAvailability = null;

    /** @internal Override Redis availability (unit tests). */
    private static ?bool $forcedRedisAvailability = null;

    /**
     * When non-null, use process memory instead of APCu (unit tests / CI without APCu).
     *
     * @var array{counters: array<string, int>, blocks: array<string, int>}|null
     */
    private static ?array $memoryStore = null;

    /**
     * When non-null, in-process Redis substitute for unit tests.
     *
     * @var array{counters: array<string, int>, blocks: array<string, int>}|null
     */
    private static ?array $redisMemoryStore = null;

    /** @internal Remaining simulated write failures (APCu/memory-store tests). */
    private static int $forcedWriteFailures = 0;

    /**
     * @internal Testing helper — APCu substitute that works without the extension.
     */
    public static function useInMemoryStoreForTests(bool $enable = true): void
    {
        if ($enable) {
            self::$memoryStore = ['counters' => [], 'blocks' => []];
            self::$apcuAvailable = true;
            self::$forcedApcuAvailability = null;
            self::$apcuWarningLogged = true;
            self::$fullCacheWarningLogged = false;
            self::$forcedWriteFailures = 0;
            return;
        }
        self::$memoryStore = null;
        self::$apcuAvailable = null;
        self::$forcedApcuAvailability = null;
        self::$apcuWarningLogged = false;
        self::$fullCacheWarningLogged = false;
        self::$forcedWriteFailures = 0;
    }

    /**
     * @internal Testing helper — Redis substitute without a live server / ext-redis.
     */
    public static function useRedisMemoryStoreForTests(bool $enable = true): void
    {
        if ($enable) {
            self::$redisMemoryStore = ['counters' => [], 'blocks' => []];
            self::$redisAvailable = true;
            self::$forcedRedisAvailability = null;
            self::$redisWarningLogged = true;
            return;
        }
        self::$redisMemoryStore = null;
        self::$redisAvailable = null;
        self::$forcedRedisAvailability = null;
        self::$redisWarningLogged = false;
    }

    /**
     * @internal Force APCu availability; null clears. Alias kept for older tests.
     */
    public static function forceAvailabilityForTests(?bool $available): void
    {
        self::forceApcuAvailabilityForTests($available);
    }

    /**
     * @internal Force APCu isAvailable path; null clears the override.
     */
    public static function forceApcuAvailabilityForTests(?bool $available): void
    {
        self::$forcedApcuAvailability = $available;
        if ($available === false) {
            self::$memoryStore = null;
            self::$apcuAvailable = false;
        } elseif ($available === true) {
            self::$apcuAvailable = true;
        } else {
            self::$apcuAvailable = null;
        }
        self::$apcuWarningLogged = false;
    }

    /**
     * @internal Force Redis availability; null clears.
     */
    public static function forceRedisAvailabilityForTests(?bool $available): void
    {
        self::$forcedRedisAvailability = $available;
        if ($available === false) {
            self::$redisMemoryStore = null;
            self::$redisAvailable = false;
        } elseif ($available === true) {
            self::$redisAvailable = true;
        } else {
            self::$redisAvailable = null;
        }
        self::$redisWarningLogged = false;
    }

    /**
     * @internal Next N APCu/memory counter/block writes fail (full-cache recovery tests).
     */
    public static function forceWriteFailuresForTests(int $count): void
    {
        self::$forcedWriteFailures = max(0, $count);
    }

    /**
     * @return 'apcu'|'redis'|'both'|'none'
     */
    public static function resolveDriver(): string
    {
        $raw = $_ENV['REQUEST_RATE_LIMIT_DRIVER'] ?? getenv('REQUEST_RATE_LIMIT_DRIVER') ?: self::DRIVER_APCU;
        if (!is_string($raw)) {
            return self::DRIVER_APCU;
        }

        return self::normalizeDriver($raw);
    }

    /**
     * @return 'apcu'|'redis'|'both'|'none'
     */
    public static function normalizeDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));

        return match ($driver) {
            self::DRIVER_REDIS => self::DRIVER_REDIS,
            self::DRIVER_BOTH => self::DRIVER_BOTH,
            self::DRIVER_NONE => self::DRIVER_NONE,
            default => self::DRIVER_APCU,
        };
    }

    /**
     * Whether the effective driver’s storage is usable.
     *
     * @param 'apcu'|'redis'|'both'|'none'|string|null $driver null = resolveDriver()
     */
    public static function isAvailable(?string $driver = null): bool
    {
        $driver = self::normalizeDriver($driver ?? self::resolveDriver());

        return match ($driver) {
            self::DRIVER_NONE => false,
            self::DRIVER_REDIS => self::isRedisBackendAvailable(),
            self::DRIVER_BOTH => self::isApcuBackendAvailable() && self::isRedisBackendAvailable(),
            default => self::isApcuBackendAvailable(),
        };
    }

    /**
     * @return 'closed'|'open'
     */
    public static function resolveFailMode(): string
    {
        $raw = $_ENV['REQUEST_RATE_LIMIT_FAIL_MODE'] ?? getenv('REQUEST_RATE_LIMIT_FAIL_MODE') ?: self::FAIL_MODE_CLOSED;
        if (!is_string($raw)) {
            return self::FAIL_MODE_CLOSED;
        }
        $mode = strtolower(trim($raw));

        return $mode === self::FAIL_MODE_OPEN ? self::FAIL_MODE_OPEN : self::FAIL_MODE_CLOSED;
    }

    /**
     * Bootstrap / global guard from env. No-op if PER_SEC unset/empty/0 or DRIVER=none.
     *
     * @throws RateLimitException
     */
    public static function enforceFromEnv(Request $request): void
    {
        $raw = $_ENV['REQUEST_RATE_LIMIT_PER_SEC'] ?? getenv('REQUEST_RATE_LIMIT_PER_SEC') ?: null;
        if ($raw === null || $raw === '' || $raw === false) {
            return;
        }
        if (!is_numeric($raw) || (int) $raw <= 0) {
            return;
        }

        $limit = (int) $raw;
        $block = self::envInt('REQUEST_RATE_LIMIT_BLOCK_SECONDS', self::DEFAULT_BLOCK_SECONDS);
        $scope = self::normalizeScope(
            (string) ($_ENV['REQUEST_RATE_LIMIT_SCOPE'] ?? getenv('REQUEST_RATE_LIMIT_SCOPE') ?: self::SCOPE_BOTH)
        );

        self::enforce($request, $limit, $scope, $block, 'bootstrap', self::resolveDriver());
    }

    /**
     * Enforce limit for this request. Throws RateLimitException on exceed/block.
     *
     * @param 'both'|'ip'|'token'|string $scope
     * @param 'apcu'|'redis'|'both'|'none'|string|null $driver null = REQUEST_RATE_LIMIT_DRIVER
     * @throws RateLimitException
     */
    public static function enforce(
        Request $request,
        int $limitPerSec = self::DEFAULT_PER_SEC,
        string $scope = self::SCOPE_BOTH,
        int $blockSeconds = self::DEFAULT_BLOCK_SECONDS,
        string $context = 'api',
        ?string $driver = null
    ): void {
        if ($limitPerSec <= 0) {
            return;
        }

        $driver = self::normalizeDriver($driver ?? self::resolveDriver());
        if ($driver === self::DRIVER_NONE) {
            return;
        }

        if (!self::isAvailable($driver)) {
            if (self::resolveFailMode() === self::FAIL_MODE_OPEN) {
                return;
            }
            throw new RateLimitException(self::unavailableMessage($driver), 429);
        }

        $scope = self::normalizeScope($scope);
        $blockSeconds = $blockSeconds > 0 ? $blockSeconds : self::DEFAULT_BLOCK_SECONDS;

        $ip = self::clientIp($request);
        $tokenKey = self::tokenKey($request);

        $buckets = [];
        if ($scope === self::SCOPE_IP || $scope === self::SCOPE_BOTH) {
            $buckets[] = ['type' => 'ip', 'id' => $ip, 'key' => 'ip:' . $ip];
        }
        if (($scope === self::SCOPE_TOKEN || $scope === self::SCOPE_BOTH) && $tokenKey !== null) {
            $buckets[] = ['type' => 'token', 'id' => $tokenKey, 'key' => 'tok:' . $tokenKey];
        }

        if ($buckets === []) {
            $buckets[] = ['type' => 'ip', 'id' => $ip, 'key' => 'ip:' . $ip];
        }

        foreach ($buckets as $bucket) {
            if (self::isBlocked($bucket['key'], $driver)) {
                self::logExceed($request, $bucket['type'], $bucket['id'], $limitPerSec, $context, true);
                throw new RateLimitException(
                    'Too many requests — temporarily blocked. Retry later.',
                    429
                );
            }
        }

        foreach ($buckets as $bucket) {
            if (!self::hit($bucket['key'], $limitPerSec, $blockSeconds, $driver)) {
                self::logExceed($request, $bucket['type'], $bucket['id'], $limitPerSec, $context, false);
                if ($scope === self::SCOPE_BOTH) {
                    foreach ($buckets as $b) {
                        self::block($b['key'], $blockSeconds, $driver);
                    }
                }
                throw new RateLimitException(
                    'Too many requests. Limit is ' . $limitPerSec . ' per second.',
                    429
                );
            }
        }
    }

    /**
     * Increment counter for this second. Returns false if over limit (and applies block).
     *
     * @param 'apcu'|'redis'|'both'|'none'|string|null $driver
     */
    public static function hit(
        string $bucketKey,
        int $limitPerSec,
        int $blockSeconds = self::DEFAULT_BLOCK_SECONDS,
        ?string $driver = null
    ): bool {
        $driver = self::normalizeDriver($driver ?? self::resolveDriver());
        if ($driver === self::DRIVER_NONE || !self::isAvailable($driver)) {
            return true;
        }
        if (self::isBlocked($bucketKey, $driver)) {
            return false;
        }

        if ($driver === self::DRIVER_BOTH) {
            return self::hitBoth($bucketKey, $limitPerSec, $blockSeconds);
        }
        if ($driver === self::DRIVER_REDIS) {
            return self::hitRedis($bucketKey, $limitPerSec, $blockSeconds);
        }

        return self::hitApcu($bucketKey, $limitPerSec, $blockSeconds);
    }

    /**
     * @param 'apcu'|'redis'|'both'|'none'|string|null $driver
     */
    public static function isBlocked(string $bucketKey, ?string $driver = null): bool
    {
        $driver = self::normalizeDriver($driver ?? self::resolveDriver());
        if ($driver === self::DRIVER_NONE || !self::isAvailable($driver)) {
            return false;
        }

        return match ($driver) {
            self::DRIVER_REDIS => self::isBlockedRedis($bucketKey),
            self::DRIVER_BOTH => self::isBlockedApcu($bucketKey) || self::isBlockedRedis($bucketKey),
            default => self::isBlockedApcu($bucketKey),
        };
    }

    /**
     * @param 'apcu'|'redis'|'both'|'none'|string|null $driver
     */
    public static function block(
        string $bucketKey,
        int $seconds = self::DEFAULT_BLOCK_SECONDS,
        ?string $driver = null
    ): void {
        $driver = self::normalizeDriver($driver ?? self::resolveDriver());
        if ($driver === self::DRIVER_NONE || !self::isAvailable($driver) || $seconds <= 0) {
            return;
        }

        if ($driver === self::DRIVER_REDIS || $driver === self::DRIVER_BOTH) {
            self::blockRedis($bucketKey, $seconds);
        }
        if ($driver === self::DRIVER_APCU || $driver === self::DRIVER_BOTH) {
            self::blockApcu($bucketKey, $seconds);
        }
    }

    /**
     * @param 'apcu'|'redis'|'both'|'none'|string|null $driver
     */
    public static function unblock(string $bucketKey, ?string $driver = null): void
    {
        $driver = self::normalizeDriver($driver ?? self::resolveDriver());
        if ($driver === self::DRIVER_NONE) {
            return;
        }

        if ($driver === self::DRIVER_REDIS || $driver === self::DRIVER_BOTH) {
            if (self::isRedisBackendAvailable()) {
                self::unblockRedis($bucketKey);
            }
        }
        if ($driver === self::DRIVER_APCU || $driver === self::DRIVER_BOTH) {
            if (self::isApcuBackendAvailable()) {
                self::unblockApcu($bucketKey);
            }
        }
    }

    /**
     * Drop GEMVC rate-limit keys to free APCu / memory-store space.
     *
     * @return int Number of keys removed (best-effort; APCu/memory only)
     */
    public static function recoverFromFullCache(): int
    {
        if (self::$memoryStore !== null) {
            $n = count(self::$memoryStore['counters']) + count(self::$memoryStore['blocks']);
            self::$memoryStore['counters'] = [];
            self::$memoryStore['blocks'] = [];
            return $n;
        }

        return self::purgeApcuRateLimitKeys();
    }

    private static function hitApcu(string $bucketKey, int $limitPerSec, int $blockSeconds): bool
    {
        $count = self::incrementCounterApcu($bucketKey);
        if ($count === null) {
            self::recoverFromFullCache();
            $count = self::incrementCounterApcu($bucketKey);
            if ($count === null) {
                self::logStorageFailure('counter');
                return false;
            }
        }

        if ($count > $limitPerSec) {
            self::blockApcu($bucketKey, $blockSeconds);
            return false;
        }

        return true;
    }

    private static function hitRedis(string $bucketKey, int $limitPerSec, int $blockSeconds): bool
    {
        $count = self::incrementCounterRedis($bucketKey);
        if ($count === null) {
            self::logRedisStorageFailure('counter');
            return false;
        }

        if ($count > $limitPerSec) {
            self::blockRedis($bucketKey, $blockSeconds);
            return false;
        }

        return true;
    }

    private static function hitBoth(string $bucketKey, int $limitPerSec, int $blockSeconds): bool
    {
        $apcuOk = self::hitApcu($bucketKey, $limitPerSec, $blockSeconds);
        $redisOk = self::hitRedis($bucketKey, $limitPerSec, $blockSeconds);

        if (!$apcuOk || !$redisOk) {
            self::blockApcu($bucketKey, $blockSeconds);
            self::blockRedis($bucketKey, $blockSeconds);
            return false;
        }

        return true;
    }

    private static function isApcuBackendAvailable(): bool
    {
        if (self::$forcedApcuAvailability !== null) {
            if (self::$forcedApcuAvailability === false && !self::$apcuWarningLogged) {
                self::$apcuWarningLogged = true;
                error_log(
                    'GEMVC RateLimiter: APCu cache is not activated on this server. '
                    . 'Enable the APCu PHP extension (apc.enable_cli=1 for CLI tests). '
                    . 'When rate limits are configured for APCu/both, requests are rejected (fail-closed) '
                    . 'unless REQUEST_RATE_LIMIT_FAIL_MODE=open. No automatic fallback to Redis.'
                );
            }

            return self::$forcedApcuAvailability;
        }

        if (self::$memoryStore !== null) {
            return true;
        }

        if (self::$apcuAvailable !== null) {
            return self::$apcuAvailable;
        }

        $ok = \extension_loaded('apcu')
            && \function_exists('apcu_enabled')
            && \apcu_enabled()
            && \function_exists('apcu_inc')
            && \function_exists('apcu_store')
            && \function_exists('apcu_fetch');

        self::$apcuAvailable = $ok;

        if (!$ok && !self::$apcuWarningLogged) {
            self::$apcuWarningLogged = true;
            error_log(
                'GEMVC RateLimiter: APCu cache is not activated on this server. '
                . 'Enable the APCu PHP extension (apc.enable_cli=1 for CLI tests). '
                . 'When rate limits are configured for APCu/both, requests are rejected (fail-closed) '
                . 'unless REQUEST_RATE_LIMIT_FAIL_MODE=open. No automatic fallback to Redis.'
            );
        }

        return $ok;
    }

    private static function isRedisBackendAvailable(): bool
    {
        if (self::$forcedRedisAvailability !== null) {
            if (self::$forcedRedisAvailability === false && !self::$redisWarningLogged) {
                self::$redisWarningLogged = true;
                error_log(
                    'GEMVC RateLimiter: Redis is not available. '
                    . 'Configure REDIS_* and enable ext-redis. '
                    . 'When rate limits use redis/both, requests are rejected (fail-closed) '
                    . 'unless REQUEST_RATE_LIMIT_FAIL_MODE=open. No automatic fallback to APCu.'
                );
            }

            return self::$forcedRedisAvailability;
        }

        if (self::$redisMemoryStore !== null) {
            return true;
        }

        if (self::$redisAvailable !== null) {
            return self::$redisAvailable;
        }

        if (!\extension_loaded('redis') || !\class_exists(\Redis::class)) {
            self::$redisAvailable = false;
            if (!self::$redisWarningLogged) {
                self::$redisWarningLogged = true;
                error_log(
                    'GEMVC RateLimiter: Redis PHP extension is not loaded. '
                    . 'When rate limits use redis/both, requests are rejected (fail-closed) '
                    . 'unless REQUEST_RATE_LIMIT_FAIL_MODE=open. No automatic fallback to APCu.'
                );
            }

            return false;
        }

        try {
            $mgr = RedisManager::getInstance();
            $ok = $mgr->connect() && $mgr->getRedis() !== null;
            self::$redisAvailable = $ok;
            if (!$ok && !self::$redisWarningLogged) {
                self::$redisWarningLogged = true;
                $err = $mgr->getError() ?? 'connection failed';
                error_log(
                    'GEMVC RateLimiter: Redis connection failed (' . $err . '). '
                    . 'No automatic fallback to APCu. FAIL_MODE applies.'
                );
            }

            return $ok;
        } catch (\Throwable $e) {
            self::$redisAvailable = false;
            if (!self::$redisWarningLogged) {
                self::$redisWarningLogged = true;
                error_log('GEMVC RateLimiter: Redis error: ' . $e->getMessage());
            }

            return false;
        }
    }

    private static function isBlockedApcu(string $bucketKey): bool
    {
        if (self::$memoryStore !== null) {
            $until = self::$memoryStore['blocks'][$bucketKey] ?? 0;
            if ($until <= time()) {
                unset(self::$memoryStore['blocks'][$bucketKey]);
                return false;
            }
            return true;
        }
        $ok = false;
        $val = \apcu_fetch(self::KEY_PREFIX . 'b:' . $bucketKey, $ok);
        return $ok === true && $val !== false;
    }

    private static function isBlockedRedis(string $bucketKey): bool
    {
        if (self::$redisMemoryStore !== null) {
            $until = self::$redisMemoryStore['blocks'][$bucketKey] ?? 0;
            if ($until <= time()) {
                unset(self::$redisMemoryStore['blocks'][$bucketKey]);
                return false;
            }
            return true;
        }

        try {
            $redis = RedisManager::getInstance()->getRedis();
            if ($redis === null) {
                return false;
            }
            return (bool) $redis->exists(self::REDIS_KEY_PREFIX . 'b:' . $bucketKey);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function blockApcu(string $bucketKey, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }
        if (self::$memoryStore !== null) {
            if (self::consumeForcedWriteFailure()) {
                self::recoverFromFullCache();
                if (self::consumeForcedWriteFailure()) {
                    self::logStorageFailure('block');
                    return;
                }
            }
            self::$memoryStore['blocks'][$bucketKey] = time() + $seconds;
            return;
        }

        $key = self::KEY_PREFIX . 'b:' . $bucketKey;
        $stored = \apcu_store($key, time() + $seconds, $seconds);
        if ($stored === false) {
            self::recoverFromFullCache();
            $stored = \apcu_store($key, time() + $seconds, $seconds);
            if ($stored === false) {
                self::logStorageFailure('block');
            }
        }
    }

    private static function blockRedis(string $bucketKey, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }
        if (self::$redisMemoryStore !== null) {
            self::$redisMemoryStore['blocks'][$bucketKey] = time() + $seconds;
            return;
        }

        try {
            $redis = RedisManager::getInstance()->getRedis();
            if ($redis === null) {
                self::logRedisStorageFailure('block');
                return;
            }
            $redis->setex(self::REDIS_KEY_PREFIX . 'b:' . $bucketKey, $seconds, (string) (time() + $seconds));
        } catch (\Throwable) {
            self::logRedisStorageFailure('block');
        }
    }

    private static function unblockApcu(string $bucketKey): void
    {
        if (self::$memoryStore !== null) {
            unset(self::$memoryStore['blocks'][$bucketKey]);
            return;
        }
        \apcu_delete(self::KEY_PREFIX . 'b:' . $bucketKey);
    }

    private static function unblockRedis(string $bucketKey): void
    {
        if (self::$redisMemoryStore !== null) {
            unset(self::$redisMemoryStore['blocks'][$bucketKey]);
            return;
        }
        try {
            RedisManager::getInstance()->getRedis()?->del(self::REDIS_KEY_PREFIX . 'b:' . $bucketKey);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @return int|null New count, or null if increment failed
     */
    private static function incrementCounterApcu(string $bucketKey): ?int
    {
        $windowKey = self::KEY_PREFIX . 'c:' . $bucketKey . ':' . (string) time();

        if (self::$memoryStore !== null) {
            if (self::consumeForcedWriteFailure()) {
                return null;
            }
            $prev = self::$memoryStore['counters'][$windowKey] ?? 0;
            $count = $prev + 1;
            self::$memoryStore['counters'][$windowKey] = $count;
            return $count;
        }

        $success = false;
        /** @var int|false $count */
        $count = \apcu_inc($windowKey, 1, $success, 2);
        if ($count === false || $success === false) {
            return null;
        }
        return $count;
    }

    /**
     * @return int|null New count, or null if increment failed
     */
    private static function incrementCounterRedis(string $bucketKey): ?int
    {
        $windowKey = self::REDIS_KEY_PREFIX . 'c:' . $bucketKey . ':' . (string) time();

        if (self::$redisMemoryStore !== null) {
            $prev = self::$redisMemoryStore['counters'][$windowKey] ?? 0;
            $count = $prev + 1;
            self::$redisMemoryStore['counters'][$windowKey] = $count;
            return $count;
        }

        try {
            $redis = RedisManager::getInstance()->getRedis();
            if ($redis === null) {
                return null;
            }
            $count = $redis->incr($windowKey);
            if ($count === false) {
                return null;
            }
            if ((int) $count === 1) {
                $redis->expire($windowKey, 2);
            }
            return (int) $count;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function consumeForcedWriteFailure(): bool
    {
        if (self::$forcedWriteFailures <= 0) {
            return false;
        }
        self::$forcedWriteFailures--;
        return true;
    }

    /**
     * @return int
     */
    private static function purgeApcuRateLimitKeys(): int
    {
        $deleted = 0;

        if (!\function_exists('apcu_cache_info') || !\function_exists('apcu_delete')) {
            return 0;
        }

        try {
            $info = \apcu_cache_info(false);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!is_array($info) || !isset($info['cache_list']) || !is_array($info['cache_list'])) {
            return 0;
        }

        foreach ($info['cache_list'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['info'] ?? $entry['key'] ?? null;
            if (!is_string($key) || !str_starts_with($key, self::KEY_PREFIX)) {
                continue;
            }
            if (\apcu_delete($key)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private static function unavailableMessage(string $driver): string
    {
        return match ($driver) {
            self::DRIVER_REDIS => 'Rate limiting is configured for Redis but Redis is not available. '
                . 'Configure REDIS_* / ext-redis, or set REQUEST_RATE_LIMIT_FAIL_MODE=open for local development.',
            self::DRIVER_BOTH => 'Rate limiting driver=both requires APCu and Redis. '
                . 'One or both are unavailable. No automatic fallback. '
                . 'Set REQUEST_RATE_LIMIT_FAIL_MODE=open for local development.',
            default => 'Rate limiting is configured but APCu is not available. '
                . 'Enable the APCu PHP extension, or set REQUEST_RATE_LIMIT_FAIL_MODE=open for local development.',
        };
    }

    private static function logStorageFailure(string $op): void
    {
        if (self::$fullCacheWarningLogged) {
            return;
        }
        self::$fullCacheWarningLogged = true;
        error_log(
            'GEMVC RateLimiter: APCu write failed for ' . $op . ' after purging gemvc:rl:* keys. '
            . 'Failing closed (deny). Increase apc.shm_size or free APCu usage.'
        );
    }

    private static function logRedisStorageFailure(string $op): void
    {
        error_log(
            'GEMVC RateLimiter: Redis write failed for ' . $op . '. '
            . 'Failing closed (deny). No automatic fallback to APCu.'
        );
    }

    private static function clientIp(Request $request): string
    {
        $ip = $request->remoteAddress ?? '';
        if (!is_string($ip) || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }
        return $ip;
    }

    private static function tokenKey(Request $request): ?string
    {
        $jwt = $request->getJwtToken();
        if ($jwt !== null && isset($jwt->user_id) && is_numeric($jwt->user_id)) {
            return 'uid:' . (string) (int) $jwt->user_id;
        }
        $raw = $request->jwtTokenStringInHeader ?? null;
        if (!is_string($raw) || $raw === '') {
            $auth = $request->authorizationHeader ?? null;
            if (is_array($auth)) {
                $auth = $auth[0] ?? null;
            }
            if (is_string($auth) && str_starts_with(strtolower($auth), 'bearer ')) {
                $raw = trim(substr($auth, 7));
            }
        }
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        return 'jwt:' . hash('sha256', $raw);
    }

    private static function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        return match ($scope) {
            self::SCOPE_IP, 'ip-only' => self::SCOPE_IP,
            self::SCOPE_TOKEN, 'jwt', 'token-only' => self::SCOPE_TOKEN,
            default => self::SCOPE_BOTH,
        };
    }

    private static function envInt(string $name, int $default): int
    {
        $raw = $_ENV[$name] ?? getenv($name) ?: null;
        if ($raw === null || $raw === '' || $raw === false || !is_numeric($raw)) {
            return $default;
        }
        $n = (int) $raw;
        return $n > 0 ? $n : $default;
    }

    private static function logExceed(
        Request $request,
        string $type,
        string $id,
        int $limitPerSec,
        string $context,
        bool $alreadyBlocked
    ): void {
        $path = $request->requestedUrl ?? ($request->requestMethod ?? '');
        $service = method_exists($request, 'getServiceName') ? $request->getServiceName() : '';
        $method = method_exists($request, 'getMethodName') ? $request->getMethodName() : '';
        $state = $alreadyBlocked ? 'blocked' : 'exceeded';
        error_log(sprintf(
            'GEMVC RateLimit %s context=%s type=%s id=%s limit=%d/s path=%s service=%s method=%s',
            $state,
            $context,
            $type,
            $id,
            $limitPerSec,
            is_string($path) ? $path : '',
            is_string($service) ? $service : '',
            is_string($method) ? $method : ''
        ));
    }
}

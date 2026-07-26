<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;

/**
 * Optional APCu-backed rate limiter (no Redis).
 *
 * - Fail-open if APCu is missing (requests allowed) + one-time warning log.
 * - If APCu is full / write fails: purge GEMVC `gemvc:rl:*` keys, retry once;
 *   if still failing → fail-closed (deny / 429) so abuse cannot bypass limits.
 * - Keys: per-IP and/or per-JWT (Authorization header hash / user id).
 * - Fixed 1-second windows via apcu_inc; optional temporary block after exceed.
 *
 * Env (Bootstrap global — only when set):
 *   REQUEST_RATE_LIMIT_PER_SEC=20
 *   REQUEST_RATE_LIMIT_BLOCK_SECONDS=60
 *   REQUEST_RATE_LIMIT_SCOPE=both|ip|token   (default both)
 *
 * DX:
 *   $this->requireRateLimit();           // 20/sec, both IP + token
 *   $this->requireRateLimit(10);         // 10/sec
 *   $this->requireRateLimit(10, 'ip');
 *   $this->requireRateLimit(5, 'token', 120);
 */
class RateLimiter
{
    public const DEFAULT_PER_SEC = 20;
    public const DEFAULT_BLOCK_SECONDS = 60;
    public const SCOPE_BOTH = 'both';
    public const SCOPE_IP = 'ip';
    public const SCOPE_TOKEN = 'token';
    public const KEY_PREFIX = 'gemvc:rl:';

    private static bool $apcuWarningLogged = false;
    private static bool $fullCacheWarningLogged = false;
    private static ?bool $apcuAvailable = null;

    /**
     * When non-null, use process memory instead of APCu (unit tests / CI without APCu).
     *
     * @var array{counters: array<string, int>, blocks: array<string, int>}|null
     */
    private static ?array $memoryStore = null;

    /** @internal Remaining simulated write failures (memory-store tests). */
    private static int $forcedWriteFailures = 0;

    /**
     * @internal Testing helper — APCu substitute that works without the extension.
     */
    public static function useInMemoryStoreForTests(bool $enable = true): void
    {
        if ($enable) {
            self::$memoryStore = ['counters' => [], 'blocks' => []];
            self::$apcuAvailable = true;
            self::$apcuWarningLogged = true;
            self::$fullCacheWarningLogged = false;
            self::$forcedWriteFailures = 0;
            return;
        }
        self::$memoryStore = null;
        self::$apcuAvailable = null;
        self::$apcuWarningLogged = false;
        self::$fullCacheWarningLogged = false;
        self::$forcedWriteFailures = 0;
    }

    /**
     * @internal Next N counter/block writes fail (to test full-cache recovery).
     */
    public static function forceWriteFailuresForTests(int $count): void
    {
        self::$forcedWriteFailures = max(0, $count);
    }

    /**
     * Whether APCu is usable. Logs a one-time warning when not.
     */
    public static function isAvailable(): bool
    {
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
                . 'Rate limiting is disabled until APCu is available.'
            );
        }

        return $ok;
    }

    /**
     * Bootstrap / global guard from env. No-op if REQUEST_RATE_LIMIT_PER_SEC unset/empty/0.
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

        self::enforce($request, $limit, $scope, $block, 'bootstrap');
    }

    /**
     * Enforce limit for this request. Throws RateLimitException on exceed/block.
     *
     * @param 'both'|'ip'|'token'|string $scope
     * @throws RateLimitException
     */
    public static function enforce(
        Request $request,
        int $limitPerSec = self::DEFAULT_PER_SEC,
        string $scope = self::SCOPE_BOTH,
        int $blockSeconds = self::DEFAULT_BLOCK_SECONDS,
        string $context = 'api'
    ): void {
        if ($limitPerSec <= 0) {
            return;
        }
        if (!self::isAvailable()) {
            return;
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
            // scope=token but no Authorization — fall back to IP so the call still protects something
            $buckets[] = ['type' => 'ip', 'id' => $ip, 'key' => 'ip:' . $ip];
        }

        foreach ($buckets as $bucket) {
            if (self::isBlocked($bucket['key'])) {
                self::logExceed($request, $bucket['type'], $bucket['id'], $limitPerSec, $context, true);
                throw new RateLimitException(
                    'Too many requests — temporarily blocked. Retry later.',
                    429
                );
            }
        }

        foreach ($buckets as $bucket) {
            if (!self::hit($bucket['key'], $limitPerSec, $blockSeconds)) {
                self::logExceed($request, $bucket['type'], $bucket['id'], $limitPerSec, $context, false);
                // Block the other dimension too when scope is both (shared abuse)
                if ($scope === self::SCOPE_BOTH) {
                    foreach ($buckets as $b) {
                        self::block($b['key'], $blockSeconds);
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
     * Returns false (deny) if storage stays unusable after purge+retry (fail-closed).
     */
    public static function hit(string $bucketKey, int $limitPerSec, int $blockSeconds = self::DEFAULT_BLOCK_SECONDS): bool
    {
        if (!self::isAvailable()) {
            return true;
        }
        if (self::isBlocked($bucketKey)) {
            return false;
        }

        $count = self::incrementCounter($bucketKey);
        if ($count === null) {
            self::recoverFromFullCache();
            $count = self::incrementCounter($bucketKey);
            if ($count === null) {
                self::logStorageFailure('counter');
                // Fail-closed: do not let attackers bypass limits when cache cannot track them
                return false;
            }
        }

        if ($count > $limitPerSec) {
            self::block($bucketKey, $blockSeconds);
            return false;
        }

        return true;
    }

    public static function isBlocked(string $bucketKey): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
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

    public static function block(string $bucketKey, int $seconds = self::DEFAULT_BLOCK_SECONDS): void
    {
        if (!self::isAvailable() || $seconds <= 0) {
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

    /**
     * Clear a temporary block (admin / tests).
     */
    public static function unblock(string $bucketKey): void
    {
        if (!self::isAvailable()) {
            return;
        }
        if (self::$memoryStore !== null) {
            unset(self::$memoryStore['blocks'][$bucketKey]);
            return;
        }
        \apcu_delete(self::KEY_PREFIX . 'b:' . $bucketKey);
    }

    /**
     * Drop GEMVC rate-limit keys to free APCu / memory-store space, then allow a retry.
     *
     * @return int Number of keys removed (best-effort)
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

    /**
     * @return int|null New count, or null if increment failed
     */
    private static function incrementCounter(string $bucketKey): ?int
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

    private static function consumeForcedWriteFailure(): bool
    {
        if (self::$forcedWriteFailures <= 0) {
            return false;
        }
        self::$forcedWriteFailures--;
        return true;
    }

    /**
     * Delete only our rate-limit keys from APCu (does not wipe unrelated cache).
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

    private static function clientIp(Request $request): string
    {
        $ip = $request->remoteAddress ?? '';
        if (!is_string($ip) || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }
        return $ip;
    }

    /**
     * Stable token identity: prefer verified user_id, else hash of raw JWT string.
     */
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

<?php

/**
 * APCu stub for IDE / static analysis (Intelephense, PHPStan).
 *
 * Real implementation is the APCu PHP extension. RateLimiter uses these
 * when available; if the extension is missing it fails open. If APCu is
 * full it purges gemvc:rl:* keys, retries, then fails closed.
 *
 * Registered in phpstan.neon stubFiles and intelephense.environment.includePaths.
 */

function apcu_enabled(): bool
{
    return false;
}

/**
 * @param-out bool $success
 */
function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): int|false
{
    return false;
}

/**
 * @param string|array<string, mixed> $key
 * @return bool|array<string, bool>
 */
function apcu_store(string|array $key, mixed $var = null, int $ttl = 0): bool|array
{
    return false;
}

/**
 * @param string|array<string> $key
 * @param-out bool $success
 */
function apcu_fetch(string|array $key, ?bool &$success = null): mixed
{
    return false;
}

/**
 * @param string|array<string> $key
 * @return bool|array<string>
 */
function apcu_delete(string|array $key): bool|array
{
    return false;
}

/**
 * @return array<string, mixed>|false
 */
function apcu_cache_info(bool $limited = false): array|false
{
    return false;
}

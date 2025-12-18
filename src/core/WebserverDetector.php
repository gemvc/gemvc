<?php

namespace Gemvc\Core;

/**
 * EnvironmentDetector: performant, cached webserver environment detection
 *
 * Detects whether runtime is 'swoole', 'apache', or 'nginx' using a layered
 * strategy optimized for fast-path checks and with simple static caching.
 */
class WebserverDetector
{
    /** @var string|null */
    private static ?string $cachedEnvironment = null;

    /** @var bool */
    private static bool $detectionPerformed = false;

    /**
     * Get cached environment, performing detection on first call.
     *
     * @return string 'swoole' | 'apache' | 'nginx'
     */
    public static function get(): string
    {
        if (!self::$detectionPerformed) {
            self::$cachedEnvironment = self::detect();
            self::$detectionPerformed = true;
        }
        return self::$cachedEnvironment ?? 'apache';
    }

    /**
     * Force a fresh detection, bypassing cache.
     *
     * @return string Detected environment
     */
    public static function forceRefresh(): string
    {
        self::$detectionPerformed = false;
        return self::get();
    }

    /**
     * Perform environment detection using fast → slow checks.
     *
     * @return string Detected environment
     */
    public static function detect(): string
    {
        // Fast-path: APP_ENV_SERVER (new standard) or WEBSERVER_TYPE (legacy)
        $envServer = $_ENV['APP_ENV_SERVER'] ?? $_ENV['WEBSERVER_TYPE'] ?? null;
        if ($envServer === 'swoole') { return 'swoole'; }
        if ($envServer === 'apache') { return 'apache'; }
        if ($envServer === 'nginx') { return 'nginx'; }

        // Fast-path: Swoole/OpenSwoole constants/extensions
        if (defined('SWOOLE_BASE') || defined('SWOOLE_PROCESS')) { return 'swoole'; }
        if (extension_loaded('openswoole') || extension_loaded('swoole')) { return 'swoole'; }

        // Medium-path: SERVER_SOFTWARE header
        if (isset($_SERVER['SERVER_SOFTWARE']) && is_string($_SERVER['SERVER_SOFTWARE'])) {
            $serverSoftware = strtolower($_SERVER['SERVER_SOFTWARE']);
            if (strpos($serverSoftware, 'nginx') !== false) { return 'nginx'; }
            if (strpos($serverSoftware, 'apache') !== false) { return 'apache'; }
        }

        // Slow-path: class availability
        if (class_exists('\\OpenSwoole\\Server', false) || class_exists('\\Swoole\\Server', false)) {
            return 'swoole';
        }

        // Heuristics: common reverse-proxy headers (favor nginx)
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_REAL_IP'])) {
            return 'nginx';
        }

        // Default fallback
        return 'apache';
    }

    /**
     * Get simple performance metrics for detection.
     *
     * @return array<string, mixed>
     */
    public static function getMetrics(): array
    {
        $start = microtime(true);
        $environment = self::forceRefresh();
        $detectionTimeMs = (microtime(true) - $start) * 1000.0;

        return [
            'detection_time_ms' => round($detectionTimeMs, 3),
            'environment' => $environment,
            'cached' => self::$detectionPerformed,
            'performance_level' => $detectionTimeMs < 0.1 ? 'excellent' : ($detectionTimeMs < 1 ? 'good' : 'needs_optimization')
        ];
    }
}



<?php

namespace Gemvc\Core;

use Gemvc\Helper\ProjectHelper;

/**
 * Server Configuration Manager
 * 
 * Handles all OpenSwoole server configuration and environment settings
 */
class SwooleServerConfig
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Get the complete server configuration array
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get server host
     */
    public function getHost(): string
    {
        $host = $_ENV["SWOOLE_SERVER_HOST"] ?? "0.0.0.0";
        return is_string($host) ? $host : "0.0.0.0";
    }

    /**
     * Get server port
     */
    public function getPort(): int
    {
        return is_numeric($_ENV["SWOOLE_SERVER_PORT"] ?? 9501) ? (int) ($_ENV["SWOOLE_SERVER_PORT"] ?? 9501) : 9501;
    }

    /**
     * Check if running in development mode
     */
    public function isDev(): bool
    {
        return ProjectHelper::isDevEnvironment();
    }

    /**
     * Load server configuration from environment variables
     */
    private function loadConfig(): void
    {
        $this->config = [
            'worker_num' => is_numeric($_ENV["SWOOLE_WORKERS"] ?? 1) ? (int) ($_ENV["SWOOLE_WORKERS"] ?? 1) : 1,
            'daemonize' => !(bool)($_ENV["SWOOLE_RUN_FOREGROUND"] ?? 0), // Inverted: 1 = foreground (daemonize=false), 0 = daemon (daemonize=true)
            'max_request' => is_numeric($_ENV["SWOOLE_MAX_REQUEST"] ?? 5000) ? (int) ($_ENV["SWOOLE_MAX_REQUEST"] ?? 5000) : 5000,
            'max_conn' => is_numeric($_ENV["SWOOLE_MAX_CONN"] ?? 1024) ? (int) ($_ENV["SWOOLE_MAX_CONN"] ?? 1024) : 1024,
            'max_wait_time' => is_numeric($_ENV["SWOOLE_MAX_WAIT_TIME"] ?? 120) ? (int) ($_ENV["SWOOLE_MAX_WAIT_TIME"] ?? 120) : 120,
            'enable_coroutine' => (bool)($_ENV["SWOOLE_ENABLE_COROUTINE"] ?? 1),
            'max_coroutine' => is_numeric($_ENV["SWOOLE_MAX_COROUTINE"] ?? 3000) ? (int) ($_ENV["SWOOLE_MAX_COROUTINE"] ?? 3000) : 3000,
            'display_errors' => is_numeric($_ENV["SWOOLE_DISPLAY_ERRORS"] ?? 1) ? (int) ($_ENV["SWOOLE_DISPLAY_ERRORS"] ?? 1) : 1,
            'heartbeat_idle_time' => is_numeric($_ENV["SWOOLE_HEARTBEAT_IDLE_TIME"] ?? 600) ? (int) ($_ENV["SWOOLE_HEARTBEAT_IDLE_TIME"] ?? 600) : 600,
            'heartbeat_check_interval' => is_numeric($_ENV["SWOOLE__HEARTBEAT_INTERVAL"] ?? 60) ? (int) ($_ENV["SWOOLE__HEARTBEAT_INTERVAL"] ?? 60) : 60,
            'log_level' => (int)(($_ENV["SWOOLE_SERVER_LOG_INFO"] ?? 0) ? 0 : 1), // 0 = SWOOLE_LOG_INFO, 1 = SWOOLE_LOG_ERROR
            'reload_async' => true
        ];
    }

    /**
     * Get a specific configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Check if a configuration key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }
}

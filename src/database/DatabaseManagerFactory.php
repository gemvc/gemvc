<?php

namespace Gemvc\Database;

use Gemvc\Core\WebserverDetector;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Pdo\PdoConnection;

/**
 * Database Manager Factory - Thin wrapper for environment detection
 * 
 * This factory automatically chooses the appropriate database manager
 * implementation based on the current web server environment.
 * 
 * **Uses New Connection Packages Directly:**
 * - `gemvc/connection-pdo` - PDO implementation for Apache/Nginx
 * - `gemvc/connection-openswoole` - OpenSwoole implementation with Hyperf pooling
 * 
 * **Minimal Implementation:**
 * - Only provides environment detection and caching
 * - Delegates all connection management to the packages
 * - No duplication - uses packages' getInstance() methods
 */
class DatabaseManagerFactory
{
    /** @var ConnectionManagerInterface|null Singleton instance */
    private static ?ConnectionManagerInterface $instance = null;

    /** @var string|null Cached environment detection result */
    private static ?string $cachedEnvironment = null;

    /**
     * Get the appropriate database manager for the current environment
     * 
     * @return ConnectionManagerInterface The database manager instance
     */
    public static function getManager(): ConnectionManagerInterface
    {
        if (self::$instance === null) {
            $environment = self::getCachedEnvironment();
            self::$instance = match ($environment) {
                'swoole' => self::getSwooleConnection(),
                default => PdoConnection::getInstance(),
            };
        }
        return self::$instance;
    }
    
    /**
     * Get SwooleConnection instance if available, otherwise fallback to PdoConnection
     * 
     * @return ConnectionManagerInterface
     */
    private static function getSwooleConnection(): ConnectionManagerInterface
    {
        // Check if gemvc/connection-openswoole package is installed
        if (class_exists('Gemvc\Database\Connection\OpenSwoole\SwooleConnection')) {
            return \Gemvc\Database\Connection\OpenSwoole\SwooleConnection::getInstance();
        }
        
        // Fallback to PDO if OpenSwoole package is not installed
        // This can happen if user is running on Apache/Nginx but environment was detected as swoole
        return PdoConnection::getInstance();
    }

    /**
     * Get cached environment detection result
     * 
     * @return string The detected environment ('swoole', 'apache', 'nginx')
     */
    private static function getCachedEnvironment(): string
    {
        if (self::$cachedEnvironment === null) {
            self::$cachedEnvironment = WebserverDetector::get();
        }
        return self::$cachedEnvironment ?? 'apache';
    }

    /**
     * Reset the singleton instance and cache (useful for testing)
     * 
     * @return void
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            // Reset the underlying manager if it has resetInstance static method
            if (self::$instance instanceof PdoConnection) {
                PdoConnection::resetInstance();
            } elseif (class_exists('Gemvc\Database\Connection\OpenSwoole\SwooleConnection')) {
                /** @var class-string<\Gemvc\Database\Connection\OpenSwoole\SwooleConnection> $swooleClass */
                $swooleClass = 'Gemvc\Database\Connection\OpenSwoole\SwooleConnection';
                if (method_exists($swooleClass, 'resetInstance')) {
                    $swooleClass::resetInstance();
                }
            }
            self::$instance = null;
        }
        self::$cachedEnvironment = null;
    }

    /**
     * Get information about the current database manager
     * 
     * @return array<string, mixed> Manager information
     */
    public static function getManagerInfo(): array
    {
        $manager = self::getManager();
        $environment = self::getCachedEnvironment();
        
        $info = [
            'environment' => $environment,
            'manager_class' => get_class($manager),
            'pool_stats' => $manager->getPoolStats(),
            'initialized' => $manager->isInitialized(),
            'has_error' => $manager->getError() !== null,
            'error' => $manager->getError(),
            'detection_cached' => self::$cachedEnvironment !== null,
            'performance_mode' => 'optimized'
        ];
        
        // Add PDO-specific configuration info
        if ($environment === 'apache' || $environment === 'nginx') {
            $persistentEnabled = $_ENV['DB_PERSISTENT_CONNECTIONS'] ?? '1';
            $info['pdo_config'] = [
                'persistent_connections' => $persistentEnabled,
                'persistent_enabled' => ($persistentEnabled === '1' || $persistentEnabled === 'true' || $persistentEnabled === 'yes'),
                'implementation' => 'PdoConnection (gemvc/connection-pdo)'
            ];
        } elseif ($environment === 'swoole') {
            $info['swoole_config'] = [
                'implementation' => 'SwooleConnection (gemvc/connection-openswoole)'
            ];
        }
        
        return $info;
    }

    /**
     * Force environment detection (bypasses cache)
     * 
     * @return string The detected environment
     */
    public static function forceDetection(): string
    {
        self::$cachedEnvironment = WebserverDetector::forceRefresh();
        return self::$cachedEnvironment;
    }

    /**
     * Get performance metrics for detection
     * 
     * @return array<string, mixed> Performance metrics
     */
    public static function getPerformanceMetrics(): array
    {
        $metrics = WebserverDetector::getMetrics();
        $metrics['detection_cached'] = self::$cachedEnvironment !== null;
        return $metrics;
    }
}

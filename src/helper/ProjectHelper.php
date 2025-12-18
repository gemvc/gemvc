<?php
namespace Gemvc\Helper;
use Symfony\Component\Dotenv\Dotenv;

class ProjectHelper
{
    private static ?string $rootDir = null;
    public static function rootDir(): string
    {
        if (self::$rootDir !== null) {
            return self::$rootDir;
        }

        $currentDir = __DIR__;

        while ($currentDir !== dirname($currentDir)) { // Stop at the filesystem root
            if (file_exists($currentDir . DIRECTORY_SEPARATOR . 'composer.lock')) {
                self::$rootDir = $currentDir;
                return self::$rootDir;
            }
            $currentDir = dirname($currentDir);
        }
        throw new \Exception('composer.lock not found');
    }

    public static function appDir(): string
    {
        $appDir = self::rootDir() . DIRECTORY_SEPARATOR . 'app';
        if (!file_exists($appDir)) {
            throw new \Exception('app directory not found in root directory');
        }
        return $appDir;
    }

    public static function loadEnv(): void
    {
        $dotenv = new Dotenv();
        
        // Try root directory first
        $rootEnvFile = self::rootDir() . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($rootEnvFile)) {
            $dotenv->load($rootEnvFile);
            return;
        }
        
        // Try app directory
        $appEnvFile = self::appDir() . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($appEnvFile)) {
            $dotenv->load($appEnvFile);
            return;
        }
        
        throw new \Exception('No .env file found in root or app directory');
    }

    /**
     * Construct API base URL using environment variables
     * 
     * Uses:
     * - APP_ENV_PUBLIC_SERVER_PORT: Port number (80/443 don't need :port, others do)
     * - APP_ENV_API_DEFAULT_SUB_URL: Sub-path for API (e.g., 'apiv2' or '')
     *   If empty, endpoints are directly on base URL (no /api prefix)
     *   If set, endpoints are on base URL + sub URL (e.g., /apiv2)
     * 
     * @return string API base URL (e.g., 'http://localhost/apiv2' or 'http://localhost:9550' or 'http://localhost')
     */
    public static function getApiBaseUrl(): string
    {
        // Ensure env is loaded
        if (!isset($_ENV['APP_ENV_PUBLIC_SERVER_PORT'])) {
            self::loadEnv();
        }

        // Get protocol
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        
        // Get host
        $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
            ? $_SERVER['HTTP_HOST']
            : 'localhost';
        
        // Remove port from HTTP_HOST if it exists (we'll add our own)
        $host = preg_replace('/:\d+$/', '', $host);
        
        // Get port from env
        $portEnv = $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] ?? '80';
        $port = is_numeric($portEnv) ? (int) $portEnv : 80;
        
        // Add port only if not 80 (http) or 443 (https)
        $portDisplay = ($port !== 80 && $port !== 443) ? ':' . $port : '';
        
        // Get API sub URL (remove quotes and trim)
        $apiSubUrl = isset($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) && is_string($_ENV['APP_ENV_API_DEFAULT_SUB_URL'])
            ? trim(trim($_ENV['APP_ENV_API_DEFAULT_SUB_URL'], '\'"'), '/')
            : '';
        $apiSubUrl = $apiSubUrl !== '' ? '/' . $apiSubUrl : '';
        
        // Construct base URL: protocol + host + port + subUrl
        $baseUrl = $protocol . '://' . $host . $portDisplay . $apiSubUrl;
        
        return rtrim($baseUrl, '/');
    }

    /**
     * Get installed GEMVC version from composer.lock
     * 
     * @return string Version string (e.g., "1.0.0") or "unknown" if not found
     */
    public static function getVersion(): string
    {
        try {
            $rootDir = self::rootDir();
            $composerLockPath = $rootDir . DIRECTORY_SEPARATOR . 'composer.lock';
            
            if (!file_exists($composerLockPath)) {
                return 'unknown';
            }
            
            $lockContent = file_get_contents($composerLockPath);
            if ($lockContent === false) {
                return 'unknown';
            }
            
            $lockData = json_decode($lockContent, true);
            if (!is_array($lockData) || !isset($lockData['packages'])) {
                return 'unknown';
            }
            
            // Search for gemvc/library package in packages array
            foreach ($lockData['packages'] as $package) {
                if (isset($package['name']) && $package['name'] === 'gemvc/library') {
                    // Return version if available, otherwise try pretty_version
                    return $package['version'] ?? $package['pretty_version'] ?? 'unknown';
                }
            }
            
            // Also check packages-dev in case it's a dev dependency
            if (isset($lockData['packages-dev']) && is_array($lockData['packages-dev'])) {
                foreach ($lockData['packages-dev'] as $package) {
                    if (isset($package['name']) && $package['name'] === 'gemvc/library') {
                        return $package['version'] ?? $package['pretty_version'] ?? 'unknown';
                    }
                }
            }
            
            return 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}


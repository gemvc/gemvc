<?php
namespace Gemvc\Helper;

use Symfony\Component\Dotenv\Dotenv;
use Gemvc\Core\Apm\ApmFactory;

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
        
        // Get host and port
        $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
            ? $_SERVER['HTTP_HOST']
            : 'localhost';
        
        // Extract port from HTTP_HOST if it exists (e.g., localhost:82)
        $detectedPort = null;
        if (preg_match('/:(\d+)$/', $host, $matches)) {
            $detectedPort = (int) $matches[1];
            $host = preg_replace('/:\d+$/', '', $host);
        }
        
        // Use detected port from HTTP_HOST if available, otherwise use env variable
        if ($detectedPort !== null) {
            $port = $detectedPort;
        } else {
            $portEnv = $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] ?? '80';
            $port = is_numeric($portEnv) ? (int) $portEnv : 80;
        }
        
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
            if (!is_array($lockData) || !isset($lockData['packages']) || !is_array($lockData['packages'])) {
                return 'unknown';
            }
            
            // Search for gemvc/library package in packages array
            foreach ($lockData['packages'] as $package) {
                if (is_array($package) && isset($package['name']) && $package['name'] === 'gemvc/library') {
                    // Return version if available, otherwise try pretty_version
                    $version = $package['version'] ?? $package['pretty_version'] ?? null;
                    if (is_string($version)) {
                        return $version;
                    }
                    return 'unknown';
                }
            }
            
            // Also check packages-dev in case it's a dev dependency
            if (isset($lockData['packages-dev']) && is_array($lockData['packages-dev'])) {
                foreach ($lockData['packages-dev'] as $package) {
                    if (is_array($package) && isset($package['name']) && $package['name'] === 'gemvc/library') {
                        $version = $package['version'] ?? $package['pretty_version'] ?? null;
                        if (is_string($version)) {
                            return $version;
                        }
                        return 'unknown';
                    }
                }
            }
            
            return 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }


    /**
     * Check if the current environment is development
     * 
     * @return bool True if development environment, false otherwise
     */
    public static function isDevEnvironment(): bool
    {
        return $_ENV['APP_ENV'] === 'dev';
    }

    /**
     * Update multiple environment variables in .env file
     * 
     * @param array<string, string> $variables Key-value pairs of env variables
     * @return bool Success status
     */
    public static function updateEnvVariables(array $variables): bool
    {
        $envPath = self::rootDir() . DIRECTORY_SEPARATOR . '.env';
        
        if (!file_exists($envPath)) {
            return false;
        }
        
        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            return false;
        }
        
        // Update or add each variable
        foreach ($variables as $key => $value) {
            // Pattern to match variable line (handles quoted and unquoted values)
            // Matches: KEY=value or KEY="value" or KEY='value'
            $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\n\r]*)/m';
            
            // Escape value if needed (wrap in quotes if contains spaces/special chars)
            $escapedValue = self::escapeEnvValue($value);
            $replacement = $key . '=' . $escapedValue;
            
            if (preg_match($pattern, $envContent)) {
                // Update existing
                $replaced = preg_replace($pattern, $replacement, $envContent);
                $envContent = is_string($replaced) ? $replaced : $envContent;
            } else {
                // Add new - try to add after APP_ENV if found, otherwise append to end
                if (preg_match('/^APP_ENV\s*=.*$/m', $envContent, $matches, PREG_OFFSET_CAPTURE)) {
                    $pos = $matches[0][1] + strlen($matches[0][0]);
                    $envContent = substr_replace($envContent, "\n" . $replacement, $pos, 0);
                } else {
                    $envContent .= "\n" . $replacement . "\n";
                }
            }
        }
        
        return file_put_contents($envPath, $envContent) !== false;
    }

    /**
     * Escape value for .env file
     * 
     * @param string $value Value to escape
     * @return string Escaped value (quoted if needed)
     */
    private static function escapeEnvValue(string $value): string
    {
        // If value contains spaces or special chars, wrap in quotes
        if (preg_match('/[\s"\'=#]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return $value;
    }
}


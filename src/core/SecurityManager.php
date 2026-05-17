<?php

namespace Gemvc\Core;

/**
 * Security Manager
 * 
 * Handles request security validation and security responses
 */
class SecurityManager
{
    /** @var array<string> */
    private array $blockedPaths;
    /** @var array<string> */
    private array $blockedExtensions;

    public function __construct()
    {
        $this->loadSecurityRules();
    }

    /**
     * Check if a request is allowed based on security rules
     */
    public function isRequestAllowed(string $requestUri): bool
    {
        // Remove query parameters and normalize path (security: prevent bypass via // or encoded segments)
        $path = strtok($requestUri, '?');
        $path = $path !== false ? $path : '';
        $path = $this->normalizePath($path);

        // Allow root path
        if ($path === '' || $path === '/') {
            return true;
        }

        // Check blocked paths
        if ($this->isBlockedPath($path)) {
            error_log("Security: Blocked direct access to: $path");
            return false;
        }

        // Check blocked file extensions
        if ($this->isBlockedFile($path)) {
            error_log("Security: Blocked file access: $path");
            return false;
        }

        // Allow all other requests (API endpoints, routes)
        return true;
    }

    /**
     * Normalize path to prevent security bypass via double slashes, encoded segments, or traversal.
     * e.g. //app -> /app, /%2e%2e/app -> /app, /api/../app -> /app
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        // Decode once so that %2f, %5c etc. are normalized before comparison
        $decoded = rawurldecode($path);
        // Collapse multiple slashes to one (prevents //app bypass)
        $normalized = (string) preg_replace('#/+#', '/', $decoded);
        // Resolve . and .. segments to prevent path traversal bypass (e.g. /api/../app)
        $segments = array_filter(explode('/', $normalized), fn (string $s): bool => $s !== '');
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($resolved);
                continue;
            }
            if ($segment !== '.') {
                $resolved[] = $segment;
            }
        }
        $joined = '/' . implode('/', $resolved);
        return rtrim($joined, '/') ?: '/';
    }

    /**
     * Send security response for blocked requests
     */
    public function sendSecurityResponse(object $response): void
    {
        // @phpstan-ignore-next-line
        $response->status(403);
        // @phpstan-ignore-next-line
        $response->header('Content-Type', 'application/json');
        // @phpstan-ignore-next-line
        $response->end(json_encode([
            'error' => 'Access Denied',
            'message' => 'Direct file access is not permitted'
        ]));
    }

    /**
     * Load security rules from configuration
     */
    private function loadSecurityRules(): void
    {
        $this->blockedPaths = [
            '/app',
            '/vendor', 
            '/bin',
            '/templates',
            '/config',
            '/logs',
            '/storage',
            '/.env',
            '/.git'
        ];

        $this->blockedExtensions = [
            '.php', '.env', '.ini', '.conf', '.config', 
            '.log', '.sql', '.db', '.sqlite', '.md', 
            '.txt', '.json', '.xml', '.yml', '.yaml'
        ];
    }

    /**
     * Check if path is in blocked paths list
     */
    private function isBlockedPath(string $path): bool
    {
        foreach ($this->blockedPaths as $blockedPath) {
            if (strpos($path, $blockedPath) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if file has blocked extension
     */
    private function isBlockedFile(string $path): bool
    {
        foreach ($this->blockedExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a blocked path
     */
    public function addBlockedPath(string $path): void
    {
        if (!in_array($path, $this->blockedPaths)) {
            $this->blockedPaths[] = $path;
        }
    }

    /**
     * Add a blocked file extension
     */
    public function addBlockedExtension(string $extension): void
    {
        if (!in_array($extension, $this->blockedExtensions)) {
            $this->blockedExtensions[] = $extension;
        }
    }

    /**
     * Get all blocked paths
     * @return array<string>
     */
    public function getBlockedPaths(): array
    {
        return $this->blockedPaths;
    }

    /**
     * Get all blocked extensions
     * @return array<string>
     */
    public function getBlockedExtensions(): array
    {
        return $this->blockedExtensions;
    }
}

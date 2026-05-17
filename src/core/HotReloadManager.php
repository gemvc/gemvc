<?php

namespace Gemvc\Core;

use Gemvc\Helper\ProjectHelper;

/**
 * Hot Reload Manager
 *
 * Handles file change detection and server reloading in development mode.
 * Watches only the application folder (app/) so reloads trigger on your code changes,
 * not on vendor or other project files.
 */
class HotReloadManager
{
    private int $lastCheck;
    private string $lastFileHash;
    private int $checkInterval;
    private int $minReloadInterval;

    public function __construct()
    {
        $this->lastCheck = time();
        $this->lastFileHash = '';
        $this->checkInterval = 5000; // 5 seconds
        $this->minReloadInterval = 10; // 10 seconds minimum between reloads
    }

    /**
     * Start hot reload monitoring (runs only in dev mode)
     */
    public function startHotReload(object $server): void
    {
        if (!ProjectHelper::isDevEnvironment()) {
            return;
        }

        $this->lastFileHash = $this->getFileHash();

        // @phpstan-ignore-next-line
        $server->tick($this->checkInterval, function () use ($server) {
            $this->checkForChanges($server);
        });
    }

    /**
     * Check for file changes and reload if necessary
     */
    private function checkForChanges(object $server): void
    {
        $currentTime = time();
        $currentFileHash = $this->getFileHash();
        
        // Only reload if files have changed AND enough time has passed
        if ($currentFileHash !== $this->lastFileHash && 
            ($currentTime - $this->lastCheck) >= $this->minReloadInterval) {
            
            $this->lastCheck = $currentTime;
            $this->lastFileHash = $currentFileHash;
            
            echo "File changes detected, reloading server...\n";
            // @phpstan-ignore-next-line
            $server->reload();
        }
    }

    /**
     * Get hash of all PHP files in the app folder only
     */
    private function getFileHash(): string
    {
        $files = [];
        try {
            $appDir = ProjectHelper::appDir();
        } catch (\Throwable $e) {
            return '';
        }

        if (!is_dir($appDir)) {
            return '';
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (is_object($file) && method_exists($file, 'isFile') && method_exists($file, 'getExtension') &&
                method_exists($file, 'getPathname') && method_exists($file, 'getMTime') &&
                $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname() . ':' . $file->getMTime();
            }
        }

        return md5(implode('|', $files));
    }

    /**
     * Set the check interval for file changes
     */
    public function setCheckInterval(int $interval): void
    {
        $this->checkInterval = $interval;
    }

    /**
     * Set the minimum interval between reloads
     */
    public function setMinReloadInterval(int $interval): void
    {
        $this->minReloadInterval = $interval;
    }

    /**
     * Add a directory to monitor for changes
     */
    public function addWatchDirectory(string $directory): void
    {
        // This could be extended to support dynamic directory watching
        // For now, directories are hardcoded in getFileHash()
    }

    /**
     * Force a reload (useful for testing)
     */
    public function forceReload(object $server): void
    {
        echo "Forcing server reload...\n";
        // @phpstan-ignore-next-line
        $server->reload();
    }
}

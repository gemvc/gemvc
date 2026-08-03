<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\AbstractInit;

/**
 * Initialize a new GEMVC FrankenPHP project (classic mode).
 *
 * Sets up Caddyfile path protection, FrankenPHP Docker image, and shared
 * StandardHttpRequest + Bootstrap entry — same app/ as other servers.
 *
 * @package Gemvc\CLI\Commands
 */
class InitFrankenPHP extends AbstractInit
{
    /**
     * FrankenPHP-specific required directories
     */
    private const FRANKENPHP_DIRECTORIES = [
        'public'
    ];

    /**
     * @var array<string, string>
     */
    private const FRANKENPHP_FILE_MAPPINGS = [];

    /**
     * @param array<int|string, mixed> $args
     * @param array<string, mixed> $options
     */
    public function __construct(array $args = [], array $options = [])
    {
        parent::__construct($args, $options);
        $this->setPackageName('frankenphp');
    }

    protected function getWebserverType(): string
    {
        return 'FrankenPHP';
    }

    /**
     * @return array<string>
     */
    protected function getWebserverSpecificDirectories(): array
    {
        return self::FRANKENPHP_DIRECTORIES;
    }

    protected function copyWebserverSpecificFiles(): void
    {
        $this->info("Copying FrankenPHP-specific files...");

        $startupPath = $this->findStartupPath();

        $filesToCopy = [
            'index.php',
            'worker.php',
            'Caddyfile',
            'Caddyfile.worker',
            'composer.json',
            'Dockerfile',
            '.gitignore',
            '.dockerignore'
        ];

        foreach ($filesToCopy as $file) {
            $sourceFile = $startupPath . DIRECTORY_SEPARATOR . $file;
            $destFile = $this->basePath . DIRECTORY_SEPARATOR . $file;

            if (file_exists($sourceFile)) {
                $this->fileSystem->copyFileWithConfirmation($sourceFile, $destFile, $file);
            }
        }

        // Never copy .htaccess — Caddy ignores it; path security is Caddyfile-only
        $htaccess = $this->basePath . DIRECTORY_SEPARATOR . '.htaccess';
        if (file_exists($htaccess)) {
            $this->warning("Found .htaccess in project root — FrankenPHP/Caddy ignores it. Use Caddyfile denies.");
        }

        $this->info("✓ FrankenPHP files copied");
    }

    /**
     * @return array<string, string>
     */
    protected function getWebserverSpecificFileMappings(): array
    {
        return self::FRANKENPHP_FILE_MAPPINGS;
    }

    protected function getDefaultPort(): int
    {
        return 80;
    }

    protected function getStartCommand(): string
    {
        return 'frankenphp run --config Caddyfile';
    }

    /**
     * @return array<string>
     */
    protected function getAdditionalInstructions(): array
    {
        return [
            "Document Root:",
            " • Project root (index.php beside app/) — Caddyfile sets root",
            " • Place static assets under public/ if needed",
            "",
            "Security (Caddyfile — not .htaccess):",
            " • Denies /app, /vendor, /bin, secrets",
            " • Do not rely on Apache .htaccess with FrankenPHP",
            "",
            "FrankenPHP:",
            " • Classic: index.php + Caddyfile (default)",
            " • Worker: worker.php + Caddyfile.worker (no die(); see docs/guides/frankenphp.md)",
            " • Docker image: dunglas/frankenphp:1-php8.4-bookworm",
            " • Local classic: frankenphp run --config Caddyfile",
            " • Local worker: frankenphp run --config Caddyfile.worker"
        ];
    }
}

<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\AbstractInit;

/**
 * Initialize a new GEMVC OpenSwoole project
 * 
 * This command sets up a new project specifically configured for OpenSwoole,
 * including Dockerfile, and OpenSwoole-specific configurations.
 * 
 * Extends AbstractInit to leverage shared initialization functionality while
 * providing OpenSwoole-specific implementations.
 * 
 * @package Gemvc\CLI\Commands
 */
class InitSwoole extends AbstractInit
{
    
    /**
     * OpenSwoole-specific file mappings
     * Maps source files to destination paths
     */
    private const SWOOLE_FILE_MAPPINGS = [
        'appIndex.php' => 'app/api/Index.php'
    ];
    
    /**
     * Get the webserver type identifier
     * 
     * @return string
     */
    protected function getWebserverType(): string
    {
        return 'OpenSwoole';
    }
    
    /**
     * Get OpenSwoole-specific required directories
     * These directories are in addition to the base directories
     * 
     * @return array<string>
     */
    protected function getWebserverSpecificDirectories(): array
    {
        // server/handlers/ directory no longer needed (unused legacy code)
        // return self::SWOOLE_DIRECTORIES;
        return [];
    }
    
    /**
     * Copy OpenSwoole-specific files
     * This includes:
     * - index.php (OpenSwoole bootstrap)
     * - Dockerfile (OpenSwoole container configuration)
     * - appIndex.php -> app/api/Index.php
     * 
     * Note: server/handlers/ directory copying is commented out (unused legacy code)
     * 
     * @return void
     */
    protected function copyWebserverSpecificFiles(): void
    {
        $this->info("Copying OpenSwoole-specific files...");
        
        $startupPath = $this->findStartupPath();
        
        // Copy OpenSwoole files to project root
        $filesToCopy = [
            'index.php',
            'Dockerfile',
            // 'docker-compose.yml', // Let DockerComposeInit create it with user-selected services
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
        
        // Copy appIndex.php to app/api/Index.php
        foreach (self::SWOOLE_FILE_MAPPINGS as $sourceFileName => $destPath) {
            $sourceFile = $startupPath . DIRECTORY_SEPARATOR . $sourceFileName;
            $destFile = $this->basePath . DIRECTORY_SEPARATOR . $destPath;
            
            if (file_exists($sourceFile)) {
                // Ensure directory exists
                $destDir = dirname($destFile);
                $this->fileSystem->createDirectoryIfNotExists($destDir);
                $this->fileSystem->copyFileWithConfirmation($sourceFile, $destFile, $sourceFileName);
            }
        }       
        $this->info("✓ OpenSwoole files copied");
    }
    
    /**
     * Get the startup template path for OpenSwoole
     * 
     * @return string
     */
    public function __construct(array $args = [], array $options = [])
    {
        parent::__construct($args, $options);
        $this->setPackageName('swoole');
        // All OpenSwoole dependencies are automatically installed via gemvc/connection-openswoole
        // which is a dependency of gemvc/library
    }
    
    protected function getStartupTemplatePath(): string
    {
        $webserverType = strtolower($this->getWebserverType());
        
        // Try webserver-specific path first
        $webserverPath = $this->packagePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup' . DIRECTORY_SEPARATOR . $webserverType;
        if (is_dir($webserverPath)) {
            return $webserverPath;
        }
        
        // Try Composer package path with package name from property
        $composerWebserverPath = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'gemvc' . DIRECTORY_SEPARATOR . $this->packageName . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup' . DIRECTORY_SEPARATOR . $webserverType;
        if (is_dir($composerWebserverPath)) {
            return $composerWebserverPath;
        }
        
        // Fallback to default startup path (current structure)
        return $this->packagePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup';
    }
    
    /**
     * Get OpenSwoole-specific file mappings
     * 
     * @return array<string, string>
     */
    protected function getWebserverSpecificFileMappings(): array
    {
        return self::SWOOLE_FILE_MAPPINGS;
    }
    
    /**
     * Get the default port number for OpenSwoole
     * 
     * @return int
     */
    protected function getDefaultPort(): int
    {
        return 9501;
    }
    
    /**
     * Get the command to start OpenSwoole server
     * 
     * @return string
     */
    protected function getStartCommand(): string
    {
        return 'php index.php';
    }
    
    /**
     * Get OpenSwoole-specific additional instructions
     * 
     * @return array<string>
     */
    protected function getAdditionalInstructions(): array
    {
        return [
            "\033[1;36mHot Reload (Development):\033[0m",
            " \033[1;36m$ \033[1;95mphp index.php --hot-reload\033[0m",
            "   \033[90m# Auto-restart server on file changes\033[0m",
            "",
            "\033[1;94mWebSocket Support:\033[0m",
            " • WebSocket support available via OpenSwooleServer class",
            " • View logs: \033[1;95mtail -f swoole.log\033[0m"
        ];
    }
    
}
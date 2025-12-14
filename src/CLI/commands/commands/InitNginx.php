<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\AbstractInit;

/**
 * Initialize a new GEMVC Nginx project
 * 
 * This command sets up a new project specifically configured for Nginx,
 * including nginx.conf, public directory structure, and Nginx-specific configurations.
 * 
 * Extends AbstractInit to leverage shared initialization functionality while
 * providing Nginx-specific implementations.
 * 
 * @package Gemvc\CLI\Commands
 */
class InitNginx extends AbstractInit
{
    /**
     * Nginx-specific required directories
     */
    private const NGINX_DIRECTORIES = [
        'public'
    ];
    
    /**
     * Nginx-specific file mappings
     * Maps source files to destination paths
     */
    private const NGINX_FILE_MAPPINGS = [
        'appIndex.php' => 'app/api/Index.php'
    ];
    
    /**
     * Constructor - set Nginx package name
     */
    public function __construct(array $args = [], array $options = [])
    {
        parent::__construct($args, $options);
        $this->setPackageName('nginx');
    }
    
    /**
     * Get the webserver type identifier
     * 
     * @return string
     */
    protected function getWebserverType(): string
    {
        return 'Nginx';
    }
    
    /**
     * Get Nginx-specific required directories
     * These directories are in addition to the base directories
     * 
     * @return array<string>
     */
    protected function getWebserverSpecificDirectories(): array
    {
        return self::NGINX_DIRECTORIES;
    }
    
    /**
     * Copy Nginx-specific files
     * This includes:
     * - index.php (Nginx bootstrap)
     * - nginx.conf (URL rewriting rules)
     * - .env (from example.env)
     * - composer.json, Dockerfile, docker-compose.yml
     * - .gitignore, .dockerignore
     * 
     * @return void
     */
    protected function copyWebserverSpecificFiles(): void
    {
        $this->info("📄 Copying Nginx-specific files...");
        
        $startupPath = $this->findStartupPath();
        
        // Copy all Nginx files to project root
        $filesToCopy = [
            'index.php',
            'nginx.conf',
            'composer.json',
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
        foreach (self::NGINX_FILE_MAPPINGS as $sourceFileName => $destPath) {
            $sourceFile = $startupPath . DIRECTORY_SEPARATOR . $sourceFileName;
            $destFile = $this->basePath . DIRECTORY_SEPARATOR . $destPath;
            
            if (file_exists($sourceFile)) {
                // Ensure directory exists
                $destDir = dirname($destFile);
                $this->fileSystem->createDirectoryIfNotExists($destDir);
                $this->fileSystem->copyFileWithConfirmation($sourceFile, $destFile, $sourceFileName);
            }
        }
        
        // Note: .env is created by createEnvFile() method in AbstractInit
        // No need to copy it here to avoid duplicate prompts
        
        $this->info("✅ Nginx files copied");
    }
    
    /**
     * Get the startup template path for Nginx
     * 
     * @return string
     */
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
     * Get Nginx-specific file mappings
     * 
     * @return array<string, string>
     */
    protected function getWebserverSpecificFileMappings(): array
    {
        return self::NGINX_FILE_MAPPINGS;
    }
    
    /**
     * Get the default port number for Nginx
     * 
     * @return int
     */
    protected function getDefaultPort(): int
    {
        return 80;
    }
    
    /**
     * Get the command to start Nginx server
     * 
     * @return string
     */
    protected function getStartCommand(): string
    {
        return 'php -S localhost:80 -t public';
    }
    
    /**
     * Get Nginx-specific additional instructions
     * 
     * @return array<string>
     */
    protected function getAdditionalInstructions(): array
    {
        return [
            "\033[1;94m📁 Document Root:\033[0m",
            " • All files are served from \033[1;36mpublic/\033[0m directory",
            " • Place your assets in \033[1;36mpublic/assets/\033[0m",
            "",
            "\033[1;94m🔧 URL Rewriting:\033[0m",
            " • nginx.conf configured for clean URLs",
            " • All requests routed through \033[1;36mindex.php\033[0m",
            "",
            "\033[1;94m⚙️ Nginx Configuration:\033[0m",
            " • Configure nginx.conf for your server",
            " • For production: \033[1;95msudo nginx -t\033[0m (test config)",
            " • For production: \033[1;95msudo nginx -s reload\033[0m (reload config)"
        ];
    }
}


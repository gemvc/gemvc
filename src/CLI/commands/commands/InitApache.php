<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\AbstractInit;

/**
 * Initialize a new GEMVC Apache project
 * 
 * This command sets up a new project specifically configured for Apache,
 * including .htaccess, public directory structure, and Apache-specific configurations.
 * 
 * Extends AbstractInit to leverage shared initialization functionality while
 * providing Apache-specific implementations.
 * 
 * @package Gemvc\CLI\Commands
 */
class InitApache extends AbstractInit
{
    /**
     * Apache-specific required directories
     */
    private const APACHE_DIRECTORIES = [
        'public'
    ];
    
    /**
     * Apache-specific file mappings
     * Maps source files to destination paths
     */
    private const APACHE_FILE_MAPPINGS = [
        'appIndex.php' => 'app/api/Index.php'
    ];
    
    /**
     * Constructor - set Apache package name
     */
    public function __construct(array $args = [], array $options = [])
    {
        parent::__construct($args, $options);
        $this->setPackageName('apache');
    }
    
    /**
     * Get the webserver type identifier
     * 
     * @return string
     */
    protected function getWebserverType(): string
    {
        return 'Apache';
    }
    
    /**
     * Get Apache-specific required directories
     * These directories are in addition to the base directories
     * 
     * @return array<string>
     */
    protected function getWebserverSpecificDirectories(): array
    {
        return self::APACHE_DIRECTORIES;
    }
    
    /**
     * Copy Apache-specific files
     * This includes:
     * - index.php (Apache bootstrap)
     * - .htaccess (URL rewriting rules)
     * - .env (from example.env)
     * - composer.json, Dockerfile, docker-compose.yml
     * - .gitignore, .dockerignore
     * 
     * @return void
     */
    protected function copyWebserverSpecificFiles(): void
    {
        $this->info("📄 Copying Apache-specific files...");
        
        $startupPath = $this->findStartupPath();
        
        // Copy all Apache files to project root
        $filesToCopy = [
            'index.php',
            '.htaccess',
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
        foreach (self::APACHE_FILE_MAPPINGS as $sourceFileName => $destPath) {
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
        
        $this->info("✅ Apache files copied");
    }
    
    /**
     * Get the startup template path for Apache
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
     * Get Apache-specific file mappings
     * 
     * @return array<string, string>
     */
    protected function getWebserverSpecificFileMappings(): array
    {
        return self::APACHE_FILE_MAPPINGS;
    }
    
    /**
     * Get the default port number for Apache
     * 
     * @return int
     */
    protected function getDefaultPort(): int
    {
        return 80;
    }
    
    /**
     * Get the command to start Apache server
     * 
     * @return string
     */
    protected function getStartCommand(): string
    {
        return 'php -S localhost:80 -t public';
    }
    
    /**
     * Get Apache-specific additional instructions
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
            " • .htaccess configured for clean URLs",
            " • All requests routed through \033[1;36mpublic/index.php\033[0m",
            "",
            "\033[1;94m⚙️ Apache Configuration:\033[0m",
            " • Enable mod_rewrite: \033[1;95msudo a2enmod rewrite\033[0m",
            " • Restart Apache: \033[1;95msudo service apache2 restart\033[0m"
        ];
    }
}


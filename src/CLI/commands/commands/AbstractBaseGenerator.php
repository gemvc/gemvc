<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\FileSystemManager;

abstract class AbstractBaseGenerator extends Command
{
    protected string $serviceName;
    protected string $basePath;
    /** @var array<string, bool> */
    protected array $flags = [];
    protected FileSystemManager $fileSystem;

    public function __construct(array $args = [], array $options = [])
    {
        parent::__construct($args, $options);
        $this->fileSystem = new FileSystemManager(false); // Default to interactive mode
    }

    /**
     * Format service name to proper case
     * 
     * @param string $name
     * @return string
     */
    protected function formatServiceName(string $name): string
    {
        return ucfirst(strtolower($name));
    }

    /**
     * Create necessary directories
     * 
     * @param array<string> $directories
     * @return void
     * @throws \RuntimeException
     */
    protected function createDirectories(array $directories): void
    {
        $this->fileSystem->createDirectories($directories);
    }

    /**
     * Confirm file overwrite
     * 
     * @param string $path
     * @return bool
     */
    protected function confirmOverwrite(string $path): bool
    {
        return $this->fileSystem->confirmFileOverwrite($path);
    }

    /**
     * Write file to disk
     * 
     * @param string $path
     * @param string $content
     * @param string $fileType
     * @return void
     */
    protected function writeFile(string $path, string $content, string $fileType): void
    {
        $this->fileSystem->writeFile($path, $content, $fileType);
    }

    /**
     * Determine project root directory
     * 
     * @return string
     */
    protected function determineProjectRoot(): string
    {
        // Start with composer's vendor directory (where this file is located)
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        
        // If we're in the vendor directory, the project root is one level up
        if (basename($vendorDir) === 'vendor') {
            return dirname($vendorDir);
        }
        
        // Fallback to current directory if we can't determine project root
        return getcwd() ?: '.';
    }

    /**
     * Get template content from project root
     * 
     * @param string $templateName
     * @return string
     */
    protected function getTemplate(string $templateName): string
    {
        // Try project root templates first (user customizable)
        $projectRoot = $this->basePath ?? $this->determineProjectRoot();
        $templatePath = $projectRoot .DIRECTORY_SEPARATOR. "templates".DIRECTORY_SEPARATOR."cli".DIRECTORY_SEPARATOR. "{$templateName}.template";
        
        if (file_exists($templatePath)) {
            $content = file_get_contents($templatePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read template: {$templatePath}");
            }
            return $content;
        }
        
        // Fallback to vendor templates if project templates don't exist
        $vendorTemplatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "cli" . DIRECTORY_SEPARATOR . "{$templateName}.template";
        if (file_exists($vendorTemplatePath)) {
            $this->warning("Using vendor template for {$templateName} - consider copying templates to project root");
            $content = file_get_contents($vendorTemplatePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read vendor template: {$vendorTemplatePath}");
            }
            return $content;
        }
        
        throw new \RuntimeException("Template not found: {$templateName} (checked: {$templatePath}, {$vendorTemplatePath})");
    }

    /**
     * Replace template variables
     * 
     * @param string $content
     * @param array<string, string> $variables
     * @return string
     */
    protected function replaceTemplateVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{$' . $key . '}', $value, $content);
        }
        return $content;
    }

    /**
     * Execute the command
     * 
     * @return bool
     */
    abstract public function execute(): bool;
} 
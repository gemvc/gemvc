<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Commands\AbstractBaseCrudGenerator;

class CreateModel extends AbstractBaseCrudGenerator
{
    protected string $serviceName;
    protected string $basePath;
    /** @var array<string, bool> */
    protected array $flags = [];

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
     * Parse command line flags
     * 
     * @return void
     */
    protected function parseFlags(): void
    {
        $this->flags = [
            'table' => false
        ];

        // Check for table flag
        if (isset($this->args[1]) && is_string($this->args[1]) && strpos($this->args[1], '-') === 0) {
            $flagStr = substr($this->args[1], 1);
            $this->flags['table'] = strpos($flagStr, 't') !== false;
        }
    }

    public function execute(): bool
    {
        if (empty($this->args[0]) || !is_string($this->args[0])) {
            $this->error("Model name is required. Usage: gemvc create:model ModelName [-t]");
            return false;
        }

        $this->serviceName = $this->formatServiceName($this->args[0]);
        $this->basePath = defined('PROJECT_ROOT') ? PROJECT_ROOT : $this->determineProjectRoot();
        $this->parseFlags();

        try {
            // Create necessary directories
            $this->createDirectories($this->getRequiredDirectories());

            // Create model file
            $this->createModel();

            // Create table file if flag is set
            if ($this->flags['table']) {
                $this->createTable();
            }

            $this->success("Model {$this->serviceName} created successfully!");
            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }


    protected function createModel(): void
    {
        $template = $this->getTemplate('model');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName
        ]);

        $path = $this->basePath . "/app/model/{$this->serviceName}Model.php";
        $this->writeFile($path, $content, "Model");
    }

    protected function createTable(): bool
    {
        $template = $this->getTemplate('table');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName,
            'tableName' => strtolower($this->serviceName) . 's'
        ]);

        $path = $this->basePath . "/app/table/{$this->serviceName}Table.php";
        $this->writeFile($path, $content, "Table");
        return true;
    }

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
} 
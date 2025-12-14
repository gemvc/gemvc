<?php

namespace Gemvc\CLI\Commands;

abstract class AbstractBaseCrudGenerator extends AbstractBaseGenerator
{
    protected string $serviceName;
    protected string $basePath;
    /** @var array<string, bool> */
    protected array $flags = [];

    /**
     * Parse command line flags
     * 
     * @return void
     */
    protected function parseFlags(): void
    {
        $this->flags = [
            'controller' => false,
            'model' => false,
            'table' => false
        ];

        // Check for combined flags (e.g., -cmt)
        if (isset($this->args[1]) && is_string($this->args[1]) && strpos($this->args[1], '-') === 0) {
            $flagStr = substr($this->args[1], 1);
            $this->flags['controller'] = strpos($flagStr, 'c') !== false;
            $this->flags['model'] = strpos($flagStr, 'm') !== false;
            $this->flags['table'] = strpos($flagStr, 't') !== false;
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get required directories based on flags
     * 
     * @return array<string>
     */
    protected function getRequiredDirectories(): array
    {
        $directories = [];
        
        if ($this instanceof CreateController || $this instanceof CreateCrud) {
            $directories[] = $this->getBasePath() . '/app/controller';
        }
        
        if ($this instanceof CreateModel || $this instanceof CreateCrud) {
            $directories[] = $this->getBasePath() . '/app/model';
        }
        
        if ($this instanceof CreateTable || $this instanceof CreateCrud) {
            $directories[] = $this->getBasePath() . '/app/table';
        }
        
        if ($this instanceof CreateService || $this instanceof CreateCrud) {
            $directories[] = $this->getBasePath() . '/app/api';
        }
        
        return array_unique($directories);
    }

    /**
     * Create service file
     * 
     * @return void
     */
    protected function createService(): void
    {
        if (!$this->flags['controller']) {
            return;
        }

        $template = $this->getTemplate('service');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName
        ]);

        $path = $this->getBasePath() . "/app/api/{$this->serviceName}.php";
        $this->writeFile($path, $content, "Service");
    }

    /**
     * Create controller file
     * 
     * @return void
     */
    protected function createController(): void
    {
        if (!$this->flags['controller']) {
            return;
        }

        $template = $this->getTemplate('controller');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName
        ]);

        $path = $this->getBasePath() . "/app/controller/{$this->serviceName}Controller.php";
        $this->writeFile($path, $content, "Controller");
    }

    /**
     * Create model file
     * 
     * @return void
     */
    protected function createModel(): void
    {
        if (!$this->flags['model']) {
            return;
        }

        $template = $this->getTemplate('model');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName
        ]);

        $path = $this->getBasePath() . "/app/model/{$this->serviceName}Model.php";
        $this->writeFile($path, $content, "Model");
    }

    /**
     * Create table file
     * 
     * @return bool
     */
    protected function createTable(): bool
    {
        if (!$this->flags['table']) {
            return false;
        }

        $template = $this->getTemplate('table');
        $content = $this->replaceTemplateVariables($template, [
            'serviceName' => $this->serviceName,
            'tableName' => strtolower($this->serviceName) . 's'
        ]);

        $path = $this->getBasePath() . "/app/table/{$this->serviceName}Table.php";
        $this->writeFile($path, $content, "Table");
        return true;
    }
} 
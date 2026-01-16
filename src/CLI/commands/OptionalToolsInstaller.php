<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;

/**
 * Optional Tools Installer
 * 
 * Handles installation of optional development tools like PHPStan, PHPUnit, and Pest.
 * Follows Single Responsibility Principle - only handles tool installation.
 */
class OptionalToolsInstaller extends Command
{
    private string $basePath;
    private string $packagePath;
    private bool $nonInteractive = false;
    
    public function __construct(string $basePath, string $packagePath, bool $nonInteractive = false)
    {
        $this->basePath = $basePath;
        $this->packagePath = $packagePath;
        $this->nonInteractive = $nonInteractive;
    }
    
    /**
     * Required by Command abstract class
     */
    public function execute(): bool
    {
        $this->error("OptionalToolsInstaller should not be executed directly. Use offerOptionalTools() method instead.");
        return false;
    }
    
    /**
     * Offer optional tools installation
     */
    public function offerOptionalTools(): void
    {
        if ($this->nonInteractive) {
            $this->info("Skipped optional tools installation (non-interactive mode)");
            return;
        }
        
        $this->offerPhpstanInstallation();
        $this->offerTestingFrameworkInstallation();
    }
    
    /**
     * Offer PHPStan installation
     */
    public function offerPhpstanInstallation(): void
    {
        $this->displayToolInstallationPrompt(
            "PHPStan Installation",
            "Would you like to install PHPStan for static analysis?",
            "PHPStan will help catch bugs and improve code quality",
            "This will install phpstan/phpstan as a dev dependency"
        );
        
        echo "\n\033[1;36mInstall PHPStan? (y/N):\033[0m ";
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            $this->error("Failed to open stdin");
            return;
        }
        $line = fgets($handle);
        fclose($handle);
        $choice = $line !== false ? trim($line) : '';
        
        if (strtolower($choice) === 'y') {
            $this->installPhpstan();
        } else {
            $this->info("PHPStan installation skipped");
        }
    }
    
    /**
     * Offer testing framework installation
     */
    public function offerTestingFrameworkInstallation(): void
    {
        $this->displayToolInstallationPrompt(
            "Testing Framework Installation",
            "Would you like to install a testing framework?",
            "Choose between PHPUnit (traditional) or Pest (modern & expressive)"
        );
        
        echo "\n\033[1;36mChoose testing framework:\033[0m\n";
        echo "  [\033[32m1\033[0m] \033[1mPHPUnit\033[0m - Traditional PHP testing framework\n";
        echo "  [\033[32m2\033[0m] \033[1mPest\033[0m - Modern, expressive testing framework\n";
        echo "  [\033[32m3\033[0m] \033[1mSkip\033[0m - No testing framework\n";
        echo "\n\033[1;36mEnter choice (1-3):\033[0m ";
        
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            $this->error("Failed to open stdin");
            return;
        }
        $line = fgets($handle);
        fclose($handle);
        $choice = $line !== false ? trim($line) : '';
        
        switch ($choice) {
            case '1':
                $this->installPhpunit();
                break;
            case '2':
                $this->installPest();
                break;
            case '3':
                $this->info("Testing framework installation skipped");
                break;
            default:
                $this->warning("Invalid choice. Testing framework installation skipped.");
                break;
        }
    }
    
    /**
     * Display tool installation prompt
     */
    private function displayToolInstallationPrompt(string $title, string $question, string $description, string $additionalInfo = ''): void
    {
        $boxShow = new CliBoxShow();
        $boxShow->displayToolInstallationPrompt($title, $question, $description, $additionalInfo);
    }
    
    /**
     * Install PHPStan
     */
    public function installPhpstan(): void
    {
        try {
            $this->info("Installing PHPStan...");
            $this->runComposerCommand('require --dev phpstan/phpstan');
            $this->copyPhpstanConfig();
            $this->info("PHPStan installed successfully!");
        } catch (\Exception $e) {
            $this->warning("PHPStan installation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Install PHPUnit
     */
    public function installPhpunit(): void
    {
        try {
            $this->info("Installing PHPUnit...");
            $this->runComposerCommand('require --dev phpunit/phpunit');
            $this->createPhpunitConfig();
            $this->info("PHPUnit installed successfully!");
        } catch (\Exception $e) {
            $this->warning("PHPUnit installation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Install Pest
     */
    public function installPest(): void
    {
        try {
            $this->info("Installing Pest...");
            $this->runComposerCommand('require --dev pestphp/pest');
            $this->initializePest();
            $this->info("Pest installed successfully!");
        } catch (\Exception $e) {
            $this->warning("Pest installation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Run composer command
     */
    private function runComposerCommand(string $command): void
    {
        $composerJsonPath = $this->basePath . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            throw new \RuntimeException("composer.json not found. Please run 'composer init' first.");
        }
        
        // Add flags to prevent issues:
        // --no-interaction: Don't ask questions
        // --no-scripts: Don't run scripts (prevents binary file locking issues)
        // --prefer-stable: Prefer stable versions (helps with alpha version conflicts)
        $enhancedCommand = $command . ' --no-interaction --no-scripts --prefer-stable';
        
        $this->info("Running: composer {$enhancedCommand}");
        $output = [];
        $returnCode = 0;
        
        $currentDir = getcwd();
        if ($currentDir === false) {
            throw new \RuntimeException("Could not get current directory");
        }
        if (chdir($this->basePath) === false) {
            throw new \RuntimeException("Could not change to directory: {$this->basePath}");
        }
        
        exec("composer {$enhancedCommand} 2>&1", $output, $returnCode);
        
        if (chdir($currentDir) === false) {
            throw new \RuntimeException("Could not restore directory: {$currentDir}");
        }
        
        if ($returnCode !== 0) {
            $this->warning("Failed to run composer command. Error output:");
            foreach ($output as $line) {
                $this->write("  {$line}\n", 'red');
            }
            
            // Check for specific errors and provide helpful suggestions
            $errorText = implode("\n", $output);
            
            // Extract package name from command (e.g., "require --dev phpstan/phpstan" -> "phpstan/phpstan")
            $packageName = '';
            if (preg_match('/require\s+--dev\s+([^\s]+)/', $command, $matches)) {
                $packageName = $matches[1];
            }
            
            if (strpos($errorText, 'Permission denied') !== false || strpos($errorText, 'Failed to open stream') !== false) {
                $this->info("\nPermission Issue Detected:");
                $this->info("   The vendor/bin/gemvc file might be locked or in use.");
                $this->info("   Solutions:");
                $this->info("   1. Close any terminals/processes using the gemvc command");
                $this->info("   2. Run this terminal as Administrator");
                if ($packageName) {
                    $this->info("   3. Manually install: composer require --dev {$packageName}");
                }
            }
            
            if (strpos($errorText, 'Downgrading') !== false || strpos($errorText, 'version conflict') !== false) {
                $this->info("\nVersion Conflict Detected:");
                $this->info("   There's a version conflict with gemvc/library.");
                $this->info("   Solutions:");
                $this->info("   1. Update gemvc/library first: composer update gemvc/library");
                if ($packageName) {
                    $this->info("   2. Or manually install: composer require --dev {$packageName} --with-all-dependencies");
                }
            }
            
            throw new \RuntimeException("Composer command failed");
        }
    }
    
    /**
     * Copy PHPStan configuration
     */
    private function copyPhpstanConfig(): void
    {
        $sourceConfig = $this->packagePath . '/src/startup/common/phpstan.neon';
        $targetConfig = $this->basePath . '/phpstan.neon';
        
        if (!file_exists($sourceConfig)) {
            $this->warning("PHPStan configuration template not found: {$sourceConfig}");
            return;
        }
        
        // If file already exists, skip copying to avoid overwriting user's custom config
        if (file_exists($targetConfig)) {
            $this->info("PHPStan configuration already exists: {$targetConfig}");
            return;
        }
        
        if (!copy($sourceConfig, $targetConfig)) {
            throw new \RuntimeException("Failed to copy PHPStan configuration file");
        }
        
        $this->info("PHPStan configuration copied: {$targetConfig}");
    }
    
    /**
     * Create PHPUnit configuration
     */
    private function createPhpunitConfig(): void
    {
        $targetConfig = $this->basePath . '/phpunit.xml';
        
        // If file already exists, skip creating to avoid overwriting user's custom config
        if (file_exists($targetConfig)) {
            $this->info("PHPUnit configuration already exists: {$targetConfig}");
            return;
        }
        
        $phpunitConfig = '<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         processIsolation="false"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="GEMVC Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">app</directory>
        </include>
    </coverage>
</phpunit>';
        
        if (!file_put_contents($targetConfig, $phpunitConfig)) {
            throw new \RuntimeException("Failed to create PHPUnit configuration file");
        }
        
        $this->info("PHPUnit configuration created: {$targetConfig}");
        
        // Create tests directory
        $testsDir = $this->basePath . '/tests';
        $this->createDirectoryIfNotExists($testsDir);
    }
    
    /**
     * Initialize Pest
     */
    private function initializePest(): void
    {
        $this->info("Initializing Pest...");
        
        $currentDir = getcwd();
        if ($currentDir === false) {
            throw new \RuntimeException("Could not get current directory");
        }
        if (chdir($this->basePath) === false) {
            throw new \RuntimeException("Could not change to directory: {$this->basePath}");
        }
        
        $output = [];
        $returnCode = 0;
        exec('./vendor/bin/pest --init 2>&1', $output, $returnCode);
        
        if (chdir($currentDir) === false) {
            throw new \RuntimeException("Could not restore directory: {$currentDir}");
        }
        
        if ($returnCode !== 0) {
            $this->warning("Failed to initialize Pest. Error output:");
            foreach ($output as $line) {
                $this->write("  {$line}\n", 'red');
            }
            throw new \RuntimeException("Pest initialization failed");
        }
        
        $this->info("Pest initialized successfully!");
    }
    
    /**
     * Create a single directory with proper error handling
     */
    private function createDirectoryIfNotExists(string $path): void
    {
        if (is_dir($path)) {
            $this->info("Directory already exists: {$path}");
            return;
        }
        
        if (!@mkdir($path, 0755, true)) {
            throw new \RuntimeException("Failed to create directory: {$path}");
        }
        
        $this->info("Created directory: {$path}");
    }
}

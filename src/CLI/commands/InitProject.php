<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\InitSwoole;
use Gemvc\CLI\Commands\InitApache;
use Gemvc\CLI\Commands\InitNginx;
use Gemvc\CLI\Commands\CliBoxShow;

/**
 * Initialize a new GEMVC project with webserver selection
 * 
 * This is the main initialization orchestrator that allows users to choose
 * between different webserver configurations:
 * - OpenSwoole (High-performance async server)
 * - Apache (Traditional PHP hosting)
 * - Nginx (Modern web server with PHP-FPM)
 * 
 * The orchestrator delegates the actual initialization to the appropriate
 * webserver-specific Init class (InitSwoole, InitApache, or InitNginx).
 * 
 * @package Gemvc\CLI\Commands
 */
class InitProject extends Command
{
    /**
     * Available webserver options
     */
    private const WEBSERVER_OPTIONS = [
        '1' => [
            'name' => 'OpenSwoole',
            'class' => InitSwoole::class,
            //'package' => 'gemvc/library',
            'package' => null,
            'description' => 'High-performance async server with WebSocket support',
            'status' => 'available',
            'icon' => '🔵',
            'color' => 'blue'
        ],
        '2' => [
            'name' => 'Apache',
            'class' => InitApache::class,
            'package' => null, // No separate package needed - included in gemvc/library
            'description' => 'Traditional PHP hosting with mod_php or PHP-FPM',
            'status' => 'available',
            'icon' => '🔴',
            'color' => 'red'
        ],
        '3' => [
            'name' => 'Nginx',
            'class' => InitNginx::class,
            'package' => null, // No separate package needed - included in gemvc/library
            'description' => 'Modern web server with PHP-FPM',
            'status' => 'available',
            'icon' => '🟢',
            'color' => 'green'
        ]
    ];
    
    /**
     * Execute the initialization orchestrator
     * 
     * @return bool
     */
    public function execute(): bool
    {
        // Display welcome banner
        $this->displayWelcomeBanner();
        
        // Check for non-interactive mode with webserver flag
        $webserverChoice = $this->getNonInteractiveChoice();
        
        if ($webserverChoice === null) {
            // Interactive mode: show menu and get user choice
            $webserverChoice = $this->displayWebserverMenu();
        }
        
        // Validate choice
        if (!isset(self::WEBSERVER_OPTIONS[$webserverChoice])) {
            $this->error("Invalid webserver choice: {$webserverChoice}");
            return false;
        }
        
        $webserver = self::WEBSERVER_OPTIONS[$webserverChoice];
        
        // Check if webserver is available
        // @phpstan-ignore-next-line identical.alwaysFalse - Defensive code for future webservers
        if ($webserver['status'] === 'coming_soon') {
            $this->warning("{$webserver['name']} support is coming soon!");
            $this->info("For now, please use OpenSwoole (option 1)");
            return false;
        }
        
        // Execute the selected webserver initialization
        return $this->executeWebserverInit($webserver);
    }
    
    /**
     * Display welcome banner
     * 
     * @return void
     */
    private function displayWelcomeBanner(): void
    {
        $boxShow = new CliBoxShow();
        
        $title = "Welcome";
        $initText = "GEMVC Framework initialization";
        $subtitle = "PHP Framework built for Microservices";
        
        // Use CliBoxShow to create properly aligned box with yellow borders
        // Title goes in the border, content lines go inside the box
        $lines = [
            "\033[1;32m💎 {$initText}\033[0m",  // Green gem emoji + Green, bold text
            "\033[37m{$subtitle}\033[0m"        // White
        ];
        
        $boxShow->displayBox($title, $lines);
        
        $this->write("\n\033[1;36mSelect Your Webserver:\033[0m\n\n", 'white');
    }
    
    /**
     * Get webserver choice from non-interactive flags
     * 
     * @return string|null Choice number or null if not in non-interactive mode
     */
    private function getNonInteractiveChoice(): ?string
    {
        // Check for --swoole flag
        if (in_array('--swoole', $this->args)) {
            $this->info("Using OpenSwoole (from --swoole flag)");
            return '1';
        }
        
        // Check for --apache flag
        if (in_array('--apache', $this->args)) {
            $this->info("Using Apache (from --apache flag)");
            return '2';
        }
        
        // Check for --nginx flag
        if (in_array('--nginx', $this->args)) {
            $this->info("Using Nginx (from --nginx flag)");
            return '3';
        }
        
        // Check for --server=<name> flag
        foreach ($this->args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (strpos($arg, '--server=') === 0) {
                $serverName = substr($arg, 9);
                switch (strtolower($serverName)) {
                    case 'swoole':
                    case 'openswoole':
                        $this->info("Using OpenSwoole (from --server flag)");
                        return '1';
                    case 'apache':
                        $this->info("Using Apache (from --server flag)");
                        return '2';
                    case 'nginx':
                        $this->info("Using Nginx (from --server flag)");
                        return '3';
                }
            }
        }
        
        return null;
    }
    
    /**
     * Display webserver selection menu and get user choice
     * 
     * @return string User's choice (1, 2, or 3)
     */
    private function displayWebserverMenu(): string
    {
        foreach (self::WEBSERVER_OPTIONS as $key => $option) {
            // Only show status badge if not available (e.g., "coming_soon")
            $statusBadge = '';
            
            // Get color code based on webserver
            $colorCode = '';
            // @phpstan-ignore-next-line impossibleType - All webserver options have 'color' key
            if (isset($option['color'])) {
                switch ($option['color']) {
                    case 'blue':
                        $colorCode = "\033[1;34m";
                        break;
                    case 'red':
                        $colorCode = "\033[1;31m";
                        break;
                    case 'green':
                        $colorCode = "\033[1;32m";
                        break;
                }
            }
            
            $this->write("  {$option['icon']} {$colorCode}[{$key}]\033[0m \033[1;97m{$option['name']}\033[0m{$statusBadge}\n", 'white');
            $this->write("      \033[90m{$option['description']}\033[0m\n\n", 'white');
        }
        
        // Get user input
        while (true) {
            $this->write("\033[1;36mEnter your choice (1-3) [1]:\033[0m ", 'white');
            
            $handle = fopen("php://stdin", "r");
            if ($handle === false) {
                $this->error("Failed to open stdin");
                return '1'; // Default to Swoole
            }
            
        $line = fgets($handle);
        fclose($handle);
        
            $choice = $line !== false ? trim($line) : '';
            
            // Default to OpenSwoole if empty
            if ($choice === '') {
                $choice = '1';
            }
            
            // Validate choice
            if (isset(self::WEBSERVER_OPTIONS[$choice])) {
                $selected = self::WEBSERVER_OPTIONS[$choice];
                $this->info("Selected: {$selected['name']}");
                return $choice;
            }
            
            $this->warning("Invalid choice. Please enter 1, 2, or 3.");
        }
    }
    
    /**
     * Execute the selected webserver initialization
     * 
     * @param array<string, mixed> $webserver Webserver configuration
     * @return bool
     */
    private function executeWebserverInit(array $webserver): bool
    {
        $this->write("\n", 'white');
        
        // Validate webserver name
        $webserverName = isset($webserver['name']) && is_string($webserver['name']) 
            ? $webserver['name'] 
            : 'Unknown';
        $packageName = isset($webserver['package']) && is_string($webserver['package']) 
            ? $webserver['package'] 
            : null;
        
        $this->info("Initializing {$webserverName} project...");
        
        // Check if specific package is required and not installed
        if ($packageName !== null && !$this->isPackageInstalled($packageName)) {
            if (!$this->offerPackageInstallation($packageName, $webserverName)) {
                return false;
            }
        }
        
        $this->write("\n", 'white');
        
        // Get the class name
        $className = $webserver['class'];
        
        // Validate class name is string
        if (!is_string($className)) {
            $this->error("Invalid class configuration");
            return false;
        }
        
        // Check if class exists
        if (!class_exists($className)) {
            $this->error("Initialization class not found: {$className}");
            $this->info("This webserver option may not be fully implemented yet.");
            return false;
        }
        
        // Create instance and execute
        try {
            // Pass through all args and options to the webserver init
            /** @var Command $initCommand */
            $initCommand = new $className($this->args, $this->options);
            
            return $initCommand->execute();
        } catch (\Exception $e) {
            $this->error("Failed to initialize project: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a package is installed
     * 
     * @param string $packageName
     * @return bool
     */
    private function isPackageInstalled(string $packageName): bool
    {
        $composerLockFile = getcwd() . '/composer.lock';
        if (!file_exists($composerLockFile)) {
            return false;
        }
        
        $lockContent = file_get_contents($composerLockFile);
        if ($lockContent === false) {
            return false;
        }
        
        return strpos($lockContent, '"name": "' . $packageName . '"') !== false;
    }
    
    /**
     * Offer to install required package
     * 
     * @param string $packageName
     * @param string $webserverName
     * @return bool
     */
    private function offerPackageInstallation(string $packageName, string $webserverName): bool
    {
        $this->warning("Package {$packageName} is required for {$webserverName} but not installed.");
        
        if (in_array('--non-interactive', $this->args) || in_array('-n', $this->args)) {
            $this->info("Non-interactive mode: attempting automatic installation...");
            return $this->installPackage($packageName);
        }
        
        $this->info("Would you like to install {$packageName} now? [Y/n]: ");
        $response = fgets(STDIN);
        if ($response !== false) {
            $response = trim($response);
        } else {
            $response = '';
        }
        
        if (empty($response) || strtolower($response[0]) === 'y') {
            return $this->installPackage($packageName);
        }
        
        $this->error("Cannot proceed without {$packageName}. Please install it manually:");
        $this->info("composer require {$packageName}");
        return false;
    }
    
    /**
     * Install package via composer
     * 
     * @param string $packageName
     * @return bool
     */
    private function installPackage(string $packageName): bool
    {
        $this->info("Installing {$packageName}...");
        
        $command = "composer require {$packageName}";
        $output = [];
        $returnCode = 0;
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->info("✓ {$packageName} installed successfully!");
            return true;
        } else {
            $this->error("Failed to install {$packageName}:");
            $this->error(implode("\n", $output));
            $this->info("Please install manually: composer require {$packageName}");
            return false;
        }
    }
}

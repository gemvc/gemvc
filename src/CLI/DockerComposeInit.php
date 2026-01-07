<?php

namespace Gemvc\CLI;

use Gemvc\CLI\Command;

/**
 * Docker Compose Initialization Manager
 * 
 * Handles creation and management of docker-compose.yml files with optional services.
 * Allows users to choose which services to include in their development environment.
 */
class DockerComposeInit extends Command
{
    private string $basePath;
    private bool $nonInteractive = false;
    /** @var array<string> */
    private array $selectedServices = [];
    private bool $developmentMode = true;
    private string $webserverType = 'openswoole';
    private int $webserverPort = 9501;
    private int $cpuLimit = 2;
    private string $memoryLimit = '2g';
    
    // Available services configuration
    private const AVAILABLE_SERVICES = [
        'redis' => [
            'name' => 'Redis',
            'description' => 'Redis cache and session storage',
            'default' => true,
            'image' => 'redis:latest',
            'ports' => ['6379:6379'],
            'volumes' => ['redis-data:/data']
        ],
        'phpmyadmin' => [
            'name' => 'phpMyAdmin',
            'description' => 'Web-based MySQL administration tool',
            'default' => true,
            'image' => 'phpmyadmin/phpmyadmin',
            'ports' => ['8080:80'],
            'environment' => [
                'PMA_HOST' => 'db',
                'PMA_PORT' => '3306',
                'MYSQL_ROOT_PASSWORD' => 'rootpassword'
            ],
            'depends_on' => ['db']
        ],
        'db' => [
            'name' => 'MySQL Database',
            'description' => 'MySQL 8.0 database server',
            'default' => true,
            'image' => 'mysql:8.0',
            'ports' => ['3306:3306'],
            'volumes' => ['mysql-data:/var/lib/mysql'],
            'environment' => [
                'MYSQL_ROOT_PASSWORD' => 'rootpassword',
                'MYSQL_ALLOW_EMPTY_PASSWORD' => 'no'
            ]
        ]
    ];
    
    public function __construct(
        string $basePath, 
        bool $nonInteractive = false, 
        string $webserverType = 'openswoole', 
        int $webserverPort = 9501
    ) {
        $this->basePath = $basePath;
        $this->nonInteractive = $nonInteractive;
        $this->webserverType = $webserverType;
        $this->webserverPort = $webserverPort;
    }
    
    /**
     * Required by Command abstract class
     */
    public function execute(): bool
    {
        $this->error("DockerComposeInit should not be executed directly. Use offerDockerServices() method instead.");
        return false;
    }
    
    /**
     * Offer Docker services installation
     */
    public function offerDockerServices(): void
    {
        if ($this->nonInteractive) {
            $this->info("Skipped Docker services installation (non-interactive mode)");
            return;
        }
        
        $this->displayDockerServicesPrompt();
        $this->getUserServiceSelection();
        $this->askForResourceLimits();
        $this->createDockerComposeFile();
    }
    
    /**
     * Display Docker services installation prompt
     */
    private function displayDockerServicesPrompt(): void
    {
        $boxShow = new \Gemvc\CLI\Commands\CliBoxShow();
        
        $lines = [
            "Would you like to set up Docker services for development?",
            "This will create a docker-compose.yml with optional services:",
            "",
            "\033[1;94mAvailable Services:\033[0m"
        ];
        
        foreach (self::AVAILABLE_SERVICES as $key => $service) {
            // @phpstan-ignore-next-line
            $default = $service['default'] ? ' (default)' : '';
            $lines[] = "  \033[1;36m{$service['name']}\033[0m - {$service['description']}{$default}";
        }
        
        $lines[] = "";
        $lines[] = "\033[1;94mThis will create:\033[0m";
        $lines[] = "  • \033[1;36mdocker-compose.yml\033[0m - Docker services configuration";
        $lines[] = "  • \033[1;36mDockerfile\033[0m - OpenSwoole container configuration";
        $lines[] = "  • \033[1;36mDev environment\033[0m - Ready to use with docker compose up";
        
        $boxShow->displayBox("Docker Services Setup", $lines);
    }
    
    /**
     * Get user service selection
     */
    private function getUserServiceSelection(): void
    {
        // Automatically proceed with Docker services setup
        // User will be asked to select specific services (Redis, phpMyAdmin, MySQL)
        $this->selectServices();
        $this->askForDevelopmentMode();
    }
    
    /**
     * Let user select which services to include
     */
    private function selectServices(): void
    {
        $this->info("Select services to include (press Enter for defaults):");
        
        foreach (self::AVAILABLE_SERVICES as $key => $service) {
            // @phpstan-ignore-next-line
            $default = $service['default'] ? ' [Y/n]' : ' [y/N]';
            echo "  \033[1;36m{$service['name']}\033[0m - {$service['description']}{$default}: ";
            
            $handle = fopen("php://stdin", "r");
            if ($handle === false) {
                continue;
            }
            $choice = fgets($handle);
            fclose($handle);
            $choice = $choice !== false ? trim($choice) : '';
            
            // @phpstan-ignore-next-line
            $include = $service['default'] ? 
                (empty($choice) || strtolower($choice) === 'y') :
                (strtolower($choice) === 'y');
                
            if ($include) {
                $this->selectedServices[] = $key;
            }
        }
        
        if (empty($this->selectedServices)) {
            $this->info("No services selected. Docker services setup skipped.");
            return;
        }
        
        $this->info("Selected services: " . implode(', ', array_map(function($key) {
            return self::AVAILABLE_SERVICES[$key]['name'];
        }, $this->selectedServices)));
    }
    
    /**
     * Ask user for development mode preference
     */
    private function askForDevelopmentMode(): void
    {
        if ($this->nonInteractive) {
            $this->info("Using development mode (non-interactive mode)");
            return;
        }
        
        echo "\n\033[1;36mMySQL Configuration Mode:\033[0m\n";
        echo "  \033[1;32m[1] Development Mode\033[0m - Clean logs, optimized for development\n";
        echo "  \033[1;33m[2] Production Mode\033[0m - Verbose logs, full security warnings\n";
        echo "\nEnter choice (1-2) [1]: ";
        
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            $this->info("Using development mode (stdin error)");
            return;
        }
        $choice = fgets($handle);
        fclose($handle);
        $choice = $choice !== false ? trim($choice) : '';
        
        if ($choice === '2') {
            $this->developmentMode = false;
            $this->info("Selected: Production Mode (verbose logs)");
        } else {
            $this->developmentMode = true;
            $this->info("Selected: Development Mode (clean logs)");
        }
    }
    
    /**
     * Ask user for resource limits (CPU and RAM)
     */
    private function askForResourceLimits(): void
    {
        if ($this->nonInteractive) {
            $this->info("Using default resource limits: {$this->cpuLimit} CPUs, {$this->memoryLimit} RAM (non-interactive mode)");
            return;
        }
        
        echo "\n\033[1;36mResource Limits Configuration:\033[0m\n";
        echo "Configure CPU and RAM limits for the application service\n";
        echo "\n\033[1;94mHow many CPUs do you want to dedicate for this service?\033[0m\n";
        echo "Enter number of CPUs [{$this->cpuLimit}]: ";
        
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            $this->info("Using default CPU limit: {$this->cpuLimit} (stdin error)");
        } else {
            $cpuInput = fgets($handle);
            fclose($handle);
            $cpuInput = $cpuInput !== false ? trim($cpuInput) : '';
            
            if (!empty($cpuInput) && is_numeric($cpuInput)) {
                $cpuValue = (int)$cpuInput;
                if ($cpuValue > 0) {
                    $this->cpuLimit = $cpuValue;
                    $this->info("Selected: {$this->cpuLimit} CPUs");
                } else {
                    $this->warning("Invalid CPU value, using default: {$this->cpuLimit}");
                }
            } else {
                $this->info("Using default CPU limit: {$this->cpuLimit}");
            }
        }
        
        echo "\n\033[1;94mHow much RAM do you want to allocate for this service?\033[0m\n";
        echo "Enter RAM amount (e.g., 2g, 512m, 4g) [{$this->memoryLimit}]: ";
        
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            $this->info("Using default RAM limit: {$this->memoryLimit} (stdin error)");
        } else {
            $ramInput = fgets($handle);
            fclose($handle);
            $ramInput = $ramInput !== false ? trim($ramInput) : '';
            
            if (!empty($ramInput) && preg_match('/^\d+[kmgKMG]?$/', $ramInput)) {
                $this->memoryLimit = strtolower($ramInput);
                // Ensure it ends with a unit
                if (preg_match('/^\d+$/', $this->memoryLimit)) {
                    $this->memoryLimit .= 'g';
                }
                $this->info("Selected: {$this->memoryLimit} RAM");
            } else {
                $this->info("Using default RAM limit: {$this->memoryLimit}");
            }
        }
    }
    
    /**
     * Create docker-compose.yml file
     */
    private function createDockerComposeFile(): void
    {
        if (empty($this->selectedServices)) {
            return;
        }
        
        $composeContent = $this->generateDockerComposeContent();
        $composePath = $this->basePath . '/docker-compose.yml';
        
        if (file_put_contents($composePath, $composeContent)) {
            $this->info("Created docker-compose.yml with selected services");
            $this->cleanupDockerVolumes();
            $this->displayDockerInstructions();
        } else {
            $this->warning("Failed to create docker-compose.yml file");
        }
    }
    
    /**
     * Clean up Docker volumes and containers
     */
    private function cleanupDockerVolumes(): void
    {
        if (!$this->nonInteractive) {
            echo "\n\033[1;36mClean up existing Docker containers and volumes? (y/N):\033[0m ";
            $handle = fopen("php://stdin", "r");
            if ($handle === false) {
                return;
            }
            $choice = fgets($handle);
            fclose($handle);
            $choice = $choice !== false ? trim($choice) : '';
            
            if (strtolower($choice) !== 'y') {
                return;
            }
        }
        
        $this->info("Cleaning up Docker resources...");
        
        $this->runDockerCommand(['compose', 'down']);
        
        // Clean up volumes for selected services
        if (in_array('db', $this->selectedServices)) {
            $this->runDockerCommand(['volume', 'rm', '-f', basename($this->basePath) . '_mysql-data']);
        }
        
        if (in_array('redis', $this->selectedServices)) {
            $this->runDockerCommand(['volume', 'rm', '-f', basename($this->basePath) . '_redis-data']);
        }
        
        $this->info("Docker cleanup completed");
    }
    
    /**
     * Run Docker command
     */
    /**
     * @param array<string> $args
     */
    private function runDockerCommand(array $args): bool
    {
        $command = 'docker ' . implode(' ', array_map('escapeshellarg', $args));
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->warning("Failed to run: {$command}");
            foreach ($output as $line) {
                $this->write("  {$line}\n", 'red');
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate Docker Compose content
     */
    private function generateDockerComposeContent(): string
    {
        $content = "# ============================================================================\n";
        $content .= "# GEMVC Docker Compose Configuration - DEVELOPMENT ENVIRONMENT\n";
        $content .= "# ============================================================================\n";
        $content .= "#\n";
        $content .= "# ⚠️  WARNING: This configuration is optimized for DEVELOPMENT ONLY!\n";
        $content .= "#\n";
        $content .= "# For PRODUCTION deployments:\n";
        $content .= "#   1. Change MySQL innodb-flush-log-at-trx-commit from 2 to 1 (CRITICAL)\n";
        $content .= "#   2. Enable binary logging (remove skip-log-bin, add --log-bin=mysql-bin)\n";
        $content .= "#   3. Enable SSL/TLS with proper certificates\n";
        $content .= "#   4. Use secrets management for passwords (not plain text)\n";
        $content .= "#   5. Configure automated backups and monitoring\n";
        $content .= "#   6. Review and adjust resource limits for production load\n";
        $content .= "#\n";
        $content .= "# See: MYSQL_PRODUCTION_GUIDE.md for complete production configuration\n";
        $content .= "#\n";
        $content .= "# ============================================================================\n\n";
        $content .= "services:\n";
        
        // Add webserver service
        $content .= $this->generateWebserverService();
        
        // Add selected services
        foreach ($this->selectedServices as $serviceKey) {
            $content .= $this->generateServiceContent($serviceKey);
        }
        
        // Add volumes
        $content .= $this->generateVolumesContent();
        
        // Add networks
        $content .= $this->generateNetworksContent();
        
        return $content;
    }
    
    /**
     * Generate webserver service configuration (OpenSwoole, Apache, or Nginx)
     */
    private function generateWebserverService(): string
    {
        $serviceName = in_array($this->webserverType, ['apache', 'nginx']) ? 'web' : 'openswoole';
        $port = $this->webserverPort;
        $dependsOn = [];
        if (in_array('db', $this->selectedServices)) {
            $dependsOn[] = 'db';
        }
        if (in_array('redis', $this->selectedServices)) {
            $dependsOn[] = 'redis';
        }
        
        $dependsOnStr = empty($dependsOn) ? '' : "\n    depends_on:\n" . 
            implode("\n", array_map(function($dep) {
                return "      - {$dep}";
            }, $dependsOn));
        
        $environment = [];
        if (in_array('redis', $this->selectedServices)) {
            $environment = array_merge($environment, [
                'REDIS_HOST' => '"redis"',
                'REDIS_PORT' => '"6379"',
                'REDIS_PASSWORD' => '"rootpassword"',
                'REDIS_DATABASE' => '"0"',
                'REDIS_PREFIX' => '"gemvc:"',
                'REDIS_PERSISTENT' => '"true"',
                'REDIS_TIMEOUT' => '"0.0"',
                'REDIS_READ_TIMEOUT' => '"0.0"'
            ]);
        }
        
        $envStr = empty($environment) ? '' : "\n    environment:\n" . 
            implode("\n", array_map(function($key, $value) {
                return "      {$key}: {$value}";
            }, array_keys($environment), $environment));
        
        // Port mapping based on webserver type
        $portMapping = $this->webserverType === 'openswoole' ? "{$port}:{$port}" : "{$port}:80";
        
        // Resource limits (using same format as MySQL service for consistency)
        $resourcesStr = "\n    cpus: {$this->cpuLimit}\n" .
            "    mem_limit: {$this->memoryLimit}\n" .
            "    mem_reservation: {$this->memoryLimit}";
        
        return <<<EOT
  {$serviceName}:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "{$portMapping}"
    volumes:
      - ./:/var/www/html:delegated
    restart: unless-stopped{$resourcesStr}
    networks:
      - backend-network{$dependsOnStr}{$envStr}

EOT;
    }
    
    /**
     * Get MySQL command based on development mode
     */
    /**
     * @return array<string>
     */
    private function getMySQLCommand(): array
    {
        $baseCommand = [
            '--character-set-server=utf8mb4',
            '--collation-server=utf8mb4_unicode_ci',
            '--authentication-policy=caching_sha2_password',
            '--host-cache-size=128',
            '--pid-file=/var/lib/mysql/mysql.pid',
            '--disable-ssl'
        ];
        
        if ($this->developmentMode) {
            // Development mode: Clean logs, optimized for development
            $baseCommand = array_merge($baseCommand, [
                // Logging
                '--log-error-verbosity=1',
                '--skip-log-bin',
                '--skip-name-resolve',
                '--skip-symbolic-links',
                // InnoDB optimization
                '--innodb-flush-log-at-trx-commit=2',
                '--innodb-buffer-pool-size=1G',
                '--innodb-log-file-size=128M',
                '--innodb-flush-method=O_DIRECT',
                '--innodb-file-per-table=1',
                // Connection optimization
                '--max-connections=200',
                '--thread-cache-size=50',
                '--table-open-cache=2000',
                '--table-definition-cache=1400',
                // Buffer optimization
                '--net-buffer-length=16384',
                '--max-allowed-packet=64M',
                '--bulk-insert-buffer-size=16M',
                '--read-buffer-size=512K',
                '--read-rnd-buffer-size=1M',
                '--sort-buffer-size=1M',
                '--join-buffer-size=1M',
                '--tmp-table-size=64M',
                '--max-heap-table-size=64M'
            ]);
        }
        // Production mode: Use base command only (verbose logs)
        
        return $baseCommand;
    }
    
    /**
     * Generate service content based on service key
     */
    private function generateServiceContent(string $serviceKey): string
    {
        $service = self::AVAILABLE_SERVICES[$serviceKey];
        $content = "\n  {$serviceKey}:\n";
        $content .= "    image: {$service['image']}\n";
        
        // Handle MySQL command dynamically
        if ($serviceKey === 'db') {
            // Add resource limits for MySQL
            $content .= "    mem_limit: 2g\n";
            $content .= "    mem_reservation: 1g\n";
            $content .= "    cpus: 2\n";
            
            $command = $this->getMySQLCommand();
            $content .= "    command:\n";
            foreach ($command as $cmd) {
                $content .= "      - {$cmd}\n";
            }
            // Continue with other MySQL service properties
        }
        
        // Handle Redis command
        if ($serviceKey === 'redis') {
            $content .= "    command: redis-server --requirepass rootpassword\n";
        }
        
        // @phpstan-ignore-next-line
        if (isset($service['ports'])) {
            $content .= "    ports:\n";
            foreach ($service['ports'] as $port) {
                $content .= "      - \"{$port}\"\n";
            }
        }
        
        if (isset($service['volumes'])) {
            $content .= "    volumes:\n";
            foreach ($service['volumes'] as $volume) {
                $content .= "      - {$volume}\n";
            }
        }
        
        // @phpstan-ignore-next-line
        if (isset($service['environment']) && !empty($service['environment'])) {
            $content .= "    environment:\n";
            foreach ($service['environment'] as $key => $value) {
                $content .= "      {$key}: \"{$value}\"\n";
            }
        }
        
        // @phpstan-ignore-next-line
        if (isset($service['command'])) {
            $content .= "    command:\n";
            foreach ($service['command'] as $cmd) {
                $content .= "      - {$cmd}\n";
            }
        }
        
        if (isset($service['depends_on'])) {
            $content .= "    depends_on:\n";
            foreach ($service['depends_on'] as $dep) {
                $content .= "      - {$dep}\n";
            }
        }
        
        $content .= "    networks:\n";
        $content .= "      - backend-network\n";
        
        return $content;
    }
    
    /**
     * Generate volumes section
     */
    private function generateVolumesContent(): string
    {
        $volumes = [];
        
        if (in_array('db', $this->selectedServices)) {
            $volumes[] = 'mysql-data';
        }
        if (in_array('redis', $this->selectedServices)) {
            $volumes[] = 'redis-data';
        }
        
        if (empty($volumes)) {
            return "";
        }
        
        $content = "\nvolumes:\n";
        foreach ($volumes as $volume) {
            $content .= "  {$volume}:\n";
            $content .= "    driver: local\n";
        }
        
        return $content;
    }
    
    /**
     * Generate networks section
     */
    private function generateNetworksContent(): string
    {
        return <<<EOT

networks:
  backend-network:
    driver: bridge
EOT;
    }
    
    /**
     * Display Docker usage instructions
     */
    private function displayDockerInstructions(): void
    {
        $boxShow = new \Gemvc\CLI\Commands\CliBoxShow();
        
        $modeText = $this->developmentMode ? 
            "\033[1;32mDevelopment Mode\033[0m (clean logs)" : 
            "\033[1;33mProduction Mode\033[0m (verbose logs)";
            
        $lines = [
            "\033[1;92m✓ Docker Services Ready!\033[0m",
            "",
            "\033[1;94mMySQL Configuration:\033[0m {$modeText}",
            "",
            "\033[1;94mTo start your development environment:\033[0m",
            " \033[1;36m$ \033[1;95mdocker compose up -d\033[0m",
            "",
            "\033[1;94mTo stop the services:\033[0m",
            " \033[1;36m$ \033[1;95mdocker compose down\033[0m",
            "",
            "\033[1;94mTo view logs:\033[0m",
            " \033[1;36m$ \033[1;95mdocker compose logs -f\033[0m",
            "",
            "\033[1;94mService URLs:\033[0m",
            " • \033[1;36m" . ucfirst($this->webserverType) . "\033[0m: http://localhost:{$this->webserverPort}"
        ];
        
        if (in_array('phpmyadmin', $this->selectedServices)) {
            $lines[] = " • \033[1;36mphpMyAdmin\033[0m: http://localhost:8080";
        }
        if (in_array('db', $this->selectedServices)) {
            $lines[] = " • \033[1;36mMySQL\033[0m: localhost:3306 (root/rootpassword)";
        }
        if (in_array('redis', $this->selectedServices)) {
            $lines[] = " • \033[1;36mRedis\033[0m: localhost:6379";
        }
        
        $boxShow->displayBox("Docker Services", $lines);
    }
    
    /**
     * Get selected services
     */
    /**
     * @return array<string>
     */
    public function getSelectedServices(): array
    {
        return $this->selectedServices;
    }
    
    /**
     * Set selected services (for non-interactive mode)
     */
    /**
     * @param array<string> $services
     */
    public function setSelectedServices(array $services): void
    {
        $this->selectedServices = array_intersect($services, array_keys(self::AVAILABLE_SERVICES));
    }
}

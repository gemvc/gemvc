<?php

namespace Gemvc\CLI;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\CliBoxShow;

/**
 * Docker Container Builder
 * 
 * Handles automatic Docker container building with pre-flight checks:
 * - Docker Desktop status
 * - Port availability
 * - Existing container conflicts
 * - Automatic port suggestions
 * 
 * Works for all webservers (OpenSwoole, Apache, Nginx)
 */
class DockerContainerBuilder extends Command
{
    private string $basePath;
    private bool $nonInteractive;
    private int $appPort;
    private string $webserverType;
    private ?string $customProjectName = null;
    private ?string $customImageName = null;
    private bool $forceNameSelection = false;
    
    // Default ports that need to be checked
    private const DEFAULT_PORTS = [
        'mysql' => 3306,
        'web' => 80,
        'phpmyadmin' => 8080
    ];
    
    public function __construct(string $basePath, bool $nonInteractive = false, int $appPort = 9501, string $webserverType = 'openswoole')
    {
        $this->basePath = $basePath;
        $this->nonInteractive = $nonInteractive;
        $this->appPort = $appPort;
        $this->webserverType = strtolower($webserverType);
    }
    
    /**
     * Required by Command abstract class
     */
    public function execute(): bool
    {
        $this->error("DockerContainerBuilder should not be executed directly. Use offerContainerBuild() method instead.");
        return false;
    }
    
    /**
     * Offer to build Docker containers with all checks
     * 
     * @return void
     */
    public function offerContainerBuild(): void
    {
        // Check if docker-compose.yml exists
        $dockerComposeFile = $this->basePath . '/docker-compose.yml';
        if (!file_exists($dockerComposeFile)) {
            $this->info("docker-compose.yml not found. Skipping container build.");
            return;
        }
        
        if ($this->nonInteractive) {
            $this->info("Skipped Docker container build (non-interactive mode)");
            return;
        }
        
        $this->displayBuildPrompt();
        
        // Get user confirmation
        echo "\n\033[1;36mBuild Docker containers now? (y/N):\033[0m ";
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            $this->info("Docker container build skipped (stdin error)");
            return;
        }
        $choice = fgets($handle);
        fclose($handle);
        $choice = $choice !== false ? trim($choice) : '';
        
        if (strtolower($choice) !== 'y') {
            $this->info("Docker container build skipped");
            return;
        }
        
        // Run all checks and build
        $this->runPreFlightChecks();
        $this->buildContainers();
    }
    
    /**
     * Display build prompt with information
     * 
     * @return void
     */
    private function displayBuildPrompt(): void
    {
        $boxShow = new CliBoxShow();
        
        // Build port list for display
        $portList = [3306, 8080, $this->appPort];
        if ($this->webserverType === 'apache' || $this->webserverType === 'nginx') {
            $portList[] = 80;
        }
        $portListStr = implode(', ', $portList);
        
        $lines = [
            "Would you like to build and start your Docker containers now?",
            "",
            "\033[1;94mThis will:\033[0m",
            "  • Check Docker Desktop status",
            "  • Verify port availability ({$portListStr})",
            "  • Check for existing containers",
            "  • Build and start containers with \033[1;36mdocker compose up -d --build\033[0m",
            "",
            "\033[1;33mNote:\033[0m If ports are in use, we'll suggest alternatives."
        ];
        
        $boxShow->displayBox("Docker Container Build", $lines);
    }
    
    /**
     * Run all pre-flight checks before building
     * 
     * @return void
     * @throws \RuntimeException If critical checks fail
     */
    private function runPreFlightChecks(): void
    {
        $this->info("Running pre-flight checks...");
        
        // Check Docker Desktop
        if (!$this->checkDockerDesktop()) {
            throw new \RuntimeException("Docker Desktop is not running. Please start Docker Desktop and try again.");
        }
        
        // Step 1: Check ports first
        $portsToCheck = [
            self::DEFAULT_PORTS['mysql'],      // 3306 - always check
            self::DEFAULT_PORTS['phpmyadmin']   // 8080 - always check
        ];
        
        // For Apache and Nginx, port 80 is the app port, so we check it as app port
        // For OpenSwoole, app port is 9501 (different from port 80)
        // Always check application port (will be 80 for Apache/Nginx, 9501 for OpenSwoole)
        $portsToCheck[] = $this->appPort;
        
        $blockedPorts = $this->checkPorts($portsToCheck);
        
        // Step 2: Separate Docker container conflicts from system process conflicts
        if (!empty($blockedPorts)) {
            $containerBlockedPorts = [];
            $systemBlockedPorts = [];
            
            foreach ($blockedPorts as $portInfo) {
                if ($portInfo['container'] !== null) {
                    // Port blocked by Docker container
                    $containerBlockedPorts[] = $portInfo;
                } else {
                    // Port blocked by system process
                    $systemBlockedPorts[] = $portInfo;
                }
            }
            
            // Step 2a: If ports are blocked by Docker containers, handle container/name conflicts first
            if (!empty($containerBlockedPorts)) {
                $this->handleContainerConflictsFirst($containerBlockedPorts);
                
                // Step 2b: Check existing containers (if not already handled)
                $this->checkExistingContainers();
                
                // Step 3: Re-check ports after handling containers/names
                $portsToCheck = [
                    self::DEFAULT_PORTS['mysql'],
                    self::DEFAULT_PORTS['phpmyadmin']
                ];
                
                // Add port 80 only for Apache/Nginx
                if (in_array($this->webserverType, ['apache', 'nginx'])) {
                    if (!in_array(80, $portsToCheck)) {
                        $portsToCheck[] = 80;
                    }
                }
                
                // Add appPort if it's not already in the list
                if (!in_array($this->appPort, $portsToCheck)) {
                    $portsToCheck[] = $this->appPort;
                }
                
                $blockedPorts = $this->checkPorts(array_unique($portsToCheck));
                
                // Step 4: If ports are still blocked after container handling, suggest new ports
                if (!empty($blockedPorts)) {
                    $this->suggestNewPorts($blockedPorts);
                }
            } else {
                // Only system process conflicts - skip container conflict handling, go straight to port suggestions
                // Step 2b: Check existing containers (for name conflicts, not port conflicts)
                $this->checkExistingContainers();
                
                // Step 3: Re-check ports (they might be available now, or still blocked)
                $portsToCheck = [
                    self::DEFAULT_PORTS['mysql'],
                    self::DEFAULT_PORTS['phpmyadmin']
                ];
                
                // Add port 80 only for Apache/Nginx
                if (in_array($this->webserverType, ['apache', 'nginx'])) {
                    if (!in_array(80, $portsToCheck)) {
                        $portsToCheck[] = 80;
                    }
                }
                
                // Add appPort if it's not already in the list
                if (!in_array($this->appPort, $portsToCheck)) {
                    $portsToCheck[] = $this->appPort;
                }
                
                $blockedPorts = $this->checkPorts(array_unique($portsToCheck));
                
                // Step 4: If ports are still blocked by system processes, suggest new ports
                if (!empty($blockedPorts)) {
                    $this->suggestNewPorts($blockedPorts);
                }
            }
        } else {
            // No port conflicts, just check existing containers
            $this->checkExistingContainers();
        }
        
        $this->info("✓ All pre-flight checks passed");
    }
    
    /**
     * Check if Docker Desktop is running
     * 
     * @return bool
     */
    private function checkDockerDesktop(): bool
    {
        $this->info("Checking Docker Desktop status...");
        
        // Try to run docker command
        $output = [];
        $returnCode = 0;
        exec('docker info 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->info("✓ Docker Desktop is running");
            return true;
        }
        
        $this->warning("✗ Docker Desktop is not running or not accessible");
        $this->info("Please start Docker Desktop and ensure it's fully loaded.");
        return false;
    }
    
    /**
     * Check if ports are available
     * 
     * @param array<int> $ports
     * @return array<array{port: int, container: string|null, suggested: int}> List of blocked ports with container info
     */
    private function checkPorts(array $ports): array
    {
        $this->info("Checking port availability...");
        $blockedPorts = [];
        
        foreach ($ports as $port) {
            $portInfo = $this->checkPortWithContainer($port);
            if ($portInfo !== null) {
                $blockedPorts[] = $portInfo;
                $containerInfo = $portInfo['container'] ? " (used by: {$portInfo['container']})" : "";
                $this->warning("Port {$port} is already in use{$containerInfo}");
            } else {
                $this->info("✓ Port {$port} is available");
            }
        }
        
        return $blockedPorts;
    }
    
    /**
     * Check port and return container using it
     * 
     * @param int $port
     * @return array{port: int, container: string|null, suggested: int}|null
     */
    private function checkPortWithContainer(int $port): ?array
    {
        // First check Docker containers
        $output = [];
        $returnCode = 0;
        exec("docker ps --format '{{.Names}}|{{.Ports}}' 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            foreach ($output as $line) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $containerName = trim($parts[0]);
                    $portsInfo = $parts[1];
                    
                    // Check if port appears in container ports
                    // Format: "0.0.0.0:3306->3306/tcp" or "3306/tcp"
                    if (preg_match("/:{$port}->|:{$port}\/|{$port}\/tcp/", $portsInfo)) {
                        $suggested = $this->suggestAlternativePort($port);
                        return [
                            'port' => $port,
                            'container' => $containerName,
                            'suggested' => $suggested
                        ];
                    }
                }
            }
        }
        
        // Also check system-level port binding
        $systemPortInUse = false;
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            $output = [];
            exec("netstat -ano | findstr :{$port}", $output, $returnCode);
            $systemPortInUse = !empty($output);
        } else {
            // Linux/Mac
            $output = [];
            exec("lsof -i :{$port} 2>&1", $output, $returnCode);
            $systemPortInUse = !empty($output);
        }
        
        if ($systemPortInUse) {
            $suggested = $this->suggestAlternativePort($port);
            return [
                'port' => $port,
                'container' => null, // System process, not Docker container
                'suggested' => $suggested
            ];
        }
        
        return null;
    }
    
    
    /**
     * Handle container conflicts first (before port suggestions)
     * This is step 1: stop containers or choose different name
     * 
     * @param array<array{port: int, container: string|null, suggested: int}> $blockedPorts
     * @return void
     */
    private function handleContainerConflictsFirst(array $blockedPorts): void
    {
        $boxShow = new CliBoxShow();
        
        $lines = [
            "\033[1;33mPort Conflicts Detected:\033[0m",
            ""
        ];
        
        $containersToStop = [];
        
        foreach ($blockedPorts as $portInfo) {
            $port = $portInfo['port'];
            $container = $portInfo['container'];
            $suggested = $portInfo['suggested'];
            $portName = $this->getPortName($port);
            
            if ($container !== null) {
                $lines[] = "  • Port \033[1;36m{$port}\033[0m ({$portName}) is used by: \033[1;31m{$container}\033[0m";
                $containersToStop[] = $container;
            } else {
                $lines[] = "  • Port \033[1;36m{$port}\033[0m ({$portName}) is in use by system process";
            }
            $lines[] = "    Suggested alternative port: \033[1;32m{$suggested}\033[0m";
            $lines[] = "";
        }
        
        $lines[] = "\033[1;33mStep 1: Handle Container Conflicts\033[0m";
        $lines[] = "";
        $lines[] = "First, let's resolve container conflicts. Choose an option:";
        $lines[] = "";
        
        // Option 1: Use different project/container name (swapped to be option 1)
        $defaultProjectName = strtolower(str_replace([' ', '_'], ['', '-'], basename($this->basePath)));
        $lines[] = "\033[1;94mOption 1: Choose Different Name and Port\033[0m";
        $lines[] = "  This will create containers with a different name prefix";
        $lines[] = "  Example: Instead of '{$defaultProjectName}-*', use 'myproject-*'";
        $lines[] = "";
        
        // Option 2: Stop conflicting containers (swapped to be option 2)
        if (!empty($containersToStop)) {
            $uniqueContainers = array_unique($containersToStop);
            $lines[] = "\033[1;94mOption 2: Stop running container\033[0m";
            $lines[] = "  This will stop the following containers:";
            foreach ($uniqueContainers as $container) {
                $lines[] = "    • \033[1;36m{$container}\033[0m";
            }
            $lines[] = "";
        }
        
        $lines[] = "\033[1;90mNote:\033[0m After resolving container conflicts, we'll check ports again";
        $lines[] = "      and suggest new ports if they're still in use.";
        
        $boxShow->displayWarningBox("Port Conflicts - Step 1", $lines);
        
        echo "\n\033[1;36mChoose option (1=Different Name and Port, 2=Stop container) [1]:\033[0m ";
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            throw new \RuntimeException("Failed to read user input");
        }
        $choice = fgets($handle);
        fclose($handle);
        $choice = $choice !== false ? trim($choice) : '';
        
        // Default to option 1 if empty
        if ($choice === '') {
            $choice = '1';
        }
        
        if (strtolower($choice) === '1') {
            // Use different project name - force name selection
            $this->forceNameSelection = true;
            $this->info("Proceeding to container name selection...");
        } elseif (strtolower($choice) === '2' && !empty($containersToStop)) {
            // Stop containers
            $uniqueContainers = array_unique($containersToStop);
            $this->stopContainers($uniqueContainers);
        } else {
            // Invalid choice or option 2 selected but no containers to stop - default to option 1
            $this->forceNameSelection = true;
            $this->info("Proceeding to container name selection...");
        }
    }
    
    /**
     * Suggest new ports after container conflicts are resolved
     * This is step 2: if ports are still blocked, suggest alternative ports
     * 
     * @param array<array{port: int, container: string|null, suggested: int}> $blockedPorts
     * @return void
     */
    private function suggestNewPorts(array $blockedPorts): void
    {
        $boxShow = new CliBoxShow();
        
        $lines = [
            "\033[1;33mPort Conflicts Still Detected:\033[0m",
            ""
        ];
        
        // Build port mapping summary
        $portMappings = [];
        foreach ($blockedPorts as $portInfo) {
            $port = $portInfo['port'];
            $suggested = $portInfo['suggested'];
            $portName = $this->getPortName($port);
            $portMappings[] = [
                'service' => $portName,
                'old' => $port,
                'new' => $suggested
            ];
        }
        
        foreach ($blockedPorts as $portInfo) {
            $port = $portInfo['port'];
            $container = $portInfo['container'];
            $suggested = $portInfo['suggested'];
            $portName = $this->getPortName($port);
            
            if ($container !== null) {
                $lines[] = "  • Port \033[1;36m{$port}\033[0m ({$portName}) is used by: \033[1;31m{$container}\033[0m";
            } else {
                $lines[] = "  • Port \033[1;36m{$port}\033[0m ({$portName}) is in use by system process";
            }
            $lines[] = "    Suggested alternative port: \033[1;32m{$suggested}\033[0m";
            $lines[] = "";
        }
        
        $lines[] = "\033[1;33mStep 2: Use Different Ports\033[0m";
        $lines[] = "";
        $lines[] = "Ports are still in use. Choose an option:";
        $lines[] = "";
        $lines[] = "\033[1;94mOption 1: Accept suggested alternative ports\033[0m";
        $lines[] = "  I will automatically update docker-compose.yml with these ports:";
        $lines[] = "";
        foreach ($portMappings as $mapping) {
            $lines[] = "    • \033[1;36m{$mapping['service']}\033[0m: Port \033[1;32m{$mapping['new']}\033[0m (was {$mapping['old']})";
        }
        $lines[] = "";
        $lines[] = "\033[1;94mOption 2: Continue anyway\033[0m";
        $lines[] = "  Build may fail if ports are still in use";
        
        $boxShow->displayWarningBox("Port Conflicts - Step 2", $lines);
        
        echo "\n\033[1;36mChoose option (1=Accept suggested ports, 2=Continue anyway, N=Cancel):\033[0m ";
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            throw new \RuntimeException("Failed to read user input");
        }
        $choice = fgets($handle);
        fclose($handle);
        $choice = $choice !== false ? trim($choice) : '';
        
        if (strtolower($choice) === '1') {
            // Automatically update docker-compose.yml with suggested ports
            $this->updateDockerComposePorts($portMappings);
            $this->info("✓ Updated docker-compose.yml with alternative ports");
        } elseif (strtolower($choice) === '2') {
            // Continue anyway
            $this->warning("Continuing with port conflicts. Build may fail.");
        } else {
            throw new \RuntimeException("Container build cancelled. Please resolve port conflicts and try again.");
        }
    }
    
    /**
     * Update docker-compose.yml with alternative ports
     * 
     * @param array<array{service: string, old: int, new: int}> $portMappings
     * @return void
     */
    private function updateDockerComposePorts(array $portMappings): void
    {
        $dockerComposeFile = $this->basePath . '/docker-compose.yml';
        $content = file_get_contents($dockerComposeFile);
        
        if ($content === false) {
            throw new \RuntimeException("Could not read docker-compose.yml to update ports");
        }
        
        // Create a mapping of old port -> new port for easy lookup
        $portMap = [];
        foreach ($portMappings as $mapping) {
            $portMap[$mapping['old']] = $mapping['new'];
        }
        
        // Update port mappings in docker-compose.yml
        // Pattern: "3306:3306" or "9501:9501" or "8080:80" etc.
        foreach ($portMap as $oldPort => $newPort) {
            // Match patterns like "3306:3306", "9501:9501", "8080:80", etc.
            // We need to replace the host port (first number) but keep the container port (second number)
            
            // For app port, the format depends on webserver type
            if ($oldPort === $this->appPort) {
                if ($this->webserverType === 'openswoole') {
                    // Format: "9501:9501" -> "9502:9501"
                    $pattern = '/("|\s*-\s*")(\d+):' . preg_quote($oldPort, '/') . '(")/';
                    $replacement = '${1}' . $newPort . ':' . $oldPort . '${3}';
                } else {
                    // Format: "80:80" -> "8080:80" (for Apache/Nginx)
                    $pattern = '/("|\s*-\s*")(\d+):80(")/';
                    $replacement = '${1}' . $newPort . ':80${3}';
                }
            } else {
                // For other services like MySQL (3306:3306) or phpMyAdmin (8080:80)
                // We need to find the service and update accordingly
                if ($oldPort === 3306) {
                    // MySQL: "3306:3306" -> "3307:3306"
                    $pattern = '/("|\s*-\s*")(\d+):3306(")/';
                    $replacement = '${1}' . $newPort . ':3306${3}';
                } elseif ($oldPort === 8080) {
                    // phpMyAdmin: "8080:80" -> "8081:80" (or whatever new port)
                    $pattern = '/("|\s*-\s*")(\d+):80(")/';
                    // But we need to be careful - this might match the app port too
                    // Let's be more specific - look for db service or phpmyadmin service
                    $pattern = '/("|\s*-\s*")(\d+):80(")/';
                    // Actually, we need to check which service uses this port
                    // For now, let's use a more general approach
                } else {
                    // Generic: replace first occurrence of oldPort in port mapping
                    $pattern = '/("|\s*-\s*")(\d+):' . preg_quote($oldPort, '/') . '(")/';
                    $replacement = '${1}' . $newPort . ':' . $oldPort . '${3}';
                }
            }
            
            // More robust approach: find all port mappings and update the ones that match
            // Pattern: "ports:" section with "- "HOST:CONTAINER""
            $pattern = '/(ports:\s*\n(?:\s+-\s*"[^"]+"\s*\n?)*)/';
            
            // Better: find each port line and update if it matches
            $content = preg_replace_callback(
                '/(\s+-\s*")(\d+):(\d+)(")/',
                function($matches) use ($oldPort, $newPort) {
                    $hostPort = (int)$matches[2];
                    $containerPort = (int)$matches[3];
                    
                    // If this port mapping uses the old port as host port, update it
                    if ($hostPort === $oldPort) {
                        return $matches[1] . $newPort . ':' . $containerPort . $matches[4];
                    }
                    return $matches[0];
                },
                $content
            );
        }
        
        file_put_contents($dockerComposeFile, $content);
        
        // Show what was changed
        foreach ($portMappings as $mapping) {
            $this->info("  • {$mapping['service']}: Changed port {$mapping['old']} → {$mapping['new']}");
        }
    }
    
    /**
     * Stop conflicting containers
     * 
     * @param array<string> $containerNames
     * @return void
     */
    private function stopContainers(array $containerNames): void
    {
        $this->info("Stopping conflicting containers...");
        
        foreach ($containerNames as $containerName) {
            $output = [];
            $returnCode = 0;
            exec("docker stop {$containerName} 2>&1", $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->info("✓ Stopped container: {$containerName}");
            } else {
                $this->warning("Could not stop container: {$containerName}");
                $this->warning(implode("\n", $output));
            }
        }
        
        $this->info("✓ Containers stopped");
    }
    
    /**
     * Suggest an alternative port
     * 
     * @param int $port
     * @return int
     */
    private function suggestAlternativePort(int $port): int
    {
        // Try next 10 ports
        for ($i = 1; $i <= 10; $i++) {
            $suggested = $port + $i;
            if ($this->checkPortWithContainer($suggested) === null) {
                return $suggested;
            }
        }
        
        // If no port found in range, return original + 100
        return $port + 100;
    }
    
    /**
     * Get friendly name for port
     * 
     * @param int $port
     * @return string
     */
    private function getPortName(int $port): string
    {
        $portNames = [
            3306 => 'MySQL',
            80 => 'Web Server',
            8080 => 'phpMyAdmin',
        ];
        
        // Application port name depends on webserver type
        if ($port === $this->appPort) {
            if ($this->webserverType === 'openswoole') {
                return 'OpenSwoole';
            } elseif ($this->webserverType === 'apache') {
                return 'Apache';
            } elseif ($this->webserverType === 'nginx') {
                return 'Nginx';
            }
            return 'Application Server';
        }
        
        return $portNames[$port] ?? 'Application';
    }
    
    /**
     * Check for existing containers and images with same names
     * 
     * @return void
     */
    private function checkExistingContainers(): void
    {
        $this->info("Checking for existing containers and images...");
        
        // Read docker-compose.yml to get service names
        $dockerComposeFile = $this->basePath . '/docker-compose.yml';
        $content = file_get_contents($dockerComposeFile);
        
        if ($content === false) {
            $this->warning("Could not read docker-compose.yml");
            return;
        }
        
        // Get project name from directory (default)
        $defaultProjectName = strtolower(str_replace([' ', '_'], ['', '-'], basename($this->basePath)));
        
        // Extract service names
        preg_match_all('/^\s*([a-zA-Z0-9_-]+):\s*$/m', $content, $matches);
        
        if (empty($matches[1])) {
            return;
        }
        
        $serviceNames = $matches[1];
        $existingContainers = [];
        $existingImages = [];
        
        // Check for running containers with project name pattern
        $output = [];
        $returnCode = 0;
        exec("docker ps --format '{{.Names}}' 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            foreach ($output as $containerName) {
                $containerName = trim($containerName);
                // Check if container name starts with project name (with or without -1 suffix)
                if (strpos($containerName, $defaultProjectName . '-') === 0) {
                    $existingContainers[] = $containerName;
                }
            }
        }
        
        // Also check stopped containers
        $output = [];
        exec("docker ps -a --format '{{.Names}}' 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            foreach ($output as $containerName) {
                $containerName = trim($containerName);
                // Check if container name starts with project name and not already in list
                if (strpos($containerName, $defaultProjectName . '-') === 0 && !in_array($containerName, $existingContainers, true)) {
                    $existingContainers[] = $containerName;
                }
            }
        }
        
        // Check for existing images (from build context)
        // Get the main app service name (usually openswoole, web, or nginx)
        $appServiceName = $this->getAppServiceName($content);
        if ($appServiceName) {
            // Check if service uses build (not just image)
            if (preg_match("/^\s*{$appServiceName}:\s*$/m", $content)) {
                // Check for images with project name pattern
                $imagePattern = $defaultProjectName;
                $output = [];
                exec("docker images --format '{{.Repository}}:{{.Tag}}' 2>&1", $output, $returnCode);
                
                if ($returnCode === 0) {
                    foreach ($output as $imageName) {
                        $imageName = trim($imageName);
                        // Check if image name contains project name
                        if (strpos($imageName, $defaultProjectName) !== false && $imageName !== '<none>:<none>') {
                            $existingImages[] = $imageName;
                        }
                    }
                }
            }
        }
        
        if (!empty($existingContainers) || !empty($existingImages) || $this->forceNameSelection) {
            $this->handleContainerAndImageConflicts($existingContainers, $existingImages, $defaultProjectName);
        } else {
            $this->info("✓ No conflicting containers or images found");
        }
    }
    
    /**
     * Get the main application service name from docker-compose.yml
     * 
     * @param string $content
     * @return string|null
     */
    private function getAppServiceName(string $content): ?string
    {
        // Common app service names
        $possibleNames = ['openswoole', 'web', 'nginx', 'apache', 'app'];
        
        foreach ($possibleNames as $name) {
            if (preg_match("/^\s*{$name}:\s*$/m", $content)) {
                return $name;
            }
        }
        
        return null;
    }
    
    /**
     * Handle existing containers and images - ask user for custom names
     * 
     * @param array<string> $containerNames
     * @param array<string> $imageNames
     * @param string $defaultProjectName
     * @return void
     */
    private function handleContainerAndImageConflicts(array $containerNames, array $imageNames, string $defaultProjectName): void
    {
        $boxShow = new CliBoxShow();
        
        if ($this->forceNameSelection && empty($containerNames) && empty($imageNames)) {
            // User chose option 2 but no conflicts found - just ask for custom name
            $lines = [
                "\033[1;33mCustom Container Name Selection\033[0m",
                "",
                "You chose to use a different container/project name.",
                "",
                "\033[1;33mCurrent Project Name:\033[0m {$defaultProjectName}",
                "",
                "\033[1;94mPlease provide a custom project name:\033[0m"
            ];
            $boxShow->displayBox("Container Name Selection", $lines);
        } else {
            // Normal conflict detection
            $lines = [
                "\033[1;33mConflicts Detected:\033[0m",
                ""
            ];
            
            if (!empty($containerNames)) {
                $lines[] = "\033[1;33mRunning Containers:\033[0m";
                foreach ($containerNames as $containerName) {
                    $lines[] = "  • \033[1;36m{$containerName}\033[0m";
                }
                $lines[] = "";
            }
            
            if (!empty($imageNames)) {
                $lines[] = "\033[1;33mExisting Images:\033[0m";
                foreach ($imageNames as $imageName) {
                    $lines[] = "  • \033[1;36m{$imageName}\033[0m";
                }
                $lines[] = "";
            }
            
            $lines[] = "\033[1;33mCurrent Project Name:\033[0m {$defaultProjectName}";
            $lines[] = "";
            $lines[] = "\033[1;94mPlease provide custom names to avoid conflicts:\033[0m";
            
            $boxShow->displayWarningBox("Container & Image Conflicts", $lines);
        }
        
        // Ask for custom project name
        echo "\n\033[1;36mEnter custom project name for containers [{$defaultProjectName}]:\033[0m ";
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            throw new \RuntimeException("Failed to read user input");
        }
        $projectNameInput = fgets($handle);
        fclose($handle);
        $projectNameInput = $projectNameInput !== false ? trim($projectNameInput) : '';
        
        if (!empty($projectNameInput)) {
            // Validate project name (Docker Compose requirements: lowercase, alphanumeric, hyphens, underscores)
            $projectNameInput = strtolower(preg_replace('/[^a-z0-9_-]/', '', $projectNameInput));
            if (!empty($projectNameInput)) {
                $this->customProjectName = $projectNameInput;
                $this->info("Using custom project name: {$this->customProjectName}");
            } else {
                $this->warning("Invalid project name, using default: {$defaultProjectName}");
                $this->customProjectName = $defaultProjectName;
            }
        } else {
            $this->customProjectName = $defaultProjectName;
        }
        
        // Ask for custom image name (optional, only if images exist)
        if (!empty($imageNames)) {
            echo "\033[1;36mEnter custom image name for application container [{$defaultProjectName}-app:latest]:\033[0m ";
            $handle = fopen("php://stdin", "r");
            if ($handle === false) {
                throw new \RuntimeException("Failed to read user input");
            }
            $imageNameInput = fgets($handle);
            fclose($handle);
            $imageNameInput = $imageNameInput !== false ? trim($imageNameInput) : '';
            
            if (!empty($imageNameInput)) {
                // Validate image name
                $imageNameInput = strtolower(preg_replace('/[^a-z0-9._-]/', '', $imageNameInput));
                if (!empty($imageNameInput)) {
                    // Ensure it has a tag
                    if (strpos($imageNameInput, ':') === false) {
                        $imageNameInput .= ':latest';
                    }
                    $this->customImageName = $imageNameInput;
                    $this->info("Using custom image name: {$this->customImageName}");
                } else {
                    $this->warning("Invalid image name, will use default");
                }
            }
        }
        
        // Update .env file with COMPOSE_PROJECT_NAME
        $this->updateEnvFile();
        
        // Update docker-compose.yml with custom image name if provided
        if ($this->customImageName !== null) {
            $this->updateDockerComposeImage();
        }
    }
    
    /**
     * Update .env file with COMPOSE_PROJECT_NAME
     * 
     * @return void
     */
    private function updateEnvFile(): void
    {
        $envFile = $this->basePath . '/.env';
        $composeProjectName = "COMPOSE_PROJECT_NAME={$this->customProjectName}";
        
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if ($content === false) {
                $content = '';
            }
            
            // Check if COMPOSE_PROJECT_NAME already exists
            if (preg_match('/^COMPOSE_PROJECT_NAME=.*$/m', $content)) {
                // Update existing
                $content = preg_replace('/^COMPOSE_PROJECT_NAME=.*$/m', $composeProjectName, $content);
            } else {
                // Add new
                $content .= "\n" . $composeProjectName . "\n";
            }
            
            file_put_contents($envFile, $content);
        } else {
            // Create new .env file
            file_put_contents($envFile, $composeProjectName . "\n");
        }
        
        $this->info("✓ Updated .env with COMPOSE_PROJECT_NAME={$this->customProjectName}");
    }
    
    /**
     * Update docker-compose.yml with custom image name
     * 
     * @return void
     */
    private function updateDockerComposeImage(): void
    {
        $dockerComposeFile = $this->basePath . '/docker-compose.yml';
        $content = file_get_contents($dockerComposeFile);
        
        if ($content === false) {
            $this->warning("Could not read docker-compose.yml to update image name");
            return;
        }
        
        $appServiceName = $this->getAppServiceName($content);
        if (!$appServiceName) {
            $this->warning("Could not find app service name in docker-compose.yml");
            return;
        }
        
        // Find the app service section and add/update image name
        // Pattern: service_name: ... build: ... -> service_name: ... image: custom_name ... build: ...
        $servicePattern = '/^(\s*' . preg_quote($appServiceName, '/') . ':\s*\n)(.*?)(?=^\s*[a-z]|\Z)/ms';
        
        if (preg_match($servicePattern, $content, $matches)) {
            $serviceContent = $matches[2];
            
            // Check if image already exists
            if (preg_match('/^\s*image:\s*.*$/m', $serviceContent)) {
                // Update existing image
                $serviceContent = preg_replace('/^\s*image:\s*.*$/m', "    image: {$this->customImageName}", $serviceContent);
            } else {
                // Add image before build section
                if (preg_match('/^\s*build:/m', $serviceContent)) {
                    $serviceContent = preg_replace('/^\s*build:/m', "    image: {$this->customImageName}\n    build:", $serviceContent);
                } else {
                    // Add at the beginning of service content
                    $serviceContent = "    image: {$this->customImageName}\n" . $serviceContent;
                }
            }
            
            $content = str_replace($matches[0], $matches[1] . $serviceContent, $content);
            file_put_contents($dockerComposeFile, $content);
            $this->info("✓ Updated docker-compose.yml with custom image name: {$this->customImageName}");
        }
    }
    
    /**
     * Remove existing containers
     * 
     * @param array<string> $containerNames
     * @return void
     */
    private function removeContainers(array $containerNames): void
    {
        $this->info("Removing existing containers...");
        
        foreach ($containerNames as $containerName) {
            $output = [];
            $returnCode = 0;
            exec("docker rm -f {$containerName} 2>&1", $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->info("✓ Removed container: {$containerName}");
            } else {
                $this->warning("Could not remove container: {$containerName}");
            }
        }
    }
    
    /**
     * Build and start Docker containers
     * 
     * @return void
     */
    private function buildContainers(): void
    {
        $this->info("Building Docker containers...");
        
        $command = "docker compose up -d --build";
        $output = [];
        $returnCode = 0;
        
        // Change to project directory
        $originalDir = getcwd();
        chdir($this->basePath);
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        // Restore original directory
        chdir($originalDir);
        
        if ($returnCode === 0) {
            $this->info("✓ Docker containers built and started successfully!");
            $this->displayContainerStatus();
        } else {
            $this->error("Failed to build Docker containers:");
            $this->error(implode("\n", $output));
            $this->info("Please check the errors above and try again manually: {$command}");
        }
    }
    
    /**
     * Display container status after build
     * 
     * @return void
     */
    private function displayContainerStatus(): void
    {
        $this->info("Checking container status...");
        
        $output = [];
        $returnCode = 0;
        
        // Change to project directory
        $originalDir = getcwd();
        chdir($this->basePath);
        
        exec("docker compose ps 2>&1", $output, $returnCode);
        
        // Restore original directory
        chdir($originalDir);
        
        if ($returnCode === 0 && !empty($output)) {
            $this->write("\n", 'white');
            foreach ($output as $line) {
                $this->write($line . "\n", 'white');
            }
            $this->write("\n", 'white');
        }
    }
}


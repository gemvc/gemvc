<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
use Gemvc\CLI\Commands\DbInit;
use Gemvc\CLI\Commands\DbMigrate;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\Database\DatabaseManagerFactory;
use App\Model\UserModel;

class SetAdmin extends Command
{
    /**
     * Read input from user
     * 
     * @param string $prompt
     * @return string
     */
    private function readInput(string $prompt): string
    {
        echo $prompt;
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            return '';
        }
        $input = fgets($handle);
        fclose($handle);
        return $input !== false ? trim($input) : '';
    }

    /**
     * Read password from user input (hidden on Unix, visible on Windows)
     * 
     * @param string $prompt
     * @return string
     */
    private function readPassword(string $prompt): string
    {
        echo $prompt;
        
        // On Windows, we can't hide input easily, so just read normally
        // On Unix/Linux, we can use shell commands to hide input
        if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
            // Unix/Linux: Use stty to hide input
            shell_exec('stty -echo');
            $password = trim(fgets(STDIN) ?: '');
            shell_exec('stty echo');
            echo "\n";
            return $password;
        } else {
            // Windows: Just read normally (can't easily hide)
            $password = trim(fgets(STDIN) ?: '');
            return $password;
        }
    }

    /**
     * Validate email format
     * 
     * @param string $email
     * @return bool
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if database is initialized, if not ask user to initialize it
     * 
     * @return bool
     */
    private function ensureDatabaseInitialized(): bool
    {
        // First, try to connect as root to check if database exists
        $pdoRoot = DbConnect::connectAsRoot();
        if ($pdoRoot === null) {
            $this->error("Cannot connect to MySQL server. Please check your database configuration.");
            return false;
        }
        
        // Check if database exists
        ProjectHelper::loadEnv();
        $dbName = is_string($_ENV['DB_NAME'] ?? null) ? $_ENV['DB_NAME'] : '';
        if (empty($dbName)) {
            $this->error("Database name not found in environment variables");
            return false;
        }
        
        try {
            $stmt = $pdoRoot->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
            if ($stmt === false) {
                $this->error("Failed to check if database exists");
                return false;
            }
            $stmt->execute([$dbName]);
            $result = $stmt->fetch();
            $dbExists = $result !== false && is_array($result) && isset($result['SCHEMA_NAME']);
            
            if ($dbExists) {
                // Database exists, verify we can connect to it
                $pdo = DbConnect::connect();
                if ($pdo !== null) {
                    return true;
                }
            }
            
            // Database doesn't exist or not accessible, ask user
            $this->warning("Database '{$dbName}' is not initialized.");
            $response = $this->readInput("Do you want to initialize it now? [Y/n]: ");
            $response = strtolower(trim($response));
            
            // Default is Yes (empty response or 'y' or 'yes')
            if ($response === '' || $response === 'y' || $response === 'yes') {
                // Initialize database
                $this->info("Initializing database...");
                $dbInit = new DbInit();
                if (!$dbInit->execute()) {
                    $this->error("Failed to initialize database");
                    return false;
                }
            } else {
                $this->warning("Database initialization skipped. You can run 'gemvc db:init' later.");
                // Continue anyway - it will fail later when trying to connect, but we chain the commands
            }
            
            return true;
        } catch (\Exception $e) {
            $this->error("Error checking database: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if UserTable is migrated, if not ask user to migrate it
     * 
     * @return bool
     */
    private function ensureUserTableMigrated(): bool
    {
        ProjectHelper::loadEnv();
        $pdo = DbConnect::connect();
        if ($pdo === null) {
            $this->error("Cannot connect to database to check UserTable");
            return false;
        }
        
        // Check if users table exists
        $dbName = is_string($_ENV['DB_NAME'] ?? null) ? $_ENV['DB_NAME'] : '';
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users'");
            if ($stmt === false) {
                $this->error("Failed to prepare query to check if users table exists");
                return false;
            }
            $stmt->execute([$dbName]);
            $result = $stmt->fetch();
            $count = (is_array($result) && isset($result['count']) && is_numeric($result['count'])) ? (int)$result['count'] : 0;
            $tableExists = $count > 0;
        } catch (\Exception $e) {
            // If query fails, assume table doesn't exist
            $tableExists = false;
        }
        
        if ($tableExists) {
            // Table exists
            return true;
        }
        
        // Table doesn't exist, ask user
        $this->warning("UserTable is not migrated. You can migrate it with command: gemvc db:migrate UserTable");
        $response = $this->readInput("Do you want to migrate it now? [Y/n]: ");
        $response = strtolower(trim($response));
        
        // Default is Yes (empty response or 'y' or 'yes')
        if ($response === '' || $response === 'y' || $response === 'yes') {
            // Migrate UserTable
            $this->info("Migrating UserTable...");
            $dbMigrate = new DbMigrate(['UserTable']);
            if (!$dbMigrate->execute()) {
                $this->error("Failed to migrate UserTable");
                return false;
            }
        } else {
            $this->warning("UserTable migration skipped. You can run 'gemvc db:migrate UserTable' later.");
            // Continue anyway - it will fail later when trying to create user, but we chain the commands
        }
        
        return true;
    }

    public function execute(): bool
    {
        try {
            $this->info("Creating first admin user...");
            
            // Load environment
            ProjectHelper::loadEnv();
            
            // Check and initialize database if needed
            if (!$this->ensureDatabaseInitialized()) {
                return false;
            }
            
            // Check and migrate UserTable if needed
            if (!$this->ensureUserTableMigrated()) {
                return false;
            }
            
            // Ensure CLI uses localhost for database connection (not Docker hostname)
            // This is needed because UserModel uses Table layer which connects via DatabaseManagerFactory
            // PdoConnection reads DB_HOST and DB_HOST_CLI_DEV, so we need to override both for CLI
            $currentHost = is_string($_ENV['DB_HOST'] ?? null) ? $_ENV['DB_HOST'] : 'localhost';
            $dockerHostnames = ['db', 'mysql', 'database'];
            if (in_array(strtolower($currentHost), $dockerHostnames, true)) {
                // Docker hostname detected, use localhost for CLI
                $_ENV['DB_HOST'] = 'localhost';
                putenv('DB_HOST=localhost');
                // Also set DB_HOST_CLI_DEV in case DatabaseManagerFactory uses it for CLI
                $_ENV['DB_HOST_CLI_DEV'] = 'localhost';
                putenv('DB_HOST_CLI_DEV=localhost');
                // Reset DatabaseManagerFactory to pick up new configuration
                DatabaseManagerFactory::resetInstance();
                $this->info("Using localhost for database connection (CLI mode)");
            }
            
            // Security check: Count existing users
            try {
                $pdo = DbConnect::connect();
                if ($pdo === null) {
                    $this->error("Cannot connect to database to check existing users");
                    return false;
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
                if ($stmt === false) {
                    $this->error("Failed to prepare query to count users");
                    return false;
                }
            $stmt->execute();
            $result = $stmt->fetch();
            $userCount = (is_array($result) && isset($result['count']) && is_numeric($result['count'])) ? (int)$result['count'] : 0;
                
                if ($userCount > 0) {
                    $this->error("Cannot perform this operation: Users already exist in the database (count: {$userCount})");
                    $this->error("This command can only be used when the database is empty for security reasons.");
                    return false;
                }
            } catch (\Exception $e) {
                // If table doesn't exist or query fails, we'll let it continue
                // The firstAdminUser method will handle the error appropriately
                $this->warning("Could not check user count: " . $e->getMessage());
            }
            
            // Get name
            $name = '';
            while (empty($name)) {
                $name = $this->readInput("Enter admin name: ");
                if (empty($name)) {
                    $this->warning("Name cannot be empty. Please try again.");
                }
            }
            
            // Get email
            $email = '';
            while (empty($email)) {
                $email = $this->readInput("Enter admin email: ");
                if (empty($email)) {
                    $this->warning("Email cannot be empty. Please try again.");
                } elseif (!$this->isValidEmail($email)) {
                    $this->warning("Invalid email format. Please try again.");
                    $email = '';
                }
            }
            
            // Get password
            $password = '';
            while (empty($password)) {
                $password = $this->readPassword("Enter admin password: ");
                if (empty($password)) {
                    $this->warning("Password cannot be empty. Please try again.");
                }
            }
            
            // Confirm password
            $confirmPassword = $this->readPassword("Confirm admin password: ");
            
            if ($password !== $confirmPassword) {
                $this->error("Passwords do not match");
                return false;
            }
            
            // Reload environment to ensure DB_HOST overrides are picked up
            ProjectHelper::loadEnv();
            // Ensure DB_HOST is still set to localhost (in case loadEnv() overwrote it)
            $currentHost = is_string($_ENV['DB_HOST'] ?? null) ? $_ENV['DB_HOST'] : 'localhost';
            $dockerHostnames = ['db', 'mysql', 'database'];
            if (in_array(strtolower($currentHost), $dockerHostnames, true)) {
                $_ENV['DB_HOST'] = 'localhost';
                putenv('DB_HOST=localhost');
                $_ENV['DB_HOST_CLI_DEV'] = 'localhost';
                putenv('DB_HOST_CLI_DEV=localhost');
                DatabaseManagerFactory::resetInstance();
            }
            
            // Create UserModel instance and call firstAdminUser
            /** @phpstan-ignore-next-line */
            $userModel = new UserModel();
            /** @phpstan-ignore-next-line */
            $response = $userModel->firstAdminUser($email, $password, $name);
            
            // Check response
            if ($response->response_code === 201) {
                // Success - created
                $this->success("Admin user created successfully!");
                $this->info("Name: {$name}");
                $this->info("Email: {$email}");
                $this->info("Role: admin");
                return true;
            } elseif ($response->response_code === 403) {
                // Forbidden - admin already exists
                $this->error($response->message ?? "Admin user already exists");
                return false;
            } else {
                // Other error
                $errorMessage = $response->service_message ?? $response->message ?? "Unknown error";
                $this->error("Failed to create admin user: {$errorMessage}");
                return false;
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to create admin user: " . $e->getMessage());
            return false;
        }
    }
}

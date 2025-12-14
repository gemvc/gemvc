<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
use Gemvc\Helper\CryptHelper;

class AdminSetpassword extends Command
{
    /**
     * Update ADMIN_PASSWORD in .env file
     * 
     * @param string $envPath Path to .env file
     * @param string $password Password to set (plain text)
     * @return bool
     */
    private function updateEnvFile(string $envPath, string $password): bool
    {
        if (!file_exists($envPath)) {
            $this->error(".env file not found at: {$envPath}");
            return false;
        }

        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            $this->error("Failed to read .env file");
            return false;
        }

        // Pattern to match ADMIN_PASSWORD line (handles quoted and unquoted values)
        // Matches: ADMIN_PASSWORD=value or ADMIN_PASSWORD="value" or ADMIN_PASSWORD='value'
        $pattern = '/^ADMIN_PASSWORD\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\n\r]*)/m';
        
        // Replace existing ADMIN_PASSWORD or add if not exists
        if (preg_match($pattern, $envContent)) {
            // Replace existing - always use double quotes for consistency
            $replacement = 'ADMIN_PASSWORD="' . $password . '"';
            $envContent = preg_replace($pattern, $replacement, $envContent);
        } else {
            // Add new line after APP_ENV if found, otherwise append to end
            if (preg_match('/^APP_ENV\s*=.*$/m', $envContent, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1] + strlen($matches[0][0]);
                $envContent = substr_replace($envContent, "\nADMIN_PASSWORD=\"" . $password . "\"", $pos, 0);
            } else {
                $envContent .= "\nADMIN_PASSWORD=\"" . $password . "\"\n";
            }
        }

        if (file_put_contents($envPath, $envContent) === false) {
            $this->error("Failed to write to .env file");
            return false;
        }

        return true;
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

    public function execute(): bool
    {
        try {
            $this->info("Setting admin password for system pages...");
            
            // Get project root and .env path
            $rootDir = ProjectHelper::rootDir();
            $envPath = $rootDir . DIRECTORY_SEPARATOR . '.env';
            
            // Check if .env exists
            if (!file_exists($envPath)) {
                $this->error(".env file not found. Please run 'gemvc init' first.");
                return false;
            }

            // Read password from user
            $password = $this->readPassword("Enter admin password: ");
            
            if (empty($password)) {
                $this->error("Password cannot be empty");
                return false;
            }

            // Confirm password
            $confirmPassword = $this->readPassword("Confirm admin password: ");
            
            if ($password !== $confirmPassword) {
                $this->error("Passwords do not match");
                return false;
            }

            // Store password in plain text (acceptable for dev-only admin access)
            // .env files are already used for passwords and are not in version control
            $plainPassword = $password;
            
            // Update .env file
            if ($this->updateEnvFile($envPath, $plainPassword)) {
                $this->success("Admin password set successfully!");
                $this->info("You can now access system pages using this password.");
                return true;
            } else {
                return false;
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to set admin password: " . $e->getMessage());
            return false;
        }
    }
}


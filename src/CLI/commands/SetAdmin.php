<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
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

    public function execute(): bool
    {
        try {
            $this->info("Creating first admin user...");
            
            // Load environment
            ProjectHelper::loadEnv();
            
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
            
            // Create UserModel instance and call firstAdminUser
            $userModel = new UserModel();
            $response = $userModel->firstAdminUser($email, $password);
            
            // Check response
            if ($response->response_code === 201) {
                // Success - created
                $this->success("Admin user created successfully!");
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

<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
use PDO;
/**
 * Drop Table from the database
 */
class DbDrop extends Command
{
    public function execute(): bool
    {
        ProjectHelper::loadEnv();
        try {
            // Check if table name is provided
            if (empty($this->args)) {
                $this->error("Table name is required. Usage: db:drop TableName [--force]");
                return false;
            }

            if (!is_string($this->args[0])) {
                $this->error("Table name must be a string");
                return false;
            }
            $tableName = strtolower($this->args[0]);
            $force = in_array('--force', $this->args);

            // Ask for confirmation unless --force is used
            if (!$force) {
                $this->error("\nWARNING: This will permanently delete the table '{$tableName}' and all its data!");
                $this->error("This action cannot be undone.");

                $this->write("\nAre you sure you want to drop this table? (yes/no): ", 'yellow');
                $handle = fopen("php://stdin", "r");
                if ($handle === false) {
                    $this->error("Failed to open stdin");
                    return false;
                }
                $line = fgets($handle);
                fclose($handle);
                $confirm = $line !== false ? trim($line) : '';

                if (strtolower($confirm) !== 'yes') {
                    $this->info("Operation cancelled.");
                    return false;
                }
            }
            // Get database connection
            $pdo = DbConnect::connect();
            if (!$pdo) {
                $this->error("Failed to connect to database");
                return false;
            }

            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
            if ($stmt === false) {
                throw new \Exception("Failed to check if table exists");
            }
            if ($stmt->rowCount() === 0) {
                throw new \Exception("Table '{$tableName}' does not exist.");
            }

            // Show table structure before dropping
            $this->info("\nTable structure to be dropped:");
            $stmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
            if ($stmt === false) {
                throw new \Exception("Failed to get table structure");
            }
            $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($tableInfo) && isset($tableInfo['Create Table']) && is_string($tableInfo['Create Table'])) {
                $this->info($tableInfo['Create Table']);
            }

            // Drop the table
            $pdo->exec("DROP TABLE `{$tableName}`");

            $this->success("Table '{$tableName}' has been dropped successfully!");
            return true;
        } catch (\Exception $e) {
            $this->error("Failed to drop table: " . $e->getMessage());
            return false;
        }
    }
}

<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\Helper\ProjectHelper;

class DbList extends Command
{
    public function execute(): bool
    {
        try {
            $this->info("Fetching database tables...");
            
            // Load environment variables
            ProjectHelper::loadEnv();
            
            // Get database name from environment
            $dbName = $_ENV['DB_NAME'] ?? null;
            if (!$dbName || !is_string($dbName)) {
                $this->error("Database name not found in configuration (DB_NAME)");
                return false;
            }
            
            // Get database connection
            
            $pdo = DbConnect::connect();
            if (!$pdo) {
                return false;
            }
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES FROM `{$dbName}`");
            if ($stmt === false) {
                $this->error("Failed to query database tables");
                return false;
            }
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                $this->info("No tables found in database '{$dbName}'");
                return false;
            }
            
            // Display tables and their columns
            $this->write("\nTables in database '{$dbName}':\n", 'yellow');
            foreach ($tables as $table) {
                $this->write("\nTable: {$table}\n", 'green');
                
                // Get columns for this table
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
                if ($stmt === false) {
                    $this->warning("Failed to get columns for table: {$table}");
                    return false;
                }
                $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                if (empty($columns)) {
                    $this->write("  No columns found\n", 'red');
                    return false;
                }
                
                // Display column information
                $this->write("  Columns:\n", 'cyan');
                foreach ($columns as $column) {
                    $type = $column['Type'];
                    $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                    $key = $column['Key'] ? "({$column['Key']})" : '';
                    $default = $column['Default'] !== null ? "DEFAULT {$column['Default']}" : '';
                    $extra = $column['Extra'] ? " {$column['Extra']}" : '';
                    
                    $columnInfo = sprintf(
                        "    - %s: %s %s %s %s %s",
                        $column['Field'],
                        $type,
                        $null,
                        $key,
                        $default,
                        $extra
                    );
                    $this->write(trim($columnInfo) . "\n", 'white');
                }
            }
            $this->write("\n");
            return true;
        } catch (\Exception $e) {
            $this->error("Failed to list tables: " . $e->getMessage());
            return false;
        }
    }
} 
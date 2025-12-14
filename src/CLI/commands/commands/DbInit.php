<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\Helper\ProjectHelper;
use Gemvc\CLI\Commands\DbConnect;

class DbInit extends Command
{
    public function execute(): bool
    {
        ProjectHelper::loadEnv();
        try {
            $this->info("Initializing database...");
            $pdo = DbConnect::connectAsRoot();
            if(!$pdo){
                return false;
            }   
            $dbName = $_ENV['DB_NAME'];
            if (!is_string($dbName)) {
                $this->error("Database name not found in environment variables");
                return false;
            }
            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}`";
            $pdo->exec($sql);
            $this->success("Database '{$dbName}' initialized successfully!");
            return true;
            
        } catch (\Exception $e) {
            $this->error("Failed to initialize database: " . $e->getMessage());
            return false;
        }
    }
} 
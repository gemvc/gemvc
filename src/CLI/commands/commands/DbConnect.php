<?php

namespace Gemvc\CLI\Commands;

use Gemvc\Helper\ProjectHelper;
use PDO;
use Gemvc\CLI\Command;

class DbConnect extends Command
{
    /**
     * Connect to the database as root not to specific database
     * @return PDO|null
     */
    public static function connectAsRoot(): ?PDO
    {
        ProjectHelper::loadEnv();
        $me = new self();
        $dbHost = is_string($_ENV['DB_HOST_CLI_DEV'] ?? null) ? $_ENV['DB_HOST_CLI_DEV'] : 'localhost';
        $dbUser = is_string($_ENV['DB_USER'] ?? null) ? $_ENV['DB_USER'] : 'root';
        $dbPass = is_string($_ENV['DB_PASSWORD'] ?? null) ? $_ENV['DB_PASSWORD'] : '';
        $dbPort = is_string($_ENV['DB_PORT'] ?? null) ? $_ENV['DB_PORT'] : '3306';
        $dbCharset = is_string($_ENV['DB_CHARSET'] ?? null) ? $_ENV['DB_CHARSET'] : 'utf8mb4';
        
        // Create connection without database name
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $dbHost,
            $dbPort,
            $dbCharset
        );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbCharset}"
            ];
            $me->info("trying to connect to the database as root on the host {$dbHost}...");
            $pdo = null;
            try{
                $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                $me->success("Connected to the database as root successfully",false);
                return $pdo;
            }catch(\Exception $e){
                $me->error("Failed to connect to the database as root: ".$e->getMessage());
                return null;
            }
    }
    /**
     * Connect to the database with or without special Database name
     * @return PDO|null
     */
    public static function connect(): ?PDO
    {
        ProjectHelper::loadEnv();
        $me = new self();
        $dbHost = is_string($_ENV['DB_HOST_CLI_DEV'] ?? null) ? $_ENV['DB_HOST_CLI_DEV'] : 'localhost';
        $dbUser = is_string($_ENV['DB_USER'] ?? null) ? $_ENV['DB_USER'] : 'root';
        $dbPass = is_string($_ENV['DB_PASSWORD'] ?? null) ? $_ENV['DB_PASSWORD'] : '';
        $dbPort = is_string($_ENV['DB_PORT'] ?? null) ? $_ENV['DB_PORT'] : '3306';
        $dbCharset = is_string($_ENV['DB_CHARSET'] ?? null) ? $_ENV['DB_CHARSET'] : 'utf8mb4';
        $dbName = is_string($_ENV['DB_NAME'] ?? null) ? $_ENV['DB_NAME'] : '';
        $me->info("trying to connect to the database {$dbName} on the host {$dbHost}...");
        $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $dbHost,
                $dbPort,
                $dbName,
                $dbCharset
            );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbCharset}"
        ];
        $pdo = null;
        try{
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            $me->success("Connected to the database {$dbName} on the host {$dbHost} successfully",false);
            return $pdo;
        }catch(\Exception $e){
            $me->error("Failed to connect to the database {$dbName} on the host {$dbHost}: ".$e->getMessage());
            return null;
        }
    }

    public function execute(): bool
    {
        $this->info(" Test Connecting to the database...");
        $pdo = self::connect();
        if($pdo){
            $this->success("Connected to the database successfully",false);
            return true;
        }else{
            $this->error("Failed to connect to the database");
            return false;
        }
    }


}
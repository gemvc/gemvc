<?php

namespace Gemvc\CLI\Commands;

use Gemvc\Helper\ProjectHelper;
use PDO;
use Gemvc\CLI\Command;

class DbConnect extends Command
{
    /**
     * Connect to the database as root not to specific database
     * 
     * Driver-aware (DB_DRIVER: mysql/pgsql/sqlite). For PostgreSQL, "root" connects
     * to the default 'postgres' administrative database (Postgres has no concept of
     * connecting without selecting *some* database). SQLite has no root/admin concept
     * at all (it's just a file) - this returns null with an error for that driver.
     * 
     * @return PDO|null
     */
    public static function connectAsRoot(): ?PDO
    {
        ProjectHelper::loadEnv();
        $me = new self();
        $driver = self::resolveDriver();

        if ($driver === 'sqlite') {
            $me->error("connectAsRoot() is not applicable for the 'sqlite' driver (SQLite has no separate root/admin connection).");
            return null;
        }

        $dbHost = is_string($_ENV['DB_HOST_CLI_DEV'] ?? null) ? $_ENV['DB_HOST_CLI_DEV'] : 'localhost';
        $dbUser = is_string($_ENV['DB_USER'] ?? null) ? $_ENV['DB_USER'] : self::defaultUser($driver);
        $dbPass = is_string($_ENV['DB_PASSWORD'] ?? null) ? $_ENV['DB_PASSWORD'] : '';
        $dbPort = is_string($_ENV['DB_PORT'] ?? null) ? $_ENV['DB_PORT'] : (string) self::defaultPort($driver);
        $dbCharset = is_string($_ENV['DB_CHARSET'] ?? null) ? $_ENV['DB_CHARSET'] : self::defaultCharset($driver);

        if ($driver === 'pgsql') {
            // Postgres requires a database name even for an administrative connection;
            // 'postgres' is the conventional default admin database.
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=postgres', $dbHost, $dbPort);
            $sslmode = is_string($_ENV['DB_SSLMODE'] ?? null) ? $_ENV['DB_SSLMODE'] : '';
            if ($sslmode !== '') {
                $dsn .= ';sslmode=' . $sslmode;
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ];
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $dbHost, $dbPort, $dbCharset);
            $options = self::mysqlOptions($dbCharset);
        }

        $me->info("trying to connect to the database as root on the host {$dbHost}...");
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            if ($driver === 'pgsql') {
                $pdo->exec(sprintf("SET NAMES '%s'", $dbCharset));
            }
            $me->success("Connected to the database as root successfully", false);
            return $pdo;
        } catch (\Exception $e) {
            $me->error("Failed to connect to the database as root: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Connect to the database with or without special Database name
     * 
     * Driver-aware (DB_DRIVER: mysql/pgsql/sqlite). Uses DB_HOST_CLI_DEV (the
     * host-machine-resolvable address), not DB_HOST (which may only resolve inside a
     * docker network) - CLI commands run on the host, not inside the app container.
     * 
     * @return PDO|null
     */
    public static function connect(): ?PDO
    {
        ProjectHelper::loadEnv();
        $me = new self();
        $driver = self::resolveDriver();

        if ($driver === 'sqlite') {
            return self::connectSqlite($me);
        }

        $dbHost = is_string($_ENV['DB_HOST_CLI_DEV'] ?? null) ? $_ENV['DB_HOST_CLI_DEV'] : 'localhost';
        $dbUser = is_string($_ENV['DB_USER'] ?? null) ? $_ENV['DB_USER'] : self::defaultUser($driver);
        $dbPass = is_string($_ENV['DB_PASSWORD'] ?? null) ? $_ENV['DB_PASSWORD'] : '';
        $dbPort = is_string($_ENV['DB_PORT'] ?? null) ? $_ENV['DB_PORT'] : (string) self::defaultPort($driver);
        $dbCharset = is_string($_ENV['DB_CHARSET'] ?? null) ? $_ENV['DB_CHARSET'] : self::defaultCharset($driver);
        $dbName = is_string($_ENV['DB_NAME'] ?? null) ? $_ENV['DB_NAME'] : '';

        $me->info("trying to connect to the database {$dbName} on the host {$dbHost}...");

        if ($driver === 'pgsql') {
            // PostgreSQL DSN does NOT support a 'charset' parameter (unlike MySQL) -
            // passing one causes "invalid connection option" errors, so it's deliberately
            // omitted here. Charset/encoding is set post-connect via SET NAMES instead.
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);
            $sslmode = is_string($_ENV['DB_SSLMODE'] ?? null) ? $_ENV['DB_SSLMODE'] : '';
            if ($sslmode !== '') {
                $dsn .= ';sslmode=' . $sslmode;
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ];
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
            $options = self::mysqlOptions($dbCharset);
        }

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            if ($driver === 'pgsql') {
                // Postgres has no ATTR_INIT_COMMAND equivalent, so charset/encoding must be
                // set as a statement right after connecting (mirrors MySQL's "SET NAMES"
                // behavior, which MySQL applies atomically via INIT_COMMAND).
                $pdo->exec(sprintf("SET NAMES '%s'", $dbCharset));
            }
            $me->success("Connected to the database {$dbName} on the host {$dbHost} successfully", false);
            return $pdo;
        } catch (\Exception $e) {
            $me->error("Failed to connect to the database {$dbName} on the host {$dbHost}: " . $e->getMessage());
            return null;
        }
    }

    private static function connectSqlite(self $me): ?PDO
    {
        $dbName = is_string($_ENV['DB_NAME'] ?? null) && $_ENV['DB_NAME'] !== '' ? $_ENV['DB_NAME'] : ':memory:';
        $dsn = $dbName === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $dbName;

        $me->info("trying to connect to the SQLite database {$dbName}...");
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            $pdo = new PDO($dsn, null, null, $options);
            $me->success("Connected to the SQLite database {$dbName} successfully", false);
            return $pdo;
        } catch (\Exception $e) {
            $me->error("Failed to connect to the SQLite database {$dbName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<int, mixed>
     */
    private static function mysqlOptions(string $dbCharset): array
    {
        $mysqlInitCmdAttr = \PHP_VERSION_ID >= 80500 && \class_exists('Pdo\\Mysql')
            ? \Pdo\Mysql::ATTR_INIT_COMMAND
            : \PDO::MYSQL_ATTR_INIT_COMMAND;

        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            $mysqlInitCmdAttr => "SET NAMES {$dbCharset}",
        ];
    }

    private static function resolveDriver(): string
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';
        if (!is_string($driver)) {
            return 'mysql';
        }
        $driver = strtolower($driver);
        return in_array($driver, ['mysql', 'pgsql', 'sqlite'], true) ? $driver : 'mysql';
    }

    private static function defaultPort(string $driver): int
    {
        return $driver === 'pgsql' ? 5432 : 3306;
    }

    private static function defaultUser(string $driver): string
    {
        return $driver === 'pgsql' ? 'postgres' : 'root';
    }

    private static function defaultCharset(string $driver): string
    {
        return $driver === 'pgsql' ? 'UTF8' : 'utf8mb4';
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

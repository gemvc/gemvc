<?php

namespace Gemvc\Database;

use PDO;
use SplObjectStorage;
use Hyperf\DbConnection\Connection;

/**
 * Adapter for SwooleDatabaseManager to implement DatabaseManagerInterface
 * 
 * This adapter wraps the existing SwooleDatabaseManager to provide
 * a consistent interface while maintaining backward compatibility.
 * 
 * Key features:
 * - Maps PDO instances back to their underlying Hyperf connections
 * - Properly releases connections back to the pool
 * - Manages transaction state per pool name
 * - Prevents connection leaks in Swoole environment
 */
class SwooleDatabaseManagerAdapter implements DatabaseManagerInterface
{
    /** @var SwooleDatabaseManager The wrapped Swoole database manager */
    private SwooleDatabaseManager $swooleManager;
    
    /** @var SplObjectStorage<PDO, Connection> Map PDO -> underlying pooled Connection */
    private SplObjectStorage $pdoToConnectionMap;
    
    /** @var array<string, Connection> Active transaction connections by pool name */
    private array $transactionConnections = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->swooleManager = SwooleDatabaseManager::getInstance();
        $this->pdoToConnectionMap = new SplObjectStorage();
    }

    /**
     * Get a database connection
     * 
     * @param string $poolName Connection pool name
     * @return \PDO|null Active PDO connection or null on error
     */
    public function getConnection(string $poolName = 'default'): ?PDO
    {
        $connection = $this->swooleManager->getConnection($poolName);
        if ($connection === null) {
            return null;
        }
        
        // @phpstan-ignore-next-line
        $pdo = $connection->getPdo();
        // Track mapping to be able to release it later
        $this->pdoToConnectionMap[$pdo] = $connection;
        return $pdo;
    }

    /**
     * Release a connection back to the pool
     * 
     * @param \PDO $connection The connection to release
     * @return void
     */
    public function releaseConnection(\PDO $connection): void
    {
        // Release the pooled connection that backs this PDO
        if (isset($this->pdoToConnectionMap[$connection])) {
            /** @var Connection $hyperfConn */
            $hyperfConn = $this->pdoToConnectionMap[$connection];
            unset($this->pdoToConnectionMap[$connection]);
            try {
                $hyperfConn->release();
            } catch (\Throwable $e) {
                // best-effort; log if needed
                error_log('Swoole adapter release failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get the last error message
     * 
     * @return string|null Error message or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->swooleManager->getError();
    }

    /**
     * Set an error message
     * 
     * @param string|null $error The error message to set
     * @param array<string, mixed> $context Additional context information
     * @return void
     */
    public function setError(?string $error, array $context = []): void
    {
        $this->swooleManager->setError($error, $context);
    }

    /**
     * Clear the last error message
     * 
     * @return void
     */
    public function clearError(): void
    {
        $this->swooleManager->clearError();
    }

    /**
     * Check if the database manager is properly initialized
     * 
     * @return bool True if initialized, false otherwise
     */
    public function isInitialized(): bool
    {
        return true; // SwooleDatabaseManager is always initialized
    }

    /**
     * Get connection pool statistics
     * 
     * @return array<string, mixed> Pool statistics
     */
    public function getPoolStats(): array
    {
        return [
            'type' => 'Swoole Database Manager',
            'environment' => 'OpenSwoole',
            'has_error' => $this->swooleManager->getError() !== null,
            'error' => $this->swooleManager->getError(),
        ];
    }

    /**
     * Begin a database transaction
     * 
     * @param string $poolName Connection pool name
     * @return bool True on success, false on failure
     */
    public function beginTransaction(string $poolName = 'default'): bool
    {
        if (isset($this->transactionConnections[$poolName])) {
            $this->setError('Transaction already active for pool: ' . $poolName);
            return false;
        }

        $connection = $this->swooleManager->getConnection($poolName);
        if ($connection === null) {
            return false;
        }

        try {
            // @phpstan-ignore-next-line
            $started = $connection->getPdo()->beginTransaction();
            if ($started) {
                $this->transactionConnections[$poolName] = $connection;
            } else {
                // if failed, ensure it is returned to pool
                $connection->release();
            }
            return $started;
        } catch (\Throwable $e) {
            $this->setError('Failed to begin transaction: ' . $e->getMessage());
            try { $connection->release(); } catch (\Throwable $t) {}
            return false;
        }
    }

    /**
     * Commit the current transaction
     * 
     * @param string $poolName Connection pool name
     * @return bool True on success, false on failure
     */
    public function commit(string $poolName = 'default'): bool
    {
        if (!isset($this->transactionConnections[$poolName])) {
            $this->setError('No active transaction for pool: ' . $poolName);
            return false;
        }

        /** @var Connection $connection */
        $connection = $this->transactionConnections[$poolName];
        try {
            // @phpstan-ignore-next-line
            $result = $connection->getPdo()->commit();
            unset($this->transactionConnections[$poolName]);
            try { $connection->release(); } catch (\Throwable $t) {}
            return $result;
        } catch (\Throwable $e) {
            $this->setError('Failed to commit transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback the current transaction
     * 
     * @param string $poolName Connection pool name
     * @return bool True on success, false on failure
     */
    public function rollback(string $poolName = 'default'): bool
    {
        if (!isset($this->transactionConnections[$poolName])) {
            $this->setError('No active transaction for pool: ' . $poolName);
            return false;
        }

        /** @var Connection $connection */
        $connection = $this->transactionConnections[$poolName];
        try {
            // @phpstan-ignore-next-line
            $result = $connection->getPdo()->rollBack();
            unset($this->transactionConnections[$poolName]);
            try { $connection->release(); } catch (\Throwable $t) {}
            return $result;
        } catch (\Throwable $e) {
            $this->setError('Failed to rollback transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if currently in a transaction
     * 
     * @param string $poolName Connection pool name
     * @return bool True if in transaction, false otherwise
     */
    public function inTransaction(string $poolName = 'default'): bool
    {
        return isset($this->transactionConnections[$poolName]);
    }
}


<?php

namespace Gemvc\Database;

use PDO;
use PDOStatement;
use Throwable;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;
use Gemvc\Http\Request;
use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Helper\ProjectHelper;

/**
 * Universal Query Executer for Multiple Web Server Environments
 * 
 * This class provides a unified interface for database query execution
 * across different web server environments:
 * - Apache PHP-FPM
 * - Nginx PHP-FPM  
 * - OpenSwoole
 * 
 * It automatically detects the environment and uses the appropriate
 * database manager implementation.
 */
class UniversalQueryExecuter
{
    private ?string $error = null;
    private int $affectedRows = 0;
    private string|false $lastInsertedId = false;
    private ?PDOStatement $statement = null;
    private float $startExecutionTime;
    private ?float $endExecutionTime = null;
    private string $query = '';
    /** @var array<string, mixed> */
    private array $bindings = [];
    private bool $inTransaction = false;

    /** @var \PDO|null The database connection */
    private ?PDO $db = null;

    /** @var ConnectionInterface|null The active connection interface */
    private ?ConnectionInterface $activeConnection = null;

    /** @var ConnectionManagerInterface The database manager */
    private ConnectionManagerInterface $dbManager;

    /** @var Request|null Request object for APM trace context propagation */
    private ?Request $_request = null;

    /**
     * Constructor - automatically detects environment and uses appropriate manager
     * 
     * @param Request|null $request Optional Request object for APM trace context propagation
     */
    public function __construct(?Request $request = null)
    {
        $this->startExecutionTime = microtime(true);
        $this->dbManager = DatabaseManagerFactory::getManager();
        $this->_request = $request;
    }

    /**
     * Get a connection from the appropriate manager
     * 
     * @param string $poolName The connection pool name (default: 'default')
     * @return \PDO|null A PDO connection, or null on error
     */
    private function getConnection(string $poolName = 'default'): ?PDO
    {
        $this->activeConnection = $this->dbManager->getConnection($poolName);
        if ($this->activeConnection === null) {
            $this->setError('Connection error: ' . ($this->dbManager->getError() ?? 'Unknown error'));
            return null;
        }

        $driver = $this->activeConnection->getConnection();
        if ($driver instanceof PDO) {
            return $driver;
        }

        $this->setError('Connection did not return a PDO instance');
        // Release non-PDO connection
        $connection = $this->activeConnection;
        if ($connection !== null) {
            $this->dbManager->releaseConnection($connection);
        }
        $this->activeConnection = null;
        return null;
    }

    /**
     * Destructor ensures that resources are cleaned up
     */
    public function __destruct()
    {
        // Force rollback if a transaction is still open when the object is destroyed
        $this->secure(true);
    }

    private function debug(string $message): void
    {
        if (($_ENV['APP_ENV'] ?? '') === 'dev') {
            // Use error_log instead of echo to avoid "headers already sent" errors
            error_log('[UniversalQueryExecuter] ' . $message);
        }
    }

    public function getQuery(): ?string
    {
        return $this->query ?: null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setError(?string $error, array $context = []): void
    {
        if ($error === null) {
            $this->error = null;
            return;
        }
        
        // Add context information to error message
        if (!empty($context)) {
            $contextStr = ' [Context: ' . json_encode($context) . ']';
            $this->error = $error . $contextStr;
        } else {
            $this->error = $error;
        }
    }

    /**
     * Detect duplicate entry/unique constraint violations and set appropriate error message
     * 
     * This is the ROOT level detection - all duplicate entry errors are handled here.
     * Other classes (PdoQuery, Table, Model) just check getError() and return it.
     * 
     * @param \PDOException $e The PDO exception
     * @return string|null The error message if it's a duplicate entry error, null otherwise
     */
    private function detectAndSetDuplicateEntryError(\PDOException $e): ?string
    {
        $sqlState = $e->getCode();
        $errorInfo = $e->errorInfo ?? [];
        $errorMessage = $e->getMessage();
        
        // Check for duplicate key/unique constraint violations
        // MySQL: SQLSTATE 23000, Error code 1062
        // PostgreSQL: SQLSTATE 23505 (unique_violation)
        // SQLite: SQLSTATE 23000, Error code 19 or 1555
        $isDuplicate = (
            $sqlState === '23000' || 
            $sqlState === '23505' || 
            (isset($errorInfo[1]) && ($errorInfo[1] === 1062 || $errorInfo[1] === 19 || $errorInfo[1] === 1555)) ||
            stripos($errorMessage, 'duplicate') !== false ||
            stripos($errorMessage, 'unique') !== false ||
            stripos($errorMessage, 'already exists') !== false
        );
        
        if (!$isDuplicate) {
            return null;
        }
        
        // Determine operation type from query
        $queryUpper = strtoupper(ltrim($this->query));
        $isInsert = ($queryUpper[0] ?? '') === 'I' && str_starts_with($queryUpper, 'INSERT');
        $isUpdate = ($queryUpper[0] ?? '') === 'U' && str_starts_with($queryUpper, 'UPDATE');
        
        // Set appropriate error message based on operation type
        if ($isInsert) {
            $duplicateError = 'This record cannot be created because a record with the same unique information already exists. Please use different values.';
        } elseif ($isUpdate) {
            $duplicateError = 'This record cannot be updated because another record with the same unique information already exists. Please use different values.';
        } else {
            // Generic duplicate entry error for other operations
            $duplicateError = 'This operation cannot be completed because a record with the same unique information already exists. Please use different values.';
        }
        
        $this->setError($duplicateError);
        return $duplicateError;
    }

    /**
     * Prepares a new SQL query for execution
     *
     * @param string $query The SQL query string
     */
    public function query(string $query): void
    {
        $this->setError(null);

        if (empty($query)) {
            $this->setError('Query cannot be empty');
            return;
        }

        if (strlen($query) > 1000000) { // 1MB limit
            $this->setError('Query exceeds maximum length');
            return;
        }

        if ($this->statement) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
        $this->bindings = [];
        $this->query = $query;

        try {
            if (!$this->db) {
                $this->db = $this->getConnection();
                if ($this->db === null) {
                    // Error already set by getConnection()
                    return;
                }
            }
            $this->statement = $this->db->prepare($query);
        } catch (Throwable $e) {
            $this->setError('Error preparing statement: ' . $e->getMessage());
            $this->releaseConnection(true); // Release potentially broken connection
        }
    }

    /**
     * Binds a value to a corresponding named or question mark placeholder in the SQL statement
     *
     * @param string $param Parameter identifier
     * @param mixed $value The value to bind to the parameter
     */
    public function bind(string $param, mixed $value): void
    {
        $this->setError(null);

        if (!$this->statement) {
            $this->setError('Cannot bind parameters: No statement prepared');
            return;
        }

        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };

        try {
            $this->statement->bindValue($param, $value, $type);
            $this->bindings[$param] = $value;
        } catch (\PDOException $e) {
            $this->setError('Error binding parameter: ' . $e->getMessage());
            // Release connection on bind error since execution won't proceed
            $this->releaseConnection(true);
        }
    }

    /**
     * Check if database query tracing is enabled via environment variable
     * 
     * NO static cache - env vars are fast enough, and static cache causes issues in OpenSwoole
     * 
     * @return bool True if APM_TRACE_DB_QUERY is set to '1' or 'true'
     */
    private static function shouldTraceDbQuery(): bool
    {
        $value = $_ENV['APM_TRACE_DB_QUERY'] ?? null;
        // $_ENV always returns strings, so only check strings
        return ($value === '1' || $value === 'true');
    }

    /**
     * Executes the prepared statement
     *
     * @return bool Returns TRUE on success or FALSE on failure
     */
    public function execute(): bool
    {
        $this->setError(null);
        $this->affectedRows = 0;
        $this->lastInsertedId = false;

        if (!$this->statement) {
            $this->setError('No statement prepared to execute');
            $this->endExecutionTime = microtime(true);
            // Release connection if it was acquired but statement preparation failed
            if ($this->activeConnection !== null) {
                $this->releaseConnection();
            }
            return false;
        }

        // APM tracing for database queries
        $dbSpan = [];
        $shouldTrace = false;
        $apm = null;
        
        // Try to use Request APM first (shares traceId)
        if ($this->_request !== null && $this->_request->apm !== null) {
            $apm = $this->_request->apm;
        } elseif (ApmFactory::isEnabled() !== null) {
            // Fallback: standalone APM for CLI/background jobs
            $apm = ApmFactory::create(null);
        }
        
        // Check if tracing is enabled (no static cache - see Fix 1 in review)
        if ($apm !== null && $apm->isEnabled() && self::shouldTraceDbQuery()) {
            $shouldTrace = true;
            
            // Optimize query type detection (only check first 10 chars)
            $queryStart = substr($this->query, 0, 10);
            $queryUpper = strtoupper(ltrim($queryStart));
            $queryType = match(true) {
                str_starts_with($queryUpper, 'SELECT') => 'SELECT',
                str_starts_with($queryUpper, 'INSERT') => 'INSERT',
                str_starts_with($queryUpper, 'UPDATE') => 'UPDATE',
                str_starts_with($queryUpper, 'DELETE') => 'DELETE',
                str_starts_with($queryUpper, 'CREATE') => 'CREATE',
                str_starts_with($queryUpper, 'ALTER') => 'ALTER',
                str_starts_with($queryUpper, 'DROP') => 'DROP',
                default => 'UNKNOWN'
            };
            
            // Detect database system from connection (default to mysql)
            $dbSystem = 'mysql'; // TODO: Detect from connection if needed
            
            // Start database query span with error handling
            try {
                $dbSpan = $apm->startSpan('database-query', [
                    'db.system' => $dbSystem,
                    'db.operation' => $queryType,
                    'db.statement' => $this->query,
                    'db.parameter_count' => (string)count($this->bindings),  // Performance: count only, not json_encode
                    'db.in_transaction' => $this->inTransaction ? 'true' : 'false',
                ], ApmInterface::SPAN_KIND_CLIENT);
            } catch (\Throwable $e) {
                // Graceful degradation - don't break queries if APM fails
                if (ProjectHelper::isDevEnvironment()) {
                    error_log("APM tracing error: " . $e->getMessage());
                }
                $dbSpan = [];
                $shouldTrace = false;
            }
        }

        try {
            $this->statement->execute();
            $this->affectedRows = $this->statement->rowCount();
            
            // PERFORMANCE: Cache trimmed query and check first char instead of stripos
            $queryUpper = strtoupper(ltrim($this->query));
            $isInsert = ($queryUpper[0] ?? '') === 'I' && str_starts_with($queryUpper, 'INSERT');
            $isSelect = ($queryUpper[0] ?? '') === 'S' && str_starts_with($queryUpper, 'SELECT');
            
            if ($isInsert && $this->db !== null) {
                $this->lastInsertedId = $this->db->lastInsertId();
            }
            $this->endExecutionTime = microtime(true);
            
            // APM: End span with success details (BEFORE connection release)
            if ($shouldTrace && !empty($dbSpan) && $apm !== null) {
                try {
                    $executionTime = $this->getExecutionTime();
                    $apm->endSpan($dbSpan, [
                        'db.rows_affected' => (string)$this->affectedRows,
                        'db.execution_time_ms' => (string)$executionTime,
                        'db.last_insert_id' => $this->lastInsertedId !== false ? (string)$this->lastInsertedId : 'none',
                    ], ApmInterface::STATUS_OK);
                } catch (\Throwable $e) {
                    // Graceful degradation
                    if (ProjectHelper::isDevEnvironment()) {
                        error_log("APM endSpan error: " . $e->getMessage());
                    }
                }
            }
            
            // PERFORMANCE: Release connection immediately after INSERT/UPDATE/DELETE
            // Don't wait for destructor - release as soon as we're done
            // SELECT queries need to keep connection open for fetching
            if (!$this->inTransaction && !$isSelect) {
                $this->statement->closeCursor();
                $this->releaseConnection();
            }
            
            return true;
        } catch (\PDOException $e) {
            // APM: Record exception and end span with error
            if ($shouldTrace && !empty($dbSpan) && $apm !== null) {
                try {
                    $apm->recordException($dbSpan, $e);
                    $executionTime = $this->getExecutionTime();
                    $apm->endSpan($dbSpan, [
                        'db.execution_time_ms' => (string)$executionTime,
                    ], ApmInterface::STATUS_ERROR);
                } catch (\Throwable $apmError) {
                    // Graceful degradation
                    if (ProjectHelper::isDevEnvironment()) {
                        error_log("APM error handling failed: " . $apmError->getMessage());
                    }
                }
            }
            $context = [
                'query' => $this->query,
                'bindings' => $this->bindings,
                'execution_time' => $this->getExecutionTime(),
                'in_transaction' => $this->inTransaction,
                'error_code' => $e->getCode()
            ];
            
            // PERFORMANCE: Log errors only in dev mode
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                $errorDetails = json_encode(['message' => $e->getMessage(), 'code' => $e->getCode(), 'query' => $this->query, 'bindings' => $this->bindings]);
                error_log("UniversalQueryExecuter::execute() - PDO Exception: " . $errorDetails);
            }
            
            // Detect duplicate entry/unique constraint violations at the root level
            // This ensures all classes that use UniversalQueryExecuter get proper error messages
            $errorMessage = $this->detectAndSetDuplicateEntryError($e);
            if ($errorMessage === null) {
                // Not a duplicate entry error, use the original exception message
                $this->setError($e->getMessage(), $context);
            }
            
            $this->endExecutionTime = microtime(true);
            // Release potentially broken connection
            $this->releaseConnection(true);
            return false;
        }
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    public function getLastInsertedId(): false|string
    {
        return $this->lastInsertedId;
    }

    public function getExecutionTime(): float
    {
        if ($this->endExecutionTime === null) {
            return 0;
        }
        return round(($this->endExecutionTime - $this->startExecutionTime) * 1000, 2);
    }

    /**
     * @return array<object>|false
     */
    public function fetchAllObjects(): array|false
    {
        if (!$this->statement) { $this->setError('No statement executed.'); return false; }
        try {
            $result = $this->statement->fetchAll(PDO::FETCH_OBJ);
            $this->statement->closeCursor();
            
            // Auto-release connection if not in transaction
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Error fetching objects: ' . $e->getMessage());
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            return false;
        }
    }

    /**
     * @return array<array<string, mixed>>|false
     */
    public function fetchAll(): array|false
    {
        if (!$this->statement) { $this->setError('No statement executed.'); return false; }
        try {
            $result = $this->statement->fetchAll(PDO::FETCH_ASSOC);
            $this->statement->closeCursor();
            
            // Auto-release connection if not in transaction
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Error fetching results: ' . $e->getMessage());
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            return false;
        }
    }

    public function fetchColumn(): mixed
    {
        if (!$this->statement) { $this->setError('No statement executed.'); return false; }
        try {
            $result = $this->statement->fetchColumn();
            $this->statement->closeCursor();
            
            // Auto-release connection if not in transaction
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Error fetching column: ' . $e->getMessage());
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            return false;
        }
    }

    /**
     * Fetches a single row as an associative array
     *
     * @return array<string, mixed>|false Returns the row as an associative array, or false on failure
     */
    public function fetchOne(): array|false
    {
        if (!$this->statement) { $this->setError('No statement executed.'); return false; }
        try {
            $result = $this->statement->fetch(PDO::FETCH_ASSOC);
            $this->statement->closeCursor();
            
            // Auto-release connection if not in transaction
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            
            if ($result === false) {
                return false;
            }
            /** @var array<string, mixed> $result */
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Error fetching row: ' . $e->getMessage());
            if (!$this->inTransaction) {
                $this->releaseConnection();
            }
            return false;
        }
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction) { $this->setError('Already in transaction'); return false; }
        if ($this->db) { $this->setError('Cannot start transaction, a connection is already active'); return false; }

        try {
            $this->db = $this->getConnection();
            if ($this->db === null) {
                // Error already set by getConnection()
                return false;
            }
            $this->db->beginTransaction();
            $this->inTransaction = true;
            return true;
        } catch (Throwable $e) {
            $this->setError('Error starting transaction: ' . $e->getMessage());
            $this->releaseConnection(true);
            return false;
        }
    }

    public function commit(): bool
    {
        if (!$this->inTransaction || !$this->db) { $this->setError('No active transaction to commit'); return false; }
        try {
            $this->db->commit();
            $this->inTransaction = false;
            return true;
        } catch (Throwable $e) {
            $this->setError('Error committing transaction: ' . $e->getMessage());
            return false;
        } finally {
            $this->releaseConnection();
        }
    }

    public function rollback(): bool
    {
        if (!$this->inTransaction || !$this->db) { $this->setError('No active transaction to rollback'); return false; }
        try {
            $this->db->rollBack();
            $this->inTransaction = false;
            return true;
        } catch (Throwable $e) {
            $this->setError('Error rolling back transaction: ' . $e->getMessage());
            return false;
        } finally {
            $this->releaseConnection();
        }
    }

    /**
     * Securely clean up database resources
     */
    public function secure(bool $forceRollback = false): void
    {
        if ($this->inTransaction && $this->db && $forceRollback) {
            try {
                $this->db->rollBack();
                $this->debug("Transaction rolled back in secure()");
            } catch (Throwable $e) {
                error_log('Error during forced rollback in secure(): ' . $e->getMessage());
            }
        }
        $this->releaseConnection();
    }

    /**
     * Release the current database connection back to the pool
     */
    private function releaseConnection(bool $isBroken = false): void
    {
        if ($this->statement) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
        if ($this->activeConnection !== null) {
            try {
                /** @var ConnectionInterface $connection */
                $connection = $this->activeConnection;
                $this->dbManager->releaseConnection($connection);
                if ($isBroken) {
                    $this->debug("Broken connection released back to pool.");
                }
            } catch (Throwable $e) {
                error_log('Error releasing connection: ' . $e->getMessage());
            }
            $this->activeConnection = null;
        }
        $this->db = null;
        $this->inTransaction = false;
    }

    /**
     * Get information about the current database manager
     * 
     * @return array<string, mixed> Manager information
     */
    public function getManagerInfo(): array
    {
        return DatabaseManagerFactory::getManagerInfo();
    }
}

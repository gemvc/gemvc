<?php

namespace Gemvc\Database;
use Gemvc\Database\UniversalQueryExecuter;
use Gemvc\Http\Request;

/**
 * PdoQuery uses UniversalQueryExecuter as a component with lazy loading to provide high-level methods for common database operations
 * All methods follow the unified return pattern: result|null where null indicates error and result indicates success
 * 
 * This class now works across all web server environments:
 * - OpenSwoole (with connection pooling)
 * - Apache PHP-FPM (with simple PDO)
 * - Nginx PHP-FPM (with simple PDO)
 */
class PdoQuery
{
    /** @var UniversalQueryExecuter|null Lazy-loaded universal query executor */
    private ?UniversalQueryExecuter $executer = null;
    
    /** @var bool Whether we have an active database connection */
    private bool $isConnected = false;

    /** @var Request|null Request object for APM trace context propagation */
    private ?Request $_request = null;

    /**
     * Clean constructor - no parameters needed!
     * Everything is handled automatically through the universal database interface.
     */
    public function __construct()
    {
        // No configuration needed - UniversalQueryExecuter handles everything
    }

    /**
     * Set Request object for APM trace context propagation
     * 
     * @param Request|null $request Request object to pass to UniversalQueryExecuter
     * @return void
     */
    public function setRequest(?Request $request): void
    {
        $this->_request = $request;
        // If executer already exists, we can't update it, but that's okay
        // The Request is only needed when creating the executer
    }

    /**
     * Lazy initialization of UniversalQueryExecuter.
     * Connection is automatically acquired from the appropriate manager when needed.
     */
    private function getExecuter(): UniversalQueryExecuter
    {
        if ($this->executer === null) {
            $this->executer = new UniversalQueryExecuter($this->_request);
            $this->isConnected = true;
        }
        
        // Propagate errors from UniversalQueryExecuter to PdoQuery
        if ($this->executer->getError() !== null) {
            $this->setError($this->executer->getError());
        }
        
        /** @var UniversalQueryExecuter */
        return $this->executer;
    }

    /**
     * Execute an INSERT query and return the last inserted ID
     * 
     * @param string $query The SQL INSERT query
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return int|null The last inserted ID (or 1 for tables without auto-increment) on success, null on failure
     */
    public function insertQuery(string $query, array $params = []): int|null
    {
        $success = false;
        try {
            if ($this->executeQuery($query, $params)) {
                // Check if there were any errors during execution
                if ($this->getExecuter()->getError() !== null) {
                    return null;
                }
                
                // Try to get the last inserted ID
                $lastId = $this->getExecuter()->getLastInsertedId();
                
                // If lastId is a valid ID (not 0 or false), return it as integer
                if ($lastId && is_numeric($lastId) && (int)$lastId > 0) {
                    $success = true;
                    return (int)$lastId;
                }
                
                // If no auto-increment ID but query was successful, 
                // check affected rows to confirm insert success
                $affectedRows = $this->getExecuter()->getAffectedRows();
                if ($affectedRows > 0) {
                    // Insert was successful but table has no auto-increment ID
                    // Return 1 to indicate success
                    $success = true;
                    return 1;
                }
                
                // No rows were affected, something went wrong
                $this->setError('Insert query executed but no rows were affected');
                return null;
            }
            return null;
        } catch (\PDOException $e) {
            $this->handleInsertError($e);
            return null;
        } finally {
            // PERFORMANCE: Only call secure() if query failed - successful queries already released connection
            // This avoids redundant release attempts when connection is already back in pool
            if ($this->executer !== null && !$success) {
                $this->getExecuter()->secure(true);
            }
        }
    }

    /**
     * Handle insert operation errors
     * 
     * Note: Duplicate entry errors are already detected and set by UniversalQueryExecuter.
     * This method delegates to handleQueryError which preserves existing errors.
     * 
     * @param \PDOException $e The exception that was thrown
     */
    private function handleInsertError(\PDOException $e): void
    {
        // UniversalQueryExecuter already detected and set duplicate entry errors at the root
        // handleQueryError will preserve the error if already set
        $this->handleQueryError('Insert', $e);
    }

    /**
     * Execute an UPDATE query and return the number of affected rows
     * 
     * @param string $query The SQL UPDATE query
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return int|null Number of affected rows (0 if no changes, null on error)
     */
    public function updateQuery(string $query, array $params = []): int|null
    {
        $success = false;
        try {
            if ($this->executeQuery($query, $params)) {
                $affectedRows = $this->getExecuter()->getAffectedRows();
                // Note: 0 affected rows is valid (no changes needed), not an error
                $success = true;
                return $affectedRows;
            }
            return null;
        } catch (\PDOException $e) {
            $this->handleUpdateError($e);
            return null;
        } finally {
            // PERFORMANCE: Only call secure() if query failed - successful queries already released connection
            if ($this->executer !== null && !$success) {
                $this->getExecuter()->secure(true);
            }
        }
    }

    /**
     * Handle update operation errors
     * 
     * Note: Duplicate entry errors are already detected and set by UniversalQueryExecuter.
     * This method only handles non-duplicate errors or adds context if needed.
     * 
     * @param \PDOException $e The exception that was thrown
     */
    private function handleUpdateError(\PDOException $e): void
    {
        // UniversalQueryExecuter already detected and set duplicate entry errors
        // Just use the general error handler for logging/context
        // The error message is already set by UniversalQueryExecuter
        $this->handleQueryError('Update', $e);
    }

    /**
     * Execute a DELETE query and return the number of affected rows
     * 
     * @param string $query The SQL DELETE query
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return int|null Number of affected rows (0 if no records found, null on error)
     */
    public function deleteQuery(string $query, array $params = []): int|null
    {
        $success = false;
        try {
            if ($this->executeQuery($query, $params)) {
                $affectedRows = $this->getExecuter()->getAffectedRows();
                // Note: 0 affected rows is valid (record not found), not an error
                $success = true;
                return $affectedRows;
            }
            return null;
        } catch (\PDOException $e) {
            $this->handleDeleteError($e);
            return null;
        } finally {
            // PERFORMANCE: Only call secure() if query failed - successful queries already released connection
            if ($this->executer !== null && !$success) {
                $this->getExecuter()->secure(true);
            }
        }
    }

    /**
     * Handle delete operation errors with special handling for foreign key constraints
     * 
     * @param \PDOException $e The exception that was thrown
     */
    private function handleDeleteError(\PDOException $e): void
    {
        $sqlState = $e->getCode();
        $errorInfo = $e->errorInfo ?? [];
        
        // Check for foreign key constraint violations
        // MySQL: SQLSTATE 23000, Error code 1451
        // PostgreSQL: SQLSTATE 23503  
        // SQLite: SQLSTATE 23000, Error code 787
        if (
            $sqlState === '23000' || 
            $sqlState === '23503' || 
            (isset($errorInfo[1]) && ($errorInfo[1] === 1451 || $errorInfo[1] === 787)) ||
            stripos($e->getMessage(), 'foreign key constraint') !== false ||
            stripos($e->getMessage(), 'cannot delete') !== false
        ) {
            $this->setError('This record cannot be deleted because it has related data in other tables. Please remove the related records first.');
        } else {
            // Use the general error handler for other types of errors
            $this->handleQueryError('Delete', $e);
        }
    }

    /**
     * Execute a SELECT query and return results as objects
     * 
     * @param string $query The SQL SELECT query
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return array<object>|null Array of objects (empty array if no results), null on error
     */
    public function selectQueryObjects(string $query, array $params = []): array|null
    {
        try {
            if ($this->executeQuery($query, $params)) {
                $result = $this->getExecuter()->fetchAllObjects();
                if ($result === false) {
                    $this->setError('Failed to fetch results from the query');
                    return null;
                }
                // Empty array is valid - means no results found
                return $result;
            }
            return null;
        } catch (\PDOException $e) {
            $this->handleQueryError('Select objects', $e);
            return null;
        } finally {
            // PERFORMANCE: Connection already released in fetchAllObjects(), skip redundant call
            // secure() only needed on error path
        }
    }

    /**
     * Execute a SELECT query and return results as associative arrays
     * 
     * @param string $query The SQL SELECT query
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return array<array<string, mixed>>|null Array of rows (empty array if no results), null on error
     */
    public function selectQuery(string $query, array $params = []): array|null
    {
        try {
            if ($this->executeQuery($query, $params)) {
                $result = $this->getExecuter()->fetchAll();
                if ($result === false) {
                    $this->setError('Failed to fetch results from the query');
                    return null;
                }
                // Empty array is valid - means no results found
                return $result;
            }
            return null;
        } catch (\PDOException $e) {
            $this->handleQueryError('Select', $e);
            return null;
        } finally {
            // PERFORMANCE: Connection already released in fetchAll(), skip redundant call
            // secure() only needed on error path
        }
    }

    /**
     * Execute a COUNT query and return the result as an integer
     * 
     * @param string $query The SQL SELECT COUNT query
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return int|null The count result (0 if no records), null on error
     */
    public function selectCountQuery(string $query, array $params = []): int|null
    {
        try {
            if ($this->executeQuery($query, $params)) {
                $result = $this->getExecuter()->fetchColumn();
                if ($result === false) {
                    $this->setError('Failed to fetch count result');
                    return null;
                }
                
                if (is_numeric($result)) {
                    return (int)$result;
                } else {
                    $this->setError('Count query did not return a numeric value');
                    return null;
                }
            }
            return null;
        } catch (\PDOException $e) {
            $this->handleQueryError('Count', $e);
            return null;
        } finally {
            // PERFORMANCE: Connection already released in fetchColumn(), skip redundant call
            // secure() only needed on error path
        }
    }

    /**
     * Execute a query with parameter binding
     * 
     * @param string $query The SQL query to execute
     * @param array<string, mixed> $params Key-value pairs for parameter binding
     * @return bool True on success, false on failure
     */
    private function executeQuery(string $query, array $params): bool
    {
        try {
            // Connection is created only when this method is called
            $executer = $this->getExecuter();
            
            $executer->query($query);
            
            // Propagate error from QueryExecuter
            if ($executer->getError() !== null) {
                $this->setError($executer->getError());
                return false;
            }
            
            // PERFORMANCE: Bind all parameters in one pass, check errors only at the end
            // This reduces error checking overhead in the loop
            foreach ($params as $key => $value) {
                $executer->bind($key, $value);
            }
            
            // Check for binding errors after all binds (more efficient)
            if ($executer->getError() !== null) {
                $this->setError($executer->getError());
                return false;
            }
            
            $result = $executer->execute();
            
            // Propagate execution errors
            // @phpstan-ignore-next-line
            if (!$result && $executer->getError() !== null) {
                $this->setError($executer->getError());
            }
            
            return $result;
        } catch (\PDOException $e) {
            $this->handleQueryError('Query execution', $e);
            return false;
        }
    }

    /**
     * Handle query errors consistently with retry logic for transient errors
     * 
     * Note: If error is already set by UniversalQueryExecuter (e.g., duplicate entry errors),
     * this method preserves it and only adds logging/context.
     * 
     * @param string $operation The operation that failed
     * @param \PDOException $e The exception that was thrown
     */
    private function handleQueryError(string $operation, \PDOException $e): void
    {
        // If error is already set (e.g., by UniversalQueryExecuter for duplicate entries),
        // preserve it and only add logging
        $existingError = $this->getError();
        if ($existingError !== null && $existingError !== '') {
            // Error already set - just log it
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("PdoQuery::handleQueryError() - Error already set: " . $existingError);
            }
            return;
        }
        
        $context = [
            'operation' => $operation,
            'error_code' => $e->getCode(),
            'sql_state' => $e->errorInfo[0] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Check for transient errors that might be retryable
        $isTransient = $this->isTransientError($e);
        if ($isTransient) {
            $context['retryable'] = true;
            $context['suggestion'] = 'This error might be temporary. Consider retrying the operation.';
        }
        
        $errorMessage = sprintf(
            '%s operation failed: %s (Code: %s)',
            $operation,
            $e->getMessage(),
            $e->getCode()
        );
        
        $this->setError($errorMessage, $context);
        
        // PERFORMANCE: Log errors only in dev mode - removed from production path
        // This eliminates expensive error_log() and stack trace generation on every error
        if (($_ENV['APP_ENV'] ?? '') === 'dev') {
            error_log($errorMessage . "\nStack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Check if an error is transient and potentially retryable
     * 
     * @param \PDOException $e The exception to check
     * @return bool True if the error is transient
     */
    private function isTransientError(\PDOException $e): bool
    {
        $transientCodes = [
            '08000', // Connection exception
            '08003', // Connection does not exist
            '08006', // Connection failure
            '08001', // SQL client unable to establish SQL connection
            '08004', // SQL server rejected establishment of SQL connection
            '08007', // Transaction resolution unknown
            '40001', // Serialization failure
            '40P01', // Deadlock detected
        ];
        
        $errorCode = $e->getCode();
        $sqlState = $e->errorInfo[0] ?? '';
        
        return in_array($sqlState, $transientCodes) || 
               in_array($errorCode, $transientCodes) ||
               stripos($e->getMessage(), 'timeout') !== false ||
               stripos($e->getMessage(), 'connection') !== false ||
               stripos($e->getMessage(), 'deadlock') !== false;
    }

    /**
     * Set error message
     * 
     * @param string|null $error Error message
     * @param array<string, mixed> $context
     */
    public function setError(?string $error, array $context = []): void
    {
        if ($this->executer !== null) {
            $this->executer->setError($error, $context);
        }
    }

    /**
     * Get error message
     * 
     * @return string|null Error message or null if no error
     */
    public function getError(): ?string
    {
        if ($this->executer !== null) {
            return $this->executer->getError();
        }
        return null;
    }

    /**
     * Check if we have an active connection
     * 
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->isConnected && $this->executer !== null;
    }

    /**
     * Force connection cleanup
     */
    public function disconnect(): void
    {
        if ($this->executer !== null) {
            $this->executer->secure();
            $this->executer = null;
            $this->isConnected = false;
        }
    }

    /**
     * Begin a database transaction
     * Connection is created only when this method is called
     * 
     * @return bool True on success, false on failure
     */
    public function beginTransaction(): bool
    {
        return $this->getExecuter()->beginTransaction();
    }

    /**
     * Commit the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        if ($this->executer === null) {
            $this->setError('No active transaction to commit');
            return false;
        }
        return $this->executer->commit();
    }

    /**
     * Rollback the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        if ($this->executer === null) {
            $this->setError('No active transaction to rollback');
            return false;
        }
        return $this->executer->rollback();
    }

    /**
     * Get information about the current database environment
     * 
     * @return array<string, mixed> Environment information
     */
    public function getEnvironmentInfo(): array
    {
        if ($this->executer !== null) {
            return $this->executer->getManagerInfo();
        }
        
        // If no executer yet, get info directly from factory
        return DatabaseManagerFactory::getManagerInfo();
    }

    /**
     * Clear the error message.
     */
    public function clearError(): void
    {
        if ($this->executer !== null) {
            $this->executer->setError(null);
        }
    }

    /**
     * Clean up resources
     */
    public function __destruct()
    {
        if ($this->executer !== null) {
            $this->executer->secure();
            $this->executer = null;
            $this->isConnected = false;
        }
    }
}

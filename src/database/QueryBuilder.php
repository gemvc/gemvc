<?php

namespace Gemvc\Database;

use Gemvc\Database\Query\Delete;
use Gemvc\Database\Query\Insert;
use Gemvc\Database\Query\Select;
use Gemvc\Database\Query\Update;

/**
 * Enhanced Query Builder with improved error handling and integration
 * 
 * Build and run SQL queries with a fluent interface, integrated with our 
 * enhanced Table/PdoQuery architecture for consistent error handling and performance.
 */
class QueryBuilder
{
    /**
     * Stores the last executed query object for error retrieval
     */
    private ?QueryBuilderInterface $lastQuery = null;

    /**
     * Stores any error that occurs during query building or execution
     */
    private ?string $error = null;

    /**
     * PdoQuery instance for database operations with lazy loading
     */
    private ?PdoQuery $pdoQuery = null;

    /**
     * Create a SELECT query with enhanced error handling
     * 
     * **Important:** If validation fails (empty columns or empty column names),
     * an error is set via `setError()` but a safe default query (SELECT *) is still
     * returned to prevent fatal errors. Always check `getError()` after calling
     * this method to ensure validation passed.
     * 
     * **Example:**
     * ```php
     * $query = $builder->select('id', 'name');
     * if ($builder->getError() !== null) {
     *     // Handle validation error
     *     return;
     * }
     * // Proceed with query
     * ```
     * 
     * @param string ...$select Column names to select
     * @return Select Query object for method chaining (may be default SELECT * if validation failed)
     */
    public function select(string ...$select): Select
    {
        $this->clearError();
        
        // Validate parameters
        if (empty($select)) {
            $this->setError("SELECT query must specify at least one column");
            return new Select(['*']); // Return safe default
        }
        
        foreach ($select as $column) {
            if (empty(trim($column))) {
                $this->setError("Column name cannot be empty in SELECT");
                return new Select(['*']); // Return safe default
            }
        }

        $query = new Select($select);
        $query->setQueryBuilder($this);
        return $query;
    }

    /**
     * Create an INSERT query with validation
     * 
     * @param string $intoTableName Table name for insertion
     * @return Insert|null Query object for method chaining, or null if validation fails
     */
    public function insert(string $intoTableName): ?Insert
    {
        $this->clearError();
        
        // Validate table name
        if (empty(trim($intoTableName))) {
            $this->setError("Table name cannot be empty for INSERT");
            return null;
        }

        $query = new Insert($intoTableName);
        $query->setQueryBuilder($this);
        return $query;
    }

    /**
     * Create an UPDATE query with validation
     * 
     * @param string $tableName Table name for update
     * @return Update|null Query object for method chaining, or null if validation fails
     */
    public function update(string $tableName): ?Update
    {
        $this->clearError();
        
        // Validate table name
        if (empty(trim($tableName))) {
            $this->setError("Table name cannot be empty for UPDATE");
            return null;
        }

        $query = new Update($tableName);
        $query->setQueryBuilder($this);
        return $query;
    }

    /**
     * Create a DELETE query with validation
     * 
     * @param string $tableName Table name for deletion
     * @return Delete|null Query object for method chaining, or null if validation fails
     */
    public function delete(string $tableName): ?Delete
    {
        $this->clearError();
        
        // Validate table name
        if (empty(trim($tableName))) {
            $this->setError("Table name cannot be empty for DELETE");
            return null;
        }

        $query = new Delete($tableName);
        $query->setQueryBuilder($this);
        return $query;
    }
    
    /**
     * Get the error from the last executed query or builder operation
     * 
     * @return string|null The error message or null if no error occurred
     */
    public function getError(): ?string
    {
        // First check builder-level errors
        if ($this->error !== null) {
            return $this->error;
        }
        
        // Then check last query errors
        return $this->lastQuery?->getError();
    }
    
    /**
     * Set the last executed query object
     * This should be called by the query objects after execution
     * 
     * @param QueryBuilderInterface $query The query object that was executed
     */
    public function setLastQuery(QueryBuilderInterface $query): void
    {
        $this->lastQuery = $query;
    }

    /**
     * Set an error message at the builder level with optional context
     * 
     * @param string|null $error Error message to set
     * @param array<string, mixed> $context Additional context information
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
     * Clear any existing error
     */
    public function clearError(): void
    {
        $this->error = null;
    }

    /**
     * Get a shared PdoQuery instance for consistent connection management.
     * This provides lazy loading and automatic connection pooling.
     * All connections are intelligently managed by the singleton DatabaseManager.
     * 
     * @return PdoQuery Database query executor
     */
    public function getPdoQuery(): PdoQuery
    {
        if ($this->pdoQuery === null) {
            $this->pdoQuery = new PdoQuery();
        }
        
        // Propagate errors from PdoQuery to QueryBuilder
        if ($this->pdoQuery->getError() !== null) {
            $this->setError($this->pdoQuery->getError());
        }
        
        /** @var PdoQuery */
        return $this->pdoQuery;
    }

    /**
     * Check if the builder has an active database connection
     * 
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->pdoQuery !== null && $this->pdoQuery->isConnected();
    }

    /**
     * Begin a database transaction
     * 
     * @return bool True on success, false on failure
     */
    public function beginTransaction(): bool
    {
        $this->clearError();
        $result = $this->getPdoQuery()->beginTransaction();
        if (!$result) {
            // Propagate error from PdoQuery
            $pdoError = $this->getPdoQuery()->getError();
            $this->setError("Failed to begin transaction" . ($pdoError ? ": " . $pdoError : ''));
        }
        return $result;
    }

    /**
     * Commit the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        $this->clearError();
        
        if (!$this->isConnected()) {
            $this->setError("No active connection to commit transaction");
            return false;
        }
        
        $pdoQuery = $this->getPdoQuery();
        $result = $pdoQuery->commit();
        if (!$result) {
            // Propagate error from PdoQuery
            $pdoError = $pdoQuery->getError();
            $this->setError("Failed to commit transaction" . ($pdoError ? ": " . $pdoError : ''));
        }
        return $result;
    }

    /**
     * Rollback the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        $this->clearError();
        
        if (!$this->isConnected()) {
            $this->setError("No active connection to rollback transaction");
            return false;
        }
        
        $pdoQuery = $this->getPdoQuery();
        $result = $pdoQuery->rollback();
        if (!$result) {
            // Propagate error from PdoQuery
            $pdoError = $pdoQuery->getError();
            $this->setError("Failed to rollback transaction" . ($pdoError ? ": " . $pdoError : ''));
        }
        return $result;
    }

    /**
     * Force connection cleanup
     */
    public function disconnect(): void
    {
        if ($this->pdoQuery !== null) {
            $this->pdoQuery->disconnect();
            $this->pdoQuery = null;
        }
    }

    /**
     * Clean up resources
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}

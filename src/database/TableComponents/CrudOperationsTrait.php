<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

/**
 * CRUD Operations Trait for Table Class
 * 
 * Provides insert, update, and delete operations.
 * Extracted from Table class to follow Single Responsibility Principle.
 * Uses trait for optimal performance (zero delegation overhead, direct method calls).
 */
trait CrudOperationsTrait
{
    /**
     * Inserts a single row into the database table
     * 
     * @return static|null The current instance with inserted id on success, null on error
     */
    public function insertSingleQuery(): ?static
    {
        // PERFORMANCE: Skip validateProperties([]) - empty array means no validation needed
        // This eliminates unnecessary function call overhead
        
        // Auto-generate UUID if needed
        if ($this->isPrimaryKeyAutoGenerate() && $this->getPrimaryKeyType() === 'uuid') {
            $pkValue = $this->getPrimaryKeyValue();
            if ($pkValue === null || $pkValue === '') {
                $this->setPrimaryKeyValue($this->generateUuid());
            }
        }
        
        // PERFORMANCE: Build query and bindings in single pass instead of two iterations
        $columns = [];
        $params = [];
        $arrayBind = [];
        
        // Single iteration over object properties (was done twice before)
        // @phpstan-ignore-next-line
        foreach ($this as $key => $value) {
            if ($key[0] === '_') {
                continue;
            }
            $columns[] = $key;
            $params[] = ':' . $key;
            $arrayBind[':' . $key] = $value;
        }
        
        // Build query string efficiently
        $columnsStr = implode(',', $columns);
        $paramsStr = implode(',', $params);
        $query = "INSERT INTO {$this->getTable()} ({$columnsStr}) VALUES ({$paramsStr})";
        
        // PERFORMANCE: Debug logging only in dev mode - removed from production path
        if (($_ENV['APP_ENV'] ?? '') === 'dev') {
            error_log("Table::insertSingleQuery() - Executing query: " . $query);
            error_log("Table::insertSingleQuery() - With bindings: " . json_encode($arrayBind));
        }
        
        $result = $this->getPdoQuery()->insertQuery($query, $arrayBind);
        
        if ($result === null) {
            // Error message already set by PdoQuery, just add context
            $currentError = $this->getError();
            
            // Enhanced error logging with SQL details
            $errorInfo = [
                'table' => $this->getTable(),
                'query' => $query,
                'bindings' => $arrayBind,
                'error' => $currentError
            ];
            // PERFORMANCE: Error logging only in dev mode
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("Table::insertSingleQuery() - Insert failed with full details: " . json_encode($errorInfo));
            }
            
            $this->setError("Insert failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        
        // Set primary key value after insert
        $pkColumn = $this->getPrimaryKeyColumn();
        $pkType = $this->getPrimaryKeyType();
        
        if ($pkType === 'int' && property_exists($this, $pkColumn)) {
            // For int IDs, use the returned auto-increment value
            $this->$pkColumn = $result;
        }
        // For UUID/string, we already set it before insert (or it was provided)
        
        return $this;
    }

    /**
     * Updates a record based on its ID property
     * 
     * @return static|null Current instance on success, null on error
     */
    public function updateSingleQuery(): ?static
    {
        $pkColumn = $this->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue();
        
        if ($pkValue === null) {
            $this->setError("Primary key '{$pkColumn}' must be set for update");
            return null;
        }
        
        if (!$this->validatePrimaryKey($pkValue, 'update')) {
            return null;
        }
        
        [$query, $arrayBind] = $this->buildUpdateQuery($pkColumn, $pkValue);
        
        $result = $this->getPdoQuery()->updateQuery($query, $arrayBind);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Update failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        
        return $this;
    }

    /**
     * Deletes a record by ID and return id for deleted object
     * 
     * @param int|string $id Record ID to delete
     * @return int|string|null Deleted ID on success, null on error
     */
    public function deleteByIdQuery(int|string $id): int|string|null
    {
        $pkColumn = $this->getPrimaryKeyColumn();
        
        if (!$this->validatePrimaryKey($id, 'delete')) {
            return null;
        }
              
        $query = "DELETE FROM {$this->getTable()} WHERE {$pkColumn} = :{$pkColumn}";
        $result = $this->getPdoQuery()->deleteQuery($query, [":{$pkColumn}" => $id]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Delete failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        return $id;
    }

    /**
     * Removes an object from the database by ID
     * 
     * @return int|null Number of affected rows on success, null on error
     */
    public function deleteSingleQuery(): ?int
    {
        $pkColumn = $this->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue();
        
        if ($pkValue === null) {
            $this->setError("Primary key '{$pkColumn}' must be set for delete");
            return null;
        }
        
        if (!$this->validatePrimaryKey($pkValue, 'delete')) {
            return null;
        }
        
        return $this->removeConditionalQuery($pkColumn, $pkValue);
    }

    /**
     * Builds an UPDATE query with bindings
     * 
     * @param string $idWhereKey Column for WHERE clause
     * @param mixed $idWhereValue Value for WHERE clause
     * @return array{0: string, 1: array<string,mixed>} Query and bindings
     */
    private function buildUpdateQuery(string $idWhereKey, mixed $idWhereValue): array
    {
        $query = "UPDATE {$this->getTable()} SET ";
        $arrayBind = [];          
        
        // @phpstan-ignore-next-line
        foreach ($this as $key => $value) {
            if ($key[0] === '_' || $key === $idWhereKey) {
                continue;
            }
            
            $query .= " {$key} = :{$key},";
            $arrayBind[":{$key}"] = $value;
        }

        $query = rtrim($query, ',');
        $query .= " WHERE {$idWhereKey} = :{$idWhereKey} ";
        $arrayBind[":{$idWhereKey}"] = $idWhereValue;
        
        return [$query, $arrayBind];
    }
}


<?php

namespace Gemvc\Database;

use Gemvc\Database\PdoQuery;

/**
 * Base table class for database operations
 * 
 * Provides a fluent interface for database queries and operations using composition with lazy loading
 */
class Table
{
    /** @var PdoQuery|null Lazy-loaded database query instance */
    private ?PdoQuery $_pdoQuery = null;
    
    /** @var string|null Stored error message before PdoQuery is instantiated */
    private ?string $_storedError = null;

    /** @var string|null SQL query being built */
    private ?string $_query = null;
    
    /** @var bool Whether a SELECT query has been initiated */
    private bool $_isSelectSet = false;
    
    /** @var bool Whether to apply limits to the query */
    private bool $_no_limit = false;
    
    /** @var bool Whether to skip count queries for performance */
    private bool $_skip_count = false;
    
    /** @var array<string,mixed> Query parameter bindings */
    private array $_binds = [];
    
    /** @var int Number of rows per page */
    private int $_limit;
    
    /** @var int Pagination offset */
    private int $_offset = 0;
    
    /** @var string ORDER BY clause */
    private string $_orderBy = '';
    
    /** @var int Total count of rows from last query */
    private int $_total_count = 0;
    
    /** @var int Number of pages from last query */
    private int $_count_pages = 0;
    
    /** @var array<string> WHERE clauses */
    private array $_arr_where = [];
    
    /** @var array<string> JOIN clauses */
    private array $_joins = [];
 
    /** @var array<string, string> Type mapping for property casting */
    protected array $_type_map = [];

    /**
     * Initialize a new Table instance
     * No database connection is created here - lazy loading
     */
    public function __construct()
    {
        $this->_limit = (isset($_ENV['QUERY_LIMIT']) && is_numeric($_ENV['QUERY_LIMIT'])) 
            ? (int)$_ENV['QUERY_LIMIT'] 
            : 10;
    }

    /**
     * Lazy initialization of PdoQuery
     * Database connection is created only when this method is called
     */
    private function getPdoQuery(): PdoQuery
    {
        if ($this->_pdoQuery === null) {
            $this->_pdoQuery = new PdoQuery();
            // Transfer any stored error to the new PdoQuery instance
            if ($this->_storedError !== null) {
                $this->_pdoQuery->setError($this->_storedError);
                $this->_storedError = null;
            }
        }
        return $this->_pdoQuery;
    }

    /**
     * Set error message - optimized to avoid unnecessary connection creation
     */
    public function setError(?string $error): void
    {
        if ($this->_pdoQuery !== null) {
            $this->_pdoQuery->setError($error);
        } else {
            // Store the error until PdoQuery is instantiated
            $this->_storedError = $error;
        }
    }

    /**
     * Get error message
     */
    public function getError(): ?string
    {
        if ($this->_pdoQuery !== null) {
            return $this->_pdoQuery->getError();
        }
        return $this->_storedError;
    }

    /**
     * Check if we have an active connection
     */
    public function isConnected(): bool
    {
        return $this->_pdoQuery !== null && $this->_pdoQuery->isConnected();
    }

    /**
     * Validate essential properties and show error if not valid
     * 
     * @param array<string> $properties Properties to validate
     * @return bool True if all properties exist
     */
    protected function validateProperties(array $properties): bool 
    {
        foreach ($properties as $property) {
            if (!property_exists($this, $property)) {
                $this->setError("Property '{$property}' is not set in table");
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate ID parameter
     * 
     * @param int $id ID to validate
     * @param string $operation Operation name for error message
     * @return bool True if ID is valid
     */
    protected function validateId(int $id, string $operation = 'operation'): bool
    {
        if ($id < 1) {
            $this->setError("ID must be a positive integer for {$operation} in {$this->_internalTable()}");
            return false;
        }
        return true;
    }

    /*
     * =============================================
     * CRUD OPERATIONS
     * =============================================
     */

    /**
     * Inserts a single row into the database table
     * 
     * @return static|null The current instance with inserted id on success, null on error
     */
    public function insertSingleQuery(): ?static
    {
        // PERFORMANCE: Skip validateProperties([]) - empty array means no validation needed
        // This eliminates unnecessary function call overhead
        
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
        $query = "INSERT INTO {$this->_internalTable()} ({$columnsStr}) VALUES ({$paramsStr})";
        
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
                'table' => $this->_internalTable(),
                'query' => $query,
                'bindings' => $arrayBind,
                'error' => $currentError
            ];
            // PERFORMANCE: Error logging only in dev mode
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("Table::insertSingleQuery() - Insert failed with full details: " . json_encode($errorInfo));
            }
            
            $this->setError("Insert failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        if (property_exists($this, 'id')) {
            $this->id = $result;
        }
        return $this;
    }

    /**
     * Updates a record based on its ID property
     * 
     * @return static|null Current instance on success, null on error
     */
    public function updateSingleQuery(): ?static
    {
        if (!property_exists($this, 'id')) {
            $this->setError("Property 'id' does not exist in object");
            return null;
        }
        
        if (!$this->validateId($this->id, 'update')) {
            return null;
        }
        
        [$query, $arrayBind] = $this->buildUpdateQuery('id', $this->id);
        
        $result = $this->getPdoQuery()->updateQuery($query, $arrayBind);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Update failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        return $this;
    }

    /**
     * Deletes a record by ID and return id for deleted object
     * 
     * @param int $id Record ID to delete
     * @return int|null Deleted ID on success, null on error
     */
    public function deleteByIdQuery(int $id): ?int
    {
        if (!property_exists($this, 'id')) {
            $this->setError("Property 'id' does not exist in object");
            return null;
        }
        
        if (!$this->validateId($id, 'delete')) {
            return null;
        }
              
        $query = "DELETE FROM {$this->_internalTable()} WHERE id = :id";
        $result = $this->getPdoQuery()->deleteQuery($query, [':id' => $id]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Delete failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        return $id;
    }

    /**
     * Marks a record as deleted (soft delete)
     * @return static|null Current instance on success, null on error
     */
    public function safeDeleteQuery(): ?static
    {
        if (!property_exists($this, 'id')) {
            $this->setError("Property 'id' does not exist in object");
            return null;
        }
        
        if (!$this->validateId($this->id, 'safe delete')) {
            return null;
        }
        
        if (!$this->validateProperties(['deleted_at'])) {
            $this->setError("For safe delete, deleted_at must exist in the Database table and object");
            return null;
        }
        
        $id = $this->id;
        $query = "UPDATE {$this->_internalTable()} SET deleted_at = NOW() WHERE id = :id";
        
        if (property_exists($this, 'is_active')) {
            $query = "UPDATE {$this->_internalTable()} SET deleted_at = NOW(), is_active = 0 WHERE id = :id";
        }
        
        $result = $this->getPdoQuery()->updateQuery($query, [':id' => $id]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Safe delete failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        if (property_exists($this, 'deleted_at')) {
            $this->deleted_at = date('Y-m-d H:i:s');
        }
        
        // Only set is_active if the property exists
        if (property_exists($this, 'is_active')) {
            $this->is_active = 0;
        }
        
        return $this;
    }

    /**
     * Restores a soft-deleted record
     * 
     * @return static|null Current instance on success, null on error
     */
    public function restoreQuery(): ?static
    {
        if (!property_exists($this, 'id')) {
            $this->setError("Property 'id' does not exist in object");
            return null;
        }
        
        if (!$this->validateId($this->id, 'restore')) {
            return null;
        }
        
        if (!$this->validateProperties(['deleted_at'])) {
            $this->setError("For restore operation, deleted_at must exist in the Database table and object");
            return null;
        }
        
        $id = $this->id;
        $query = "UPDATE {$this->_internalTable()} SET deleted_at = NULL WHERE id = :id";
               
        $result = $this->getPdoQuery()->updateQuery($query, [':id' => $id]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Restore failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        if (property_exists($this, 'deleted_at')) {
            $this->deleted_at = null;
        }       
        return $this;
    }

    /**
     * Removes an object from the database by ID
     * 
     * @return int|null Number of affected rows on success, null on error
     */
    public function deleteSingleQuery(): ?int
    {
        if (!property_exists($this, 'id')) {
            $this->setError("Property 'id' does not exist in object");
            return null;
        }
        
        if (!$this->validateId($this->id, 'delete')) {
            return null;
        }
        
        return $this->removeConditionalQuery('id', $this->id);
    }

    /**
     * Removes records based on conditional WHERE clauses
     * 
     * @param string $whereColumn Primary column for WHERE condition
     * @param mixed $whereValue Value to match in primary column
     * @param string|null $secondWhereColumn Optional second column for WHERE condition
     * @param mixed $secondWhereValue Value to match in second column
     * @return int|null Number of affected rows on success, null on error
     */
    public function removeConditionalQuery(
        string $whereColumn, 
        mixed $whereValue, 
        ?string $secondWhereColumn = null, 
        mixed $secondWhereValue = null
    ): ?int {
        // Validate input parameters
        if (empty($whereColumn)) {
            $this->setError("Where column cannot be empty");
            return null;
        }
        
        if ($whereValue === null || $whereValue === '') {
            $this->setError("Where value cannot be null or empty");
            return null;
        }
        
        $this->validateProperties([]);

        $query = "DELETE FROM {$this->_internalTable()} WHERE {$whereColumn} = :{$whereColumn}";
        $arrayBind = [':' . $whereColumn => $whereValue];
        
        if ($secondWhereColumn !== null) {
            if (empty($secondWhereColumn)) {
                $this->setError("Second where column cannot be empty");
                return null;
            }
            $query .= " AND {$secondWhereColumn} = :{$secondWhereColumn}";
            $arrayBind[':' . $secondWhereColumn] = $secondWhereValue;
        }
        
        $result = $this->getPdoQuery()->deleteQuery($query, $arrayBind);

        if ($result === null) { 
            $currentError = $this->getError();
            $this->setError("Conditional delete failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
       
        return $result;
    }
    
    /*
     * =============================================
     * QUERY BUILDING METHODS - SELECT
     * =============================================
     */

    /**
     * Starts building a SELECT query
     * 
     * @param string|null $columns Columns to select (defaults to *)
     * @return self For method chaining
     */
    public function select(?string $columns = null): self
    {
        if (!$this->_isSelectSet) {
            $this->_query = $columns ? "SELECT $columns " : "SELECT * ";
            $this->_isSelectSet = true;
        } else {
            // If select is called again, append the new columns
            $this->_query .= $columns ? ", $columns" : "";
        }
        return $this;
    }

    /**
     * Adds a JOIN clause to the query
     * 
     * @param string $table Table to join
     * @param string $condition Join condition (ON clause)
     * @param string $type Join type (INNER, LEFT, RIGHT, etc.)
     * @return self For method chaining
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self
    {
        $this->_joins[] = strtoupper($type) . " JOIN $table ON $condition";
        return $this;
    }

    /**
     * Sets the results limit for pagination
     * 
     * @param int $limit Maximum number of rows to return
     * @return self For method chaining
     */
    public function limit(int $limit): self
    {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * Disables pagination limits
     * 
     * @return self For method chaining
     */
    public function noLimit(): self
    {
        $this->_no_limit = true;
        return $this;
    }

    /**
     * Alias for noLimit() - returns all results
     * 
     * @return self For method chaining
     */
    public function all(): self
    {
        $this->_no_limit = true;
        return $this;
    }

    /**
     * Adds an ORDER BY clause to the query
     * 
     * @param string|null $columnName Column to sort by (defaults to 'id')
     * @param bool|null $ascending Whether to sort in ascending order (true) or descending (false/null)
     * @return self For method chaining
     */
    public function orderBy(?string $columnName = null, ?bool $ascending = null): self
    {
        $columnName = $columnName ?: 'id';
        $ascending = $ascending ? ' ASC ' : ' DESC ';
        $this->_orderBy .= " ORDER BY {$columnName}{$ascending}";
        return $this;
    }
    
    /*
     * =============================================
     * WHERE CLAUSES
     * =============================================
     */

    /**
     * Adds a basic WHERE equality condition
     * 
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return self For method chaining
     */
    public function where(string $column, mixed $value): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE clause");
            return $this;
        }
        
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} = :{$column} " 
            : " WHERE {$column} = :{$column} ";
            
        $this->_binds[':' . $column] = $value;
        return $this;
    }

    /**
     * Adds a LIKE condition with wildcard after the value
     * 
     * @param string $column Column name
     * @param string $value Value to match (% will be appended)
     * @return self For method chaining
     */
    public function whereLike(string $column, string $value): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE LIKE clause");
            return $this;
        }
        
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} LIKE :{$column} " 
            : " WHERE {$column} LIKE :{$column} ";
            
        $this->_binds[':' . $column] = $value . '%';
        return $this;
    }

    /**
     * Adds a LIKE condition with wildcard before the value
     * 
     * @param string $column Column name
     * @param string $value Value to match (% will be prepended)
     * @return self For method chaining
     */
    public function whereLikeLast(string $column, string $value): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE LIKE clause");
            return $this;
        }
        
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} LIKE :{$column} " 
            : " WHERE {$column} LIKE :{$column} ";
            
        $this->_binds[':' . $column] = '%' . $value;
        return $this;
    }

    /**
     * Adds a BETWEEN condition
     * 
     * @param string $columnName Column name
     * @param int|string|float $lowerBand Lower bound value
     * @param int|string|float $higherBand Upper bound value
     * @return self For method chaining
     */
    public function whereBetween(
        string $columnName, 
        int|string|float $lowerBand, 
        int|string|float $higherBand
    ): self {
        if (empty($columnName)) {
            $this->setError("Column name cannot be empty in WHERE BETWEEN clause");
            return $this;
        }
        
        $colLower = ':' . $columnName . 'lowerBand';
        $colHigher = ':' . $columnName . 'higherBand';

        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$columnName} BETWEEN {$colLower} AND {$colHigher} " 
            : " WHERE {$columnName} BETWEEN {$colLower} AND {$colHigher} ";
            
        $this->_binds[$colLower] = $lowerBand;
        $this->_binds[$colHigher] = $higherBand;
        return $this;
    }

    /**
     * Adds a WHERE IS NULL condition
     * 
     * @param string $column Column name
     * @return self For method chaining
     */
    public function whereNull(string $column): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE IS NULL clause");
            return $this;
        }
        
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} IS NULL " 
            : " WHERE {$column} IS NULL ";
            
        return $this;
    }

    /**
     * Adds a WHERE IS NOT NULL condition
     * 
     * @param string $column Column name
     * @return self For method chaining
     */
    public function whereNotNull(string $column): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE IS NOT NULL clause");
            return $this;
        }
        
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} IS NOT NULL " 
            : " WHERE {$column} IS NOT NULL ";
            
        return $this;
    }

    /**
     * Adds a WHERE condition using OR operator (if not the first condition)
     * 
     * Note: If this is the first condition in the query, it behaves like a regular WHERE
     * since there's no previous condition to join with OR.
     * 
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return self For method chaining
     */
    public function whereOr(string $column, mixed $value): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE OR clause");
            return $this;
        }
        
        if (count($this->_arr_where) == 0) {
            // If this is the first condition, use WHERE instead of OR
            return $this->where($column, $value);
        }
        
        $paramName = $column . '_or_' . count($this->_arr_where);
        $this->_arr_where[] = " OR {$column} = :{$paramName} ";
        $this->_binds[':' . $paramName] = $value;
        return $this;
    }

    /**
     * Adds a WHERE condition for greater than comparison
     * 
     * @param string $column Column name
     * @param int|float $value Value to compare against
     * @return self For method chaining
     */
    public function whereBiggerThan(string $column, int|float $value): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE > clause");
            return $this;
        }
        
        $paramName = $column . '_gt_' . count($this->_arr_where);
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} > :{$paramName} " 
            : " WHERE {$column} > :{$paramName} ";
            
        $this->_binds[':' . $paramName] = $value;
        return $this;
    }

    /**
     * Adds a WHERE condition for less than comparison
     * 
     * @param string $column Column name
     * @param int|float $value Value to compare against
     * @return self For method chaining
     */
    public function whereLessThan(string $column, int|float $value): self
    {
        if (empty($column)) {
            $this->setError("Column name cannot be empty in WHERE < clause");
            return $this;
        }
        
        $paramName = $column . '_lt_' . count($this->_arr_where);
        $this->_arr_where[] = count($this->_arr_where) 
            ? " AND {$column} < :{$paramName} " 
            : " WHERE {$column} < :{$paramName} ";
            
        $this->_binds[':' . $paramName] = $value;
        return $this;
    }
    
    /**
     * Alias for whereOr() for backward compatibility
     * 
     * @deprecated Use whereOr() instead for clearer semantics
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return self For method chaining
     */
    public function orWhere(string $column, mixed $value): self
    {
        return $this->whereOr($column, $value);
    }
    
    /*
     * =============================================
     * SPECIALIZED UPDATE OPERATIONS
     * =============================================
     */

    /**
     * Sets a column to NULL based on a WHERE condition
     * 
     * @param string $columnNameSetToNull Column to set to NULL
     * @param string $whereColumn WHERE condition column
     * @param mixed $whereValue WHERE condition value
     * @return int|null Number of affected rows on success, null on error
     */
    public function setNullQuery(string $columnNameSetToNull, string $whereColumn, mixed $whereValue): ?int
    {
        // Validate input parameters
        if (empty($columnNameSetToNull)) {
            $this->setError("Column name to set NULL cannot be empty");
            return null;
        }
        
        if (empty($whereColumn)) {
            $this->setError("Where column cannot be empty");
            return null;
        }
        
        if ($whereValue === null || $whereValue === '') {
            $this->setError("Where value cannot be null or empty");
            return null;
        }
        
        $this->validateProperties([]);

        $query = "UPDATE {$this->_internalTable()} SET {$columnNameSetToNull} = NULL WHERE {$whereColumn} = :whereValue";
        $result = $this->getPdoQuery()->updateQuery($query, [':whereValue' => $whereValue]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Set NULL failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        return $result;
    }

    /**
     * Sets a column to current timestamp based on a WHERE condition
     * 
     * @param string $columnNameSetToNowTomeStamp Column to set to NOW()
     * @param string $whereColumn WHERE condition column
     * @param mixed $whereValue WHERE condition value
     * @return int|null Number of affected rows on success, null on error
     */
    public function setTimeNowQuery(string $columnNameSetToNowTomeStamp, string $whereColumn, mixed $whereValue): ?int
    {
        // Validate input parameters
        if (empty($columnNameSetToNowTomeStamp)) {
            $this->setError("Column name to set timestamp cannot be empty");
            return null;
        }
        
        if (empty($whereColumn)) {
            $this->setError("Where column cannot be empty");
            return null;
        }
        
        if ($whereValue === null || $whereValue === '') {
            $this->setError("Where value cannot be null or empty");
            return null;
        }
        
        $this->validateProperties([]);

        $query = "UPDATE {$this->_internalTable()} SET {$columnNameSetToNowTomeStamp} = NOW() WHERE {$whereColumn} = :whereValue";
        $result = $this->getPdoQuery()->updateQuery($query, [':whereValue' => $whereValue]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Set timestamp failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        return $result;
    }

    /**
     * Sets is_active to 1 (activate record)
     * 
     * @param int $id Record ID to activate
     * @return int|null Number of affected rows on success, null on error
     */
    public function activateQuery(int $id): ?int
    {
        if (!$this->validateProperties(['is_active'])) {
            $this->setError('is_active column is not present in the table');
            return null;
        }

        if (!$this->validateId($id, 'activate')) {
            return null;
        }
        
        $result = $this->getPdoQuery()->updateQuery(
            "UPDATE {$this->_internalTable()} SET is_active = 1 WHERE id = :id", 
            [':id' => $id]
        );
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Activate failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        return $result;
    }

    /**
     * Sets is_active to 0 (deactivate record)
     * 
     * @param int $id Record ID to deactivate
     * @return int|null Number of affected rows on success, null on error
     */
    public function deactivateQuery(int $id): ?int
    {
        if (!$this->validateProperties(['is_active'])) {
            $this->setError('is_active column is not present in the table');
            return null;
        }

        if (!$this->validateId($id, 'deactivate')) {
            return null;
        }
        
        $result = $this->getPdoQuery()->updateQuery(
            "UPDATE {$this->_internalTable()} SET is_active = 0 WHERE id = :id", 
            [':id' => $id]
        );
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Deactivate failed in {$this->_internalTable()}: {$currentError}");
            return null;
        }
        
        return $result;
    }
    
    /*
     * =============================================
     * FETCH OPERATIONS
     * =============================================
     */

    /**
     * Selects a single row by ID
     * 
     * @param int $id Record ID to select
     * @return static|null Found instance or null if not found
     */
    public function selectById(int $id): ?static
    {
        if (!$this->validateId($id, 'select')) {
            return null;
        }
        
        $result = $this->select()->where('id', $id)->limit(1)->run();
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError(get_class($this) . ": Select by ID failed: {$currentError}");
            return null;
        }
        
        if (count($result) === 0) {
            $this->setError('Record not found');
            return null;
        }
        
        /** @var static */
        return $result[0];
    }

    /**
     * Executes a SELECT query and returns results
     * 
     * @return array<static>|null Array of model instances on success, null on error
     */
    public function run(): ?array
    {
        $objectName = get_class($this);
        
        if (!$this->_isSelectSet) {
            $this->setError('Before any chain function you shall first use select()');
            return null;
        }

        // Don't check for existing errors here - let the query execute and handle its own errors
        $this->buildCompleteSelectQuery();
        $queryResult = $this->executeSelectQuery();
        
        if ($queryResult === null) {
            // Error already set by executeSelectQuery
            return null;
        }
        
        if (!count($queryResult)) {
            return [];
        }
        
        return $this->hydrateResults($queryResult);
    }
    
    /*
     * =============================================
     * PAGINATION METHODS
     * =============================================
     */

    /**
     * Sets the current page for pagination
     * 
     * @param int $page Page number (1-based)
     * @return void
     */
    public function setPage(int $page): void
    {
        $page = $page < 1 ? 0 : $page - 1;
        $this->_offset = $page * $this->_limit;
    }

    /**
     * Gets the current page number
     * 
     * @return int Current page (1-based)
     */
    public function getCurrentPage(): int
    {
        return $this->_offset + 1;
    }

    /**
     * Gets the number of pages from the last query
     * 
     * @return int Page count
     */
    public function getCount(): int
    {
        return $this->_count_pages;
    }

    /**
     * Gets the total number of records from the last query
     * 
     * @return int Total count
     */
    public function getTotalCounts(): int
    {
        return $this->_total_count;
    }

    /**
     * Gets the current limit per page
     * 
     * @return int Current limit
     */
    public function getLimit(): int
    {
        return $this->_limit;
    }
    
    /*
     * =============================================
     * HELPER METHODS
     * =============================================
     */

    /**
     * Gets the current query string
     * 
     * @return string|null Current query
     */
    public function getQuery(): string|null
    {
        return $this->_query;
    }

    /**
     * Gets the current parameter bindings
     * 
     * @return array<mixed> Current bindings
     */
    public function getBind(): array
    {
        return $this->_binds;
    }

    /**
     * Gets the current SELECT query string
     * 
     * @return string|null Current SELECT query
     */
    public function getSelectQueryString(): string|null
    {
        return $this->_query;
    }

    /**
     * Hydrates model properties from database row
     * 
     * @param array<mixed> $row Database row
     * @return void
     */
    protected function fetchRow(array $row): void
    {
        foreach ($row as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $this->castValue($key, $value);
            }
        }
    }
    
    /**
     * Cast database value to appropriate PHP type
     * 
     * @param string $property Property name
     * @param mixed $value Database value
     * @return mixed Properly typed value
     */
    protected function castValue(string $property, mixed $value): mixed
    {
        if (!isset($this->_type_map[$property])) {
            return $value;
        }
        
        $type = $this->_type_map[$property];
        switch ($type) {
            case 'int':
                return is_numeric($value) ? (int)$value : 0;
            case 'float':
                return is_numeric($value) ? (float)$value : 0.0;
            case 'bool':
                return (bool)$value;
            case 'datetime':
                return new \DateTime(is_string($value) ? $value : 'now');
            default:
                return $value;
        }
    }

    /**
     * Force connection cleanup
     */
    public function disconnect(): void
    {
        if ($this->_pdoQuery !== null) {
            $this->_pdoQuery->disconnect();
            $this->_pdoQuery = null;
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
        return $this->getPdoQuery()->beginTransaction();
    }

    /**
     * Commit the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        if ($this->_pdoQuery === null) {
            $this->setError('No active transaction to commit');
            return false;
        }
        return $this->_pdoQuery->commit();
    }

    /**
     * Rollback the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        if ($this->_pdoQuery === null) {
            $this->setError('No active transaction to rollback');
            return false;
        }
        return $this->_pdoQuery->rollback();
    }

    /**
     * Clean up resources
     */
    public function __destruct()
    {
        if ($this->_pdoQuery !== null) {
            $this->_pdoQuery->disconnect();
            $this->_pdoQuery = null;
        }
    }
    
    /*
     * =============================================
     * PRIVATE HELPER METHODS
     * =============================================
     */
    
    /**
     * Builds a complete WHERE clause from stored conditions
     * 
     * @return string WHERE clause
     */
    private function whereMaker(): string
    {
        if (!count($this->_arr_where)) {
            return ' WHERE 1 ';
        }
        
        $query = ' ';
        
        foreach ($this->_arr_where as $value) {
            $query .= ' ' . $value . ' ';
        }
        
        return trim($query);
    }
    
    // REMOVED: getInsertBindings() and buildInsertQuery() methods
    // These methods became unused after performance optimization 3.1
    // The logic was inlined in insertSingleQuery() for single-pass iteration
    
    /**
     * Builds an UPDATE query with bindings
     * 
     * @param string $idWhereKey Column for WHERE clause
     * @param mixed $idWhereValue Value for WHERE clause
     * @return array{0: string, 1: array<string,mixed>} Query and bindings
     */
    private function buildUpdateQuery(string $idWhereKey, mixed $idWhereValue): array
    {
        $query = "UPDATE {$this->_internalTable()} SET ";
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
    
    /**
     * Builds the complete SELECT query
     * 
     * @return void
     */
    private function buildCompleteSelectQuery(): void
    {
        $joinClause = implode(' ', $this->_joins);
        $whereClause = $this->whereMaker();

        if ($this->_skip_count) {
            $this->_query = $this->_query . 
                "FROM {$this->_internalTable()} $joinClause $whereClause ";
        } else {
            // Avoid duplicate parameter binding by building simple query without subquery
            // The count will be calculated separately if needed
            $this->_query = $this->_query . 
                "FROM {$this->_internalTable()} $joinClause $whereClause ";
        }

        if (!$this->_no_limit) {
            $this->_query .= $this->_orderBy . " LIMIT {$this->_limit} OFFSET {$this->_offset} ";
        } else {
            $this->_query .= $this->_orderBy;
        }

        $this->_query = trim($this->_query);
        $this->_query = preg_replace('/\s+/', ' ', $this->_query);
        
        if (!$this->_query) {
            $this->setError("Given query-string is not acceptable: " . $this->getError());
            return;
        }
    }
    
    /**
     * Executes the SELECT query
     * 
     * @return array<mixed>|null Query results on success, null on error
     */
    private function executeSelectQuery(): ?array
    {
        if (!$this->_query) {
            $this->setError("Query string is empty or invalid");
            return null;
        }
        
        $queryResult = $this->getPdoQuery()->selectQuery($this->_query, $this->_binds);
        
        if ($queryResult === null) {
            $currentError = $this->getError();
            $this->setError("SELECT query failed for " . get_class($this) . ": {$currentError}");
            return null;
        }
        
        return $queryResult;
    }
    
    /**
     * Hydrates model instances from query results
     * 
     * Also calculates total count and page count if skipCount is not used
     * 
     * @param array<mixed> $queryResult Query results
     * @return array<static> Hydrated model instances
     */
    private function hydrateResults(array $queryResult): array
    {
        $object_result = [];
        
        if (!$this->_skip_count && !empty($queryResult)) {
            // Since we removed the subquery, calculate total count with a separate query if needed
            // @phpstan-ignore-next-line
            if (isset($queryResult[0]['_total_count'])) {
                $totalCount = $queryResult[0]['_total_count'];
                $this->_total_count = is_numeric($totalCount) ? (int)$totalCount : 0;
            } else {
                // Calculate total count with separate query to avoid parameter binding issues
                $this->_total_count = count($queryResult);
                
                // If we have a limit and got exactly that many results, there might be more
                if ($this->_limit > 0 && count($queryResult) >= $this->_limit) {
                    // Run a separate count query
                    $countQuery = "SELECT COUNT(*) as total FROM {$this->_internalTable()}" . $this->whereMaker();
                    $countResult = $this->getPdoQuery()->selectQuery($countQuery, $this->_binds);
                    if ($countResult && isset($countResult[0]['total'])) {
                        $this->_total_count = is_numeric($countResult[0]['total']) ? (int)$countResult[0]['total'] : 0;
                    }
                }
            }
            $this->_count_pages = $this->_limit > 0 ? (int) ceil($this->_total_count / $this->_limit) : 1;
        }
        
        foreach ($queryResult as $item) {
            if (!$this->_skip_count && isset($item['_total_count'])) {
                unset($item['_total_count']);
            }
            $instance = new $this();
            if (is_array($item)) {
                $instance->fetchRow($item);
            }
            $object_result[] = $instance;
        }
        
        return $object_result;
    }

    private function _internalTable(): string
    {
        if (!method_exists($this, 'getTable')) {
            throw new \Exception('Method getTable():string must be implemented in child Table class');
        }
        $table_name = $this->getTable();
        if (!is_string($table_name)) {
            throw new \Exception('Method getTable():string must return a string');
        }
        return $table_name;
    }
}

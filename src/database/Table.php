<?php

namespace Gemvc\Database;

use Gemvc\Database\PdoQuery;
use Gemvc\Database\TableComponents\PropertyCaster;
use Gemvc\Database\TableComponents\TableValidator;
use Gemvc\Database\TableComponents\PaginationManager;
use Gemvc\Database\TableComponents\ConnectionManager;
use Gemvc\Database\TableComponents\CrudOperationsTrait;
use Gemvc\Database\TableComponents\SoftDeleteOperationsTrait;
use Gemvc\Http\Request;

/**
 * Base table class for database operations
 * 
 * Provides a fluent interface for database queries and operations using composition with lazy loading
 * 
 * All child classes must implement getTable() method to return the database table name.
 */
abstract class Table
{
    use CrudOperationsTrait;
    use SoftDeleteOperationsTrait;
    
    /** @var ConnectionManager Lazy-loaded connection manager */
    private ?ConnectionManager $_connectionManager = null;

    /** @var Request|null Request object for APM trace context propagation */
    private ?Request $_request = null;

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
    
    /** @var string ORDER BY clause */
    private string $_orderBy = '';
    
    /** @var PaginationManager Lazy-loaded pagination manager */
    private ?PaginationManager $_paginationManager = null;
    
    /** @var array<string> WHERE clauses */
    private array $_arr_where = [];
    
    /** @var array<string> JOIN clauses */
    private array $_joins = [];
 
    /** @var array<string, string> Type mapping for property casting */
    protected array $_type_map = [];
    
    /** @var PropertyCaster|null Lazy-loaded property caster */
    private ?PropertyCaster $_propertyCaster = null;
    
    /** @var TableValidator|null Lazy-loaded table validator */
    private ?TableValidator $_tableValidator = null;

    /** @var array<string, mixed>|null Primary key configuration (set via setPrimaryKey()) */
    private ?array $_primaryKeyConfig = null;
    
    /** @var string|null Detected primary key column name (set in constructor) */
    private ?string $_primaryKeyColumn = null;
    
    /** @var string|null Detected primary key type: 'int', 'string', 'uuid' (set in constructor) */
    private ?string $_primaryKeyType = null;
    
    /** @var bool Whether primary key auto-generates (UUID) */
    private bool $_primaryKeyAutoGenerate = false;
    /**
     * Get the database table name
     * 
     * This method must be implemented by all child classes to return
     * the name of the database table this class represents.
     * 
     * @return string The database table name
     */
    abstract public function getTable(): string;

    /**
     * Initialize a new Table instance
     * No database connection is created here - lazy loading
     */
    public function __construct()
    {
        $defaultLimit = (isset($_ENV['QUERY_LIMIT']) && is_numeric($_ENV['QUERY_LIMIT']))  ? (int)$_ENV['QUERY_LIMIT'] : 10;
        $this->_paginationManager = new PaginationManager($defaultLimit);
        $this->_detectPrimaryKey();
    }

    /**
     * Get or create PropertyCaster instance
     * 
     * @return PropertyCaster The property caster instance
     */
    private function getPropertyCaster(): PropertyCaster
    {
        if ($this->_propertyCaster === null) {
            $this->_propertyCaster = new PropertyCaster($this->_type_map);
        }
        return $this->_propertyCaster;
    }

    /**
     * Get or create TableValidator instance
     * 
     * @return TableValidator The table validator instance
     */
    private function getTableValidator(): TableValidator
    {
        if ($this->_tableValidator === null) {
            $this->_tableValidator = new TableValidator($this);
        }
        return $this->_tableValidator;
    }

    /**
     * Get PaginationManager instance
     * 
     * @return PaginationManager The pagination manager instance
     */
    private function getPaginationManager(): PaginationManager
    {
        // PaginationManager is created in constructor, so it should never be null
        // But we check for safety
        if ($this->_paginationManager === null) {
            $defaultLimit = (isset($_ENV['QUERY_LIMIT']) && is_numeric($_ENV['QUERY_LIMIT']))  ? (int)$_ENV['QUERY_LIMIT'] : 10;
            $this->_paginationManager = new PaginationManager($defaultLimit);
        }
        return $this->_paginationManager;
    }

    /**
     * Set Request object for APM trace context propagation
     * 
     * @param Request|null $request Request object to pass to ConnectionManager
     * @return void
     */
    public function setRequest(?Request $request): void
    {
        $this->_request = $request;
        // If ConnectionManager already exists, update it
        if ($this->_connectionManager !== null) {
            $this->_connectionManager->setRequest($request);
        }
    }

    /**
     * Get or create ConnectionManager instance
     * 
     * @return ConnectionManager The connection manager instance
     */
    private function getConnectionManager(): ConnectionManager
    {
        if ($this->_connectionManager === null) {
            $this->_connectionManager = new ConnectionManager();
            // Set Request if available (for APM trace context propagation)
            if ($this->_request !== null) {
                $this->_connectionManager->setRequest($this->_request);
            }
        }
        return $this->_connectionManager;
    }

    /**
     * Lazy initialization of PdoQuery
     * Database connection is created only when this method is called
     * 
     * Delegates to ConnectionManager for PdoQuery lifecycle management.
     */
    private function getPdoQuery(): PdoQuery
    {
        return $this->getConnectionManager()->getPdoQuery();
    }

    /**
     * Set error message - optimized to avoid unnecessary connection creation
     * 
     * Delegates to ConnectionManager for error handling.
     */
    public function setError(?string $error): void
    {
        $this->getConnectionManager()->setError($error);
    }

    /**
     * Get error message
     * 
     * Delegates to ConnectionManager for error retrieval.
     */
    public function getError(): ?string
    {
        return $this->getConnectionManager()->getError();
    }

    /**
     * Check if we have an active connection
     * 
     * Delegates to ConnectionManager for connection status.
     */
    public function isConnected(): bool
    {
        return $this->getConnectionManager()->isConnected();
    }

    /**
     * Validate essential properties and show error if not valid
     * 
     * Delegates to TableValidator for property validation.
     * 
     * @param array<string> $properties Properties to validate
     * @return bool True if all properties exist
     */
    protected function validateProperties(array $properties): bool
    {
        return $this->getTableValidator()->validateProperties($properties);
    }

    /**
     * Validate ID parameter
     * 
     * @deprecated This method is kept for backward compatibility.
     *             Use validatePrimaryKey() instead for flexible primary key support.
     * 
     * @param int $id ID to validate
     * @param string $operation Operation name for error message
     * @return bool True if ID is valid
     */
    protected function validateId(int $id, string $operation = 'operation'): bool
    {
        // Use validatePrimaryKey() internally to avoid calling deprecated method
        return $this->validatePrimaryKey($id, $operation);
    }

    /*
     * =============================================
     * CRUD OPERATIONS
     * =============================================
     * 
     * CRUD operations are now provided by CrudOperationsTrait
     * Methods: insertSingleQuery(), updateSingleQuery(), deleteByIdQuery(), deleteSingleQuery()
     */

    /**
     * Marks a record as deleted (soft delete)
     * @return static|null Current instance on success, null on error
     */
    // Soft delete operations are now provided by SoftDeleteOperationsTrait
    // Methods: safeDeleteQuery(), restoreQuery(), activateQuery(), deactivateQuery()


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

        $query = "DELETE FROM {$this->getTable()} WHERE {$whereColumn} = :{$whereColumn}";
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
            $this->setError("Conditional delete failed in {$this->getTable()}: {$currentError}");
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
        $this->getPaginationManager()->setLimit($limit);
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
        // Default to primary key column if not specified
        $columnName = $columnName ?: $this->getPrimaryKeyColumn();
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
     * Adds a WHERE condition for equality comparison
     * 
     * This is the preferred method for equality conditions. It's more explicit
     * than the deprecated where() method and aligns with QueryBuilder's WhereTrait.
     * 
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return self For method chaining
     */
    public function whereEqual(string $column, mixed $value): self
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
     * @deprecated Use whereEqual() instead.
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return self For method chaining
     */
    public function where(string $column, mixed $value): self
    {
        return $this->whereEqual($column, $value);
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
            return $this->whereEqual($column, $value);
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

        $query = "UPDATE {$this->getTable()} SET {$columnNameSetToNull} = NULL WHERE {$whereColumn} = :whereValue";
        $result = $this->getPdoQuery()->updateQuery($query, [':whereValue' => $whereValue]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Set NULL failed in {$this->getTable()}: {$currentError}");
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

        $query = "UPDATE {$this->getTable()} SET {$columnNameSetToNowTomeStamp} = NOW() WHERE {$whereColumn} = :whereValue";
        $result = $this->getPdoQuery()->updateQuery($query, [':whereValue' => $whereValue]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Set timestamp failed in {$this->getTable()}: {$currentError}");
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
     * Selects a single row by primary key
     * 
     * @param int|string $id Primary key value to select
     * @return static|null Found instance or null if not found
     */
    public function selectById(int|string $id): ?static
    {
        $pkColumn = $this->getPrimaryKeyColumn();
        
        if (!$this->validatePrimaryKey($id, 'select')) {
            return null;
        }
        
        $result = $this->select()->whereEqual($pkColumn, $id)->limit(1)->run();
        
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
     * Delegates to PaginationManager for page calculation.
     * 
     * @param int $page Page number (1-based)
     * @return void
     */
    public function setPage(int $page): void
    {
        $this->getPaginationManager()->setPage($page);
    }

    /**
     * Gets the current page number
     * 
     * Delegates to PaginationManager for page calculation.
     * 
     * @return int Current page (1-based)
     */
    public function getCurrentPage(): int
    {
        return $this->getPaginationManager()->getCurrentPage();
    }

    /**
     * Gets the number of pages from the last query
     * 
     * Delegates to PaginationManager for page count.
     * 
     * @return int Page count
     */
    public function getCount(): int
    {
        return $this->getPaginationManager()->getCount();
    }

    /**
     * Gets the total number of records from the last query
     * 
     * Delegates to PaginationManager for total count.
     * Used by Controller::createList() for API responses.
     * 
     * @return int Total count
     */
    public function getTotalCounts(): int
    {
        return $this->getPaginationManager()->getTotalCounts();
    }

    /**
     * Gets the current limit per page
     * 
     * Delegates to PaginationManager for limit.
     * 
     * @return int Current limit
     */
    public function getLimit(): int
    {
        return $this->getPaginationManager()->getLimit();
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
    /**
     * Hydrate model properties from database row
     * 
     * Delegates to PropertyCaster for type casting and property assignment.
     * 
     * @param array<string, mixed> $row Database row as associative array
     * @return void
     */
    protected function fetchRow(array $row): void
    {
        $this->getPropertyCaster()->fetchRow($this, $row);
    }
    
    /**
     * Cast database value to appropriate PHP type
     * 
     * Delegates to PropertyCaster for type casting.
     * 
     * @param string $property Property name
     * @param mixed $value Database value
     * @return mixed Properly typed value
     */
    protected function castValue(string $property, mixed $value): mixed
    {
        return $this->getPropertyCaster()->castValue($property, $value);
    }

    /**
     * Force connection cleanup
     * 
     * Delegates to ConnectionManager for connection cleanup.
     */
    public function disconnect(): void
    {
        if ($this->_connectionManager !== null) {
            $this->_connectionManager->disconnect();
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
        $connectionManager = $this->getConnectionManager();
        if (!$connectionManager->hasConnection()) {
            $this->setError('No active transaction to commit');
            return false;
        }
        return $connectionManager->getPdoQuery()->commit();
    }

    /**
     * Rollback the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        $connectionManager = $this->getConnectionManager();
        if (!$connectionManager->hasConnection()) {
            $this->setError('No active transaction to rollback');
            return false;
        }
        return $connectionManager->getPdoQuery()->rollback();
    }

    /**
     * Clean up resources
     */
    public function __destruct()
    {
        if ($this->_connectionManager !== null) {
            $this->_connectionManager->disconnect();
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
    
    // buildUpdateQuery() is now in CrudOperationsTrait
    
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
                "FROM {$this->getTable()} $joinClause $whereClause ";
        } else {
            // Avoid duplicate parameter binding by building simple query without subquery
            // The count will be calculated separately if needed
            $this->_query = $this->_query . 
                "FROM {$this->getTable()} $joinClause $whereClause ";
        }

        if (!$this->_no_limit) {
            $limit = $this->getPaginationManager()->getLimit();
            $offset = $this->getPaginationManager()->getOffset();
            $this->_query .= $this->_orderBy . " LIMIT {$limit} OFFSET {$offset} ";
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
            $pagination = $this->getPaginationManager();
            $limit = $pagination->getLimit();
            
            // Since we removed the subquery, calculate total count with a separate query if needed
            // @phpstan-ignore-next-line
            if (isset($queryResult[0]['_total_count'])) {
                $totalCount = $queryResult[0]['_total_count'];
                $pagination->setTotalCount(is_numeric($totalCount) ? (int)$totalCount : 0);
            } else {
                // Calculate total count with separate query to avoid parameter binding issues
                $totalCount = count($queryResult);
                
                // If we have a limit and got exactly that many results, there might be more
                if ($limit > 0 && count($queryResult) >= $limit) {
                    // Run a separate count query
                    $countQuery = "SELECT COUNT(*) as total FROM {$this->getTable()}" . $this->whereMaker();
                    $countResult = $this->getPdoQuery()->selectQuery($countQuery, $this->_binds);
                    if ($countResult && isset($countResult[0]['total'])) {
                        $totalCount = is_numeric($countResult[0]['total']) ? (int)$countResult[0]['total'] : 0;
                    }
                }
                $pagination->setTotalCount($totalCount);
            }
        }
        
        foreach ($queryResult as $item) {
            if (!$this->_skip_count && isset($item['_total_count'])) {
                unset($item['_total_count']);
            }
            /** @var static $instance */
            $instance = new (static::class)();
            if (is_array($item)) {
                // Use PropertyCaster to hydrate the instance
                /** @var array<string, mixed> $item */
                $this->getPropertyCaster()->fetchRow($instance, $item);
            }
            $object_result[] = $instance;
        }
        
        return $object_result;
    }

    /*
     * =============================================
     * PRIMARY KEY CONFIGURATION
     * =============================================
     */

    /**
     * Detect and set primary key configuration
     * 
     * Logic:
     * 1. If 'id' property exists in child class → use 'id' (int)
     * 2. Else if setPrimaryKey() was called → use configuration
     * 3. Else → default to 'id' (int) for backward compatibility
     * 
     * @return void
     */
    private function _detectPrimaryKey(): void
    {
        // Step 1: Check if 'id' property exists in child class
        if (property_exists($this, 'id')) {
            $this->_primaryKeyColumn = 'id';
            $this->_primaryKeyType = 'int';
            $this->_primaryKeyAutoGenerate = false;
            return;
        }
        
        // Step 2: Check if setPrimaryKey() was called (configuration exists)
        if ($this->_primaryKeyConfig !== null) {
            /** @var array{column: string, type: string, auto_generate: bool} $config */
            $config = $this->_primaryKeyConfig;
            $this->_primaryKeyColumn = $config['column'];
            $this->_primaryKeyType = $config['type'];
            $this->_primaryKeyAutoGenerate = $config['auto_generate'];
            return;
        }
        
        // Step 3: Default to 'id' (int) for backward compatibility
        // Even if property doesn't exist, we default to 'id' for SQL queries
        $this->_primaryKeyColumn = 'id';
        $this->_primaryKeyType = 'int';
        $this->_primaryKeyAutoGenerate = false;
    }

    /**
     * Get primary key column name
     * 
     * @return string Primary key column name
     */
    protected function getPrimaryKeyColumn(): string
    {
        return $this->_primaryKeyColumn ?? 'id';
    }

    /**
     * Get primary key type
     * 
     * @return string Primary key type: 'int', 'string', 'uuid'
     */
    protected function getPrimaryKeyType(): string
    {
        return $this->_primaryKeyType ?? 'int';
    }

    /**
     * Check if primary key auto-generates (UUID)
     * 
     * @return bool True if auto-generates
     */
    protected function isPrimaryKeyAutoGenerate(): bool
    {
        return $this->_primaryKeyAutoGenerate;
    }

    /**
     * Configure primary key column and type
     * 
     * By default, tables use 'id' (int) as primary key. This method allows
     * you to configure a different column name and/or type.
     * 
     * **Types:**
     * - `'int'` - Integer primary key (default, auto-increment)
     * - `'string'` - String primary key (must be set manually)
     * - `'uuid'` - UUID primary key (auto-generated if not set)
     * 
     * **Example:**
     * ```php
     * // Default (no configuration needed)
     * class UserTable extends Table {
     *     public int $id; // Works automatically
     * }
     * 
     * // UUID primary key
     * class ProductTable extends Table {
     *     public string $uuid;
     *     
     *     public function __construct() {
     *         parent::__construct();
     *         $this->setPrimaryKey('uuid', 'uuid'); // Auto-generates UUID
     *     }
     * }
     * ```
     * 
     * @param string $column Column name (default: 'id')
     * @param string $type Type: 'int', 'string', 'uuid' (default: 'int')
     * @return self For method chaining
     */
    public function setPrimaryKey(string $column = 'id', string $type = 'int'): self
    {
        // Validate type
        if (!in_array($type, ['int', 'string', 'uuid'], true)) {
            $this->setError("Invalid primary key type: {$type}. Must be 'int', 'string', or 'uuid'");
            return $this;
        }
        
        // Validate column name
        if (empty(trim($column))) {
            $this->setError("Primary key column name cannot be empty");
            return $this;
        }
        
        $this->_primaryKeyConfig = [
            'column' => $column,
            'type' => $type,
            'auto_generate' => ($type === 'uuid')
        ];
        
        // Update detected values immediately
        $this->_primaryKeyColumn = $column;
        $this->_primaryKeyType = $type;
        $this->_primaryKeyAutoGenerate = ($type === 'uuid');
        
        return $this;
    }

    /**
     * Get primary key configuration
     * Returns default ['id', 'int'] if not configured
     * 
     * @return array{column: string, type: string, auto_generate: bool}
     */
    protected function getPrimaryKeyConfig(): array
    {
        if ($this->_primaryKeyConfig === null) {
            // Default: 'id' (int) - backward compatible
            /** @var array{column: string, type: string, auto_generate: bool} */
            return [
                'column' => 'id',
                'type' => 'int',
                'auto_generate' => false
            ];
        }
        /** @var array{column: string, type: string, auto_generate: bool} */
        return $this->_primaryKeyConfig;
    }

    /**
     * Get primary key value from current object
     * Auto-generates UUID if needed and type is 'uuid'
     * 
     * @return int|string|null Primary key value, or null if property doesn't exist
     */
    protected function getPrimaryKeyValue(): int|string|null
    {
        $column = $this->getPrimaryKeyColumn();
        
        // Check if property exists
        if (!property_exists($this, $column)) {
            return null;
        }
        
        $value = $this->$column;
        
        // Auto-generate UUID if needed
        if ($this->getPrimaryKeyType() === 'uuid' && ($value === null || $value === '')) {
            $value = $this->generateUuid();
            $this->setPrimaryKeyValue($value);
        }
        
        return $value;
    }

    /**
     * Set primary key value on current object
     * 
     * @param int|string $value Primary key value
     * @return void
     */
    protected function setPrimaryKeyValue(int|string $value): void
    {
        $column = $this->getPrimaryKeyColumn();
        
        if (property_exists($this, $column)) {
            $this->$column = $value;
        }
    }

    /**
     * Generate a UUID v4 string
     * 
     * Uses ramsey/uuid if available, otherwise native PHP implementation
     * 
     * @return string UUID v4 string
     */
    protected function generateUuid(): string
    {      
        // Native PHP UUID v4 generation (RFC 4122 compliant)
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Validate primary key value
     * 
     * Delegates to TableValidator for primary key validation.
     * 
     * @param int|string|null $value Primary key value to validate
     * @param string $operation Operation name for error message
     * @return bool True if primary key is valid
     */
    protected function validatePrimaryKey(int|string|null $value, string $operation = 'operation'): bool
    {
        return $this->getTableValidator()->validatePrimaryKey(
            $value,
            $this->getPrimaryKeyColumn(),
            $this->getPrimaryKeyType(),
            $operation
        );
    }
}

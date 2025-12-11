<?php

declare(strict_types=1);
namespace Gemvc\Database\Query;

use Gemvc\Database\PdoQuery;
use Gemvc\Database\QueryBuilderInterface;
use Gemvc\Database\QueryBuilder;
use Gemvc\Database\SqlEnumCondition;

class Select implements QueryBuilderInterface
{
    use LimitTrait;
    use WhereTrait;

    public mixed $result = null;

    public ?string $json = null;

    /**
     * @var array<object>
     */
    public array $object = [];

    public string $query = "";

    /**
     * @var array<mixed>
     */
    public array $arrayBindValues = [];

    /**
     * Store the last error message
     */
    private ?string $_lastError = null;

    /**
     * Reference to the query builder that created this select query
     */
    private ?QueryBuilder $queryBuilder = null;

    /**
     * @var array<mixed>
     */
    private array $fields = [];

    /**
     * @var array<string>
     */
    private array $whereConditions = [];

    /**
     * @var array<string>
     */
    private array $order = [];

    /**
     * @var array<string>
     */
    private array $from = [];

    /**
     * @var array<string>
     */
    private array $innerJoin = [];

    /**
     * @var array<string>
     */
    private array $leftJoin = [];

    /**
     * @param array<mixed> $select
     */
    public function __construct(array $select)
    {
        $this->fields = $select;
    }

    /**
     * Set the query builder reference
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;
        return $this;
    }

    public function __toString(): string
    {
        // Validate FROM clause exists
        if (empty($this->from)) {
            throw new \RuntimeException('SELECT query must have a FROM clause. Call from() method first.');
        }
        
        $this->query = $this->selectMaker() . implode(', ', $this->from)
            . ([] === $this->leftJoin ? '' : ' LEFT JOIN ' . implode(' LEFT JOIN ', $this->leftJoin))
            . ([] === $this->innerJoin ? '' : ' INNER JOIN ' . implode(' INNER JOIN ', $this->innerJoin))
            . ([] === $this->whereConditions ? '' : ' WHERE ' . implode(' AND ', $this->whereConditions))
            . ([] === $this->order ? '' : ' ORDER BY ' . implode(', ', $this->order))
            . $this->limitMaker();
        // echo $this->query;
        return $this->query;
    }

    public function select(string ...$select): self
    {
        foreach ($select as $arg) {
            $this->fields[] = $arg;
        }

        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        // Replace instead of append - only allow one FROM clause
        $this->from = [null === $alias ? $table : "{$table} AS {$alias}"];

        return $this;
    }

    /**
     * Summary of orderBy
     * @param string $columnName
     * @param bool|null $descending
     * @return Select
     * default is ASC if not provided , only DESC when true is provided
     * @example
     * $select->orderBy('name', true); // ORDER BY name DESC
     * $select->orderBy('name', false); // ORDER BY name ASC
     * $select->orderBy('name'); // ORDER BY name ASC
     * @example
     * $select->orderBy('name', true); // ORDER BY name DESC
     * $select->orderBy('name', false); // ORDER BY name ASC
     * $select->orderBy('name'); // ORDER BY name ASC
     */
    public function orderBy(string $columnName, ?bool $descending = null): self
    {
        if ($descending === true) {
            $this->order[] = $columnName . ' DESC';
        } elseif ($descending === false) {
            $this->order[] = $columnName . ' ASC';
        } else {
            // null means default (ASC, but we'll leave as-is for backward compatibility)
            $this->order[] = $columnName;
        }

        return $this;
    }

    /**
     * Add INNER JOIN clause(s) to the query
     * 
     * **Design Note:** This method clears any existing LEFT JOIN clauses.
     * Only one JOIN type (INNER or LEFT) can be used per query. This is an
     * intentional design choice to keep queries simple and consistent.
     * Most queries use a single JOIN type, and mixing types can lead to
     * complex and hard-to-maintain SQL.
     * 
     * If you need both INNER and LEFT JOINs in the same query, consider
     * using raw SQL or restructuring your query logic.
     * 
     * @param string ...$join JOIN clauses (e.g., "table ON condition")
     * @return self For method chaining
     */
    public function innerJoin(string ...$join): self
    {
        $this->leftJoin = [];
        foreach ($join as $arg) {
            $this->innerJoin[] = $arg;
        }

        return $this;
    }

    /**
     * Add LEFT JOIN clause(s) to the query
     * 
     * **Design Note:** This method clears any existing INNER JOIN clauses.
     * Only one JOIN type (INNER or LEFT) can be used per query. This is an
     * intentional design choice to keep queries simple and consistent.
     * Most queries use a single JOIN type, and mixing types can lead to
     * complex and hard-to-maintain SQL.
     * 
     * If you need both INNER and LEFT JOINs in the same query, consider
     * using raw SQL or restructuring your query logic.
     * 
     * @param string ...$join JOIN clauses (e.g., "table ON condition")
     * @return self For method chaining
     */
    public function leftJoin(string ...$join): self
    {
        $this->innerJoin = [];
        foreach ($join as $arg) {
            $this->leftJoin[] = $arg;
        }

        return $this;
    }

    /**
     * Run the select query and return the results
     * Following our unified return pattern: result|null
     * 
     * @return array<mixed>|null Array of results on success, null on error
     */
    public function run(): array|null
    {
        // Validate FROM clause before building query
        if (empty($this->from)) {
            $this->_lastError = 'SELECT query must have a FROM clause. Call from() method first.';
            // Register this query with the builder for error tracking
            if ($this->queryBuilder !== null) {
                $this->queryBuilder->setLastQuery($this);
            }
            return null;
        }
        
        // Use the shared PdoQuery instance from QueryBuilder if available
        $pdoQuery = $this->queryBuilder ? $this->queryBuilder->getPdoQuery() : new PdoQuery();
        
        $query = $this->__toString();
        $result = $pdoQuery->selectQuery($query, $this->arrayBindValues);
        
        if ($result === null) {
            $this->_lastError = $pdoQuery->getError();
            // Register this query with the builder for error tracking
            if ($this->queryBuilder !== null) {
                $this->queryBuilder->setLastQuery($this);
            }
            return null;
        }
        
        // Register this query with the builder for error tracking
        if ($this->queryBuilder !== null) {
            $this->queryBuilder->setLastQuery($this);
        }
        
        return $result;
    }

    /**
     * Get the last error message if any
     */
    public function getError(): ?string
    {
        return $this->_lastError;
    }

    /**
     * Execute query and return results as JSON string
     * 
     * @return string|null JSON string on success, null on error
     */
    public function json(): string|null
    {
        $result = $this->run();
        
        if ($result === null) {
            return null;
        }
        
        $jsonResult = json_encode($result);
        if ($jsonResult === false) {
            $this->_lastError = "Failed to encode results as JSON";
            return null;
        }
        
        return $jsonResult;
    }

    /**
     * @param  PdoQuery $classTable
     * @return array<mixed>
     */
    /**
     * Execute query and return results as an array of stdClass objects
     * 
     * This method executes the SELECT query using the provided PdoQuery instance
     * and converts each row into a stdClass object, providing object-oriented
     * access to query results.
     * 
     * **Example:**
     * ```php
     * $pdoQuery = new PdoQuery();
     * $query = $queryBuilder->select('id', 'name', 'email')
     *     ->from('users')
     *     ->whereEqual('active', 1)
     *     ->object($pdoQuery);
     * 
     * foreach ($query as $user) {
     *     echo $user->name;  // Access as object property
     *     echo $user->email;
     * }
     * ```
     * 
     * **Note:** This method stores results in the `$object` property for later access.
     * If the query fails, an empty array is returned and the error can be retrieved
     * via `getError()`.
     * 
     * **Comparison with `run()`:**
     * - `run()`: Returns raw array of associative arrays
     * - `object()`: Returns array of stdClass objects (more OOP-friendly)
     * 
     * @param PdoQuery $classTable The PdoQuery instance to execute the query
     * @return array<object> Array of stdClass objects representing query results, or empty array on error
     */
    public function object(PdoQuery $classTable): array
    {
        $query = $this->__toString();
        $result = $classTable->selectQuery($query, $this->arrayBindValues);
        if ($result === null) {
            $this->_lastError = $classTable->getError();
            return [];
        }
        foreach ($result as $item) {
            $this->object[] = (object) $item;
        }
        return $this->object;
    }

    private function selectMaker(): string
    {
        if (count($this->fields)) {
            return 'SELECT ' . implode(', ', $this->fields) . ' FROM ';
        }
        return 'SELECT * FROM ';
    }
}

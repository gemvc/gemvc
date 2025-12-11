<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

 namespace Gemvc\Database\Query;

use Gemvc\Database\PdoQuery;
use Gemvc\Database\QueryBuilderInterface;
use Gemvc\Database\QueryBuilder;

class Insert implements QueryBuilderInterface
{
    /**
     * Result of the insert operation (number of affected rows)
     * 
     * @var int|null
     */
    public ?int $result = null;

    private string $_table;

    private string $_query;

    /**
     * Column names for the INSERT statement
     * 
     * @var array<string>
     */
    private array $columns = [];

    /**
     * Parameter placeholders for the INSERT statement
     * 
     * @var array<string>
     */
    private array $binds = [];

    /**
     * Values to be inserted
     * 
     * @var array<mixed>
     */
    private array $values = [];

    /**
     * Key-value pairs for insert operations
     * 
     * @var array<mixed>
     */
    private array $keyValue = [];

    private ?string $_lastError = null;
    
    /**
     * Reference to the query builder that created this insert query
     */
    private ?QueryBuilder $queryBuilder = null;

    public function __construct(string $table)
    {
        $this->_table = $table;
        $this->_query = '';
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
        // Validate that we have columns (binds are set automatically when columns() is called)
        if (empty($this->columns) || empty($this->binds)) {
            throw new \RuntimeException('INSERT query must have columns and values. Call columns() and values() methods first.');
        }
        
        $this->_query = 'INSERT INTO ' . $this->_table
            . ' (' . implode(', ', $this->columns) . ') VALUES (' . implode(', ', $this->binds) . ')';

        return $this->_query;
    }

    public function columns(string ...$columns): self
    {
        $this->columns = $columns;
        foreach ($columns as $column) {
            $this->binds[] = ":{$column}";
        }

        return $this;
    }

    public function values(mixed ...$values): self
    {
        foreach ($values as $arg) {
            $this->values[] = $arg;
        }
        if (\count($this->binds) === \count($this->values)) {
            foreach ($this->binds as $key => $item) {
                $this->keyValue[$item] = $this->values[$key];
            }
        }

        return $this;
    }

    /**
     * Execute the INSERT query and return the inserted ID
     * Following our unified return pattern: result|null
     * 
     * @return int|null Inserted ID on success, null on error
     */
    public function run(): int|null
    {
        // Validate that we have columns and values
        if (empty($this->columns)) {
            $this->_lastError = "No columns specified for INSERT query";
            $this->registerWithBuilder();
            return null;
        }
        
        if (empty($this->keyValue)) {
            $this->_lastError = "No values specified for INSERT query";
            $this->registerWithBuilder();
            return null;
        }

        // Use the shared PdoQuery instance from QueryBuilder if available
        $pdoQuery = $this->queryBuilder ? $this->queryBuilder->getPdoQuery() : new PdoQuery();

        $query = $this->__toString();
        $result = $pdoQuery->insertQuery($query, $this->keyValue);
        
        if ($result === null) {
            $this->_lastError = $pdoQuery->getError();
            $this->registerWithBuilder();
            return null;
        }
        
        $this->registerWithBuilder();
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
     * Get the key-value pairs for testing/debugging
     * 
     * @return array<mixed> The bound key-value pairs
     */
    public function getBindValues(): array
    {
        return $this->keyValue;
    }

    /**
     * Register this query with the builder for error tracking
     */
    private function registerWithBuilder(): void
    {
        if ($this->queryBuilder !== null) {
            $this->queryBuilder->setLastQuery($this);
        }
    }
}

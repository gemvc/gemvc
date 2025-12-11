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

class Update  implements QueryBuilderInterface
{
    use WhereTrait;

    public ?int $result;

    /**
     * @var array<mixed>
     */
    public array $values = [];

    /**
     * @var array<mixed>
     */
    public array $arrayBindValues = [];

    private string $_table;

    private string $_query;

    /**
     * @var array<string>
     */
    private $columns = [];

    /**
     * @var array<string>
     */
    private array $whereConditions = [];
    
    /**
     * Store the last error message
     */
    private ?string $_lastError = null;
    
    /**
     * Reference to the query builder that created this update query
     */
    private ?QueryBuilder $queryBuilder = null;

    public function __construct(string $table)
    {
        $this->_table = $table;
    }

    public function __toString(): string
    {
        // Validate that we have columns to update
        if (empty($this->columns)) {
            throw new \RuntimeException('UPDATE query must have at least one SET clause. Call set() method first.');
        }
        
        $this->_query = 'UPDATE ' . $this->_table . ' SET ' . implode(', ', $this->columns)
            . ([] === $this->whereConditions ? '' : ' WHERE ' . implode(' AND ', $this->whereConditions));

        return $this->_query;
    }

    /**
     * Set a column to be updated with a value
     * 
     * @param string $column Column name to update
     * @param mixed $value New value for the column
     * @return self For method chaining
     */
    public function set(string $column, mixed $value): self
    {
        if (empty(trim($column))) {
            return $this; // Skip invalid columns silently to maintain chain
        }
        
        $colToUpdate = ':' . str_replace('.', '_', $column) . '_ToUpdate';
        $this->columns[] = "{$column} = {$colToUpdate}";
        $this->arrayBindValues[$colToUpdate] = $value;

        return $this;
    }

    /**
     * Set the query builder reference
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;
        return $this;
    }

    /**
     * Execute the UPDATE query and return the number of affected rows
     * Following our unified return pattern: result|null
     * 
     * @return int|null Number of affected rows on success, null on error
     */
    public function run(): int|null
    {
        // Validate that we have columns to update
        if (empty($this->columns)) {
            $this->_lastError = "No columns specified for UPDATE query";
            $this->registerWithBuilder();
            return null;
        }

        // Use the shared PdoQuery instance from QueryBuilder if available
        $pdoQuery = $this->queryBuilder ? $this->queryBuilder->getPdoQuery() : new PdoQuery();
        
        $query = $this->__toString();
        $result = $pdoQuery->updateQuery($query, $this->arrayBindValues);
        
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
     * Register this query with the builder for error tracking
     */
    private function registerWithBuilder(): void
    {
        if ($this->queryBuilder !== null) {
            $this->queryBuilder->setLastQuery($this);
        }
    }
}

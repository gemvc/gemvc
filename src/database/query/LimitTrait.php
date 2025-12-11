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

/**
 * Enhanced LIMIT and OFFSET functionality for SQL queries
 * 
 * Provides modern SQL LIMIT/OFFSET syntax with proper validation
 * and safety features for database compatibility.
 */
trait LimitTrait
{
    /**
     * Maximum value for LIMIT clause when OFFSET is used without LIMIT
     * 
     * This is MySQL's maximum unsigned BIGINT value (2^64 - 1), used to support
     * OFFSET without LIMIT on databases that require a LIMIT clause.
     * 
     * @var int
     */
    private const MAX_LIMIT_FOR_OFFSET = 18446744073709551615;

    /**
     * Maximum number of rows to return
     * 
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * Number of rows to skip
     * 
     * @var int|null
     */
    protected ?int $offset = null;

    /**
     * Set the maximum number of rows to return
     * 
     * @param int $limit Maximum number of rows (must be positive)
     * @return self For method chaining
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            // Skip invalid limits silently to maintain method chaining
            return $this;
        }
        
        if ($limit === 0) {
            // Allow 0 to effectively disable results
            $this->limit = 0;
        } else {
            $this->limit = $limit;
        }

        return $this;
    }

    /**
     * Set the number of rows to skip
     * 
     * @param int $offset Number of rows to skip (must be non-negative)
     * @return self For method chaining
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            // Skip invalid offsets silently to maintain method chaining
            return $this;
        }
        
        $this->offset = $offset;

        return $this;
    }

    /**
     * Get the first N records ordered by specified column
     * 
     * @param int $count Number of records to retrieve (default: 1)
     * @param string $orderByColumn Column to order by (default: 'id')
     * @return self For method chaining
     */
    public function first(int $count = 1, string $orderByColumn = 'id'): self
    {
        if ($count < 0) {
            return $this; // Skip invalid counts
        }
        
        if (empty(trim($orderByColumn))) {
            $orderByColumn = 'id'; // Safe default
        }
        
        // Call orderBy method (guaranteed to exist in classes using this trait)
        $this->orderBy($orderByColumn, false); // ASC for first
        
        $this->limit = $count;
        $this->offset = null; // Reset offset for first()

        return $this;
    }

    /**
     * Get the last N records ordered by specified column
     * 
     * @param int $count Number of records to retrieve (default: 1)
     * @param string $byColumn Column to order by (default: 'id')
     * @return self For method chaining
     */
    public function last(int $count = 1, string $byColumn = 'id'): self
    {
        if ($count < 0) {
            return $this; // Skip invalid counts
        }
        
        if (empty(trim($byColumn))) {
            $byColumn = 'id'; // Safe default
        }
        
        // Call orderBy method (guaranteed to exist in classes using this trait)
        $this->orderBy($byColumn, true); // DESC for last
        
        $this->limit = $count;
        $this->offset = null; // Reset offset for last()

        return $this;
    }

    /**
     * Set pagination parameters
     * 
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return self For method chaining
     */
    public function paginate(int $page, int $perPage): self
    {
        if ($page < 1 || $perPage < 1) {
            return $this; // Skip invalid pagination
        }
        
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        return $this;
    }

    /**
     * Skip the first N records
     * Alias for offset() with more descriptive name
     * 
     * @param int $count Number of records to skip
     * @return self For method chaining
     */
    public function skip(int $count): self
    {
        return $this->offset($count);
    }

    /**
     * Take only N records
     * Alias for limit() with more descriptive name
     * 
     * @param int $count Number of records to take
     * @return self For method chaining
     */
    public function take(int $count): self
    {
        return $this->limit($count);
    }

    /**
     * Generate the LIMIT clause with modern SQL syntax
     * 
     * Uses standard LIMIT ... OFFSET ... syntax for better database compatibility
     * instead of the old MySQL-specific LIMIT offset,limit syntax.
     * 
     * @return string The LIMIT clause string
     */
    private function limitMaker(): string
    {
        $limitQuery = '';
        
        // Handle LIMIT clause
        if (isset($this->limit) && $this->limit >= 0) {
            $limitQuery = ' LIMIT ' . $this->limit;
            
            // Add OFFSET if specified and valid
            if (isset($this->offset) && $this->offset > 0) {
                $limitQuery .= ' OFFSET ' . $this->offset;
            }
        }
        // Handle OFFSET without LIMIT (supported by some databases)
        elseif (isset($this->offset) && $this->offset > 0) {
            // For databases that support OFFSET without LIMIT, use a large number
            // This is more compatible across different database systems
            $limitQuery = ' LIMIT ' . self::MAX_LIMIT_FOR_OFFSET . ' OFFSET ' . $this->offset;
        }
        
        return $limitQuery;
    }

    /**
     * Get current limit value
     * 
     * @return int|null Current limit or null if not set
     */
    public function getLimit(): ?int
    {
        return $this->limit ?? null;
    }

    /**
     * Get current offset value
     * 
     * @return int|null Current offset or null if not set
     */
    public function getOffset(): ?int
    {
        return $this->offset ?? null;
    }

    /**
     * Check if pagination is active
     * 
     * @return bool True if either limit or offset is set
     */
    public function isPaginated(): bool
    {
        return isset($this->limit) || isset($this->offset);
    }

    /**
     * Reset all pagination settings
     * 
     * @return self For method chaining
     */
    public function resetPagination(): self
    {
        $this->limit = null;
        $this->offset = null;
        
        return $this;
    }

    /**
     * Disable pagination limits (return all results)
     * 
     * This method clears both limit and offset, allowing the query to return
     * all matching records without pagination restrictions.
     * 
     * **Example:**
     * ```php
     * $query->from('users')
     *       ->noLimit()
     *       ->run();  // Returns all users
     * ```
     * 
     * @return self For method chaining
     */
    public function noLimit(): self
    {
        $this->limit = null;
        $this->offset = null;
        return $this;
    }

    /**
     * Alias for noLimit() - returns all results
     * 
     * This is a convenience method that provides a more descriptive name
     * for disabling pagination. It's equivalent to calling `noLimit()`.
     * 
     * **Example:**
     * ```php
     * $query->from('users')
     *       ->all()
     *       ->run();  // Returns all users
     * ```
     * 
     * @return self For method chaining
     */
    public function all(): self
    {
        return $this->noLimit();
    }
}

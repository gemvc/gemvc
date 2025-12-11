<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

/**
 * Pagination Manager for Table Class
 * 
 * Handles pagination state and calculations for database queries.
 * Extracted from Table class to follow Single Responsibility Principle.
 */
class PaginationManager
{
    private int $limit;
    private int $offset = 0;
    private int $totalCount = 0;
    private int $countPages = 0;
    
    /**
     * @param int $defaultLimit Default limit per page (from QUERY_LIMIT env or 10)
     */
    public function __construct(int $defaultLimit = 10)
    {
        $this->limit = $defaultLimit;
    }
    
    /**
     * Set the current page for pagination
     * 
     * Converts 1-based page number to 0-based offset.
     * 
     * @param int $page Page number (1-based)
     * @return void
     */
    public function setPage(int $page): void
    {
        $page = $page < 1 ? 0 : $page - 1;
        $this->offset = $page * $this->limit;
    }
    
    /**
     * Get the current page number
     * 
     * Note: This maintains backward compatibility with the original implementation
     * which returned offset + 1. The correct formula would be floor(offset/limit) + 1,
     * but we preserve the original behavior for compatibility.
     * 
     * @return int Current page (1-based)
     */
    public function getCurrentPage(): int
    {
        // Original behavior: offset + 1
        // This matches the original Table::getCurrentPage() implementation
        return $this->offset + 1;
    }
    
    /**
     * Get the number of pages from the last query
     * 
     * @return int Page count
     */
    public function getCount(): int
    {
        return $this->countPages;
    }
    
    /**
     * Get the total number of records from the last query
     * 
     * @return int Total count
     */
    public function getTotalCounts(): int
    {
        return $this->totalCount;
    }
    
    /**
     * Get the current limit per page
     * 
     * @return int Current limit
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
    
    /**
     * Get the current offset
     * 
     * @return int Current offset
     */
    public function getOffset(): int
    {
        return $this->offset;
    }
    
    /**
     * Set the limit per page
     * 
     * @param int $limit Maximum number of rows per page
     * @return void
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
    
    /**
     * Calculate and set total count and page count
     * 
     * @param int $totalCount Total number of records
     * @return void
     */
    public function calculatePages(int $totalCount): void
    {
        $this->totalCount = $totalCount;
        $this->countPages = $this->limit > 0 ? (int)ceil($this->totalCount / $this->limit) : 1;
    }
    
    /**
     * Set total count directly (used when count is calculated externally)
     * 
     * @param int $totalCount Total number of records
     * @return void
     */
    public function setTotalCount(int $totalCount): void
    {
        $this->totalCount = $totalCount;
        $this->countPages = $this->limit > 0 ? (int)ceil($this->totalCount / $this->limit) : 1;
    }
    
    /**
     * Reset pagination state
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->offset = 0;
        $this->totalCount = 0;
        $this->countPages = 0;
    }
}


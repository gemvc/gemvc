<?php

declare(strict_types=1);

namespace Tests\Unit\Database\TableComponents;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\TableComponents\PaginationManager;

class PaginationManagerTest extends TestCase
{
    private PaginationManager $pagination;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->pagination = new PaginationManager(10);
    }
    
    // ==========================================
    // Constructor Tests
    // ==========================================
    
    public function testConstructorWithDefaultLimit(): void
    {
        $pagination = new PaginationManager(10);
        $this->assertEquals(10, $pagination->getLimit());
        $this->assertEquals(0, $pagination->getOffset());
        $this->assertEquals(0, $pagination->getTotalCounts());
        $this->assertEquals(0, $pagination->getCount());
    }
    
    public function testConstructorWithCustomLimit(): void
    {
        $pagination = new PaginationManager(25);
        $this->assertEquals(25, $pagination->getLimit());
    }
    
    // ==========================================
    // setPage Tests
    // ==========================================
    
    public function testSetPage(): void
    {
        $this->pagination->setPage(3);
        
        // setPage(3) sets offset to (3-1) * limit = 2 * 10 = 20
        // getCurrentPage() returns offset + 1 = 20 + 1 = 21
        $this->assertEquals(20, $this->pagination->getOffset());
        $this->assertEquals(21, $this->pagination->getCurrentPage());
    }
    
    public function testSetPageWithZero(): void
    {
        $this->pagination->setPage(0);
        
        // Page should be set to 1 minimum (offset = 0)
        $this->assertEquals(0, $this->pagination->getOffset());
        $this->assertEquals(1, $this->pagination->getCurrentPage());
    }
    
    public function testSetPageWithNegative(): void
    {
        $this->pagination->setPage(-5);
        
        // Page should be set to 1 minimum (offset = 0)
        $this->assertEquals(0, $this->pagination->getOffset());
        $this->assertEquals(1, $this->pagination->getCurrentPage());
    }
    
    public function testSetPageFirstPage(): void
    {
        $this->pagination->setPage(1);
        $this->assertEquals(0, $this->pagination->getOffset());
        $this->assertEquals(1, $this->pagination->getCurrentPage());
    }
    
    public function testSetPageSecondPage(): void
    {
        $this->pagination->setPage(2);
        $this->assertEquals(10, $this->pagination->getOffset());
        // getCurrentPage() returns offset + 1 (backward compatibility)
        $this->assertEquals(11, $this->pagination->getCurrentPage());
    }
    
    // ==========================================
    // getCurrentPage Tests
    // ==========================================
    
    public function testGetCurrentPageDefault(): void
    {
        // Default should be 1 (offset 0 + 1)
        $this->assertEquals(1, $this->pagination->getCurrentPage());
    }
    
    public function testGetCurrentPageAfterSetPage(): void
    {
        $this->pagination->setPage(5);
        // Page 5: offset = (5-1) * 10 = 40, currentPage = 40 + 1 = 41
        $this->assertEquals(41, $this->pagination->getCurrentPage());
    }
    
    // ==========================================
    // getCount Tests
    // ==========================================
    
    public function testGetCountDefault(): void
    {
        // Before any query, should return 0
        $this->assertEquals(0, $this->pagination->getCount());
    }
    
    public function testGetCountAfterSetTotalCount(): void
    {
        $this->pagination->setTotalCount(100);
        // 100 records / 10 per page = 10 pages
        $this->assertEquals(10, $this->pagination->getCount());
    }
    
    public function testGetCountWithCustomLimit(): void
    {
        $this->pagination->setLimit(25);
        $this->pagination->setTotalCount(100);
        // 100 records / 25 per page = 4 pages
        $this->assertEquals(4, $this->pagination->getCount());
    }
    
    public function testGetCountWithZeroLimit(): void
    {
        $this->pagination->setLimit(0);
        $this->pagination->setTotalCount(100);
        // With limit 0, should return 1 page
        $this->assertEquals(1, $this->pagination->getCount());
    }
    
    // ==========================================
    // getTotalCounts Tests
    // ==========================================
    
    public function testGetTotalCountsDefault(): void
    {
        $this->assertEquals(0, $this->pagination->getTotalCounts());
    }
    
    public function testGetTotalCountsAfterSetTotalCount(): void
    {
        $this->pagination->setTotalCount(100);
        $this->assertEquals(100, $this->pagination->getTotalCounts());
    }
    
    // ==========================================
    // getLimit Tests
    // ==========================================
    
    public function testGetLimitDefault(): void
    {
        $this->assertEquals(10, $this->pagination->getLimit());
    }
    
    public function testGetLimitWithCustomValue(): void
    {
        $this->pagination->setLimit(25);
        $this->assertEquals(25, $this->pagination->getLimit());
    }
    
    // ==========================================
    // getOffset Tests
    // ==========================================
    
    public function testGetOffsetDefault(): void
    {
        $this->assertEquals(0, $this->pagination->getOffset());
    }
    
    public function testGetOffsetAfterSetPage(): void
    {
        $this->pagination->setPage(3);
        // Page 3: offset = (3-1) * 10 = 20
        $this->assertEquals(20, $this->pagination->getOffset());
    }
    
    // ==========================================
    // setLimit Tests
    // ==========================================
    
    public function testSetLimit(): void
    {
        $this->pagination->setLimit(50);
        $this->assertEquals(50, $this->pagination->getLimit());
    }
    
    public function testSetLimitWithZero(): void
    {
        $this->pagination->setLimit(0);
        $this->assertEquals(0, $this->pagination->getLimit());
    }
    
    // ==========================================
    // setTotalCount Tests
    // ==========================================
    
    public function testSetTotalCount(): void
    {
        $this->pagination->setTotalCount(100);
        $this->assertEquals(100, $this->pagination->getTotalCounts());
        // Should also calculate page count
        $this->assertEquals(10, $this->pagination->getCount());
    }
    
    public function testSetTotalCountWithZeroLimit(): void
    {
        $this->pagination->setLimit(0);
        $this->pagination->setTotalCount(100);
        $this->assertEquals(1, $this->pagination->getCount());
    }
    
    public function testSetTotalCountWithPartialPage(): void
    {
        $this->pagination->setTotalCount(25);
        // 25 records / 10 per page = 3 pages (ceil)
        $this->assertEquals(3, $this->pagination->getCount());
    }
    
    // ==========================================
    // calculatePages Tests
    // ==========================================
    
    public function testCalculatePages(): void
    {
        $this->pagination->calculatePages(100);
        $this->assertEquals(100, $this->pagination->getTotalCounts());
        $this->assertEquals(10, $this->pagination->getCount());
    }
    
    // ==========================================
    // reset Tests
    // ==========================================
    
    public function testReset(): void
    {
        $this->pagination->setPage(5);
        $this->pagination->setTotalCount(100);
        
        $this->pagination->reset();
        
        $this->assertEquals(0, $this->pagination->getOffset());
        $this->assertEquals(0, $this->pagination->getTotalCounts());
        $this->assertEquals(0, $this->pagination->getCount());
        // Limit should remain unchanged
        $this->assertEquals(10, $this->pagination->getLimit());
    }
    
    // ==========================================
    // Integration Tests
    // ==========================================
    
    public function testPaginationFlow(): void
    {
        // Set up pagination
        $this->pagination->setLimit(20);
        $this->pagination->setPage(3);
        $this->pagination->setTotalCount(100);
        
        // Verify state
        $this->assertEquals(20, $this->pagination->getLimit());
        $this->assertEquals(40, $this->pagination->getOffset()); // (3-1) * 20
        $this->assertEquals(41, $this->pagination->getCurrentPage()); // offset + 1
        $this->assertEquals(100, $this->pagination->getTotalCounts());
        $this->assertEquals(5, $this->pagination->getCount()); // 100 / 20 = 5 pages
    }
}


<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Query\LimitTrait;

/**
 * Test class using LimitTrait for testing
 */
class TestClassWithLimitTrait
{
    use LimitTrait;
    
    public ?int $limit = null;
    public ?int $offset = null;
    public string $orderByColumn = '';
    public bool $orderAsc = true;
    
    public function orderBy(string $column, bool $desc): self
    {
        $this->orderByColumn = $column;
        $this->orderAsc = !$desc; // Trait passes false for ASC, true for DESC
        return $this;
    }
    
    // Expose private method for testing
    public function exposeLimitMaker(): string
    {
        return $this->limitMaker();
    }
}

class LimitTraitTest extends TestCase
{
    private TestClassWithLimitTrait $testObject;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testObject = new TestClassWithLimitTrait();
    }
    
    // ==========================================
    // limit() Method Tests
    // ==========================================
    
    public function testLimitWithPositiveValue(): void
    {
        $result = $this->testObject->limit(10);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertSame($this->testObject, $result); // Fluent interface
        $this->assertEquals(10, $this->testObject->limit);
    }
    
    public function testLimitWithZero(): void
    {
        $result = $this->testObject->limit(0);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(0, $this->testObject->limit);
    }
    
    public function testLimitWithNegativeValue(): void
    {
        $this->testObject->limit = 10; // Set initial value
        $result = $this->testObject->limit(-5);
        
        // Should skip negative values silently
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
    }
    
    public function testLimitWithLargeValue(): void
    {
        $result = $this->testObject->limit(999999);
        
        $this->assertEquals(999999, $this->testObject->limit);
    }
    
    // ==========================================
    // offset() Method Tests
    // ==========================================
    
    public function testOffsetWithPositiveValue(): void
    {
        $result = $this->testObject->offset(20);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertSame($this->testObject, $result);
        $this->assertEquals(20, $this->testObject->offset);
    }
    
    public function testOffsetWithZero(): void
    {
        $result = $this->testObject->offset(0);
        
        $this->assertEquals(0, $this->testObject->offset);
    }
    
    public function testOffsetWithNegativeValue(): void
    {
        $this->testObject->offset = 10; // Set initial value
        $result = $this->testObject->offset(-5);
        
        // Should skip negative values silently
        $this->assertEquals(10, $this->testObject->offset); // Unchanged
    }
    
    // ==========================================
    // first() Method Tests
    // ==========================================
    
    public function testFirstWithDefaultParameters(): void
    {
        $result = $this->testObject->first();
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(1, $this->testObject->limit);
        $this->assertNull($this->testObject->offset); // Reset by first()
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertTrue($this->testObject->orderAsc); // ASC for first
    }
    
    public function testFirstWithCustomCount(): void
    {
        $result = $this->testObject->first(5);
        
        $this->assertEquals(5, $this->testObject->limit);
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertTrue($this->testObject->orderAsc);
    }
    
    public function testFirstWithCustomColumn(): void
    {
        $result = $this->testObject->first(3, 'created_at');
        
        $this->assertEquals(3, $this->testObject->limit);
        $this->assertEquals('created_at', $this->testObject->orderByColumn);
        $this->assertTrue($this->testObject->orderAsc);
    }
    
    public function testFirstWithNegativeCount(): void
    {
        $this->testObject->limit = 10; // Set initial value
        $result = $this->testObject->first(-1);
        
        // Should return self without changes for invalid counts
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
    }
    
    public function testFirstWithEmptyColumn(): void
    {
        $result = $this->testObject->first(5, '');
        
        // Should use 'id' as default for empty column
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertEquals(5, $this->testObject->limit);
    }
    
    public function testFirstWithWhitespaceColumn(): void
    {
        $result = $this->testObject->first(5, '   ');
        
        // Should use 'id' as default for whitespace-only column
        $this->assertEquals('id', $this->testObject->orderByColumn);
    }
    
    public function testFirstResetsOffset(): void
    {
        $this->testObject->offset = 50; // Set initial offset
        $result = $this->testObject->first(10);
        
        // first() should reset offset to null
        $this->assertNull($this->testObject->offset);
    }
    
    // ==========================================
    // last() Method Tests
    // ==========================================
    
    public function testLastWithDefaultParameters(): void
    {
        $result = $this->testObject->last();
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(1, $this->testObject->limit);
        $this->assertNull($this->testObject->offset);
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertFalse($this->testObject->orderAsc); // DESC for last
    }
    
    public function testLastWithCustomCount(): void
    {
        $result = $this->testObject->last(10);
        
        $this->assertEquals(10, $this->testObject->limit);
        $this->assertFalse($this->testObject->orderAsc); // DESC
    }
    
    public function testLastWithCustomColumn(): void
    {
        $result = $this->testObject->last(5, 'updated_at');
        
        $this->assertEquals(5, $this->testObject->limit);
        $this->assertEquals('updated_at', $this->testObject->orderByColumn);
        $this->assertFalse($this->testObject->orderAsc); // DESC
    }
    
    public function testLastWithNegativeCount(): void
    {
        $this->testObject->limit = 10; // Set initial value
        $result = $this->testObject->last(-1);
        
        // Should return self without changes for invalid counts
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
    }
    
    public function testLastWithEmptyColumn(): void
    {
        $result = $this->testObject->last(5, '');
        
        // Should use 'id' as default
        $this->assertEquals('id', $this->testObject->orderByColumn);
    }
    
    public function testLastResetsOffset(): void
    {
        $this->testObject->offset = 50;
        $result = $this->testObject->last(10);
        
        // last() should reset offset to null
        $this->assertNull($this->testObject->offset);
    }
    
    // ==========================================
    // paginate() Method Tests
    // ==========================================
    
    public function testPaginateWithValidParameters(): void
    {
        $result = $this->testObject->paginate(1, 10);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(10, $this->testObject->limit);
        $this->assertEquals(0, $this->testObject->offset); // Page 1: offset = 0
    }
    
    public function testPaginateSecondPage(): void
    {
        $result = $this->testObject->paginate(2, 10);
        
        $this->assertEquals(10, $this->testObject->limit);
        $this->assertEquals(10, $this->testObject->offset); // Page 2: offset = 10
    }
    
    public function testPaginateThirdPage(): void
    {
        $result = $this->testObject->paginate(3, 25);
        
        $this->assertEquals(25, $this->testObject->limit);
        $this->assertEquals(50, $this->testObject->offset); // Page 3: offset = 50
    }
    
    public function testPaginateWithPageZero(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 10;
        
        $result = $this->testObject->paginate(0, 10);
        
        // Should skip invalid page number (< 1)
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
        $this->assertEquals(10, $this->testObject->offset); // Unchanged
    }
    
    public function testPaginateWithNegativePage(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 10;
        
        $result = $this->testObject->paginate(-1, 10);
        
        // Should skip invalid page number
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
        $this->assertEquals(10, $this->testObject->offset); // Unchanged
    }
    
    public function testPaginateWithZeroPerPage(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 10;
        
        $result = $this->testObject->paginate(2, 0);
        
        // Should skip invalid perPage (< 1)
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
        $this->assertEquals(10, $this->testObject->offset); // Unchanged
    }
    
    public function testPaginateWithNegativePerPage(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 10;
        
        $result = $this->testObject->paginate(2, -5);
        
        // Should skip invalid perPage
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
        $this->assertEquals(10, $this->testObject->offset); // Unchanged
    }
    
    // ==========================================
    // skip() Method Tests (Alias for offset)
    // ==========================================
    
    public function testSkipWithPositiveValue(): void
    {
        $result = $this->testObject->skip(15);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(15, $this->testObject->offset);
    }
    
    public function testSkipWithZero(): void
    {
        $result = $this->testObject->skip(0);
        
        $this->assertEquals(0, $this->testObject->offset);
    }
    
    public function testSkipWithNegativeValue(): void
    {
        $this->testObject->offset = 10;
        $result = $this->testObject->skip(-5);
        
        // Should skip negative values
        $this->assertEquals(10, $this->testObject->offset); // Unchanged
    }
    
    // ==========================================
    // take() Method Tests (Alias for limit)
    // ==========================================
    
    public function testTakeWithPositiveValue(): void
    {
        $result = $this->testObject->take(20);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(20, $this->testObject->limit);
    }
    
    public function testTakeWithZero(): void
    {
        $result = $this->testObject->take(0);
        
        $this->assertEquals(0, $this->testObject->limit);
    }
    
    public function testTakeWithNegativeValue(): void
    {
        $this->testObject->limit = 10;
        $result = $this->testObject->take(-5);
        
        // Should skip negative values
        $this->assertEquals(10, $this->testObject->limit); // Unchanged
    }
    
    // ==========================================
    // limitMaker() Method Tests
    // ==========================================
    
    public function testLimitMakerWithOnlyLimit(): void
    {
        $this->testObject->limit = 10;
        
        $result = $this->testObject->exposeLimitMaker();
        
        $this->assertEquals(' LIMIT 10', $result);
    }
    
    public function testLimitMakerWithLimitAndOffset(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 20;
        
        $result = $this->testObject->exposeLimitMaker();
        
        $this->assertEquals(' LIMIT 10 OFFSET 20', $result);
    }
    
    public function testLimitMakerWithOnlyOffset(): void
    {
        $this->testObject->offset = 50;
        
        $result = $this->testObject->exposeLimitMaker();
        
        // Should use large limit with offset
        $this->assertEquals(' LIMIT 18446744073709551615 OFFSET 50', $result);
    }
    
    public function testLimitMakerWithZeroLimit(): void
    {
        $this->testObject->limit = 0;
        
        $result = $this->testObject->exposeLimitMaker();
        
        $this->assertEquals(' LIMIT 0', $result);
    }
    
    public function testLimitMakerWithZeroOffset(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 0;
        
        $result = $this->testObject->exposeLimitMaker();
        
        // Should not include OFFSET 0
        $this->assertEquals(' LIMIT 10', $result);
    }
    
    public function testLimitMakerWithNoLimitOrOffset(): void
    {
        $result = $this->testObject->exposeLimitMaker();
        
        // Should return empty string when neither is set
        $this->assertEquals('', $result);
    }
    
    // ==========================================
    // getLimit() Method Tests
    // ==========================================
    
    public function testGetLimitReturnsSetValue(): void
    {
        $this->testObject->limit = 25;
        
        $result = $this->testObject->getLimit();
        
        $this->assertEquals(25, $result);
    }
    
    public function testGetLimitReturnsNullWhenNotSet(): void
    {
        $result = $this->testObject->getLimit();
        
        $this->assertNull($result);
    }
    
    // ==========================================
    // getOffset() Method Tests
    // ==========================================
    
    public function testGetOffsetReturnsSetValue(): void
    {
        $this->testObject->offset = 40;
        
        $result = $this->testObject->getOffset();
        
        $this->assertEquals(40, $result);
    }
    
    public function testGetOffsetReturnsNullWhenNotSet(): void
    {
        $result = $this->testObject->getOffset();
        
        $this->assertNull($result);
    }
    
    // ==========================================
    // isPaginated() Method Tests
    // ==========================================
    
    public function testIsPaginatedReturnsTrueWithLimit(): void
    {
        $this->testObject->limit = 10;
        
        $result = $this->testObject->isPaginated();
        
        $this->assertTrue($result);
    }
    
    public function testIsPaginatedReturnsTrueWithOffset(): void
    {
        $this->testObject->offset = 20;
        
        $result = $this->testObject->isPaginated();
        
        $this->assertTrue($result);
    }
    
    public function testIsPaginatedReturnsTrueWithBoth(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 20;
        
        $result = $this->testObject->isPaginated();
        
        $this->assertTrue($result);
    }
    
    public function testIsPaginatedReturnsFalseWithNeither(): void
    {
        $result = $this->testObject->isPaginated();
        
        $this->assertFalse($result);
    }
    
    // ==========================================
    // resetPagination() Method Tests
    // ==========================================
    
    public function testResetPaginationClearsLimit(): void
    {
        $this->testObject->limit = 10;
        
        $result = $this->testObject->resetPagination();
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertNull($this->testObject->limit);
    }
    
    public function testResetPaginationClearsOffset(): void
    {
        $this->testObject->offset = 20;
        
        $result = $this->testObject->resetPagination();
        
        $this->assertNull($this->testObject->offset);
    }
    
    public function testResetPaginationClearsBoth(): void
    {
        $this->testObject->limit = 10;
        $this->testObject->offset = 20;
        
        $result = $this->testObject->resetPagination();
        
        $this->assertNull($this->testObject->limit);
        $this->assertNull($this->testObject->offset);
        $this->assertFalse($this->testObject->isPaginated());
    }
    
    public function testResetPaginationFluentInterface(): void
    {
        $this->testObject->limit = 10;
        
        $result = $this->testObject->resetPagination();
        
        $this->assertSame($this->testObject, $result);
    }
    
    // ==========================================
    // Method Chaining Tests
    // ==========================================
    
    public function testMethodChaining(): void
    {
        $result = $this->testObject
            ->limit(10)
            ->offset(20)
            ->take(15)
            ->skip(30);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertSame($this->testObject, $result);
        $this->assertEquals(15, $this->testObject->limit); // Last take() wins
        $this->assertEquals(30, $this->testObject->offset); // Last skip() wins
    }
    
    public function testMethodChainingWithPaginate(): void
    {
        $result = $this->testObject
            ->limit(5)
            ->paginate(3, 20);
        
        $this->assertEquals(20, $this->testObject->limit); // paginate() overrides
        $this->assertEquals(40, $this->testObject->offset); // Page 3 with 20 per page
    }
}


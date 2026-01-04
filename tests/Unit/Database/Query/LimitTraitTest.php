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
    
    // Note: $limit and $offset are now defined as protected in LimitTrait
    // Tests should use getLimit() and getOffset() methods instead of direct property access
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
    
    /**
     * Helper method to set protected limit property
     */
    private function setLimit(int|null $value): void
    {
        $reflection = new \ReflectionClass($this->testObject);
        $property = $reflection->getProperty('limit');
        $property->setValue($this->testObject, $value);
    }
    
    /**
     * Helper method to set protected offset property
     */
    private function setOffset(int|null $value): void
    {
        $reflection = new \ReflectionClass($this->testObject);
        $property = $reflection->getProperty('offset');
        $property->setValue($this->testObject, $value);
    }
    
    // ==========================================
    // limit() Method Tests
    // ==========================================
    
    public function testLimitWithPositiveValue(): void
    {
        $result = $this->testObject->limit(10);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertSame($this->testObject, $result); // Fluent interface
        $this->assertEquals(10, $this->testObject->getLimit());
    }
    
    public function testLimitWithZero(): void
    {
        $result = $this->testObject->limit(0);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(0, $this->testObject->getLimit());
    }
    
    public function testLimitWithNegativeValue(): void
    {
        $this->setLimit(10); // Set initial value
        $result = $this->testObject->limit(-5);
        
        // Should skip negative values silently
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
    }
    
    public function testLimitWithLargeValue(): void
    {
        $result = $this->testObject->limit(999999);
        
        $this->assertEquals(999999, $this->testObject->getLimit());
    }
    
    // ==========================================
    // offset() Method Tests
    // ==========================================
    
    public function testOffsetWithPositiveValue(): void
    {
        $result = $this->testObject->offset(20);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertSame($this->testObject, $result);
        $this->assertEquals(20, $this->testObject->getOffset());
    }
    
    public function testOffsetWithZero(): void
    {
        $result = $this->testObject->offset(0);
        
        $this->assertEquals(0, $this->testObject->getOffset());
    }
    
    public function testOffsetWithNegativeValue(): void
    {
        $this->setOffset(10); // Set initial value
        $result = $this->testObject->offset(-5);
        
        // Should skip negative values silently
        $this->assertEquals(10, $this->testObject->getOffset()); // Unchanged
    }
    
    // ==========================================
    // first() Method Tests
    // ==========================================
    
    public function testFirstWithDefaultParameters(): void
    {
        $result = $this->testObject->first();
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(1, $this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset()); // Reset by first()
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertTrue($this->testObject->orderAsc); // ASC for first
    }
    
    public function testFirstWithCustomCount(): void
    {
        $result = $this->testObject->first(5);
        
        $this->assertEquals(5, $this->testObject->getLimit());
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertTrue($this->testObject->orderAsc);
    }
    
    public function testFirstWithCustomColumn(): void
    {
        $result = $this->testObject->first(3, 'created_at');
        
        $this->assertEquals(3, $this->testObject->getLimit());
        $this->assertEquals('created_at', $this->testObject->orderByColumn);
        $this->assertTrue($this->testObject->orderAsc);
    }
    
    public function testFirstWithNegativeCount(): void
    {
        $this->setLimit(10); // Set initial value
        $result = $this->testObject->first(-1);
        
        // Should return self without changes for invalid counts
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
    }
    
    public function testFirstWithEmptyColumn(): void
    {
        $result = $this->testObject->first(5, '');
        
        // Should use 'id' as default for empty column
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertEquals(5, $this->testObject->getLimit());
    }
    
    public function testFirstWithWhitespaceColumn(): void
    {
        $result = $this->testObject->first(5, '   ');
        
        // Should use 'id' as default for whitespace-only column
        $this->assertEquals('id', $this->testObject->orderByColumn);
    }
    
    public function testFirstResetsOffset(): void
    {
        $this->setOffset(50); // Set initial offset
        $result = $this->testObject->first(10);
        
        // first() should reset offset to null
        $this->assertNull($this->testObject->getOffset());
    }
    
    // ==========================================
    // last() Method Tests
    // ==========================================
    
    public function testLastWithDefaultParameters(): void
    {
        $result = $this->testObject->last();
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(1, $this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset());
        $this->assertEquals('id', $this->testObject->orderByColumn);
        $this->assertFalse($this->testObject->orderAsc); // DESC for last
    }
    
    public function testLastWithCustomCount(): void
    {
        $result = $this->testObject->last(10);
        
        $this->assertEquals(10, $this->testObject->getLimit());
        $this->assertFalse($this->testObject->orderAsc); // DESC
    }
    
    public function testLastWithCustomColumn(): void
    {
        $result = $this->testObject->last(5, 'updated_at');
        
        $this->assertEquals(5, $this->testObject->getLimit());
        $this->assertEquals('updated_at', $this->testObject->orderByColumn);
        $this->assertFalse($this->testObject->orderAsc); // DESC
    }
    
    public function testLastWithNegativeCount(): void
    {
        $this->setLimit(10); // Set initial value
        $result = $this->testObject->last(-1);
        
        // Should return self without changes for invalid counts
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
    }
    
    public function testLastWithEmptyColumn(): void
    {
        $result = $this->testObject->last(5, '');
        
        // Should use 'id' as default
        $this->assertEquals('id', $this->testObject->orderByColumn);
    }
    
    public function testLastResetsOffset(): void
    {
        $this->setOffset(50);
        $result = $this->testObject->last(10);
        
        // last() should reset offset to null
        $this->assertNull($this->testObject->getOffset());
    }
    
    // ==========================================
    // paginate() Method Tests
    // ==========================================
    
    public function testPaginateWithValidParameters(): void
    {
        $result = $this->testObject->paginate(1, 10);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(10, $this->testObject->getLimit());
        $this->assertEquals(0, $this->testObject->getOffset()); // Page 1: offset = 0
    }
    
    public function testPaginateSecondPage(): void
    {
        $result = $this->testObject->paginate(2, 10);
        
        $this->assertEquals(10, $this->testObject->getLimit());
        $this->assertEquals(10, $this->testObject->getOffset()); // Page 2: offset = 10
    }
    
    public function testPaginateThirdPage(): void
    {
        $result = $this->testObject->paginate(3, 25);
        
        $this->assertEquals(25, $this->testObject->getLimit());
        $this->assertEquals(50, $this->testObject->getOffset()); // Page 3: offset = 50
    }
    
    public function testPaginateWithPageZero(): void
    {
        $this->setLimit(10);
        $this->setOffset(10);
        
        $result = $this->testObject->paginate(0, 10);
        
        // Should skip invalid page number (< 1)
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
        $this->assertEquals(10, $this->testObject->getOffset()); // Unchanged
    }
    
    public function testPaginateWithNegativePage(): void
    {
        $this->setLimit(10);
        $this->setOffset(10);
        
        $result = $this->testObject->paginate(-1, 10);
        
        // Should skip invalid page number
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
        $this->assertEquals(10, $this->testObject->getOffset()); // Unchanged
    }
    
    public function testPaginateWithZeroPerPage(): void
    {
        $this->setLimit(10);
        $this->setOffset(10);
        
        $result = $this->testObject->paginate(2, 0);
        
        // Should skip invalid perPage (< 1)
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
        $this->assertEquals(10, $this->testObject->getOffset()); // Unchanged
    }
    
    public function testPaginateWithNegativePerPage(): void
    {
        $this->setLimit(10);
        $this->setOffset(10);
        
        $result = $this->testObject->paginate(2, -5);
        
        // Should skip invalid perPage
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
        $this->assertEquals(10, $this->testObject->getOffset()); // Unchanged
    }
    
    // ==========================================
    // skip() Method Tests (Alias for offset)
    // ==========================================
    
    public function testSkipWithPositiveValue(): void
    {
        $result = $this->testObject->skip(15);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(15, $this->testObject->getOffset());
    }
    
    public function testSkipWithZero(): void
    {
        $result = $this->testObject->skip(0);
        
        $this->assertEquals(0, $this->testObject->getOffset());
    }
    
    public function testSkipWithNegativeValue(): void
    {
        $this->setOffset(10);
        $result = $this->testObject->skip(-5);
        
        // Should skip negative values
        $this->assertEquals(10, $this->testObject->getOffset()); // Unchanged
    }
    
    // ==========================================
    // take() Method Tests (Alias for limit)
    // ==========================================
    
    public function testTakeWithPositiveValue(): void
    {
        $result = $this->testObject->take(20);
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertEquals(20, $this->testObject->getLimit());
    }
    
    public function testTakeWithZero(): void
    {
        $result = $this->testObject->take(0);
        
        $this->assertEquals(0, $this->testObject->getLimit());
    }
    
    public function testTakeWithNegativeValue(): void
    {
        $this->setLimit(10);
        $result = $this->testObject->take(-5);
        
        // Should skip negative values
        $this->assertEquals(10, $this->testObject->getLimit()); // Unchanged
    }
    
    // ==========================================
    // limitMaker() Method Tests
    // ==========================================
    
    public function testLimitMakerWithOnlyLimit(): void
    {
        $this->setLimit(10);
        
        $result = $this->testObject->exposeLimitMaker();
        
        $this->assertEquals(' LIMIT 10', $result);
    }
    
    public function testLimitMakerWithLimitAndOffset(): void
    {
        $this->setLimit(10);
        $this->setOffset(20);
        
        $result = $this->testObject->exposeLimitMaker();
        
        $this->assertEquals(' LIMIT 10 OFFSET 20', $result);
    }
    
    public function testLimitMakerWithOnlyOffset(): void
    {
        $this->setOffset(50);
        
        $result = $this->testObject->exposeLimitMaker();
        
        // Should use PHP_INT_MAX as limit with offset
        // Note: The constant may be converted to scientific notation in string concatenation
        $this->assertStringContainsString('LIMIT', $result);
        $this->assertStringContainsString('OFFSET 50', $result);
        // Verify it uses PHP_INT_MAX (9223372036854775807 on 64-bit systems)
        $this->assertTrue(
            str_contains($result, (string)PHP_INT_MAX) || 
            str_contains($result, '9.2233720368548E+18'),
            'Expected PHP_INT_MAX as LIMIT value'
        );
    }
    
    public function testLimitMakerWithZeroLimit(): void
    {
        $this->setLimit(0);
        
        $result = $this->testObject->exposeLimitMaker();
        
        $this->assertEquals(' LIMIT 0', $result);
    }
    
    public function testLimitMakerWithZeroOffset(): void
    {
        $this->setLimit(10);
        $this->setOffset(0);
        
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
        $this->setLimit(25);
        
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
        $this->setOffset(40);
        
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
        $this->setLimit(10);
        
        $result = $this->testObject->isPaginated();
        
        $this->assertTrue($result);
    }
    
    public function testIsPaginatedReturnsTrueWithOffset(): void
    {
        $this->setOffset(20);
        
        $result = $this->testObject->isPaginated();
        
        $this->assertTrue($result);
    }
    
    public function testIsPaginatedReturnsTrueWithBoth(): void
    {
        $this->setLimit(10);
        $this->setOffset(20);
        
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
        $this->setLimit(10);
        
        $result = $this->testObject->resetPagination();
        
        $this->assertInstanceOf(TestClassWithLimitTrait::class, $result);
        $this->assertNull($this->testObject->getLimit());
    }
    
    public function testResetPaginationClearsOffset(): void
    {
        $this->setOffset(20);
        
        $result = $this->testObject->resetPagination();
        
        $this->assertNull($this->testObject->getOffset());
    }
    
    public function testResetPaginationClearsBoth(): void
    {
        $this->setLimit(10);
        $this->setOffset(20);
        
        $result = $this->testObject->resetPagination();
        
        $this->assertNull($this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset());
        $this->assertFalse($this->testObject->isPaginated());
    }
    
    public function testResetPaginationFluentInterface(): void
    {
        $this->setLimit(10);
        
        $result = $this->testObject->resetPagination();
        
        $this->assertSame($this->testObject, $result);
    }
    
    // ==========================================
    // noLimit() Method Tests
    // ==========================================
    
    public function testNoLimitClearsLimitAndOffset(): void
    {
        $this->setLimit(10);
        $this->setOffset(20);
        
        $result = $this->testObject->noLimit();
        
        $this->assertSame($this->testObject, $result);
        $this->assertNull($this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset());
    }
    
    public function testNoLimitWithNoExistingLimit(): void
    {
        $result = $this->testObject->noLimit();
        
        $this->assertSame($this->testObject, $result);
        $this->assertNull($this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset());
    }
    
    // ==========================================
    // all() Method Tests
    // ==========================================
    
    public function testAllClearsLimitAndOffset(): void
    {
        $this->setLimit(10);
        $this->setOffset(20);
        
        $result = $this->testObject->all();
        
        $this->assertSame($this->testObject, $result);
        $this->assertNull($this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset());
    }
    
    public function testAllIsAliasForNoLimit(): void
    {
        $this->setLimit(10);
        $this->setOffset(20);
        
        $this->testObject->all();
        
        // Should have same effect as noLimit()
        $this->assertNull($this->testObject->getLimit());
        $this->assertNull($this->testObject->getOffset());
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
        $this->assertEquals(15, $this->testObject->getLimit()); // Last take() wins
        $this->assertEquals(30, $this->testObject->getOffset()); // Last skip() wins
    }
    
    public function testMethodChainingWithPaginate(): void
    {
        $result = $this->testObject
            ->limit(5)
            ->paginate(3, 20);
        
        $this->assertEquals(20, $this->testObject->getLimit()); // paginate() overrides
        $this->assertEquals(40, $this->testObject->getOffset()); // Page 3 with 20 per page
    }
}


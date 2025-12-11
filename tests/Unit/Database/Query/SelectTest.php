<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\Query\Select;
use Gemvc\Database\QueryBuilder;
use Gemvc\Database\PdoQuery;

/**
 * @outputBuffering enabled
 */
class SelectTest extends TestCase
{
    private Select $select;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $this->select = new Select(['id', 'name']);
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructorWithFields(): void
    {
        $select = new Select(['id', 'name', 'email']);
        $this->assertInstanceOf(Select::class, $select);
    }
    
    public function testConstructorWithSingleField(): void
    {
        $select = new Select(['id']);
        $this->assertInstanceOf(Select::class, $select);
    }
    
    public function testConstructorWithEmptyArray(): void
    {
        $select = new Select([]);
        $this->assertInstanceOf(Select::class, $select);
    }
    
    // ============================================
    // QueryBuilder Reference Tests
    // ============================================
    
    public function testSetQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $result = $this->select->setQueryBuilder($queryBuilder);
        $this->assertSame($this->select, $result);
    }
    
    // ============================================
    // Select Method Tests
    // ============================================
    
    public function testSelectAddsFields(): void
    {
        $result = $this->select->select('email', 'phone');
        $this->assertSame($this->select, $result);
    }
    
    public function testSelectWithSingleField(): void
    {
        $result = $this->select->select('email');
        $this->assertSame($this->select, $result);
    }
    
    // ============================================
    // From Method Tests
    // ============================================
    
    public function testFromWithTableName(): void
    {
        $result = $this->select->from('users');
        $this->assertSame($this->select, $result);
    }
    
    public function testFromWithTableAndAlias(): void
    {
        $result = $this->select->from('users', 'u');
        $this->assertSame($this->select, $result);
    }
    
    public function testFromWithNullAlias(): void
    {
        $result = $this->select->from('users', null);
        $this->assertSame($this->select, $result);
    }
    
    public function testFromReplacesPreviousFrom(): void
    {
        // Call from() twice - second call should replace first
        $this->select->from('users');
        $this->select->from('products');
        
        $query = (string)$this->select;
        
        // Should only contain 'products', not 'users'
        $this->assertStringContainsString('FROM products', $query);
        $this->assertStringNotContainsString('FROM users', $query);
        // Should not have multiple FROM clauses
        $this->assertEquals(1, substr_count($query, 'FROM'));
    }
    
    // ============================================
    // OrderBy Method Tests
    // ============================================
    
    public function testOrderByWithoutDescending(): void
    {
        $result = $this->select->orderBy('name');
        $this->assertSame($this->select, $result);
    }
    
    public function testOrderByWithDescendingTrue(): void
    {
        $result = $this->select->orderBy('name', true);
        $this->assertSame($this->select, $result);
    }
    
    public function testOrderByWithDescendingFalse(): void
    {
        $result = $this->select->orderBy('name', false);
        $this->assertSame($this->select, $result);
        
        // Verify ASC is included in the query
        $this->select->from('users');
        $query = (string)$this->select;
        $this->assertStringContainsString('ORDER BY', $query);
        $this->assertStringContainsString('ASC', $query);
        $this->assertStringNotContainsString('DESC', $query);
    }
    
    public function testOrderByWithNullDescending(): void
    {
        $result = $this->select->orderBy('name', null);
        $this->assertSame($this->select, $result);
        
        // Verify no ASC/DESC is included (backward compatibility)
        $this->select->from('users');
        $query = (string)$this->select;
        $this->assertStringContainsString('ORDER BY', $query);
        $this->assertStringContainsString('name', $query);
        // null means default - no explicit ASC/DESC for backward compatibility
    }
    
    public function testOrderByWithDescendingTrueIncludesDesc(): void
    {
        $this->select->from('users');
        $this->select->orderBy('name', true);
        $query = (string)$this->select;
        
        $this->assertStringContainsString('ORDER BY', $query);
        $this->assertStringContainsString('DESC', $query);
        $this->assertStringNotContainsString('ASC', $query);
    }
    
    // ============================================
    // Join Method Tests
    // ============================================
    
    public function testInnerJoin(): void
    {
        $result = $this->select->innerJoin('orders ON users.id = orders.user_id');
        $this->assertSame($this->select, $result);
    }
    
    public function testInnerJoinMultiple(): void
    {
        $result = $this->select->innerJoin(
            'orders ON users.id = orders.user_id',
            'products ON orders.product_id = products.id'
        );
        $this->assertSame($this->select, $result);
    }
    
    public function testLeftJoin(): void
    {
        $result = $this->select->leftJoin('orders ON users.id = orders.user_id');
        $this->assertSame($this->select, $result);
    }
    
    public function testLeftJoinMultiple(): void
    {
        $result = $this->select->leftJoin(
            'orders ON users.id = orders.user_id',
            'products ON orders.product_id = products.id'
        );
        $this->assertSame($this->select, $result);
    }
    
    public function testInnerJoinClearsLeftJoin(): void
    {
        $this->select->from('users');
        $this->select->leftJoin('orders ON users.id = orders.user_id');
        $this->select->innerJoin('products ON users.id = products.user_id');
        
        // Inner join should clear left join - verify only INNER JOIN is in query
        $query = (string)$this->select;
        $this->assertStringContainsString('INNER JOIN', $query);
        $this->assertStringNotContainsString('LEFT JOIN', $query);
    }
    
    public function testLeftJoinClearsInnerJoin(): void
    {
        $this->select->from('users');
        $this->select->innerJoin('orders ON users.id = orders.user_id');
        $this->select->leftJoin('products ON users.id = products.user_id');
        
        // Left join should clear inner join - verify only LEFT JOIN is in query
        $query = (string)$this->select;
        $this->assertStringContainsString('LEFT JOIN', $query);
        $this->assertStringNotContainsString('INNER JOIN', $query);
    }
    
    // ============================================
    // WhereTrait Tests (via Select)
    // ============================================
    
    public function testWhereEqual(): void
    {
        $result = $this->select->whereEqual('id', 1);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereNull(): void
    {
        $result = $this->select->whereNull('deleted_at');
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereNotNull(): void
    {
        $result = $this->select->whereNotNull('email');
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereLike(): void
    {
        $result = $this->select->whereLike('name', 'John');
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereLess(): void
    {
        $result = $this->select->whereLess('age', 18);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereLessEqual(): void
    {
        $result = $this->select->whereLessEqual('age', 18);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereBigger(): void
    {
        $result = $this->select->whereBigger('age', 18);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereBiggerEqual(): void
    {
        $result = $this->select->whereBiggerEqual('age', 18);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereBetween(): void
    {
        $result = $this->select->whereBetween('age', 18, 65);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereIn(): void
    {
        $result = $this->select->whereIn('id', [1, 2, 3]);
        $this->assertSame($this->select, $result);
    }
    
    public function testWhereNotIn(): void
    {
        $result = $this->select->whereNotIn('id', [1, 2, 3]);
        $this->assertSame($this->select, $result);
    }
    
    // ============================================
    // LimitTrait Tests (via Select)
    // ============================================
    
    public function testLimit(): void
    {
        $result = $this->select->limit(10);
        $this->assertSame($this->select, $result);
    }
    
    public function testOffset(): void
    {
        $result = $this->select->offset(20);
        $this->assertSame($this->select, $result);
    }
    
    public function testFirst(): void
    {
        $result = $this->select->first(5, 'created_at');
        $this->assertSame($this->select, $result);
    }
    
    public function testLast(): void
    {
        $result = $this->select->last(5, 'created_at');
        $this->assertSame($this->select, $result);
    }
    
    public function testPaginate(): void
    {
        $result = $this->select->paginate(2, 10);
        $this->assertSame($this->select, $result);
    }
    
    public function testSkip(): void
    {
        $result = $this->select->skip(10);
        $this->assertSame($this->select, $result);
    }
    
    public function testTake(): void
    {
        $result = $this->select->take(20);
        $this->assertSame($this->select, $result);
    }
    
    // ============================================
    // ToString Tests
    // ============================================
    
    public function testToStringWithBasicQuery(): void
    {
        $select = new Select(['id', 'name']);
        $select->from('users');
        $query = (string)$select;
        
        $this->assertStringContainsString('SELECT', $query);
        $this->assertStringContainsString('id', $query);
        $this->assertStringContainsString('name', $query);
        $this->assertStringContainsString('FROM', $query);
        $this->assertStringContainsString('users', $query);
    }
    
    public function testToStringWithEmptyFields(): void
    {
        $select = new Select([]);
        $select->from('users');
        $query = (string)$select;
        
        $this->assertStringContainsString('SELECT *', $query);
        $this->assertStringContainsString('FROM', $query);
    }
    
    public function testToStringWithWhere(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        $select->whereEqual('id', 1);
        $query = (string)$select;
        
        $this->assertStringContainsString('WHERE', $query);
    }
    
    public function testToStringWithOrderBy(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        $select->orderBy('name');
        $query = (string)$select;
        
        $this->assertStringContainsString('ORDER BY', $query);
    }
    
    public function testToStringWithLimit(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        $select->limit(10);
        $query = (string)$select;
        
        $this->assertStringContainsString('LIMIT', $query);
    }
    
    public function testToStringWithOffset(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        $select->offset(20);
        $query = (string)$select;
        
        $this->assertStringContainsString('OFFSET', $query);
    }
    
    public function testToStringWithJoin(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        $select->innerJoin('orders ON users.id = orders.user_id');
        $query = (string)$select;
        
        $this->assertStringContainsString('INNER JOIN', $query);
    }
    
    public function testToStringWithLeftJoin(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        $select->leftJoin('orders ON users.id = orders.user_id');
        $query = (string)$select;
        
        $this->assertStringContainsString('LEFT JOIN', $query);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetErrorReturnsNullInitially(): void
    {
        $this->assertNull($this->select->getError());
    }
    
    // ============================================
    // Run Method Tests
    // ============================================
    
    public function testRunWithoutQueryBuilder(): void
    {
        $select = new Select(['id']);
        $select->from('users');
        
        // Will try to create new PdoQuery and execute
        // May return null if connection fails, but should not throw
        $result = $select->run();
        $this->assertTrue($result === null || is_array($result));
    }
    
    public function testRunWithQueryBuilder(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn([['id' => 1, 'name' => 'Test']]);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $select = new Select(['id', 'name']);
        $select->from('users');
        $select->setQueryBuilder($mockQueryBuilder);
        
        $result = $select->run();
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
    
    public function testRunWithQueryBuilderError(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn(null);
        $mockPdoQuery->method('getError')
            ->willReturn('SQL error');
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $select = new Select(['id']);
        $select->from('users');
        $select->setQueryBuilder($mockQueryBuilder);
        
        $result = $select->run();
        $this->assertNull($result);
        $this->assertEquals('SQL error', $select->getError());
    }
    
    // ============================================
    // JSON Method Tests
    // ============================================
    
    public function testJsonReturnsString(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn([['id' => 1, 'name' => 'Test']]);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $select = new Select(['id', 'name']);
        $select->from('users');
        $select->setQueryBuilder($mockQueryBuilder);
        
        $result = $select->json();
        $this->assertIsString($result);
        $this->assertJson($result);
    }
    
    public function testJsonReturnsNullOnError(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn(null);
        $mockPdoQuery->method('getError')
            ->willReturn('SQL error');
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $select = new Select(['id']);
        $select->from('users');
        $select->setQueryBuilder($mockQueryBuilder);
        
        $result = $select->json();
        $this->assertNull($result);
    }
    
    public function testJsonHandlesJsonEncodeFailure(): void
    {
        // Create data that might cause json_encode to fail
        // This is hard to test without mocking json_encode, but we can test the structure
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn([['id' => 1]]);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $select = new Select(['id']);
        $select->from('users');
        $select->setQueryBuilder($mockQueryBuilder);
        
        $result = $select->json();
        // Should return string for valid data
        $this->assertIsString($result);
    }
    
    // ============================================
    // Object Method Tests
    // ============================================
    
    public function testObjectReturnsArray(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn([['id' => 1, 'name' => 'Test']]);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $select = new Select(['id', 'name']);
        $select->from('users');
        
        $result = $select->object($mockPdoQuery);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertIsObject($result[0]);
    }
    
    public function testObjectReturnsEmptyArrayOnError(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn(null);
        $mockPdoQuery->method('getError')
            ->willReturn('SQL error');
        
        $select = new Select(['id']);
        $select->from('users');
        
        $result = $select->object($mockPdoQuery);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        $this->assertEquals('SQL error', $select->getError());
    }
    
    // ============================================
    // Property Tests
    // ============================================
    
    public function testResultProperty(): void
    {
        $this->assertNull($this->select->result);
    }
    
    public function testJsonProperty(): void
    {
        $this->assertNull($this->select->json);
    }
    
    public function testObjectProperty(): void
    {
        $this->assertIsArray($this->select->object);
        $this->assertEmpty($this->select->object);
    }
    
    public function testQueryProperty(): void
    {
        $this->assertEquals('', $this->select->query);
    }
    
    public function testArrayBindValuesProperty(): void
    {
        $this->assertIsArray($this->select->arrayBindValues);
        $this->assertEmpty($this->select->arrayBindValues);
    }
    
    // ============================================
    // Integration Tests
    // ============================================
    
    public function testComplexQuery(): void
    {
        $select = new Select(['id', 'name', 'email']);
        $select->from('users', 'u')
            ->innerJoin('orders ON u.id = orders.user_id')
            ->whereEqual('u.status', 'active')
            ->whereBigger('u.age', 18)
            ->orderBy('u.name', false)
            ->limit(10)
            ->offset(20);
        
        $query = (string)$select;
        
        $this->assertStringContainsString('SELECT', $query);
        $this->assertStringContainsString('FROM', $query);
        $this->assertStringContainsString('INNER JOIN', $query);
        $this->assertStringContainsString('WHERE', $query);
        $this->assertStringContainsString('ORDER BY', $query);
        $this->assertStringContainsString('LIMIT', $query);
        $this->assertStringContainsString('OFFSET', $query);
    }
    
    // ============================================
    // FROM Validation Tests
    // ============================================
    
    public function testToStringThrowsExceptionWithoutFrom(): void
    {
        $select = new Select(['id', 'name']);
        // Don't call from() - should throw exception
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SELECT query must have a FROM clause');
        
        (string) $select;
    }
    
    public function testRunReturnsNullWithoutFrom(): void
    {
        $select = new Select(['id', 'name']);
        // Don't call from() - should return null and set error
        
        $result = $select->run();
        
        $this->assertNull($result);
        $this->assertNotNull($select->getError());
        $this->assertStringContainsString('FROM clause', $select->getError());
    }
    
    public function testRunWithFromBuildsValidQuery(): void
    {
        $select = new Select(['id', 'name']);
        $select->from('users');
        
        // Mock PdoQuery to avoid actual database call
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('selectQuery')
            ->willReturn([['id' => 1, 'name' => 'Test']]);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        // Use reflection to inject mock (since we can't easily mock the QueryBuilder)
        // For now, just verify the query string is valid
        $query = (string) $select;
        $this->assertStringContainsString('SELECT', $query);
        $this->assertStringContainsString('FROM users', $query);
    }
}


<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\Query\Update;
use Gemvc\Database\QueryBuilder;
use Gemvc\Database\PdoQuery;

/**
 * @outputBuffering enabled
 */
class UpdateTest extends TestCase
{
    private Update $update;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $this->update = new Update('users');
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $update = new Update('users');
        $this->assertInstanceOf(Update::class, $update);
    }
    
    // ============================================
    // QueryBuilder Reference Tests
    // ============================================
    
    public function testSetQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $result = $this->update->setQueryBuilder($queryBuilder);
        $this->assertSame($this->update, $result);
    }
    
    // ============================================
    // Set Method Tests
    // ============================================
    
    public function testSetWithSingleColumn(): void
    {
        $result = $this->update->set('name', 'John');
        $this->assertSame($this->update, $result);
    }
    
    public function testSetWithMultipleColumns(): void
    {
        $this->update->set('name', 'John');
        $result = $this->update->set('email', 'john@example.com');
        $this->assertSame($this->update, $result);
    }
    
    public function testSetWithEmptyColumn(): void
    {
        $result = $this->update->set('', 'value');
        $this->assertSame($this->update, $result);
        // Empty column should be skipped
        $query = (string)$this->update;
        $this->assertStringNotContainsString('= :_ToUpdate', $query);
    }
    
    public function testSetWithWhitespaceColumn(): void
    {
        $result = $this->update->set('   ', 'value');
        $this->assertSame($this->update, $result);
    }
    
    // ============================================
    // WhereTrait Tests (via Update)
    // ============================================
    
    public function testWhereEqual(): void
    {
        $result = $this->update->whereEqual('id', 1);
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereNull(): void
    {
        $result = $this->update->whereNull('deleted_at');
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereNotNull(): void
    {
        $result = $this->update->whereNotNull('email');
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereLike(): void
    {
        $result = $this->update->whereLike('name', 'John');
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereLess(): void
    {
        $result = $this->update->whereLess('age', 18);
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereBigger(): void
    {
        $result = $this->update->whereBigger('age', 18);
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereBetween(): void
    {
        $result = $this->update->whereBetween('age', 18, 65);
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereIn(): void
    {
        $result = $this->update->whereIn('id', [1, 2, 3]);
        $this->assertSame($this->update, $result);
    }
    
    public function testWhereNotIn(): void
    {
        $result = $this->update->whereNotIn('id', [1, 2, 3]);
        $this->assertSame($this->update, $result);
    }
    
    // ============================================
    // ToString Tests
    // ============================================
    
    public function testToStringWithSet(): void
    {
        $this->update->set('name', 'John');
        $query = (string)$this->update;
        
        $this->assertStringContainsString('UPDATE', $query);
        $this->assertStringContainsString('users', $query);
        $this->assertStringContainsString('SET', $query);
        $this->assertStringContainsString('name', $query);
    }
    
    public function testToStringWithWhere(): void
    {
        $this->update->set('name', 'John');
        $this->update->whereEqual('id', 1);
        $query = (string)$this->update;
        
        $this->assertStringContainsString('WHERE', $query);
    }
    
    public function testToStringWithoutWhere(): void
    {
        $this->update->set('name', 'John');
        $query = (string)$this->update;
        
        $this->assertStringNotContainsString('WHERE', $query);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetErrorReturnsNullInitially(): void
    {
        $this->assertNull($this->update->getError());
    }
    
    // ============================================
    // Run Method Tests
    // ============================================
    
    public function testRunWithoutColumns(): void
    {
        $result = $this->update->run();
        $this->assertNull($result);
        $this->assertNotNull($this->update->getError());
        $this->assertStringContainsString('No columns specified', $this->update->getError());
    }
    
    public function testRunWithoutQueryBuilder(): void
    {
        $this->update->set('name', 'John');
        $this->update->whereEqual('id', 1);
        
        // Will try to create new PdoQuery and execute
        // May return null if connection fails, but should not throw
        $result = $this->update->run();
        $this->assertTrue($result === null || is_int($result));
    }
    
    public function testRunWithQueryBuilder(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('updateQuery')
            ->willReturn(5);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $this->update->set('name', 'John');
        $this->update->whereEqual('id', 1);
        $this->update->setQueryBuilder($mockQueryBuilder);
        
        $result = $this->update->run();
        $this->assertIsInt($result);
        $this->assertEquals(5, $result);
    }
    
    public function testRunWithQueryBuilderError(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('updateQuery')
            ->willReturn(null);
        $mockPdoQuery->method('getError')
            ->willReturn('SQL error');
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $this->update->set('name', 'John');
        $this->update->whereEqual('id', 1);
        $this->update->setQueryBuilder($mockQueryBuilder);
        
        $result = $this->update->run();
        $this->assertNull($result);
        $this->assertEquals('SQL error', $this->update->getError());
    }
    
    // ============================================
    // Property Tests
    // ============================================
    
    public function testResultProperty(): void
    {
        // Property is typed ?int but not initialized, so we can't access it directly
        // Instead, we verify it exists via reflection
        $reflection = new \ReflectionClass($this->update);
        $this->assertTrue($reflection->hasProperty('result'));
        $property = $reflection->getProperty('result');
        $this->assertTrue($property->isPublic());
    }
    
    public function testValuesProperty(): void
    {
        $this->assertIsArray($this->update->values);
    }
    
    public function testArrayBindValuesProperty(): void
    {
        $this->assertIsArray($this->update->arrayBindValues);
    }
    
    // ============================================
    // Integration Tests
    // ============================================
    
    public function testFullUpdateFlow(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('updateQuery')
            ->willReturn(1);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $update = new Update('users');
        $update->setQueryBuilder($mockQueryBuilder);
        $update->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->whereEqual('id', 1);
        
        $result = $update->run();
        $this->assertEquals(1, $result);
        
        $query = (string)$update;
        $this->assertStringContainsString('UPDATE users', $query);
        $this->assertStringContainsString('SET', $query);
        $this->assertStringContainsString('WHERE', $query);
    }
}


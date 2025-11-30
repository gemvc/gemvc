<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\Query\Delete;
use Gemvc\Database\QueryBuilder;
use Gemvc\Database\PdoQuery;

/**
 * @outputBuffering enabled
 */
class DeleteTest extends TestCase
{
    private Delete $delete;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $this->delete = new Delete('users');
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $delete = new Delete('users');
        $this->assertInstanceOf(Delete::class, $delete);
    }
    
    // ============================================
    // QueryBuilder Reference Tests
    // ============================================
    
    public function testSetQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $result = $this->delete->setQueryBuilder($queryBuilder);
        $this->assertSame($this->delete, $result);
    }
    
    // ============================================
    // WhereTrait Tests (via Delete)
    // ============================================
    
    public function testWhereEqual(): void
    {
        $result = $this->delete->whereEqual('id', 1);
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereNull(): void
    {
        $result = $this->delete->whereNull('deleted_at');
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereNotNull(): void
    {
        $result = $this->delete->whereNotNull('email');
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereLike(): void
    {
        $result = $this->delete->whereLike('name', 'John');
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereLess(): void
    {
        $result = $this->delete->whereLess('age', 18);
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereBigger(): void
    {
        $result = $this->delete->whereBigger('age', 18);
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereBetween(): void
    {
        $result = $this->delete->whereBetween('age', 18, 65);
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereIn(): void
    {
        $result = $this->delete->whereIn('id', [1, 2, 3]);
        $this->assertSame($this->delete, $result);
    }
    
    public function testWhereNotIn(): void
    {
        $result = $this->delete->whereNotIn('id', [1, 2, 3]);
        $this->assertSame($this->delete, $result);
    }
    
    // ============================================
    // ToString Tests
    // ============================================
    
    public function testToStringWithoutWhere(): void
    {
        $query = (string)$this->delete;
        
        $this->assertStringContainsString('DELETE FROM', $query);
        $this->assertStringContainsString('users', $query);
        // Should have WHERE 1=0 for safety
        $this->assertStringContainsString('WHERE', $query);
        $this->assertStringContainsString('1=0', $query);
    }
    
    public function testToStringWithWhere(): void
    {
        $this->delete->whereEqual('id', 1);
        $query = (string)$this->delete;
        
        $this->assertStringContainsString('DELETE FROM', $query);
        $this->assertStringContainsString('users', $query);
        $this->assertStringContainsString('WHERE', $query);
        $this->assertStringNotContainsString('1=0', $query);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetErrorReturnsNullInitially(): void
    {
        $this->assertNull($this->delete->getError());
    }
    
    // ============================================
    // Run Method Tests
    // ============================================
    
    public function testRunWithoutWhere(): void
    {
        $result = $this->delete->run();
        $this->assertNull($result);
        $this->assertNotNull($this->delete->getError());
        $this->assertStringContainsString('must have WHERE conditions', $this->delete->getError());
    }
    
    public function testRunWithoutQueryBuilder(): void
    {
        $this->delete->whereEqual('id', 1);
        
        // Will try to create new PdoQuery and execute
        // May return null if connection fails, but should not throw
        $result = $this->delete->run();
        $this->assertTrue($result === null || is_int($result));
    }
    
    public function testRunWithQueryBuilder(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('deleteQuery')
            ->willReturn(1);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $this->delete->whereEqual('id', 1);
        $this->delete->setQueryBuilder($mockQueryBuilder);
        
        $result = $this->delete->run();
        $this->assertIsInt($result);
        $this->assertEquals(1, $result);
    }
    
    public function testRunWithQueryBuilderError(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('deleteQuery')
            ->willReturn(null);
        $mockPdoQuery->method('getError')
            ->willReturn('SQL error');
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $this->delete->whereEqual('id', 1);
        $this->delete->setQueryBuilder($mockQueryBuilder);
        
        $result = $this->delete->run();
        $this->assertNull($result);
        $this->assertEquals('SQL error', $this->delete->getError());
    }
    
    // ============================================
    // Property Tests
    // ============================================
    
    public function testResultProperty(): void
    {
        // Property is typed ?int but not initialized, so we can't access it directly
        // Instead, we verify it exists via reflection
        $reflection = new \ReflectionClass($this->delete);
        $this->assertTrue($reflection->hasProperty('result'));
        $property = $reflection->getProperty('result');
        $this->assertTrue($property->isPublic());
    }
    
    public function testQueryProperty(): void
    {
        // Property is typed string but not initialized, so we can't access it directly
        // Instead, we verify it exists via reflection
        $reflection = new \ReflectionClass($this->delete);
        $this->assertTrue($reflection->hasProperty('query'));
        $property = $reflection->getProperty('query');
        $this->assertTrue($property->isPublic());
    }
    
    public function testArrayBindValuesProperty(): void
    {
        $this->assertIsArray($this->delete->arrayBindValues);
    }
    
    // ============================================
    // Integration Tests
    // ============================================
    
    public function testFullDeleteFlow(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('deleteQuery')
            ->willReturn(1);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $delete = new Delete('users');
        $delete->setQueryBuilder($mockQueryBuilder);
        $delete->whereEqual('id', 1);
        
        $result = $delete->run();
        $this->assertEquals(1, $result);
        
        $query = (string)$delete;
        $this->assertStringContainsString('DELETE FROM users', $query);
        $this->assertStringContainsString('WHERE', $query);
    }
    
    public function testDeleteSafetyWithoutWhere(): void
    {
        $delete = new Delete('users');
        $query = (string)$delete;
        
        // Should have WHERE 1=0 to prevent accidental deletion
        $this->assertStringContainsString('WHERE 1=0', $query);
    }
}


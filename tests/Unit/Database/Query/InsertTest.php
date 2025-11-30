<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\Query\Insert;
use Gemvc\Database\QueryBuilder;
use Gemvc\Database\PdoQuery;

/**
 * @outputBuffering enabled
 */
class InsertTest extends TestCase
{
    private Insert $insert;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $this->insert = new Insert('users');
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $insert = new Insert('users');
        $this->assertInstanceOf(Insert::class, $insert);
    }
    
    // ============================================
    // QueryBuilder Reference Tests
    // ============================================
    
    public function testSetQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $result = $this->insert->setQueryBuilder($queryBuilder);
        $this->assertSame($this->insert, $result);
    }
    
    // ============================================
    // Columns Method Tests
    // ============================================
    
    public function testColumnsWithSingleColumn(): void
    {
        $result = $this->insert->columns('name');
        $this->assertSame($this->insert, $result);
    }
    
    public function testColumnsWithMultipleColumns(): void
    {
        $result = $this->insert->columns('name', 'email', 'password');
        $this->assertSame($this->insert, $result);
    }
    
    // ============================================
    // Values Method Tests
    // ============================================
    
    public function testValuesWithSingleValue(): void
    {
        $this->insert->columns('name');
        $result = $this->insert->values('John');
        $this->assertSame($this->insert, $result);
    }
    
    public function testValuesWithMultipleValues(): void
    {
        $this->insert->columns('name', 'email');
        $result = $this->insert->values('John', 'john@example.com');
        $this->assertSame($this->insert, $result);
    }
    
    public function testValuesMapsToKeyValue(): void
    {
        $this->insert->columns('name', 'email');
        $this->insert->values('John', 'john@example.com');
        
        $bindValues = $this->insert->getBindValues();
        $this->assertArrayHasKey(':name', $bindValues);
        $this->assertArrayHasKey(':email', $bindValues);
        $this->assertEquals('John', $bindValues[':name']);
        $this->assertEquals('john@example.com', $bindValues[':email']);
    }
    
    public function testValuesWithMismatchedCount(): void
    {
        $this->insert->columns('name', 'email', 'password');
        $this->insert->values('John', 'john@example.com');
        // Should not map if counts don't match
        $bindValues = $this->insert->getBindValues();
        $this->assertEmpty($bindValues);
    }
    
    // ============================================
    // ToString Tests
    // ============================================
    
    public function testToStringWithColumnsAndValues(): void
    {
        $this->insert->columns('name', 'email');
        $this->insert->values('John', 'john@example.com');
        $query = (string)$this->insert;
        
        $this->assertStringContainsString('INSERT INTO', $query);
        $this->assertStringContainsString('users', $query);
        $this->assertStringContainsString('name', $query);
        $this->assertStringContainsString('email', $query);
        $this->assertStringContainsString('VALUES', $query);
    }
    
    public function testToStringWithEmptyColumns(): void
    {
        $query = (string)$this->insert;
        $this->assertStringContainsString('INSERT INTO', $query);
        $this->assertStringContainsString('users', $query);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetErrorReturnsNullInitially(): void
    {
        $this->assertNull($this->insert->getError());
    }
    
    // ============================================
    // Run Method Tests
    // ============================================
    
    public function testRunWithoutColumns(): void
    {
        $result = $this->insert->run();
        $this->assertNull($result);
        $this->assertNotNull($this->insert->getError());
        $this->assertStringContainsString('No columns specified', $this->insert->getError());
    }
    
    public function testRunWithoutValues(): void
    {
        $this->insert->columns('name', 'email');
        $result = $this->insert->run();
        $this->assertNull($result);
        $this->assertNotNull($this->insert->getError());
        $this->assertStringContainsString('No values specified', $this->insert->getError());
    }
    
    public function testRunWithoutQueryBuilder(): void
    {
        $this->insert->columns('name');
        $this->insert->values('John');
        
        // Will try to create new PdoQuery and execute
        // May return null if connection fails, but should not throw
        $result = $this->insert->run();
        $this->assertTrue($result === null || is_int($result));
    }
    
    public function testRunWithQueryBuilder(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('insertQuery')
            ->willReturn(1);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $this->insert->columns('name');
        $this->insert->values('John');
        $this->insert->setQueryBuilder($mockQueryBuilder);
        
        $result = $this->insert->run();
        $this->assertIsInt($result);
        $this->assertEquals(1, $result);
    }
    
    public function testRunWithQueryBuilderError(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('insertQuery')
            ->willReturn(null);
        $mockPdoQuery->method('getError')
            ->willReturn('SQL error');
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $this->insert->columns('name');
        $this->insert->values('John');
        $this->insert->setQueryBuilder($mockQueryBuilder);
        
        $result = $this->insert->run();
        $this->assertNull($result);
        $this->assertEquals('SQL error', $this->insert->getError());
    }
    
    // ============================================
    // Property Tests
    // ============================================
    
    public function testResultProperty(): void
    {
        // Property is typed but not initialized, so we can't access it directly
        // Instead, we verify it exists via reflection
        $reflection = new \ReflectionClass($this->insert);
        $this->assertTrue($reflection->hasProperty('result'));
        $property = $reflection->getProperty('result');
        $this->assertTrue($property->isPublic());
    }
    
    // ============================================
    // Integration Tests
    // ============================================
    
    public function testFullInsertFlow(): void
    {
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('insertQuery')
            ->willReturn(123);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('getPdoQuery')
            ->willReturn($mockPdoQuery);
        
        $insert = new Insert('users');
        $insert->setQueryBuilder($mockQueryBuilder);
        $insert->columns('name', 'email', 'password');
        $insert->values('John Doe', 'john@example.com', 'hashed_password');
        
        $result = $insert->run();
        $this->assertEquals(123, $result);
        
        $query = (string)$insert;
        $this->assertStringContainsString('INSERT INTO users', $query);
        $this->assertStringContainsString('name', $query);
        $this->assertStringContainsString('email', $query);
        $this->assertStringContainsString('password', $query);
    }
}


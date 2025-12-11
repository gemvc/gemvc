<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\QueryBuilder;
use Gemvc\Database\Query\Select;
use Gemvc\Database\Query\Insert;
use Gemvc\Database\Query\Update;
use Gemvc\Database\Query\Delete;
use Gemvc\Database\PdoQuery;

/**
 * @outputBuffering enabled
 */
class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $this->queryBuilder = new QueryBuilder();
    }
    
    protected function tearDown(): void
    {
        if (isset($this->queryBuilder)) {
            $this->queryBuilder->disconnect();
        }
        parent::tearDown();
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $qb = new QueryBuilder();
        $this->assertInstanceOf(QueryBuilder::class, $qb);
        $this->assertNull($qb->getError());
    }
    
    // ============================================
    // Select Query Tests
    // ============================================
    
    public function testSelectWithSingleColumn(): void
    {
        $query = $this->queryBuilder->select('id');
        $this->assertInstanceOf(Select::class, $query);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testSelectWithMultipleColumns(): void
    {
        $query = $this->queryBuilder->select('id', 'name', 'email');
        $this->assertInstanceOf(Select::class, $query);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testSelectWithEmptyColumns(): void
    {
        $query = $this->queryBuilder->select();
        $this->assertInstanceOf(Select::class, $query);
        // Should set error for empty columns
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('must specify at least one column', $this->queryBuilder->getError());
    }
    
    public function testSelectWithEmptyStringColumn(): void
    {
        $query = $this->queryBuilder->select('');
        $this->assertInstanceOf(Select::class, $query);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('cannot be empty', $this->queryBuilder->getError());
    }
    
    public function testSelectWithWhitespaceOnlyColumn(): void
    {
        $query = $this->queryBuilder->select('   ');
        $this->assertInstanceOf(Select::class, $query);
        $this->assertNotNull($this->queryBuilder->getError());
    }
    
    public function testSelectClearsPreviousError(): void
    {
        $this->queryBuilder->setError('Previous error');
        $this->queryBuilder->select('id');
        // Error should be cleared
        $this->assertNull($this->queryBuilder->getError());
    }
    
    // ============================================
    // Insert Query Tests
    // ============================================
    
    public function testInsertWithValidTableName(): void
    {
        $query = $this->queryBuilder->insert('users');
        $this->assertInstanceOf(Insert::class, $query);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testInsertWithEmptyTableName(): void
    {
        $query = $this->queryBuilder->insert('');
        $this->assertNull($query);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('cannot be empty', $this->queryBuilder->getError());
    }
    
    public function testInsertWithWhitespaceOnlyTableName(): void
    {
        $query = $this->queryBuilder->insert('   ');
        $this->assertNull($query);
        $this->assertNotNull($this->queryBuilder->getError());
    }
    
    public function testInsertClearsPreviousError(): void
    {
        $this->queryBuilder->setError('Previous error');
        $this->queryBuilder->insert('users');
        $this->assertNull($this->queryBuilder->getError());
    }
    
    // ============================================
    // Update Query Tests
    // ============================================
    
    public function testUpdateWithValidTableName(): void
    {
        $query = $this->queryBuilder->update('users');
        $this->assertInstanceOf(Update::class, $query);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testUpdateWithEmptyTableName(): void
    {
        $query = $this->queryBuilder->update('');
        $this->assertNull($query);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('cannot be empty', $this->queryBuilder->getError());
    }
    
    public function testUpdateWithWhitespaceOnlyTableName(): void
    {
        $query = $this->queryBuilder->update('   ');
        $this->assertNull($query);
        $this->assertNotNull($this->queryBuilder->getError());
    }
    
    public function testUpdateClearsPreviousError(): void
    {
        $this->queryBuilder->setError('Previous error');
        $this->queryBuilder->update('users');
        $this->assertNull($this->queryBuilder->getError());
    }
    
    // ============================================
    // Delete Query Tests
    // ============================================
    
    public function testDeleteWithValidTableName(): void
    {
        $query = $this->queryBuilder->delete('users');
        $this->assertInstanceOf(Delete::class, $query);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testDeleteWithEmptyTableName(): void
    {
        $query = $this->queryBuilder->delete('');
        $this->assertNull($query);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('cannot be empty', $this->queryBuilder->getError());
    }
    
    public function testDeleteWithWhitespaceOnlyTableName(): void
    {
        $query = $this->queryBuilder->delete('   ');
        $this->assertNull($query);
        $this->assertNotNull($this->queryBuilder->getError());
    }
    
    public function testDeleteClearsPreviousError(): void
    {
        $this->queryBuilder->setError('Previous error');
        $this->queryBuilder->delete('users');
        $this->assertNull($this->queryBuilder->getError());
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetErrorReturnsNullInitially(): void
    {
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testSetError(): void
    {
        $this->queryBuilder->setError('Test error');
        $this->assertEquals('Test error', $this->queryBuilder->getError());
    }
    
    public function testSetErrorWithNull(): void
    {
        $this->queryBuilder->setError('Test error');
        $this->queryBuilder->setError(null);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testSetErrorWithContext(): void
    {
        $this->queryBuilder->setError('Test error', ['key' => 'value', 'number' => 123]);
        $error = $this->queryBuilder->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
        $this->assertStringContainsString('Context', $error);
    }
    
    public function testClearError(): void
    {
        $this->queryBuilder->setError('Test error');
        $this->queryBuilder->clearError();
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testGetErrorFromLastQuery(): void
    {
        // Create a mock query with an error
        $mockQuery = $this->createMock(\Gemvc\Database\QueryBuilderInterface::class);
        $mockQuery->method('getError')
            ->willReturn('Query error');
        
        // Use reflection to set lastQuery
        $reflection = new \ReflectionClass($this->queryBuilder);
        $lastQueryProperty = $reflection->getProperty('lastQuery');
        $lastQueryProperty->setValue($this->queryBuilder, $mockQuery);
        
        $error = $this->queryBuilder->getError();
        $this->assertEquals('Query error', $error);
    }
    
    public function testGetErrorPrioritizesBuilderError(): void
    {
        // Set builder error
        $this->queryBuilder->setError('Builder error');
        
        // Create a mock query with an error
        $mockQuery = $this->createMock(\Gemvc\Database\QueryBuilderInterface::class);
        $mockQuery->method('getError')
            ->willReturn('Query error');
        
        // Use reflection to set lastQuery
        $reflection = new \ReflectionClass($this->queryBuilder);
        $lastQueryProperty = $reflection->getProperty('lastQuery');
        $lastQueryProperty->setValue($this->queryBuilder, $mockQuery);
        
        // Builder error should take priority
        $error = $this->queryBuilder->getError();
        $this->assertEquals('Builder error', $error);
    }
    
    // ============================================
    // setLastQuery Tests
    // ============================================
    
    public function testSetLastQuery(): void
    {
        $mockQuery = $this->createMock(\Gemvc\Database\QueryBuilderInterface::class);
        
        // Use reflection to access setLastQuery
        $reflection = new \ReflectionClass($this->queryBuilder);
        $method = $reflection->getMethod('setLastQuery');
        $method->invoke($this->queryBuilder, $mockQuery);
        
        // Verify lastQuery was set
        $lastQueryProperty = $reflection->getProperty('lastQuery');
        $this->assertEquals($mockQuery, $lastQueryProperty->getValue($this->queryBuilder));
    }
    
    // ============================================
    // PdoQuery Tests
    // ============================================
    
    public function testGetPdoQueryLazyLoading(): void
    {
        $pdoQuery1 = $this->queryBuilder->getPdoQuery();
        $pdoQuery2 = $this->queryBuilder->getPdoQuery();
        
        // Should return the same instance (lazy loading)
        $this->assertSame($pdoQuery1, $pdoQuery2);
        $this->assertInstanceOf(PdoQuery::class, $pdoQuery1);
    }
    
    public function testGetPdoQueryPropagatesErrors(): void
    {
        // Create a mock PdoQuery with an error
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('getError')
            ->willReturn('PdoQuery error');
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $this->queryBuilder->getPdoQuery();
        
        // Error should be propagated
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('PdoQuery error', $this->queryBuilder->getError());
    }
    
    // ============================================
    // Connection Tests
    // ============================================
    
    public function testIsConnectedReturnsFalseInitially(): void
    {
        $this->assertFalse($this->queryBuilder->isConnected());
    }
    
    public function testIsConnectedReturnsTrueWhenPdoQueryConnected(): void
    {
        // Create a mock PdoQuery that is connected
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('isConnected')
            ->willReturn(true);
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $this->assertTrue($this->queryBuilder->isConnected());
    }
    
    // ============================================
    // Transaction Tests
    // ============================================
    
    public function testBeginTransaction(): void
    {
        // Mock PdoQuery
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('beginTransaction')
            ->willReturn(true);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $result = $this->queryBuilder->beginTransaction();
        $this->assertTrue($result);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testBeginTransactionWithError(): void
    {
        // Mock PdoQuery that fails
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('beginTransaction')
            ->willReturn(false);
        $mockPdoQuery->method('getError')
            ->willReturn('Transaction failed');
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $result = $this->queryBuilder->beginTransaction();
        $this->assertFalse($result);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('Failed to begin transaction', $this->queryBuilder->getError());
    }
    
    public function testCommitWithoutConnection(): void
    {
        $result = $this->queryBuilder->commit();
        $this->assertFalse($result);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('No active connection', $this->queryBuilder->getError());
    }
    
    public function testCommitWithSuccess(): void
    {
        // Mock PdoQuery that is connected
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('isConnected')
            ->willReturn(true);
        $mockPdoQuery->method('commit')
            ->willReturn(true);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $result = $this->queryBuilder->commit();
        $this->assertTrue($result);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testCommitWithError(): void
    {
        // Mock PdoQuery that fails
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('isConnected')
            ->willReturn(true);
        $mockPdoQuery->method('commit')
            ->willReturn(false);
        $mockPdoQuery->method('getError')
            ->willReturn('Commit failed');
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $result = $this->queryBuilder->commit();
        $this->assertFalse($result);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('Failed to commit transaction', $this->queryBuilder->getError());
    }
    
    public function testRollbackWithoutConnection(): void
    {
        $result = $this->queryBuilder->rollback();
        $this->assertFalse($result);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('No active connection', $this->queryBuilder->getError());
    }
    
    public function testRollbackWithSuccess(): void
    {
        // Mock PdoQuery that is connected
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('isConnected')
            ->willReturn(true);
        $mockPdoQuery->method('rollback')
            ->willReturn(true);
        $mockPdoQuery->method('getError')
            ->willReturn(null);
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $result = $this->queryBuilder->rollback();
        $this->assertTrue($result);
        $this->assertNull($this->queryBuilder->getError());
    }
    
    public function testRollbackWithError(): void
    {
        // Mock PdoQuery that fails
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('isConnected')
            ->willReturn(true);
        $mockPdoQuery->method('rollback')
            ->willReturn(false);
        $mockPdoQuery->method('getError')
            ->willReturn('Rollback failed');
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $result = $this->queryBuilder->rollback();
        $this->assertFalse($result);
        $this->assertNotNull($this->queryBuilder->getError());
        $this->assertStringContainsString('Failed to rollback transaction', $this->queryBuilder->getError());
    }
    
    // ============================================
    // Disconnect Tests
    // ============================================
    
    public function testDisconnect(): void
    {
        // Get PdoQuery to initialize it
        $pdoQuery = $this->queryBuilder->getPdoQuery();
        
        // Mock it
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('disconnect');
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        $this->queryBuilder->disconnect();
        
        // Verify pdoQuery is null after disconnect
        $this->assertNull($pdoQueryProperty->getValue($this->queryBuilder));
    }
    
    public function testDisconnectWhenNoPdoQuery(): void
    {
        // Should not throw error when no PdoQuery exists
        $this->queryBuilder->disconnect();
        $this->assertTrue(true);
    }
    
    // ============================================
    // Destructor Tests
    // ============================================
    
    public function testDestructorCallsDisconnect(): void
    {
        // Get PdoQuery to initialize it
        $pdoQuery = $this->queryBuilder->getPdoQuery();
        
        // Mock it
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('disconnect');
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->queryBuilder);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->queryBuilder, $mockPdoQuery);
        
        // Destructor will be called when object goes out of scope
        unset($this->queryBuilder);
    }
}


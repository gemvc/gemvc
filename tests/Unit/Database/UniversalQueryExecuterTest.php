<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\UniversalQueryExecuter;
use Gemvc\Database\DatabaseManagerInterface;
use Gemvc\Database\DatabaseManagerFactory;
use PDO;
use PDOStatement;
use PDOException;

/**
 * @outputBuffering enabled
 */
class UniversalQueryExecuterTest extends TestCase
{
    private ?PDO $mockPdo = null;
    private ?DatabaseManagerInterface $mockDbManager = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        
        // Create mock PDO
        $this->mockPdo = $this->createMock(PDO::class);
        
        // Create mock database manager
        $this->mockDbManager = $this->createMock(DatabaseManagerInterface::class);
        $this->mockDbManager->method('getConnection')
            ->willReturn($this->mockPdo);
        $this->mockDbManager->method('getError')
            ->willReturn(null);
        // releaseConnection is void - PHPUnit handles void methods automatically
        // We only configure expectations when needed in specific tests
        
        // Reset DatabaseManagerFactory singleton for testing
        DatabaseManagerFactory::resetInstance();
    }
    
    protected function tearDown(): void
    {
        DatabaseManagerFactory::resetInstance();
        parent::tearDown();
    }
    
    /**
     * Create a UniversalQueryExecuter with mocked database manager
     */
    private function createExecuterWithMockManager(): UniversalQueryExecuter
    {
        // Use reflection to inject mock manager
        $executer = new UniversalQueryExecuter();
        $reflection = new \ReflectionClass($executer);
        
        $dbManagerProperty = $reflection->getProperty('dbManager');
        $dbManagerProperty->setAccessible(true);
        $dbManagerProperty->setValue($executer, $this->mockDbManager);
        
        return $executer;
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $executer = new UniversalQueryExecuter();
        $this->assertInstanceOf(UniversalQueryExecuter::class, $executer);
        $this->assertNull($executer->getError());
    }
    
    public function testConstructorInitializesExecutionTime(): void
    {
        $executer = new UniversalQueryExecuter();
        $executionTime = $executer->getExecutionTime();
        $this->assertIsFloat($executionTime);
        $this->assertEquals(0, $executionTime); // Not executed yet
    }
    
    // ============================================
    // Query Preparation Tests
    // ============================================
    
    public function testQueryWithValidSql(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users');
        
        $this->assertNull($executer->getError());
        $this->assertEquals('SELECT * FROM users', $executer->getQuery());
    }
    
    public function testQueryWithEmptyString(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $executer->query('');
        
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('empty', $executer->getError());
    }
    
    public function testQueryWithExcessiveLength(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $longQuery = str_repeat('A', 1000001); // 1MB + 1 byte
        $executer->query($longQuery);
        
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('maximum length', $executer->getError());
    }
    
    public function testQueryClosesPreviousStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement1 = $this->createMock(PDOStatement::class);
        $mockStatement1->expects($this->once())
            ->method('closeCursor');
        
        $mockStatement2 = $this->createMock(PDOStatement::class);
        
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($mockStatement1, $mockStatement2);
        
        $executer->query('SELECT 1');
        $executer->query('SELECT 2');
    }
    
    public function testQueryClearsBindings(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users WHERE id = :id');
        $executer->bind(':id', 1);
        $executer->query('SELECT * FROM users');
        
        // Bindings should be cleared
        $this->assertNull($executer->getError());
    }
    
    public function testQueryWithConnectionError(): void
    {
        $executer = new UniversalQueryExecuter();
        
        // Mock manager that returns null (connection error)
        $mockManager = $this->createMock(DatabaseManagerInterface::class);
        $mockManager->method('getConnection')
            ->willReturn(null);
        $mockManager->method('getError')
            ->willReturn('Connection failed');
        
        $reflection = new \ReflectionClass($executer);
        $dbManagerProperty = $reflection->getProperty('dbManager');
        $dbManagerProperty->setAccessible(true);
        $dbManagerProperty->setValue($executer, $mockManager);
        
        $executer->query('SELECT * FROM users');
        
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('Connection error', $executer->getError());
    }
    
    public function testQueryWithPrepareException(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('SQL syntax error'));
        
        $executer->query('INVALID SQL');
        
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('preparing statement', $executer->getError());
    }
    
    // ============================================
    // Parameter Binding Tests
    // ============================================
    
    public function testBindWithInteger(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('bindValue')
            ->with(':id', 123, PDO::PARAM_INT);
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users WHERE id = :id');
        $executer->bind(':id', 123);
        
        $this->assertNull($executer->getError());
    }
    
    public function testBindWithString(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('bindValue')
            ->with(':name', 'John', PDO::PARAM_STR);
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users WHERE name = :name');
        $executer->bind(':name', 'John');
        
        $this->assertNull($executer->getError());
    }
    
    public function testBindWithBoolean(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('bindValue')
            ->with(':active', true, PDO::PARAM_BOOL);
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users WHERE active = :active');
        $executer->bind(':active', true);
        
        $this->assertNull($executer->getError());
    }
    
    public function testBindWithNull(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('bindValue')
            ->with(':value', null, PDO::PARAM_NULL);
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users WHERE deleted_at = :value');
        $executer->bind(':value', null);
        
        $this->assertNull($executer->getError());
    }
    
    public function testBindWithoutStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $executer->bind(':id', 1);
        
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('No statement prepared', $executer->getError());
    }
    
    public function testBindWithException(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('bindValue')
            ->willThrowException(new PDOException('Invalid parameter'));
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT * FROM users WHERE id = :id');
        $executer->bind(':id', 1);
        
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('binding parameter', $executer->getError());
    }
    
    // ============================================
    // Execute Tests
    // ============================================
    
    public function testExecuteWithoutStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->execute();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('No statement prepared', $executer->getError());
    }
    
    public function testExecuteWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('UPDATE users SET name = :name');
        $executer->bind(':name', 'John');
        $result = $executer->execute();
        
        $this->assertTrue($result);
        $this->assertNull($executer->getError());
        $this->assertEquals(5, $executer->getAffectedRows());
    }
    
    public function testExecuteWithInsertGetsLastInsertId(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        // closeCursor is called in execute() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->query('INSERT INTO users (name) VALUES (:name)');
        $executer->bind(':name', 'John');
        $result = $executer->execute();
        
        $this->assertTrue($result);
        $this->assertEquals('42', $executer->getLastInsertedId());
    }
    
    public function testExecuteWithException(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('execute')
            ->willThrowException(new PDOException('SQL error', 23000));
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('UPDATE users SET name = :name');
        $executer->bind(':name', 'John');
        $result = $executer->execute();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testExecuteReleasesConnectionForNonSelect(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        // closeCursor is called in execute() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection')
            ->with($this->mockPdo);
        
        $executer->query('UPDATE users SET name = :name');
        $executer->execute();
    }
    
    // ============================================
    // Fetch Tests
    // ============================================
    
    public function testFetchAllObjectsWithoutStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->fetchAllObjects();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testFetchAllObjectsWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $expectedResult = [(object)['id' => 1, 'name' => 'John']];
        $mockStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_OBJ)
            ->willReturn($expectedResult);
        // closeCursor is called in fetchAllObjects() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->query('SELECT * FROM users');
        $executer->execute();
        $result = $executer->fetchAllObjects();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->id);
    }
    
    public function testFetchAllWithoutStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->fetchAll();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testFetchAllWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $expectedResult = [['id' => 1, 'name' => 'John']];
        $mockStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedResult);
        // closeCursor is called in fetchAll() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->query('SELECT * FROM users');
        $executer->execute();
        $result = $executer->fetchAll();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
    }
    
    public function testFetchOneWithoutStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->fetchOne();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testFetchOneWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $expectedResult = ['id' => 1, 'name' => 'John'];
        $mockStatement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedResult);
        // closeCursor is called in fetchOne() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->query('SELECT * FROM users WHERE id = :id');
        $executer->bind(':id', 1);
        $executer->execute();
        $result = $executer->fetchOne();
        
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John', $result['name']);
    }
    
    public function testFetchOneWithNoResult(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false);
        // closeCursor is called in fetchOne() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->query('SELECT * FROM users WHERE id = :id');
        $executer->bind(':id', 999);
        $executer->execute();
        $result = $executer->fetchOne();
        
        $this->assertFalse($result);
    }
    
    public function testFetchColumnWithoutStatement(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->fetchColumn();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testFetchColumnWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('John');
        // closeCursor is called in fetchColumn() and again in releaseConnection()
        $mockStatement->expects($this->atLeastOnce())
            ->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->query('SELECT name FROM users WHERE id = :id');
        $executer->bind(':id', 1);
        $executer->execute();
        $result = $executer->fetchColumn();
        
        $this->assertEquals('John', $result);
    }
    
    // ============================================
    // Transaction Tests
    // ============================================
    
    public function testBeginTransaction(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        
        $result = $executer->beginTransaction();
        
        $this->assertTrue($result);
        $this->assertNull($executer->getError());
    }
    
    public function testBeginTransactionWhenAlreadyInTransaction(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        
        $executer->beginTransaction();
        $result = $executer->beginTransaction();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('Already in transaction', $executer->getError());
    }
    
    public function testBeginTransactionWithException(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(new PDOException('Transaction error'));
        
        $result = $executer->beginTransaction();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testCommitWithoutTransaction(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->commit();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('No active transaction', $executer->getError());
    }
    
    public function testCommitWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->beginTransaction();
        $result = $executer->commit();
        
        $this->assertTrue($result);
        $this->assertNull($executer->getError());
    }
    
    public function testCommitWithException(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willThrowException(new PDOException('Commit error'));
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->beginTransaction();
        $result = $executer->commit();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testRollbackWithoutTransaction(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $result = $executer->rollback();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
        $this->assertStringContainsString('No active transaction', $executer->getError());
    }
    
    public function testRollbackWithSuccess(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->beginTransaction();
        $result = $executer->rollback();
        
        $this->assertTrue($result);
        $this->assertNull($executer->getError());
    }
    
    public function testRollbackWithException(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willThrowException(new PDOException('Rollback error'));
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->beginTransaction();
        $result = $executer->rollback();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testSetErrorWithNull(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $executer->query('INVALID');
        $executer->setError(null);
        
        $this->assertNull($executer->getError());
    }
    
    public function testSetErrorWithContext(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $executer->setError('Test error', ['key' => 'value', 'number' => 123]);
        
        $error = $executer->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
        $this->assertStringContainsString('Context', $error);
    }
    
    public function testGetErrorReturnsNullInitially(): void
    {
        $executer = new UniversalQueryExecuter();
        $this->assertNull($executer->getError());
    }
    
    // ============================================
    // Execution Time Tests
    // ============================================
    
    public function testGetExecutionTimeBeforeExecution(): void
    {
        $executer = new UniversalQueryExecuter();
        $time = $executer->getExecutionTime();
        
        $this->assertEquals(0, $time);
    }
    
    public function testGetExecutionTimeAfterExecution(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')
            ->willReturn(true);
        $mockStatement->method('rowCount')
            ->willReturn(0);
        $mockStatement->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('SELECT 1');
        $executer->execute();
        
        $time = $executer->getExecutionTime();
        $this->assertGreaterThan(0, $time);
    }
    
    // ============================================
    // Affected Rows Tests
    // ============================================
    
    public function testGetAffectedRows(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')
            ->willReturn(true);
        $mockStatement->method('rowCount')
            ->willReturn(10);
        $mockStatement->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        
        $executer->query('UPDATE users SET active = 1');
        $executer->execute();
        
        $this->assertEquals(10, $executer->getAffectedRows());
    }
    
    // ============================================
    // Last Inserted ID Tests
    // ============================================
    
    public function testGetLastInsertedIdBeforeInsert(): void
    {
        $executer = new UniversalQueryExecuter();
        $this->assertFalse($executer->getLastInsertedId());
    }
    
    public function testGetLastInsertedIdAfterInsert(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')
            ->willReturn(true);
        $mockStatement->method('rowCount')
            ->willReturn(1);
        $mockStatement->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        $this->mockPdo->method('lastInsertId')
            ->willReturn('123');
        
        $executer->query('INSERT INTO users (name) VALUES (:name)');
        $executer->bind(':name', 'John');
        $executer->execute();
        
        $this->assertEquals('123', $executer->getLastInsertedId());
    }
    
    // ============================================
    // Manager Info Tests
    // ============================================
    
    public function testGetManagerInfo(): void
    {
        $executer = new UniversalQueryExecuter();
        $info = $executer->getManagerInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('environment', $info);
        $this->assertArrayHasKey('manager_class', $info);
    }
    
    // ============================================
    // Secure Cleanup Tests
    // ============================================
    
    public function testSecureWithForceRollback(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->beginTransaction();
        $executer->secure(true);
    }
    
    public function testSecureWithoutForceRollback(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        // secure() calls releaseConnection() which checks if $this->db exists
        // If no connection exists, releaseConnection() does nothing (checks if ($this->db))
        // So we need to have a connection first
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        
        // Set up expectation BEFORE calling beginTransaction
        $this->mockDbManager->expects($this->atLeastOnce())
            ->method('releaseConnection')
            ->with($this->mockPdo);
        
        $executer->beginTransaction();
        
        // Now secure() should call releaseConnection since we have a connection
        $executer->secure(false);
    }
    
    // ============================================
    // Destructor Tests
    // ============================================
    
    public function testDestructorCallsSecure(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);
        
        $this->mockDbManager->expects($this->once())
            ->method('releaseConnection');
        
        $executer->beginTransaction();
        // Destructor will be called when object goes out of scope
        unset($executer);
    }
    
    // ============================================
    // Connection Management Tests
    // ============================================
    
    public function testConnectionNotReleasedForSelectInTransaction(): void
    {
        $executer = $this->createExecuterWithMockManager();
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')
            ->willReturn(true);
        $mockStatement->method('rowCount')
            ->willReturn(0);
        // closeCursor is not called in execute() for SELECT in transaction
        // but may be called elsewhere, so we allow it
        $mockStatement->method('closeCursor');
        
        $this->mockPdo->method('prepare')
            ->willReturn($mockStatement);
        $this->mockPdo->method('beginTransaction')
            ->willReturn(true);
        
        // Connection should NOT be released for SELECT queries in transaction during execute()
        // However, the destructor will call secure() which calls releaseConnection()
        // So we need to verify that execute() itself doesn't call releaseConnection
        // We'll use a callback to track when it's called
        $releaseCallCount = 0;
        $this->mockDbManager->method('releaseConnection')
            ->willReturnCallback(function () use (&$releaseCallCount) {
                $releaseCallCount++;
            });
        
        $executer->beginTransaction();
        $executer->query('SELECT * FROM users');
        
        // Track calls before execute
        $callsBeforeExecute = $releaseCallCount;
        $executer->execute();
        $callsAfterExecute = $releaseCallCount;
        
        // Verify execute() didn't release connection (no new calls during execute)
        $this->assertNull($executer->getError());
        $this->assertEquals($callsBeforeExecute, $callsAfterExecute, 'Connection should not be released during execute() for SELECT in transaction');
        
        // Note: Destructor will call releaseConnection, but that's after execute()
        // We explicitly unset to trigger destructor and verify it works
        unset($executer);
        $this->assertGreaterThan($callsAfterExecute, $releaseCallCount, 'Destructor should call releaseConnection');
    }
}


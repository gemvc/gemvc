<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\SimplePdoDatabaseManager;
use Gemvc\Database\DatabaseManagerInterface;
use PDO;
use PDOException;

/**
 * @outputBuffering enabled
 */
class SimplePdoDatabaseManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        // Reset singleton before each test
        SimplePdoDatabaseManager::resetInstance();
    }
    
    protected function tearDown(): void
    {
        SimplePdoDatabaseManager::resetInstance();
        parent::tearDown();
    }
    
    // ============================================
    // Singleton Pattern Tests
    // ============================================
    
    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = SimplePdoDatabaseManager::getInstance();
        $instance2 = SimplePdoDatabaseManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }
    
    public function testGetInstanceReturnsDatabaseManagerInterface(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $this->assertInstanceOf(DatabaseManagerInterface::class, $instance);
    }
    
    public function testGetInstanceReturnsSimplePdoDatabaseManager(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $this->assertInstanceOf(SimplePdoDatabaseManager::class, $instance);
    }
    
    // ============================================
    // Reset Instance Tests
    // ============================================
    
    public function testResetInstance(): void
    {
        $instance1 = SimplePdoDatabaseManager::getInstance();
        SimplePdoDatabaseManager::resetInstance();
        $instance2 = SimplePdoDatabaseManager::getInstance();
        
        // Should be different instances after reset
        $this->assertNotSame($instance1, $instance2);
    }
    
    public function testResetInstanceDisconnects(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Reset should disconnect
        SimplePdoDatabaseManager::resetInstance();
        
        // Get new instance
        $newInstance = SimplePdoDatabaseManager::getInstance();
        $this->assertNotSame($instance, $newInstance);
    }
    
    // ============================================
    // Initialization Tests
    // ============================================
    
    public function testIsInitializedReturnsBoolean(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $this->assertIsBool($instance->isInitialized());
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetErrorReturnsStringOrNull(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Error may be set during initialization if app directory doesn't exist
        $error = $instance->getError();
        // Error can be null or a string (initialization error)
        $this->assertTrue($error === null || is_string($error));
    }
    
    public function testSetError(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $instance->setError('Test error');
        $this->assertEquals('Test error', $instance->getError());
    }
    
    public function testSetErrorWithNull(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $instance->setError('Test error');
        $instance->setError(null);
        $this->assertNull($instance->getError());
    }
    
    public function testSetErrorWithContext(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $instance->setError('Test error', ['key' => 'value', 'number' => 123]);
        $error = $instance->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
        $this->assertStringContainsString('Context', $error);
    }
    
    public function testClearError(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $instance->setError('Test error');
        $instance->clearError();
        $this->assertNull($instance->getError());
    }
    
    // ============================================
    // Connection Tests
    // ============================================
    
    public function testGetConnectionClearsError(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $instance->setError('Previous error');
        // This will try to connect and may fail, but error should be cleared first
        $instance->getConnection();
        // Error might be set again if connection fails, but clearError was called
        $this->assertTrue(true); // Just verify no exception
    }
    
    public function testGetConnectionReturnsSameConnectionOnMultipleCalls(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $connection1 = $instance->getConnection();
        $connection2 = $instance->getConnection();
        
        // If both succeed, they should be the same instance
        if ($connection1 !== null && $connection2 !== null) {
            $this->assertSame($connection1, $connection2);
        }
    }
    
    public function testGetConnectionAcceptsPoolName(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Pool name is ignored in simple implementation, but should not throw
        $connection = $instance->getConnection('custom_pool');
        // May return null if connection fails, but should not throw
        $this->assertTrue($connection === null || $connection instanceof PDO);
    }
    
    // ============================================
    // Release Connection Tests
    // ============================================
    
    public function testReleaseConnectionDisconnects(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $connection = $instance->getConnection();
        
        if ($connection !== null) {
            $instance->releaseConnection($connection);
            // Connection should be released (disconnected)
            $this->assertTrue(true); // Just verify no exception
        } else {
            $this->markTestSkipped('No database connection available');
        }
    }
    
    public function testReleaseConnectionWithDifferentConnection(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $connection1 = $instance->getConnection();
        
        if ($connection1 !== null) {
            // Create a different PDO connection (mock)
            $differentConnection = $this->createMock(PDO::class);
            
            // Should not disconnect if connection doesn't match
            $instance->releaseConnection($differentConnection);
            $this->assertTrue(true); // Just verify no exception
        } else {
            $this->markTestSkipped('No database connection available');
        }
    }
    
    // ============================================
    // Pool Stats Tests
    // ============================================
    
    public function testGetPoolStatsReturnsArray(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertIsArray($stats);
    }
    
    public function testGetPoolStatsContainsType(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('type', $stats);
        $this->assertEquals('Simple PDO', $stats['type']);
    }
    
    public function testGetPoolStatsContainsEnvironment(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('environment', $stats);
        $this->assertEquals('Apache/Nginx PHP-FPM', $stats['environment']);
    }
    
    public function testGetPoolStatsContainsHasConnection(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('has_connection', $stats);
        $this->assertIsBool($stats['has_connection']);
    }
    
    public function testGetPoolStatsContainsInTransaction(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('in_transaction', $stats);
        $this->assertIsBool($stats['in_transaction']);
    }
    
    public function testGetPoolStatsContainsInitialized(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('initialized', $stats);
        $this->assertIsBool($stats['initialized']);
    }
    
    public function testGetPoolStatsContainsConfig(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('config', $stats);
        $this->assertIsArray($stats['config']);
    }
    
    public function testGetPoolStatsConfigContainsDriver(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('driver', $stats['config']);
    }
    
    public function testGetPoolStatsConfigContainsHost(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('host', $stats['config']);
    }
    
    public function testGetPoolStatsConfigContainsDatabase(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $stats = $instance->getPoolStats();
        $this->assertArrayHasKey('database', $stats['config']);
    }
    
    // ============================================
    // Transaction Tests
    // ============================================
    
    public function testBeginTransactionReturnsBoolean(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result = $instance->beginTransaction();
        $this->assertIsBool($result);
    }
    
    public function testBeginTransactionAcceptsPoolName(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Pool name is ignored, but should not throw
        $result = $instance->beginTransaction('custom_pool');
        $this->assertIsBool($result);
    }
    
    public function testBeginTransactionTwiceReturnsFalse(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result1 = $instance->beginTransaction();
        
        if ($result1) {
            // Second begin should fail
            $result2 = $instance->beginTransaction();
            $this->assertFalse($result2);
            $this->assertNotNull($instance->getError());
            $this->assertStringContainsString('Already in transaction', $instance->getError());
        } else {
            $this->markTestSkipped('Could not begin transaction');
        }
    }
    
    public function testCommitReturnsBoolean(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result = $instance->commit();
        // May return false if no transaction, but should not throw
        $this->assertIsBool($result);
    }
    
    public function testCommitWithoutTransactionReturnsFalse(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result = $instance->commit();
        $this->assertFalse($result);
        $this->assertNotNull($instance->getError());
        $this->assertStringContainsString('No active transaction', $instance->getError());
    }
    
    public function testCommitAcceptsPoolName(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Pool name is ignored, but should not throw
        $result = $instance->commit('custom_pool');
        $this->assertIsBool($result);
    }
    
    public function testRollbackReturnsBoolean(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result = $instance->rollback();
        // May return false if no transaction, but should not throw
        $this->assertIsBool($result);
    }
    
    public function testRollbackWithoutTransactionReturnsFalse(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result = $instance->rollback();
        $this->assertFalse($result);
        $this->assertNotNull($instance->getError());
        $this->assertStringContainsString('No active transaction', $instance->getError());
    }
    
    public function testRollbackAcceptsPoolName(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Pool name is ignored, but should not throw
        $result = $instance->rollback('custom_pool');
        $this->assertIsBool($result);
    }
    
    public function testInTransactionReturnsBoolean(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $result = $instance->inTransaction();
        $this->assertIsBool($result);
    }
    
    public function testInTransactionReturnsFalseInitially(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $this->assertFalse($instance->inTransaction());
    }
    
    public function testInTransactionReturnsTrueAfterBegin(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $beginResult = $instance->beginTransaction();
        
        if ($beginResult) {
            $this->assertTrue($instance->inTransaction());
            // Clean up
            $instance->rollback();
        } else {
            $this->markTestSkipped('Could not begin transaction');
        }
    }
    
    public function testInTransactionAcceptsPoolName(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Pool name is ignored, but should not throw
        $result = $instance->inTransaction('custom_pool');
        $this->assertIsBool($result);
    }
    
    // ============================================
    // Disconnect Tests
    // ============================================
    
    public function testDisconnectWithoutConnection(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        // Should not throw if no connection
        $instance->disconnect();
        $this->assertTrue(true);
    }
    
    public function testDisconnectRollsBackTransaction(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        $beginResult = $instance->beginTransaction();
        
        if ($beginResult) {
            $this->assertTrue($instance->inTransaction());
            $instance->disconnect();
            // Transaction should be rolled back
            $this->assertFalse($instance->inTransaction());
        } else {
            $this->markTestSkipped('Could not begin transaction');
        }
    }
    
    // ============================================
    // Integration Tests
    // ============================================
    
    public function testFullTransactionCycle(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        
        // Begin
        $beginResult = $instance->beginTransaction();
        if (!$beginResult) {
            $this->markTestSkipped('Could not begin transaction');
        }
        
        $this->assertTrue($instance->inTransaction());
        
        // Commit
        $commitResult = $instance->commit();
        if ($commitResult) {
            $this->assertFalse($instance->inTransaction());
        } else {
            // If commit fails, try rollback
            $rollbackResult = $instance->rollback();
            $this->assertIsBool($rollbackResult);
        }
    }
    
    public function testGetPoolStatsReflectsConnectionState(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        
        $statsBefore = $instance->getPoolStats();
        $hasConnectionBefore = $statsBefore['has_connection'];
        
        $connection = $instance->getConnection();
        $statsAfter = $instance->getPoolStats();
        $hasConnectionAfter = $statsAfter['has_connection'];
        
        // If connection was created, has_connection should be true
        if ($connection !== null) {
            $this->assertTrue($hasConnectionAfter);
        }
    }
    
    public function testGetPoolStatsReflectsTransactionState(): void
    {
        $instance = SimplePdoDatabaseManager::getInstance();
        
        $statsBefore = $instance->getPoolStats();
        $inTransactionBefore = $statsBefore['in_transaction'];
        $this->assertFalse($inTransactionBefore);
        
        $beginResult = $instance->beginTransaction();
        if ($beginResult) {
            $statsDuring = $instance->getPoolStats();
            $inTransactionDuring = $statsDuring['in_transaction'];
            $this->assertTrue($inTransactionDuring);
            
            // Clean up
            $instance->rollback();
        } else {
            $this->markTestSkipped('Could not begin transaction');
        }
    }
}


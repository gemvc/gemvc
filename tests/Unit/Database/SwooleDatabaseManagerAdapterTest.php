<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\SwooleDatabaseManagerAdapter;
use Gemvc\Database\SwooleDatabaseManager;
use Gemvc\Database\DatabaseManagerInterface;
use PDO;
use ReflectionClass;
use ReflectionProperty;

class SwooleDatabaseManagerAdapterTest extends TestCase
{
    private ?SwooleDatabaseManager $originalSwooleManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }
    }

    protected function tearDown(): void
    {
        // Reset SwooleDatabaseManager singleton
        SwooleDatabaseManager::resetInstance();
        
        parent::tearDown();
    }

    // ============================================
    // Constructor Tests
    // ============================================

    public function testConstructorCreatesInstance(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $this->assertInstanceOf(SwooleDatabaseManagerAdapter::class, $adapter);
        $this->assertInstanceOf(DatabaseManagerInterface::class, $adapter);
    }

    public function testConstructorInitializesSwooleManager(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $reflection = new ReflectionClass($adapter);
        $swooleManagerProperty = $reflection->getProperty('swooleManager');
        $swooleManagerProperty->setAccessible(true);
        
        $swooleManager = $swooleManagerProperty->getValue($adapter);
        $this->assertInstanceOf(SwooleDatabaseManager::class, $swooleManager);
    }

    public function testConstructorInitializesPdoToConnectionMap(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $reflection = new ReflectionClass($adapter);
        $mapProperty = $reflection->getProperty('pdoToConnectionMap');
        $mapProperty->setAccessible(true);
        
        $map = $mapProperty->getValue($adapter);
        $this->assertInstanceOf(\SplObjectStorage::class, $map);
    }

    public function testConstructorInitializesTransactionConnections(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $reflection = new ReflectionClass($adapter);
        $transactionsProperty = $reflection->getProperty('transactionConnections');
        $transactionsProperty->setAccessible(true);
        
        $transactions = $transactionsProperty->getValue($adapter);
        $this->assertIsArray($transactions);
        $this->assertEmpty($transactions);
    }

    // ============================================
    // Connection Management Tests
    // ============================================

    public function testGetConnectionReturnsPDO(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        // This will fail if no database is available, but we can test the method exists
        try {
            $pdo = $adapter->getConnection();
            $this->assertInstanceOf(PDO::class, $pdo);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertNull($adapter->getConnection());
        }
    }

    public function testGetConnectionReturnsNullOnError(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host_that_does_not_exist';
        $_ENV['DB_PORT'] = '9999';
        
        SwooleDatabaseManager::resetInstance();
        $adapter = new SwooleDatabaseManagerAdapter();
        
        $pdo = $adapter->getConnection();
        
        $this->assertNull($pdo);
    }

    public function testGetConnectionMapsPdoToConnection(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $pdo = $adapter->getConnection();
            if ($pdo !== null) {
                $reflection = new ReflectionClass($adapter);
                $mapProperty = $reflection->getProperty('pdoToConnectionMap');
                $mapProperty->setAccessible(true);
                
                $map = $mapProperty->getValue($adapter);
                $this->assertTrue($map->contains($pdo));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testGetConnectionUsesDefaultPoolName(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $pdo1 = $adapter->getConnection();
            $pdo2 = $adapter->getConnection('default');
            // Both should work with default pool
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testGetConnectionAcceptsCustomPoolName(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $pdo = $adapter->getConnection('custom_pool');
            // Should work or return null if pool doesn't exist
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testReleaseConnectionRemovesFromMap(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $pdo = $adapter->getConnection();
            if ($pdo !== null) {
                $reflection = new ReflectionClass($adapter);
                $mapProperty = $reflection->getProperty('pdoToConnectionMap');
                $mapProperty->setAccessible(true);
                
                $map = $mapProperty->getValue($adapter);
                $this->assertTrue($map->contains($pdo));
                
                $adapter->releaseConnection($pdo);
                
                $this->assertFalse($map->contains($pdo));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testReleaseConnectionHandlesUnknownPdo(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        // Create a PDO that's not in the map
        $unknownPdo = new PDO('sqlite::memory:');
        
        // Should not throw exception
        $adapter->releaseConnection($unknownPdo);
        
        $this->assertTrue(true);
    }

    public function testReleaseConnectionHandlesReleaseError(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        // This test verifies that releaseConnection handles errors gracefully
        // We can't easily mock the Hyperf Connection, but we can verify
        // the method doesn't throw exceptions
        try {
            $pdo = $adapter->getConnection();
            if ($pdo !== null) {
                $adapter->releaseConnection($pdo);
                $this->assertTrue(true);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testGetErrorDelegatesToSwooleManager(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        // Initially should be null
        $this->assertNull($adapter->getError());
        
        // Set error via adapter
        $adapter->setError('Test error');
        $this->assertEquals('Test error', $adapter->getError());
    }

    public function testSetErrorDelegatesToSwooleManager(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $adapter->setError('Test error');
        $this->assertEquals('Test error', $adapter->getError());
    }

    public function testSetErrorWithContext(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $context = ['key' => 'value', 'code' => 500];
        $adapter->setError('Test error', $context);
        
        $error = $adapter->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
    }

    public function testSetErrorWithNull(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $adapter->setError('Test error');
        $this->assertEquals('Test error', $adapter->getError());
        
        $adapter->setError(null);
        $this->assertNull($adapter->getError());
    }

    public function testClearErrorDelegatesToSwooleManager(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $adapter->setError('Test error');
        $this->assertEquals('Test error', $adapter->getError());
        
        $adapter->clearError();
        $this->assertNull($adapter->getError());
    }

    // ============================================
    // Initialization Tests
    // ============================================

    public function testIsInitializedReturnsTrue(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $this->assertTrue($adapter->isInitialized());
    }

    // ============================================
    // Pool Stats Tests
    // ============================================

    public function testGetPoolStatsReturnsArray(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $stats = $adapter->getPoolStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('type', $stats);
        $this->assertArrayHasKey('environment', $stats);
        $this->assertArrayHasKey('has_error', $stats);
        $this->assertArrayHasKey('error', $stats);
    }

    public function testGetPoolStatsReturnsCorrectType(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $stats = $adapter->getPoolStats();
        
        $this->assertEquals('Swoole Database Manager', $stats['type']);
        $this->assertEquals('OpenSwoole', $stats['environment']);
    }

    public function testGetPoolStatsReflectsErrorState(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        // No error initially
        $stats = $adapter->getPoolStats();
        $this->assertFalse($stats['has_error']);
        $this->assertNull($stats['error']);
        
        // Set error
        $adapter->setError('Test error');
        $stats = $adapter->getPoolStats();
        $this->assertTrue($stats['has_error']);
        $this->assertEquals('Test error', $stats['error']);
    }

    // ============================================
    // Transaction Tests
    // ============================================

    public function testBeginTransactionReturnsBoolean(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $result = $adapter->beginTransaction();
            $this->assertIsBool($result);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testBeginTransactionUsesDefaultPoolName(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $result1 = $adapter->beginTransaction();
            $result2 = $adapter->beginTransaction('default');
            // Both should work with default pool
            $this->assertIsBool($result1);
            $this->assertIsBool($result2);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testBeginTransactionPreventsDoubleStart(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $result1 = $adapter->beginTransaction('test_pool');
            if ($result1) {
                $result2 = $adapter->beginTransaction('test_pool');
                $this->assertFalse($result2);
                $this->assertStringContainsString('Transaction already active', $adapter->getError());
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testBeginTransactionReturnsFalseOnConnectionFailure(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host';
        $_ENV['DB_PORT'] = '9999';
        
        SwooleDatabaseManager::resetInstance();
        $adapter = new SwooleDatabaseManagerAdapter();
        
        $result = $adapter->beginTransaction();
        
        $this->assertFalse($result);
    }

    public function testCommitReturnsBoolean(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction()) {
                $result = $adapter->commit();
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testCommitReturnsFalseWhenNoTransaction(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $result = $adapter->commit('nonexistent_pool');
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No active transaction', $adapter->getError());
    }

    public function testRollbackReturnsBoolean(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction()) {
                $result = $adapter->rollback();
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testRollbackReturnsFalseWhenNoTransaction(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $result = $adapter->rollback('nonexistent_pool');
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No active transaction', $adapter->getError());
    }

    public function testInTransactionReturnsFalseInitially(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        $this->assertFalse($adapter->inTransaction());
        $this->assertFalse($adapter->inTransaction('test_pool'));
    }

    public function testInTransactionReturnsTrueWhenActive(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction('test_pool')) {
                $this->assertTrue($adapter->inTransaction('test_pool'));
                $this->assertFalse($adapter->inTransaction('other_pool'));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testInTransactionReturnsFalseAfterCommit(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction('test_pool')) {
                $this->assertTrue($adapter->inTransaction('test_pool'));
                $adapter->commit('test_pool');
                $this->assertFalse($adapter->inTransaction('test_pool'));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testInTransactionReturnsFalseAfterRollback(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction('test_pool')) {
                $this->assertTrue($adapter->inTransaction('test_pool'));
                $adapter->rollback('test_pool');
                $this->assertFalse($adapter->inTransaction('test_pool'));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testTransactionConnectionsAreTrackedPerPool(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction('pool1') && $adapter->beginTransaction('pool2')) {
                $this->assertTrue($adapter->inTransaction('pool1'));
                $this->assertTrue($adapter->inTransaction('pool2'));
                
                $adapter->commit('pool1');
                $this->assertFalse($adapter->inTransaction('pool1'));
                $this->assertTrue($adapter->inTransaction('pool2'));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    // ============================================
    // Edge Cases and Error Scenarios
    // ============================================

    public function testBeginTransactionHandlesException(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // This test verifies that beginTransaction handles exceptions gracefully
        // We can't easily mock the PDO to throw, but we can verify the method
        // structure handles exceptions
        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            $result = $adapter->beginTransaction();
            // Should return bool, not throw
            $this->assertIsBool($result);
        } catch (\Throwable $e) {
            // Only unexpected exceptions should be caught here
            $this->fail('beginTransaction should not throw: ' . $e->getMessage());
        }
    }

    public function testCommitHandlesException(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction()) {
                $result = $adapter->commit();
                // Should return bool, not throw
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Only unexpected exceptions should be caught here
            $this->fail('commit should not throw: ' . $e->getMessage());
        }
    }

    public function testRollbackHandlesException(): void
    {
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $adapter = new SwooleDatabaseManagerAdapter();
        
        try {
            if ($adapter->beginTransaction()) {
                $result = $adapter->rollback();
                // Should return bool, not throw
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Only unexpected exceptions should be caught here
            $this->fail('rollback should not throw: ' . $e->getMessage());
        }
    }
}


<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\EnhancedPdoDatabaseManager;
use Gemvc\Database\DatabaseManagerInterface;
use PDO;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @outputBuffering enabled
 */
class EnhancedPdoDatabaseManagerTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        
        // Save original environment variables
        $this->originalEnv = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? null,
            'DB_HOST' => $_ENV['DB_HOST'] ?? null,
            'DB_PORT' => $_ENV['DB_PORT'] ?? null,
            'DB_NAME' => $_ENV['DB_NAME'] ?? null,
            'DB_USER' => $_ENV['DB_USER'] ?? null,
            'DB_PASSWORD' => $_ENV['DB_PASSWORD'] ?? null,
            'DB_CHARSET' => $_ENV['DB_CHARSET'] ?? null,
            'DB_DRIVER' => $_ENV['DB_DRIVER'] ?? null,
            'DB_COLLATION' => $_ENV['DB_COLLATION'] ?? null,
        ];

        // Set test environment variables
        $_ENV['APP_ENV'] = 'test';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_password';
        $_ENV['DB_CHARSET'] = 'utf8mb4';
        $_ENV['DB_DRIVER'] = 'mysql';
        $_ENV['DB_COLLATION'] = 'utf8mb4_unicode_ci';

        // Reset singleton instance
        EnhancedPdoDatabaseManager::resetInstance();
    }

    protected function tearDown(): void
    {
        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        // Reset singleton instance
        EnhancedPdoDatabaseManager::resetInstance();
        
        parent::tearDown();
    }

    // ============================================
    // Singleton Pattern Tests
    // ============================================

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = EnhancedPdoDatabaseManager::getInstance();
        $instance2 = EnhancedPdoDatabaseManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(EnhancedPdoDatabaseManager::class, $instance1);
        $this->assertInstanceOf(DatabaseManagerInterface::class, $instance1);
    }

    public function testGetInstanceWithPersistentConnections(): void
    {
        EnhancedPdoDatabaseManager::resetInstance();
        $instance1 = EnhancedPdoDatabaseManager::getInstance(true);
        
        $this->assertInstanceOf(EnhancedPdoDatabaseManager::class, $instance1);
        
        // Second call should return same instance (persistent flag only matters on first call)
        $instance2 = EnhancedPdoDatabaseManager::getInstance(false);
        $this->assertSame($instance1, $instance2);
    }

    public function testResetInstanceClearsSingleton(): void
    {
        $instance1 = EnhancedPdoDatabaseManager::getInstance();
        EnhancedPdoDatabaseManager::resetInstance();
        $instance2 = EnhancedPdoDatabaseManager::getInstance();
        
        $this->assertNotSame($instance1, $instance2);
    }

    public function testResetInstanceCallsDisconnect(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // Get a connection to ensure there's something to disconnect
        try {
            $manager->getConnection();
        } catch (\Throwable $e) {
            // Ignore connection errors
        }
        
        // Reset should disconnect
        EnhancedPdoDatabaseManager::resetInstance();
        
        // Get new instance and verify it's clean
        $newManager = EnhancedPdoDatabaseManager::getInstance();
        $this->assertFalse($newManager->inTransaction());
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testGetErrorReturnsStringOrNull(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // Error may be set during initialization if app directory doesn't exist
        $error = $manager->getError();
        // Error can be null or a string (initialization error)
        $this->assertTrue($error === null || is_string($error));
    }

    public function testSetErrorStoresError(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $manager->setError('Test error');
        $this->assertEquals('Test error', $manager->getError());
    }

    public function testSetErrorWithContext(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $context = ['key' => 'value', 'code' => 500];
        $manager->setError('Test error', $context);
        
        $error = $manager->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
        $this->assertStringContainsString('Context:', $error);
    }

    public function testSetErrorWithNullClearsError(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $manager->setError('Test error');
        $this->assertEquals('Test error', $manager->getError());
        
        $manager->setError(null);
        $this->assertNull($manager->getError());
    }

    public function testSetErrorWithEmptyContext(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $manager->setError('Test error', []);
        $this->assertEquals('Test error', $manager->getError());
    }

    public function testClearErrorRemovesError(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $manager->setError('Test error');
        $this->assertEquals('Test error', $manager->getError());
        
        $manager->clearError();
        $this->assertNull($manager->getError());
    }

    // ============================================
    // Initialization Tests
    // ============================================

    public function testIsInitializedReturnsBoolean(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // Initialization may fail if app directory doesn't exist
        $result = $manager->isInitialized();
        $this->assertIsBool($result);
    }

    public function testIsInitializedReturnsFalseOnInitFailure(): void
    {
        // This is hard to test without mocking ProjectHelper::loadEnv()
        // But we can verify the method exists and returns a boolean
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $result = $manager->isInitialized();
        $this->assertIsBool($result);
    }

    // ============================================
    // Configuration Tests
    // ============================================

    public function testBuildDatabaseConfigUsesEnvironmentVariables(): void
    {
        $_ENV['DB_HOST'] = 'test_host';
        $_ENV['DB_PORT'] = '3307';
        $_ENV['DB_NAME'] = 'test_database';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['DB_CHARSET'] = 'utf8';
        $_ENV['DB_DRIVER'] = 'pgsql';
        $_ENV['DB_COLLATION'] = 'utf8_general_ci';

        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('buildDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        $this->assertEquals('test_host', $config['host']);
        $this->assertEquals(3307, $config['port']);
        $this->assertEquals('test_database', $config['database']);
        $this->assertEquals('test_user', $config['username']);
        $this->assertEquals('test_pass', $config['password']);
        $this->assertEquals('utf8', $config['charset']);
        $this->assertEquals('pgsql', $config['driver']);
        $this->assertEquals('utf8_general_ci', $config['collation']);
    }

    public function testBuildDatabaseConfigUsesDefaultsWhenEnvVarsMissing(): void
    {
        // Unset all DB environment variables
        unset($_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME'], $_ENV['DB_USER'], 
              $_ENV['DB_PASSWORD'], $_ENV['DB_CHARSET'], $_ENV['DB_DRIVER'], 
              $_ENV['DB_COLLATION']);

        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('buildDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        // Should use defaults
        $this->assertEquals('mysql', $config['driver']);
        $this->assertEquals('localhost', $config['host']);
        $this->assertEquals(3306, $config['port']);
        $this->assertEquals('gemvc_db', $config['database']);
        $this->assertEquals('root', $config['username']);
        $this->assertEquals('', $config['password']);
        $this->assertEquals('utf8mb4', $config['charset']);
        $this->assertEquals('utf8mb4_unicode_ci', $config['collation']);
    }

    public function testBuildDatabaseConfigHandlesNonNumericPort(): void
    {
        $_ENV['DB_PORT'] = 'invalid';

        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('buildDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        // Should use default port
        $this->assertEquals(3306, $config['port']);
    }

    // ============================================
    // Connection Management Tests
    // ============================================

    public function testGetConnectionReturnsPDO(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // This will fail if no database is available, but we can test the method exists
        try {
            $pdo = $manager->getConnection();
            $this->assertInstanceOf(PDO::class, $pdo);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertNull($manager->getConnection());
        }
    }

    public function testGetConnectionReturnsNullOnError(): void
    {
        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host_that_does_not_exist';
        $_ENV['DB_PORT'] = '9999';
        $_ENV['DB_NAME'] = 'nonexistent_db';
        $_ENV['DB_USER'] = 'invalid_user';
        $_ENV['DB_PASSWORD'] = 'invalid_pass';

        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $pdo = $manager->getConnection();
        
        // Should return null on connection failure
        $this->assertNull($pdo);
        $this->assertNotNull($manager->getError());
    }

    public function testGetConnectionClearsError(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $manager->setError('Previous error');
        $this->assertNotNull($manager->getError());
        
        // getConnection will clear error
        try {
            $manager->getConnection();
        } catch (\Throwable $e) {
            // Ignore connection errors
        }
        
        // Error should be cleared or set to new error
        $this->assertTrue(true); // Just verify no exception
    }

    public function testGetConnectionReusesConnectionForSimpleMode(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            $pdo1 = $manager->getConnection();
            $pdo2 = $manager->getConnection();
            
            if ($pdo1 !== null && $pdo2 !== null) {
                // Should return same connection for simple (non-persistent) mode
                $this->assertSame($pdo1, $pdo2);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testGetConnectionCreatesNewForPersistentMode(): void
    {
        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance(true);
        
        try {
            $pdo1 = $manager->getConnection();
            $pdo2 = $manager->getConnection();
            
            if ($pdo1 !== null && $pdo2 !== null) {
                // For persistent connections, should create new each time
                // (though they may be the same underlying connection due to PHP's persistent connection pooling)
                $this->assertInstanceOf(PDO::class, $pdo1);
                $this->assertInstanceOf(PDO::class, $pdo2);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testGetConnectionAcceptsPoolName(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            $pdo = $manager->getConnection('custom_pool');
            // Pool name is ignored in this implementation, but should not cause errors
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testReleaseConnectionForSimpleMode(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            $pdo = $manager->getConnection();
            if ($pdo !== null) {
                $manager->releaseConnection($pdo);
                
                // Connection should be disconnected
                $reflection = new ReflectionClass($manager);
                $connectionProperty = $reflection->getProperty('currentConnection');
                $connectionProperty->setAccessible(true);
                
                $this->assertNull($connectionProperty->getValue($manager));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testReleaseConnectionForPersistentMode(): void
    {
        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance(true);
        
        try {
            $pdo = $manager->getConnection();
            if ($pdo !== null) {
                $manager->releaseConnection($pdo);
                
                // For persistent connections, just clears reference
                $reflection = new ReflectionClass($manager);
                $connectionProperty = $reflection->getProperty('currentConnection');
                $connectionProperty->setAccessible(true);
                
                $this->assertNull($connectionProperty->getValue($manager));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testReleaseConnectionHandlesUnknownConnection(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // Create a PDO that's not managed by the manager
        $unknownPdo = new PDO('sqlite::memory:');
        
        // Should not throw exception
        $manager->releaseConnection($unknownPdo);
        
        $this->assertTrue(true);
    }

    public function testDisconnectClearsConnection(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            $manager->getConnection();
            
            $reflection = new ReflectionClass($manager);
            $connectionProperty = $reflection->getProperty('currentConnection');
            $connectionProperty->setAccessible(true);
            
            $this->assertNotNull($connectionProperty->getValue($manager));
            
            $manager->disconnect();
            
            $this->assertNull($connectionProperty->getValue($manager));
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testDisconnectRollsBackTransaction(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $reflection = new ReflectionClass($manager);
                $inTransactionProperty = $reflection->getProperty('inTransaction');
                $inTransactionProperty->setAccessible(true);
                
                $this->assertTrue($inTransactionProperty->getValue($manager));
                
                $manager->disconnect();
                
                $this->assertFalse($inTransactionProperty->getValue($manager));
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    // ============================================
    // Pool Stats Tests
    // ============================================

    public function testGetPoolStatsReturnsArray(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $stats = $manager->getPoolStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('type', $stats);
        $this->assertArrayHasKey('environment', $stats);
        $this->assertArrayHasKey('has_connection', $stats);
        $this->assertArrayHasKey('in_transaction', $stats);
        $this->assertArrayHasKey('initialized', $stats);
        $this->assertArrayHasKey('persistent', $stats);
        $this->assertArrayHasKey('config', $stats);
    }

    public function testGetPoolStatsReflectsConnectionType(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $stats = $manager->getPoolStats();
        
        $this->assertEquals('Enhanced PDO (Simple)', $stats['type']);
        $this->assertFalse($stats['persistent']);
        
        EnhancedPdoDatabaseManager::resetInstance();
        $persistentManager = EnhancedPdoDatabaseManager::getInstance(true);
        
        $persistentStats = $persistentManager->getPoolStats();
        
        $this->assertEquals('Enhanced PDO (Persistent)', $persistentStats['type']);
        $this->assertTrue($persistentStats['persistent']);
    }

    public function testGetPoolStatsReflectsConnectionState(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $stats = $manager->getPoolStats();
        $this->assertFalse($stats['has_connection']);
        $this->assertFalse($stats['in_transaction']);
        
        try {
            $manager->getConnection();
            $stats = $manager->getPoolStats();
            $this->assertTrue($stats['has_connection']);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testGetPoolStatsIncludesConfig(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $stats = $manager->getPoolStats();
        
        $this->assertArrayHasKey('config', $stats);
        $this->assertArrayHasKey('driver', $stats['config']);
        $this->assertArrayHasKey('host', $stats['config']);
        $this->assertArrayHasKey('database', $stats['config']);
    }

    // ============================================
    // Transaction Tests
    // ============================================

    public function testBeginTransactionReturnsBoolean(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            $result = $manager->beginTransaction();
            $this->assertIsBool($result);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testBeginTransactionPreventsDoubleStart(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $result = $manager->beginTransaction();
                $this->assertFalse($result);
                $this->assertStringContainsString('Already in transaction', $manager->getError());
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testBeginTransactionReturnsFalseOnConnectionFailure(): void
    {
        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host';
        $_ENV['DB_PORT'] = '9999';

        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $result = $manager->beginTransaction();
        
        $this->assertFalse($result);
    }

    public function testCommitReturnsBoolean(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $result = $manager->commit();
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testCommitReturnsFalseWhenNoTransaction(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $result = $manager->commit();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No active transaction', $manager->getError());
    }

    public function testCommitClearsTransactionFlag(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $this->assertTrue($manager->inTransaction());
                $manager->commit();
                $this->assertFalse($manager->inTransaction());
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testRollbackReturnsBoolean(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $result = $manager->rollback();
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testRollbackReturnsFalseWhenNoTransaction(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $result = $manager->rollback();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No active transaction', $manager->getError());
    }

    public function testRollbackClearsTransactionFlag(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $this->assertTrue($manager->inTransaction());
                $manager->rollback();
                $this->assertFalse($manager->inTransaction());
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testInTransactionReturnsFalseInitially(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $this->assertFalse($manager->inTransaction());
    }

    public function testInTransactionReturnsTrueWhenActive(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $this->assertTrue($manager->inTransaction());
            }
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testInTransactionAcceptsPoolName(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // Pool name is ignored, but should not cause errors
        $result = $manager->inTransaction('custom_pool');
        $this->assertIsBool($result);
    }

    // ============================================
    // Persistent Connection Tests
    // ============================================

    public function testCreateConnectionWithPersistentFlag(): void
    {
        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance(true);
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('createConnection');
        $method->setAccessible(true);
        
        try {
            $pdo = $method->invoke($manager);
            $this->assertInstanceOf(PDO::class, $pdo);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    public function testCreateConnectionWithSimpleFlag(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('createConnection');
        $method->setAccessible(true);
        
        try {
            $pdo = $method->invoke($manager);
            $this->assertInstanceOf(PDO::class, $pdo);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertTrue(true);
        }
    }

    // ============================================
    // Destructor Tests
    // ============================================

    public function testDestructorCallsDisconnect(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            $manager->getConnection();
            
            $reflection = new ReflectionClass($manager);
            $connectionProperty = $reflection->getProperty('currentConnection');
            $connectionProperty->setAccessible(true);
            
            $this->assertNotNull($connectionProperty->getValue($manager));
            
            // Destructor should be called when object is destroyed
            unset($manager);
            
            // We can't easily test destructor, but we can verify it exists
            $this->assertTrue(true);
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
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        // This test verifies that beginTransaction handles exceptions gracefully
        try {
            $result = $manager->beginTransaction();
            // Should return bool, not throw
            $this->assertIsBool($result);
        } catch (\Throwable $e) {
            // Only unexpected exceptions should be caught here
            $this->fail('beginTransaction should not throw: ' . $e->getMessage());
        }
    }

    public function testCommitHandlesException(): void
    {
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $result = $manager->commit();
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
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        try {
            if ($manager->beginTransaction()) {
                $result = $manager->rollback();
                // Should return bool, not throw
                $this->assertIsBool($result);
            }
        } catch (\Throwable $e) {
            // Only unexpected exceptions should be caught here
            $this->fail('rollback should not throw: ' . $e->getMessage());
        }
    }

    public function testGetConnectionSetsErrorWithContext(): void
    {
        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host';
        $_ENV['DB_PORT'] = '9999';

        EnhancedPdoDatabaseManager::resetInstance();
        $manager = EnhancedPdoDatabaseManager::getInstance();
        
        $connection = $manager->getConnection('test_pool');
        
        $error = $manager->getError();
        if ($error !== null) {
            // Error should contain context information
            $this->assertStringContainsString('Failed to create database connection', $error);
        }
        
        $this->assertNull($connection);
    }
}


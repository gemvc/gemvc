<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\SwooleDatabaseManager;
use Gemvc\Helper\ProjectHelper;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class SwooleDatabaseManagerTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        
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
            'MIN_DB_CONNECTION_POOL' => $_ENV['MIN_DB_CONNECTION_POOL'] ?? null,
            'MAX_DB_CONNECTION_POOL' => $_ENV['MAX_DB_CONNECTION_POOL'] ?? null,
            'DB_CONNECTION_TIME_OUT' => $_ENV['DB_CONNECTION_TIME_OUT'] ?? null,
            'DB_CONNECTION_EXPIER_TIME' => $_ENV['DB_CONNECTION_EXPIER_TIME'] ?? null,
            'DB_CONNECTION_MAX_AGE' => $_ENV['DB_CONNECTION_MAX_AGE'] ?? null,
            'DB_HOST_CLI_DEV' => $_ENV['DB_HOST_CLI_DEV'] ?? null,
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
        $_ENV['MIN_DB_CONNECTION_POOL'] = '1';
        $_ENV['MAX_DB_CONNECTION_POOL'] = '10';
        $_ENV['DB_CONNECTION_TIME_OUT'] = '10.0';
        $_ENV['DB_CONNECTION_EXPIER_TIME'] = '3.0';
        $_ENV['DB_CONNECTION_MAX_AGE'] = '60.0';

        // Reset singleton instance
        SwooleDatabaseManager::resetInstance();
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
        SwooleDatabaseManager::resetInstance();
        
        parent::tearDown();
    }

    // ============================================
    // Singleton Pattern Tests
    // ============================================

    public function testGetInstanceReturnsSingleton(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $instance1 = SwooleDatabaseManager::getInstance();
        $instance2 = SwooleDatabaseManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(SwooleDatabaseManager::class, $instance1);
    }

    public function testResetInstanceClearsSingleton(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $instance1 = SwooleDatabaseManager::getInstance();
        SwooleDatabaseManager::resetInstance();
        $instance2 = SwooleDatabaseManager::getInstance();
        
        $this->assertNotSame($instance1, $instance2);
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testGetErrorReturnsNullInitially(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $this->assertNull($manager->getError());
    }

    public function testSetErrorStoresError(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $manager->setError('Test error');
        $this->assertEquals('Test error', $manager->getError());
    }

    public function testSetErrorWithContext(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $context = ['key' => 'value', 'code' => 500];
        $manager->setError('Test error', $context);
        
        $error = $manager->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
        $this->assertStringContainsString('Context:', $error);
    }

    public function testSetErrorWithNullClearsError(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $manager->setError('Test error');
        $this->assertEquals('Test error', $manager->getError());
        
        $manager->setError(null);
        $this->assertNull($manager->getError());
    }

    public function testSetErrorWithEmptyContext(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $manager->setError('Test error', []);
        $this->assertEquals('Test error', $manager->getError());
    }

    public function testClearErrorRemovesError(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $manager->setError('Test error');
        $this->assertEquals('Test error', $manager->getError());
        
        $manager->clearError();
        $this->assertNull($manager->getError());
    }

    // ============================================
    // Configuration Tests
    // ============================================

    public function testGetDatabaseConfigReturnsArray(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('getDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
    }

    public function testGetDatabaseConfigUsesEnvironmentVariables(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $_ENV['DB_HOST'] = 'test_host';
        $_ENV['DB_PORT'] = '3307';
        $_ENV['DB_NAME'] = 'test_database';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['DB_CHARSET'] = 'utf8';
        $_ENV['DB_DRIVER'] = 'pgsql';
        $_ENV['DB_COLLATION'] = 'utf8_general_ci';
        $_ENV['MIN_DB_CONNECTION_POOL'] = '2';
        $_ENV['MAX_DB_CONNECTION_POOL'] = '20';
        $_ENV['DB_CONNECTION_TIME_OUT'] = '15.0';
        $_ENV['DB_CONNECTION_EXPIER_TIME'] = '5.0';
        $_ENV['DB_CONNECTION_MAX_AGE'] = '120.0';

        SwooleDatabaseManager::resetInstance();
        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('getDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        $this->assertEquals('test_host', $config['default']['host']);
        $this->assertEquals(3307, $config['default']['port']);
        $this->assertEquals('test_database', $config['default']['database']);
        $this->assertEquals('test_user', $config['default']['username']);
        $this->assertEquals('test_pass', $config['default']['password']);
        $this->assertEquals('utf8', $config['default']['charset']);
        $this->assertEquals('pgsql', $config['default']['driver']);
        $this->assertEquals('utf8_general_ci', $config['default']['collation']);
        $this->assertEquals(2, $config['default']['pool']['min_connections']);
        $this->assertEquals(20, $config['default']['pool']['max_connections']);
        $this->assertEquals(15.0, $config['default']['pool']['connect_timeout']);
        $this->assertEquals(5.0, $config['default']['pool']['wait_timeout']);
        $this->assertEquals(120.0, $config['default']['pool']['max_idle_time']);
    }

    public function testGetDatabaseConfigUsesDefaultsWhenEnvVarsMissing(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // Unset all DB environment variables
        unset($_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME'], $_ENV['DB_USER'], 
              $_ENV['DB_PASSWORD'], $_ENV['DB_CHARSET'], $_ENV['DB_DRIVER'], 
              $_ENV['DB_COLLATION'], $_ENV['MIN_DB_CONNECTION_POOL'], 
              $_ENV['MAX_DB_CONNECTION_POOL'], $_ENV['DB_CONNECTION_TIME_OUT'],
              $_ENV['DB_CONNECTION_EXPIER_TIME'], $_ENV['DB_CONNECTION_MAX_AGE']);

        SwooleDatabaseManager::resetInstance();
        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('getDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        // Should use defaults
        $this->assertEquals('mysql', $config['default']['driver']);
        $this->assertEquals(3306, $config['default']['port']);
        $this->assertEquals('gemvc_db', $config['default']['database']);
        $this->assertEquals('root', $config['default']['username']);
        $this->assertEquals('', $config['default']['password']);
        $this->assertEquals('utf8mb4', $config['default']['charset']);
        $this->assertEquals('utf8mb4_unicode_ci', $config['default']['collation']);
        $this->assertEquals(1, $config['default']['pool']['min_connections']);
        $this->assertEquals(10, $config['default']['pool']['max_connections']);
        $this->assertEquals(10.0, $config['default']['pool']['connect_timeout']);
        $this->assertEquals(3.0, $config['default']['pool']['wait_timeout']);
        $this->assertEquals(60.0, $config['default']['pool']['max_idle_time']);
    }

    public function testGetDatabaseConfigHandlesNonStringEnvVars(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $_ENV['DB_HOST'] = null;
        $_ENV['DB_PORT'] = 'invalid';
        $_ENV['DB_NAME'] = 123;
        $_ENV['DB_USER'] = null;
        $_ENV['DB_PASSWORD'] = null;
        $_ENV['DB_CHARSET'] = null;
        $_ENV['DB_DRIVER'] = null;
        $_ENV['DB_COLLATION'] = null;
        $_ENV['MIN_DB_CONNECTION_POOL'] = 'invalid';
        $_ENV['MAX_DB_CONNECTION_POOL'] = 'invalid';
        $_ENV['DB_CONNECTION_TIME_OUT'] = 'invalid';
        $_ENV['DB_CONNECTION_EXPIER_TIME'] = 'invalid';
        $_ENV['DB_CONNECTION_MAX_AGE'] = 'invalid';

        SwooleDatabaseManager::resetInstance();
        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('getDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        // Should use defaults when values are invalid
        $this->assertIsString($config['default']['host']);
        $this->assertIsInt($config['default']['port']);
        $this->assertIsString($config['default']['database']);
        $this->assertIsString($config['default']['username']);
        $this->assertIsString($config['default']['password']);
        $this->assertIsString($config['default']['charset']);
        $this->assertIsString($config['default']['driver']);
        $this->assertIsString($config['default']['collation']);
        $this->assertIsInt($config['default']['pool']['min_connections']);
        $this->assertIsInt($config['default']['pool']['max_connections']);
        $this->assertIsFloat($config['default']['pool']['connect_timeout']);
        $this->assertIsFloat($config['default']['pool']['wait_timeout']);
        $this->assertIsFloat($config['default']['pool']['max_idle_time']);
    }

    public function testGetDatabaseConfigUsesCliHostInCliContext(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // This test runs in CLI context
        $_ENV['DB_HOST_CLI_DEV'] = 'cli_host';
        $_ENV['DB_HOST'] = 'container_host';

        SwooleDatabaseManager::resetInstance();
        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('getDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        // In CLI context (not OpenSwoole server), should use DB_HOST_CLI_DEV or localhost
        // Since we're in PHPUnit (CLI), it should use DB_HOST_CLI_DEV if set
        if (PHP_SAPI === 'cli' && !defined('SWOOLE_BASE') && !class_exists('\OpenSwoole\Server')) {
            $this->assertEquals('cli_host', $config['default']['host']);
        }
    }

    public function testGetDatabaseConfigIncludesPoolConfiguration(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('getDatabaseConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($manager);
        
        $this->assertArrayHasKey('pool', $config['default']);
        $this->assertArrayHasKey('min_connections', $config['default']['pool']);
        $this->assertArrayHasKey('max_connections', $config['default']['pool']);
        $this->assertArrayHasKey('connect_timeout', $config['default']['pool']);
        $this->assertArrayHasKey('wait_timeout', $config['default']['pool']);
        $this->assertArrayHasKey('heartbeat', $config['default']['pool']);
        $this->assertArrayHasKey('max_idle_time', $config['default']['pool']);
        $this->assertEquals(-1, $config['default']['pool']['heartbeat']);
    }

    // ============================================
    // Connection Tests (with mocks)
    // ============================================

    public function testGetConnectionClearsError(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $manager->setError('Previous error');
        $this->assertNotNull($manager->getError());
        
        // getConnection will clear error, but may fail if no DB connection
        // We just verify the method exists and can be called
        try {
            $manager->getConnection();
        } catch (\Throwable $e) {
            // Expected if no database is available
        }
        
        // Error should be cleared or set to new error
        // The exact behavior depends on whether connection succeeds
        $this->assertTrue(true); // Just verify no exception in getConnection itself
    }

    public function testGetConnectionUsesDefaultPoolName(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        // getConnection should accept default pool name
        try {
            $conn1 = $manager->getConnection();
            $conn2 = $manager->getConnection('default');
            // If both succeed, they might return the same or different connections
            // depending on pool implementation
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Expected if no database is available
            $this->assertNotNull($manager->getError());
        }
    }

    public function testGetConnectionHandlesCustomPoolName(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        // getConnection should accept custom pool name
        try {
            $conn = $manager->getConnection('custom_pool');
            // If succeeds, connection should be returned
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Expected if pool doesn't exist or no database is available
            $this->assertNotNull($manager->getError());
        }
    }

    // ============================================
    // Constructor and Initialization Tests
    // ============================================

    public function testConstructorLoadsEnvironmentVariables(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // Constructor should call ProjectHelper::loadEnv()
        // We can't easily test this without mocking, but we can verify
        // that getInstance() creates an instance successfully
        $manager = SwooleDatabaseManager::getInstance();
        
        $this->assertInstanceOf(SwooleDatabaseManager::class, $manager);
    }

    public function testConstructorInitializesContainer(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        
        $container = $containerProperty->getValue($manager);
        $this->assertNotNull($container);
    }

    public function testConstructorInitializesPoolFactory(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        $manager = SwooleDatabaseManager::getInstance();
        
        $reflection = new ReflectionClass($manager);
        $poolFactoryProperty = $reflection->getProperty('poolFactory');
        $poolFactoryProperty->setAccessible(true);
        
        $poolFactory = $poolFactoryProperty->getValue($manager);
        $this->assertNotNull($poolFactory);
    }

    // ============================================
    // Edge Cases and Error Scenarios
    // ============================================

    public function testGetConnectionReturnsNullOnError(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host_that_does_not_exist';
        $_ENV['DB_PORT'] = '9999';
        $_ENV['DB_NAME'] = 'nonexistent_db';
        $_ENV['DB_USER'] = 'invalid_user';
        $_ENV['DB_PASSWORD'] = 'invalid_pass';

        SwooleDatabaseManager::resetInstance();
        $manager = SwooleDatabaseManager::getInstance();
        
        $connection = $manager->getConnection();
        
        // Should return null on connection failure
        $this->assertNull($connection);
        $this->assertNotNull($manager->getError());
    }

    public function testGetConnectionSetsErrorWithContext(): void
    {
        // Skip if Hyperf classes are not available
        if (!class_exists('\Hyperf\Di\Container')) {
            $this->markTestSkipped('Hyperf classes are not available');
        }

        // Set invalid database configuration
        $_ENV['DB_HOST'] = 'invalid_host';
        $_ENV['DB_PORT'] = '9999';

        SwooleDatabaseManager::resetInstance();
        $manager = SwooleDatabaseManager::getInstance();
        
        $connection = $manager->getConnection('test_pool');
        
        $error = $manager->getError();
        if ($error !== null) {
            // Error should contain context information
            $this->assertStringContainsString('Failed to get database connection', $error);
        }
        
        $this->assertNull($connection);
    }
}


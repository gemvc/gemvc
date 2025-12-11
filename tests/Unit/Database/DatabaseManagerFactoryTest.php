<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\DatabaseManagerFactory;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Pdo\PdoConnection;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;

/**
 * @outputBuffering enabled
 */
class DatabaseManagerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        // Reset factory before each test
        DatabaseManagerFactory::resetInstance();
        // Reset package singletons
        PdoConnection::resetInstance();
        SwooleConnection::resetInstance();
    }
    
    protected function tearDown(): void
    {
        DatabaseManagerFactory::resetInstance();
        PdoConnection::resetInstance();
        SwooleConnection::resetInstance();
        parent::tearDown();
    }
    
    // ============================================
    // Singleton Pattern Tests
    // ============================================
    
    public function testGetManagerReturnsSameInstance(): void
    {
        $manager1 = DatabaseManagerFactory::getManager();
        $manager2 = DatabaseManagerFactory::getManager();
        
        $this->assertSame($manager1, $manager2);
    }
    
    public function testGetManagerReturnsConnectionManagerInterface(): void
    {
        $manager = DatabaseManagerFactory::getManager();
        $this->assertInstanceOf(ConnectionManagerInterface::class, $manager);
    }
    
    // ============================================
    // Reset Instance Tests
    // ============================================
    
    public function testResetInstance(): void
    {
        $manager1 = DatabaseManagerFactory::getManager();
        DatabaseManagerFactory::resetInstance();
        $manager2 = DatabaseManagerFactory::getManager();
        
        // Should be different instances after reset
        $this->assertNotSame($manager1, $manager2);
    }
    
    public function testResetInstanceClearsCache(): void
    {
        // Get manager to initialize cache
        DatabaseManagerFactory::getManager();
        
        // Reset
        DatabaseManagerFactory::resetInstance();
        
        // Get manager again - should create new instance
        $manager = DatabaseManagerFactory::getManager();
        $this->assertInstanceOf(ConnectionManagerInterface::class, $manager);
    }
    
    // ============================================
    // Environment Detection Tests
    // ============================================
    
    public function testGetManagerInfoReturnsArray(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertIsArray($info);
    }
    
    public function testGetManagerInfoContainsEnvironment(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('environment', $info);
        $this->assertContains($info['environment'], ['swoole', 'apache', 'nginx']);
    }
    
    public function testGetManagerInfoContainsManagerClass(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('manager_class', $info);
        $this->assertIsString($info['manager_class']);
    }
    
    public function testGetManagerInfoContainsPoolStats(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('pool_stats', $info);
        $this->assertIsArray($info['pool_stats']);
    }
    
    public function testGetManagerInfoContainsInitialized(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('initialized', $info);
        $this->assertIsBool($info['initialized']);
    }
    
    public function testGetManagerInfoContainsHasError(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('has_error', $info);
        $this->assertIsBool($info['has_error']);
    }
    
    public function testGetManagerInfoContainsError(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('error', $info);
        // Error can be null or string
        $this->assertTrue($info['error'] === null || is_string($info['error']));
    }
    
    public function testGetManagerInfoContainsDetectionCached(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('detection_cached', $info);
        $this->assertIsBool($info['detection_cached']);
    }
    
    public function testGetManagerInfoContainsPerformanceMode(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('performance_mode', $info);
        $this->assertEquals('optimized', $info['performance_mode']);
    }
    
    public function testGetManagerInfoContainsPdoConfigForApache(): void
    {
        // Force apache environment
        $_ENV['WEBSERVER_TYPE'] = 'apache';
        DatabaseManagerFactory::resetInstance();
        
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('pdo_config', $info);
        $this->assertIsArray($info['pdo_config']);
        $this->assertArrayHasKey('persistent_connections', $info['pdo_config']);
        $this->assertArrayHasKey('persistent_enabled', $info['pdo_config']);
        $this->assertArrayHasKey('implementation', $info['pdo_config']);
        
        unset($_ENV['WEBSERVER_TYPE']);
    }
    
    public function testGetManagerInfoContainsPdoConfigForNginx(): void
    {
        // Force nginx environment
        $_ENV['WEBSERVER_TYPE'] = 'nginx';
        DatabaseManagerFactory::resetInstance();
        
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertArrayHasKey('pdo_config', $info);
        $this->assertIsArray($info['pdo_config']);
        
        unset($_ENV['WEBSERVER_TYPE']);
    }
    
    public function testGetManagerInfoPdoConfigPersistentConnectionsEnabled(): void
    {
        // Force apache environment with persistent connections
        $_ENV['WEBSERVER_TYPE'] = 'apache';
        $_ENV['DB_PERSISTENT_CONNECTIONS'] = '1';
        DatabaseManagerFactory::resetInstance();
        
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertEquals('1', $info['pdo_config']['persistent_connections']);
        $this->assertTrue($info['pdo_config']['persistent_enabled']);
        $this->assertStringContainsString('PdoConnection', $info['pdo_config']['implementation']);
        
        unset($_ENV['WEBSERVER_TYPE']);
        unset($_ENV['DB_PERSISTENT_CONNECTIONS']);
    }
    
    public function testGetManagerInfoPdoConfigPersistentConnectionsDisabled(): void
    {
        // Force apache environment without persistent connections
        $_ENV['WEBSERVER_TYPE'] = 'apache';
        $_ENV['DB_PERSISTENT_CONNECTIONS'] = '0';
        DatabaseManagerFactory::resetInstance();
        
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertEquals('0', $info['pdo_config']['persistent_connections']);
        $this->assertFalse($info['pdo_config']['persistent_enabled']);
        $this->assertStringContainsString('PdoConnection', $info['pdo_config']['implementation']);
        
        unset($_ENV['WEBSERVER_TYPE']);
        unset($_ENV['DB_PERSISTENT_CONNECTIONS']);
    }
    
    public function testGetManagerInfoPdoConfigPersistentConnectionsTrue(): void
    {
        // Force apache environment with persistent connections as 'true'
        $_ENV['WEBSERVER_TYPE'] = 'apache';
        $_ENV['DB_PERSISTENT_CONNECTIONS'] = 'true';
        DatabaseManagerFactory::resetInstance();
        
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertTrue($info['pdo_config']['persistent_enabled']);
        $this->assertStringContainsString('PdoConnection', $info['pdo_config']['implementation']);
        
        unset($_ENV['WEBSERVER_TYPE']);
        unset($_ENV['DB_PERSISTENT_CONNECTIONS']);
    }
    
    public function testGetManagerInfoPdoConfigPersistentConnectionsYes(): void
    {
        // Force apache environment with persistent connections as 'yes'
        $_ENV['WEBSERVER_TYPE'] = 'apache';
        $_ENV['DB_PERSISTENT_CONNECTIONS'] = 'yes';
        DatabaseManagerFactory::resetInstance();
        
        $info = DatabaseManagerFactory::getManagerInfo();
        $this->assertTrue($info['pdo_config']['persistent_enabled']);
        $this->assertStringContainsString('PdoConnection', $info['pdo_config']['implementation']);
        
        unset($_ENV['WEBSERVER_TYPE']);
        unset($_ENV['DB_PERSISTENT_CONNECTIONS']);
    }
    
    // ============================================
    // Force Detection Tests
    // ============================================
    
    public function testForceDetectionReturnsString(): void
    {
        $environment = DatabaseManagerFactory::forceDetection();
        $this->assertIsString($environment);
        $this->assertContains($environment, ['swoole', 'apache', 'nginx']);
    }
    
    public function testForceDetectionBypassesCache(): void
    {
        // Get manager to initialize cache
        DatabaseManagerFactory::getManager();
        
        // Force detection should bypass cache
        $environment = DatabaseManagerFactory::forceDetection();
        $this->assertIsString($environment);
    }
    
    // ============================================
    // Performance Metrics Tests
    // ============================================
    
    public function testGetPerformanceMetricsReturnsArray(): void
    {
        $metrics = DatabaseManagerFactory::getPerformanceMetrics();
        $this->assertIsArray($metrics);
    }
    
    public function testGetPerformanceMetricsContainsDetectionTime(): void
    {
        $metrics = DatabaseManagerFactory::getPerformanceMetrics();
        $this->assertArrayHasKey('detection_time_ms', $metrics);
        $this->assertIsFloat($metrics['detection_time_ms']);
    }
    
    public function testGetPerformanceMetricsContainsEnvironment(): void
    {
        $metrics = DatabaseManagerFactory::getPerformanceMetrics();
        $this->assertArrayHasKey('environment', $metrics);
        $this->assertContains($metrics['environment'], ['swoole', 'apache', 'nginx']);
    }
    
    public function testGetPerformanceMetricsContainsCached(): void
    {
        $metrics = DatabaseManagerFactory::getPerformanceMetrics();
        $this->assertArrayHasKey('cached', $metrics);
        $this->assertIsBool($metrics['cached']);
    }
    
    public function testGetPerformanceMetricsContainsDetectionCached(): void
    {
        $metrics = DatabaseManagerFactory::getPerformanceMetrics();
        $this->assertArrayHasKey('detection_cached', $metrics);
        $this->assertIsBool($metrics['detection_cached']);
    }
    
    public function testGetPerformanceMetricsContainsPerformanceLevel(): void
    {
        $metrics = DatabaseManagerFactory::getPerformanceMetrics();
        $this->assertArrayHasKey('performance_level', $metrics);
        $this->assertContains($metrics['performance_level'], ['excellent', 'good', 'needs_optimization']);
    }
    
    // ============================================
    // Default Environment Tests
    // ============================================
    
    public function testDefaultEnvironmentIsApache(): void
    {
        // Clear any environment overrides
        unset($_ENV['WEBSERVER_TYPE']);
        unset($_SERVER['SERVER_SOFTWARE']);
        
        DatabaseManagerFactory::resetInstance();
        $manager = DatabaseManagerFactory::getManager();
        
        // Should default to PdoConnection (Apache/Nginx default)
        $this->assertInstanceOf(ConnectionManagerInterface::class, $manager);
    }
    
    // ============================================
    // Manager Creation Tests
    // ============================================
    
    public function testGetManagerCreatesPdoConnectionByDefault(): void
    {
        // Ensure default environment
        unset($_ENV['WEBSERVER_TYPE']);
        unset($_ENV['DB_PERSISTENT_CONNECTIONS']);
        
        DatabaseManagerFactory::resetInstance();
        $manager = DatabaseManagerFactory::getManager();
        
        // Should be PdoConnection
        $this->assertInstanceOf(PdoConnection::class, $manager);
    }
    
    public function testGetManagerUsesCachedEnvironment(): void
    {
        // Get manager twice - should use cached environment
        $manager1 = DatabaseManagerFactory::getManager();
        $manager2 = DatabaseManagerFactory::getManager();
        
        // Should be same instance (singleton)
        $this->assertSame($manager1, $manager2);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testGetManagerInfoHandlesManagerErrors(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        
        // Should have error-related keys even if no error
        $this->assertArrayHasKey('has_error', $info);
        $this->assertArrayHasKey('error', $info);
    }
    
    // ============================================
    // Integration Tests
    // ============================================
    
    public function testGetManagerInfoIntegration(): void
    {
        $info = DatabaseManagerFactory::getManagerInfo();
        
        // Verify all required keys exist
        $requiredKeys = [
            'environment',
            'manager_class',
            'pool_stats',
            'initialized',
            'has_error',
            'error',
            'detection_cached',
            'performance_mode'
        ];
        
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $info, "Missing key: {$key}");
        }
    }
    
    public function testForceDetectionAndGetManagerInfo(): void
    {
        // Force detection
        $environment = DatabaseManagerFactory::forceDetection();
        
        // Get info
        $info = DatabaseManagerFactory::getManagerInfo();
        
        // Environment should match
        $this->assertEquals($environment, $info['environment']);
    }
    
    public function testResetInstanceAndGetManager(): void
    {
        // Get initial manager
        $manager1 = DatabaseManagerFactory::getManager();
        $info1 = DatabaseManagerFactory::getManagerInfo();
        
        // Reset
        DatabaseManagerFactory::resetInstance();
        
        // Get new manager
        $manager2 = DatabaseManagerFactory::getManager();
        $info2 = DatabaseManagerFactory::getManagerInfo();
        
        // Should be different instances
        $this->assertNotSame($manager1, $manager2);
        
        // But environment should be same (unless changed)
        $this->assertEquals($info1['environment'], $info2['environment']);
    }
}


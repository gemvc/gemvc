<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\WebHelper;

class WebHelperTest extends TestCase
{
    private ?string $originalServerSoftware = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Save original SERVER_SOFTWARE
        $this->originalServerSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
    }
    
    protected function tearDown(): void
    {
        // Restore original SERVER_SOFTWARE
        if ($this->originalServerSoftware !== null) {
            $_SERVER['SERVER_SOFTWARE'] = $this->originalServerSoftware;
        } else {
            unset($_SERVER['SERVER_SOFTWARE']);
        }
        parent::tearDown();
    }
    
    // ============================================
    // detectWebServer Tests
    // ============================================
    
    public function testDetectWebServerReturnsArray(): void
    {
        $result = WebHelper::detectWebServer();
        $this->assertIsArray($result);
    }
    
    public function testDetectWebServerReturnsExpectedKeys(): void
    {
        $result = WebHelper::detectWebServer();
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('swoole_available', $result);
        $this->assertArrayHasKey('openswoole_available', $result);
        $this->assertArrayHasKey('capabilities', $result);
    }
    
    public function testDetectWebServerReturnsSwooleAvailability(): void
    {
        $result = WebHelper::detectWebServer();
        $this->assertIsBool($result['swoole_available']);
        $this->assertIsBool($result['openswoole_available']);
    }
    
    public function testDetectWebServerReturnsCapabilitiesArray(): void
    {
        $result = WebHelper::detectWebServer();
        $this->assertIsArray($result['capabilities']);
    }
    
    public function testDetectWebServerWithApacheServerSoftware(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41 (Unix)';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('apache', $result['name']);
        $this->assertNotNull($result['version']);
        $this->assertIsArray($result['capabilities']);
    }
    
    public function testDetectWebServerWithApacheVersionExtraction(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('apache', $result['name']);
        $this->assertEquals('2.4.41', $result['version']);
    }
    
    public function testDetectWebServerWithNginxServerSoftware(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18.0';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('nginx', $result['name']);
        $this->assertNotNull($result['version']);
    }
    
    public function testDetectWebServerWithNginxVersionExtraction(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.20.1';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('nginx', $result['name']);
        $this->assertEquals('1.20.1', $result['version']);
    }
    
    public function testDetectWebServerWithSwooleInServerSoftware(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'swoole-http-server';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('swoole', $result['name']);
        $this->assertArrayHasKey('websockets', $result['capabilities']);
        $this->assertArrayHasKey('async', $result['capabilities']);
    }
    
    public function testDetectWebServerWithSwooleExtensionAvailable(): void
    {
        // Test when Swoole extension is available
        // Note: This depends on actual extension availability
        $_SERVER['SERVER_SOFTWARE'] = null;
        $result = WebHelper::detectWebServer();
        
        // If Swoole is available, it should be detected
        if ($result['swoole_available'] || $result['openswoole_available']) {
            $this->assertContains($result['name'], ['swoole', 'cli']);
        } else {
            // If Swoole is not available, name should be 'cli' or null
            $this->assertContains($result['name'], ['cli', null]);
        }
        
        // Always verify structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
    }
    
    public function testDetectWebServerWithNoServerSoftware(): void
    {
        unset($_SERVER['SERVER_SOFTWARE']);
        $result = WebHelper::detectWebServer();
        
        // Should still return array structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        
        // In CLI mode, name should be 'cli' or 'swoole' if extension available
        if (PHP_SAPI === 'cli') {
            $this->assertContains($result['name'], ['cli', 'swoole', null]);
        }
    }
    
    public function testDetectWebServerWithApacheCapabilities(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41';
        $result = WebHelper::detectWebServer();
        
        if ($result['name'] === 'apache') {
            $this->assertArrayHasKey('mod_rewrite', $result['capabilities']);
            $this->assertArrayHasKey('htaccess', $result['capabilities']);
            $this->assertArrayHasKey('gzip', $result['capabilities']);
            $this->assertArrayHasKey('ssl', $result['capabilities']);
            
            // All should be boolean values
            $this->assertIsBool($result['capabilities']['mod_rewrite']);
            $this->assertIsBool($result['capabilities']['htaccess']);
            $this->assertIsBool($result['capabilities']['gzip']);
            $this->assertIsBool($result['capabilities']['ssl']);
        }
    }
    
    public function testDetectWebServerWithNginxCapabilities(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18.0';
        $result = WebHelper::detectWebServer();
        
        if ($result['name'] === 'nginx') {
            $this->assertArrayHasKey('rewrite', $result['capabilities']);
            $this->assertArrayHasKey('gzip', $result['capabilities']);
            $this->assertArrayHasKey('ssl', $result['capabilities']);
            
            // Nginx capabilities should all be true
            $this->assertTrue($result['capabilities']['rewrite']);
            $this->assertTrue($result['capabilities']['gzip']);
            $this->assertTrue($result['capabilities']['ssl']);
        }
    }
    
    public function testDetectWebServerWithSwooleCapabilities(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'swoole-http-server';
        $result = WebHelper::detectWebServer();
        
        if ($result['name'] === 'swoole') {
            $this->assertArrayHasKey('websockets', $result['capabilities']);
            $this->assertArrayHasKey('async', $result['capabilities']);
            $this->assertArrayHasKey('hot_reload', $result['capabilities']);
            $this->assertArrayHasKey('static_files', $result['capabilities']);
            
            // All Swoole capabilities should be true
            $this->assertTrue($result['capabilities']['websockets']);
            $this->assertTrue($result['capabilities']['async']);
            $this->assertTrue($result['capabilities']['hot_reload']);
            $this->assertTrue($result['capabilities']['static_files']);
        }
    }
    
    public function testDetectWebServerWithCaseInsensitiveApacheDetection(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'APACHE/2.4.41';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('apache', $result['name']);
    }
    
    public function testDetectWebServerWithCaseInsensitiveNginxDetection(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'NGINX/1.18.0';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('nginx', $result['name']);
    }
    
    public function testDetectWebServerWithCaseInsensitiveSwooleDetection(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'SWOOLE-HTTP-SERVER';
        $result = WebHelper::detectWebServer();
        $this->assertEquals('swoole', $result['name']);
    }
    
    public function testDetectWebServerWithUnknownServerSoftware(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'Unknown-Server/1.0';
        $result = WebHelper::detectWebServer();
        
        // Should still return array structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        
        // Name might be null or detected based on extensions
        $this->assertContains($result['name'], [null, 'swoole', 'cli']);
    }
    
    public function testDetectWebServerWithNullServerSoftware(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = null;
        $result = WebHelper::detectWebServer();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        
        // In CLI, should detect as 'cli' or 'swoole'
        if (PHP_SAPI === 'cli') {
            $this->assertContains($result['name'], ['cli', 'swoole', null]);
        }
    }
    
    public function testDetectWebServerWithEmptyServerSoftware(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = '';
        $result = WebHelper::detectWebServer();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
    }
    
    public function testDetectWebServerVersionCanBeNull(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'Apache';
        $result = WebHelper::detectWebServer();
        
        // Version might be null if pattern doesn't match
        $this->assertIsArray($result);
        $this->assertArrayHasKey('version', $result);
        // Version can be null if not found in pattern
    }
    
    public function testDetectWebServerReturnsConsistentStructure(): void
    {
        // Test that the structure is consistent across different server software
        $testCases = [
            'Apache/2.4.41',
            'nginx/1.18.0',
            'swoole-http-server',
            null
        ];
        
        foreach ($testCases as $serverSoftware) {
            if ($serverSoftware === null) {
                unset($_SERVER['SERVER_SOFTWARE']);
            } else {
                $_SERVER['SERVER_SOFTWARE'] = $serverSoftware;
            }
            
            $result = WebHelper::detectWebServer();
            
            // All should have the same structure
            $this->assertIsArray($result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('version', $result);
            $this->assertArrayHasKey('swoole_available', $result);
            $this->assertArrayHasKey('openswoole_available', $result);
            $this->assertArrayHasKey('capabilities', $result);
            $this->assertIsArray($result['capabilities']);
        }
    }
}


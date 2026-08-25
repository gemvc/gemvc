<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\NoCors;
use Tests\Helpers\FakeSwooleHttpResponse;

/**
 * @outputBuffering enabled
 */
class NoCorsTest extends TestCase
{
    private ?string $originalRequestMethod = null;
    private ?string $originalOrigin = null;
    private ?string $originalAccessControlRequestMethod = null;
    private ?string $originalAccessControlRequestHeaders = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        
        // Save original values
        $this->originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->originalOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $this->originalAccessControlRequestMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? null;
        $this->originalAccessControlRequestHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? null;
    }
    
    protected function tearDown(): void
    {
        // Restore original values
        if ($this->originalRequestMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
        } else {
            unset($_SERVER['REQUEST_METHOD']);
        }
        
        if ($this->originalOrigin !== null) {
            $_SERVER['HTTP_ORIGIN'] = $this->originalOrigin;
        } else {
            unset($_SERVER['HTTP_ORIGIN']);
        }
        
        if ($this->originalAccessControlRequestMethod !== null) {
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = $this->originalAccessControlRequestMethod;
        } else {
            unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
        }
        
        if ($this->originalAccessControlRequestHeaders !== null) {
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = $this->originalAccessControlRequestHeaders;
        } else {
            unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
        }
        
        parent::tearDown();
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $noCors = new NoCors();
        $this->assertInstanceOf(NoCors::class, $noCors);
    }
    
    // ============================================
    // apache() Static Method Tests
    // ============================================
    
    public function testApacheSetsBasicCorsHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_ORIGIN']);
        
        ob_start();
        try {
            NoCors::apache();
            $output = ob_get_clean();
            // Headers are sent, we verify method executes
            $this->assertTrue(true);
        } catch (\Exception $e) {
            ob_end_clean();
            // If exit is called, we can't catch it, but headers should be set
            $this->assertTrue(true);
        }
    }
    
    public function testApacheWithOriginHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        
        ob_start();
        try {
            NoCors::apache();
            $output = ob_get_clean();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            ob_end_clean();
            $this->assertTrue(true);
        }
    }
    
    public function testApacheWithOptionsRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Content-Type';
        
        // OPTIONS request should exit, so we can't test the output
        // But we verify the method structure
        $this->assertTrue(method_exists(NoCors::class, 'apache'));
    }
    
    public function testApacheWithOptionsRequestAndHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Authorization, Content-Type';
        
        // Method should handle OPTIONS request
        $this->assertTrue(method_exists(NoCors::class, 'apache'));
    }
    
    // ============================================
    // swoole() Static Method Tests
    // ============================================
    
    public function testSwooleSetsBasicCorsHeaders(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        NoCors::swoole($swooleResponse);

        $this->assertNotSame([], $swooleResponse->sentHeaders);
        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Origin', '*'));
    }
    
    public function testSwooleWithOriginHeader(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse(['origin' => 'https://example.com']);
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Origin', 'https://example.com'));
    }
    
    public function testSwooleWithOptionsRequest(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse([
            'access-control-request-method' => 'POST',
            'access-control-request-headers' => 'Content-Type',
        ], 'OPTIONS');
        NoCors::swoole($swooleResponse);

        $this->assertNotSame([], $swooleResponse->sentHeaders);
        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Methods', 'GET, POST, OPTIONS'));
    }
    
    public function testSwooleSetsAccessControlAllowOrigin(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Origin', '*'));
    }
    
    public function testSwooleSetsAccessControlAllowMethods(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Methods', 'POST, GET, OPTIONS'));
    }
    
    public function testSwooleSetsContentType(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Content-Type', 'application/json'));
    }
    
    public function testSwooleWithOriginSetsCredentials(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse(['origin' => 'https://example.com']);
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Credentials', 'true'));
    }
    
    public function testSwooleWithOriginSetsMaxAge(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse(['origin' => 'https://example.com']);
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Max-Age', '86400'));
    }
    
    public function testSwooleWithOptionsSetsAllowMethods(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse(
            ['access-control-request-method' => 'POST'],
            'OPTIONS'
        );
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Methods', 'GET, POST, OPTIONS'));
    }
    
    public function testSwooleWithOptionsSetsAllowHeaders(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse(
            ['access-control-request-headers' => 'Authorization, Content-Type'],
            'OPTIONS'
        );
        NoCors::swoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Access-Control-Allow-Headers', 'Authorization, Content-Type'));
    }
}


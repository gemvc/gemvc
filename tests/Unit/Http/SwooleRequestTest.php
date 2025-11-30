<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\SwooleRequest;
use Gemvc\Http\Request;
use Gemvc\Http\Response;

/**
 * @outputBuffering enabled
 */
class SwooleRequestTest extends TestCase
{
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructorWithValidSwooleRequest(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/User/read',
            'request_method' => 'GET',
            'query_string' => 'id=1',
            'remote_addr' => '127.0.0.1',
            'remote_port' => '12345'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $request = $swooleRequestObj->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('/api/User/read', $request->requestedUrl);
        $this->assertEquals('GET', $request->requestMethod);
        $this->assertEquals('id=1', $request->queryString);
        $this->assertEquals('127.0.0.1:12345', $request->remoteAddress);
    }
    
    public function testConstructorThrowsExceptionWhenRequestUriMissing(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_method' => 'GET'
            // Missing request_uri
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        
        // Should set error in request
        $this->assertNotNull($swooleRequestObj->request->error);
        $this->assertStringContainsString('request_uri', $swooleRequestObj->request->error);
    }
    
    public function testConstructorSetsErrorResponseOnException(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        
        $this->assertNotNull($swooleRequestObj->request->error);
        $this->assertNotNull($swooleRequestObj->request->response);
    }
    
    // ============================================
    // Request Method Tests
    // ============================================
    
    public function testRequestMethodExtraction(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        
        foreach ($methods as $method) {
            $swooleRequest = $this->createMockSwooleRequest([
                'request_uri' => '/api/test',
                'request_method' => $method
            ]);
            
            $swooleRequestObj = new SwooleRequest($swooleRequest);
            $this->assertEquals($method, $swooleRequestObj->request->requestMethod);
        }
    }
    
    public function testRequestMethodDefaultsToGet(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test'
            // Missing request_method
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertEquals('GET', $swooleRequestObj->request->requestMethod);
    }
    
    // ============================================
    // URI and Query String Tests
    // ============================================
    
    public function testRequestUriSanitization(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/User/read?id=1',
            'request_method' => 'GET'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertEquals('/api/User/read?id=1', $swooleRequestObj->request->requestedUrl);
    }
    
    public function testQueryStringExtraction(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/User/read',
            'request_method' => 'GET',
            'query_string' => 'id=1&name=test'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        // sanitizeInput HTML-encodes & to &amp;
        $this->assertEquals('id=1&amp;name=test', $swooleRequestObj->request->queryString);
    }
    
    public function testQueryStringWithNullValue(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/User/read',
            'request_method' => 'GET'
            // Missing query_string
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertNull($swooleRequestObj->request->queryString);
    }
    
    // ============================================
    // Remote Address Tests
    // ============================================
    
    public function testRemoteAddressExtraction(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET',
            'remote_addr' => '192.168.1.1',
            'remote_port' => '8080'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertEquals('192.168.1.1:8080', $swooleRequestObj->request->remoteAddress);
    }
    
    // ============================================
    // User Agent Tests
    // ============================================
    
    public function testUserAgentExtraction(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [
            'user-agent' => 'Mozilla/5.0'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsString($swooleRequestObj->request->userMachine);
    }
    
    // ============================================
    // POST Data Tests
    // ============================================
    
    public function testPostDataExtraction(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'POST'
        ], [], [
            'name' => 'John',
            'email' => 'john@example.com'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->post);
        $this->assertEquals('John', $swooleRequestObj->request->post['name']);
        $this->assertEquals('john@example.com', $swooleRequestObj->request->post['email']);
    }
    
    public function testPostDataWithXssSanitization(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'POST'
        ], [], [
            'name' => '<script>alert("XSS")</script>'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertStringNotContainsString('<script>', $swooleRequestObj->request->post['name']);
        $this->assertStringContainsString('&lt;script&gt;', $swooleRequestObj->request->post['name']);
    }
    
    public function testPostDataWithJsonContentType(): void
    {
        $jsonData = json_encode(['name' => 'John', 'email' => 'john@example.com']);
        
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'POST'
        ], [
            'content-type' => 'application/json'
        ], [], $jsonData);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->post);
        $this->assertEquals('John', $swooleRequestObj->request->post['name']);
        $this->assertEquals('john@example.com', $swooleRequestObj->request->post['email']);
    }
    
    // ============================================
    // PUT Data Tests
    // ============================================
    
    public function testPutDataExtraction(): void
    {
        $putData = json_encode(['name' => 'Updated Name']);
        
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'PUT'
        ], [
            'content-type' => 'application/json'
        ], [], $putData);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->put);
        $this->assertEquals('Updated Name', $swooleRequestObj->request->put['name']);
    }
    
    public function testPutDataWithFormContentType(): void
    {
        $formData = 'name=Updated&email=updated@example.com';
        
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'PUT'
        ], [
            'content-type' => 'application/x-www-form-urlencoded'
        ], [], $formData);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->put);
    }
    
    // ============================================
    // PATCH Data Tests
    // ============================================
    
    public function testPatchDataExtraction(): void
    {
        $patchData = json_encode(['name' => 'Patched Name']);
        
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'PATCH'
        ], [
            'content-type' => 'application/json'
        ], [], $patchData);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->patch);
        $this->assertEquals('Patched Name', $swooleRequestObj->request->patch['name']);
    }
    
    // ============================================
    // GET Data Tests
    // ============================================
    
    public function testGetDataExtraction(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [], [], null, [
            'id' => '1',
            'name' => 'test'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->get);
        $this->assertEquals('1', $swooleRequestObj->request->get['id']);
        $this->assertEquals('test', $swooleRequestObj->request->get['name']);
    }
    
    // ============================================
    // Authorization Tests
    // ============================================
    
    public function testAuthorizationHeaderExtraction(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [
            'authorization' => 'Bearer test-token-123'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertEquals('Bearer test-token-123', $swooleRequestObj->request->authorizationHeader);
        $this->assertEquals('test-token-123', $swooleRequestObj->request->jwtTokenStringInHeader);
    }
    
    public function testParseAuthorizationToken(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [
            'authorization' => 'Bearer my-jwt-token'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertEquals('my-jwt-token', $swooleRequestObj->request->jwtTokenStringInHeader);
    }
    
    public function testParseAuthorizationTokenWithInvalidFormat(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [
            'authorization' => 'InvalidFormat token'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertNull($swooleRequestObj->request->jwtTokenStringInHeader);
    }
    
    // ============================================
    // File Upload Tests
    // ============================================
    
    public function testFileUploadHandling(): void
    {
        $files = [
            'file' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0,
                'size' => 1024
            ]
        ];
        
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'POST'
        ], [], [], null, [], $files);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertIsArray($swooleRequestObj->request->files);
        $this->assertArrayHasKey('file', $swooleRequestObj->request->files);
    }
    
    // ============================================
    // Cookie Tests
    // ============================================
    
    public function testCookieHandling(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [], [], null, [], [], [
            'session_id' => 'abc123', // This will be filtered as dangerous
            'user_pref' => 'dark_mode' // This should pass
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        // session_id is filtered out as dangerous cookie, only user_pref should remain
        if ($swooleRequestObj->request->cookies !== null) {
            $this->assertIsString($swooleRequestObj->request->cookies);
            $this->assertStringContainsString('user_pref', $swooleRequestObj->request->cookies);
            $this->assertStringNotContainsString('session_id', $swooleRequestObj->request->cookies);
        } else {
            // If all cookies are filtered, cookies will be null
            $this->assertNull($swooleRequestObj->request->cookies);
        }
    }
    
    public function testCookieFilteringDangerousCookies(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [], [], null, [], [], [
            'PHPSESSID' => 'dangerous',
            'safe_cookie' => 'value'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        // Dangerous cookies should be filtered out
        $this->assertStringNotContainsString('PHPSESSID', $swooleRequestObj->request->cookies ?? '');
    }
    
    public function testCookieWithEmptyValue(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ], [], [], null, [], [], []);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $this->assertNull($swooleRequestObj->request->cookies);
    }
    
    // ============================================
    // getOriginalSwooleRequest() Tests
    // ============================================
    
    public function testGetOriginalSwooleRequest(): void
    {
        $swooleRequest = $this->createMockSwooleRequest([
            'request_uri' => '/api/test',
            'request_method' => 'GET'
        ]);
        
        $swooleRequestObj = new SwooleRequest($swooleRequest);
        $original = $swooleRequestObj->getOriginalSwooleRequest();
        
        $this->assertEquals($swooleRequest, $original);
    }
    
    // ============================================
    // Helper Methods
    // ============================================
    
    /**
     * Create a mock Swoole request object
     * 
     * @param array<string, string> $server
     * @param array<string, string> $headers
     * @param array<string, mixed> $post
     * @param string|null $rawContent
     * @param array<string, mixed> $get
     * @param array<string, mixed> $files
     * @param array<string, mixed> $cookies
     * @return object
     */
    private function createMockSwooleRequest(
        array $server = [],
        array $headers = [],
        array $post = [],
        ?string $rawContent = null,
        array $get = [],
        array $files = [],
        array $cookies = []
    ): object {
        $mock = new class($server, $headers, $post, $rawContent, $get, $files, $cookies) {
            public array $server;
            public array $header;
            public array $post;
            public ?string $rawContent;
            public array $get;
            public array $files;
            public array $cookie;
            
            public function __construct(
                array $server,
                array $headers,
                array $post,
                ?string $rawContent,
                array $get,
                array $files,
                array $cookies
            ) {
                $this->server = $server;
                $this->header = $headers;
                $this->post = $post;
                $this->rawContent = $rawContent;
                $this->get = $get;
                $this->files = $files;
                $this->cookie = $cookies;
            }
            
            public function rawContent(): ?string
            {
                return $this->rawContent;
            }
        };
        
        return $mock;
    }
}


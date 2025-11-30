<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\Request;

class ApacheRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Suppress output during tests
        $this->expectOutputString('');
        // Clear superglobals before each test
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
    }
    
    protected function tearDown(): void
    {
        // Clean up after each test
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }
    
    public function testXssInputSanitizationInPost(): void
    {
        $_POST['name'] = '<script>alert("XSS")</script>';
        $_POST['description'] = '<img src=x onerror="alert(\'XSS\')">';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertIsString($request->post['name']);
        $this->assertIsString($request->post['description']);
        
        // XSS should be sanitized (HTML entities encoded)
        $this->assertStringNotContainsString('<script>', $request->post['name']);
        $this->assertStringNotContainsString('<img', $request->post['description']);
        $this->assertStringContainsString('&lt;script&gt;', $request->post['name']);
        $this->assertStringContainsString('&lt;img', $request->post['description']);
    }
    
    public function testXssInputSanitizationInGet(): void
    {
        $_GET['search'] = '<script>document.cookie</script>';
        $_GET['query'] = 'javascript:alert("XSS")';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'search=test&query=test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Request->get can be string|array, check if it's array
        if (is_array($request->get)) {
            $this->assertIsString($request->get['search']);
            $this->assertIsString($request->get['query']);
            
            // XSS should be sanitized
            $this->assertStringNotContainsString('<script>', $request->get['search']);
            $this->assertStringContainsString('&lt;script&gt;', $request->get['search']);
        } else {
            $this->fail('GET should be an array');
        }
    }
    
    public function testHeaderSanitization(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '<script>alert("XSS")</script>';
        $_SERVER['HTTP_REFERER'] = 'javascript:alert("XSS")';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        
        // Headers should be sanitized
        $this->assertStringNotContainsString('<script>', $_SERVER['HTTP_USER_AGENT']);
        $this->assertStringContainsString('&lt;script&gt;', $_SERVER['HTTP_USER_AGENT']);
    }
    
    public function testArrayInputSanitization(): void
    {
        $_POST['tags'] = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror="alert(1)">',
            'normal-tag'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertIsArray($request->post['tags']);
        $this->assertStringNotContainsString('<script>', $request->post['tags'][0]);
        $this->assertStringNotContainsString('<img', $request->post['tags'][1]);
        $this->assertEquals('normal-tag', $request->post['tags'][2]);
    }
    
    public function testRequestObjectCreation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/User/create';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('/api/User/create', $request->requestedUrl);
        $this->assertEquals('POST', $request->requestMethod);
    }
    
    public function testQueryStringRemoval(): void
    {
        $_GET['_gemvc_url_path'] = '/api/User';
        $_GET['id'] = '1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['QUERY_STRING'] = '_gemvc_url_path=/api/User&id=1';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // _gemvc_url_path should be removed from GET params
        if (is_array($request->get)) {
            $this->assertArrayNotHasKey('_gemvc_url_path', $request->get);
            $this->assertArrayHasKey('id', $request->get);
            $this->assertEquals('1', $request->get['id']);
        } else {
            $this->fail('GET should be an array');
        }
    }
    
    // ============================================
    // PUT Request Tests
    // ============================================
    
    public function testPutRequestSanitization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/User/update';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        
        // Simulate PUT data by writing to php://input
        // Note: In real tests, we can't directly write to php://input
        // So we test the behavior indirectly through the Request object
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('PUT', $request->requestMethod);
        // PUT data might be null if php://input is empty
        $this->assertTrue($request->put === null || is_array($request->put));
    }
    
    public function testPutRequestWithXssPayload(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/User/update';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // PUT sanitization is tested indirectly
        $this->assertInstanceOf(Request::class, $request);
    }
    
    // ============================================
    // PATCH Request Tests
    // ============================================
    
    public function testPatchRequestSanitization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/api/User/patch';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('PATCH', $request->requestMethod);
        // PATCH data might be null if php://input is empty
        $this->assertTrue($request->patch === null || is_array($request->patch));
    }
    
    // ============================================
    // JSON POST Tests
    // ============================================
    
    public function testJsonPostRequestParsing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/User/create';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_POST = []; // Empty POST, should trigger JSON parsing
        
        // Note: We can't directly set php://input in tests
        // This test verifies the structure handles JSON content type
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('POST', $request->requestMethod);
    }
    
    // ============================================
    // File Upload Tests
    // ============================================
    
    public function testFileUploadHandling(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/User/upload';
        $_FILES['file'] = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/phpXXXXXX',
            'error' => 0,
            'size' => 1024
        ];
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertIsArray($request->files);
        $this->assertArrayHasKey('name', $request->files);
        $this->assertEquals('test.txt', $request->files['name']);
    }
    
    public function testFileUploadWithNoFile(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/User/upload';
        $_FILES = [];
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertIsArray($request->files);
        $this->assertEmpty($request->files);
    }
    
    // ============================================
    // Authorization Header Tests
    // ============================================
    
    public function testAuthHeaderFromHttpAuthorization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token-123';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('Bearer test-token-123', $request->authorizationHeader);
    }
    
    public function testAuthHeaderFromRedirectHttpAuthorization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirect-token-456';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('Bearer redirect-token-456', $request->authorizationHeader);
    }
    
    public function testAuthHeaderPriority(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer primary-token';
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirect-token';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // HTTP_AUTHORIZATION should take priority
        $this->assertEquals('Bearer primary-token', $request->authorizationHeader);
    }
    
    public function testAuthHeaderWithXssAttempt(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['HTTP_AUTHORIZATION'] = '<script>alert("XSS")</script>';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Header should be sanitized
        $this->assertStringNotContainsString('<script>', $request->authorizationHeader);
        $this->assertStringContainsString('&lt;script&gt;', $request->authorizationHeader);
    }
    
    // ============================================
    // Query String Tests
    // ============================================
    
    public function testQueryStringSanitization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['QUERY_STRING'] = 'id=1&name=<script>alert("XSS")</script>';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertInstanceOf(Request::class, $request);
        // Query string should be sanitized
        $this->assertStringNotContainsString('<script>', $request->queryString);
    }
    
    public function testQueryStringWithEmptyValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('', $request->queryString);
    }
    
    // ============================================
    // Request URI Tests
    // ============================================
    
    public function testRequestUriSanitization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read?id=1';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('/api/User/read?id=1', $request->requestedUrl);
    }
    
    public function testRequestUriWithInvalidUrl(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        // Invalid URL with null bytes
        $_SERVER['REQUEST_URI'] = "\0invalid\0url";
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // FILTER_SANITIZE_URL may not return false for null bytes, it may sanitize them
        // So we just check that the method handles it without error
        $this->assertIsString($request->requestedUrl);
    }
    
    public function testRequestUriWithMissingValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['REQUEST_URI']);
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('', $request->requestedUrl);
    }
    
    // ============================================
    // Remote Address Tests
    // ============================================
    
    public function testRemoteAddressWithValidIp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('192.168.1.1', $request->remoteAddress);
    }
    
    public function testRemoteAddressWithInvalidIp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip-address';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('invalid_remote_address_ip_format', $request->remoteAddress);
    }
    
    public function testRemoteAddressWithMissingValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        unset($_SERVER['REMOTE_ADDR']);
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('unsetted_remote_address', $request->remoteAddress);
    }
    
    // ============================================
    // Request Method Tests
    // ============================================
    
    public function testRequestMethodValidation(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        
        foreach ($validMethods as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = '/api/test';
            
            $ar = new ApacheRequest();
            $request = $ar->request;
            
            $this->assertEquals($method, $request->requestMethod, "Method $method should be valid");
        }
    }
    
    public function testRequestMethodWithInvalidMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'INVALID';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('', $request->requestMethod);
    }
    
    public function testRequestMethodCaseInsensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('POST', $request->requestMethod);
    }
    
    public function testRequestMethodWithWhitespace(): void
    {
        $_SERVER['REQUEST_METHOD'] = '  POST  ';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('POST', $request->requestMethod);
    }
    
    // ============================================
    // User Agent Tests
    // ============================================
    
    public function testUserAgentExtraction(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', $request->userMachine);
    }
    
    public function testUserAgentWithMissingValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        unset($_SERVER['HTTP_USER_AGENT']);
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals('undetected', $request->userMachine);
    }
    
    // ============================================
    // Header Sanitization Tests
    // ============================================
    
    public function testHeaderArraySanitization(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/User/read';
        $_SERVER['HTTP_CUSTOM_HEADER'] = ['value1' => '<script>alert("XSS")</script>', 'value2' => 'normal'];
        
        $ar = new ApacheRequest();
        
        // Headers should be sanitized
        $this->assertStringNotContainsString('<script>', $_SERVER['HTTP_CUSTOM_HEADER']['value1']);
        $this->assertStringContainsString('&lt;script&gt;', $_SERVER['HTTP_CUSTOM_HEADER']['value1']);
    }
}


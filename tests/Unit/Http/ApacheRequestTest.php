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
}


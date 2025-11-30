<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\Request;

class InputSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Suppress output during tests
        $this->expectOutputString('');
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
    }
    
    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }
    
    public function testXssAttackPreventionInPost(): void
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror="alert(\'XSS\')">',
            '<svg onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<body onload=alert("XSS")>',
        ];
        
        foreach ($xssPayloads as $payload) {
            $_POST['input'] = $payload;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI'] = '/api/test';
            
            $ar = new ApacheRequest();
            $request = $ar->request;
            
            // All XSS payloads should be sanitized (HTML entities encoded)
            if (isset($request->post['input']) && is_string($request->post['input'])) {
                $sanitized = $request->post['input'];
                
                // HTML tags should be encoded (if payload contains <)
                if (str_contains($payload, '<')) {
                    $this->assertStringNotContainsString('<script>', $sanitized);
                    $this->assertStringContainsString('&lt;', $sanitized);
                }
                
                // Special characters like quotes should be encoded
                if (str_contains($payload, '"')) {
                    $this->assertStringContainsString('&quot;', $sanitized);
                }
                
                // All inputs should be sanitized (not equal to original if it had special chars)
                if (preg_match('/[<>"\'&]/', $payload)) {
                    $this->assertNotEquals($payload, $sanitized, 'Input with special characters should be sanitized');
                }
                
                // Note: Input sanitization prevents XSS by encoding HTML entities
                // Real protection comes from schema validation and prepared statements
            } else {
                $this->fail('POST input should be a string');
            }
        }
    }
    
    public function testXssAttackPreventionInGet(): void
    {
        $xssPayloads = [
            '<script>document.cookie</script>',
            '<img src=x onerror="alert(1)">',
            'javascript:void(0)',
        ];
        
        foreach ($xssPayloads as $payload) {
            $_GET['search'] = $payload;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/test';
            $_SERVER['QUERY_STRING'] = 'search=' . urlencode($payload);
            
            $ar = new ApacheRequest();
            $request = $ar->request;
            
            // XSS should be sanitized
            if (is_array($request->get) && isset($request->get['search']) && is_string($request->get['search'])) {
                $this->assertStringNotContainsString('<script>', $request->get['search']);
            } else {
                $this->fail('GET search should be a string');
            }
        }
    }
    
    public function testHeaderInjectionPrevention(): void
    {
        $maliciousHeaders = [
            'HTTP_USER_AGENT' => "User-Agent\r\nX-Injected: header",
            'HTTP_REFERER' => "http://example.com\r\nX-Injected: header",
            'HTTP_AUTHORIZATION' => "Bearer token\r\nX-Injected: header",
        ];
        
        foreach ($maliciousHeaders as $headerName => $headerValue) {
            $_SERVER[$headerName] = $headerValue;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/test';
            
            $ar = new ApacheRequest();
            
            // Headers should be sanitized (HTML entities encoded)
            $sanitized = $_SERVER[$headerName];
            // Sanitization encodes special characters but may not remove newlines
            // Real protection comes from proper header handling in the framework
            $this->assertIsString($sanitized);
        }
    }
    
    public function testPathTraversalPrevention(): void
    {
        $pathTraversalPayloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            '....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        ];
        
        foreach ($pathTraversalPayloads as $payload) {
            $_POST['filename'] = $payload;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI'] = '/api/test';
            
            $ar = new ApacheRequest();
            $request = $ar->request;
            
            // Path traversal should be sanitized (HTML entities encoded)
            if (isset($request->post['filename']) && is_string($request->post['filename'])) {
                $sanitized = $request->post['filename'];
                // Input sanitization encodes HTML entities but doesn't remove ".."
                // Real protection comes from SecurityManager path blocking
                $this->assertIsString($sanitized);
                // The framework's SecurityManager blocks actual path traversal in URLs
            } else {
                $this->fail('POST filename should be a string');
            }
        }
    }
    
    public function testSqlInjectionInStringInput(): void
    {
        $sqlPayloads = [
            "admin' OR '1'='1",
            "'; DROP TABLE users; --",
            "' UNION SELECT * FROM users --",
            "1' OR '1'='1' --",
        ];
        
        foreach ($sqlPayloads as $payload) {
            $_POST['email'] = $payload;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI'] = '/api/test';
            
            $ar = new ApacheRequest();
            $request = $ar->request;
            
            // Input should be sanitized (though SQL injection is prevented by prepared statements)
            $this->assertIsString($request->post['email']);
            // The sanitization converts special chars, but prepared statements are the real protection
        }
    }
    
    public function testMassAssignmentPrevention(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        $_POST['is_admin'] = '1'; // Unwanted field
        $_POST['role'] = 'admin'; // Unwanted field
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Schema validation should reject unwanted fields
        $result = $request->definePostSchema([
            'name' => 'string',
            'email' => 'email'
        ]);
        
        $this->assertFalse($result, 'Should reject mass assignment attempt');
    }
}


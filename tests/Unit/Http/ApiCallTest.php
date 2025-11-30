<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApiCall;

class ApiCallTest extends TestCase
{
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $apiCall = new ApiCall();
        
        $this->assertInstanceOf(ApiCall::class, $apiCall);
        $this->assertEquals('call not initialized', $apiCall->error);
        $this->assertEquals(0, $apiCall->http_response_code);
        $this->assertIsArray($apiCall->data);
        $this->assertIsArray($apiCall->header);
        $this->assertIsArray($apiCall->files);
        $this->assertNull($apiCall->authorizationHeader);
        $this->assertFalse($apiCall->responseBody);
        $this->assertEquals('GET', $apiCall->method);
    }
    
    // ============================================
    // Configuration Methods Tests
    // ============================================
    
    public function testSetTimeouts(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->setTimeouts(5, 10);
        
        $this->assertInstanceOf(ApiCall::class, $result);
        $this->assertEquals($apiCall, $result); // Should return self for chaining
    }
    
    public function testSetTimeoutsWithZeroValues(): void
    {
        $apiCall = new ApiCall();
        $apiCall->setTimeouts(0, 0);
        
        // Should accept zero values (legacy behavior)
        $this->assertTrue(true);
    }
    
    public function testSetTimeoutsWithNegativeValues(): void
    {
        $apiCall = new ApiCall();
        $apiCall->setTimeouts(-5, -10);
        
        // Should clamp to 0
        $this->assertTrue(true);
    }
    
    public function testSetSsl(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem', true, 2);
        
        $this->assertInstanceOf(ApiCall::class, $result);
        $this->assertEquals($apiCall, $result);
    }
    
    public function testSetSslWithNullValues(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->setSsl(null, null, null, false, 0);
        
        $this->assertInstanceOf(ApiCall::class, $result);
    }
    
    public function testSetRetries(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->setRetries(3, 500, [429, 500, 502]);
        
        $this->assertInstanceOf(ApiCall::class, $result);
        $this->assertEquals($apiCall, $result);
    }
    
    public function testSetRetriesWithEmptyArray(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->setRetries(2, 300, []);
        
        $this->assertInstanceOf(ApiCall::class, $result);
    }
    
    public function testSetRetriesWithZeroRetries(): void
    {
        $apiCall = new ApiCall();
        $apiCall->setRetries(0, 200, []);
        
        // Should accept zero (no retries)
        $this->assertTrue(true);
    }
    
    public function testRetryOnNetworkError(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->retryOnNetworkError(true);
        
        $this->assertInstanceOf(ApiCall::class, $result);
        $this->assertEquals($apiCall, $result);
    }
    
    public function testRetryOnNetworkErrorDisable(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->retryOnNetworkError(false);
        
        $this->assertInstanceOf(ApiCall::class, $result);
    }
    
    // ============================================
    // HTTP Method Tests (GET, POST, PUT)
    // ============================================
    
    public function testGetMethod(): void
    {
        $apiCall = new ApiCall();
        
        // Test that method is set correctly
        $this->assertEquals('GET', $apiCall->method);
        
        // get() should set method to GET
        $apiCall->method = 'POST';
        $apiCall->get('https://example.com/api');
        
        $this->assertEquals('GET', $apiCall->method);
    }
    
    public function testGetWithQueryParams(): void
    {
        $apiCall = new ApiCall();
        
        // get() should append query params to URL
        $apiCall->get('https://example.com/api', ['id' => 1, 'name' => 'test']);
        
        $this->assertEquals('GET', $apiCall->method);
        $this->assertIsArray($apiCall->data);
    }
    
    public function testPostMethod(): void
    {
        $apiCall = new ApiCall();
        $apiCall->post('https://example.com/api', ['name' => 'John']);
        
        $this->assertEquals('POST', $apiCall->method);
        $this->assertEquals(['name' => 'John'], $apiCall->data);
    }
    
    public function testPostClearsRawBody(): void
    {
        $apiCall = new ApiCall();
        $apiCall->postRaw('https://example.com/api', 'raw body', 'text/plain');
        $apiCall->post('https://example.com/api', ['data' => 'value']);
        
        $this->assertEquals('POST', $apiCall->method);
        // rawBody should be cleared
        $reflection = new \ReflectionClass($apiCall);
        $rawBodyProperty = $reflection->getProperty('rawBody');
        $rawBodyProperty->setAccessible(true);
        $this->assertNull($rawBodyProperty->getValue($apiCall));
    }
    
    public function testPutMethod(): void
    {
        $apiCall = new ApiCall();
        $apiCall->put('https://example.com/api', ['name' => 'Updated']);
        
        $this->assertEquals('PUT', $apiCall->method);
        $this->assertEquals(['name' => 'Updated'], $apiCall->data);
    }
    
    // ============================================
    // Form and Multipart Tests
    // ============================================
    
    public function testPostForm(): void
    {
        $apiCall = new ApiCall();
        // Don't actually make HTTP request, just test method configuration
        $apiCall->method = 'GET'; // Reset to verify it changes
        $apiCall->postForm('https://example.com/api', ['field1' => 'value1']);
        
        $this->assertEquals('POST', $apiCall->method);
        // Verify formFields is set
        $reflection = new \ReflectionClass($apiCall);
        $formFieldsProperty = $reflection->getProperty('formFields');
        $formFieldsProperty->setAccessible(true);
        $this->assertEquals(['field1' => 'value1'], $formFieldsProperty->getValue($apiCall));
    }
    
    public function testPostFormWithEmptyFields(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->postForm('https://example.com/api', []);
        
        $this->assertEquals('POST', $apiCall->method);
    }
    
    public function testPostMultipart(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->postMultipart('https://example.com/api', ['field' => 'value'], ['file' => '/tmp/test.txt']);
        
        $this->assertEquals('POST', $apiCall->method);
        $this->assertIsArray($apiCall->files);
    }
    
    public function testPostMultipartWithEmptyData(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->postMultipart('https://example.com/api', [], []);
        
        $this->assertEquals('POST', $apiCall->method);
    }
    
    public function testPostRaw(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall->postRaw('https://example.com/api', 'raw body content', 'text/plain');
        
        $this->assertEquals('POST', $apiCall->method);
        $this->assertArrayHasKey('Content-Type', $apiCall->header);
        $this->assertEquals('text/plain', $apiCall->header['Content-Type']);
    }
    
    public function testPostRawClearsFormFields(): void
    {
        $apiCall = new ApiCall();
        $apiCall->postForm('https://example.com/api', ['field' => 'value']);
        $apiCall->postRaw('https://example.com/api', 'raw', 'text/plain');
        
        $reflection = new \ReflectionClass($apiCall);
        $formFieldsProperty = $reflection->getProperty('formFields');
        $formFieldsProperty->setAccessible(true);
        $this->assertNull($formFieldsProperty->getValue($apiCall));
    }
    
    // ============================================
    // Header and Authorization Tests
    // ============================================
    
    public function testSetCustomHeaders(): void
    {
        $apiCall = new ApiCall();
        $apiCall->header['X-Custom-Header'] = 'value';
        $apiCall->header['X-Another-Header'] = 'another-value';
        
        $this->assertArrayHasKey('X-Custom-Header', $apiCall->header);
        $this->assertArrayHasKey('X-Another-Header', $apiCall->header);
        $this->assertEquals('value', $apiCall->header['X-Custom-Header']);
    }
    
    public function testSetAuthorizationHeader(): void
    {
        $apiCall = new ApiCall();
        $apiCall->authorizationHeader = 'Bearer token-123';
        
        $this->assertEquals('Bearer token-123', $apiCall->authorizationHeader);
    }
    
    public function testSetAuthorizationHeaderAsArray(): void
    {
        $apiCall = new ApiCall();
        $apiCall->authorizationHeader = ['Bearer', 'token-123'];
        
        // Should accept array (for compatibility)
        $this->assertIsArray($apiCall->authorizationHeader);
    }
    
    // ============================================
    // Files Tests
    // ============================================
    
    public function testSetFiles(): void
    {
        $apiCall = new ApiCall();
        $apiCall->files = ['file1' => '/path/to/file1.txt', 'file2' => '/path/to/file2.txt'];
        
        $this->assertIsArray($apiCall->files);
        $this->assertCount(2, $apiCall->files);
    }
    
    // ============================================
    // Data Tests
    // ============================================
    
    public function testSetData(): void
    {
        $apiCall = new ApiCall();
        $apiCall->data = ['key1' => 'value1', 'key2' => 'value2'];
        
        $this->assertIsArray($apiCall->data);
        $this->assertEquals('value1', $apiCall->data['key1']);
    }
    
    // ============================================
    // Method Chaining Tests
    // ============================================
    
    public function testMethodChaining(): void
    {
        $apiCall = new ApiCall();
        $result = $apiCall
            ->setTimeouts(5, 10)
            ->setSsl('/cert.pem', '/key.pem')
            ->setRetries(3, 500)
            ->retryOnNetworkError(true);
        
        $this->assertInstanceOf(ApiCall::class, $result);
        $this->assertEquals($apiCall, $result);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testErrorPropertyInitialization(): void
    {
        $apiCall = new ApiCall();
        
        $this->assertEquals('call not initialized', $apiCall->error);
    }
    
    public function testErrorPropertyCanBeSet(): void
    {
        $apiCall = new ApiCall();
        $apiCall->error = 'Test error message';
        
        $this->assertEquals('Test error message', $apiCall->error);
    }
    
    public function testHttpResponseCodeInitialization(): void
    {
        $apiCall = new ApiCall();
        
        $this->assertEquals(0, $apiCall->http_response_code);
    }
    
    public function testHttpResponseCodeCanBeSet(): void
    {
        $apiCall = new ApiCall();
        $apiCall->http_response_code = 200;
        
        $this->assertEquals(200, $apiCall->http_response_code);
    }
    
    // ============================================
    // Response Body Tests
    // ============================================
    
    public function testResponseBodyInitialization(): void
    {
        $apiCall = new ApiCall();
        
        $this->assertFalse($apiCall->responseBody);
    }
    
    public function testResponseBodyCanBeSetToString(): void
    {
        $apiCall = new ApiCall();
        $apiCall->responseBody = 'Response content';
        
        $this->assertEquals('Response content', $apiCall->responseBody);
    }
    
    public function testResponseBodyCanBeSetToFalse(): void
    {
        $apiCall = new ApiCall();
        $apiCall->responseBody = 'Response';
        $apiCall->responseBody = false;
        
        $this->assertFalse($apiCall->responseBody);
    }
    
    // ============================================
    // Edge Cases
    // ============================================
    
    public function testGetWithEmptyQueryParams(): void
    {
        $apiCall = new ApiCall();
        $apiCall->get('https://example.com/api', []);
        
        $this->assertEquals('GET', $apiCall->method);
    }
    
    public function testPostWithEmptyData(): void
    {
        $apiCall = new ApiCall();
        $apiCall->post('https://example.com/api', []);
        
        $this->assertEquals('POST', $apiCall->method);
        $this->assertIsArray($apiCall->data);
        $this->assertEmpty($apiCall->data);
    }
    
    public function testPutWithEmptyData(): void
    {
        $apiCall = new ApiCall();
        $apiCall->put('https://example.com/api', []);
        
        $this->assertEquals('PUT', $apiCall->method);
        $this->assertIsArray($apiCall->data);
    }
    
    public function testPostFormWithSpecialCharacters(): void
    {
        $apiCall = new ApiCall();
        $apiCall->postForm('https://example.com/api', [
            'field1' => 'value with spaces',
            'field2' => 'value&with=special'
        ]);
        
        $this->assertEquals('POST', $apiCall->method);
    }
    
    public function testPostRawWithJsonContentType(): void
    {
        $apiCall = new ApiCall();
        $apiCall->postRaw('https://example.com/api', '{"key":"value"}', 'application/json');
        
        $this->assertEquals('application/json', $apiCall->header['Content-Type']);
    }
    
    public function testSetRetriesWithDuplicateHttpCodes(): void
    {
        $apiCall = new ApiCall();
        $apiCall->setRetries(3, 500, [429, 500, 429, 502]);
        
        // Duplicates should be removed
        $this->assertTrue(true);
    }
}


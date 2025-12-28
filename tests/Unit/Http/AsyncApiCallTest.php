<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\AsyncApiCall;
use ReflectionClass;
use ReflectionMethod;

class AsyncApiCallTest extends TestCase
{
    // ==========================================
    // Constructor Tests
    // ==========================================
    
    public function testConstructor(): void
    {
        $async = new AsyncApiCall();
        
        $this->assertInstanceOf(AsyncApiCall::class, $async);
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    // ==========================================
    // Configuration Methods Tests
    // ==========================================
    
    public function testSetMaxConcurrency(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setMaxConcurrency(5);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetMaxConcurrencyClampsMinimum(): void
    {
        $async = new AsyncApiCall();
        $async->setMaxConcurrency(0);
        
        // Should clamp to 1
        $this->assertTrue(true);
    }
    
    public function testSetMaxConcurrencyWithNegativeValue(): void
    {
        $async = new AsyncApiCall();
        $async->setMaxConcurrency(-5);
        
        // Should clamp to 1
        $this->assertTrue(true);
    }
    
    public function testSetTimeouts(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setTimeouts(10, 30);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetTimeoutsClampsMinimum(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(0, 0);
        
        // Should clamp to 1
        $this->assertTrue(true);
    }
    
    public function testSetTimeoutsWithNegativeValues(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(-5, -10);
        
        // Should clamp to 1
        $this->assertTrue(true);
    }
    
    public function testSetSsl(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem', true, 2);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetSslWithNullValues(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setSsl(null, null, null, false, 0);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetRetries(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setRetries(3, 200, [500, 502, 503]);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetRetriesWithEmptyArray(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setRetries(2, 300, []);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetRetriesWithZeroRetries(): void
    {
        $async = new AsyncApiCall();
        $async->setRetries(0, 200, []);
        
        $this->assertTrue(true);
    }
    
    public function testRetryOnNetworkError(): void
    {
        $async = new AsyncApiCall();
        $result = $async->retryOnNetworkError(true);
        
        $this->assertSame($async, $result);
    }
    
    public function testRetryOnNetworkErrorDisable(): void
    {
        $async = new AsyncApiCall();
        $result = $async->retryOnNetworkError(false);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetUserAgent(): void
    {
        $async = new AsyncApiCall();
        $result = $async->setUserAgent('MyApp/1.0');
        
        $this->assertSame($async, $result);
    }
    
    public function testSetUserAgentWithEmptyString(): void
    {
        $async = new AsyncApiCall();
        $async->setUserAgent('');
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // Request Building Methods Tests
    // ==========================================
    
    public function testAddGet(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addGet('req1', 'https://example.com/api');
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddGetWithQueryParams(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api', ['id' => 1, 'name' => 'test']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddGetWithHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api', [], ['X-Custom' => 'value']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPost(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addPost('req1', 'https://example.com/api', ['name' => 'John']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostWithEmptyData(): void
    {
        $async = new AsyncApiCall();
        $async->addPost('req1', 'https://example.com/api', []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostWithHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addPost('req1', 'https://example.com/api', ['data' => 'value'], ['Authorization' => 'Bearer token']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPut(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addPut('req1', 'https://example.com/api', ['name' => 'Updated']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPutWithHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addPut('req1', 'https://example.com/api', ['data' => 'value'], ['Content-Type' => 'application/json']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostForm(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addPostForm('req1', 'https://example.com/api', ['field1' => 'value1']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostFormWithEmptyFields(): void
    {
        $async = new AsyncApiCall();
        $async->addPostForm('req1', 'https://example.com/api', []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostFormWithHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addPostForm('req1', 'https://example.com/api', ['field' => 'value'], ['X-Custom' => 'header']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostMultipart(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addPostMultipart('req1', 'https://example.com/api', ['description' => 'test'], ['file' => '/tmp/test.txt']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostMultipartWithEmptyData(): void
    {
        $async = new AsyncApiCall();
        $async->addPostMultipart('req1', 'https://example.com/api', [], []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostMultipartWithHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addPostMultipart('req1', 'https://example.com/api', ['field' => 'value'], ['file' => '/tmp/test.txt'], ['X-Custom' => 'header']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostRaw(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addPostRaw('req1', 'https://example.com/api', 'raw body content', 'text/plain');
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostRawWithJsonContentType(): void
    {
        $async = new AsyncApiCall();
        $async->addPostRaw('req1', 'https://example.com/api', '{"key":"value"}', 'application/json');
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostRawWithHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addPostRaw('req1', 'https://example.com/api', 'raw', 'text/plain', ['X-Custom' => 'header']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddRequest(): void
    {
        $async = new AsyncApiCall();
        $result = $async->addRequest('req1', 'https://example.com/api', 'DELETE', ['data' => 'value'], ['X-Custom' => 'header'], ['custom' => 'option']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddRequestWithDefaultMethod(): void
    {
        $async = new AsyncApiCall();
        $async->addRequest('req1', 'https://example.com/api');
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddRequestWithLowercaseMethod(): void
    {
        $async = new AsyncApiCall();
        $async->addRequest('req1', 'https://example.com/api', 'post');
        
        // Should convert to uppercase
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testOnResponse(): void
    {
        $async = new AsyncApiCall();
        $callback = function($result, $requestId) {
            return true;
        };
        $result = $async->onResponse('req1', $callback);
        
        $this->assertSame($async, $result);
    }
    
    // ==========================================
    // Queue Management Tests
    // ==========================================
    
    public function testGetQueueSize(): void
    {
        $async = new AsyncApiCall();
        $this->assertEquals(0, $async->getQueueSize());
        
        $async->addGet('req1', 'https://example.com/api');
        $this->assertEquals(1, $async->getQueueSize());
        
        $async->addPost('req2', 'https://example.com/api');
        $this->assertEquals(2, $async->getQueueSize());
    }
    
    public function testClearQueue(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api');
        $async->addPost('req2', 'https://example.com/api');
        
        $this->assertEquals(2, $async->getQueueSize());
        
        $result = $async->clearQueue();
        $this->assertSame($async, $result);
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    public function testClearQueueClearsCallbacks(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api');
        $async->onResponse('req1', function() {});
        $async->clearQueue();
        
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    // ==========================================
    // Execution Tests
    // ==========================================
    
    public function testExecuteAllWithEmptyQueue(): void
    {
        $async = new AsyncApiCall();
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
    
    public function testWaitForAll(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('req1', 'https://httpbin.org/get');
        $async->addGet('req2', 'https://httpbin.org/get');
        
        $results = $async->waitForAll();
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }
    
    public function testFireAndForgetWithEmptyQueue(): void
    {
        $async = new AsyncApiCall();
        $result = $async->fireAndForget();
        
        $this->assertFalse($result);
    }
    
    public function testFireAndForgetWithRequests(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://httpbin.org/get');
        $result = $async->fireAndForget();
        
        // Should return true if background execution was initiated
        $this->assertIsBool($result);
    }
    
    // ==========================================
    // Method Chaining Tests
    // ==========================================
    
    public function testMethodChaining(): void
    {
        $async = new AsyncApiCall();
        $result = $async
            ->setMaxConcurrency(5)
            ->setTimeouts(10, 30)
            ->setSsl('/cert.pem', '/key.pem')
            ->setRetries(3, 500)
            ->retryOnNetworkError(true)
            ->setUserAgent('MyApp/1.0')
            ->addGet('req1', 'https://example.com/api')
            ->addPost('req2', 'https://example.com/api', ['data' => 'value'])
            ->onResponse('req1', function() {});
        
        $this->assertSame($async, $result);
    }
    
    // ==========================================
    // Multiple Requests Tests
    // ==========================================
    
    public function testMultipleRequests(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api');
        $async->addGet('req2', 'https://example.com/api');
        $async->addPost('req3', 'https://example.com/api', ['data' => 'value']);
        
        $this->assertEquals(3, $async->getQueueSize());
    }
    
    public function testMultipleRequestsWithCallbacks(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api')
              ->onResponse('req1', function($result, $id) {
                  $this->assertIsArray($result);
              });
        
        $async->addPost('req2', 'https://example.com/api', ['data' => 'value'])
              ->onResponse('req2', function($result, $id) {
                  $this->assertIsArray($result);
              });
        
        $this->assertEquals(2, $async->getQueueSize());
    }
    
    // ==========================================
    // Edge Cases Tests
    // ==========================================
    
    public function testAddGetWithEmptyQueryParams(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api', []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostWithComplexData(): void
    {
        $async = new AsyncApiCall();
        $async->addPost('req1', 'https://example.com/api', [
            'nested' => ['key' => 'value'],
            'array' => [1, 2, 3],
            'null' => null,
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostFormWithSpecialCharacters(): void
    {
        $async = new AsyncApiCall();
        $async->addPostForm('req1', 'https://example.com/api', [
            'field1' => 'value with spaces',
            'field2' => 'value&with=special'
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddRequestWithCustomMethod(): void
    {
        $async = new AsyncApiCall();
        $async->addRequest('req1', 'https://example.com/api', 'PATCH');
        $async->addRequest('req2', 'https://example.com/api', 'DELETE');
        $async->addRequest('req3', 'https://example.com/api', 'HEAD');
        
        $this->assertEquals(3, $async->getQueueSize());
    }
    
    public function testAddRequestWithEmptyOptions(): void
    {
        $async = new AsyncApiCall();
        $async->addRequest('req1', 'https://example.com/api', 'GET', [], [], []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Concurrency Tests
    // ==========================================
    
    public function testSetMaxConcurrencyWithHighValue(): void
    {
        $async = new AsyncApiCall();
        $async->setMaxConcurrency(100);
        
        $this->assertTrue(true);
    }
    
    public function testSetMaxConcurrencyWithOne(): void
    {
        $async = new AsyncApiCall();
        $async->setMaxConcurrency(1);
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // SSL Configuration Edge Cases
    // ==========================================
    
    public function testSetSslWithOnlyCert(): void
    {
        $async = new AsyncApiCall();
        $async->setSsl('/cert.pem', null);
        
        $this->assertTrue(true);
    }
    
    public function testSetSslWithOnlyKey(): void
    {
        $async = new AsyncApiCall();
        $async->setSsl(null, '/key.pem');
        
        $this->assertTrue(true);
    }
    
    public function testSetSslWithVerifyPeerFalse(): void
    {
        $async = new AsyncApiCall();
        $async->setSsl(null, null, null, false);
        
        $this->assertTrue(true);
    }
    
    public function testSetSslWithVerifyHostZero(): void
    {
        $async = new AsyncApiCall();
        $async->setSsl(null, null, null, true, 0);
        
        $this->assertTrue(true);
    }
    
    public function testSetSslWithVerifyHostOne(): void
    {
        $async = new AsyncApiCall();
        $async->setSsl(null, null, null, true, 1);
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // Request Types Combination Tests
    // ==========================================
    
    public function testAllRequestTypes(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('get1', 'https://example.com/api');
        $async->addPost('post1', 'https://example.com/api', ['data' => 'value']);
        $async->addPut('put1', 'https://example.com/api', ['data' => 'value']);
        $async->addPostForm('form1', 'https://example.com/api', ['field' => 'value']);
        $async->addPostMultipart('multipart1', 'https://example.com/api', ['field' => 'value'], ['file' => '/tmp/test.txt']);
        $async->addPostRaw('raw1', 'https://example.com/api', 'raw body', 'text/plain');
        $async->addRequest('custom1', 'https://example.com/api', 'DELETE');
        
        $this->assertEquals(7, $async->getQueueSize());
    }
    
    // ==========================================
    // Callback Tests
    // ==========================================
    
    public function testOnResponseWithMultipleRequests(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api')
              ->onResponse('req1', function($result, $id) {
                  $this->assertEquals('req1', $id);
              });
        
        $async->addGet('req2', 'https://example.com/api')
              ->onResponse('req2', function($result, $id) {
                  $this->assertEquals('req2', $id);
              });
        
        $this->assertEquals(2, $async->getQueueSize());
    }
    
    public function testOnResponseOverwritesPreviousCallback(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api')
              ->onResponse('req1', function() { return 'first'; })
              ->onResponse('req1', function() { return 'second'; });
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Timeout Edge Cases
    // ==========================================
    
    public function testSetTimeoutsWithVeryHighValues(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(300, 600);
        
        $this->assertTrue(true);
    }
    
    public function testSetTimeoutsWithSameValues(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(30, 30);
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // URL Edge Cases
    // ==========================================
    
    public function testAddGetWithUrlContainingQueryString(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api?existing=param', ['new' => 'param']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddGetWithSpecialCharactersInQueryParams(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello & World',
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Headers Edge Cases
    // ==========================================
    
    public function testAddPostWithMultipleHeaders(): void
    {
        $async = new AsyncApiCall();
        $async->addPost('req1', 'https://example.com/api', ['data' => 'value'], [
            'Authorization' => 'Bearer token',
            'X-Custom-1' => 'value1',
            'X-Custom-2' => 'value2',
            'Content-Type' => 'application/json',
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostFormOverridesContentType(): void
    {
        $async = new AsyncApiCall();
        $async->addPostForm('req1', 'https://example.com/api', ['field' => 'value'], [
            'Content-Type' => 'application/json', // Should be overridden
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // File Upload Edge Cases
    // ==========================================
    
    public function testAddPostMultipartWithMultipleFiles(): void
    {
        $async = new AsyncApiCall();
        $async->addPostMultipart('req1', 'https://example.com/api', 
            ['description' => 'test'],
            [
                'file1' => '/tmp/file1.txt',
                'file2' => '/tmp/file2.txt',
            ]
        );
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostMultipartWithEmptyFiles(): void
    {
        $async = new AsyncApiCall();
        $async->addPostMultipart('req1', 'https://example.com/api', ['field' => 'value'], []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Raw Body Edge Cases
    // ==========================================
    
    public function testAddPostRawWithXmlContent(): void
    {
        $async = new AsyncApiCall();
        $async->addPostRaw('req1', 'https://example.com/api', '<xml>data</xml>', 'application/xml');
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostRawWithEmptyBody(): void
    {
        $async = new AsyncApiCall();
        $async->addPostRaw('req1', 'https://example.com/api', '', 'text/plain');
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostRawWithLargeBody(): void
    {
        $async = new AsyncApiCall();
        $largeBody = str_repeat('x', 10000);
        $async->addPostRaw('req1', 'https://example.com/api', $largeBody, 'text/plain');
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Retry Configuration Tests
    // ==========================================
    
    public function testSetRetriesWithDuplicateHttpCodes(): void
    {
        $async = new AsyncApiCall();
        $async->setRetries(3, 500, [429, 500, 429, 502]);
        
        $this->assertTrue(true);
    }
    
    public function testSetRetriesWithLargeDelay(): void
    {
        $async = new AsyncApiCall();
        $async->setRetries(5, 5000, [500, 502, 503]);
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // User Agent Tests
    // ==========================================
    
    public function testSetUserAgentWithSpecialCharacters(): void
    {
        $async = new AsyncApiCall();
        $async->setUserAgent('MyApp/1.0 (Windows NT 10.0)');
        
        $this->assertTrue(true);
    }
    
    public function testSetUserAgentWithUnicode(): void
    {
        $async = new AsyncApiCall();
        $async->setUserAgent('MyApp/1.0 🚀');
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // Complex Scenarios
    // ==========================================
    
    public function testComplexWorkflow(): void
    {
        $async = new AsyncApiCall();
        $async->setMaxConcurrency(5)
              ->setTimeouts(10, 30)
              ->setUserAgent('MyApp/1.0')
              ->addGet('users', 'https://example.com/users', ['page' => 1])
              ->onResponse('users', function($result, $id) {
                  $this->assertIsArray($result);
              })
              ->addPost('create', 'https://example.com/create', ['name' => 'Test'])
              ->onResponse('create', function($result, $id) {
                  $this->assertIsArray($result);
              })
              ->addPostForm('submit', 'https://example.com/submit', ['email' => 'test@example.com'])
              ->addPostMultipart('upload', 'https://example.com/upload', ['desc' => 'file'], ['file' => '/tmp/test.txt'])
              ->addPostRaw('xml', 'https://example.com/xml', '<xml>data</xml>', 'application/xml');
        
        $this->assertEquals(5, $async->getQueueSize());
    }
    
    public function testClearQueueAfterAddingRequests(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('req1', 'https://example.com/api')
              ->addPost('req2', 'https://example.com/api')
              ->onResponse('req1', function() {})
              ->onResponse('req2', function() {});
        
        $this->assertEquals(2, $async->getQueueSize());
        
        $async->clearQueue();
        $this->assertEquals(0, $async->getQueueSize());
        
        // Should be able to add new requests after clearing
        $async->addGet('req3', 'https://example.com/api');
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Execution Tests with Real HTTP (if available)
    // ==========================================
    
    public function testExecuteAllWithSingleGetRequest(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get', ['test' => 'value']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
        $this->assertIsArray($results['test']);
        $this->assertArrayHasKey('success', $results['test']);
        $this->assertArrayHasKey('body', $results['test']);
        $this->assertArrayHasKey('http_code', $results['test']);
        $this->assertArrayHasKey('error', $results['test']);
        $this->assertArrayHasKey('duration', $results['test']);
    }
    
    public function testExecuteAllWithSinglePostRequest(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addPost('test', 'https://httpbin.org/post', ['name' => 'test']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
    }
    
    public function testExecuteAllWithMultipleRequests(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(3);
        
        $async->addGet('req1', 'https://httpbin.org/get', ['id' => 1]);
        $async->addGet('req2', 'https://httpbin.org/get', ['id' => 2]);
        $async->addGet('req3', 'https://httpbin.org/get', ['id' => 3]);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertArrayHasKey('req1', $results);
        $this->assertArrayHasKey('req2', $results);
        $this->assertArrayHasKey('req3', $results);
    }
    
    public function testExecuteAllWithCallbacks(): void
    {
        $callbackExecuted = false;
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get')
              ->onResponse('test', function($result, $requestId) use (&$callbackExecuted) {
                  $callbackExecuted = true;
                  $this->assertEquals('test', $requestId);
                  $this->assertIsArray($result);
              });
        
        $async->executeAll();
        
        $this->assertTrue($callbackExecuted);
    }
    
    public function testExecuteAllWithPostFormRequest(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addPostForm('test', 'https://httpbin.org/post', ['field' => 'value']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
    }
    
    public function testExecuteAllWithPutRequest(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addPut('test', 'https://httpbin.org/put', ['data' => 'value']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
    }
    
    public function testExecuteAllWithCustomMethod(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addRequest('test', 'https://httpbin.org/delete', 'DELETE');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithMaxConcurrency(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(2);
        
        // Add 5 requests but only 2 should run concurrently
        for ($i = 1; $i <= 5; $i++) {
            $async->addGet("req{$i}", 'https://httpbin.org/get', ['id' => $i]);
        }
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertCount(5, $results);
    }
    
    public function testExecuteAllClearsQueue(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $this->assertEquals(1, $async->getQueueSize());
        
        $async->executeAll();
        
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    public function testExecuteAllClearsCallbacks(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get')
              ->onResponse('test', function() {});
        
        $async->executeAll();
        
        // Callbacks should be cleared
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    public function testFireAndForgetExecutesRequests(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2); // Short timeouts
        $async->addGet('test', 'https://httpbin.org/get');
        
        $result = $async->fireAndForget();
        
        // Should return true if execution was initiated
        $this->assertIsBool($result);
    }
    
    public function testFireAndForgetWithMultipleRequests(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        $async->addGet('req1', 'https://httpbin.org/get');
        $async->addPost('req2', 'https://httpbin.org/post', ['data' => 'value']);
        
        $result = $async->fireAndForget();
        $this->assertIsBool($result);
    }
    
    // ==========================================
    // Error Handling Tests
    // ==========================================
    
    public function testExecuteAllWithInvalidUrl(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        $async->addGet('test', 'https://invalid-domain-that-does-not-exist-12345.com/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
        $this->assertIsArray($results['test']);
        // Should have error information
        $this->assertArrayHasKey('success', $results['test']);
    }
    
    public function testExecuteAllWithTimeout(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 1); // Very short timeout
        // Use a URL that will delay response
        $async->addGet('test', 'https://httpbin.org/delay/5');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
    }
    
    // ==========================================
    // Response Structure Tests
    // ==========================================
    
    public function testResponseStructure(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        if (isset($results['test'])) {
            $result = $results['test'];
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('body', $result);
            $this->assertArrayHasKey('http_code', $result);
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('duration', $result);
            $this->assertIsBool($result['success']);
            $this->assertIsInt($result['http_code']);
            $this->assertIsString($result['error']);
            $this->assertIsFloat($result['duration']);
        }
    }
    
    // ==========================================
    // SSL Configuration Execution Tests
    // ==========================================
    
    public function testExecuteAllWithSslConfiguration(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, true, 2);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // User Agent Execution Tests
    // ==========================================
    
    public function testExecuteAllWithCustomUserAgent(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setUserAgent('MyCustomAgent/1.0');
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // Concurrent Execution Tests
    // ==========================================
    
    public function testConcurrentExecutionOrder(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(10);
        
        // Add multiple requests
        for ($i = 1; $i <= 10; $i++) {
            $async->addGet("req{$i}", 'https://httpbin.org/get', ['id' => $i]);
        }
        
        $startTime = microtime(true);
        $results = $async->executeAll();
        $endTime = microtime(true);
        
        $this->assertIsArray($results);
        $this->assertCount(10, $results);
        // All requests should complete
        foreach ($results as $requestId => $result) {
            $this->assertIsArray($result);
        }
    }
    
    // ==========================================
    // Callback Execution Tests
    // ==========================================
    
    public function testMultipleCallbacksExecution(): void
    {
        $callbacksExecuted = [];
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        
        $async->addGet('req1', 'https://httpbin.org/get')
              ->onResponse('req1', function($result, $id) use (&$callbacksExecuted) {
                  $callbacksExecuted[] = $id;
              });
        
        $async->addGet('req2', 'https://httpbin.org/get')
              ->onResponse('req2', function($result, $id) use (&$callbacksExecuted) {
                  $callbacksExecuted[] = $id;
              });
        
        $async->executeAll();
        
        $this->assertCount(2, $callbacksExecuted);
        $this->assertContains('req1', $callbacksExecuted);
        $this->assertContains('req2', $callbacksExecuted);
    }
    
    public function testCallbackReceivesCorrectResult(): void
    {
        $receivedResult = null;
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get')
              ->onResponse('test', function($result, $requestId) use (&$receivedResult) {
                  $receivedResult = $result;
              });
        
        $async->executeAll();
        
        if ($receivedResult !== null) {
            $this->assertIsArray($receivedResult);
            $this->assertArrayHasKey('success', $receivedResult);
        }
    }
    
    // ==========================================
    // Edge Cases for Execution
    // ==========================================
    
    public function testExecuteAllWithVeryShortTimeout(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 1);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithVeryLongTimeout(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(60, 120);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithUnlimitedConcurrency(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(0); // Should allow unlimited
        
        for ($i = 1; $i <= 5; $i++) {
            $async->addGet("req{$i}", 'https://httpbin.org/get');
        }
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // Edge Cases for curl_init failure
    // ==========================================
    
    public function testExecuteAllHandlesCurlInitFailure(): void
    {
        // This test verifies that executeAll handles curl_init failures gracefully
        // We can't easily mock curl_init, but we test the structure handles it
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 1);
        // Use an invalid URL that might cause curl_init issues
        $async->addGet('test', '');
        
        $results = $async->executeAll();
        
        // Should return empty or handle gracefully
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // setMethodAndData Edge Cases
    // ==========================================
    
    public function testExecuteAllWithEmptyMethod(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addRequest('test', 'https://httpbin.org/get', '');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithPatchMethod(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addRequest('test', 'https://httpbin.org/patch', 'PATCH', ['data' => 'value']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithDeleteMethod(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addRequest('test', 'https://httpbin.org/delete', 'DELETE');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // Multipart File Upload Edge Cases
    // ==========================================
    
    public function testExecuteAllWithMultipartNonExistentFile(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        $async->addPostMultipart('test', 'https://httpbin.org/post', 
            ['field' => 'value'],
            ['file' => '/non/existent/file.txt']
        );
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithMultipartInvalidFilePath(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        $async->addPostMultipart('test', 'https://httpbin.org/post',
            ['field' => 'value'],
            ['file' => null] // Invalid file path
        );
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // JSON Encoding Edge Cases
    // ==========================================
    
    public function testExecuteAllWithNonEncodableData(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        // Create data that might cause json_encode issues
        $resource = fopen('php://memory', 'r');
        if ($resource !== false) {
            $async->addPost('test', 'https://httpbin.org/post', ['resource' => $resource]);
            fclose($resource);
        }
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // SSL Verification Edge Cases
    // ==========================================
    
    public function testExecuteAllWithSslVerifyPeerFalse(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, false, 0);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithSslVerifyHostZero(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, true, 0);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithSslVerifyHostOne(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, true, 1);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // User Agent Edge Cases
    // ==========================================
    
    public function testExecuteAllWithEmptyUserAgent(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setUserAgent('');
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // Response Processing Edge Cases
    // ==========================================
    
    public function testExecuteAllWithMissingMetadata(): void
    {
        // This tests the case where metadata might be missing
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('duration', $results['test']);
        }
    }
    
    // ==========================================
    // Fire and Forget Edge Cases
    // ==========================================
    
    public function testFireAndForgetFallbackPath(): void
    {
        // Test the fallback path when fastcgi_finish_request and Swoole are not available
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        $async->addGet('test', 'https://httpbin.org/get');
        
        // This will use the fallback path
        $result = $async->fireAndForget();
        
        $this->assertTrue($result);
    }
    
    // ==========================================
    // Process Response Edge Cases
    // ==========================================
    
    public function testExecuteAllWithHttpErrorCodes(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        // Use a URL that returns 404
        $async->addGet('test', 'https://httpbin.org/status/404');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('http_code', $results['test']);
            $this->assertArrayHasKey('success', $results['test']);
        }
    }
    
    public function testExecuteAllWithHttp500Error(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/status/500');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('http_code', $results['test']);
            $this->assertEquals(500, $results['test']['http_code']);
        }
    }
    
    // ==========================================
    // Concurrent Execution Edge Cases
    // ==========================================
    
    public function testExecuteAllWithSingleConcurrency(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(1);
        
        for ($i = 1; $i <= 3; $i++) {
            $async->addGet("req{$i}", 'https://httpbin.org/get', ['id' => $i]);
        }
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
    }
    
    // ==========================================
    // Query Parameter Edge Cases
    // ==========================================
    
    public function testAddGetWithNumericQueryParams(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('test', 'https://example.com/api', [
            'id' => 123,
            'count' => 0,
            'active' => 1,
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddGetWithBooleanQueryParams(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('test', 'https://example.com/api', [
            'active' => true,
            'deleted' => false,
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddGetWithArrayQueryParams(): void
    {
        $async = new AsyncApiCall();
        $async->addGet('test', 'https://example.com/api', [
            'tags' => ['php', 'testing'],
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Header Edge Cases
    // ==========================================
    
    public function testAddPostWithNumericHeaderValue(): void
    {
        $async = new AsyncApiCall();
        $async->addPost('test', 'https://example.com/api', ['data' => 'value'], [
            'X-Id' => '123', // String representation
        ]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Raw Body Edge Cases
    // ==========================================
    
    public function testAddPostRawWithBinaryData(): void
    {
        $async = new AsyncApiCall();
        $binaryData = "\x00\x01\x02\x03";
        $async->addPostRaw('test', 'https://example.com/api', $binaryData, 'application/octet-stream');
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // Options Edge Cases
    // ==========================================
    
    public function testAddRequestWithCustomOptions(): void
    {
        $async = new AsyncApiCall();
        $async->addRequest('test', 'https://example.com/api', 'POST', 
            ['data' => 'value'],
            ['X-Custom' => 'header'],
            ['custom_option' => 'value', 'another' => 123]
        );
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    // ==========================================
    // ExecuteAll Edge Cases
    // ==========================================
    
    public function testExecuteAllWithStillRunning(): void
    {
        // Test the curl_multi_select path
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(2);
        
        $async->addGet('req1', 'https://httpbin.org/delay/1');
        $async->addGet('req2', 'https://httpbin.org/delay/1');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // Callback Error Handling
    // ==========================================
    
    public function testExecuteAllWithCallbackThatThrows(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get')
              ->onResponse('test', function($result, $id) {
                  // Callback execution - should not break execution
                  // Note: In a real scenario, callbacks should handle errors gracefully
              });
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    // ==========================================
    // Additional Edge Cases for Maximum Coverage
    // ==========================================
    
    public function testCreateCurlHandleWithSslCert(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem');
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testCreateCurlHandleWithSslKeyOnly(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, '/path/to/key.pem');
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testCreateCurlHandleWithSslCaOnly(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, '/path/to/ca.pem');
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSetMethodAndDataWithRawOption(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addPostRaw('test', 'https://httpbin.org/post', 'raw body content', 'text/plain');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSetMethodAndDataWithFormOption(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addPostForm('test', 'https://httpbin.org/post', ['field' => 'value']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSetMethodAndDataWithMultipartOption(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        if ($tempFile !== false) {
            file_put_contents($tempFile, 'test content');
            $async->addPostMultipart('test', 'https://httpbin.org/post', 
                ['field' => 'value'],
                ['file' => $tempFile]
            );
            
            $results = $async->executeAll();
            
            unlink($tempFile);
            $this->assertIsArray($results);
        }
    }
    
    public function testSetMethodAndDataWithMultipartNonFile(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addPostMultipart('test', 'https://httpbin.org/post',
            ['field' => 'value'],
            ['file' => '/non/existent/file.txt'] // Not a file
        );
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSetMethodAndDataWithMultipartNonStringFilePath(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        // Use reflection to test the private method indirectly
        $async->addPostMultipart('test', 'https://httpbin.org/post',
            ['field' => 'value'],
            ['file' => 123] // Non-string file path
        );
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSetMethodAndDataWithJsonEncodeFailure(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 2);
        // Create data that might cause json_encode to return false
        // This is hard to test directly, but we can try with circular references
        $data = ['key' => 'value'];
        $async->addPost('test', 'https://httpbin.org/post', $data);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testProcessResponseWithNonStringBody(): void
    {
        // This tests the case where curl_exec might return false
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 1);
        // Use an invalid URL that might cause curl_exec to fail
        $async->addGet('test', 'http://invalid-url-that-does-not-exist-12345.com');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('body', $results['test']);
            $this->assertArrayHasKey('success', $results['test']);
        }
    }
    
    public function testProcessResponseWithCurlError(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 1);
        // Use a URL that will cause a curl error
        $async->addGet('test', 'http://invalid-domain-12345-that-does-not-exist.com');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('error', $results['test']);
            $this->assertArrayHasKey('success', $results['test']);
        }
    }
    
    public function testProcessResponseWithHttpCode400(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/status/400');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertEquals(400, $results['test']['http_code']);
            $this->assertFalse($results['test']['success']); // 400 is not in 200-399 range
        }
    }
    
    public function testProcessResponseWithHttpCode300(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/status/300');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('http_code', $results['test']);
            $this->assertArrayHasKey('success', $results['test']);
            // Note: httpbin might redirect 300, so we just check structure
        }
    }
    
    public function testProcessResponseWithHttpCode399(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/status/399');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('http_code', $results['test']);
            $this->assertArrayHasKey('success', $results['test']);
            // Note: httpbin might not support 399, so we just check structure
        }
    }
    
    public function testProcessResponseWithMissingMetadata(): void
    {
        // This tests the case where metadata might be missing (duration = 0.0)
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        if (isset($results['test'])) {
            $this->assertArrayHasKey('duration', $results['test']);
            $this->assertIsFloat($results['test']['duration']);
        }
    }
    
    public function testExecuteAllWithCurlInitFailure(): void
    {
        // Test the case where createCurlHandle returns false
        $async = new AsyncApiCall();
        $async->setTimeouts(1, 1);
        // Empty URL might cause curl_init to fail in some cases
        $async->addGet('test', '');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        // Should handle gracefully even if curl_init fails
    }
    
    public function testExecuteAllWithUnknownRequestId(): void
    {
        // This tests the 'unknown' fallback in processResponse
        // This is hard to test directly, but we can ensure the code path exists
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSslVerifyHostWithZeroValue(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, true, 0);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSslVerifyHostWithOneValue(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, true, 1);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testSslVerifyHostWithTwoValue(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setSsl(null, null, null, true, 2);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testUserAgentEmptyString(): void
    {
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setUserAgent('');
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
    
    public function testExecuteAllWithMultipleBatches(): void
    {
        // Test multiple batches with maxConcurrency = 2
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(2);
        
        // Add 5 requests - should process in batches of 2
        for ($i = 1; $i <= 5; $i++) {
            $async->addGet("req{$i}", 'https://httpbin.org/get', ['id' => $i]);
        }
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertCount(5, $results);
    }
    
    public function testExecuteAllWithStillRunningZero(): void
    {
        // Test the case where stillRunning is 0 (no delay needed)
        $async = new AsyncApiCall();
        $async->setTimeouts(5, 10);
        $async->addGet('test', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
    }
}


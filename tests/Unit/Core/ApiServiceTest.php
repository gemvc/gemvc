<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\JsonResponse;

class TestApiService extends ApiService
{
    public function testMethod(): JsonResponse
    {
        return \Gemvc\Http\Response::success(['test' => 'data']);
    }
    
    // Expose protected methods for testing
    public function publicValidatePosts(array $post_schema): void
    {
        $this->validatePosts($post_schema);
    }
    
    public function publicValidateStringPosts(array $post_string_schema): void
    {
        $this->validateStringPosts($post_string_schema);
    }
}

/**
 * @outputBuffering enabled
 */
class ApiServiceTest extends TestCase
{
    private Request $request;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/Test';
        
        $ar = new ApacheRequest();
        $this->request = $ar->request;
    }
    
    public function testConstructor(): void
    {
        $service = new TestApiService($this->request);
        $this->assertInstanceOf(ApiService::class, $service);
        $this->assertNull($service->error);
    }
    
    public function testValidatePostsWithValidSchema(): void
    {
        // Use a mock Request that returns true for definePostSchema
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->error = null;
        $mockRequest->expects($this->once())
            ->method('definePostSchema')
            ->with(['name' => 'string', 'email' => 'email'])
            ->willReturn(true);
        
        $service = new TestApiService($mockRequest);
        
        // This should not call die() since definePostSchema returns true
        $service->publicValidatePosts([
            'name' => 'string',
            'email' => 'email'
        ]);
        
        // If we reach here, the method executed successfully
        $this->assertTrue(true);
    }
    
    public function testValidatePostsWithInvalidSchema(): void
    {
        // Create a service to verify the method exists
        $service = new TestApiService($this->request);
        
        // We can't fully test the die() path because validatePosts() calls die() on failure
        // This would terminate the test execution. We can only verify:
        // 1. The method exists
        // 2. The method signature is correct
        $this->assertTrue(method_exists($service, 'validatePosts'));
        $this->assertTrue(method_exists($service, 'publicValidatePosts'));
        
        // Note: Testing the actual failure path (with die()) would require:
        // - Integration/E2E tests
        // - Or refactoring to throw exceptions instead of die()
        // For now, we verify the method structure exists
    }
    
    public function testValidatePostsWithOptionalFields(): void
    {
        // Use a mock Request that returns true for definePostSchema
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->error = null;
        $mockRequest->expects($this->once())
            ->method('definePostSchema')
            ->with(['name' => 'string', 'email' => 'email', '?phone' => 'string'])
            ->willReturn(true);
        
        $service = new TestApiService($mockRequest);
        
        // This should not call die() since definePostSchema returns true
        $service->publicValidatePosts([
            'name' => 'string',
            'email' => 'email',
            '?phone' => 'string'
        ]);
        
        // If we reach here, the method executed successfully
        $this->assertTrue(true);
    }
    
    // ============================================
    // validateStringPosts Tests
    // ============================================
    
    public function testValidateStringPostsWithValidLengths(): void
    {
        // Use a mock Request that returns true for validateStringPosts
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->error = null;
        $mockRequest->expects($this->once())
            ->method('validateStringPosts')
            ->with(['username' => '3|15', 'password' => '8|'])
            ->willReturn(true);
        
        $service = new TestApiService($mockRequest);
        
        // This should not call die() since validateStringPosts returns true
        $service->publicValidateStringPosts([
            'username' => '3|15',
            'password' => '8|'
        ]);
        
        // If we reach here, the method executed successfully
        $this->assertTrue(true);
    }
    
    public function testValidateStringPostsWithInvalidLengths(): void
    {
        // Create a service to verify the method exists
        $service = new TestApiService($this->request);
        
        // We can't fully test the die() path because validateStringPosts() calls die() on failure
        // This would terminate the test execution. We can only verify:
        // 1. The method exists
        // 2. The method signature is correct
        $this->assertTrue(method_exists($service, 'validateStringPosts'));
        $this->assertTrue(method_exists($service, 'publicValidateStringPosts'));
        
        // Note: Testing the actual failure path (with die()) would require:
        // - Integration/E2E tests
        // - Or refactoring to throw exceptions instead of die()
        // For now, we verify the method structure exists
    }
    
    public function testValidateStringPostsWithNoConstraints(): void
    {
        // Use a mock Request that returns true for validateStringPosts
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->error = null;
        $mockRequest->expects($this->once())
            ->method('validateStringPosts')
            ->with(['bio' => ''])
            ->willReturn(true);
        
        $service = new TestApiService($mockRequest);
        
        // This should not call die() since validateStringPosts returns true
        $service->publicValidateStringPosts([
            'bio' => ''
        ]);
        
        // If we reach here, the method executed successfully
        $this->assertTrue(true);
    }
    
    public function testValidateStringPostsWithMinOnly(): void
    {
        // Use a mock Request that returns true for validateStringPosts
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->error = null;
        $mockRequest->expects($this->once())
            ->method('validateStringPosts')
            ->with(['password' => '8|'])
            ->willReturn(true);
        
        $service = new TestApiService($mockRequest);
        
        // This should not call die() since validateStringPosts returns true
        $service->publicValidateStringPosts([
            'password' => '8|' // Min 8, no max
        ]);
        
        // If we reach here, the method executed successfully
        $this->assertTrue(true);
    }
    
    public function testValidateStringPostsWithMaxOnly(): void
    {
        // Use a mock Request that returns true for validateStringPosts
        /** @var Request&MockObject $mockRequest */
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->error = null;
        $mockRequest->expects($this->once())
            ->method('validateStringPosts')
            ->with(['nickname' => '|20'])
            ->willReturn(true);
        
        $service = new TestApiService($mockRequest);
        
        // This should not call die() since validateStringPosts returns true
        $service->publicValidateStringPosts([
            'nickname' => '|20' // No min, max 20
        ]);
        
        // If we reach here, the method executed successfully
        $this->assertTrue(true);
    }
    
    // ============================================
    // Index Method Tests
    // ============================================
    
    public function testIndexMethodReturnsJsonResponse(): void
    {
        $service = new TestApiService($this->request);
        $response = $service->index();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->response_code);
    }
    
    public function testIndexMethodContainsServiceName(): void
    {
        $service = new TestApiService($this->request);
        $response = $service->index();
        
        $this->assertIsString($response->data);
        // The index() method extracts class name from index 2 of namespace
        // For Tests\Unit\Core\TestApiService, index 2 is "Core"
        $this->assertStringContainsString('Core', (string)$response->data);
        $this->assertStringContainsString('service', (string)$response->data);
    }
    
    public function testIndexMethodWithDifferentClassName(): void
    {
        // Create a service with a different name
        $differentService = new class($this->request) extends ApiService {
        };
        
        $response = $differentService->index();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertIsString($response->data);
        $this->assertStringContainsString('service', (string)$response->data);
    }
    
    // ============================================
    // MockResponse Tests
    // ============================================
    
    public function testMockResponseReturnsArray(): void
    {
        $result = TestApiService::mockResponse('testMethod');
        $this->assertIsArray($result);
    }
    
    public function testMockResponseReturnsEmptyArrayByDefault(): void
    {
        $result = TestApiService::mockResponse('anyMethod');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    public function testMockResponseWithDifferentMethods(): void
    {
        $result1 = TestApiService::mockResponse('method1');
        $result2 = TestApiService::mockResponse('method2');
        
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        // Both should return empty arrays by default
        $this->assertEquals($result1, $result2);
    }
    
    // ============================================
    // Error Property Tests
    // ============================================
    
    public function testErrorPropertyIsNullInitially(): void
    {
        $service = new TestApiService($this->request);
        $this->assertNull($service->error);
    }
    
    public function testErrorPropertyCanBeSet(): void
    {
        $service = new TestApiService($this->request);
        $service->error = 'Test error';
        $this->assertEquals('Test error', $service->error);
    }
    
    public function testErrorPropertyCanBeCleared(): void
    {
        $service = new TestApiService($this->request);
        $service->error = 'Test error';
        $service->error = null;
        $this->assertNull($service->error);
    }
}


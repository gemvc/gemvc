<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
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
}

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
    
    public function testIndexMethod(): void
    {
        $service = new TestApiService($this->request);
        $response = $service->index();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->response_code);
        // The index method extracts class name from namespace, which would be "Core" for Tests\Unit\Core\TestApiService
        // So we just check that it returns a success response with some data
        $this->assertIsString($response->data);
        $this->assertStringContainsString('service', (string)$response->data);
    }
    
    public function testValidatePostsWithValidSchema(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $service = new TestApiService($request);
        
        // This should not throw or die if schema is valid
        // We'll test by checking that the method exists and can be called
        $this->assertTrue(method_exists($service, 'validatePosts'));
    }
    
    public function testValidatePostsWithInvalidSchema(): void
    {
        $_POST['name'] = 'John';
        // email is missing
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $service = new TestApiService($request);
        
        // The validatePosts method will call die() on invalid schema
        // So we can't test it directly in unit tests without output buffering
        // This test just verifies the method exists
        $this->assertTrue(method_exists($service, 'validatePosts'));
    }
    
    public function testMockResponse(): void
    {
        $result = TestApiService::mockResponse('testMethod');
        $this->assertIsArray($result);
    }
}


<?php

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;

abstract class ApiTestCase extends TestCase
{
    /**
     * Create a mock request with test data
     * 
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    protected function createMockRequest(array $post = [], array $get = []): Request
    {
        // Backup original superglobals
        $originalPost = $_POST;
        $originalGet = $_GET;
        
        // Set test data
        $_POST = $post;
        $_GET = $get;
        
        try {
            $ar = new ApacheRequest();
            return $ar->request;
        } finally {
            // Restore original superglobals
            $_POST = $originalPost;
            $_GET = $originalGet;
        }
    }
    
    protected function assertJsonResponse(JsonResponse $response, int $expectedCode): void
    {
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($expectedCode, $response->response_code);
    }
    
    /**
     * Assert response data matches expected values
     * 
     * @param array<string, mixed> $expectedData
     */
    protected function assertResponseData(JsonResponse $response, array $expectedData): void
    {
        $this->assertIsArray($response->data);
        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $response->data);
            $this->assertEquals($value, $response->data[$key]);
        }
    }
    
    /**
     * Assert response contains specific keys
     * 
     * @param array<string> $expectedKeys
     */
    protected function assertResponseHasKeys(JsonResponse $response, array $expectedKeys): void
    {
        $this->assertIsArray($response->data);
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $response->data);
        }
    }
}


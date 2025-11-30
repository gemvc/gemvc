<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\JsonResponse;

class JsonResponseTest extends TestCase
{
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $response = new JsonResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertNull($response->data);
    }
    
    // ============================================
    // Create Method Tests
    // ============================================
    
    public function testCreateWithAllParameters(): void
    {
        $response = new JsonResponse();
        $data = ['id' => 1, 'name' => 'Test'];
        $result = $response->create(200, $data, 1, 'Test message');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals('OK', $response->message);
        $this->assertEquals(1, $response->count);
        $this->assertEquals('Test message', $response->service_message);
        $this->assertEquals($data, $response->data);
        $this->assertIsString($response->json_response);
    }
    
    public function testCreateWithNullCount(): void
    {
        $response = new JsonResponse();
        $result = $response->create(200, ['test' => 'data'], null, 'Message');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertNull($response->count);
    }
    
    public function testCreateWithNullServiceMessage(): void
    {
        $response = new JsonResponse();
        $result = $response->create(200, ['test' => 'data'], 1, null);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertNull($response->service_message);
    }
    
    // ============================================
    // Success Response Tests
    // ============================================
    
    public function testSuccess(): void
    {
        $response = new JsonResponse();
        $data = ['id' => 1];
        $result = $response->success($data, 1, 'Success message');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals('OK', $response->message);
        $this->assertEquals($data, $response->data);
    }
    
    public function testSuccessWithNullParameters(): void
    {
        $response = new JsonResponse();
        $result = $response->success(['test' => 'data']);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $response->response_code);
    }
    
    // ============================================
    // Created Response Tests
    // ============================================
    
    public function testCreated(): void
    {
        $response = new JsonResponse();
        $data = ['id' => 1, 'name' => 'New'];
        $result = $response->created($data, 1, 'Created message');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(201, $response->response_code);
        $this->assertEquals('created', $response->message);
    }
    
    // ============================================
    // Updated Response Tests
    // ============================================
    
    public function testUpdated(): void
    {
        $response = new JsonResponse();
        $result = $response->updated(['id' => 1], 1, 'Updated message');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(209, $response->response_code);
        $this->assertEquals('updated', $response->message);
    }
    
    // ============================================
    // Deleted Response Tests
    // ============================================
    
    public function testDeleted(): void
    {
        $response = new JsonResponse();
        $result = $response->deleted(['id' => 1], 1, 'Deleted message');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(210, $response->response_code);
        $this->assertEquals('deleted', $response->message);
    }
    
    // ============================================
    // Success But No Content Tests
    // ============================================
    
    public function testSuccessButNoContentToShow(): void
    {
        $response = new JsonResponse();
        $result = $response->successButNoContentToShow(null, 0, 'No content');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(204, $response->response_code);
        $this->assertEquals('no-content', $response->message);
    }
    
    // ============================================
    // Error Response Tests
    // ============================================
    
    public function testUnauthorized(): void
    {
        $response = new JsonResponse();
        $result = $response->unauthorized('Auth required');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(401, $response->response_code);
        $this->assertEquals('unauthorized', $response->message);
        $this->assertEquals('Auth required', $response->service_message);
    }
    
    public function testUnauthorizedWithNullMessage(): void
    {
        $response = new JsonResponse();
        $result = $response->unauthorized(null);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(401, $response->response_code);
        $this->assertNull($response->service_message);
    }
    
    public function testForbidden(): void
    {
        $response = new JsonResponse();
        $result = $response->forbidden('Access denied');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(403, $response->response_code);
        $this->assertEquals('forbidden', $response->message);
    }
    
    public function testNotFound(): void
    {
        $response = new JsonResponse();
        $result = $response->notFound('Resource not found');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(404, $response->response_code);
        $this->assertEquals('not found', $response->message);
    }
    
    public function testInternalError(): void
    {
        $response = new JsonResponse();
        $result = $response->internalError('Server error');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(500, $response->response_code);
        $this->assertEquals('internal error', $response->message);
    }
    
    public function testBadRequest(): void
    {
        $response = new JsonResponse();
        $result = $response->badRequest('Invalid request');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(400, $response->response_code);
        $this->assertEquals('bad request', $response->message);
    }
    
    public function testNotAcceptable(): void
    {
        $response = new JsonResponse();
        $result = $response->notAcceptable('Not acceptable');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(406, $response->response_code);
        $this->assertEquals('not acceptable', $response->message);
    }
    
    public function testConflict(): void
    {
        $response = new JsonResponse();
        $result = $response->conflict('Conflict occurred');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(409, $response->response_code);
        $this->assertEquals('conflict', $response->message);
    }
    
    public function testUnsupportedMediaType(): void
    {
        $response = new JsonResponse();
        $result = $response->unsupportedMediaType('Unsupported media');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(415, $response->response_code);
        $this->assertEquals('unsupported media type', $response->message);
    }
    
    public function testUnprocessableEntity(): void
    {
        $response = new JsonResponse();
        $result = $response->unprocessableEntity('Validation failed');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(422, $response->response_code);
        $this->assertEquals('unprocessable entity', $response->message);
    }
    
    public function testUnknownError(): void
    {
        $response = new JsonResponse();
        $data = ['error' => 'details'];
        $result = $response->unknownError('Unknown error', $data);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(0, $response->response_code);
        $this->assertEquals('unknown error', $response->message);
        $this->assertEquals($data, $response->data);
    }
    
    // ============================================
    // HTTP Message Tests
    // ============================================
    
    public function testSetHttpMessageForAllCodes(): void
    {
        $response = new JsonResponse();
        
        // Test all known HTTP codes
        $codes = [
            200 => 'OK',
            201 => 'created',
            204 => 'no-content',
            209 => 'updated',
            210 => 'deleted',
            400 => 'bad request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not found',
            406 => 'not acceptable',
            409 => 'conflict',
            415 => 'unsupported media type',
            422 => 'unprocessable entity',
            500 => 'internal error',
        ];
        
        foreach ($codes as $code => $expectedMessage) {
            $response->create($code, ['test' => 'data']);
            $this->assertEquals($expectedMessage, $response->message, "Failed for code $code");
        }
    }
    
    public function testSetHttpMessageForUnknownCode(): void
    {
        $response = new JsonResponse();
        $result = $response->create(999, ['test' => 'data']);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals('unknown error', $response->message);
    }
    
    // ============================================
    // Payload Need Items Tests
    // ============================================
    
    public function testPayloadNeedItems(): void
    {
        $response = new JsonResponse();
        $response->payloadNeedItems(['name', 'email', 'password']);
        
        $this->assertEquals(400, $response->response_code);
        $this->assertStringContainsString('payload need items', $response->service_message);
        $this->assertStringContainsString('name', $response->service_message);
        $this->assertStringContainsString('email', $response->service_message);
        $this->assertStringContainsString('password', $response->service_message);
    }
    
    public function testPayloadNeedItemsWithSingleItem(): void
    {
        $response = new JsonResponse();
        $response->payloadNeedItems(['id']);
        
        $this->assertEquals(400, $response->response_code);
        $this->assertStringContainsString('payload need items', $response->service_message);
        $this->assertStringContainsString('id', $response->service_message);
    }
    
    public function testPayloadNeedItemsWithEmptyArray(): void
    {
        $response = new JsonResponse();
        $response->payloadNeedItems([]);
        
        $this->assertEquals(400, $response->response_code);
        $this->assertStringContainsString('payload need items', $response->service_message);
    }
    
    // ============================================
    // JSON Encoding Tests
    // ============================================
    
    public function testJsonResponseIsEncoded(): void
    {
        $response = new JsonResponse();
        $data = ['id' => 1, 'name' => 'Test'];
        $response->success($data);
        
        $this->assertIsString($response->json_response);
        $this->assertNotEmpty($response->json_response);
        
        // Verify it's valid JSON
        $decoded = json_decode($response->json_response, true);
        $this->assertNotNull($decoded);
    }
    
    public function testJsonResponseContainsAllFields(): void
    {
        $response = new JsonResponse();
        $data = ['id' => 1];
        $response->success($data, 1, 'Test message');
        
        $decoded = json_decode($response->json_response, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('response_code', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('count', $decoded);
        $this->assertArrayHasKey('service_message', $decoded);
    }
    
    // ============================================
    // Show Method Tests (Output Suppression)
    // ============================================
    
    public function testShowMethodExists(): void
    {
        $response = new JsonResponse();
        $this->assertTrue(method_exists($response, 'show'));
    }
    
    // ============================================
    // ShowSwoole Method Tests
    // ============================================
    
    public function testShowSwooleWithStandardCode(): void
    {
        $response = new JsonResponse();
        $response->success(['test' => 'data']);
        
        // Create a mock Swoole response object
        $swooleResponse = new class {
            public array $headers = [];
            public ?int $statusCode = null;
            public ?string $statusMessage = null;
            public ?string $endData = null;
            
            public function header(string $name, string $value): void
            {
                $this->headers[$name] = $value;
            }
            
            public function status(int $code, ?string $message = null): void
            {
                $this->statusCode = $code;
                $this->statusMessage = $message;
            }
            
            public function end(string $data): void
            {
                $this->endData = $data;
            }
        };
        
        $response->showSwoole($swooleResponse);
        
        $this->assertEquals('application/json', $swooleResponse->headers['Content-Type']);
        $this->assertEquals(200, $swooleResponse->statusCode);
        $this->assertNotNull($swooleResponse->endData);
    }
    
    public function testShowSwooleWithCustomCode209(): void
    {
        $response = new JsonResponse();
        $response->updated(['test' => 'data']);
        
        $this->assertEquals(209, $response->response_code);
        $this->assertTrue(method_exists($response, 'showSwoole'));
    }
    
    public function testShowSwooleWithCustomCode210(): void
    {
        $response = new JsonResponse();
        $response->deleted(['test' => 'data']);
        
        $this->assertEquals(210, $response->response_code);
        $this->assertTrue(method_exists($response, 'showSwoole'));
    }
    
    // ============================================
    // Edge Cases Tests
    // ============================================
    
    public function testCreateWithComplexData(): void
    {
        $response = new JsonResponse();
        $complexData = [
            'user' => [
                'id' => 1,
                'name' => 'John',
                'roles' => ['admin', 'user']
            ],
            'metadata' => [
                'created_at' => '2024-01-01',
                'tags' => ['important', 'urgent']
            ]
        ];
        
        $result = $response->success($complexData);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals($complexData, $response->data);
        
        // Verify JSON encoding works with complex data
        $decoded = json_decode($response->json_response, true);
        $this->assertIsArray($decoded);
        $this->assertEquals($complexData, $decoded['data']);
    }
    
    public function testCreateWithNullData(): void
    {
        $response = new JsonResponse();
        $result = $response->success(null);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertNull($response->data);
    }
    
    public function testCreateWithEmptyArray(): void
    {
        $response = new JsonResponse();
        $result = $response->success([]);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals([], $response->data);
    }
    
    public function testCreateWithStringData(): void
    {
        $response = new JsonResponse();
        $result = $response->success('Simple string');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals('Simple string', $response->data);
    }
    
    public function testCreateWithNumericData(): void
    {
        $response = new JsonResponse();
        $result = $response->success(12345);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(12345, $response->data);
    }
    
    public function testCreateWithBooleanData(): void
    {
        $response = new JsonResponse();
        $result = $response->success(true);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertTrue($response->data);
    }
}


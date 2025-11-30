<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;

class ResponseTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $response = Response::success($data, 1, 'Operation successful');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals('OK', $response->message);
        $this->assertEquals(1, $response->count);
        $this->assertEquals('Operation successful', $response->service_message);
        $this->assertEquals($data, $response->data);
    }
    
    public function testCreatedResponse(): void
    {
        $data = ['id' => 1, 'name' => 'New Item'];
        $response = Response::created($data, 1, 'Item created');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->response_code);
        $this->assertEquals('created', $response->message);
        $this->assertEquals(1, $response->count);
        $this->assertEquals('created: Item created', $response->service_message);
    }
    
    public function testUpdatedResponse(): void
    {
        $response = Response::updated(true, 1, 'Item updated');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(209, $response->response_code);
        $this->assertEquals('updated', $response->message);
        $this->assertEquals(1, $response->count);
        $this->assertEquals('updated: Item updated', $response->service_message);
    }
    
    public function testDeletedResponse(): void
    {
        $response = Response::deleted(true, 1, 'Item deleted');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(210, $response->response_code);
        $this->assertEquals('deleted', $response->message);
        $this->assertEquals(1, $response->count);
        $this->assertEquals('deleted: Item deleted', $response->service_message);
    }
    
    public function testNotFoundResponse(): void
    {
        $response = Response::notFound('Resource not found');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->response_code);
        $this->assertEquals('not found', $response->message);
        $this->assertEquals('not found: Resource not found', $response->service_message);
    }
    
    public function testBadRequestResponse(): void
    {
        $response = Response::badRequest('Invalid input');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->response_code);
        $this->assertEquals('bad request', $response->message);
        $this->assertEquals('bad request: Invalid input', $response->service_message);
    }
    
    public function testUnprocessableEntityResponse(): void
    {
        $response = Response::unprocessableEntity('Validation failed');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->response_code);
        $this->assertEquals('unprocessable entity', $response->message);
        $this->assertEquals('unprocessable entity: Validation failed', $response->service_message);
    }
    
    public function testUnauthorizedResponse(): void
    {
        $response = Response::unauthorized('Authentication required');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
        $this->assertEquals('unauthorized', $response->message);
        $this->assertEquals('unauthorized: Authentication required', $response->service_message);
    }
    
    public function testForbiddenResponse(): void
    {
        $response = Response::forbidden('Access denied');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->response_code);
        $this->assertEquals('forbidden', $response->message);
        $this->assertEquals('forbidden: Access denied', $response->service_message);
    }
    
    public function testInternalErrorResponse(): void
    {
        $response = Response::internalError('Server error');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->response_code);
        $this->assertEquals('internal error', $response->message);
        $this->assertEquals('internal error: Server error', $response->service_message);
    }
}


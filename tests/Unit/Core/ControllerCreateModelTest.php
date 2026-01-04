<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\ApacheRequest;
use Gemvc\Database\Table;

/**
 * Mock Table class for createModel tests
 */
class MockTableForCreateModel extends Table
{
    public int $id = 1;
    public string $name = 'Test';
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
    ];
    
    public function getTable(): string
    {
        return 'mock_table';
    }
    
    public function defineSchema(): array
    {
        return [];
    }
    
    // Expose getRequest for testing
    public function getRequestForTesting(): ?Request
    {
        // Use reflection to access private $_request property from Table class
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $property = $reflection->getProperty('_request');
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}

/**
 * Mock object without setRequest method
 */
class MockObjectWithoutSetRequest
{
    public int $id = 1;
}

class TestControllerForCreateModel extends Controller
{
    public ?string $error = null;
}

/**
 * Tests for Controller::createModel() method
 */
class ControllerCreateModelTest extends TestCase
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
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $this->request = $ar->request;
    }
    
    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }
    
    public function testCreateModelSetsRequestOnTable(): void
    {
        $controller = new TestControllerForCreateModel($this->request);
        $model = new MockTableForCreateModel();
        
        // Verify Request is not set initially
        $this->assertNull($model->getRequestForTesting());
        
        // Call createModel using reflection (protected method)
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('createModel');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $model);
        
        // Verify same instance is returned
        $this->assertSame($model, $result);
        
        // Verify Request is now set
        $this->assertSame($this->request, $model->getRequestForTesting());
    }
    
    public function testCreateModelReturnsSameInstance(): void
    {
        $controller = new TestControllerForCreateModel($this->request);
        $model = new MockTableForCreateModel();
        
        // Call createModel using reflection
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('createModel');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $model);
        
        $this->assertSame($model, $result);
    }
    
    public function testCreateModelHandlesObjectWithoutSetRequest(): void
    {
        $controller = new TestControllerForCreateModel($this->request);
        $object = new MockObjectWithoutSetRequest();
        
        // Call createModel using reflection
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('createModel');
        $method->setAccessible(true);
        
        // Should not throw exception
        $result = $method->invoke($controller, $object);
        
        // Should return same instance
        $this->assertSame($object, $result);
    }
    
    public function testCreateModelPropagatesRequestToConnectionManager(): void
    {
        $controller = new TestControllerForCreateModel($this->request);
        $model = new MockTableForCreateModel();
        
        // Call createModel using reflection
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('createModel');
        $method->setAccessible(true);
        $method->invoke($controller, $model);
        
        // Verify Request is set
        $this->assertSame($this->request, $model->getRequestForTesting());
        
        // When ConnectionManager is created, it should receive the Request
        // This is tested indirectly by checking that setRequest was called
        // The actual propagation is tested in ConnectionManager tests
    }
}


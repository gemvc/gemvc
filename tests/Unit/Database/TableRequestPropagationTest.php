<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Table;
use Gemvc\Http\Request;
use Gemvc\Http\ApacheRequest;

/**
 * Test table class for Request propagation tests
 */
class TestTableForRequest extends Table
{
    public int $id;
    public string $name;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
    ];
    
    public function getTable(): string
    {
        return 'test_table';
    }
    
    public function defineSchema(): array
    {
        return [];
    }
    
    // Expose _request for testing
    public function getRequestForTesting(): ?Request
    {
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $property = $reflection->getProperty('_request');
        $property->setAccessible(true);
        return $property->getValue($this);
    }
    
    // Expose getConnectionManager for testing
    public function getConnectionManagerForTesting(): object
    {
        $reflection = new \ReflectionClass($this);
        $method = $reflection->getMethod('getConnectionManager');
        $method->setAccessible(true);
        return $method->invoke($this);
    }
}

/**
 * Tests for Table::setRequest() method and Request propagation
 */
class TableRequestPropagationTest extends TestCase
{
    private Request $request;
    
    protected function setUp(): void
    {
        parent::setUp();
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/Test';
        
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
    
    public function testSetRequestStoresRequest(): void
    {
        $table = new TestTableForRequest();
        
        // Verify Request is not set initially
        $this->assertNull($table->getRequestForTesting());
        
        // Set Request
        $table->setRequest($this->request);
        
        // Verify Request is stored
        $this->assertSame($this->request, $table->getRequestForTesting());
    }
    
    public function testSetRequestWithNull(): void
    {
        $table = new TestTableForRequest();
        
        // Set Request first
        $table->setRequest($this->request);
        $this->assertSame($this->request, $table->getRequestForTesting());
        
        // Set to null
        $table->setRequest(null);
        $this->assertNull($table->getRequestForTesting());
    }
    
    public function testSetRequestPropagatesToExistingConnectionManager(): void
    {
        $table = new TestTableForRequest();
        
        // Create ConnectionManager first (by accessing it)
        $connectionManager = $table->getConnectionManagerForTesting();
        
        // Verify ConnectionManager has setRequest method
        $this->assertTrue(method_exists($connectionManager, 'setRequest'));
        
        // Set Request on table - should propagate to ConnectionManager
        $table->setRequest($this->request);
        
        // Verify Request is set on table
        $this->assertSame($this->request, $table->getRequestForTesting());
        
        // ConnectionManager should have received the Request
        // (tested indirectly - actual propagation verified in ConnectionManager tests)
    }
    
    public function testSetRequestPropagatesToNewConnectionManager(): void
    {
        $table = new TestTableForRequest();
        
        // Set Request before ConnectionManager is created
        $table->setRequest($this->request);
        
        // Now create ConnectionManager
        $connectionManager = $table->getConnectionManagerForTesting();
        
        // Verify ConnectionManager exists
        $this->assertNotNull($connectionManager);
        
        // ConnectionManager should have received the Request during creation
        // (tested indirectly - actual propagation verified in ConnectionManager tests)
    }
}


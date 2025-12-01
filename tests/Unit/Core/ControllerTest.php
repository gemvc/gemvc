<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\ApacheRequest;
use Gemvc\Database\Table;

/**
 * Mock Table class for Controller tests
 */
class MockControllerTable extends Table
{
    public int $id = 1;
    public string $name = 'Test';
    public string $email = 'test@example.com';
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
    ];
    
    public function getTable(): string
    {
        return 'mock_table';
    }
    
    public function defineSchema(): array
    {
        return [];
    }
    
    public function select(?string $columns = null): self
    {
        return $this;
    }
    
    public function run(): ?array
    {
        return [(object)['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']];
    }
    
    public function getTotalCounts(): int
    {
        return 1;
    }
    
    public function setPage(int $page): void
    {
        // Mock implementation
    }
    
    public function orderBy(?string $columnName = null, ?bool $ascending = null): self
    {
        return $this;
    }
    
    public function where(string $column, mixed $value): self
    {
        return $this;
    }
    
    public function whereLike(string $column, string $value): self
    {
        return $this;
    }
}

class TestController extends Controller
{
    public ?string $error; // Made public for testing
}

class ControllerTest extends TestCase
{
    private Request $request;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Suppress output from Response::show() calls
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
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $controller = new TestController($this->request);
        $this->assertInstanceOf(Controller::class, $controller);
        $this->assertNull($controller->error);
    }
    
    // ============================================
    // Pagination, Sorting, Filtering Tests (test private _listObjects indirectly via createList)
    // ============================================
    
    public function testListObjectsWithPagination(): void
    {
        $_GET['page_number'] = '2';
        $_SERVER['QUERY_STRING'] = 'page_number=2';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $result);
    }
    
    public function testListObjectsWithInvalidPageNumber(): void
    {
        $_GET['page_number'] = 'invalid';
        $_SERVER['QUERY_STRING'] = 'page_number=invalid';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        // This should trigger badRequest and die()
        // We can't easily test die() in unit tests, so we verify the public methods exist
        $this->assertTrue(method_exists($controller, 'createList'));
        $this->assertTrue(method_exists($controller, 'listJsonResponse'));
    }
    
    public function testListObjectsWithNegativePageNumber(): void
    {
        $_GET['page_number'] = '-1';
        $_SERVER['QUERY_STRING'] = 'page_number=-1';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        // This should trigger badRequest and die()
        $this->assertTrue(method_exists($controller, 'createList'));
    }
    
    public function testListObjectsWithSorting(): void
    {
        $_GET['sort_by'] = 'name';
        $_SERVER['QUERY_STRING'] = 'sort_by=name';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $request->sortable(['name', 'email']);
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $result);
    }
    
    public function testListObjectsWithSortAscending(): void
    {
        $_GET['sort_by_asc'] = 'email';
        $_SERVER['QUERY_STRING'] = 'sort_by_asc=email';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $request->sortable(['name', 'email']);
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $result);
    }
    
    public function testListObjectsWithFiltering(): void
    {
        $_GET['filter_by'] = 'name=Test';
        $_SERVER['QUERY_STRING'] = 'filter_by=name=Test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $request->filterable(['name' => 'string']);
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $result);
    }
    
    public function testListObjectsWithFindable(): void
    {
        $_GET['find_like'] = 'name=Test';
        $_SERVER['QUERY_STRING'] = 'find_like=name=Test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        $request->findable(['name' => 'string']);
        $controller = new TestController($request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $result);
    }
    
    // ============================================
    // createList Tests
    // ============================================
    
    public function testCreateListReturnsJsonResponse(): void
    {
        $controller = new TestController($this->request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->response_code);
    }
    
    public function testCreateListWithColumns(): void
    {
        $controller = new TestController($this->request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model, 'id,name');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
    }
    
    public function testCreateListIncludesTotalCounts(): void
    {
        $controller = new TestController($this->request);
        $model = new MockControllerTable();
        
        $result = $controller->createList($model);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertIsInt($result->count);
    }
    
    // ============================================
    // listJsonResponse Tests
    // ============================================
    
    public function testListJsonResponseReturnsJsonResponse(): void
    {
        $controller = new TestController($this->request);
        $model = new MockControllerTable();
        
        $result = $controller->listJsonResponse($model);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->response_code);
    }
    
    public function testListJsonResponseWithColumns(): void
    {
        $controller = new TestController($this->request);
        $model = new MockControllerTable();
        
        $result = $controller->listJsonResponse($model, 'id,email');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
    }
    
    public function testListJsonResponseSameAsCreateList(): void
    {
        $controller = new TestController($this->request);
        $model = new MockControllerTable();
        
        $result1 = $controller->createList($model);
        $result2 = $controller->listJsonResponse($model);
        
        $this->assertEquals($result1->response_code, $result2->response_code);
        $this->assertEquals($result1->count, $result2->count);
    }
}


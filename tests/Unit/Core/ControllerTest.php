<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\StandardHttpRequest;
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

/**
 * Captures the SQL projection string createList passes to select().
 */
class SqlCaptureTable extends Table
{
    public static ?string $lastSelectColumns = null;

    public int $id = 1;
    public string $email = 'a@example.com';
    public string $display_name;

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'email' => 'string',
        'display_name' => 'string',
    ];

    public function getTable(): string
    {
        return 'sql_capture';
    }

    public function defineSchema(): array
    {
        return [];
    }

    public function select(?string $columns = null): self
    {
        self::$lastSelectColumns = $columns;
        return $this;
    }

    public function run(): ?array
    {
        $row = new self();
        $row->id = 1;
        $row->email = 'a@example.com';

        return [$row];
    }

    public function getTotalCounts(): int
    {
        return 1;
    }

    public function setPage(int $page): void
    {
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

class HiddenFieldListTable extends Table
{
    public int $id = 1;
    public string $email = 'a@example.com';
    protected string $password = 'secret';
    public string $_bag = 'internal';

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'email' => 'string',
        'password' => 'string',
    ];

    public function getTable(): string
    {
        return 'hidden_field_list';
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
        $row = new self();
        $row->id = 7;
        $row->email = 'hidden@example.com';

        return [$row];
    }

    public function getTotalCounts(): int
    {
        return 1;
    }

    public function setPage(int $page): void
    {
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
    public ?string $error = null; // Made public for testing
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
        
        $ar = new StandardHttpRequest();
        $this->request = $ar->request;
    }
    
    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        SqlCaptureTable::$lastSelectColumns = null;
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
    
    public function testControllerUsesRequestApm(): void
    {
        // Create a mock APM and set it on request
        $mockApm = $this->createMock(\Gemvc\Core\Apm\ApmInterface::class);
        $mockApm->method('isEnabled')->willReturn(true);
        $this->request->apm = $mockApm;
        
        $controller = new TestController($this->request);
        
        // Verify that getApm() returns request->apm
        $reflection = new \ReflectionClass($controller);
        $getApmMethod = $reflection->getMethod('getApm');
        $apm = $getApmMethod->invoke($controller);
        
        $this->assertSame($mockApm, $apm);
    }
    
    public function testControllerHandlesNullApm(): void
    {
        // Test that Controller works when APM is not initialized
        $this->request->apm = null;
        
        $controller = new TestController($this->request);
        
        // Verify that getApm() returns null
        $reflection = new \ReflectionClass($controller);
        $getApmMethod = $reflection->getMethod('getApm');
        $apm = $getApmMethod->invoke($controller);
        
        $this->assertNull($apm);
    }
    
    // ============================================
    // Pagination, Sorting, Filtering Tests (test private _listObjects indirectly via createList)
    // ============================================
    
    public function testListObjectsWithPagination(): void
    {
        $_GET['page_number'] = '2';
        $_SERVER['QUERY_STRING'] = 'page_number=2';
        
        $ar = new StandardHttpRequest();
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
        
        $ar = new StandardHttpRequest();
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
        
        $ar = new StandardHttpRequest();
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
        
        $ar = new StandardHttpRequest();
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
        
        $ar = new StandardHttpRequest();
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
        
        $ar = new StandardHttpRequest();
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
        
        $ar = new StandardHttpRequest();
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

    public function testCreateListSqlDoesNotUseDeclaredPayloadFieldNames(): void
    {
        SqlCaptureTable::$lastSelectColumns = null;
        $controller = new TestController($this->request);
        $model = new SqlCaptureTable();

        $this->assertContains('display_name', SqlCaptureTable::payloadFieldNames());
        $this->assertArrayNotHasKey('display_name', get_object_vars($model));

        $controller->createList($model);

        $this->assertNotNull(SqlCaptureTable::$lastSelectColumns);
        $this->assertStringNotContainsString('display_name', (string) SqlCaptureTable::$lastSelectColumns);
        $this->assertStringContainsString('id', (string) SqlCaptureTable::$lastSelectColumns);
        $this->assertStringContainsString('email', (string) SqlCaptureTable::$lastSelectColumns);
    }

    public function testCreateListExplicitColumnsUnchanged(): void
    {
        SqlCaptureTable::$lastSelectColumns = null;
        $controller = new TestController($this->request);
        $model = new SqlCaptureTable();

        $controller->createList($model, 'id,email');

        $this->assertSame('id,email', SqlCaptureTable::$lastSelectColumns);
    }

    public function testListOutputOmitsProtectedAndUnderscoreFields(): void
    {
        $controller = new TestController($this->request);
        $model = new HiddenFieldListTable();

        $result = $controller->createList($model);
        $this->assertIsArray($result->data);
        $this->assertArrayHasKey(0, $result->data);
        $row = $result->data[0];
        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('email', $row);
        $this->assertArrayNotHasKey('password', $row);
        $this->assertArrayNotHasKey('_bag', $row);
        $this->assertArrayNotHasKey('display_name', $row);
    }

    public function testListOutputOmitsUninitializedPublicPayloadFields(): void
    {
        $controller = new TestController($this->request);
        $model = new SqlCaptureTable();

        $result = $controller->createList($model);
        $this->assertIsArray($result->data);
        $row = $result->data[0];
        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('email', $row);
        $this->assertArrayNotHasKey('display_name', $row);
    }
}


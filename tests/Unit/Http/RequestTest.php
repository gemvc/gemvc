<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Request;
use Gemvc\Http\ApacheRequest;

class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Suppress output during tests
        $this->expectOutputString('');
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
    }
    
    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }
    
    public function testIntValueGetReturnsInt(): void
    {
        $_GET['id'] = '123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'id=123';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $id = $request->intValueGet('id');
        $this->assertIsInt($id);
        $this->assertEquals(123, $id);
    }
    
    public function testIntValueGetReturnsFalseForMissingKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $id = $request->intValueGet('id');
        $this->assertFalse($id);
    }
    
    public function testGetArrayAccess(): void
    {
        $_GET['name'] = 'John Doe';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'name=John+Doe';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Request->get can be string|array, so we need to check
        if (is_array($request->get) && isset($request->get['name'])) {
            $name = $request->get['name'];
            $this->assertIsString($name);
            $this->assertEquals('John Doe', $name);
        } else {
            $this->fail('GET array should contain name key');
        }
    }
    
    public function testDefinePostSchemaValidatesRequiredFields(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->definePostSchema([
            'name' => 'string',
            'email' => 'email'
        ]);
        
        $this->assertTrue($result);
    }
    
    public function testDefinePostSchemaRejectsMissingRequiredFields(): void
    {
        $_POST['name'] = 'John';
        // email is missing
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->definePostSchema([
            'name' => 'string',
            'email' => 'email'
        ]);
        
        $this->assertFalse($result);
    }
    
    public function testDefinePostSchemaRejectsUnwantedFields(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        $_POST['is_admin'] = '1'; // Unwanted field
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->definePostSchema([
            'name' => 'string',
            'email' => 'email'
        ]);
        
        // Should reject because is_admin is not in schema
        $this->assertFalse($result);
    }
    
    public function testDefinePostSchemaAcceptsOptionalFields(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        // phone is optional (prefixed with ?)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            '?phone' => 'string'
        ]);
        
        $this->assertTrue($result);
    }
    
    // ============================================
    // Type-Safe Getters Tests
    // ============================================
    
    public function testIntValuePostReturnsInt(): void
    {
        $_POST['id'] = '123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $id = $request->intValuePost('id');
        $this->assertIsInt($id);
        $this->assertEquals(123, $id);
    }
    
    public function testIntValuePostReturnsFalseForMissingKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $id = $request->intValuePost('id');
        $this->assertFalse($id);
    }
    
    public function testIntValuePostReturnsFalseForInvalidValue(): void
    {
        $_POST['id'] = 'not a number';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $id = $request->intValuePost('id');
        $this->assertFalse($id);
    }
    
    public function testFloatValuePostReturnsFloat(): void
    {
        $_POST['price'] = '19.99';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $price = $request->floatValuePost('price');
        $this->assertIsFloat($price);
        $this->assertEquals(19.99, $price);
    }
    
    public function testFloatValuePostReturnsFalseForMissingKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $price = $request->floatValuePost('price');
        $this->assertFalse($price);
    }
    
    public function testFloatValueGetReturnsFloat(): void
    {
        $_GET['price'] = '29.99';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'price=29.99';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $price = $request->floatValueGet('price');
        $this->assertIsFloat($price);
        $this->assertEquals(29.99, $price);
    }
    
    public function testFloatValueGetReturnsFalseForMissingKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $price = $request->floatValueGet('price');
        $this->assertFalse($price);
    }
    
    // ============================================
    // Schema Validation Tests
    // ============================================
    
    public function testDefineGetSchemaValidatesRequiredFields(): void
    {
        $_GET['id'] = '123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'id=123';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->defineGetSchema(['id' => 'int']);
        $this->assertTrue($result);
    }
    
    public function testDefineGetSchemaRejectsMissingRequiredFields(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->defineGetSchema(['id' => 'int']);
        $this->assertFalse($result);
    }
    
    public function testDefinePutSchema(): void
    {
        // PUT data is parsed from php://input
        // For testing, we'll set it manually
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Manually set PUT data for testing
        $request->put = ['name' => 'John', 'email' => 'john@example.com'];
        
        $result = $request->definePutSchema([
            'name' => 'string',
            'email' => 'email'
        ]);
        $this->assertTrue($result);
    }
    
    public function testDefinePatchSchema(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Manually set PATCH data for testing
        $request->patch = ['name' => 'Jane'];
        
        $result = $request->definePatchSchema(['name' => 'string']);
        $this->assertTrue($result);
    }
    
    // ============================================
    // String Validation Tests
    // ============================================
    
    public function testValidateStringPostsWithMinMaxLength(): void
    {
        $_POST['username'] = 'john_doe';
        $_POST['password'] = 'secure123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->validateStringPosts([
            'username' => '3|15',  // Min 3, max 15
            'password' => '8|'     // Min 8, no max
        ]);
        
        $this->assertTrue($result);
    }
    
    public function testValidateStringPostsRejectsTooShort(): void
    {
        $_POST['username'] = 'ab';  // Too short (min 3)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->validateStringPosts([
            'username' => '3|15'
        ]);
        
        $this->assertFalse($result);
        $this->assertNotNull($request->error);
    }
    
    public function testValidateStringPostsRejectsTooLong(): void
    {
        $_POST['username'] = 'this_username_is_too_long_for_validation';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->validateStringPosts([
            'username' => '3|15'
        ]);
        
        $this->assertFalse($result);
    }
    
    public function testValidateStringPostsWithNoConstraints(): void
    {
        $_POST['bio'] = 'Any length is fine';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->validateStringPosts([
            'bio' => ''  // No constraints
        ]);
        
        $this->assertTrue($result);
    }
    
    public function testValidateStringPostsRejectsMissingKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->validateStringPosts([
            'username' => '3|15'
        ]);
        
        $this->assertFalse($result);
    }
    
    // ============================================
    // Filtering and Sorting Tests
    // ============================================
    
    public function testFilterableWithValidFilter(): void
    {
        $_GET['filter_by'] = 'name=John,email=john@example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'filter_by=name=John,email=john@example.com';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->filterable([
            'name' => 'string',
            'email' => 'email'
        ]);
        
        $this->assertTrue($result);
    }
    
    public function testFilterableWithInvalidType(): void
    {
        $_GET['filter_by'] = 'email=notanemail';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'filter_by=email=notanemail';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->filterable([
            'email' => 'email'
        ]);
        
        $this->assertFalse($result);
    }
    
    public function testFindableWithValidSearch(): void
    {
        $_GET['find_like'] = 'name=John';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'find_like=name=John';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->findable([
            'name' => 'string'
        ]);
        
        $this->assertTrue($result);
    }
    
    public function testSortableWithValidSortBy(): void
    {
        $_GET['sort_by'] = 'name';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'sort_by=name';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->sortable(['name', 'email', 'id']);
        
        $this->assertTrue($result);
    }
    
    public function testSortableWithValidSortByAsc(): void
    {
        $_GET['sort_by_asc'] = 'email';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'sort_by_asc=email';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->sortable(['name', 'email', 'id']);
        
        $this->assertTrue($result);
    }
    
    public function testSortableRejectsInvalidField(): void
    {
        $_GET['sort_by'] = 'invalid_field';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'sort_by=invalid_field';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->sortable(['name', 'email']);
        
        $this->assertFalse($result);
    }
    
    // ============================================
    // Pagination Tests
    // ============================================
    
    public function testSetPageNumberWithValidValue(): void
    {
        $_GET['page_number'] = '5';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'page_number=5';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->setPageNumber();
        $this->assertTrue($result);
        $this->assertEquals(5, $request->getPageNumber());
    }
    
    public function testSetPageNumberRejectsInvalidValue(): void
    {
        $_GET['page_number'] = 'not_a_number';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'page_number=not_a_number';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->setPageNumber();
        $this->assertFalse($result);
    }
    
    public function testSetPageNumberRejectsZero(): void
    {
        $_GET['page_number'] = '0';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'page_number=0';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->setPageNumber();
        $this->assertFalse($result);
    }
    
    public function testSetPerPageWithValidValue(): void
    {
        $_GET['per_page'] = '25';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'per_page=25';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->setPerPage();
        $this->assertTrue($result);
        $this->assertEquals(25, $request->getPerPage());
    }
    
    public function testSetPerPageRejectsInvalidValue(): void
    {
        $_GET['per_page'] = 'not_a_number';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = 'per_page=not_a_number';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $result = $request->setPerPage();
        $this->assertFalse($result);
    }
    
    public function testGetPageNumberReturnsDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertEquals(1, $request->getPageNumber());
    }
    
    public function testGetPerPageReturnsDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['QUERY_STRING'] = '';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Default should be from QUERY_LIMIT env or 10
        $perPage = $request->getPerPage();
        $this->assertIsInt($perPage);
        $this->assertGreaterThan(0, $perPage);
    }
    
    // ============================================
    // Getter Tests
    // ============================================
    
    public function testGetId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $id = $request->getId();
        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }
    
    public function testGetTime(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $time = $request->getTime();
        $this->assertIsString($time);
        $this->assertNotEmpty($time);
    }
    
    public function testGetStartExecutionTime(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $startTime = $request->getStartExecutionTime();
        $this->assertIsFloat($startTime);
        $this->assertGreaterThan(0, $startTime);
    }
    
    public function testGetErrorReturnsNullWhenNoError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertNull($request->getError());
    }
    
    public function testGetErrorReturnsErrorWhenSet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Trigger an error
        $request->intValueGet('nonexistent');
        
        $error = $request->getError();
        $this->assertNotNull($error);
        $this->assertIsString($error);
    }
    
    // ============================================
    // Response Tests
    // ============================================
    
    public function testReturnResponseReturnsSetResponse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $expectedResponse = \Gemvc\Http\Response::success(['test' => 'data']);
        $request->response = $expectedResponse;
        
        $response = $request->returnResponse();
        $this->assertSame($expectedResponse, $response);
    }
    
    public function testReturnResponseReturnsUnknownErrorWhenNotSet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $response = $request->returnResponse();
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $response);
        // Response should have an error message or service_message
        $this->assertTrue(
            isset($response->error) || isset($response->service_message),
            'Response should have error or service_message when no response is set'
        );
    }
    
    // ============================================
    // Object Mapping Tests
    // ============================================
    
    public function testMapPostToObject(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $object = new class {
            public ?string $name = null;
            public ?string $email = null;
        };
        
        $result = $request->mapPostToObject($object, [
            'name' => 'name',
            'email' => 'email'
        ]);
        
        $this->assertNotNull($result);
        $this->assertEquals('John', $object->name);
        $this->assertEquals('john@example.com', $object->email);
    }
    
    public function testMapPostToObjectWithMethodCall(): void
    {
        $_POST['password'] = 'plaintext';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $object = new class {
            public ?string $password = null;
            
            public function setPassword(string $value): void
            {
                $this->password = 'hashed_' . $value;
            }
        };
        
        $result = $request->mapPostToObject($object, [
            'password' => 'setPassword()'
        ]);
        
        $this->assertNotNull($result);
        $this->assertEquals('hashed_plaintext', $object->password);
    }
}


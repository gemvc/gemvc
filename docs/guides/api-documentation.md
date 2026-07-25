# GEMVC Auto-Documentation Directives

GEMVC has a built-in **auto-documentation generator** that reads PHPDoc directives to create beautiful HTML API documentation!

## 🎯 How It Works

The `ApiDocGenerator.php` uses reflection to scan API service classes and extract documentation from PHPDoc comments.

### Access Documentation
Visit: `http://localhost/api/index/document`

---

## 📝 Available Directives

### 1. HTTP Method Directive
```php
/**
 * @http GET
 */
public function read(): JsonResponse { ... }
```
**Purpose**: Specifies HTTP method (GET, POST, PUT, DELETE, PATCH)

---

### 2. Description Directive
```php
/**
 * @description Get User by id from database
 */
public function read(): JsonResponse { ... }
```
**Purpose**: Human-readable description shown in documentation

---

### 3. Example Directive
```php
/**
 * @example /api/User/read/?id=1
 */
public function read(): JsonResponse { ... }
```
**Purpose**: Example URL showing how to call the endpoint

---

### 4. Hidden Directive
```php
/**
 * @hidden
 */
public static function mockResponse(string $method): array { ... }
```
**Purpose**: Hides method from API documentation

---

### 5. Parameter Directive (Advanced)
```php
/**
 * @parameter id int required
 * @parameter name string optional
 */
public function update(): JsonResponse { ... }
```
**Purpose**: Explicit parameter documentation

---

## 🔍 Automatic Parameter Extraction

The generator automatically extracts parameters from your validation schemas!

### POST Parameters
```php
public function create(): JsonResponse
{
    if(!$this->request->definePostSchema([
        'name' => 'string',
        'email' => 'email',
        '?phone' => 'string'  // ? = optional
    ])) {
        return $this->request->returnResponse();
    }
    // ...
}
```
**Result**: Documentation shows Body Parameters table with `name`, `email`, `phone`

### GET Parameters
```php
public function read(): JsonResponse
{
    if(!$this->request->defineGetSchema([
        "id" => "int"
    ])) {
        return $this->request->returnResponse();
    }
    // ...
}
```
**Result**: Documentation shows GET Parameters table with `id`

### Query Parameters (for list operations)
```php
public function list(): JsonResponse
{
    $this->request->findable([
        'name' => 'string',
        'email' => 'email'
    ]);
    
    $this->request->sortable([
        'id',
        'name'
    ]);
    
    // ...
}
```
**Result**: Documentation shows Query Parameters with Filters and Sort sections

---

## 📊 Mock Responses

Provide example responses using the `mockResponse()` static method:

```php
/**
 * @hidden
 */
public static function mockResponse(string $method): array
{
    return match($method) {
        'read' => [
            'response_code' => 200,
            'message' => 'OK',
            'count' => 1,
            'service_message' => 'User retrieved successfully',
            'data' => [
                'id' => 1,
                'name' => 'Sample User',
                'description' => 'User description'
            ]
        ],
        // ... other methods
    };
}
```

**Result**: Beautiful JSON response displayed in documentation

---

## 🎨 Complete Example

```php
<?php
namespace App\Api;

use App\Controller\UserController;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;

class User extends ApiService
{
    /**
     * Read User by ID
     * 
     * @return JsonResponse
     * @http GET                          ← HTTP Method
     * @description Get User by id from database  ← Description
     * @example /api/User/read/?id=1      ← Example URL
     */
    public function read(): JsonResponse
    {
        // Generator extracts parameters from defineGetSchema()
        if(!$this->request->defineGetSchema(["id" => "int"])) {
            return $this->request->returnResponse();
        }
        
        $id = $this->request->intValueGet("id");
        if(!$id) {
            return $this->request->returnResponse();
        }
        
        $this->request->post['id'] = $id;
        return (new UserController($this->request))->read();
    }

    /**
     * @hidden  ← Hide from documentation
     */
    public static function mockResponse(string $method): array
    {
        return match($method) {
            'read' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'User retrieved successfully',
                'data' => [
                    'id' => 1,
                    'name' => 'Sample User',
                    'description' => 'User description'
                ]
            ],
            // ...
        };
    }
}
```

---

## 📋 Generated Documentation Includes

1. **HTTP Method Badge** (GET/POST/PUT/DELETE)
2. **Endpoint Path**
3. **Description**
4. **Example URL**
5. **Parameter Tables**:
   - URL Parameters
   - GET Parameters
   - Body Parameters
   - Query Parameters (Filters, Sort, Search)
6. **JSON Response Example**
7. **Postman Export** (one-click button)

---

## 🎯 Best Practices

### 1. Always Add Directives
```php
/**
 * @http GET
 * @description What this endpoint does
 * @example /api/Entity/action/?param=value
 */
public function myMethod(): JsonResponse { ... }
```

### 2. Use Mock Responses
```php
/**
 * @hidden
 */
public static function mockResponse(string $method): array
{
    return match($method) {
        'myMethod' => [/* response data */],
        default => [/* default response */]
    };
}
```

### 3. Document Optional Parameters
```php
// Use ? prefix to mark optional
$this->request->definePostSchema([
    'name' => 'string',      // Required
    '?phone' => 'string'    // Optional
])
```

### 4. Hide Helper Methods
```php
/**
 * @hidden
 */
private function helperMethod(): void { ... }
```

---

## 🚀 Usage in AI Assistants

### When Generating Code

**DO**:
```php
/**
 * Create new User
 * 
 * @return JsonResponse
 * @http POST
 * @description Create new User in database
 * @example /api/User/create
 */
public function create(): JsonResponse { ... }
```

**DON'T**:
```php
// Missing directives
public function create(): JsonResponse { ... }
```

### Always Include
1. `@http` directive for HTTP method
2. `@description` for human-readable text
3. `@example` showing full URL
4. `mockResponse()` method for example responses

---

## 📸 Documentation Features

- **Auto-generated** from your PHPDoc
- **Always up-to-date** (scans code)
- **Postman export** (one-click)
- **Beautiful UI** (clean, modern design)
- **Interactive** (click to copy URLs)
- **Parameter tables** (automatic from schemas)

---

## 🎓 Summary

**Directive System** = PHPDoc annotations that document your API
**Generator** = Reads code + directives → Beautiful HTML documentation
**Result** = Always up-to-date, professional API documentation

**Remember**: 
- Directives tell the generator what to show
- Schemas tell the generator what parameters exist
- `mockResponse()` provides example responses


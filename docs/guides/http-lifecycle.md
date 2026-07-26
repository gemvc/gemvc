# GEMVC HTTP Request Life Cycle

**Audience:** adapters, unified `Request` / `JsonResponse`, Apache vs OpenSwoole paths.

**Related:** [api.md](api.md) · [architecture.md](architecture.md) · [security.md](security.md)

Complete guide to GEMVC's server-agnostic HTTP request handling.

## Reading map (AI)

| Need | Jump to |
|------|---------|
| Apache vs Swoole path | [Request Life Cycle](#request-life-cycle) |
| Adapters | [Server Adapters](#server-adapters) |
| Unified Request fields | [Unified Request Object](#unified-request-object) |
| Writing `app/api` | [api.md](api.md) |

| URL sections / Swoole vs Apache | [architecture.md](architecture.md#url-to-code-mapping) · [api.md](api.md) |

## Table of Contents

- [Overview](#overview)
- [Server-Agnostic Architecture](#server-agnostic-architecture)
- [Request Life Cycle](#request-life-cycle)
- [Server Adapters](#server-adapters)
- [Unified Request Object](#unified-request-object)
- [Response Handling](#response-handling)
- [Flow Diagrams](#flow-diagrams)
- [Examples](#examples)

---

## Overview

GEMVC's HTTP layer is designed to be **completely server-agnostic**. The same application code works identically on Apache, OpenSwoole, and Nginx. This is achieved through:

1. **Server Adapters** - Convert webserver-specific requests to unified format
2. **Unified Request Object** - Single interface for all webservers
3. **Response Abstraction** - Consistent response handling across platforms

**Key Principle**: Your `app/` code never changes when switching webservers!

---

## Server-Agnostic Architecture

### Architecture Pattern

```
Webserver-Specific Request
    ↓
Server Adapter (ApacheRequest | SwooleRequest)
    ↓
Unified Request Object
    ↓
Application Code (app/api/, app/controller/)
    ↓
Unified Response
    ↓
Webserver-Specific Output
```

### Components

1. **Server Adapters** - Convert webserver requests to unified format
   - `ApacheRequest` - Apache/PHP-FPM adapter
   - `SwooleRequest` - OpenSwoole adapter
   - `NginxRequest` - Nginx adapter (coming soon)

2. **Unified Request** - Single interface for all requests
   - `Request` - Core request object with sanitization

3. **Response Abstraction** - Consistent responses
   - `JsonResponse` - JSON responses with `show()` and `showSwoole()`
   - `Response` - Response factory

---

## Request Life Cycle

### Apache/Nginx Life Cycle

```
1. HTTP Request arrives at Apache/Nginx
    ↓
2. PHP-FPM processes request
    ↓
3. index.php loads Bootstrap
    ↓
4. ApacheRequest adapter created
    ├─ Sanitizes all headers
    ├─ Sanitizes GET, POST, PUT, PATCH
    ├─ Extracts files, cookies, auth headers
    └─ Creates unified Request object
    ↓
5. Bootstrap routes to API service
    ↓
6. API service validates schema
    ↓
7. Controller orchestrates (map request → Model)
    ↓
8. Model applies business rules / transforms
    ↓
9. Table performs database operations
    ↓
10. JsonResponse returned
    ↓
11. JsonResponse->show() outputs to Apache
```

### OpenSwoole Life Cycle

```
1. HTTP Request arrives at OpenSwoole server
    ↓
2. OpenSwooleServer receives request
    ↓
3. SecurityManager checks path access
    ↓
4. SwooleRequest adapter created
    ├─ Sanitizes headers
    ├─ Sanitizes request data
    ├─ Extracts files, cookies, auth headers
    └─ Creates unified Request object
    ↓
5. SwooleBootstrap routes to API service
    ↓
6. API service validates schema
    ↓
7. Controller orchestrates (map request → Model)
    ↓
8. Model applies business rules / transforms
    ↓
9. Table performs database operations
    ↓
10. JsonResponse returned
    ↓
11. JsonResponse->showSwoole() outputs to OpenSwoole
```

---

## Server Adapters

### ApacheRequest Adapter

**Purpose**: Converts Apache/PHP-FPM requests to unified `Request` object.

**Key Features**:
- Sanitizes all HTTP headers (`$_SERVER['HTTP_*']`)
- Sanitizes GET, POST, PUT, PATCH data
- Handles file uploads (`$_FILES`)
- Extracts cookies and auth headers
- Creates unified `Request` object

**Implementation**:
```php
<?php
namespace Gemvc\Http;

class ApacheRequest
{
    public Request $request;
    
    public function __construct()
    {
        // Sanitize all inputs BEFORE creating Request
        $this->sanitizeAllServerHttpRequestHeaders();
        $this->sanitizeAllHTTPGetRequest();
        $this->sanitizeAllHTTPPostRequest();
        $put = $this->sanitizeAllHTTPPutRequest();
        $patch = $this->sanitizeAllHTTPPatchRequest();
        
        // Create unified Request object
        $this->request = new Request();
        
        // Populate with sanitized data
        $this->request->post = $_POST;
        $this->request->get = $_GET;
        $this->request->put = $put;
        $this->request->patch = $patch;
        $this->request->files = $_FILES;
        
        // Extract headers
        $this->getAuthHeader();
    }
}
```

**Usage** (in `Bootstrap.php`):
```php
$ar = new ApacheRequest();
$request = $ar->request;  // Unified Request object
```

---

### SwooleRequest Adapter

**Purpose**: Converts OpenSwoole requests to unified `Request` object.

**Key Features**:
- Sanitizes OpenSwoole request headers
- Handles raw request body parsing
- Normalizes file uploads
- Filters dangerous cookies
- Creates unified `Request` object

**Implementation**:
```php
<?php
namespace Gemvc\Http;

class SwooleRequest
{
    public Request $request;
    private object $incomingRequestObject;
    
    public function __construct(object $swooleRequest)
    {
        $this->request = new Request();
        $this->incomingRequestObject = $swooleRequest;
        
        // Sanitize and extract data
        $this->request->requestMethod = $swooleRequest->server['request_method'] ?? 'GET';
        $this->request->requestedUrl = $this->sanitizeRequestURI($swooleRequest->server['request_uri']);
        
        // Handle request body based on method
        $this->setData();  // Sets POST, PUT, PATCH
        $this->setCookies();  // Filters dangerous cookies
        $this->setAuthorizationToken();
    }
}
```

**Usage** (in `OpenSwooleServer.php`):
```php
$sr = new SwooleRequest($request);
$unifiedRequest = $sr->request;  // Unified Request object
```

---

## Unified Request Object

### Request Class Structure

The `Request` class provides a **unified interface** for all webservers:

```php
class Request
{
    // Request Information
    public string $requestedUrl;
    public ?string $queryString;
    public ?string $requestMethod;
    public string $userMachine;
    public ?string $remoteAddress;
    
    // Request Data (already sanitized by adapter)
    public array $post;
    public array $get;
    public null|array $put;
    public null|array $patch;
    public null|array $files;
    public mixed $cookies;
    
    // Authentication
    public null|string|array $authorizationHeader;
    public ?string $jwtTokenStringInHeader;
    public bool $isAuthenticated;
    public bool $isAuthorized;
    
    // Schema Validation
    public function definePostSchema(array $schema): bool { }
    public function defineGetSchema(array $schema): bool { }
    
    // Query Helpers
    public function intValueGet(string $key): int|false { }
    public function stringValueGet(string $key): string|false { }
    
    // Filtering & Sorting
    public function findable(array $fields): bool { }
    public function sortable(array $fields): bool { }
    public function filterable(array $fields): bool { }
    
    // Authentication
    public function auth(array $roles = null): bool { }
    
    // Response
    public function returnResponse(): JsonResponse { }
}
```

### Key Features

1. **Already Sanitized** - All inputs sanitized by adapter
2. **Type-Safe Access** - Methods like `intValueGet()`, `stringValueGet()`
3. **Schema Validation** - `definePostSchema()`, `defineGetSchema()`
4. **JWT Authentication** - Built-in JWT token handling
5. **Query Helpers** - Filtering, sorting, pagination

---

## Response Handling

### Response Abstraction

GEMVC abstracts responses to work across all webservers:

```php
class JsonResponse implements ResponseInterface
{
    // ... response data ...
    
    /**
     * Show response for Apache/Nginx
     */
    public function show(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->data);
        die();  // Terminate for Apache
    }
    
    /**
     * Show response for OpenSwoole
     */
    public function showSwoole(object $response): void
    {
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode($this->data));
        // No die() - persistent process
    }
}
```

### Usage in Application Code

**Your application code is identical**:

```php
// app/api/User.php - Works on ALL webservers!
class User extends ApiService
{
    public function create(): JsonResponse
    {
        // Validation logic (same for all servers)
        if(!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email'
        ])) {
            return $this->request->returnResponse();
        }
        
        // Same app code on every server — Controller orchestrates; Model holds rules
        return (new UserController($this->request))->create();
    }
}
```

**Framework handles output**:
- Apache: `JsonResponse->show()` called automatically
- OpenSwoole: `JsonResponse->showSwoole()` called automatically

---

## Flow Diagrams

### Complete Request Flow (Apache)

```
HTTP Request
    ↓
Apache Server
    ↓
PHP-FPM Processes Request
    ↓
index.php
    ↓
new ApacheRequest()
    ├─ Sanitize $_SERVER headers
    ├─ Sanitize $_POST, $_GET
    ├─ Sanitize PUT/PATCH (from php://input)
    ├─ Extract $_FILES
    └─ Create Request object
    ↓
new Bootstrap($request)
    ↓
Extract URL segments → Service/Method
    ↓
Load app/api/User.php
    ↓
Call User::create()
    ├─ Validate schema
    └─ Delegate to Controller
    ↓
UserController::create()
    ├─ Map POST to Model
    └─ Call Model
    ↓
UserModel::createModel()
    ├─ Business validation
    └─ Database operation
    ↓
JsonResponse returned
    ↓
JsonResponse->show()
    ├─ Set headers
    ├─ Output JSON
    └─ die()
```

### Complete Request Flow (OpenSwoole)

```
HTTP Request
    ↓
OpenSwoole Server
    ↓
OpenSwooleServer->on('request')
    ↓
SecurityManager::isRequestAllowed()
    ↓
new SwooleRequest($swooleRequest)
    ├─ Sanitize headers
    ├─ Parse request body
    ├─ Normalize files
    ├─ Filter cookies
    └─ Create Request object
    ↓
new SwooleBootstrap($request)
    ↓
Extract URL segments → Service/Method
    ↓
Load app/api/User.php
    ↓
Call User::create()
    ├─ Validate schema
    └─ Delegate to Controller
    ↓
UserController::create()
    ├─ Map POST to Model
    └─ Call Model
    ↓
UserModel::createModel()
    ├─ Business validation
    └─ Database operation
    ↓
JsonResponse returned
    ↓
JsonResponse->showSwoole($response)
    ├─ Set headers
    ├─ Output JSON
    └─ Return (no die())
```

---

## Security Features

### Automatic Sanitization

**All server adapters automatically sanitize**:

1. **HTTP Headers**
   ```php
   // ApacheRequest
   $this->sanitizeAllServerHttpRequestHeaders();
   
   // SwooleRequest
   $this->sanitizeInput($swooleRequest->header['user-agent']);
   ```

2. **Request Data**
   ```php
   // GET, POST, PUT, PATCH all sanitized
   $_POST[$key] = $this->sanitizeInput($value);
   ```

3. **Cookies**
   ```php
   // SwooleRequest filters dangerous cookies
   $this->filterDangerousCookies($cookies);
   ```

4. **File Names**
   ```php
   // File names sanitized
   $file['name'] = $this->sanitizeInput($file['name']);
   ```

**Result**: Your application code receives **already-sanitized** data!

---

## Examples

### Example 1: Apache Request Handling

**index.php** (Apache):
```php
<?php
require_once 'vendor/autoload.php';

use Gemvc\Http\ApacheRequest;
use Gemvc\Core\Bootstrap;

// Create adapter
$ar = new ApacheRequest();
$request = $ar->request;  // Unified Request

// Route to API service
$bootstrap = new Bootstrap($request);
```

**What Happens**:
1. `ApacheRequest` sanitizes all `$_SERVER`, `$_POST`, `$_GET`
2. Creates unified `Request` object
3. `Bootstrap` routes to API service
4. Application code receives clean, sanitized data

---

### Example 2: OpenSwoole Request Handling

**OpenSwooleServer.php**:
```php
$this->server->on("request", function ($request, $response) {
    // Security check
    if (!$this->security->isRequestAllowed($request->server['request_uri'])) {
        $this->security->sendSecurityResponse($response);
        return;
    }
    
    // Create adapter
    $sr = new SwooleRequest($request);
    $unifiedRequest = $sr->request;  // Unified Request
    
    // Route to API service
    $bs = new SwooleBootstrap($unifiedRequest);
    $result = $bs->processRequest();
    
    // Send response
    if ($result instanceof JsonResponse) {
        $result->showSwoole($response);
    }
});
```

**What Happens**:
1. Security check (OpenSwoole-specific)
2. `SwooleRequest` sanitizes OpenSwoole request object
3. Creates unified `Request` object
4. `SwooleBootstrap` routes to API service
5. `JsonResponse->showSwoole()` outputs to OpenSwoole

---

### Example 3: Application Code (Server-Agnostic)

**app/api/User.php** — same layering on every server; **base class differs**:

```php
// Apache/Nginx: extend ApiService and prefer callController(...) for APM
// OpenSwoole: extend SwooleApiService and use bare (new UserController(...))->create()
```

```php
<?php
namespace App\Api;

use App\Controller\UserController;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;

class User extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }
    
    public function create(): JsonResponse
    {
        // Schema validation (same for all servers)
        if(!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        // Apache ApiService: prefer callController for APM controller spans
        return $this->callController(new UserController($this->request))->create();
        // OpenSwoole SwooleApiService: return (new UserController($this->request))->create();
    }
}
```

**Same 4-layer app code**; only API base class + Controller invoke style differ (see [api.md](api.md)).

---

## Key Benefits

### 1. **Server-Agnostic Application Code**

```php
//  Works on Apache, OpenSwoole, Nginx
class User extends ApiService
{
    public function create(): JsonResponse
    {
        // Your code here - never changes!
    }
}
```

### 2. **Automatic Input Sanitization**

```php
//  Already sanitized by adapter
$email = $this->request->post['email'];  // Safe!
```

### 3. **Unified Request Interface**

```php
//  Same interface for all servers
$this->request->post           // POST data
$this->request->get            // GET data
$this->request->files          // File uploads
$this->request->auth()         // JWT authentication
```

### 4. **Consistent Response Handling**

```php
//  Framework handles output automatically
return Response::success($data);  // Works everywhere!
```

---

## Summary

**GEMVC's HTTP Request Life Cycle**:

1. **Server Adapters** convert webserver requests to unified format
2. **Unified Request Object** provides single interface
3. **Automatic Sanitization** happens in adapters
4. **Application Code** remains unchanged across servers
5. **Response Abstraction** handles webserver differences

**Result**: Write code once, run on any webserver!
---

## Key Takeaways

1. **Server adapters** (`ApacheRequest`, `SwooleRequest`) convert requests
2. **Unified `Request` object** - same interface for all servers
3. **Automatic sanitization** - adapters handle security
4. **Application code is server-agnostic** - never changes
5. **Response abstraction** - framework handles output differences

**The Magic**: Your `app/` folder code works identically on Apache, OpenSwoole, and Nginx without a single line change! ✨


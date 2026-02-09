# GEMVC API Reference for AI Assistants

This document provides a complete API reference for GEMVC framework to help AI assistants understand available classes and methods.

## Core Classes

### Request Object
**Namespace**: `Gemvc\Http\Request`

**Properties**:
```php
public string $requestedUrl;           // Sanitized URL
public ?string $queryString;         // Sanitized query string
public ?string $requestMethod;       // Request method
public string $userMachine;          // Client info
public ?string $remoteAddress;        // Client IP
public array $post;                  // Sanitized POST data ✅
public array $get;                   // Sanitized GET data ✅
public null|array $put;              // Sanitized PUT data ✅
public null|array $patch;            // Sanitized PATCH data ✅
public null|array $files;            // Sanitized file uploads ✅
public mixed $cookies;               // Filtered cookies
public null|string|array $authorizationHeader;  // Auth header
public ?string $jwtTokenStringInHeader;
public bool $isAuthenticated;
public bool $isAuthorized;
// Note: Pagination is handled via setPageNumber() and setPerPage() methods
```

**Methods**:
```php
// Schema Validation
definePostSchema(array $schema): bool
defineGetSchema(array $schema): bool
definePutSchema(array $schema): bool      // PUT validation
definePatchSchema(array $schema): bool    // PATCH validation
validateStringPosts(array $validations): bool

// Type-Safe Getters
intValueGet(string $key): int|false
intValuePost(string $key): int|false
floatValueGet(string $key): float|false
floatValuePost(string $key): float|false
// String values: use $this->request->get[$key] or $this->request->post[$key] (already sanitized)

// Filtering & Sorting
findable(array $fields): bool      // Enable LIKE search
sortable(array $fields): bool      // Enable sorting
filterable(array $fields): bool    // Enable exact match filtering
setPageNumber(): bool
setPerPage(): bool
getPageNumber(): int
getPerPage(): int

// Authentication
auth(?array $roles = null): bool

// Mapping
mapPostToObject(object $object, ?array $mapping = null): object|null
mapPutToObject(object $object, ?array $mapping = null): object|false

// Response
returnResponse(): JsonResponse
```

### Response Factory
**Namespace**: `Gemvc\Http\Response`

**Methods**:
```php
Response::success($data, $count, $message): JsonResponse
Response::created($data, $count, $message): JsonResponse  // 201
Response::updated($result, $count, $message): JsonResponse  // 209
Response::deleted($result, $count, $message): JsonResponse  // 210
Response::notFound(string $message): JsonResponse  // 404
Response::badRequest(string $message): JsonResponse  // 400
Response::unprocessableEntity(string $message): JsonResponse  // 422
Response::internalError(string $message): JsonResponse  // 500
Response::unauthorized(string $message): JsonResponse  // 401
Response::forbidden(string $message): JsonResponse  // 403
```

### Table ORM
**Namespace**: `Gemvc\Database\Table`

**Properties**:
```php
protected array $_type_map = [];  // Type mapping
protected int $_limit;            // Pagination limit
protected int $_offset = 0;       // Pagination offset
protected string $_orderBy = '';  // ORDER BY clause
protected array $_arr_where = []; // WHERE clauses
protected array $_joins = [];     // JOIN clauses
```

**Methods**:
```php
// Database Connection
__construct(): void

// Query Builder
select(?string $columns = null): self   // e.g. "id,name,email" or null for *
where(string $column, mixed $value): self
whereEqual(string $column, mixed $value): self  // Preferred for equality
whereLike(string $column, string $pattern): self
whereOr(string $column, mixed $value): self
whereNull(string $column): self
whereNotNull(string $column): self
join(string $table, string $condition, string $type = 'INNER'): self
orderBy(string|null $column = null, bool|null $ascending = null): self  // Default: primary key DESC
limit(int $limit): self
run(): ?array  // Returns array of objects, null on error

// CRUD Operations
insertSingleQuery(): ?static     // Returns self with ID on success
updateSingleQuery(): ?static    // Returns self on success
deleteByIdQuery(int|string $id): int|string|null  // Returns deleted ID on success (supports UUID PKs)
selectById(int|string $id): null|static  // Custom method pattern

// Helper Methods
getError(): ?string
setError(?string $error): void
validateId(int $id, string $operation = 'operation'): bool
isConnected(): bool

// Required Methods (MUST implement)
getTable(): string  // Return table name
defineSchema(): array  // Return schema constraints
```

### Controller Base
**Namespace**: `Gemvc\Core\Controller`

**Methods**:
```php
__construct(Request $request): void
createList(object $model, ?string $columns = null): JsonResponse  // Auto pagination/filtering
protected function createModel(object $model): object  // Sets Request on model for APM tracing
protected function getApm(): ?ApmInterface  // Returns $this->request->apm
```

### API Service Base
**Namespace**: `Gemvc\Core\ApiService`

**Properties**:
```php
protected Request $request;
```

**Methods**:
```php
__construct(Request $request): void
protected function callController(Controller $controller): ControllerTracingProxy  // For APM tracing
```

### Schema Constraints For Tables layer Classes
**Namespace**: `Gemvc\Database\Schema`

**Methods**:
```php
Schema::primary(string|array $columns)
Schema::autoIncrement(string $column)
Schema::unique(string|array $columns): self
Schema::foreignKey(string $column, string $reference): ForeignKeyConstraint
Schema::index(string|array $columns): IndexConstraint
Schema::check(string $condition): CheckConstraint
Schema::fullText(string|array $columns): FulltextConstraint
```

**ForeignKeyConstraint methods** (e.g. onDeleteCascade):
```php
->name(string $name): self
->onDeleteCascade(): self
->onDeleteRestrict(): self
->onDeleteSetNull(): self
```

### CryptHelper
**Namespace**: `Gemvc\Helper\CryptHelper`

**Methods**:
```php
CryptHelper::hashPassword(string $plainPassword): string  // Argon2i
CryptHelper::passwordVerify(string $plain, string $hashed): bool
CryptHelper::encrypt(string $data, string $secret): string  // AES-256-CBC
CryptHelper::decrypt(string $encrypted, string $secret): string
CryptHelper::generateRandomString(int $length): string
```

### FileHelper
**Namespace**: `Gemvc\Helper\FileHelper`

**Methods**:
```php
__construct(string $sourceFile, string $destinationFile): void
encrypt(): ?string  // Returns encrypted file path
decrypt(): ?string  // Returns decrypted file path
public ?string $error
public ?string $secret  // Encryption key
```

### ImageHelper
**Namespace**: `Gemvc\Helper\ImageHelper`

**Methods**:
```php
__construct(string $sourceFile): void
convertToWebP(int $quality = 80): bool  // Validates signature ✅
resize(int $width, int $height): bool
getError(): ?string
```

### TypeChecker
**Namespace**: `Gemvc\Helper\TypeChecker`

**Methods**:
```php
TypeChecker::check(mixed $value, string $type, array $options = []): bool
TypeChecker::validateEmail(string $email): bool
TypeChecker::validateUrl(string $url): bool
TypeChecker::validateDate(string $date): bool
TypeChecker::validateIp(string $ip): bool
```

### ServerMonitorHelper
**Namespace**: `Gemvc\Helper\ServerMonitorHelper`

**Methods**:
```php
ServerMonitorHelper::getMemoryUsage(): array<string, mixed>  // RAM metrics
ServerMonitorHelper::getCpuLoad(): array<string, float>      // CPU load average
ServerMonitorHelper::getCpuCores(): int                      // CPU core count
ServerMonitorHelper::getCpuUsage(): ?float                   // CPU usage percentage
```

### NetworkHelper
**Namespace**: `Gemvc\Helper\NetworkHelper`

**Methods**:
```php
NetworkHelper::getNetworkStats(): array<string, mixed>       // All network interfaces
NetworkHelper::getNetworkInterfaces(): array<string>          // List of interfaces
NetworkHelper::getInterfaceStats(string $interface): array    // Stats for specific interface
```

### ApmFactory (from gemvc/apm-contracts)
**Namespace**: `Gemvc\Core\Apm\ApmFactory`

**Methods**:
```php
ApmFactory::create(?Request $request): ?ApmInterface  // Create APM instance
ApmFactory::isEnabled(): ?string                      // Returns APM name or null
```

### ApmInterface (from gemvc/apm-contracts)
**Namespace**: `Gemvc\Core\Apm\ApmInterface`

**Methods**:
```php
$apm->startSpan(string $operationName, array $attributes = [], int $kind = SPAN_KIND_INTERNAL): array
$apm->endSpan(array $spanData, array $finalAttributes = [], ?string $status = STATUS_OK): void
$apm->recordException(array $spanData, \Throwable $exception): array
$apm->isEnabled(): bool
$apm->shouldTraceResponse(): bool
$apm->shouldTraceDbQuery(): bool
$apm->getTraceId(): ?string
$apm->flush(): void
```

**Constants**:
```php
ApmInterface::SPAN_KIND_UNSPECIFIED = 0
ApmInterface::SPAN_KIND_INTERNAL = 1
ApmInterface::SPAN_KIND_SERVER = 2
ApmInterface::SPAN_KIND_CLIENT = 3
ApmInterface::SPAN_KIND_PRODUCER = 4
ApmInterface::SPAN_KIND_CONSUMER = 5
ApmInterface::STATUS_OK = 'OK'
ApmInterface::STATUS_ERROR = 'ERROR'
```

**Static Methods**:
```php
ApmInterface::determineStatusFromHttpCode(int $statusCode): string  // Use AbstractApm:: instead
ApmInterface::limitStringForTracing(string $value): string           // Use AbstractApm:: instead
```

### ApmTracingTrait
**Namespace**: `Gemvc\Core\Apm\ApmTracingTrait`

**Methods** (available when trait is used):
```php
protected function getApm(): ?ApmInterface
protected function startApmSpan(string $operationName, array $attributes = [], int $kind = SPAN_KIND_INTERNAL): array
protected function endApmSpan(array $spanData, array $attributes = [], string $status = 'OK'): void
protected function recordApmException(array $spanData, \Throwable $exception): void
protected function traceApm(string $operationName, callable $callback, array $attributes = []): mixed
```

### ApiService - APM Methods
**Namespace**: `Gemvc\Core\ApiService`

**Methods**:
```php
protected function callController(Controller $controller): ControllerTracingProxy
// Returns proxy that automatically traces controller operations (if APM_TRACE_CONTROLLER=1)
```

### Controller - APM Methods
**Namespace**: `Gemvc\Core\Controller`

**Methods**:
```php
protected function getApm(): ?ApmInterface  // Returns $this->request->apm
protected function createModel(object $model): object  // Sets Request on model for APM tracing
protected function startTraceSpan(string $operationName, array $attributes = [], int $kind = SPAN_KIND_INTERNAL): array
protected function endTraceSpan(array $spanData, array $finalAttributes = [], ?string $status = 'OK'): void
public function recordApmException(\Throwable $exception): void
```

### Table - APM Methods
**Namespace**: `Gemvc\Database\Table`

**Methods**:
```php
public function setRequest(?Request $request): void  // Set Request for APM trace context propagation
```

### Request Object - APM Property
**Namespace**: `Gemvc\Http\Request`

**Properties**:
```php
public ?ApmInterface $apm = null;  // APM instance (set by Bootstrap/SwooleBootstrap)
```

### JWTToken
**Namespace**: `Gemvc\Http\JWTToken`

**Methods**:
```php
__construct(): void
createAccessToken(int $userId, array $data = []): string
createRefreshToken(int $userId, array $data = []): string
createLoginToken(int $userId, array $data = []): string
verify(string $token): object|false
renew(string $token): ?string
```

## Validation Types

### Schema Validation Types
```php
'string'      // String validation
'int'         // Integer validation
'float'       // Float validation
'bool'        // Boolean validation
'array'       // Array validation
'email'       // Email format validation
'url'         // URL format validation
'date'        // Date format validation
'datetime'    // DateTime format validation
'json'        // JSON format validation
'ip'          // IP address validation
'ipv4'        // IPv4 address validation
'ipv6'        // IPv6 address validation
'?field'      // Optional field (prefix with ?)
```

### String Length Validation
```php
validateStringPosts([
    'name' => '2|100',        // 2-100 characters
    'password' => '8|128'     // 8-128 characters
])
```

## Common Patterns

### 1. Create Operation Pattern
```php
// API Layer
public function create(): JsonResponse {
    if(!$this->request->definePostSchema([
        'name' => 'string',
        'email' => 'email',
        'password' => 'string'
    ])) {
        return $this->request->returnResponse();
    }
    // Use callController() for automatic APM tracing (if APM_TRACE_CONTROLLER=1)
    return $this->callController(new UserController($this->request))->create();
}

// Controller Layer
public function create(): JsonResponse {
    // Use createModel() to automatically set Request for APM trace context propagation
    $model = $this->createModel(new UserModel());
    
    $model = $this->request->mapPostToObject(
        $model,
        ['email'=>'email', 'name'=>'name', 'password'=>'setPassword()']
    );
    if(!$model instanceof UserModel) {
        return $this->request->returnResponse();
    }
    return $model->createModel();
}

// Model Layer
public function createModel(): JsonResponse {
    // Business validation
    $this->email = strtolower($this->email);
    $found = $this->selectByEmail($this->email);
    if ($found) {
        return Response::unprocessableEntity("User already exists");
    }
    
    // Data transformation
    $this->setPassword($this->password);
    
    // Database operation
    $this->insertSingleQuery();
    if ($this->getError()) {
        return Response::internalError($this->getError());
    }
    
    return Response::created($this, 1, "User created successfully");
}
```

### 2. Read Operation Pattern
```php
// API Layer
public function read(): JsonResponse {
    if(!$this->request->defineGetSchema(["id" => "int"])) {
        return $this->request->returnResponse();
    }
    $id = $this->request->intValueGet("id");
    if(!$id) {
        return $this->request->returnResponse();
    }
    $this->request->post['id'] = $id;
    // Use callController() for automatic APM tracing (if APM_TRACE_CONTROLLER=1)
    return $this->callController(new UserController($this->request))->read();
}

// Model Layer
public function readModel(): JsonResponse {
    $item = $this->selectById($this->id);
    if (!$item) {
        return Response::notFound("User not found");
    }
    $item->password = "-";  // Hide password
    return Response::success($item, 1, "User retrieved successfully");
}
```

### 3. List with Filtering Pattern
```php
// API Layer
public function list(): JsonResponse {
    $this->request->findable(['name' => 'string', 'email' => 'email']);
    $this->request->sortable(['id', 'name', 'created_at']);
    // Use callController() for automatic APM tracing (if APM_TRACE_CONTROLLER=1)
    return $this->callController(new UserController($this->request))->list();
}

// Controller Layer
public function list(): JsonResponse {
    // Use createModel() to automatically set Request for APM trace context propagation
    $model = $this->createModel(new UserModel());
    return $this->createList($model);  // Auto pagination/filtering/sorting
}
```

### 4. Authentication Pattern
```php
public function update(): JsonResponse {
    // Authenticate
    if (!$this->request->auth()) {
        return $this->request->returnResponse();  // 401
    }
    
    // Authorize
    if (!$this->request->auth(['admin', 'moderator'])) {
        return $this->request->returnResponse();  // 403
    }
    
    // Continue...
}
```

## Available CLI Commands

```bash
# Project Management
gemvc init [--swoole|--apache|--nginx]
gemvc init --server=swoole --non-interactive

# Code Generation
gemvc create:crud ServiceName
gemvc create:service ServiceName [-cmt]
gemvc create:controller Name [-mt]
gemvc create:model Name [-t]
gemvc create:table Name

# Database Management
gemvc db:init
gemvc db:migrate TableClassName [--force] [--sync-schema]
gemvc db:list
gemvc db:describe TableName
gemvc db:drop TableName
gemvc db:unique TableName ColumnName
```

## Important Rules

1. **ALL table classes MUST extend `Table`**
2. **ALL API services MUST extend `ApiService`**
3. **ALL controllers MUST extend `Controller`**
4. **Properties starting with `_` are IGNORED in CRUD**
5. **Properties match database column names exactly**
6. **Use `protected` for sensitive data** (not returned in SELECT)
7. **All inputs are pre-sanitized** (no manual sanitization needed)
8. **All queries use prepared statements** (automatic ✅)
9. **URL mapping is automatic** (no routes config needed)
10. **PHPStan Level 9 compliance** (full type safety)

## Response Structure

All responses follow this structure:
```json
{
  "response_code": 200,
  "message": "OK",
  "count": 1,
  "service_message": "Operation successful",
  "data": { ... }
}
```

## HTTP Status Codes

- `200` - Success (OK)
- `201` - Created
- `209` - Updated
- `210` - Deleted
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity
- `500` - Internal Server Error


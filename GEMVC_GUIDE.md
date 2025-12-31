# GEMVC Framework Guide for AI Assistants

> Quick reference for GitHub Copilot and similar AI assistants

## Framework Overview

GEMVC is a 4-layer PHP REST API framework that works on Apache, OpenSwoole, and Nginx.

**Architecture**: API → Controller → Model → Table

## Quick Start - Code Generation

### Generate CRUD
```bash
gemvc create:crud Product
```

This creates 4 files:
- `app/api/Product.php` - API layer
- `app/controller/ProductController.php` - Controller layer
- `app/model/ProductModel.php` - Model layer
- `app/table/ProductTable.php` - Table layer

## Key Classes (Always Use)

```php
// Request object (already sanitized ✅)
$this->request->post
$this->request->definePostSchema([...])
$this->request->auth()

// Response factory
Response::success($data, 1, "Message")    // 200
Response::created($data, 1, "Message")     // 201
Response::updated(true, 1, "Message")      // 209
Response::deleted(true, 1, "Message")       // 210
Response::notFound("Message")             // 404
Response::unprocessableEntity("Message")   // 422
Response::internalError($error)            // 500

// Database queries
$this->select()->where('id', $id)->run()
$this->insertSingleQuery()
$this->updateSingleQuery()
$this->deleteByIdQuery($id)

// APM (Application Performance Monitoring)
ApmFactory::create($request)  // Returns ApmInterface or null
ProjectHelper::isApmEnabled() // Returns APM provider name or null

// Server Monitoring (cross-platform)
ServerMonitorHelper::getMemoryUsage()  // RAM metrics
ServerMonitorHelper::getCpuLoad()      // CPU load
NetworkHelper::getNetworkStats()       // Network stats
```

## Always Remember

1. **4 layers required**: API → Controller → Model → Table
2. **Extend base classes**: `ApiService`, `Controller`, `Table`
3. **No manual sanitization**: Already done ✅
4. **Use prepared statements**: Automatic ✅
5. **Properties match columns**: Exact names
6. **Use `_` prefix**: For aggregation (ignored in CRUD)
7. **Use `protected`**: For sensitive data (hidden from SELECT)

## Common Code Patterns

### Creating an Endpoint
Always follow this pattern:

```php
// API Layer
public function create(): JsonResponse {
    if(!$this->request->definePostSchema(['name' => 'string'])) {
        return $this->request->returnResponse();
    }
    return (new UserController($this->request))->create();
}

// Controller Layer
public function create(): JsonResponse {
    $model = $this->request->mapPostToObject(new UserModel());
    return $model->createModel();
}

// Model Layer
public function createModel(): JsonResponse {
    $this->insertSingleQuery();
    if ($this->getError()) {
        return Response::internalError($this->getError());
    }
    return Response::created($this, 1, "Created successfully");
}
```

### Adding Authentication
```php
if (!$this->request->auth(['admin'])) {
    return $this->request->returnResponse();
}
```

## CLI Commands

```bash
gemvc create:crud ServiceName
gemvc db:migrate TableClassName
gemvc db:list
```

## File Naming

- API: `User.php` in `app/api/`
- Controller: `UserController.php` in `app/controller/`
- Model: `UserModel.php` in `app/model/`
- Table: `UserTable.php` in `app/table/`

## What NOT to Create

- ❌ Routes config files (automatic mapping)
- ❌ Manual sanitization (automatic ✅)
- ❌ Raw SQL with concatenation (use prepared statements ✅)
- ❌ Laravel-style conventions

## Required Table Methods

```php
public function getTable(): string { return 'users'; }
public function defineSchema(): array { return []; }
protected array $_type_map = [...];
```


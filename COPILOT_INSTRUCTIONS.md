# GEMVC Framework - Instructions for AI Assistants

## 🎯 Use This Framework

GEMVC is a 4-layer PHP REST API framework.

**Architecture**: `API → Controller → Model → Table`

## 🔑 Essential Classes (Always Use)

```php
$this->request->definePostSchema([...])  // Schema validation
$this->request->auth(['role'])            // Authentication
Response::success($data, 1, "Message")    // Success response
Response::created($data, 1, "Message")    // Created response
Response::notFound("Message")             // Not found
$this->select()->where('id', $id)->run() // Query

// APM (Application Performance Monitoring)
ApmFactory::create($request)  // Returns ApmInterface or null
ProjectHelper::isApmEnabled() // Returns APM provider name or null

// Server Monitoring (cross-platform)
ServerMonitorHelper::getMemoryUsage()  // RAM metrics
ServerMonitorHelper::getCpuLoad()      // CPU load
NetworkHelper::getNetworkStats()       // Network stats
```

## 📋 Code Generation Pattern

**When generating code, ALWAYS follow 4 layers:**

1. **API Layer** - Validates schema, authenticates
2. **Controller Layer** - Maps request to model
3. **Model Layer** - Business logic, validates data
4. **Table Layer** - Database operations

```php
// Example: Create endpoint
// API: Validates schema → Controller: Maps to model → Model: Inserts → Table: Database
```

## ⚠️ Important Rules

1. **Always extend base classes**: `ApiService`, `Controller`, `Table`
2. **Properties match column names** exactly
3. **Use `_` prefix for aggregation** (ignored in CRUD)
4. **Use `protected` for sensitive data** (hidden from SELECT)
5. **No manual sanitization** (automatic ✅)
6. **No routes config** (automatic mapping ✅)
7. **Use prepared statements** (automatic ✅)

## 🚀 When User Asks: "Create [Entity] CRUD"

Generate these 4 files:

1. `app/api/{Entity}.php` - API service
2. `app/controller/{Entity}Controller.php` - Controller
3. `app/model/{Entity}Model.php` - Model
4. `app/table/{Entity}Table.php` - Table

Use CLI: `gemvc create:crud {Entity}`

## 🔍 Quick Examples

### Create Operation
```php
// API Layer
public function create(): JsonResponse {
    if(!$this->request->definePostSchema(['name'=>'string', 'email'=>'email'])) {
        return $this->request->returnResponse();
    }
    return (new UserController($this->request))->create();
}

// Model Layer  
public function createModel(): JsonResponse {
    $this->insertSingleQuery();
    if ($this->getError()) return Response::internalError($this->getError());
    return Response::created($this, 1, "Created");
}
```

### Authentication
```php
if (!$this->request->auth(['admin'])) {
    return $this->request->returnResponse(); // 403
}
```

## ❌ DON'T Create

- Routes config files (auto routing ✅)
- Manual sanitization code (auto ✅)
- Raw SQL strings (prepared ✅)
- Laravel-style conventions

## ✅ DO Create

- Classes extending base classes
- Type-safe code (PHPStan Level 9)
- 4-layer architecture
- Proper error handling

## 📁 File Locations

- API: `app/api/`
- Controller: `app/controller/`
- Model: `app/model/`
- Table: `app/table/`


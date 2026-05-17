# GEMVC AI Assistant Master Instructions

> **For AI Assistants**: This file instructs you to read ALL GEMVC framework documentation files to fully understand the architecture, patterns, and conventions.

## 📖 MANDATORY READING - Read These Files in Order

You MUST read these files to understand GEMVC framework:

### 1. Core Documentation (Read First)
- **`.cursorrules`** - Complete framework rules and patterns
- **`GEMVC_GUIDE.md`** - Quick reference guide for code generation
- **`AI_CONTEXT.md`** - Framework overview and quick patterns

### 2. API Reference (Read Second)
- **`AI_API_REFERENCE.md`** - Complete API documentation with method signatures
- **`GEMVC_PHPDOC_REFERENCE.php`** - PHPDoc annotations for type hints

### 3. Structured Data (Read Third)
- **`gemvc-api-reference.jsonc`** - Structured framework data for programmatic access

### 4. Advanced Topics (Read Fourth)
- **`GEMVC_DOCUMENTATION_DIRECTIVES.md`** - Auto-documentation system for API endpoints

### 5. Setup Guide (Optional Reference)
- **`AI_ASSISTANT_SETUP.md`** - Overview of all documentation files

---

## 🎯 Your Purpose as AI Assistant

You MUST understand GEMVC's architecture:

### 4-Layer Architecture (MANDATORY)
```
API Layer (app/api/)     → Schema validation, authentication
Controller Layer         → Business logic orchestration  
Model Layer (app/model/) → Data logic, validations, transformations
Table Layer (app/table/) → Database operations
```

**CRITICAL**: NEVER skip layers. All 4 layers are required.

---

## 🔥 Key Framework Rules You MUST Follow

### 1. Security (Automatic - NO Manual Work)
- ✅ Input sanitization is AUTOMATIC
- ✅ SQL injection prevention is AUTOMATIC  
- ✅ Header sanitization is AUTOMATIC
- ✅ Path protection is AUTOMATIC
- **You ONLY call**: `definePostSchema()` and `auth()`

### 2. Request Layer (`Gemvc\Http\Request`)
```php
// Schema Validation
$this->request->definePostSchema(['name' => 'string', 'email' => 'email'])
$this->request->defineGetSchema(['id' => 'int'])

// Type-Safe Getters (returns int|false, float|false, not ?int!)
$this->request->intValueGet('id')       // Returns int|false
$this->request->intValuePost('id')      // Returns int|false
$this->request->stringValueGet('name')   // Returns ?string

// Authentication
$this->request->auth()                  // JWT check
$this->request->auth(['admin'])          // Role check

// Filtering & Sorting
$this->request->findable(['name' => 'string'])    // LIKE search
$this->request->sortable(['id', 'name'])          // Enable sorting
$this->request->filterable(['status' => 'string']) // Exact match

// Mapping
$this->request->mapPostToObject($model, ['email'=>'email', 'password'=>'setPassword()'])

// Response
$this->request->returnResponse()          // Returns JsonResponse
```

### 3. Response Layer (`Gemvc\Http\Response`)
```php
Response::success($data, $count, $message)       // 200
Response::created($data, $count, $message)        // 201
Response::updated($result, $count, $message)      // 209
Response::deleted($result, $count, $message)      // 210
Response::notFound($message)                     // 404
Response::badRequest($message)                    // 400
Response::unprocessableEntity($message)           // 422
Response::internalError($message)                 // 500
Response::unauthorized($message)                  // 401
Response::forbidden($message)                     // 403
```

### 4. Table Layer (`Gemvc\Database\Table`)
```php
// Query Builder (Fluent Interface)
$result = $this->select()
    ->where('active', true)
    ->whereLike('name', '%test%')
    ->orderBy('name', true)  // true = ASC, false/null = DESC
    ->limit(10)
    ->run();  // Returns array<static>

// CRUD Operations (Return Types!)
insertSingleQuery(): ?static    // Returns SELF on success
updateSingleQuery(): ?static    // Returns SELF on success
deleteByIdQuery(int $id): ?int   // Returns DELETED ID on success
selectById(int $id): null|static // Custom pattern

// Helper Methods
if ($this->getError()) { ... }
$this->validateId($id, 'operation')
```

### 5. Property Rules
- Properties match database columns EXACTLY
- `protected` properties are NOT returned in SELECT queries (use for passwords)
- Properties starting with `_` are IGNORED in CRUD (use for aggregation)
- Define ALL properties in `$_type_map`

### 6. Required Methods
```php
// Table Layer MUST implement:
public function getTable(): string
public function defineSchema(): array
protected array $_type_map = [...];
```

---

## 🎯 Code Generation Patterns You MUST Follow

### When User Says: "Create a Product CRUD"

You MUST generate all 4 files following this pattern:

**API Layer** - `app/api/Product.php`
```php
class Product extends ApiService {
    public function create(): JsonResponse {
        if(!$this->request->definePostSchema(['name' => 'string', 'price' => 'float'])) {
            return $this->request->returnResponse();
        }
        return (new ProductController($this->request))->create();
    }
    
    public function read(): JsonResponse {
        if(!$this->request->defineGetSchema(['id' => 'int'])) {
            return $this->request->returnResponse();
        }
        $id = $this->request->intValueGet('id');
        if($id === false) return $this->request->returnResponse();
        $this->request->post['id'] = $id;
        return (new ProductController($this->request))->read();
    }
}
```

**Controller Layer** - `app/controller/ProductController.php`
```php
class ProductController extends Controller {
    public function create(): JsonResponse {
        $model = $this->request->mapPostToObject(new ProductModel());
        if(!$model instanceof ProductModel) {
            return $this->request->returnResponse();
        }
        return $model->createModel();
    }
}
```

**Model Layer** - `app/model/ProductModel.php`
```php
class ProductModel extends ProductTable {
    public function createModel(): JsonResponse {
        $this->insertSingleQuery();
        if ($this->getError()) {
            return Response::internalError($this->getError());
        }
        return Response::created($this, 1, "Product created successfully");
    }
}
```

**Table Layer** - `app/table/ProductTable.php`
```php
class ProductTable extends Table {
    public int $id;
    public string $name;
    public float $price;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'price' => 'float',
    ];
    
    public function getTable(): string { return 'products'; }
    
    public function defineSchema(): array {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            Schema::index('name'),
        ];
    }
}
```

---

## ❌ NEVER DO THESE THINGS

1. ❌ **NEVER skip layers** - Always create all 4 files
2. ❌ **NEVER create routes files** - URL mapping is automatic
3. ❌ **NEVER add manual sanitization** - Already automatic ✅
4. ❌ **NEVER use string concatenation for SQL** - Use prepared statements ✅
5. ❌ **NEVER use Laravel patterns** - GEMVC has its own conventions
6. ❌ **NEVER forget type hints** - PHPStan Level 9 compliance required
7. ❌ **NEVER return bool from insertSingleQuery/updateSingleQuery** - They return ?static

---

## ✅ ALWAYS DO THESE THINGS

1. ✅ **Always extend base classes**: `ApiService`, `Controller`, `Table`
2. ✅ **Always implement required methods**: `getTable()`, `defineSchema()`, `$_type_map`
3. ✅ **Always use `definePostSchema()`** - Schema validation prevents mass assignment
4. ✅ **Always use `auth()`** - For authentication/authorization
5. ✅ **Always check for errors**: `if ($this->getError()) { ... }`
6. ✅ **Always use prepared statements** - Automatic via fluent query builder
7. ✅ **Always match property names to database columns**

---

## 📚 How to Use This Documentation

When a user asks you to generate GEMVC code:

1. **READ** `.cursorrules` for complete rules
2. **READ** `GEMVC_GUIDE.md` for quick patterns
3. **READ** `AI_API_REFERENCE.md` for method signatures
4. **READ** `AI_CONTEXT.md` for examples
5. **REFERENCE** `gemvc-api-reference.jsonc` for structured data

**Generate code following ALL rules from these files!**

---

## 🎓 Example User Requests and How to Handle Them

**User**: "Create a Task management system"

**You should:**
1. Generate TaskTable.php with properties and $_type_map
2. Generate TaskModel.php extending TaskTable
3. Generate TaskController.php extending Controller
4. Generate Task.php extending ApiService with create/read/update/delete methods
5. Follow ALL patterns from documentation

**User**: "Add authentication to Product delete"

**You should:**
1. Add `auth()` call in Product::delete() method
2. Return JsonResponse on failure
3. Follow exact pattern from AI_API_REFERENCE.md

---

## 🚀 Final Instructions

**REMEMBER**:
- GEMVC is NOT Laravel or Symfony
- Has its own 4-layer architecture
- Security is 90% automatic
- Same code works on OpenSwoole, Apache, and Nginx (server-agnostic)
- Type safety is critical (PHPStan Level 9)
- All inputs are pre-sanitized
- No routing config needed

**YOU MUST**:
- Read all documentation files listed above
- Follow the 4-layer pattern MANDATORY
- Use correct return types
- Follow GEMVC conventions, not Laravel
- Apply all security best practices
- Generate type-safe code

**NOW READ THE DOCUMENTATION FILES AND START HELPING USERS!**


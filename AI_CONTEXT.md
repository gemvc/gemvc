# GEMVC Framework - AI Assistant Context

> **Quick Reference**: Use this file to understand how to generate GEMVC code correctly.

## 🎯 Framework Philosophy

**GEMVC is NOT Laravel or Symfony** - It has its own architecture, conventions, and patterns.

### Key Principles
- ✅ **4-Layer Architecture** (API → Controller → Model → Table)
- ✅ **Server-Agnostic** (OpenSwoole, Apache, Nginx - all supported!)
- ✅ **90% Automatic Security** (No manual sanitization)
- ✅ **Type Safety** (PHPStan Level 9)
- ✅ **Lightweight ORM** (Microservice-friendly)

---

## 📐 Architecture Pattern

**NEVER skip layers - This is mandatory!**

```
┌─────────────────────────────────────────┐
│  API Layer (app/api/User.php)          │
│  - Schema validation                    │
│  - Authentication                        │
│  - Delegates to Controller              │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│  Controller Layer (app/controller/)     │
│  - Business logic orchestration         │
│  - Maps request to model               │
│  - Delegates to Model                  │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│  Model Layer (app/model/UserModel.php) │
│  - Data validations                     │
│  - Business rules                       │
│  - Data transformations                │
│  - Delegates to Table                  │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│  Table Layer (app/table/UserTable.php) │
│  - Database queries                     │
│  - Uses prepared statements ✅          │
│  - Fluent query builder                │
└─────────────────────────────────────────┘
```

---

## 🔑 Quick Reference

### Request Object (`$this->request`)
```php
// Already sanitized ✅ - No manual sanitization needed!
$this->request->post                    // POST data
$this->request->get                     // GET data
$this->request->files                   // File uploads

// Schema Validation
$this->request->definePostSchema([...]) // Returns bool

// Authentication
$this->request->auth()                  // JWT check
$this->request->auth(['admin'])         // Role check

// Response
$this->request->returnResponse()        // Returns JsonResponse
```

### Response Methods
```php
Response::success($data, 1, "Message")      // 200
Response::created($data, 1, "Message")       // 201
Response::updated(true, 1, "Message")       // 209
Response::deleted(true, 1, "Message")        // 210
Response::notFound("Message")               // 404
Response::unprocessableEntity("Message")    // 422
Response::internalError($error)             // 500
```

### Database Query Builder
```php
// Fluent interface
$this->select(['id', 'name'])
    ->where('active', true)
    ->whereIn('id', [1, 2, 3])
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->run();  // Returns array of objects

// CRUD
$this->insertSingleQuery()   // Insert
$this->updateSingleQuery()   // Update
$this->deleteByIdQuery($id)  // Delete
$this->selectById($id)       // Custom method
```

### Security (Automatic ✅)
- ✅ Input sanitization (XSS prevention)
- ✅ SQL injection prevention (prepared statements)
- ✅ Header sanitization
- ✅ Path protection
- ✅ Cookie filtering

**Only call**: `definePostSchema()` and `auth()`

### APM (Application Performance Monitoring)
```php
// APM is automatically initialized in ApiService and Controller
// Uses ApmFactory from gemvc/apm-contracts package
$apm = ApmFactory::create($request);  // Returns ApmInterface or null

// Check if APM is enabled
if (ApmFactory::isEnabled() !== null) {
    // APM is enabled
}

// APM providers: TraceKit, Datadog, etc. (via separate packages)
```

### Server Monitoring Helpers
```php
// RAM metrics
$ram = ServerMonitorHelper::getMemoryUsage();

// CPU metrics
$load = ServerMonitorHelper::getCpuLoad();
$cores = ServerMonitorHelper::getCpuCores();
$usage = ServerMonitorHelper::getCpuUsage();

// Network statistics
$network = NetworkHelper::getNetworkStats();
$interfaces = NetworkHelper::getNetworkInterfaces();
```

---

## 💡 Code Generation Examples

### When User Says: "Create a User CRUD endpoint"

**DO Create:**
```php
// app/api/User.php
class User extends ApiService {
    public function create(): JsonResponse {
        if(!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        return (new UserController($this->request))->create();
    }
}

// app/controller/UserController.php
class UserController extends Controller {
    public function create(): JsonResponse {
        $model = $this->request->mapPostToObject(
            new UserModel(),
            ['email'=>'email', 'name'=>'name', 'password'=>'setPassword()']
        );
        if(!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        return $model->createModel();
    }
}

// app/model/UserModel.php
class UserModel extends UserTable {
    public function createModel(): JsonResponse {
        $this->email = strtolower($this->email);
        $found = $this->selectByEmail($this->email);
        if ($found) {
            return Response::unprocessableEntity("User already exists");
        }
        $this->setPassword($this->password);
        $this->insertSingleQuery();
        if ($this->getError()) {
            return Response::internalError($this->getError());
        }
        return Response::created($this, 1, "User created successfully");
    }
}

// app/table/UserTable.php
class UserTable extends Table {
    public int $id;
    public string $name;
    public string $email;
    protected string $password;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'password' => 'string',
    ];
    
    public function getTable(): string { return 'users'; }
    public function defineSchema(): array {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            Schema::unique('email'),
        ];
    }
    
    public function selectByEmail(string $email): null|static {
        $arr = $this->select()->where('email', $email)->limit(1)->run();
        return $arr[0] ?? null;
    }
}
```

---

## ❌ Common Mistakes to AVOID

### Don't Create:
- ❌ Routes config files (`routes/web.php`, `routes/api.php`)
- ❌ Manual sanitization (`htmlspecialchars()`, etc.)
- ❌ String concatenation for SQL queries
- ❌ Magic methods that break PHPStan Level 9
- ❌ Autoloader configuration
- ❌ Laravel-style conventions

### Don't Do:
- ❌ Skip the 4-layer architecture
- ❌ Create helpers for sanitization (already automatic)
- ❌ Use Eloquent-style ORM syntax
- ❌ Mix database operations across layers
- ❌ Forget to extend base classes (`ApiService`, `Controller`, `Table`)

---

## ✅ DO Create

### Always Extend Base Classes:
```php
✅ class User extends ApiService { }
✅ class UserController extends Controller { }
✅ class UserModel extends UserTable { }  // Or Table directly
✅ class UserTable extends Table { }
```

### Always Implement Required Methods:
```php
// Table Layer
✅ public function getTable(): string
✅ public function defineSchema(): array
✅ protected array $_type_map = [];

// Model Layer
✅ public function createModel(): JsonResponse
✅ public function readModel(): JsonResponse
✅ public function updateModel(): JsonResponse
✅ public function deleteModel(): JsonResponse
```

### Property Naming Rules:
```php
✅ public int $id;              // Matches database column 'id'
✅ public string $name;         // Matches database column 'name'
✅ public ?string $description; // Nullable column
✅ protected string $password;  // Hidden from SELECT queries
✅ public ?Profile $_profile;   // Aggregation (ignored in CRUD)
```

---

## 🎯 Key Patterns

### 1. Authentication Pattern
```php
// In API Layer
if (!$this->request->auth()) {
    return $this->request->returnResponse();  // 401
}

if (!$this->request->auth(['admin'])) {
    return $this->request->returnResponse();  // 403
}
```

### 2. Schema Validation Pattern
```php
if(!$this->request->definePostSchema([
    'name' => 'string',
    'email' => 'email',
    '?phone' => 'string'  // Optional field
])) {
    return $this->request->returnResponse();  // 400
}
```

### 3. Error Handling Pattern
```php
if ($this->getError()) {
    return Response::internalError($this->getError());
}
```

### 4. Aggregation Pattern
```php
// Properties starting with _ are ignored in CRUD
public ?Profile $_profile = null;
public array $_orders = [];

// Usage
$user->_profile = $profile;  // Won't be inserted
$user->insertSingleQuery();  // Only inserts: id, name, email
```

---

## 🚀 Common Tasks

### Task: "Create a Product API with authentication"

**Step 1**: Generate code
```bash
gemvc create:crud Product
```

**Step 2**: Add authentication to API
```php
// app/api/Product.php
public function create(): JsonResponse {
    if (!$this->request->auth(['admin'])) {
        return $this->request->returnResponse();
    }
    // ... rest of code
}
```

**Step 3**: Migrate database
```bash
gemvc db:migrate ProductTable
```

---

## 📊 Response Structure

All responses follow this format:
```json
{
  "response_code": 200,
  "message": "OK",
  "count": 1,
  "service_message": "Operation successful",
  "data": { ... }
}
```

---

## 🎓 Remember

1. **4-Layer Architecture is MANDATORY** - Never skip layers
2. **Security is 90% automatic** - Only call `auth()` and `definePostSchema()`
3. **Same code everywhere** - Server-agnostic design
4. **Type safety is critical** - PHPStan Level 9 compliance
5. **No routing config** - URLs auto-map to classes
6. **Use _ prefix** - For aggregation properties
7. **Use protected** - For sensitive data (not in SELECT)
8. **Match column names** - Properties match database columns exactly

---

## 📁 File Locations

```
app/
├── api/          # API services (URL endpoints)
├── controller/   # Business logic
├── model/        # Data logic, validations
└── table/        # Database operations

src/
├── core/         # Bootstrap, ApiService, Security
├── http/         # Request, Response, JWT
├── database/     # Table, QueryBuilder
└── helper/       # CryptHelper, FileHelper, etc.
```

---

## 🆘 Quick Help

**Need to create CRUD?**
```bash
gemvc create:crud ServiceName
```

**Need to add authentication?**
```php
if (!$this->request->auth(['role'])) {
    return $this->request->returnResponse();
}
```

**Need to validate schema?**
```php
if(!$this->request->definePostSchema([...])) {
    return $this->request->returnResponse();
}
```

**Need to query database?**
```php
$this->select()->where('id', $id)->limit(1)->run();
```

**Need error response?**
```php
Response::internalError($this->getError())
```

---

**For more details, see:**
- `.cursorrules` - Detailed AI assistant rules
- `AI_API_REFERENCE.md` - Complete API reference
- `gemvc-api-reference.jsonc` - Structured API data
- Source files in `src/startup/user/` - Example implementation


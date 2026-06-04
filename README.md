![gemvc-tracekit](https://github.com/user-attachments/assets/c730d3b8-877f-4793-9261-34ca392cf692)


# [GEMVC](https://www.gemvc.de) - The PHP Multi-Platform Microservices REST API Framework

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/releases/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Swoole](https://img.shields.io/badge/Swoole-Supported-green.svg?style=flat-square&logo=swoole&logoColor=white)](https://openswoole.com/)
[![Apache](https://img.shields.io/badge/Apache-Supported-D22128.svg?style=flat-square&logo=apache&logoColor=white)](https://httpd.apache.org/)
[![Nginx](https://img.shields.io/badge/Nginx-Supported-009639.svg?style=flat-square&logo=nginx&logoColor=white)](https://nginx.org/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-Supported-3b8c3b.svg?style=flat-square&logo=phpunit&logoColor=white)](https://phpunit.de/)
[![Pest](https://img.shields.io/badge/Pest-Supported-purple.svg?style=flat-square&logo=pest&logoColor=white)](https://pestphp.com/)
## 🚀 Start in 30 Seconds
```bash
composer require gemvc/library
php vendor/bin/gemvc init
```

### Stop using a Swiss Army Knife when you need a Scalpel. *GEMVC* is a PHP Multi-Platform(OpenSwoole, Apache and NginX) specialized, ultra-lightweight framework designed for high-performance REST APIs and Microservices.

### Not to replace, but to complete
> GEMVC is not a replacement for lovely Laravel or powerful Symfony; it is a complementary tool designed to solve special challenges. It is always good to have it in your arsenal!

### You are the Master of your Code
> In GEMVC, architecture is just a recommendation. GEMVC NEVER forces you to follow its rules. You can implement the recommended 4-layer architecture, or write your own familiar 3, 2, or even single-layer code!

### AI-Ready - Born for AI Agents
>Thanks to its transparent structure, strict naming conventions, and clean 4-layer separation of concerns (with no magic functions or hidden classes), GEMVC is natively **AI-Friendly**. AI Assistants can easily understand your code context and assist you efficiently. Writing code with GEMVC is a joy for both humans and AI.
>GEMVC is designed to work seamlessly with **Cursor, Copilot, and ChatGPT**.
We include pre-configured context files (`AI_API_REFERENCE.md`, `.cursorrules`) right in the root. 
Your AI assistant already knows how to write GEMVC code perfectly!

### Easy to Learn
> It takes only a couple of hours to master this tool. GEMVC respects your time and is not here to replace your existing knowledge, but to sharpen it.

### No-Hassle Documentation
> In GEMVC, **Sanitization IS Documentation**. When you sanitize API inputs (as you normally should), the framework automatically generates full documentation for your endpoints. You get beautiful, well-designed documentation simply by defining your validation rules. Plus, you get out-of-the-box support to export your latest API version to **Postman** with a single click. Your frontend developers will love GEMVC!

### ⚡ The "Async Magic"
> Write standard, synchronous PHP code (like you always do) on Apache/Nginx. 
Switch to **OpenSwoole**, and your exact same code automatically runs as **Asynchronous Non-blocking I/O**.
**Zero code changes required.**

### Fully and Easily Customizable
> You can easily define custom Class Templates for any layer, and then CLI Commands will generate your classes in seconds!

### Library or Framework? The Choice is Yours.
> You can use GEMVC as a library within a legacy project to migrate to a modern architecture step-by-step, without **breaking** existing functionality. Or, for new projects, simply install it via Composer and run the `gemvc init` command to use it as a modern framework from day one.

### Testing & Quality Assurance
> When coding with GEMVC, you can enjoy **PHPStan Level 9** compliance! The framework natively supports strong typing—while not forced, it is strongly recommended. It also comes with **PHPUnit** and **Pest** support right out of the box during the installation process. You can easily test your product using your beloved PHPUnit or the beautiful Pest framework. Plus, the 4-layer architecture makes creating comprehensive Unit and Integration tests a breeze.

### Full Transparency Policy
> It is Open Source, MIT Licensed, and small. There is no hidden code or "black box". You can download the source code, analyze it, test it, and use it with **peace of mind**. It is built with love to make your life as a developer more enjoyable.


---

## 🤖 FOR AI ASSISTANTS - READ THIS FIRST!

**ATTENTION: If you are an AI assistant (Cursor AI, GitHub Copilot, Claude, etc.), you MUST read the following file first:**
> **GEMVC is NOT Laravel or Symfony** - It has its own unique architecture, naming conventions, and design philosophy. This guide will teach you how to write code the GEMVC way.
### ⚠️ **[QUICK_START_AI.md](QUICK_START_AI.md)** - MASTER INSTRUCTIONS FOR AI ASSISTANTS

**This file will:**
1. ✅ Instruct you to read ALL GEMVC documentation files
2. ✅ Teach you the complete 4-layer architecture (MANDATORY)
3. ✅ Show you correct method signatures and return types
4. ✅ Explain security features (90% automatic)
5. ✅ Provide code generation patterns you MUST follow
6. ✅ List common mistakes you MUST avoid

**After reading QUICK_START_AI.md, you should read:**
- `.cursorrules` - Complete framework rules
- `AI_API_REFERENCE.md` - All method signatures
- `AI_CONTEXT.md` - Quick examples
- `GEMVC_GUIDE.md` - Code generation patterns
- `vendor/gemvc/cli-base/AI-Assistant.md` - CLI foundation (`Command`, `CliColor`, generators) when editing CLI or codegen

**Do NOT skip layers or use Laravel patterns. Follow GEMVC conventions only!**
---

## 🎯 What is GEMVC?

GEMVC is a **multi-platform PHP REST API framework** that works identically on:
- ✅ **OpenSwoole** (async, WebSocket, hot reload)
- ✅ **Apache** (traditional PHP-FPM)
- ✅ **Nginx** (high performance, reverse proxy)

**Key Features**:
- 🔒 **90% Security Automatic** - Input sanitization, SQL injection prevention, path protection
- 🌐 **Webserver-Agnostic** - Same code works on all platforms
- 🛠️ **CLI Code Generation** - Generate Services, Controllers, Models, Tables (shared foundation in [`gemvc/cli-base`](https://github.com/gemvc/cli-base))
- 📝 **Simple API** - Clean, straightforward code structure
- ⚡ **High Performance** - Connection pooling, async capabilities
- ✅ **PHPStan Level 9** - Write type-safe, bug-free code with the highest static analysis level
- 📊 **Native APM Integration** - Automatic Application Performance Monitoring with zero configuration
  - Automatic request tracing (root spans)
  - Controller operation tracing (optional)
  - Database query tracing (optional)
  - Environment-controlled via `.env` flags
  - Works with any APM provider (TraceKit, Datadog, New Relic, etc.)
  - Fire-and-forget pattern (zero performance impact)
- 📈 **Server Monitoring** - Built-in RAM, CPU, network, and database metrics

---

## 🏗️ Architecture Overview

GEMVC uses a **4-layer architecture** pattern:

```
API Layer (app/api/)          → URL endpoints, schema validation
    ↓
Controller Layer (app/controller/) → Business logic orchestration
    ↓
Model Layer (app/model/)      → Data logic, validations
    ↓
Table Layer (app/table/)      → Database access, queries
```

**Example Flow**:
```
POST /api/User/create
    ↓
app/api/User.php::create()        → Validates schema
    ↓
app/controller/UserController.php  → Handles business logic
    ↓
app/model/UserModel.php           → Data validations, transformations
    ↓
app/table/UserTable.php           → Database operations
```

---

## 📊 Native APM Integration

GEMVC includes **native Application Performance Monitoring (APM)** integration that works automatically with zero code changes. The APM system captures the full request lifecycle and provides deep insights into your application's performance.

### Key Features

✅ **Automatic Root Trace** - Captures full request lifecycle from Bootstrap  
✅ **Controller Tracing** - Automatic spans for controller operations (optional)  
✅ **Database Query Tracing** - Automatic spans for all SQL queries (optional)  
✅ **Exception Tracking** - Automatic exception recording in traces  
✅ **Environment-Controlled** - Enable/disable via `.env` variables  
✅ **Zero Performance Impact** - Fire-and-forget pattern, traces sent after HTTP response  
✅ **Provider-Agnostic** - Works with any APM provider (TraceKit, Datadog, New Relic, etc.)

### Quick Setup

**1. Install APM Provider:**
```bash
composer require gemvc/apm-tracekit
```

**2. Configure Environment:**
```env
APM_NAME=TraceKit
TRACEKIT_API_KEY=your-api-key
TRACEKIT_API_URL=https://app.tracekit.dev/v1/traces

# Optional: Enable controller and database tracing
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

**3. Use `callController()` in API Services:**
```php
// app/api/User.php
public function create(): JsonResponse
{
    // Automatic controller tracing (if APM_TRACE_CONTROLLER=1)
    return $this->UserController->create();
}
```

*Note: You can still use `$this->callController(new UserController($this->request))` if you prefer, but the magic property syntax is recommended.*

**4. Use `createModel()` in Controllers:**
```php
// app/controller/UserController.php
public function create(): JsonResponse
{
    // Automatic Request propagation for database tracing
    $model = $this->createModel(new UserModel());
    // ... rest of code
}
```

**That's it!** APM tracing works automatically. No code changes needed beyond using `callController()` and `createModel()`.

### What Gets Traced

- **Root Request** - Full request lifecycle (automatic)
- **Controller Operations** - Method calls with response codes (if `APM_TRACE_CONTROLLER=1`)
- **Database Queries** - All SQL queries with execution time (if `APM_TRACE_DB_QUERY=1`)
- **Exceptions** - All exceptions automatically recorded

### Trace Structure

```
Root Trace (Bootstrap)
  └─ controller-operation (UserController::create)
      └─ database-query (INSERT INTO users ...)
```

All spans share the same `traceId` for complete request visibility.

### Performance

- **Zero Overhead When Disabled** - Environment flags control tracing
- **Minimal Overhead When Enabled** - ~0.25ms per request
- **Non-Blocking** - Traces sent after HTTP response (fire-and-forget)

### Documentation

For complete APM integration guide, see **[GEMVC_APM_INTEGRATION.md](GEMVC_APM_INTEGRATION.md)**

---

## 📐 4-Layer Architecture

### 1. **API Layer** (`app/api/`)
**Purpose**: URL endpoints, request validation, authentication

**Naming**: PascalCase (e.g., `User.php`, `Product.php`)

**Responsibilities**:
- Define request schemas (`definePostSchema()`, `defineGetSchema()`)
- Handle authentication (`$request->auth()`)
- Delegate to Controller layer

**Example** (`app/api/User.php`):
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
        // Validate request schema
        if(!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        // Delegate to controller
        return (new UserController($this->request))->create();
    }
}
```

**Key Points**:
- ✅ Extends `ApiService` (or `SwooleApiService` for OpenSwoole)
- ✅ Uses `definePostSchema()` for validation
- ✅ Uses `?` prefix for optional fields: `'?name' => 'string'`
- ✅ Delegates to Controller, doesn't handle business logic

---

### 2. **Controller Layer** (`app/controller/`)
**Purpose**: Business logic orchestration

**Naming**: PascalCase + "Controller" suffix (e.g., `UserController.php`)

**Responsibilities**:
- Orchestrate business logic
- Map request data to models
- Handle request/response flow

**Example** (`app/controller/UserController.php`):
```php
<?php
namespace App\Controller;

use App\Model\UserModel;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function create(): JsonResponse
    {
        // Map POST data to Model with custom handlers
        $model = $this->request->mapPostToObject(
            new UserModel(),
            [
                'email' => 'email',
                'name' => 'name',
                'description' => 'description',
                'password' => 'setPassword()'  // Calls setPassword() method
            ]
        );
        
        if(!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        
        return $model->createModel();
    }
}
```

**Key Points**:
- ✅ Extends `Controller`
- ✅ Uses `mapPostToObject()` to convert request to model
- ✅ Can specify method calls: `'password' => 'setPassword()'`
- ✅ Delegates to Model layer

---

### 3. **Model Layer** (`app/model/`)
**Purpose**: Data logic, validations, transformations

**Naming**: PascalCase + "Model" suffix (e.g., `UserModel.php`)

**Responsibilities**:
- Business validations (e.g., duplicate email check)
- Data transformations (e.g., password hashing)
- Error handling

**Example** (`app/model/UserModel.php`):
```php
<?php
namespace App\Model;

use App\Table\UserTable;
use Gemvc\Helper\CryptHelper;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class UserModel extends UserTable
{
    public function createModel(): JsonResponse
    {
        // Business validation: Check duplicate email
        $this->email = strtolower($this->email);
        $found = $this->selectByEmail($this->email);
        if ($found) {
            return Response::unprocessableEntity("User already exists");
        }
        
        // Data transformation: Hash password
        $this->setPassword($this->password);
        
        // Perform database operation
        $success = $this->insertSingleQuery();
        if ($this->getError()) {
            return Response::internalError($this->getError());
        }
        
        return Response::created($this, 1, "User created successfully");
    }
    
    public function setPassword(string $plainPassword): void
    {
        $this->password = CryptHelper::hashPassword($plainPassword);
    }
}
```

**Key Points**:
- ✅ Extends corresponding `Table` class (e.g., `UserModel extends UserTable`)
- ✅ Contains business logic and validations
- ✅ Uses `insertSingleQuery()`, `updateSingleQuery()`, `deleteByIdQuery()`
- ✅ Returns `JsonResponse` objects

---

### 4. **Table Layer** (`app/table/`)
**Purpose**: Database access, queries, schema definition

**Naming**: PascalCase + "Table" suffix (e.g., `UserTable.php`)

**Responsibilities**:
- Define database table structure
- Define properties matching database columns
- Provide query methods

**Example** (`app/table/UserTable.php`):
```php
<?php
namespace App\Table;

use Gemvc\Database\Table;
use Gemvc\Database\Schema;

class UserTable extends Table
{
    // Properties match database columns
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    protected string $password;  // Protected = not exposed in selects
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
        'password' => 'string',
    ];
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function getTable(): string
    {
        return 'users';  // Database table name
    }
    
    public function defineSchema(): array
    {
        return [
            Schema::index('email'),
            Schema::unique('email'),
            Schema::index('description')
        ];
    }
    
    // Custom query methods
    public function selectById(int $id): null|static
    {
        $result = $this->select()->where('id', $id)->limit(1)->run();
        return $result[0] ?? null;
    }
    
    public function selectByEmail(string $email): null|static
    {
        $arr = $this->select()->where('email', $email)->limit(1)->run();
        return $arr[0] ?? null;
    }
}
```

**Key Points**:
- ✅ Extends `Table` class
- ✅ Properties match database columns (with types)
- ✅ `protected` properties are not exposed in SELECT queries
- ✅ Properties starting with `_` are **ignored in CRUD operations** (see below)
- ✅ Uses fluent query builder: `$this->select()->where()->limit()->run()`
- ✅ Returns `null|static` or `null|static[]` for query methods

---

## 🔗 Model Aggregation & Composition (Properties with `_` prefix)

**Important Feature**: Properties starting with `_` are **completely ignored** in all CRUD table operations!

This allows you to:
- ✅ Aggregate other models (composition)
- ✅ Store arrays of related models
- ✅ Create relationships without affecting database operations
- ✅ Use PHPStan Level 9 type checking for aggregated models

### How It Works

**Properties starting with `_` are skipped**:
- ❌ Not included in `INSERT` operations
- ❌ Not included in `UPDATE` operations
- ❌ Not included in table schema generation
- ✅ Can be used for aggregation/composition
- ✅ Can be public, private, or protected

### Example 1: Aggregating a Profile Model

**User Model with Profile Aggregation**:
```php
<?php
namespace App\Model;

use App\Table\UserTable;
use App\Model\Profile;  // Aggregated model

class UserModel extends UserTable
{
    // This property is IGNORED in database operations
    public ?Profile $_profile = null;
    
    /**
     * Get user with profile loaded
     */
    public function withProfile(): self
    {
        if ($this->_profile === null && $this->id) {
            $profileTable = new ProfileTable();
            $this->_profile = $profileTable->selectByUserId($this->id);
        }
        return $this;
    }
    
    /**
     * Set profile
     */
    public function setProfile(Profile $profile): void
    {
        $this->_profile = $profile;
    }
}
```

**Usage**:
```php
$user = new UserModel();
$user->id = 1;
$user->name = "John";
$user->email = "john@example.com";

// Add profile without affecting database operations
$profile = new Profile();
$profile->bio = "Software Developer";
$user->_profile = $profile;

// Save user (profile is NOT inserted, only user data)
$user->insertSingleQuery();  // Only inserts: id, name, email
```

---

### Example 2: Aggregating an Array of Orders

**User Model with Orders Array** (PHPStan Level 9):

```php
<?php
namespace App\Model;

use App\Table\UserTable;
use App\Model\Order;

class UserModel extends UserTable
{
    /**
     * Array of Order models - ignored in CRUD operations
     * @var array<Order>
     */
    public array $_orders = [];
    
    /**
     * Get user's orders
     * @return array<Order>
     */
    public function orders(): array
    {
        if (empty($this->_orders) && $this->id) {
            $orderTable = new OrderTable();
            $this->_orders = $orderTable->selectByUserId($this->id);
        }
        return $this->_orders;
    }
    
    /**
     * Add order to user
     */
    public function addOrder(Order $order): void
    {
        $this->_orders[] = $order;
    }
    
    /**
     * Create order for user
     */
    public function createOrder(array $orderData): Order
    {
        $order = new Order();
        $order->user_id = $this->id;
        $order->amount = $orderData['amount'];
        $order->insertSingleQuery();
        
        $this->_orders[] = $order;
        return $order;
    }
}
```

**Usage**:
```php
$user = new UserModel();
$user->id = 1;
$user->name = "John";

// Add orders (ignored in database operations)
$order1 = new Order();
$order1->amount = 100;
$user->addOrder($order1);

$order2 = new Order();
$order2->amount = 200;
$user->addOrder($order2);

// Save user (orders array is NOT inserted!)
$user->insertSingleQuery();  // Only inserts: id, name

// Later, create actual orders in database
foreach ($user->_orders as $order) {
    $order->user_id = $user->id;
    $order->insertSingleQuery();  // Save each order separately
}
```

---

### Example 3: Complex Relationships

**Product Model with Categories and Reviews**:

```php
<?php
namespace App\Model;

use App\Table\ProductTable;
use App\Model\Category;
use App\Model\Review;

class ProductModel extends ProductTable
{
    /**
     * Single related model
     * @var Category|null
     */
    public ?Category $_category = null;
    
    /**
     * Array of related models
     * @var array<Review>
     */
    public array $_reviews = [];
    
    /**
     * Load product with category and reviews
     */
    public function loadRelations(): self
    {
        if ($this->id) {
            // Load category
            $categoryTable = new CategoryTable();
            $this->_category = $categoryTable->selectById($this->category_id);
            
            // Load reviews
            $reviewTable = new ReviewTable();
            $this->_reviews = $reviewTable->selectByProductId($this->id);
        }
        return $this;
    }
    
    /**
     * Get average rating
     */
    public function getAverageRating(): float
    {
        if (empty($this->_reviews)) {
            return 0.0;
        }
        
        $total = 0;
        foreach ($this->_reviews as $review) {
            $total += $review->rating;
        }
        
        return round($total / count($this->_reviews), 2);
    }
}
```

**Usage**:
```php
$product = new ProductModel();
$product->id = 1;
$product->name = "Laptop";
$product->price = 999.99;

// Load relations
$product->loadRelations();

// Use aggregated data
echo $product->_category->name;  // "Electronics"
echo $product->getAverageRating();  // 4.5

// Save product (category and reviews are NOT affected!)
$product->updateSingleQuery();  // Only updates: id, name, price
```

---

### Best Practices

1. **Use Descriptive Names**: `$_profile`, `$_orders`, `$_reviews`
2. **Add Type Hints**: Use PHPStan Level 9 compatible types
   ```php
   public ?Profile $_profile = null;        // Single model
   public array $_orders = [];              // Array of models
   public array<int, Order> $_orders = [];  // Typed array (PHPStan)
   ```
3. **Create Helper Methods**: `orders()`, `withProfile()`, `loadRelations()`
4. **Lazy Loading**: Load aggregated data only when needed
5. **Keep Separate**: Don't mix database columns with aggregated properties

### Common Patterns

```php
// Pattern 1: Single aggregation
public ?Profile $_profile = null;

// Pattern 2: Array aggregation
public array $_orders = [];

// Pattern 3: Private aggregation with getter
private array $_reviews = [];
public function getReviews(): array {
    return $this->_reviews;
}

// Pattern 4: Protected aggregation
protected ?Category $_category = null;
```

### PHPStan Level 9 Examples

```php
// ✅ Full type safety with PHPStan Level 9
/** @var array<Order> */
public array $_orders = [];

// ✅ Nullable single model
public ?Profile $_profile = null;

// ✅ Typed array with PHPDoc
/**
 * @var array<int, Review>
 */
public array $_reviews = [];
```

**Result**: Clean, type-safe code with powerful aggregation capabilities! 🎯

---

## 🔄 URL Mapping

GEMVC maps URLs to code automatically:

```
URL: /api/User/create
    ↓
Extracts: Service = "User", Method = "create"
    ↓
Loads: app/api/User.php
    ↓
Calls: User::create()
```

**URL Structure**:
```
/api/{ServiceName}/{MethodName}
```

**Examples**:
- `POST /api/User/create` → `User::create()`
- `GET /api/User/read/?id=1` → `User::read()`
- `POST /api/User/update` → `User::update()`
- `POST /api/User/delete` → `User::delete()`
- `GET /api/User/list` → `User::list()`

**Configuration** (`.env`):
```env
SERVICE_IN_URL_SECTION=1  # Service name position in URL
METHOD_IN_URL_SECTION=2   # Method name position in URL
```

---

## 🔑 Key Differences from Laravel/Symfony

### 1. **Architecture Pattern**
**Laravel/Symfony**: MVC (Model-View-Controller)  
**GEMVC**: 4-Layer (API → Controller → Model → Table)

### 2. **Routing**
**Laravel**: Routes defined in `routes/web.php` or `routes/api.php`  
**GEMVC**: Automatic URL-to-class mapping (`/api/User/create` → `User::create()`)

### 3. **Request Validation**
**Laravel**: Form Requests, Validation Rules  
**GEMVC**: `definePostSchema()` method with inline validation

### 4. **Database Queries**
**Laravel**: Eloquent ORM (`User::create()`, `User::find()`)  
**GEMVC**: Fluent Query Builder (`$this->select()->where()->run()`)

### 5. **Naming Conventions**
**Laravel**: Singular models (`User`), plural tables (`users`)  
**GEMVC**: Consistent naming (`User` API, `UserController`, `UserModel`, `UserTable`)

### 6. **Response Format**
**Laravel**: Various response types  
**GEMVC**: Consistent `JsonResponse` with `Response::success()`, `Response::created()`, etc.

### 7. **Security**
**Laravel**: Manual middleware, CSRF tokens  
**GEMVC**: **90% automatic** - Input sanitization, SQL injection prevention built-in

---

## 🚀 Quick Start

> **📦 For complete installation guide, see [INSTALLATION.md](INSTALLATION.md)**

### Quick Overview:

**1. Install GEMVC:**
```bash
composer require gemvc/swoole
```

**2. Initialize Project:**
```bash
php vendor/bin/gemvc init
# Select: 1) OpenSwoole, 2) Apache, or 3) Nginx
# Install PHPStan: Yes (recommended)
# Setup Docker: Yes (recommended)
```

**3. Start Server:**
```bash
# With Docker
docker-compose up -d

# Without Docker (OpenSwoole)
php index.php
```

**4. Test Server:**
```bash
# Visit: http://localhost:9501/api
# Should return: "GEMVC server is running"
```

**5. Setup Database (optional):**
```bash
php vendor/bin/gemvc db:init
php vendor/bin/gemvc db:migrate UserTable
```

**6. Generate Your Service:**
```bash
php vendor/bin/gemvc create:crud Product
```

**7. Run PHPStan:**
```bash
composer phpstan
# or: vendor/bin/phpstan analyse -c phpstan.neon
```

**✅ For detailed step-by-step instructions, see [INSTALLATION.md](INSTALLATION.md)**

---

## 📚 Examples

### Example 1: Creating a User

**Request**:
```http
POST /api/User/create
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123"
}
```

**Flow**:
1. `User::create()` validates schema
2. `UserController::create()` maps data to `UserModel`
3. `UserModel::createModel()` validates business rules, hashes password
4. `UserTable::insertSingleQuery()` inserts into database

**Response**:
```json
{
    "response_code": 201,
    "message": "created",
    "count": 1,
    "service_message": "User created successfully",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "description": null
    }
}
```

---

### Example 2: Reading a User

**Request**:
```http
GET /api/User/read/?id=1
```

**Flow**:
1. `User::read()` validates GET parameter
2. `UserController::read()` delegates to model
3. `UserModel::readModel()` calls `selectById()`
4. Password is hidden (`password = "-"`)

**Response**:
```json
{
    "response_code": 200,
    "message": "OK",
    "count": 1,
    "service_message": "User retrieved successfully",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "password": "-"
    }
}
```

---

### Example 3: Listing Users with Filtering

**Request**:
```http
GET /api/User/list/?sort_by=name&find_like=name=John
```

**Flow**:
1. `User::list()` defines `findable()` and `sortable()` fields
2. `UserController::list()` uses `createList()` helper
3. Automatic filtering and sorting applied

**Response**:
```json
{
    "response_code": 200,
    "message": "OK",
    "count": 1,
    "service_message": "Users retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        }
    ]
}
```

---

## 🎓 Learning Path

1. **Study the User Example** (`src/startup/common/init_example/`)
   - See how all 4 layers work together
   - Understand request flow
   - Learn validation patterns

2. **Explore Built-in Services**
   - `Apm.php` - APM provider management and testing
   - `GemvcAssistant.php` - Developer/admin tools
   - `GemvcMonitoring.php` - Server monitoring metrics

3. **Generate Your First CRUD**
   ```bash
   gemvc create:crud Product
   ```

4. **Run PHPStan Level 9**
   ```bash
   vendor/bin/phpstan analyse
   ```
   - Fix any type errors
   - Write type-safe code
   - Catch bugs early!

5. **Customize Templates** (`templates/cli/`)
   - Edit templates to match your coding style
   - Add custom methods and patterns

6. **Read Documentation**
   - `ARCHITECTURE.md` - Full architecture details
   - `SECURITY.md` - Security features
   - `TEMPLATE_SYSTEM.md` - Template customization

---

## 📖 Important Notes

### ⚠️ Remember:
- **GEMVC is NOT Laravel** - Don't expect Laravel conventions
- **GEMVC is NOT Symfony** - Different architecture and patterns
- **Follow GEMVC patterns** - Use the User example as a reference
- **4-Layer Architecture** - API → Controller → Model → Table
- **Automatic Security** - Input sanitization, SQL injection prevention built-in
- **Native APM Integration** - Automatic Application Performance Monitoring
  - Zero configuration required (just install APM provider)
  - Environment-controlled tracing (`APM_TRACE_CONTROLLER`, `APM_TRACE_DB_QUERY`)
  - Use `callController()` for controller tracing
  - Use `createModel()` for database query tracing
  - See [GEMVC_APM_INTEGRATION.md](GEMVC_APM_INTEGRATION.md) for complete guide
- **Built-in Services** - Example services for APM, monitoring, and developer tools are included

### ✅ Do's:
- ✅ Extend `ApiService` for API classes
- ✅ Extend `Controller` for controllers
- ✅ Extend `Table` for models and tables
- ✅ Use `definePostSchema()` for validation
- ✅ Use fluent query builder for database operations
- ✅ Return `JsonResponse` objects
- ✅ **Use PHPStan Level 9** - Write type-safe code!
- ✅ Add type hints to all methods and properties
- ✅ Use strict types: `declare(strict_types=1);`
- ✅ **Use `_` prefix for aggregation** - Properties starting with `_` are ignored in CRUD operations
- ✅ **Use `callController()` in API services** - Enables automatic controller tracing
- ✅ **Use `createModel()` in controllers** - Enables automatic database query tracing

### ❌ Don'ts:
- ❌ Don't use Laravel conventions
- ❌ Don't create routes files
- ❌ Don't use Eloquent-style syntax
- ❌ Don't skip the 4-layer architecture
- ❌ Don't manually sanitize inputs (it's automatic!)
- ❌ Don't skip PHPStan - Run it regularly!
- ❌ Don't ignore type errors - Fix them!
- ❌ Don't use `mixed` types without reason

---

## 📚 Comprehensive Documentation

GEMVC has extensive documentation to help you understand every aspect of the framework. **All documentation files (.md) are important** and provide different perspectives on GEMVC:

### 📖 Core Documentation Files

#### 🏗️ [ARCHITECTURE.md](ARCHITECTURE.md) - Complete Architecture Overview
**Learn**: Overall framework structure, component breakdown, design patterns, request flow
- Directory structure and component organization
- Webserver-agnostic architecture
- Automatic security features
- Request flow for Apache and OpenSwoole
- Performance optimizations
- Design patterns used

#### 🛠️ [CLI.md](CLI.md) - CLI Commands Reference
**Learn**: All command-line tools, code generation, project management
- Complete CLI command reference
- **Two-package CLI architecture**: [`gemvc/cli-base`](https://github.com/gemvc/cli-base) (foundation) + `gemvc/library` (framework commands)
- How commands extend `Gemvc\CLI\Command` and use `CliColor` / `CliBoxShow`
- How `AbstractInit` uses Template Method pattern (library)
- How `DockerComposeInit` manages Docker services
- Step-by-step examples and troubleshooting
- **CLI-base API for AIs:** `vendor/gemvc/cli-base/AI-Assistant.md`

#### 🗄️ [DATABASE_LAYER.md](DATABASE_LAYER.md) - Database Layer Guide
**Learn**: Table layer, schema definition, type mapping, migrations
- How all table classes must extend `Table`
- Understanding `$_type_map` property
- Using `defineSchema()` for constraints
- Property mapping and visibility rules
- Schema constraints (primary, unique, foreign keys, indexes)
- Complete examples and best practices

#### 🌐 [HTTP_REQUEST_LIFE_CYCLE.md](HTTP_REQUEST_LIFE_CYCLE.md) - Request Handling
**Learn**: Server-agnostic HTTP request handling, adapters, unified Request object
- How server adapters (`ApacheRequest`, `SwooleRequest`) work
- Unified `Request` object design
- Request life cycle for Apache and OpenSwoole
- Automatic input sanitization
- Response handling abstraction
- Security features in request processing

#### 🎨 [TEMPLATE_SYSTEM.md](TEMPLATE_SYSTEM.md) - Customizable Templates
**Learn**: Code generation templates, customization, template variables
- How templates are copied during `gemvc init`
- Template lookup priority (project vs vendor)
- Available templates (service, controller, model, table)
- Template variables and replacement
- Customization examples
- Best practices for template management

#### 🔒 [SECURITY.md](SECURITY.md) - Security Features
**Learn**: Security architecture, attack prevention, automatic security
- Multi-layer security architecture
- 90% automatic security coverage
- Input sanitization (XSS prevention)
- SQL injection prevention
- Header sanitization
- File security (MIME, signature, encryption)
- JWT authentication/authorization
- Schema validation for mass assignment prevention

#### 📊 [GEMVC_APM_INTEGRATION.md](GEMVC_APM_INTEGRATION.md) - APM Integration Guide
**Learn**: Application Performance Monitoring, tracing, performance insights
- Automatic request tracing setup
- Controller and database query tracing
- Environment variable configuration
- Custom tracing with ApmTracingTrait
- Performance optimization and sample rates
- Troubleshooting and best practices

### 📑 Additional Documentation

- **[CHANGELOG.md](CHANGELOG.md)** - Version history and notable changes
- **[RELEASE_NOTES.md](RELEASE_NOTES.md)** - Release overviews and migration guides (latest stable: **5.8.0**)
- **[SECURITY.md](SECURITY.md)** - Detailed security features and best practices
- **[LICENSE](LICENSE)** - License information

### 💡 Learning Resources

- **Example Code**: `src/startup/common/init_example/` - Complete examples including User, Apm, GemvcAssistant, GemvcMonitoring
- **Template Examples**: `templates/cli/` - Default code generation templates
- **Stubs**: `src/stubs/` - IDE type stubs for OpenSwoole and Redis
- **Helper Classes**: `src/helper/` - ServerMonitorHelper, NetworkHelper, and other utilities

### 🎯 Quick Navigation for AI Assistants

**To understand GEMVC architecture:**
1. Start with [README.MD](README.MD) (this file) for overview
2. Read [ARCHITECTURE.md](ARCHITECTURE.md) for deep dive
3. Study `src/startup/common/init_example/` for code examples

**To understand CLI commands:**
1. Read [CLI.md](CLI.md) for framework commands (`init`, `create:*`, `db:*`)
2. Read `vendor/gemvc/cli-base/AI-Assistant.md` for `Command`, `CliColor`, `FileSystemManager`, generators
3. Understand Template Method pattern in `AbstractInit` (library)

**To understand database layer:**
1. Read [DATABASE_LAYER.md](DATABASE_LAYER.md) for Table layer guide
2. Learn about `$_type_map` and `defineSchema()`
3. Understand how all table classes extend `Table`

**To understand HTTP request handling:**
1. Read [HTTP_REQUEST_LIFE_CYCLE.md](HTTP_REQUEST_LIFE_CYCLE.md)
2. Learn about server adapters (`ApacheRequest`, `SwooleRequest`)
3. Understand unified `Request` object

**To understand template system:**
1. Read [TEMPLATE_SYSTEM.md](TEMPLATE_SYSTEM.md)
2. Learn template customization
3. Understand template variable replacement

**To understand security:**
1. Read [SECURITY.md](SECURITY.md)
2. Learn about automatic security features
3. Understand attack prevention mechanisms

**To understand APM integration:**
1. Read [GEMVC_APM_INTEGRATION.md](GEMVC_APM_INTEGRATION.md) for complete guide
2. Learn about automatic tracing and environment controls
3. Understand trace context propagation
4. Study `src/startup/common/init_example/api/Apm.php` for examples

---

## 🆘 Need Help?

- 📖 Read [ARCHITECTURE.md](ARCHITECTURE.md) for detailed architecture
- 🔒 Read [SECURITY.md](SECURITY.md) for security features
- 🎨 Read [TEMPLATE_SYSTEM.md](TEMPLATE_SYSTEM.md) for template customization
- 💡 Study `src/startup/common/init_example/` for code examples

---

## 🎯 Code Quality with PHPStan Level 9

GEMVC is built with **PHPStan Level 9** (the highest level!) and **you should use it too!**

### Why PHPStan Level 9?

**PHPStan Level 9** catches:
- ✅ Type errors before runtime
- ✅ Null pointer exceptions
- ✅ Undefined method calls
- ✅ Incorrect array access
- ✅ Type mismatches
- ✅ Missing return types
- ✅ And much more!

### Setup PHPStan in Your Project

**During `gemvc init`**, you'll be asked if you want to install PHPStan. Say **YES!**

Or install manually:
```bash
composer require --dev phpstan/phpstan
```

### Run PHPStan Analysis

```bash
# Recommended (library: analyzes src/ only)
composer phpstan

# Equivalent
vendor/bin/phpstan analyse -c phpstan.neon
```

In a **generated project** after `gemvc init`, use `vendor/bin/phpstan analyse` with the copied `phpstan.neon` (typically `src/` or `app/` paths as configured).

### Example: PHPStan Catches Bugs

**Without PHPStan** (bugs in production):
```php
public function getUser($id)
{
    return $this->selectById($id)->name;  // ❌ Might be null!
}
```

**With PHPStan Level 9** (caught at development):
```php
public function getUser(int $id): ?UserModel
{
    $user = $this->selectById($id);
    if (!$user) {
        return null;
    }
    return $user;  // ✅ Type-safe!
}
```

### Benefits for Your Code

1. **Type Safety**: Catch errors before they happen
2. **Better IDE Support**: Auto-completion, refactoring
3. **Cleaner Code**: Forces you to write explicit types
4. **Fewer Bugs**: Static analysis catches issues early
5. **Team Consistency**: Everyone writes code the same way

### GEMVC's PHPStan Configuration

GEMVC includes:
- ✅ Level 9 configuration (highest level)
- ✅ OpenSwoole stubs for proper type checking
- ✅ Redis stubs for connection type safety
- ✅ Pre-configured `phpstan.neon` file (`src/` in the library repo; `tests/` excluded)
- ✅ `composer phpstan` script in `gemvc/library`

**Use PHPStan Level 9** - Write clean, type-safe, bug-free code! 🎯

---
## Links

- [🤖 FOR AI ASSISTANTS - READ THIS FIRST!](GEMVC_GUID.md)
- [📦 Installation Guide](INSTALLATION.md) ⭐ **Start Here!**
- [What is GEMVC?](GEMVC.md)
- [Architecture](ARCHITECTURE.md)
---
## 📄 License

MIT License bei Ali Khorsandfard gemvc.de(https://www.gemvc.de)


---

**Built with ❤️ for developers who want simplicity, security, and performance.**


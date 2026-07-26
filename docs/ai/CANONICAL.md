# GEMVC Canonical Guide for AI Assistants

Framework hub: **gemvc/library 5.9.1**.  
**GEMVC is an ecosystem** of Composer packages under `vendor/gemvc/` — not Laravel, not Symfony, not a single monolith.

**Before inventing helpers, DB pools, APM, HTTP clients, or CLI codegen**, read **[guides/ecosystem.md](../guides/ecosystem.md)** and the package’s own `vendor/gemvc/<pkg>/README.md`.

---

## Ecosystem (short)

| Package | Job |
|---------|-----|
| `gemvc/library` | Framework: Bootstrap, ApiService, Table, Request, `bin/gemvc` |
| `gemvc/helper` | TypeChecker, CryptHelper, ProjectHelper, File/Image helpers |
| `gemvc/connection-contracts` | DB interfaces |
| `gemvc/connection-pdo` | PDO connections (Apache/Nginx/CLI); MySQL/Postgres/SQLite |
| `gemvc/connection-openswoole` | OpenSwoole **pooled** connections |
| `gemvc/apm-contracts` | ApmInterface / ApmFactory |
| `gemvc/apm-tracekit` | TraceKit provider (default APM) |
| `gemvc/http-client` | Outbound sync/async HTTP |
| `gemvc/cli-base` | CLI Command foundation |
| `gemvc/cli-dev` | **require-dev**: `create:*`, `db:list|describe|…`, `admin:*` |

Apps install **`composer require gemvc/library`**; most packages arrive as dependencies. Install **`cli-dev`** only for codegen.

---

## 4-layer architecture (mandatory)

```
API (app/api/)           → schema validation, auth, thin
Controller (app/controller/) → orchestration, map request → model
Model (app/model/)       → business rules, transforms, domain ops; may return `JsonResponse` **or** PHP types (Controller then builds response) — [guides/model.md](../guides/model.md)
Table (app/table/)       → DB only (extends Table)
```

**Naming**

| Layer | File | Class |
|-------|------|-------|
| API | `User.php` | `User extends ApiService` (or `SwooleApiService`) |
| Controller | `UserController.php` | `UserController extends Controller` |
| Model | `UserModel.php` | `UserModel extends UserTable` |
| Table | `UserTable.php` | `UserTable extends Table` |

**URL**: `/api/{Service}/{method}` → `App\Api\User::create()`

**Properties**

- `_prefix` → ignored in INSERT/UPDATE (aggregations: `$_profile`, `$_orders`)
- `protected` → not selected into public payloads (e.g. password)
- Property names = column names exactly

---

## Apache/Nginx vs OpenSwoole

| | `ApiService` | `SwooleApiService` |
|--|--------------|-------------------|
| Bootstrap | `Bootstrap` (may `die`) | `SwooleBootstrap` (return responses) |
| APM helpers | `callController()`, magic `$this->UserController` | **No** — call controllers manually |
| Validation fail | often throws / dies | return `?JsonResponse` |
| Auth whole service | `requireAuth()` in constructor | same |

Use the matching base class for the target server.

---

## Authentication (5.9.1)

### Prefer service-wide guard

```php
class User extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->requireAuth(['admin']); // throws AuthException → 401 or 403
    }

    public function create(): JsonResponse { /* no per-method auth */ }
}
```

- `requireAuth(null)` or `requireAuth([])` → any authenticated user
- `requireAuth(['admin','editor'])` → must have one of these roles
- Throws `Gemvc\Core\AuthException` — Bootstrap converts to JSON; method body never runs if called from constructor

### Per-method (still valid)

```php
if (!$this->request->auth(['admin'])) {
    return $this->request->returnResponse();
}
```

### Status codes

| Situation | HTTP |
|-----------|------|
| No token / cannot extract `Authorization` | **401** Unauthorized |
| Token present but invalid (bad signature, expired, …) | **403** Forbidden |
| Valid token, wrong role | **403** Forbidden |

---

## Request / Response patterns

```php
// Schema (? = optional). Unlisted fields rejected.
$this->request->definePostSchema([
    'name' => 'string',
    'email' => 'email',
    'price' => 'decimal',
    '?phone' => 'string',
]);
$this->request->validateStringPosts(['name' => '2|100', 'password' => '8|128']);

$this->request->findable(['name' => 'string']);
$this->request->sortable(['id', 'name', 'created_at']);

$model = $this->request->mapPostToObject($model, [
    'email' => 'email',
    'name' => 'name',
    'password' => 'setPassword()',
]);
```

**Schema types** (via `gemvc/helper` TypeChecker):  
`string`, `int`, `float`, `bool`, `email`, `array`, `json`, `jsonb`, `date`, `datetime`, `url`, `ip`, `ipv4`, `ipv6`,  
`decimal` / `decimal:p,s`, `hex`, `uuid`, `slug`, `positive_int`, `timestamp`

**Decimal / money** — use `string` properties, never `float`:

```php
public string $price;
protected array $_type_map = ['price' => 'decimal']; // or 'decimal:12,4'
$price = $this->request->decimalValuePost('price'); // string|false
```

**Responses**

```php
Response::success($data, $count, $msg);       // 200
Response::created($data, $count, $msg);       // 201
Response::updated($result, $count, $msg);     // 209
Response::deleted($result, $count, $msg);     // 210
Response::badRequest($msg);                   // 400
Response::unauthorized($msg);                 // 401
Response::forbidden($msg);                    // 403
Response::notFound($msg);                     // 404
Response::unprocessableEntity($msg);          // 422
Response::internalError($msg);                // 500
```

---

## Full CRUD pattern (with APM hooks)

Model method details: [guides/model.md](../guides/model.md).

```php
// API
public function create(): JsonResponse {
    if (!$this->request->definePostSchema([
        'name' => 'string', 'email' => 'email', 'password' => 'string',
    ])) {
        return $this->request->returnResponse();
    }
    return $this->callController(new UserController($this->request))->create();
}

// Controller
public function create(): JsonResponse {
    $model = $this->createModel(new UserModel());
    $model = $this->request->mapPostToObject($model, [
        'email' => 'email', 'name' => 'name', 'password' => 'setPassword()',
    ]);
    if (!$model instanceof UserModel) {
        return $this->request->returnResponse();
    }
    return $model->createModel();
}

// Model
public function createModel(): JsonResponse {
    if ($this->selectByEmail($this->email)) {
        return Response::unprocessableEntity('User already exists');
    }
    $this->insertSingleQuery();
    if ($this->getError()) {
        return Response::internalError($this->getError());
    }
    return Response::created($this, 1, 'User created successfully');
}

// Table
public function selectByEmail(string $email): null|static {
    $arr = $this->select()->where('email', $email)->limit(1)->run();
    return $arr[0] ?? null;
}
```

**Style note:** Models may return PHP types instead of `JsonResponse`; then Controller maps to `Response::*`. See [guides/model.md — Return style](../guides/model.md#return-style-jsonresponse-vs-php-types).

List:

```php
// API
$this->request->findable(['name' => 'string', 'email' => 'email']);
$this->request->sortable(['id', 'name', 'created_at']);
return $this->callController(new UserController($this->request))->list();

// Controller
return $this->createList($this->createModel(new UserModel()), 'id,name,email,created_at');
```

Full list params / `createList` vs columns: [guides/controller.md](../guides/controller.md).

Magic controller access (ApiService only): `$this->UserController->create()` (same as `callController`).

---

## Table / database

Extend `Table`. **You do not manage pooling, PDO, or row hydration** — Table + `connection-pdo` / `connection-openswoole` (via `DatabaseManagerFactory`) do that. Same Table code on Apache, Nginx, and OpenSwoole. Details: [database.md](../guides/database.md).

```php
class UserTable extends Table {
    public int $id;
    public string $name;
    public ?string $description;
    protected string $password;

    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'description' => 'string',
        'password' => 'string',
    ];

    public function getTable(): string { return 'users'; }

    public function defineSchema(): array {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            Schema::unique('email'),
            Schema::index('name'),
        ];
    }
}

$this->select('id,name')
    ->whereEqual('id', $id)
    ->whereLike('name', '%x%')
    ->whereIn('status', ['active', 'pending'])
    ->orderBy('name', true)   // true = ASC; false or null = DESC
    ->limit(10)
    ->run();

$this->insertSingleQuery();
$this->updateSingleQuery();
$this->deleteByIdQuery($id);  // int|string id → returns id or null
```

**Primary key (runtime):** default `id` (int). For UUID/string columns call `$this->setPrimaryKey('uuid', 'uuid')` after `parent::__construct()` and match `Schema::primary(...)`. See [database.md — Primary keys](../guides/database.md#primary-keys-ddl--runtime).

**Soft delete** (when table has `deleted_at` / soft-delete columns):

```php
$this->safeDeleteQuery();  // soft delete
$this->restoreQuery();     // restore
```

**Multi-DB** (`DB_DRIVER=mysql|pgsql|sqlite`): DSN from connection packages; dialects auto-selected for `db:migrate`.  
Limitations: SQLite cannot ALTER column type/null/default without rebuild; no FULLTEXT on Postgres/SQLite.

**Complex reads:** prefer a **SQL VIEW** + Table class on the view (`getTable()` = view name) instead of JOINs in PHP — [database.md — SQL views](../guides/database.md#sql-views-as-tables-recommended).

---

## CLI

**Bundled in library**

```bash
gemvc init --swoole|--apache|--nginx [--db=mysql|postgres|sqlite]
gemvc db:migrate UserTable
```

**Dev commands** — require `composer require --dev gemvc/cli-dev`:

```bash
gemvc create:crud Product
gemvc db:init | db:list | db:describe | db:drop | db:unique
gemvc admin:setadmin
```

Do not assume `create:*` exists without cli-dev.

---

## APM (optional)

```env
APM_NAME=TraceKit
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

- Root span: Bootstrap (automatic)
- Controllers: use `callController()`
- DB: use `createModel()`

---

## Security

Automatic: path protection, header/input sanitization, prepared statements.  
Developer must: `define*Schema()`, `auth()` / `requireAuth()`, hash passwords (`CryptHelper` / `setPassword()`).

---

## Auto API documentation

```php
/**
 * @http POST
 * @description Create user
 * @example /api/User/create
 */
public function create(): JsonResponse { ... }

public static function mockResponse(string $method): array { ... }
```

Visit `/api/index/document`.

---

## DO

- Extend `ApiService` / `SwooleApiService`, `Controller`, `Table`
- Use `callController` + `createModel` on Apache/Nginx path
- Use `_` for relations; `protected` for secrets
- PHPStan Level 9 types; nullable returns with null checks
- Match `$_type_map` to columns

## DON'T

- Laravel routes / Eloquent / magic relations
- Skip layers or invent routes files
- Manual sanitization or string-concat SQL
- `float` for money
- Copy `callController` / magic `$this->XController` into `SwooleApiService` subclasses without checking — those helpers are on `ApiService` only

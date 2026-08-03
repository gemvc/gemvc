# GEMVC Canonical Guide for AI Assistants

Framework hub: **gemvc/library 5.14.0**.
**GEMVC is an ecosystem** of Composer packages under `vendor/gemvc/` — not Laravel, not Symfony, not a single monolith.

---

## Ecosystem (short)

| Package | Job |
|---------|-----|
| `gemvc/library` | Framework hub: Bootstrap, ApiService, Table, ViewTable, Request, `bin/gemvc` |
| **`gemvc/helper`** | **Core:** TypeChecker, CryptHelper, ProjectHelper, File/Image — [guides/helper.md](../guides/helper.md) |
| **`gemvc/http-client`** | **Core:** outbound sync/async HTTP — [guides/http-client.md](../guides/http-client.md) |
| `gemvc/connection-contracts` | DB interfaces |
| `gemvc/connection-pdo` | PDO connections (Apache/Nginx/FrankenPHP classic/CLI); MySQL/Postgres/SQLite |
| `gemvc/connection-openswoole` | OpenSwoole **pooled** connections |
| `gemvc/apm-contracts` | ApmInterface / ApmFactory |
| `gemvc/apm-tracekit` | TraceKit provider (default APM) |
| `gemvc/cli-base` | CLI Command foundation |
| `gemvc/cli-dev` | **require-dev**: `create:*`, `db:list|describe|…`, `admin:*` |

Apps install **`composer require gemvc/library`**; **helper** and **http-client** arrive as dependencies. Install **`cli-dev`** only for codegen.

**Before inventing** validators, crypto, curl wrappers, DB pools, APM, or CLI codegen — read **[guides/ecosystem.md](../guides/ecosystem.md)** and `vendor/gemvc/<pkg>/README.md`.

---

## 4-layer architecture (strongly recommended)

```
API (app/api/)           → schema validation, auth, thin — [guides/api.md](../guides/api.md)
Controller (app/controller/) → orchestration, map request → model — [guides/controller.md](../guides/controller.md)
Model (app/model/)       → business rules / workflows; Table-backed (`extends XTable`) **or** composition (plain class + other Models); may return `JsonResponse` **or** PHP types — [guides/model.md](../guides/model.md)
Table (app/table/)       → DB only: `extends Table` (physical) or `extends ViewTable` (SQL view) — [guides/database.md](../guides/database.md)
```

The stack is **not** hard-enforced by the framework: you can call a Model from API, or put SQL in a Controller, and requests will still run. That is **strongly discouraged**. Use all four layers for HTTP services unless you have an exceptional, deliberate reason not to. Flexibility belongs *inside* each layer (e.g. composition Models, return styles) — not in skipping layers.

**Naming**

| Layer | File | Class |
|-------|------|-------|
| API | `User.php` | `User extends ProtectedApiService` (auth) or `ApiService` (public) — **all** servers; `Swoole*` names deprecated |
| Controller | `UserController.php` | `UserController extends Controller` |
| Model | `UserModel.php` | `UserModel extends UserTable` **or** composition class (no Table) |
| Table | `UserTable.php` | `UserTable extends Table` |
| View | `UserAccessTable.php` | `UserAccessTable extends ViewTable` |

**URL (Apache/Nginx/FrankenPHP classic):** `/api/{Service}/{method}` → `App\Api\User::create()`  
**OpenSwoole:** path segments come from `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (defaults `1` / `2`) — there is no automatic `api` hop; configure sections so `{Service}` / `{method}` land correctly (see [architecture.md](../guides/architecture.md)).

**Properties**

- `_prefix` → ignored in INSERT/UPDATE (aggregations: `$_profile`, `$_orders`)
- `protected` → still a DB column; excluded from typical list/API payloads / `createList` defaults (prefer explicit columns). Not the same as “absent from SQL SELECT *”.
- Property names = column names exactly

---

## Runtimes (Apache / Nginx / FrankenPHP / OpenSwoole)

| | Apache / Nginx / FrankenPHP classic | FrankenPHP worker | OpenSwoole |
|--|-------------------------------------|-------------------|------------|
| Recommended API base | `ApiService` / `ProtectedApiService` | **same** | **same** |
| Deprecated aliases | — | — | `SwooleApiService` → `ApiService`; `ProtectedSwooleApiService` → `ProtectedApiService` |
| Bootstrap | `Bootstrap` (may `die`) | `FrankenPhpBootstrap` (no `die`) | `SwooleBootstrap` (no `die`) |
| Request adapter | `StandardHttpRequest` (deprecated: `ApacheRequest`) | `StandardHttpRequest` | `SwooleRequest` |
| Edge path deny | Apache: `.htaccess` · Nginx: `nginx.conf` · FrankenPHP: **`Caddyfile`** | Caddyfile + `SecurityManager` | `SecurityManager` |
| DB | `connection-pdo` | `connection-pdo` | `connection-openswoole` |
| Shared helpers | `ApiServiceSharedTrait` | inherited | inherited |
| `validatePosts` | throws `ValidationException` | same | same; legacy return → `safeValidatePosts()` on deprecated `SwooleApiService` only |

Usual schema API: `definePostSchema()` / `defineGetSchema()` → `bool` + `return $this->request->returnResponse()`. Throw helpers: `validateOrFail()` / `validateStringOrFail()`.

Prefer **`ApiService` / `ProtectedApiService`** on every server.

**OpenSwoole isolation, pooling, no-`die()`, production FAQ:** [openswoole.md](../guides/openswoole.md).  
**FrankenPHP classic + worker:** [frankenphp.md](../guides/frankenphp.md).

---

## Authentication (5.9.1)

### Prefer protected base (authenticated CRUD)

```php
class User extends ProtectedApiService  // all servers including OpenSwoole
{
    public function __construct(Request $request)
    {
        parent::__construct($request, ['admin']); // throws AuthException → 401 or 403
    }

    public function create(): JsonResponse { /* no per-method auth */ }
}
```

- `parent::__construct($request, null)` or `[]` → any authenticated user
- `parent::__construct($request, ['admin','editor'])` → must have one of these roles
- Public endpoints (login, register, health): `extends ApiService`
- Deprecated: `ProtectedSwooleApiService` / `SwooleApiService`
### Or call `requireAuth()` on a public base

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

### Rate limit

**Global — automatic (no code):** if `.env` sets `REQUEST_RATE_LIMIT_PER_SEC` to a positive int, **every** request is limited by `Bootstrap` / `SwooleBootstrap` via `RateLimiter::enforceFromEnv()`. Unset or `0` = off. `DRIVER=none` disables this path and default `requireRateLimit()`.

```env
REQUEST_RATE_LIMIT_DRIVER=apcu
REQUEST_RATE_LIMIT_PER_SEC=20
REQUEST_RATE_LIMIT_BLOCK_SECONDS=60
REQUEST_RATE_LIMIT_SCOPE=both|ip|token
REQUEST_RATE_LIMIT_FAIL_MODE=closed
# REDIS_* when DRIVER=redis or both
```

Drivers: `apcu` | `redis` | `both` (simultaneous dual check, **not** failover) | `none`. **No automatic Redis↔APCu fallback.** Unavailable chosen backend(s) → `FAIL_MODE` only.

**Per-service / method (optional DX):**

```php
$this->requireRateLimit();                  // uses REQUEST_RATE_LIMIT_DRIVER
$this->requireRateLimit(10, 'ip');
$this->requireRateLimitApcu(30, 'ip');      // force APCu
$this->requireRateLimitRedis(5, 'ip', 120); // force Redis
$this->requireRateLimitBoth(10);            // force dual check
```

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

**Concurrent balance transfers:** Model on one Table instance — `beginTransaction()` → `select(…)->whereIn(…)->orderBy('id')->forUpdate()->run()` → BCMath → `$this->updateSingleQuery()` (not on hydrated rows) → `commit()` / `rollback()`. **Never** `DatabaseManagerFactory…->getPdo()`. See [model.md — Atomic money transfers](../guides/model.md#atomic-money-transfers-pessimistic-lock).

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

---

## Lists — flagship (`createList`)

**Powerful and intentional:** API allowlists type-check GET params; Controller `createList()` applies filter / LIKE / sort / pagination, selects columns, returns JSON + total count, and wires APM via `createModel()`. Unlisted filter fields never become SQL.

```php
// API
$this->request->findable(['name' => 'string', 'email' => 'email']);      // GET find_like=
$this->request->filterable(['role' => 'string']);                       // GET filter_by=
$this->request->sortable(['id', 'name', 'created_at']);                 // GET sort_by / sort_by_asc
return $this->callController(new UserController($this->request))->list();

// Controller — prefer explicit columns
return $this->createList($this->createModel(new UserModel()), 'id,name,email,created_at');
```

| GET | Allowlist | Applies |
|-----|-----------|---------|
| `find_like=` | `findable` | `whereLike` |
| `filter_by=` | `filterable` | `where` (exact) |
| `sort_by` / `sort_by_asc` | `sortable` | `orderBy` |
| `page_number` | — | `setPage` + `getTotalCounts()` |

Full params / `createList` vs `listJsonResponse` / column rules: [guides/controller.md](../guides/controller.md#lists-createlist) · [guides/api.md](../guides/api.md#list-allowlists).

Magic controller access (both bases): `$this->UserController->create()` (same as `callController`).

---

## Table / database

Extend `Table` (physical) or `ViewTable` (SQL view). **You do not manage pooling, PDO, or row hydration** — Table + `connection-pdo` / `connection-openswoole` (via `DatabaseManagerFactory`) do that. Same Table/`ViewTable` code on Apache, Nginx, and OpenSwoole. Details: [database.md](../guides/database.md).

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
            // Schema::primary / autoIncrement are NOT migrate DDL today — PK from property `id`
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

**Primary key (runtime):** default `id` (int). Prefer `public int $id` so migrate creates PK/AI. For UUID/string identity call `$this->setPrimaryKey('uuid', 'uuid')` after `parent::__construct()` — that is **ORM only**; `Schema::primary(...)` is **not** applied as DDL by current migrate. See [database.md — Primary keys](../guides/database.md#primary-keys-ddl-runtime).

**Soft delete** (when table has `deleted_at` / soft-delete columns):

```php
$this->safeDeleteQuery();  // soft delete
$this->restoreQuery();     // restore
```

**Multi-DB** (`DB_DRIVER=mysql|pgsql|sqlite`): DSN from connection packages; dialects auto-selected for `db:migrate`.  
Limitations: SQLite cannot ALTER column type/null/default without rebuild (migrate skips); no FULLTEXT on Postgres/SQLite; type map SQL differs by dialect (see [database.md](../guides/database.md)).

**Complex reads — `ViewTable`:** extend `Gemvc\Database\ViewTable` (not a plain `Table`). Flat column props + `$_type_map` match SELECT aliases; `defineView(): string` composes other Tables via `getTable()`; optional `viewDependsOn()` for `--all`. Row insert/update/delete hard-fail. Nest JSON in the Model. Migrate: `gemvc db:migrate YourViewTable` or `--all`. Guide: [database.md — SQL views via ViewTable](../guides/database.md#sql-views-via-viewtable-recommended).

---

## CLI

**Bundled in library**

```bash
gemvc init --swoole|--apache|--nginx [--db=mysql|postgres|sqlite]
gemvc db:migrate UserTable
gemvc db:migrate UserAccessTable   # ViewTable → CREATE OR REPLACE VIEW
gemvc db:migrate --all             # FK order, then views
```

**Dev commands** — require `composer require --dev gemvc/cli-dev`:

```bash
gemvc create:crud Product
gemvc db:init | db:list | db:describe | db:drop | db:unique
gemvc admin:setadmin
```

`db:list` (cli-dev **≥ 1.3**) lists **tables and views**. The Developer UI table list is still **BASE TABLE only**. Do not assume `create:*` exists without cli-dev.

---

## APM (optional)

Built on **`gemvc/apm-contracts`** (`ApmFactory` → `ApmInterface`). TraceKit is one provider (`gemvc/apm-tracekit`), not the framework API.

```env
APM_NAME=TraceKit
APM_SAMPLE_RATE=1.0
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
# Provider-specific keys as needed (e.g. TRACEKIT_API_KEY / TRACEKIT_ENDPOINT)
```

- Root span: Bootstrap (automatic)
- Controllers: use `callController()` on `ApiService` (all servers)
- DB: use `createModel()`
- Details: [guides/apm.md](../guides/apm.md) · `vendor/gemvc/apm-contracts/README.md`

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

- Extend `ApiService` / **`ProtectedApiService`** (all servers), plus `Controller`, `Table` / `ViewTable`. Deprecated: `SwooleApiService` / `ProtectedSwooleApiService`
- Use `callController` + `createModel`
- Use `_` for relations; `protected` for secrets
- PHPStan Level 9 types; nullable returns with null checks
- Match `$_type_map` to columns / view aliases
- Use `ViewTable` + `db:migrate` for SQL views (never migrate a plain `Table` as a view)

## DON'T

- Laravel routes / Eloquent / magic relations / view 1:n nesting in SQL
- Skip layers on normal HTTP services (runtime allows; strongly discouraged) or invent routes files
- Manual sanitization or string-concat SQL
- Point a plain `Table` at a view name and run `db:migrate` (creates a physical table)
- Invent PDO `CREATE VIEW` helpers when `ViewTable` exists
- `float` for money
- Use deprecated `SwooleApiService` for new services — prefer `ApiService` on OpenSwoole too

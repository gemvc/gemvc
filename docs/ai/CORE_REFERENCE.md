# GEMVC Core Reference (AI)

Version **5.12.0**. Compact **framework class signatures** for assistants — `Request`, `Response`, `ApiService`, `Controller`, `Table`, `ViewTable`, schema types.

**Not** HTTP endpoint docs (that is [api-documentation.md](../guides/api-documentation.md) / `/api/index/document`).  
**Not** the `app/api/` layer guide. Prefer this over inventing methods from training data.

**Packages:** GEMVC is an ecosystem — see [guides/ecosystem.md](../guides/ecosystem.md) and `vendor/gemvc/*/README.md` before inventing helpers, DB pools, APM, or CLI.

---

## `Gemvc\Http\Request`

**Properties (sanitized):** `$post`, `$get`, `$put`, `$patch`, `$files`, `$cookies`, `$authorizationHeader`, `$isAuthenticated`, `$isAuthorized`, `$token`, `$apm`, …

```php
definePostSchema(array $schema): bool
defineGetSchema(array $schema): bool
definePutSchema(array $schema): bool
definePatchSchema(array $schema): bool
validateStringPosts(array $rules): bool   // 'field' => 'min|max'

intValueGet(string $key): int|false
intValuePost(string $key): int|false
floatValueGet(string $key): float|false
floatValuePost(string $key): float|false
stringValueGet(string $key): string|false
stringValuePost(string $key): string|false
decimalValueGet(string $key, string $type = 'decimal'): string|false
decimalValuePost(string $key, string $type = 'decimal'): string|false

auth(?array $roles = null): bool          // 401 missing token; 403 invalid token or wrong role
returnResponse(): JsonResponse

findable(array $fields): bool             // LIKE
filterable(array $fields): bool           // exact
sortable(array $fields): bool
setPageNumber(): bool
setPerPage(): bool
getPageNumber(): int
getPerPage(): int

mapPostToObject(object $o, ?array $map = null): object|null
mapPutToObject(object $o, ?array $map = null): object|null
mapPatchToObject(object $o, ?array $map = null): object|null
// Manual map: key = request field name (= object property for non-method maps).
// Value ending in () → call that method with the field value.
// Otherwise value is ignored; property name is the map key (e.g. 'email' => 'email').
```

**Schema type strings:**  
`string`, `int`, `integer`, `float`, `number`, `bool`, `boolean`, `email`, `array`, `json`, `jsonb`, `date`, `datetime`, `url`, `ip`, `ipv4`, `ipv6`, `decimal`, `decimal:10,2`, `hex`, `uuid`, `slug`, `positive_int`, `timestamp`  
Prefix `?` for optional: `'?phone' => 'string'`.

---

## `Gemvc\Http\Response` / `JsonResponse`

```php
Response::success($data, ?int $count = null, ?string $msg = null): JsonResponse      // 200
Response::created(...): JsonResponse   // 201
Response::updated(...): JsonResponse   // 209
Response::deleted(...): JsonResponse   // 210
Response::successButNoContentToShow(...): JsonResponse  // 204
Response::badRequest(?string $msg): JsonResponse           // 400
Response::unauthorized(?string $msg): JsonResponse         // 401
Response::forbidden(?string $msg): JsonResponse            // 403
Response::notFound(?string $msg): JsonResponse             // 404
Response::conflict(?string $msg): JsonResponse             // 409
Response::unprocessableEntity(?string $msg): JsonResponse  // 422
Response::tooManyRequests(?string $msg): JsonResponse      // 429
Response::internalError(?string $msg): JsonResponse        // 500
$response->show();           // Apache/Nginx
$response->showSwoole($swooleResponse);
```

---

## `Gemvc\Core\ApiService`

Public / optional-auth endpoints (login, register, health). For authenticated CRUD prefer {@see ProtectedApiService}.
Shares `ApiServiceSharedTrait` with `SwooleApiService`.

```php
public function __construct(Request $request)
public function requireAuth(?array $roles = []): void   // throws AuthException
public function requireRateLimit(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void  // global DRIVER; throws RateLimitException
public function requireRateLimitApcu(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
public function requireRateLimitRedis(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
public function requireRateLimitBoth(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
protected function callController(Controller $c): ControllerTracingProxy
// Magic: $this->UserController → ControllerTracingProxy
public function index(): JsonResponse
protected function validateOrFail(array $schema): void          // throws ValidationException (preferred throw helper)
protected function validateStringOrFail(array $schema): void    // throws ValidationException
protected function validatePosts(array $schema): void           // throws; same as validateOrFail on this class
public static function mockResponse(string $method): array
```

## `Gemvc\Core\ProtectedApiService`

Extends `ApiService`. Constructor **always** calls `requireAuth($roles)` — use for authenticated services.

```php
public function __construct(Request $request, ?array $roles = null)  // throws AuthException
// null|[] = any authenticated user; ['admin'] = role gate
```

```php
class User extends ProtectedApiService {
    public function __construct(Request $request) {
        parent::__construct($request, ['admin']);
    }
}
```

## `Gemvc\Core\SwooleApiService`

Public / optional-auth on OpenSwoole. For authenticated CRUD prefer {@see ProtectedSwooleApiService}.
Shares `ApiServiceSharedTrait` with `ApiService` (`requireAuth`, rate limits, `callController`).

```php
public function requireAuth(?array $roles = []): void
public function requireRateLimit(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
public function requireRateLimitApcu(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
public function requireRateLimitRedis(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
public function requireRateLimitBoth(int $perSec = 20, string $scope = 'both', int $blockSeconds = 60): void
protected function callController(Controller $c): ControllerTracingProxy  // same as ApiService
protected function validateOrFail(array $schema): void              // throws; preferred (SwooleBootstrap → 400)
protected function validateStringOrFail(array $schema): void        // throws
protected function validatePosts(array $schema): ?JsonResponse      // legacy return style
protected function validateStringPosts(array $schema): ?JsonResponse
protected function safeValidatePosts(array $schema): ?JsonResponse  // alias of validatePosts
```

## `Gemvc\Core\ApiServiceSharedTrait`

Internal trait used by `ApiService` and `SwooleApiService`: `requireAuth`, `requireRateLimit*`, `callController`, magic `__get` controllers.
## `Gemvc\Core\ProtectedSwooleApiService`

Extends `SwooleApiService`. Same auth-in-constructor contract as `ProtectedApiService`.

```php
public function __construct(Request $request, ?array $roles = null)  // throws AuthException
```

## `Gemvc\Core\AuthException`

Thrown by `requireAuth()`. Response codes come from `Request::auth()`:
**401** (no token) or **403** (invalid token / wrong role). Caught by `Bootstrap` / `SwooleBootstrap`.

## `Gemvc\Core\RateLimitException` / `RateLimiter`

HTTP **429**. Drivers: `apcu` | `redis` | `both` | `none` via `REQUEST_RATE_LIMIT_DRIVER` (default `apcu`). **No automatic fallback** between stores.

**Global:** `enforceFromEnv()` when `REQUEST_RATE_LIMIT_PER_SEC` > 0. Env also: `BLOCK_SECONDS`, `SCOPE`, `FAIL_MODE`, `DRIVER`. `DRIVER=none` disables Bootstrap + default `requireRateLimit()`.

**Per-service:** `requireRateLimit()` (global driver); overrides `requireRateLimitApcu()` / `requireRateLimitRedis()` / `requireRateLimitBoth()` (still enforce when global is `none`).

**Fail modes:** chosen backend(s) unavailable → fail-closed unless `FAIL_MODE=open`.

## `Gemvc\Core\Controller`

```php
public function __construct(Request $request)
protected function createModel(object $model): object   // wires Request for DB APM
public function createList(object $model, ?string $columns = null): JsonResponse
public function listJsonResponse(object $model, ?string $columns = null): JsonResponse
protected function addError(string $message, int $httpCode = 400): void
public function getErrors(): array
public function hasErrors(): bool
public function clearErrors(): void
```

List GET params (API allowlists first): `find_like`, `filter_by`, `sort_by`, `sort_by_asc`, `page_number`.  
**Flagship:** `createList` applies those allowlists (filter / LIKE / sort / page + total count + APM).  
`createList($model, null)` builds columns from `get_object_vars($model)` (initialized public props only) — **prefer an explicit column list**. Guide: [controller.md](../guides/controller.md#lists-createlist).

App Models are **not** a framework base class. Usual shape: extend your Table (`UserModel extends UserTable`). Also valid: **composition** Models (plain class + other Models) — [model.md](../guides/model.md).

---

## `Gemvc\Database\Table`

```php
abstract public function getTable(): string   // required on every Table subclass
// Convention (not declared on base Table): public function defineSchema(): array
// — used by db:migrate / generators via method_exists; must be public
protected array $_type_map;

select(?string $columns = null): self
where(string $col, mixed $val): self
whereEqual(string $col, mixed $val): self
whereLike(string $col, string $pattern): self
whereIn(string $col, array $vals): self
whereNotIn(string $col, array $vals): self
orderBy(?string $col = null, ?bool $ascending = null): self  // true = ASC; false/null = DESC; null col = PK
limit(int $n): self
noLimit(): self                 // disable pagination LIMIT/OFFSET
all(): self                     // alias of noLimit()
forUpdate(bool $enable = true): self  // SELECT … FOR UPDATE (use inside beginTransaction)
run(): ?array

beginTransaction(): bool
commit(): bool
rollback(): bool

insertSingleQuery(): ?static
updateSingleQuery(): ?static
deleteByIdQuery(int|string $id): int|string|null  // returns deleted id, or null on error

safeDeleteQuery(): ?static    // soft delete
restoreQuery(): ?static

getError(): ?string
setError(?string $error): void

setPrimaryKey(string $column = 'id', string $type = 'int'): self  // int|string|uuid; uuid auto-generates
```

**Schema helpers:** `Schema::unique`, `index`, `foreignKey`, `check`, `fullText` (MySQL). `primary` / `autoIncrement` exist in the API but **migrate does not emit PK DDL from them** — prefer property `id`.

**Primary keys:** create-table PK from property **`id`**; runtime ORM via `setPrimaryKey` — see [database.md](../guides/database.md#primary-keys-ddl-runtime).

**Transactions / money:** `beginTransaction` + `forUpdate` + updates on **`$this`** (not hydrated rows). Guide: [model.md — Atomic money transfers](../guides/model.md#atomic-money-transfers-pessimistic-lock).

**Dialects:** `DialectResolver::resolve(PDO)` → Mysql / Postgres / Sqlite for migrations (incl. view DDL).

---

## `Gemvc\Database\ViewTable`

Extends `Table`. SQL **VIEW** read models — migrate creates/replaces a view, never a physical table from props.

```php
abstract public function defineView(): string          // SELECT body; aliases = public props
public function viewDependsOn(): array                // list<class-string<Table>> for db:migrate --all
public function createViewQuery(?PDO $pdo = null): bool
public function replaceViewQuery(?PDO $pdo = null): bool
public function dropViewQuery(?PDO $pdo = null): bool
// insert/update/delete / soft-delete → hard-fail (read-only)
```

Also: `ViewGenerator`, `TableMigrateOrder`. Guide: [database.md — ViewTable](../guides/database.md#sql-views-via-viewtable-recommended).

---

## Helpers (`gemvc/helper`)

Core package. Powers schema types, passwords, paths. Guide: [helper.md](../guides/helper.md) · `vendor/gemvc/helper/README.md`.

```php
// TypeChecker — same types as definePostSchema / findable
TypeChecker::check(mixed $type, mixed $value, array $options = []): bool

CryptHelper::hashPassword(string $password): string
CryptHelper::passwordVerify(string $passwordToCheck, string $hash): bool
CryptHelper::encryptString(string $string, string $key): false|string
CryptHelper::decryptString(string $encryptedString, string $key): false|string

// Also: ProjectHelper, FileHelper, ImageHelper, TypeHelper, JsonHelper, …
```

**Do not** invent Laravel Hash/Validator clones — use helper.

---

## HTTP client (`gemvc/http-client`)

**Outbound** HTTP (calling other APIs). Not inbound `Request`. Core package. Guide: [http-client.md](../guides/http-client.md) · `vendor/gemvc/http-client/README.md`.

```php
use Gemvc\Http\Client\HttpClient;
use Gemvc\Http\Client\AsyncHttpClient;

$client = new HttpClient();
$client->get(string $url, array $query = []): string|false
$client->post(string $url, array $data = []): string|false
// put, patch, delete, setTimeouts, setRetries, throwExceptions, …

$async = new AsyncHttpClient();
$async->addGet($id, $url, $query = [])->addPost(...)->executeAll();
$async->fireAndForget();  // non-blocking

// OpenSwoole: SwooleHttpClient (coroutines)
```

Library facades: `Gemvc\Http\ApiCall`, `AsyncApiCall` (wrap http-client).

**Do not** invent curl/Guzzle wrappers for microservice calls.

---

## CLI (library)

```
gemvc init [--swoole|--apache|--nginx] [--db=mysql|postgres|sqlite] [--non-interactive|-n]
gemvc db:migrate TableOrViewClass [--force] [--sync-schema]
gemvc db:migrate --all [--force] [--sync-schema]
```

## CLI (`gemvc/cli-dev`, require-dev)

```
gemvc create:crud|service|controller|model|table …
gemvc db:init|list|describe|drop|unique
gemvc admin:setadmin
gemvc admin:setpassword
```

---

## Env (common)

```env
DB_DRIVER=mysql|pgsql|sqlite
DB_HOST= DB_PORT= DB_NAME= DB_USER= DB_PASSWORD=
TOKEN_SECRET= TOKEN_ISSUER=
QUERY_LIMIT=10
APM_NAME=TraceKit
APM_SAMPLE_RATE=1.0
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
# Provider keys (example TraceKit): TRACEKIT_API_KEY TRACEKIT_ENDPOINT
# Contracts: vendor/gemvc/apm-contracts — providers implement ApmInterface
```

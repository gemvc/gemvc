# GEMVC Core Reference (AI)

Version **5.9.1**. Compact **framework class signatures** for assistants — `Request`, `Response`, `ApiService`, `Controller`, `Table`, schema types.

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
Response::internalError(?string $msg): JsonResponse        // 500
$response->show();           // Apache/Nginx
$response->showSwoole($swooleResponse);
```

---

## `Gemvc\Core\ApiService`

```php
public function __construct(Request $request)
public function requireAuth(?array $roles = []): void   // throws AuthException
protected function callController(Controller $c): ControllerTracingProxy
// Magic: $this->UserController → ControllerTracingProxy
public function index(): JsonResponse
protected function validatePosts(array $schema): void
public static function mockResponse(string $method): array
```

## `Gemvc\Core\SwooleApiService`

```php
public function requireAuth(?array $roles = []): void
protected function validatePosts(array $schema): ?JsonResponse
protected function validateStringPosts(array $schema): ?JsonResponse
// No callController / magic controllers — instantiate Controller yourself
```

## `Gemvc\Core\AuthException`

Thrown by `requireAuth()`. Response codes come from `Request::auth()`:
**401** (no token) or **403** (invalid token / wrong role). Caught by `Bootstrap` / `SwooleBootstrap`.

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
run(): ?array

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

**Primary keys:** create-table PK from property **`id`**; runtime ORM via `setPrimaryKey` — see [database.md](../guides/database.md#primary-keys-ddl--runtime).

**Dialects:** `DialectResolver::resolve(PDO)` → Mysql / Postgres / Sqlite for migrations.

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
gemvc db:migrate TableClass [--force] [--sync-schema]
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

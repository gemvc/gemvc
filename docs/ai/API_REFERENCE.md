# GEMVC API Reference (AI)

Version **5.9.1**. Prefer this over scattered legacy notes.

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
stringValueGet(string $key): ?string
stringValuePost(string $key): ?string
decimalValueGet(string $key, string $type = 'decimal'): string|false
decimalValuePost(string $key, string $type = 'decimal'): string|false

auth(?array $roles = null): bool          // sets response 401 or 403 on failure
returnResponse(): JsonResponse

findable(array $fields): bool             // LIKE
filterable(array $fields): bool           // exact
sortable(array $fields): bool
setPageNumber(): bool
setPerPage(): bool
getPageNumber(): int
getPerPage(): int

mapPostToObject(object $o, ?array $map = null): object|null
mapPutToObject(object $o, ?array $map = null): object|false
mapPatchToObject(object $o, ?array $map = null): object|null
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
Response::badRequest(?string $msg): JsonResponse           // 400
Response::unauthorized(?string $msg): JsonResponse         // 401
Response::forbidden(?string $msg): JsonResponse            // 403
Response::notFound(?string $msg): JsonResponse             // 404
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

Thrown by `requireAuth()`. Codes: **401** or **403**. Caught by `Bootstrap` / `SwooleBootstrap`.

## `Gemvc\Core\Controller`

```php
public function __construct(Request $request)
protected function createModel(object $model): object   // wires Request for DB APM
protected function createList(Table $model): JsonResponse
```

---

## `Gemvc\Database\Table`

```php
public function getTable(): string
public function defineSchema(): array
protected array $_type_map;

select(?string $columns = null): static
where($col, $val): static
whereEqual(string $col, mixed $val): static
whereLike(string $col, string $pattern): static
whereIn(string $col, array $vals): static
whereNotIn(string $col, array $vals): static
orderBy(string $col, bool $asc = true): static
limit(int $n): static
run(): ?array

insertSingleQuery(): ?static
updateSingleQuery(): ?static
deleteByIdQuery(int $id): bool

safeDeleteQuery(): ?static    // soft delete
restoreQuery(): ?static

getError(): ?string
setError(string $msg): void
```

**Schema helpers:** `Schema::primary`, `autoIncrement`, `unique`, `index`, `foreignKey`, `check`, `fullText` (MySQL).

**Dialects:** `DialectResolver::resolve(PDO)` → Mysql / Postgres / Sqlite for migrations.

---

## Helpers (`gemvc/helper`)

```php
CryptHelper::hashPassword(string $plain): string
CryptHelper::passwordVerify(string $plain, string $hash): bool
TypeChecker::check(string $type, mixed $value, array $options = []): bool
```

---

## CLI (library)

```
gemvc init [--swoole|--apache|--nginx] [--db=mysql|postgres|sqlite] [--non-interactive]
gemvc db:migrate TableClass [--sync-schema]
```

## CLI (`gemvc/cli-dev`, require-dev)

```
gemvc create:crud|service|controller|model|table …
gemvc db:init|list|describe|drop|unique
gemvc admin:setadmin
```

---

## Env (common)

```env
DB_DRIVER=mysql|pgsql|sqlite
DB_HOST= DB_PORT= DB_NAME= DB_USER= DB_PASSWORD=
TOKEN_SECRET= TOKEN_ISSUER=
QUERY_LIMIT=10
APM_NAME=TraceKit
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

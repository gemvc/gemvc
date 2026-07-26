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
```

---

## `Gemvc\Database\Table`

```php
public function getTable(): string
public function defineSchema(): array
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
```

**Schema helpers:** `Schema::primary`, `autoIncrement`, `unique`, `index`, `foreignKey`, `check`, `fullText` (MySQL).

**Dialects:** `DialectResolver::resolve(PDO)` → Mysql / Postgres / Sqlite for migrations.

---

## Helpers (`gemvc/helper`)

```php
CryptHelper::hashPassword(string $password): string
CryptHelper::passwordVerify(string $passwordToCheck, string $hash): bool
CryptHelper::encryptString(string $string, string $key): false|string
CryptHelper::decryptString(string $encryptedString, string $key): false|string
TypeChecker::check(mixed $type, mixed $value, array $options = []): bool
```

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
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

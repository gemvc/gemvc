# GEMVC API Layer

**Audience:** developers writing `app/api` · AI assistants generating API services.

**Related:** [helper.md](helper.md) · [controller.md](controller.md) · [security.md](security.md) · [http-lifecycle.md](http-lifecycle.md) · [api-documentation.md](api-documentation.md) · [CANONICAL.md](../ai/CANONICAL.md)

---

## What API does for you

API is the **thin HTTP boundary**:

```
HTTP → API (schema + auth) → Controller → Model → Table → DB
```

You extend `Gemvc\Core\ApiService` (Apache/Nginx) or `Gemvc\Core\SwooleApiService` (OpenSwoole). URL mapping: Apache `/api/{Service}/{method}` → `App\Api\{Service}::{method}()`; OpenSwoole uses `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (no automatic `api` hop — see [architecture.md](architecture.md)).

| Belongs in API | Belongs elsewhere |
|----------------|-------------------|
| `define*Schema`, list allowlists | Controller: map request → Model |
| `requireAuth` / `auth` | Model: business rules |
| Call Controller | Table: SQL / CRUD |
| PHPDoc `@http` / mocks for docs | |

**No business rules here.** 4-layer stack is **strongly recommended** — [CANONICAL](../ai/CANONICAL.md).

---

## Reading map (AI)

| Goal | Section |
|------|---------|
| Role & rules | [Hard rules](#hard-rules-ai) |
| Base class choice | [ApiService vs SwooleApiService](#apiservice-vs-swooleapiservice) |
| Auth | [Authentication](#authentication) |
| Schemas | [Schema validation](#schema-validation) |
| Lists | [List allowlists](#list-allowlists) |
| Call Controller | [Invoking Controller](#invoking-controller) |
| Docs directives | [Auto documentation](#auto-documentation) |
| Skeleton | [Minimal service](#minimal-service) |
| Codegen | [CLI](#cli-codegen) |
| Mistakes | [Do / Don’t](#do-dont) |

---

## Hard rules (AI)

1. API classes live in `app/api/` as `User.php` → `App\Api\User`.
2. Extend **`ProtectedApiService`** / **`ProtectedSwooleApiService`** for authenticated CRUD; **`ApiService`** / **`SwooleApiService`** for public endpoints (login, register, health). Match Apache vs OpenSwoole.
3. Always **`definePostSchema` / `defineGetSchema` / …** before using body/query data.
4. If using a public base, prefer **`requireAuth([...])`** in the constructor to guard the whole service.
5. Apache/Nginx: prefer **`callController(new XController($this->request))->method()`**.
6. OpenSwoole: **`(new XController($this->request))->method()`** — **no** `callController` / magic `$this->XController`.
7. For lists: call `findable` / `filterable` / `sortable` **in API before** Controller `createList`.
8. Never invent a routes file. Never put Model/Table SQL in API.

---

## ApiService vs SwooleApiService

| | `ApiService` / `ProtectedApiService` | `SwooleApiService` / `ProtectedSwooleApiService` |
|--|--------------|-------------------|
| Server | Apache / Nginx | OpenSwoole |
| Auth by default | `Protected*` yes; plain `Api*` no | `Protected*` yes; plain `Swoole*` no |
| `callController()` | yes (APM proxy) on both Apache bases | **no** |
| Magic `$this->UserController` | yes on both Apache bases | **no** |
| Validation helpers (`validatePosts` / `validateStringPosts`) | throws `ValidationException` (Bootstrap → JSON) | return `?JsonResponse` (legacy) |
| Cross-runtime throw helpers | `validateOrFail()` / `validateStringOrFail()` | same (SwooleBootstrap → 400) |
| `requireAuth()` | yes | yes |
| `requireRateLimit()` / `requireRateLimitApcu\|Redis\|Both()` | yes | yes |

Usual schema path: `definePostSchema()` / `defineGetSchema()` → `bool` + `returnResponse()` (does **not** throw). Prefer that, or `validateOrFail()` for throw-style on **both** servers. Do not rely on Swoole’s legacy `validatePosts()` return style for new code.

Same `app/` layering either way; only the API base class and how you invoke Controllers differ. Details: [http-lifecycle.md](http-lifecycle.md).

---

## Authentication

### Protected base (preferred for authenticated CRUD)

```php
use Gemvc\Core\ProtectedApiService; // OpenSwoole: ProtectedSwooleApiService

class User extends ProtectedApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request, ['admin']); // throws AuthException → 401 or 403
    }
}
```

- `parent::__construct($request, null)` or `[]` → any authenticated user  
- `parent::__construct($request, ['admin','editor'])` → one of these roles  
- Public endpoints: keep `extends ApiService` / `SwooleApiService`

### Or `requireAuth()` on a public base

```php
public function __construct(Request $request)
{
    parent::__construct($request);
    $this->requireAuth(['admin']); // throws AuthException → 401 or 403
}
```

- `requireAuth([])` / `requireAuth(null)` → any authenticated user  
- `requireAuth(['admin','editor'])` → one of these roles  

### Per-method

```php
if (!$this->request->auth(['admin'])) {
    return $this->request->returnResponse();
}
```

| Situation | HTTP |
|-----------|------|
| No / unextractable token | **401** |
| Invalid token or wrong role | **403** |

Full detail: [security.md](security.md).

---

## Rate limiting

Optional but important for production. Drivers: **`apcu`** (default, per PHP instance), **`redis`** (cluster-wide via `RedisManager`), **`both`** (simultaneous dual check — deny if either over), **`none`** (disables Bootstrap + default `requireRateLimit()`; overrides still work).

**No automatic Redis↔APCu fallback** (would desync cluster quotas and add timeout traps). Prefer proxy/edge limits for multi-node as well.

`REQUEST_RATE_LIMIT_DRIVER=both` ≠ `REQUEST_RATE_LIMIT_SCOPE=both` (IP+token). Driver `both` is simultaneous dual check (not failover) and requires **both** APCu and Redis to be available.

### Global (Bootstrap) — automatic, no code

```env
REQUEST_RATE_LIMIT_DRIVER=apcu
REQUEST_RATE_LIMIT_PER_SEC=20
REQUEST_RATE_LIMIT_BLOCK_SECONDS=60
REQUEST_RATE_LIMIT_SCOPE=both
REQUEST_RATE_LIMIT_FAIL_MODE=closed
# REDIS_* when DRIVER=redis or both
```

Unset / `0` PER_SEC = off. `DRIVER=none` disables Bootstrap + default `requireRateLimit()` even if PER_SEC is set.

### Service / method DX

```php
$this->requireRateLimit();                 // uses REQUEST_RATE_LIMIT_DRIVER
$this->requireRateLimitApcu(30, 'ip');     // force APCu (ignores global driver)
$this->requireRateLimitRedis(5, 'ip', 120); // force Redis
$this->requireRateLimitBoth(10);           // force dual check (not failover)
```

Explicit overrides still enforce when global `DRIVER=none` (opt-in for special endpoints). Throws `RateLimitException` → HTTP **429**.

### Notes

- **FAIL_MODE** applies when the chosen backend(s) are unavailable at the start of `enforce()`. Mid-request write failures still deny (429) and never switch drivers.
- **SCOPE=token** with no JWT falls back to IP. On exceed with **SCOPE=both**, all buckets for that request are blocked.
- APCu full: purge `gemvc:rl:*`, retry once, then deny.

---

## Schema validation

Prevents mass assignment. Unlisted fields are rejected.

```php
if (!$this->request->definePostSchema([
    'name' => 'string',
    'email' => 'email',
    'password' => 'string',
    '?phone' => 'string',   // optional
])) {
    return $this->request->returnResponse();
}
```

Also: `defineGetSchema`, `definePutSchema`, `definePatchSchema`, `validateStringPosts(['name' => '2|100'])`.

Throw-style (both Apache/Nginx and OpenSwoole):

```php
$this->validateOrFail(['name' => 'string', 'email' => 'email']);
$this->validateStringOrFail(['name' => '2|100']);
```

Types: `string`, `int`, `email`, `url`, `ip`, `decimal`, `uuid`, … — [CORE_REFERENCE](../ai/CORE_REFERENCE.md).

Inputs are **already sanitized** by the framework — do not re-sanitize.

---

## List allowlists

**One of GEMVC’s strongest features.** Declare allowlists here **before** Controller `createList` — only listed fields can filter/sort; values are type-checked. No free-form query SQL from the client.

```php
public function list(): JsonResponse
{
    $this->request->findable(['name' => 'string', 'email' => 'email']);
    $this->request->filterable(['role' => 'string']);
    $this->request->sortable(['id', 'name', 'created_at']);
    return $this->callController(new UserController($this->request))->list();
}
```

Query params: `find_like`, `filter_by`, `sort_by`, `sort_by_asc`, `page_number` — [controller.md](controller.md#lists-createlist).

---

## Invoking Controller

### Apache / Nginx

```php
return $this->callController(new UserController($this->request))->create();
// or magic: return $this->UserController->create();
```

Enables controller spans when `APM_TRACE_CONTROLLER=1` — [apm.md](apm.md).

### OpenSwoole

```php
return (new UserController($this->request))->create();
```

---

## Auto documentation

PHPDoc on methods feeds `/api/index/document` + Postman:

```php
/**
 * @http POST
 * @description Create User
 * @example /api/User/create
 */
public function create(): JsonResponse { ... }

public static function mockResponse(string $method): array { ... }
```

Details: [api-documentation.md](api-documentation.md).

---

## Minimal service

```php
<?php
namespace App\Api;

use App\Controller\UserController;
use Gemvc\Core\ApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

class User extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
        // $this->requireAuth(['admin']);
    }

    /**
     * @http POST
     * @description Create User
     * @example /api/User/create
     */
    public function create(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string',
        ])) {
            return $this->request->returnResponse();
        }
        return $this->callController(new UserController($this->request))->create();
    }
}
```

OpenSwoole: extend `SwooleApiService` and use bare `new UserController(...)`.

---

## CLI codegen

Needs **`gemvc/cli-dev`**:

```bash
composer require --dev gemvc/cli-dev
gemvc create:service User -cmt
# or
gemvc create:crud User
```

Hand-add: `requireAuth`, schemas, `@http` / `mockResponse`, Apache vs Swoole invoke style.

Templates: [templates.md](templates.md).

---

## Do / Don’t

**Do**

- Keep API thin: schema + auth + call Controller  
- Match base class to server (`ApiService` vs `SwooleApiService`)  
- Use `callController` only on Apache/Nginx  
- Add `@http` + mocks for auto docs  

**Don’t**

- Put business rules or SQL in API  
- Invent routes files  
- Use `callController` / `$this->XController` on `SwooleApiService`  
- Skip `define*Schema`  
- Re-sanitize inputs  

---

## Checklist

1. Correct base class for server  
2. `requireAuth` or per-method `auth` as needed  
3. Schema on every mutating/reading endpoint that uses input  
4. List allowlists before `createList`  
5. Controller invoke style matches server  
6. PHPDoc directives + optional `mockResponse`  

---

## Reference

- Startup sample: `src/startup/common/init_example/api/User.php`  
- Auth deep dive: [security.md](security.md)  
- Controllers: [controller.md](controller.md)  
- Signatures: [CORE_REFERENCE.md](../ai/CORE_REFERENCE.md)  

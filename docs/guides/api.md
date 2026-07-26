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
| Mistakes | [Do / Don’t](#do--dont) |

---

## Hard rules (AI)

1. API classes live in `app/api/` as `User.php` → `App\Api\User`.
2. Extend **`ApiService`** (Apache/Nginx) or **`SwooleApiService`** (OpenSwoole) — match the server.
3. Always **`definePostSchema` / `defineGetSchema` / …** before using body/query data.
4. Prefer **`requireAuth([...])`** in the constructor to guard the whole service.
5. Apache/Nginx: prefer **`callController(new XController($this->request))->method()`**.
6. OpenSwoole: **`(new XController($this->request))->method()`** — **no** `callController` / magic `$this->XController`.
7. For lists: call `findable` / `filterable` / `sortable` **in API before** Controller `createList`.
8. Never invent a routes file. Never put Model/Table SQL in API.

---

## ApiService vs SwooleApiService

| | `ApiService` | `SwooleApiService` |
|--|--------------|-------------------|
| Server | Apache / Nginx | OpenSwoole |
| `callController()` | yes (APM proxy) | **no** |
| Magic `$this->UserController` | yes | **no** |
| Validation fail | throws `ValidationException` (Bootstrap → JSON) | return `?JsonResponse` |
| `requireAuth()` | yes | yes |

Same `app/` layering either way; only the API base class and how you invoke Controllers differ. Details: [http-lifecycle.md](http-lifecycle.md).

---

## Authentication

### Service-wide (preferred)

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

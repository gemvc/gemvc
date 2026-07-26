# GEMVC Controller Layer

**Audience:** developers writing `app/controller` · AI assistants generating Controller code.

**Related:** [api.md](api.md) · [model.md](model.md) · [helper.md](helper.md) · [database.md](database.md) · [apm.md](apm.md) · [security.md](security.md) · [CANONICAL.md](../ai/CANONICAL.md)

---

## What Controller does for you

Controller is the **orchestration** layer:

```
API (schema / auth) → Controller (map request → model, lists) → Model → Table
```

You extend `Gemvc\Core\Controller`, receive `Request` in the constructor, map inputs onto models, and return `JsonResponse`.

| You do | Framework / helpers do |
|--------|-------------------------|
| Map POST/PUT/PATCH → model | `Request::map*ToObject` |
| Call model business methods | Model / Table CRUD |
| Call `createList` for lists | Filters, sort, page, column strip |
| Prefer `createModel($model)` | Wires Request for **DB APM** |
| Return `JsonResponse` | Response factory codes — either pass through Model’s `JsonResponse` **or** build it when Model returns PHP types |

**Not Controller’s job:** schema validation, JWT (`requireAuth` / `auth`), routes, SQL, inventing Eloquent relations.

**Who returns `JsonResponse`?** Style choice — Model or Controller. Details: [model.md — Return style](model.md#return-style-jsonresponse-vs-php-types).


---

## Reading map (AI)

| Goal | Section |
|------|---------|
| Role & hard rules | [Hard rules](#hard-rules-ai) |
| Skeleton | [Minimal controller](#minimal-controller) |
| Call from API (Apache vs Swoole) | [Invoking from API](#invoking-from-api) |
| CRUD + mapping | [CRUD orchestration](#crud-orchestration) |
| APM Request wire | [`createModel()`](#createmodel) |
| Lists & query params | [Lists](#lists-createlist) |
| Errors | [Error bag](#error-bag) |
| Optional spans | [APM inside controller](#apm-inside-controller) |
| Codegen | [CLI](#cli-codegen) |
| Mistakes | [Do / Don’t](#do--dont) |

---

## Hard rules (AI)

1. Controllers **extend** `Gemvc\Core\Controller`; live in `app/controller/` as `UserController`, etc.
2. **No schema / auth in Controller** — that belongs in API (`define*Schema`, `requireAuth` / `auth`).
3. Prefer **`createModel(new XModel())`** before DB work so Request (and APM) reach Table queries. Works for any object; calls `setRequest` if present (composition Models should forward it to children).
4. Apache/Nginx API: prefer **`callController(new XController($this->request))->method()`**.
5. OpenSwoole API (`SwooleApiService`): **`(new XController($this->request))->method()`** — no `callController` / magic `$this->XController`.
6. Lists: API must call `findable` / `filterable` / `sortable` **before** Controller `createList`.
7. Prefer an **explicit column list** for `createList` — `null` uses `get_object_vars()` (initialized public props only; skips `protected` and often uninitialized typed publics).
8. Never invent routes or put SQL in the controller.

---

## Minimal controller

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
        $model = $this->request->mapPostToObject(
            $this->createModel(new UserModel()),
            [
                'name' => 'name',
                'email' => 'email',
                'password' => 'setPassword()',
            ]
        );
        if (!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        return $model->createModel();
    }

    public function list(): JsonResponse
    {
        return $this->createList(
            $this->createModel(new UserModel()),
            'id,name,email,description,created_at'
        );
    }
}
```

---

## Invoking from API

### Apache / Nginx — `ApiService`

```php
// app/api/User.php
return $this->callController(new UserController($this->request))->create();

// Optional magic (same tracing proxy):
// return $this->UserController->create();
```

`callController()` enables controller spans when `APM_TRACE_CONTROLLER=1`. See [apm.md](apm.md).

### OpenSwoole — `SwooleApiService`

```php
return (new UserController($this->request))->create();
```

No `callController` and no `$this->UserController` magic on `SwooleApiService`.

| | `ApiService` | `SwooleApiService` |
|--|--------------|-------------------|
| `callController()` | yes | **no** |
| Magic `$this->XController` | yes | **no** |
| Controller class itself | same | same |

---

## CRUD orchestration

Typical flow:

1. API already validated schema (and auth).
2. Controller maps body → model (`mapPostToObject` / `mapPutToObject` / `mapPatchToObject`).
3. On mapping failure → `return $this->request->returnResponse()`.
4. Delegate to Model. Either:
   - **Style A:** Model returns `JsonResponse` → pass it through (`return $model->createModel()`).
   - **Style B:** Model returns PHP types → Controller maps to `Response::*` ([model.md](model.md#return-style-jsonresponse-vs-php-types)).

```php
public function update(): JsonResponse
{
    $model = $this->request->mapPostToObject(
        $this->createModel(new UserModel()),
        ['id' => 'id', 'name' => 'name', 'email' => 'email']
    );
    if (!$model instanceof UserModel) {
        return $this->request->returnResponse();
    }
    return $model->updateModel();
}
```

Mapping tip: `'password' => 'setPassword()'` calls a setter instead of assigning a property.

Startup sample: `src/startup/common/init_example/controller/UserController.php` (some methods omit `createModel()` — prefer wrapping for APM).

---

## `createModel()`

```php
protected function createModel(object $model): object
```

If the model has `setRequest`, sets `$this->request` on it (Table/Model do). That propagates APM trace context into DB query spans when `APM_TRACE_DB_QUERY=1`.

```php
$model = $this->createModel(new UserModel());
```

`createList()` calls `createModel()` internally. Prefer calling it yourself for create/read/update/delete too.

---

## Lists (`createList`)

**Flagship DX + security:** API allowlists (`findable` / `filterable` / `sortable`) + one Controller call. Pipeline in source: `_handleSearchable` → `_handleFindable` → `_handleSortable` → `_handlePagination` → `select` → strip `_` props → `Response::success` + `getTotalCounts()`. Prefer explicit column lists.

### API side (required allowlists)

```php
// app/api/User.php — list()
$this->request->findable([
    'name' => 'string',
    'email' => 'email',
]);
$this->request->filterable([
    'role' => 'string',
]);
$this->request->sortable(['id', 'name', 'email', 'created_at']);

return $this->callController(new UserController($this->request))->list();
```

### Controller side

```php
public function list(): JsonResponse
{
    // Explicit columns when model has protected props (e.g. password)
    return $this->createList(
        $this->createModel(new UserModel()),
        'id,name,email,description,role,created_at,updated_at'
    );
}
```

### GET query parameters (consumed by Controller)

| Query param | API allowlist | Effect |
|-------------|---------------|--------|
| `find_like=name=ali,email=a@b.c` | `findable([...])` | `WHERE col LIKE` (per field) |
| `filter_by=role=admin` | `filterable([...])` | Exact `where` |
| `sort_by=name` | `sortable([...])` | `orderBy` (null/false ascending → **DESC**) |
| `sort_by_asc=name` | `sortable([...])` | `orderBy(..., true)` → **ASC** |
| `page_number=2` | — | `$model->setPage(n)` (default **1**) |

Page **size** comes from Table / `QUERY_LIMIT` (and related Table helpers), not from Controller reading `per_page` in `createList`. Request has `setPerPage()` / `getPerPage()` for custom use — `createList` does not call them today.

### `createList` vs `listJsonResponse`

| | `createList` | `listJsonResponse` |
|--|--------------|-------------------|
| Calls `createModel` first | yes | via `_listObjects` also |
| Default `$columns` | `get_object_vars` keys (initialized **public** only) | `*` inside `_listObjects` if null |
| Typical use | Prefer for app lists | Alternate helper |

**Always prefer an explicit column string** for `createList`. Reasons: (1) uninitialized typed public props may be missing from the default list; (2) you choose the public subset; (3) `protected` fields are already omitted from the default list but you should not rely on that alone for clear APIs.

List responses strip properties whose names start with `_`.

---

## Error bag

```php
protected function addError(string $message, int $httpCode = 400): void
public function getErrors(): array   // GemvcError[]
public function hasErrors(): bool
public function clearErrors(): void
```

Most CRUD paths return `JsonResponse` from the Model instead of using this bag. Use the bag for multi-step controller checks if you prefer collecting errors before responding.

Invalid list filters / `page_number` throw `ValidationException` (handled by Bootstrap).

---

## APM inside controller

- Prefer `callController` (Apache) + `createModel` for automatic spans.
- Custom child spans: `startTraceSpan` / `endApmSpan` / `traceApm` (trait) — details in [apm.md](apm.md).
- `recordApmException($e)` or `recordApmException($spanData, $e)`.

---

## CLI codegen

Needs **`gemvc/cli-dev`**:

```bash
gemvc create:controller Product
gemvc create:crud Product   # includes controller
```

Generated template often uses `mapPostToObject(new …Model())` **without** `createModel()` and `createList($model)` **without** columns. Hand-edit:

1. Wrap models with `createModel(...)`.
2. Pass **explicit column lists** to `createList` (do not rely on `null` defaults).
3. Keep API-layer `findable` / `sortable` in sync.

Templates: [templates.md](templates.md).

---

## Do / Don’t

**Do**

- Keep Controller thin on **business rules**; map → call Model → return `JsonResponse`. Logic lives in Model ([model.md](model.md)). Under Style B, Controller may be thicker on **HTTP status mapping** only.
- Use `createModel` + (on Apache) `callController`  
- Declare list allowlists in API; use `createList` in Controller  
- Pass explicit columns when needed  

**Don’t**

- Put `definePostSchema` / `requireAuth` in Controller  
- Copy `callController` into `SwooleApiService` subclasses  
- Write JOINs/SQL in Controller (use Model/Table or [SQL views](database.md#sql-views-as-tables-recommended))  
- Invent Laravel-style resource controllers / form requests  

---

## Checklist

1. Extends `Controller`; constructor calls `parent::__construct($request)`
2. API validates + auth; Controller only orchestrates
3. `createModel` before DB-bound work
4. Apache: `callController(...)`; Swoole: bare `new`
5. List: API `findable`/`filterable`/`sortable` + Controller `createList(..., $columns?)`
6. Mapping failures → `returnResponse()`

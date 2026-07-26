# GEMVC Model Layer

**Audience:** developers writing `app/model` · AI assistants generating Model code.

**Related:** [controller.md](controller.md) · [database.md](database.md) · [apm.md](apm.md) · [CANONICAL.md](../ai/CANONICAL.md)

---

## What Model does for you

Model is the **data / business-logic** layer:

```
API (schema / auth) → Controller (orchestration) → Model (rules + transforms) → Table (DB)
```

Canonical pattern:

```php
class UserModel extends UserTable  // inherits columns, queries, insert/update/delete
```

| Belongs in Model | Belongs elsewhere |
|------------------|-------------------|
| Business rules (uniqueness, domain checks) | API: schema + auth |
| Transforms (`setPassword`, normalize email) | Controller: map request → model |
| Domain ops (login, bootstrap admin) | Table: raw query helpers only |
| Domain results (`JsonResponse` **or** PHP types) | Controller: build `JsonResponse` if Model returns data |

**Controller stays thin on logic.** If rules grow past “map and call,” they belong in Model. Who builds the HTTP `JsonResponse` is a **style choice** — see [Return style](#return-style-jsonresponse-vs-php-types).

There is **no** separate framework class for “simple vs complex” Models — both extend Table. Complexity is how much domain logic you add.

---

## Reading map

| Goal | Section |
|------|---------|
| Role & rules | [Hard rules](#hard-rules-ai) |
| Who returns `JsonResponse` | [Return style](#return-style-jsonresponse-vs-php-types) |
| Thin CRUD Model | [Simple Model](#simple-model-cli-style) |
| Domain logic Model | [Domain Model](#domain-model-recommended) |
| CRUD method patterns | [CRUD methods](#crud-methods) |
| Setters + mapping | [Transforms & setters](#transforms--setters) |
| Validation | [Business validation](#business-validation) |
| Relations / `_` props | [Aggregations](#aggregations-_properties) |
| Heavy reads | [Complex reads](#complex-reads) |
| Multi-step / APM | [Beyond CRUD](#beyond-crud) |
| Rare non-Table | [Non-Table Models](#non-table-models-rare) |
| Codegen | [CLI](#cli-codegen) |
| Mistakes | [Do / Don’t](#do--dont) |

---

## Hard rules (AI)

1. App Models **extend** their Table (`UserModel extends UserTable`), not bare `Table` (unless composing tools).
2. Put **business logic in Model**, not Controller or API.
3. Model may return **`JsonResponse` or any PHP type** (`static`, `?static`, `array`, `bool`, `int`, `string`, DTOs, …). Pick one style per service and stick to it — see [Return style](#return-style-jsonresponse-vs-php-types).
4. If Model returns data (not `JsonResponse`), **Controller** must map success/failure to `Response::*` / `JsonResponse`.
5. Use inherited Table API / `QueryBuilder` — no string-concat SQL.
6. Controllers should wrap with **`createModel(new XModel())`** so Request/APM reach DB queries.
7. Map sensitive fields via setters (`'password' => 'setPassword()'`).
8. Prefer **SQL views + Table** for JOIN-heavy reads ([database.md](database.md#sql-views-as-tables-recommended)).
9. `_`-prefixed properties are aggregations — not columns.

---

## Return style: `JsonResponse` vs PHP types

GEMVC does **not** force Models to return `JsonResponse`. That is a **development style** decision.

| Style | Model returns | Controller does | Typical use |
|-------|---------------|-----------------|-------------|
| **A — Response in Model** (CLI default) | `JsonResponse` via `Response::*` | Map request → call Model → **pass through** | Fast CRUD, thin Controllers |
| **B — Data in Model** | Objects / scalars / `null` / `array` / `bool` / … | Map request → call Model → **build `JsonResponse`** | Reusable domain API, unit tests, multiple callers |

Both keep **rules in Model**. Only the HTTP envelope moves.

### Style A — Model returns `JsonResponse`

```php
// Model
public function createModel(): JsonResponse
{
    if ($this->selectByEmail($this->email)) {
        return Response::unprocessableEntity('User already exists');
    }
    if (!$this->insertSingleQuery()) {
        return Response::internalError($this->getError());
    }
    return Response::created($this, 1, 'User created successfully');
}

// Controller
return $model->createModel();  // already JsonResponse
```

### Style B — Model returns PHP types; Controller returns `JsonResponse`

```php
// Model — reusable domain method
public function createUser(): ?self
{
    if ($this->selectByEmail($this->email)) {
        $this->setError('User already exists');
        return null;
    }
    if (!$this->insertSingleQuery()) {
        return null;  // getError() set by Table
    }
    return $this;
}

public function findById(int $id): ?static
{
    return $this->selectById($id);
}

// Controller — HTTP mapping lives here
public function create(): JsonResponse
{
    $model = $this->request->mapPostToObject(
        $this->createModel(new UserModel()),
        ['email' => 'email', 'name' => 'name', 'password' => 'setPassword()']
    );
    if (!$model instanceof UserModel) {
        return $this->request->returnResponse();
    }
    $created = $model->createUser();
    if ($created === null) {
        $err = $model->getError() ?? 'Create failed';
        if (str_contains($err, 'already exists')) {
            return Response::unprocessableEntity($err);
        }
        return Response::internalError($err);
    }
    return Response::created($created, 1, 'User created successfully');
}

public function read(): JsonResponse
{
    $id = $this->request->intValueGet('id');
    if ($id === null || $id < 1) {
        return Response::badRequest('id required');
    }
    $user = $this->createModel(new UserModel())->findById($id);
    if ($user === null) {
        return Response::notFound('User not found');
    }
    return Response::success($user, 1, 'OK');
}
```

**Guidelines**

- Prefer **one style per service** (don’t mix randomly inside the same resource).
- Style A matches `gemvc create:crud` / startup samples.
- Style B when the same Model method is used from CLI, jobs, or other Controllers — keep HTTP concerns out of Model.
- Model can still use `$this->setError()` / `getError()` when returning `null`/`false` so Controller can choose status codes.
- API layer always returns `JsonResponse` either way (from Controller).

---

## Simple Model (CLI-style)

What `gemvc create:model` / `create:crud` typically emits: thin wrappers over Table CRUD using **Style A** (`JsonResponse` in Model).

```php
<?php
namespace App\Model;

use App\Table\ProductTable;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class ProductModel extends ProductTable
{
    public function createModel(): JsonResponse
    {
        if (!$this->insertSingleQuery()) {
            return Response::internalError('Failed to create: ' . $this->getError());
        }
        return Response::created($this, 1, 'Product created successfully');
    }

    public function readModel(): JsonResponse
    {
        $item = $this->selectById($this->id);
        if (!$item) {
            return Response::notFound('Product not found');
        }
        return Response::success($item, 1, 'Product retrieved successfully');
    }

    public function updateModel(): JsonResponse
    {
        if (!$this->selectById($this->id)) {
            return Response::notFound('Product not found');
        }
        $success = $this->updateSingleQuery();
        if ($this->getError()) {
            return Response::internalError('Failed to update: ' . $this->getError());
        }
        return Response::updated($success, 1, 'Product updated successfully');
    }

    public function deleteModel(): JsonResponse
    {
        if (!$this->selectById($this->id)) {
            return Response::notFound('Product not found');
        }
        $success = $this->deleteByIdQuery($this->id);
        if ($this->getError()) {
            return Response::internalError('Failed to delete: ' . $this->getError());
        }
        return Response::deleted($success, 1, 'Product deleted successfully');
    }
}
```

Enough for basic resources (Style A). Grow into a **domain Model** when you need rules, auth flows, or transforms. For Style B, the same logic returns `?self` / `bool` and the Controller wraps `Response::*`.

---

## Domain Model (recommended)

Startup `UserModel` is the reference: CRUD **plus** hashing, login, admin bootstrap, custom reads. Samples below use Style A; the same methods can return objects/`null` under Style B.

```php
class UserModel extends UserTable
{
    public function setPassword(string $plainPassword): void
    {
        $this->password = CryptHelper::hashPassword($plainPassword);
    }

    public function createModel(): JsonResponse
    {
        $this->created_at = date('Y-m-d H:i:s');
        if (!$this->insertSingleQuery()) {
            return Response::internalError('Failed to create User: ' . $this->getError());
        }
        return Response::created($this, 1, 'User created successfully');
    }

    public function loginByEmailPassword(string $email, string $password): JsonResponse
    {
        $user = $this->selectByEmail($email);
        if (!$user || !CryptHelper::passwordVerify($password, $user->password)) {
            return Response::unauthorized('Invalid email or password');
        }
        // build JWT tokens… return Response::success(...)
    }
}
```

Full sample: `src/startup/common/init_example/model/UserModel.php`.

| Simple | Domain |
|--------|--------|
| Generated CRUD only | + setters, uniqueness, login, workflows |
| Thin wrappers (often Style A) | Real product rules (A or B) |
| Fine for admin CRUD | Default for real services |

---

## CRUD methods

Naming convention (not magic — just convention). Return type depends on [style](#return-style-jsonresponse-vs-php-types):

| Method | Typical Table ops | Style A success | Style B example |
|--------|-------------------|-----------------|-----------------|
| `createModel()` / `createUser()` | `insertSingleQuery()` | `Response::created` | `?self` |
| `readModel()` / `findById()` | `selectById` / `QueryBuilder` | `Response::success` | `?static` |
| `updateModel()` | exists check → `updateSingleQuery()` | `Response::updated` | `bool` |
| `deleteModel()` | exists check → `deleteByIdQuery` / `safeDeleteQuery` | `Response::deleted` | `bool` |

Always check `$this->getError()` (or failed insert return). Style A maps that inside Model; Style B leaves mapping to Controller (`internalError` / `notFound` / `unprocessableEntity`).

Soft delete: call `safeDeleteQuery()` / `restoreQuery()` from Model when the table has `deleted_at` ([database.md](database.md#soft-delete)).

Inherited Table APIs: [database.md](database.md#queries--crud).

---

## Transforms & setters

Controller mapping can call Model methods:

```php
// Controller
$this->request->mapPostToObject($model, [
    'email' => 'email',
    'password' => 'setPassword()',  // invokes UserModel::setPassword
]);
```

Put hashing, normalization (`strtolower` email), and derived fields **in Model setters or `*Model()` methods** — not in the Controller.

Keep secrets on **`protected`** Table properties so list/select payloads stay clean.

---

## Business validation

Do domain checks **before** write:

```php
public function createModel(): JsonResponse
{
    $this->email = strtolower(trim($this->email));
    if ($this->selectByEmail($this->email)) {
        return Response::unprocessableEntity('User already exists');
    }
    $this->created_at = date('Y-m-d H:i:s');
    if (!$this->insertSingleQuery()) {
        return Response::internalError($this->getError());
    }
    return Response::created($this, 1, 'User created successfully');
}
```

API schema validates **shape**; Model validates **rules** (duplicates, state transitions, invariants).

---

## Aggregations (`_` properties)

Underscore properties are **not** DB columns (ignored by CRUD/migrate):

```php
// On Table or Model
public ?Profile $_profile = null;
public array $_orders = [];

public function withProfile(): self
{
    if ($this->_profile === null && $this->id) {
        $this->_profile = (new ProfileTable())->selectByUserId($this->id);
    }
    return $this;
}
```

There is **no** built-in Eloquent-style `with()` — write explicit loaders. For heavy multi-table SELECTs, prefer a **view Table** instead of nested aggregations.

---

## Complex reads

| Approach | When |
|----------|------|
| Table helpers (`selectByEmail`) | Single-table lookups |
| `QueryBuilder` in Model | Ad-hoc reads (see `UserModel::readModel`) |
| **SQL VIEW + Table class** | Joins / aggregates / reporting |

GEMVC recommendation: push JOIN complexity into SQL views, then a simple Model/Table over the view — [SQL views as tables](database.md#sql-views-as-tables-recommended).

---

## Beyond CRUD

Domain methods are normal Model methods. Return `JsonResponse` **or** PHP types — same [style choice](#return-style-jsonresponse-vs-php-types):

- `loginByEmailPassword` → Style A: `JsonResponse` · Style B: token DTO / `array` / `null`
- `firstAdminUser` → often `?static` even in Style A services
- Workflows (checkout, approve, …)

Optional APM for multi-step work:

```php
use Gemvc\Core\Apm\ApmTracingTrait;

class OrderModel extends OrderTable
{
    use ApmTracingTrait;

    public function placeOrder(): JsonResponse
    {
        return $this->traceApm('order.place', function () {
            // multi-step logic…
        });
    }
}
```

Details: [apm.md](apm.md). Request must be set via Controller `createModel()`.

---

## Non-Table Models (rare)

Most app Models extend a Table. Exceptions in core (tools/APM) **compose** services instead of `extends Table`. For business entities, **always** extend the Table subclass.

---

## CLI codegen

Needs **`gemvc/cli-dev`**:

```bash
gemvc create:model Product -t    # model (+ table)
gemvc create:crud Product        # full stack
```

Generated Model is **simple**. Hand-add:

1. Setters / hashing  
2. Uniqueness and domain checks  
3. Soft-delete vs hard-delete policy  
4. Login / workflow methods as needed  

Templates: [templates.md](templates.md).

---

## Do / Don’t

**Do**

- `extends XTable`; keep business rules and transforms in Model  
- Choose Style A (`JsonResponse` in Model) **or** Style B (PHP types in Model, `JsonResponse` in Controller) and stay consistent  
- Use Table query API / views for data access  
- Grow from simple CRUD to domain methods as the product needs  

**Don’t**

- Assume Models *must* return `JsonResponse` (false — it’s a style choice)  
- Put business rules only in Controller (HTTP mapping in Controller is fine under Style B)  
- Invent Eloquent relations / magic `with`  
- Skip layers (API → Table)  
- Use `float` for money (use `decimal` + string on Table)  
- Leak `protected` password into list payloads  

---

## Checklist

1. `UserModel extends UserTable` (naming matches service)
2. Style chosen: A (`Response::*` in Model) or B (types in Model + `Response::*` in Controller)
3. Setters for secrets / transforms
4. Domain validation before writes
5. Soft delete / views chosen intentionally
6. Controller uses `createModel(new …)` when calling into Model DB work

---

## Reference

- Startup: `src/startup/common/init_example/model/UserModel.php`
- Controller wiring: [controller.md](controller.md)
- Table / views / connections: [database.md](database.md)

# GEMVC Model Layer

**Audience:** developers writing `app/model` · AI assistants generating Model code.

**Related:** [controller.md](controller.md) · [database.md](database.md) · [helper.md](helper.md) · [apm.md](apm.md) · [CANONICAL.md](../ai/CANONICAL.md)

---

## What Model does for you

Model is the **data / business-logic** layer:

```
API (schema / auth) → Controller (orchestration) → Model (rules + transforms) → Table (DB)
```

Canonical patterns:

```php
// 1) Table-backed (most CRUD entities)
class UserModel extends UserTable

// 2) Composition (no Table) — orchestrate other Models into one typed object
class CheckoutModel  // does NOT extend Table
{
    public UserModel $user;
    public OrderModel $order;
    // expose only the methods you want; hide the rest
}
```

| Belongs in Model | Belongs elsewhere |
|------------------|-------------------|
| Business rules (uniqueness, domain checks) | API: schema + auth |
| Transforms (`setPassword`, normalize email) | Controller: map request → model |
| Domain ops (login, multi-model workflows) | Table: raw query helpers only |
| Domain results (`JsonResponse` **or** PHP types) | Controller: build `JsonResponse` if Model returns data |

**Controller stays thin on logic.** If rules grow past “map and call,” they belong in Model. Who builds the HTTP `JsonResponse` is a **style choice** — see [Return style](#return-style-jsonresponse-vs-php-types).

There is **no** required base class for Models. Most entity Models **extend** their Table; composition Models are plain classes that **use** other Models. Complexity and flexibility are yours.

---

## Reading map (AI)

| Goal | Section |
|------|---------|
| Role & rules | [Hard rules](#hard-rules-ai) |
| Who returns `JsonResponse` | [Return style](#return-style-jsonresponse-vs-php-types) |
| Thin CRUD Model | [Simple Model](#simple-model-cli-style) |
| Domain logic Model | [Domain Model](#domain-model-recommended) |
| CRUD method patterns | [CRUD methods](#crud-methods) |
| Setters + mapping | [Transforms & setters](#transforms-setters) |
| Validation | [Business validation](#business-validation) |
| Relations / `_` props | [Aggregations](#aggregations-_-properties) |
| Heavy reads | [Complex reads](#complex-reads) |
| Multi-step / APM | [Beyond CRUD](#beyond-crud) |
| Models without Table | [Composition Models](#composition-models-no-table) |
| Concurrent balance / wallets | [Atomic money transfers](#atomic-money-transfers-pessimistic-lock) |
| Codegen | [CLI](#cli-codegen) |
| Mistakes | [Do / Don’t](#do-dont) |

---

## Hard rules (AI)

1. **Two Model shapes are valid:**
   - **Table-backed:** `UserModel extends UserTable` (CRUD entities).
   - **Composition:** plain class that holds other Models (and/or services) as properties — no `extends Table` required.
2. Put **business logic in Model**, not Controller or API.
3. Model may return **`JsonResponse` or any PHP type** (`static`, `?static`, `array`, `bool`, DTOs, composition result objects, …). Pick one style per service — see [Return style](#return-style-jsonresponse-vs-php-types).
4. If Model returns data (not `JsonResponse`), **Controller** must map success/failure to `Response::*` / `JsonResponse`.
5. Table-backed Models use inherited Table API / `QueryBuilder` — no string-concat SQL.
6. Controllers should wrap with **`createModel(new XModel())`** when the Model (or its children) touch DB so Request/APM reach queries. Composition Models should call `setRequest` on child Table-backed Models (or implement `setRequest` and forward it).
7. Map sensitive fields via setters (`'password' => 'setPassword()'`).
8. Prefer **`ViewTable`** for JOIN-heavy reads ([database.md](database.md#sql-views-via-viewtable-recommended)); use composition Models for **cross-entity workflows** and controlled APIs.
9. On Table-backed Models, `_`-prefixed properties are aggregations — not columns.
10. Concurrent money: `beginTransaction()` + `forUpdate()` + BCMath on **one** Table instance; updates via `$this` (not hydrated rows); never raw `DatabaseManagerFactory…->getPdo()` — [Atomic money transfers](#atomic-money-transfers-pessimistic-lock).

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

Inherited Table APIs: [database.md](database.md#queries-crud).

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

Keep secrets on **`protected`** Table properties so typical list / `createList` defaults / API payloads stay clean. They are still DB columns and still appear in SQL `SELECT *` hydration — prefer explicit column lists when needed.

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

## Aggregations (_ properties)

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
| **Composition Model** | Multi-entity workflows / façades / typed mixes ([below](#composition-models-no-table)) |

GEMVC recommendation: push JOIN complexity into SQL views via **`ViewTable`**, then a Model over that view — [SQL views via ViewTable](database.md#sql-views-via-viewtable-recommended).

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

## Composition Models (no Table)

You are **not** required to `extends XTable`. A Model can be a plain PHP class that **owns other Models** (and helpers) as properties, runs **inter-model logic**, and returns a **new typed object** (or `JsonResponse`).

That is full flexibility: you decide the public API — which child methods to expose, wrap, or hide.

| Shape | Extends Table? | Best for |
|-------|----------------|----------|
| Table-backed | Yes (`UserModel extends UserTable`) | One entity / one table CRUD |
| Aggregations `_profile` | Still Table-backed | Light related data on one entity |
| **Composition Model** | **No** | Workflows across entities; façades; DTOs built from several Models |

Core examples that do **not** extend Table: `Gemvc\Core\Apm\ApmModel`, `Gemvc\Core\Assistant\GemvcAssistantModel`.

### Pattern

```php
<?php
namespace App\Model;

use App\Model\UserModel;
use App\Model\OrderModel;
use App\Model\InventoryModel;
use Gemvc\Http\Request;

/**
 * Composition Model — not a Table.
 * Mixes User + Order + Inventory into one controlled domain object.
 */
class CheckoutModel
{
    public UserModel $user;
    public OrderModel $order;
    public InventoryModel $inventory;

    /** Result shape you define — not a DB row */
    public ?CheckoutResult $result = null;

    public function __construct()
    {
        $this->user = new UserModel();
        $this->order = new OrderModel();
        $this->inventory = new InventoryModel();
    }

    /** Forward Request so child Table Models get APM / context */
    public function setRequest(Request $request): void
    {
        $this->user->setRequest($request);
        $this->order->setRequest($request);
        $this->inventory->setRequest($request);
    }

    /**
     * Inter-model workflow. Only expose what callers need.
     * Child Models keep their full APIs privately — you limit the surface here.
     */
    public function place(int $userId, array $lines): ?CheckoutResult
    {
        $user = $this->user->selectById($userId);
        if ($user === null) {
            return null;
        }

        if (!$this->inventory->reserve($lines)) {
            return null;
        }

        $order = $this->order->createFromLines($userId, $lines);
        if ($order === null) {
            $this->inventory->release($lines);
            return null;
        }

        // Typed PHP object — mix of models / scalars you choose
        $this->result = new CheckoutResult(
            user: $user,
            order: $order,
            reserved: $lines,
        );
        return $this->result;
    }

    // Intentionally NO public access to $inventory->deleteAll() etc.
    // Callers only see place() / result — full control over the façade.
}

/** Plain typed result (DTO) — optional but recommended for Style B */
final class CheckoutResult
{
    public function __construct(
        public UserModel $user,
        public OrderModel $order,
        public array $reserved,
    ) {}
}
```

Controller:

```php
public function place(): JsonResponse
{
    $checkout = $this->createModel(new CheckoutModel()); // setRequest if present
    $result = $checkout->place(
        $this->request->intValuePost('user_id') ?? 0,
        $this->request->post['lines'] ?? [],
    );
    if ($result === null) {
        return Response::unprocessableEntity('Checkout failed');
    }
    return Response::created($result, 1, 'Order placed');
}
```

`Controller::createModel()` accepts **any object**; it calls `setRequest` only if that method exists. Implement `setRequest` on composition Models to wire children.

### When to use what

| Need | Prefer |
|------|--------|
| CRUD on one table | Table-backed Model |
| JOIN / report query | SQL view + **`ViewTable`** ([database.md](database.md#sql-views-via-viewtable-recommended)) |
| Multi-step write across entities | **Composition Model** |
| Hide dangerous Table methods from Controllers | **Composition Model** façade |
| Single payload mixing several entities | Composition result DTO / Style B object |

### Do’s for composition

- Treat the composition class as the **only** public surface Controllers call.
- Forward `setRequest` to every child that hits the DB.
- Return a **typed** result object when mixing Models (not a loose `array`), unless Style A returns `JsonResponse` directly.
- Keep Table-backed Models for per-entity rules; put cross-entity orchestration here.

### Don’ts for composition

- Don’t skip the 4-layer stack for HTTP flows (API still validates; Controller still maps/calls). Runtime allows bypass; architecture strongly recommends against it.
- Don’t put SQL in the composition Model — call child Model/Table APIs.
- Don’t confuse with `_` aggregations: those hang **on** a Table-backed Model; composition **is** a separate Model class.

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

## Atomic money transfers (pessimistic lock)

For concurrent balance updates (accounting, wallets), keep work in the **Model** on a **Table-backed** class. Use GEMVC transactions + `FOR UPDATE` — do **not** grab a raw PDO from `DatabaseManagerFactory`.

### Why not raw PDO?

`Table::beginTransaction()` / `commit()` / `rollback()` go through `PdoQuery` → `UniversalQueryExecuter`, which marks the **pooled** connection as in-transaction (writes stay on that connection). A second PDO from the factory can begin a transaction on connection A while updates run on B → **not atomic**.

### Rules

| Rule | Detail |
|------|--------|
| Same Table instance | `beginTransaction` → lock SELECT → **updates on `$this`** → `commit`/`rollback` (hydrated rows are new instances — do not `updateSingleQuery()` on them) |
| Lock | Prefer one `whereIn` + `forUpdate()` + `orderBy('id')` inside the transaction |
| Lock order | Ascending id (`ORDER BY id` / lock min then max) to avoid deadlocks |
| Money | `public string $balance` + `$_type_map` `decimal`; `bccomp` / `bcsub` / `bcadd` — never `float` |
| Dialects | Row locks: **MySQL InnoDB / PostgreSQL**. Not a SQLite multi-writer recipe |

API: `definePostSchema` with `amount` => `decimal`, then `decimalValuePost('amount')`. Controller only maps and calls the Model.

### Example: `AccountModel::transfer`

Hydrated rows from `run()` are **new** instances (their own connection). Lock and **update only via `$this`** (the instance that called `beginTransaction()`).

```php
<?php
namespace App\Model;

use App\Table\AccountTable;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class AccountModel extends AccountTable
{
    public function transfer(int $fromAccountId, int $toAccountId, string $amount): JsonResponse
    {
        if ($fromAccountId === $toAccountId || bccomp($amount, '0', 2) <= 0) {
            return Response::unprocessableEntity('Invalid transfer');
        }

        if (!$this->beginTransaction()) {
            return Response::internalError($this->getError() ?? 'Could not start transaction');
        }

        try {
            $firstId = min($fromAccountId, $toAccountId);
            $secondId = max($fromAccountId, $toAccountId);

            // One SELECT … FOR UPDATE — lock order by id (deadlock-safe)
            $rows = $this->select('id,balance')
                ->whereIn('id', [$firstId, $secondId])
                ->orderBy('id', true)
                ->forUpdate()
                ->noLimit()
                ->run();

            if ($rows === null || count($rows) !== 2) {
                $this->rollback();
                return Response::notFound('Account not found');
            }

            $byId = [];
            foreach ($rows as $row) {
                $byId[$row->id] = $row->balance;
            }

            $fromBalance = $byId[$fromAccountId];
            $toBalance = $byId[$toAccountId];

            if (bccomp($fromBalance, $amount, 2) < 0) {
                $this->rollback();
                return Response::unprocessableEntity('Insufficient balance');
            }

            $newFrom = bcsub($fromBalance, $amount, 2);
            $newTo = bcadd($toBalance, $amount, 2);

            // Updates must use $this (same PdoQuery / open transaction)
            $this->id = $fromAccountId;
            $this->balance = $newFrom;
            if ($this->updateSingleQuery() === null) {
                $this->rollback();
                return Response::internalError($this->getError() ?? 'Debit failed');
            }

            $this->id = $toAccountId;
            $this->balance = $newTo;
            if ($this->updateSingleQuery() === null) {
                $this->rollback();
                return Response::internalError($this->getError() ?? 'Credit failed');
            }

            if (!$this->commit()) {
                $this->rollback();
                return Response::internalError($this->getError() ?? 'Commit failed');
            }

            return Response::success([
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $amount,
                'from_balance' => $newFrom,
                'to_balance' => $newTo,
            ], 1, 'Transfer completed');
        } catch (\Throwable $e) {
            $this->rollback();
            return Response::internalError($e->getMessage());
        }
    }
}
```

`AccountTable`: `public string $balance` + `$_type_map['balance'] = 'decimal'`.

Also: [`database.md` — Transactions & FOR UPDATE](database.md#transactions--for-update).

---

## Do / Don’t

**Do**

- Table-backed: `extends XTable` for entity CRUD  
- Composition: plain class + other Models as properties for inter-model workflows / façades  
- Keep business rules and transforms in Model (entity or composition)  
- Choose Style A or Style B and stay consistent  
- Use Table query API / views for data access; composition for cross-entity logic  
- Money transfers: `beginTransaction` + `forUpdate` + BCMath on one Table instance  

**Don’t**

- Assume every Model *must* extend Table (false — composition is first-class)  
- Assume Models *must* return `JsonResponse` (false — style choice)  
- Put business rules only in Controller  
- Invent Eloquent relations / magic `with`  
- Skip layers on HTTP services (API → Table) — possible, **strongly discouraged**
- Use `float` for money (use `decimal` + string on Table)  
- Grab `DatabaseManagerFactory…->getPdo()` for multi-step money ops  
- Leak `protected` password into list payloads  
- Expose raw child Models from a composition façade if you meant to limit their API  

---

## Checklist

1. Shape chosen: Table-backed (`extends XTable`) **or** composition (plain class + child Models)
2. Style chosen: A (`Response::*` in Model) or B (types / DTO in Model + `Response::*` in Controller)
3. Setters for secrets / transforms (Table-backed)
4. Domain validation before writes
5. Composition: `setRequest` forwarded to children; public API limited intentionally
6. Soft delete / views chosen intentionally
7. Controller uses `createModel(new …)` when Models touch DB
8. Concurrent money: same-instance tx + `forUpdate` + BCMath (not raw PDO)

---

## Reference

- Startup Table-backed: `src/startup/common/init_example/model/UserModel.php`
- Core composition (no Table): `src/core/Apm/ApmModel.php`
- Controller wiring: [controller.md](controller.md)
- Table / views / connections: [database.md](database.md)
- Atomic transfers: [Atomic money transfers](#atomic-money-transfers-pessimistic-lock)

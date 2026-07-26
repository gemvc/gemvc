# GEMVC Database Layer — `Table`

**Audience:** developers writing `app/table` · AI assistants generating Table/Model code.

**Related:** [model.md](model.md) · [controller.md](controller.md#lists-createlist) · [helper.md](helper.md) · [ecosystem.md](ecosystem.md) · [cli.md](cli.md) · [CANONICAL.md](../ai/CANONICAL.md)

---

## What `Table` does for you

Extend `Gemvc\Database\Table`. You declare **columns as properties**, a **`$_type_map`**, and **`defineSchema()`**. Then you query and save.

You do **not** need to implement or worry about:

| Concern | Handled by |
|---------|------------|
| Opening / closing DB connections | Connection packages via `DatabaseManagerFactory` |
| Connection **pooling** (OpenSwoole) or persistent PDO (Apache/Nginx) | `connection-openswoole` / `connection-pdo` — automatic by server |
| get/release around every query | `UniversalQueryExecuter` |
| Mapping object properties ↔ rows (cast, insert/update fields) | `Table` + `$_type_map` |
| Prepared statements / SQL injection safety | Query builder + executer |
| MySQL vs Postgres vs SQLite DDL differences | `DialectResolver` on migrate |
| Which PDO vs pool implementation to use | Runtime detection — same Table code everywhere |

**Your job:** model the table (or **view**), migrate base tables, call fluent queries / CRUD.  
**Not your job:** invent pools, `new PDO()`, manual hydration, server-specific connection code, or giant JOINs in PHP — use [SQL views as tables](#sql-views-as-tables-recommended) for complex reads.

Deep wiring (only if you need it): [Under the hood](#under-the-hood-connection-stack).

---

## Reading map (AI)

| Goal | Section |
|------|---------|
| Understand the abstraction | [What `Table` does for you](#what-table-does-for-you) |
| Copy a working class | [Minimal table](#minimal-table) |
| Columns & visibility | [Properties](#properties) |
| Types / decimal / money | [Type map](#type-map-_type_map) |
| Indexes, FK, unique | [Schema](#schema-defineschema) |
| UUID / string PK | [Primary keys](#primary-keys-ddl--runtime) |
| select / insert / update / delete | [Queries & CRUD](#queries--crud) |
| Soft delete | [Soft delete](#soft-delete) |
| Complex joins → SQL views | [SQL views as tables](#sql-views-as-tables-recommended) |
| Drivers & `db:migrate` | [Multi-DB & migrate](#multi-db--migrate) |
| Connection packages (advanced) | [Under the hood](#under-the-hood-connection-stack) |
| Mistakes | [Do / Don’t](#do--dont) |

---

## Hard rules (AI)

1. Extend `Table`. Implement `getTable()`, `defineSchema()`, `$_type_map`.
2. Property names = column names. `protected` = secret columns; `_prefix` = not in DB.
3. Money → `public string` + `$_type_map` `decimal` — never `float`.
4. Use Table query builder / CRUD — **never** `new PDO` or custom pools in `app/`.
5. Soft delete with `deleted_at` → `safeDeleteQuery()` / `restoreQuery()`.
6. Non-`id` PK → `setPrimaryKey(...)` after `parent::__construct()`; match `Schema::primary`.
7. Same Table class on Apache, Nginx, and OpenSwoole — do not fork connection logic.
8. Prefer **SQL views + a Table class on the view** over complex JOINs in PHP ([SQL views as tables](#sql-views-as-tables-recommended)).

---

## Minimal table

```php
<?php
namespace App\Table;

use Gemvc\Database\Table;
use Gemvc\Database\Schema;

class UserTable extends Table
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    protected string $password; // stored; hidden from list/API payloads, not from SQL SELECT *

    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
        'password' => 'string',
    ];

    public function getTable(): string
    {
        return 'users';
    }

    public function defineSchema(): array
    {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            Schema::unique('email'),
            Schema::index('name'),
        ];
    }

    public function selectByEmail(string $email): null|static
    {
        $rows = $this->select()->whereEqual('email', $email)->limit(1)->run();
        return $rows[0] ?? null;
    }
}
```

```bash
gemvc db:migrate UserTable          # library CLI
# optional: --force  --sync-schema
# codegen: gemvc create:table Product   # needs gemvc/cli-dev
```

Set `DB_*` in `.env` once. Pooling and driver selection stay invisible to this class.

---

## Properties

| Kind | In DB? | In `SELECT *` / Table hydration? | In list JSON / `createList` default cols? | In INSERT/UPDATE? |
|------|--------|-----------------------------------|-------------------------------------------|-------------------|
| `public` (initialized) | yes | yes | yes | yes |
| `public` (typed, uninitialized) | yes | yes via `SELECT *` | **often omitted** by `createList(null)` (`get_object_vars`) | yes once set |
| `protected` / `private` | yes | **yes** (`SELECT *` + reflection hydrate) | **no** (not in `get_object_vars` / list mapping) | yes |
| `_foo` | **no** | no | stripped from list payloads | no |

`protected` secrets (e.g. password) are still columns and can appear in raw `select()` results on the object; they are hidden from typical **list/API** payloads and from `createList(null)` column defaults — not excluded from SQL `SELECT *`. Prefer explicit column lists for lists.

```php
public string $email;
protected string $password;
public ?Profile $_profile;    // aggregation — ignored by CRUD/migrate
public ?string $description;  // nullable column
```

---

## Type map (`$_type_map`)

Tells migrate + casting how each **column** property maps. Do not list `_` aggregations.

```php
protected array $_type_map = [
    'id' => 'int',
    'price' => 'decimal',     // or 'decimal:12,4'
    'meta' => 'json',
];
```

| Type | Typical SQL (MySQL) | Notes |
|------|---------------------|--------|
| `int` | INT | Default PK when property is **`id`** |
| `string` | VARCHAR | `*email` names often get longer VARCHAR |
| `bool` | TINYINT(1) | PG → BOOLEAN; SQLite → INTEGER |
| `float` | DOUBLE | PG → DOUBLE PRECISION; SQLite → REAL. **Not for money** |
| `decimal` / `decimal:P,S` | DECIMAL | Pair with `public string $…`; SQLite stores as TEXT |
| `array` / `json` / `jsonb` | JSON | PG → JSONB; SQLite → TEXT |
| `datetime` | DATETIME | Mapped in dialects |
| `date` | *(falls through to TEXT today)* | Prefer `datetime` or a string column until dialects gain `date` |
| `uuid` | TEXT (all dialects today) | Runtime UUID via `setPrimaryKey(..., 'uuid')` — **not** a native UUID SQL type in migrate |

Nullable: `?string` / `?int` on the property; still list the base type in `$_type_map`.  
HTTP: `Request::decimalValuePost` / `decimalValueGet`.

---

## Schema (`defineSchema`)

Constraints for **`gemvc db:migrate`**. Empty `[]` is allowed; real apps should declare indexes/uniques.

```php
public function defineSchema(): array
{
    return [
        Schema::primary('id'),
        Schema::autoIncrement('id'),
        Schema::unique('email'),
        Schema::unique(['tenant_id', 'slug']),
        Schema::foreignKey('user_id', 'users.id')->onDeleteCascade(),
        Schema::foreignKey('role_id', 'roles.id')->onDeleteRestrict(),
        Schema::foreignKey('category_id', 'categories.id')->onDeleteSetNull(),
        Schema::index('email'),
        Schema::index(['name', 'is_active']),
        Schema::index('created_at')->name('idx_created')->timestamp(),
        Schema::check('age >= 18')->name('valid_age'),
        Schema::fullText(['name', 'description']), // MySQL
    ];
}
```

| Helper | Purpose |
|--------|---------|
| `primary` | Declared for documentation / future use — **migrate currently does not emit PK DDL from this**. Create-table PK/auto-increment comes from a property named **`id`** |
| `autoIncrement` | Same — **no DDL today**; `id` int columns get engine auto-increment from `idColumnDefinition()` |
| `unique` | Unique; optional `->name()` |
| `foreignKey` | FK + `onDeleteCascade` / `Restrict` / `SetNull` |
| `index` | Index; optional `->name()` / `->timestamp()` |
| `check` | Check constraint |
| `fullText` | MySQL fulltext (skipped on PG/SQLite) |

**AI / migrate truth:** Prefer `public int $id` for primary keys. Non-`id` PKs need careful DDL (manual or future Schema support); `setPrimaryKey()` is **runtime** ORM identity only.

---

## Primary keys (DDL + runtime)

| Concern | Reality today |
|---------|----------------|
| Create-table PK / AI | Property named **`id`** → dialect `idColumnDefinition()` |
| `Schema::primary` / `autoIncrement` | Present in API; **not applied as DDL** by current migrate |
| ORM identity (CRUD / soft delete / default order) | `setPrimaryKey($column, $type)` |

**Default (recommended):** `public int $id` → no `setPrimaryKey()` needed; migrate creates PK.

```php
public function setPrimaryKey(string $column = 'id', string $type = 'int'): self
```

| `$type` | Behavior |
|---------|----------|
| `int` | Integer PK |
| `string` | You set the value before insert |
| `uuid` | Auto-generated when empty (`generateUuid`) |

Call after `parent::__construct()`. Align `$_type_map` with the same column. For non-`id` keys, ensure the physical table PK exists (manual SQL / future Schema) — **`Schema::primary('uuid')` alone does not create a PK today**.

```php
class ProductTable extends Table
{
    public string $uuid;
    public string $name;

    protected array $_type_map = [
        'uuid' => 'string',
        'name' => 'string',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->setPrimaryKey('uuid', 'uuid'); // runtime ORM only
    }

    public function getTable(): string { return 'products'; }

    public function defineSchema(): array
    {
        // uniques/indexes/FKs here; do not rely on Schema::primary for DDL yet
        return [Schema::unique('uuid')];
    }
}
```

- **Composite DDL via `Schema::primary([...])`:** not emitted by migrate today.  
- **Composite runtime ORM:** not supported — `setPrimaryKey` is single-column only.

---

## Queries & CRUD

You write fluent queries; Table handles binding, execution, and result mapping into table objects.

```php
$rows = $this->select('id,name')
    ->whereEqual('id', $id)
    ->whereLike('name', '%x%')
    ->whereIn('status', ['active', 'pending'])
    ->whereNotIn('role', ['banned'])
    ->orderBy('name', true)   // true = ASC; false|null = DESC
    ->limit(10)
    ->run();                  // ?array of static
```

Also: `where`, `whereOr`, `join($table, $condition, $type = 'INNER')`.

| Method | Returns | Notes |
|--------|---------|--------|
| `insertSingleQuery()` | `?static` | Insert current object |
| `updateSingleQuery()` | `?static` | Update by PK |
| `deleteByIdQuery($id)` | `int\|string\|null` | Hard delete |
| `deleteSingleQuery()` | `?int` | Delete current row |
| `getError()` / `setError(?string)` | | Last error |

```php
$this->name = 'Ada';
$this->insertSingleQuery();
if ($this->getError()) { /* handle */ }
```

No `Table::offset()` / `Table::from()`. List pagination → Controller `createList()`.

---

## Soft delete

Needs `deleted_at` (optional `is_active`). Built into `Table`.

```php
$table->safeDeleteQuery();
$table->restoreQuery();
```

Prefer over hard `deleteByIdQuery` when soft delete is required.  
Also: `activateQuery($id)`, `deactivateQuery($id)`.

---

## SQL views as tables (recommended)

GEMVC is built for **microservice-style** data access: keep each service’s queries simple. For reports, dashboards, or denormalized reads that would need heavy JOINs in PHP:

1. Create a **SQL VIEW** in the database (joins, aggregates, filters live in SQL).
2. Create a **`Table` subclass** whose properties match the **view columns**.
3. Point `getTable()` at the **view name** (same as a physical table from GEMVC’s point of view).
4. Use the normal fluent `select` / `whereEqual` / `orderBy` / `limit` / `run()` API — complex SELECT becomes easy.

**Why**

- Avoids sprawling JOINs and N+1 patterns in application code  
- Lets the database optimizer own the heavy query  
- Keeps PHP typed, filterable, and list-friendly (`createList`, `findable`, …)  
- View definition can change without rewriting PHP JOIN trees  

**Read-oriented:** treat view Tables as **SELECT-first**. Do not rely on `insertSingleQuery` / `updateSingleQuery` / `deleteByIdQuery` against a view unless your engine supports updatable views and you know the rules. Writes stay on the underlying base tables.

### Example

```sql
-- Run once in the DB (migration SQL / DBA script — not gemvc db:migrate on a view class)
CREATE VIEW user_order_summary AS
SELECT
    u.id          AS user_id,
    u.name        AS user_name,
    u.email       AS email,
    COUNT(o.id)   AS order_count,
    COALESCE(SUM(o.total), 0) AS order_total
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id, u.name, u.email;
```

```php
<?php
namespace App\Table;

use Gemvc\Database\Table;

/**
 * Read model over view `user_order_summary`.
 * Properties = view output columns (exact names).
 */
class UserOrderSummaryTable extends Table
{
    public int $user_id;
    public string $user_name;
    public string $email;
    public int $order_count;
    public string $order_total; // decimal → string

    protected array $_type_map = [
        'user_id' => 'int',
        'user_name' => 'string',
        'email' => 'string',
        'order_count' => 'int',
        'order_total' => 'decimal',
    ];

    public function getTable(): string
    {
        return 'user_order_summary'; // view name
    }

    public function defineSchema(): array
    {
        // Views are not created by db:migrate — return empty (or omit unused helpers)
        return [];
    }

    public function selectByEmail(string $email): null|static
    {
        $rows = $this->select()
            ->whereEqual('email', $email)
            ->limit(1)
            ->run();
        return $rows[0] ?? null;
    }
}
```

```php
// Complex reporting SELECT — still a simple Table query
$summaries = (new UserOrderSummaryTable())
    ->select()
    ->whereEqual('order_count', 0)      // or use findable from Request in a list endpoint
    ->orderBy('order_total', false)     // DESC
    ->limit(50)
    ->run();
```

**AI rule:** Prefer a view + Table for multi-table reads inside one service. Do **not** invent Eloquent-style `hasMany` / magic joins. Across services, call HTTP APIs instead of joining foreign databases.

---

## Multi-DB & migrate

One Table class works for **MySQL / PostgreSQL / SQLite**. Set `DB_DRIVER=mysql|pgsql|sqlite`; connection packages build the DSN; migrate uses the matching dialect.

```bash
gemvc init --db=mysql|postgres|sqlite   # writes .env (Postgres → DB_DRIVER=pgsql)
gemvc db:migrate UserTable [--force] [--sync-schema]
```

| Limit | Detail |
|-------|--------|
| SQLite | No ALTER type/null/default without table rebuild — migrate **skips** those ALTERs (may set internal error; not a user-facing “drop PK” warning — drop-PK SQL is unused) |
| FULLTEXT | MySQL only; skipped on PG/SQLite |
| PK create | Prefer property **`id`**; `Schema::primary` is not DDL today |

Pooling vs simple PDO is still automatic per server — see below.

---

## Under the hood (connection stack)

Read this when debugging connections or documenting the ecosystem. **App Table authors can skip it.**

```
Table / Model
  → TableComponents\ConnectionManager   (PdoQuery helper — not the contracts manager)
    → PdoQuery
      → UniversalQueryExecuter
        → DatabaseManagerFactory::getManager()
          → PdoConnection (connection-pdo)  OR  SwooleConnection (connection-openswoole)
            → adapter → PDO
```

| Package | Role |
|---------|------|
| `gemvc/connection-contracts` | `ConnectionManagerInterface`, `ConnectionInterface` |
| `gemvc/connection-pdo` | Apache/Nginx/CLI — PDO cache / optional persistent (**not** Hyperf pool) |
| `gemvc/connection-openswoole` | OpenSwoole — true pool (get + release) |

| Axis | Who decides |
|------|-------------|
| PDO vs pool | Webserver (`WebserverDetector` → swoole / apache / nginx) |
| mysql / pgsql / sqlite | `DB_DRIVER` |

**`.env` you set:** `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Optional pool / persistent / `APP_ENV_SERVER` hints.

**Name trap:** `TableComponents\ConnectionManager` ≠ `ConnectionManagerInterface` / `PdoConnection`.  
Do not `new PdoConnection()` from `app/`. Package READMEs live under `vendor/gemvc/connection-*/`. Catalog: [ecosystem.md](ecosystem.md).

---

## Do / Don’t

**Do**

- Extend `Table` and let it own connections, pooling, and row mapping
- Complete `$_type_map`; match property names to columns
- Use `decimal` + string for money; `protected` for secrets; `_` for relations
- Query via fluent API; check `getError()` after writes
- Configure `DB_*` once
- Use **SQL views + view Table classes** for complex multi-table SELECTs

**Don’t**

- Reimplement pooling, PDO, or hydration in `app/`
- Use `float` for currency or skip `$_type_map`
- Invent Eloquent-style relations or string-concat SQL / giant JOINs in PHP
- Assume `create:table` without `gemvc/cli-dev`
- Fork Table code per webserver
- Run `db:migrate` expecting it to create views — manage view DDL in SQL separately

---

## Checklist

1. `.env` has `DB_*` (pooling chosen automatically)
2. Class `extends Table`
3. Properties + visibility correct
4. `$_type_map` complete
5. `getTable()` + `defineSchema()`
6. PK default or `setPrimaryKey` + `Schema::primary`
7. `gemvc db:migrate YourTable`

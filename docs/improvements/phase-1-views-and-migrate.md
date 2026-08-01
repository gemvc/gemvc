# Phase 1 — Views and migrate

**Status:** Implemented in `gemvc/library` (ViewTable, ViewGenerator, dialect view DDL, `db:migrate` + `--all`). Phase 1b Schema PK runtime/DDL still optional/stretch.  
**Package scope:** **`gemvc/library` only** — all implementation, CLI, dialects, docs, and tests for Phase 1 live in this repo. **Do not** change sibling packages (`cli-dev`, `helper`, connections, …) to ship Phase 1.  
**Backlog origin:** [make-gemvc-better.md](../../make-gemvc-better.md) P0 #1, #2, P1 #9.

**AI:** Phase 1 core is shipped; extend carefully. Keep work inside `gemvc/library` unless a future package task.

---

## Goals

1. **First-class SQL views via `ViewTable`** — a dedicated base class (not a plain `Table` with a flag). Developers compose views from **other normal Table classes**; migrate creates/updates a **VIEW**, never a physical table by mistake.
2. **readOnly by default** — `ViewTable` rejects `insertSingleQuery` / `updateSingleQuery` / `deleteByIdQuery` at the Table layer (optional `$_readOnly` on normal Tables remains a stretch).
3. **`db:migrate --all`** — migrate every discoverable Table / ViewTable in a **safe order** (base tables → dependents → views).

Related stretch (**Phase 1b**, same ORM/migrate area): apply `Schema::primary` at runtime (and ideally as DDL) so `setPrimaryKey()` is not the only non-`id` path. Today `SchemaGenerator::applyPrimaryKeyConstraint()` is a no-op and create-table PK comes from property named `id` via `idColumnDefinition()`.

---

## Current source gaps (verify before coding)

| Gap | Where |
|-----|--------|
| No `ViewTable` / view migrate path | `Table`, `TableGenerator`, `DbMigrate` |
| Migrating a “view” Table can `CREATE TABLE` | `TableGenerator` treats public props as columns |
| Single-class migrate only | `gemvc db:migrate TableClass` — no `--all` |
| `Schema::primary` / `autoIncrement` not DDL | `SchemaGenerator::applyPrimaryKeyConstraint` empty; AI case no-op |

---

## Proposed design

### `ViewTable` base (preferred DX)

**Do not** overload a normal `Table` with `defineView()` alone. Introduce:

```text
Table          → physical tables (CREATE TABLE)
ViewTable      → SQL views (CREATE OR REPLACE VIEW); always read-only
  extends Table (or shares traits) so select/query/list still work
```

Why a separate class:

- Clear type for migrate (`instanceof ViewTable` ⇒ view path, never CREATE TABLE).
- Developers **import and reference other Table classes** when building the view SQL — table names and (later) columns stay tied to real schema classes instead of magic strings.
- readOnly is structural, not an easy-to-forget flag.

```php
use App\Table\UserTable;
use App\Table\RoleTable;
use App\Table\ProjectTable;

/**
 * Flat columns from the SQL VIEW — same rule as Table:
 * public property name === view column / SELECT alias (1:1).
 * Without these, Model/select hydration and PHPStan have no shape.
 */
class UserAccessTable extends ViewTable
{
    public int $user_id;
    public string $email;
    public string $role_name;
    public string $project_slug;

    protected array $_type_map = [
        'user_id' => 'int',
        'email' => 'string',
        'role_name' => 'string',
        'project_slug' => 'string',
    ];

    public function getTable(): string
    {
        return 'user_access'; // view name in the database
    }

    /**
     * @return list<class-string<Table>>
     */
    public function viewDependsOn(): array
    {
        return [UserTable::class, RoleTable::class, ProjectTable::class];
    }

    /**
     * Build SELECT body using normal Table classes.
     * Framework wraps with CREATE OR REPLACE VIEW `{getTable()}` AS …
     * SELECT aliases must match public properties above.
     */
    public function defineView(): string
    {
        $users = new UserTable();
        $roles = new RoleTable();
        $projects = new ProjectTable();

        $u = $users->getTable();
        $r = $roles->getTable();
        $p = $projects->getTable();

        return <<<SQL
            SELECT
                u.id AS user_id,
                u.email,
                r.name AS role_name,
                p.slug AS project_slug
            FROM {$u} u
            INNER JOIN {$r} r ON r.user_id = u.id
            INNER JOIN {$p} p ON p.id = r.project_id
        SQL;
    }
}
```

#### Column properties (required — same as normal Table)

| Rule | Detail |
|------|--------|
| **1:1 with view columns** | Every column/alias returned by `defineView()` has a matching **public** property on the `ViewTable` subclass |
| **`$_type_map`** | Same as Table: maps those column properties for type/schema awareness |
| **Why** | SELECT hydration, Model layer, and **PHPStan level 9** need a known shape — an empty class cannot “know what is inside” |
| **Naming** | Property name = column alias (`user_id`, not nested collections) |

SQL views are **flat**. One view row → one object with scalar/typed column properties (`$row->email`, `$row->role_name`).

#### Do we need 1:n on `ViewTable`? (payments, accounts, …)

**No — not as part of Phase 1 / ViewTable core.**

A SQL `VIEW` cannot return a nested `payments[]`. It only returns columns. So `ViewTable` should not invent Eloquent-style `hasMany` / `$_payments` as a framework feature.

| Need | Do this instead |
|------|-----------------|
| User + role + project in one read | **View** with flat JOINed columns (what `ViewTable` is for) |
| User has many payments | Query **`PaymentTable`** (or a second flat view one-row-per-payment), usually from the **Model** |
| API wants `{ user, payments: [...] }` | Model loads view row + `PaymentTable` list and shapes the response |

Existing GEMVC `_` aggregation on Models still works if an app wants it — same as today on normal Tables. It is **not** required for views, migrate, or `ViewTable` acceptance.

**Out of scope for ViewTable:** first-class 1:n / nested collections / `withPayments()` framework API.

Optional stretch (same phase if small): a thin **view builder** on `ViewTable` so joins stay typed:

```php
return $this->viewQuery()
    ->from(new UserTable(), 'u')
    ->join(new RoleTable(), 'r', 'r.user_id = u.id')
    ->join(new ProjectTable(), 'p', 'p.id = r.project_id')
    ->select([
        'u.id AS user_id',
        'u.email',
        'r.name AS role_name',
        'p.slug AS project_slug',
    ])
    ->toSql();
```

v1 may ship with `defineView(): string` + Table composition via `getTable()` only; builder is nice-to-have.

### API surface: view lifecycle vs row CRUD

Two different meanings of “create / update / delete” — keep them separate.

| Concern | On `ViewTable`? | Methods (names TBD at implement time) |
|---------|-----------------|----------------------------------------|
| **View DDL** — create / replace / drop the VIEW object | **Yes** | e.g. `createViewQuery()`, `replaceViewQuery()` (maintain), `dropViewQuery()` |
| **Row DML** — insert / update / delete **rows through the view** | **No** | Do **not** expose usable `insertSingleQuery` / `updateSingleQuery` / `deleteByIdQuery` |
| **Row reads** — select / list / find | **Yes** | Inherit Table select path (`select()`, `where`, `run`, custom `selectBy…`) |

```text
Normal Table:   migrate → CREATE TABLE     + insert/update/delete rows
ViewTable:      migrate → CREATE VIEW      + select rows only
                + own helpers to create / replace / drop the VIEW definition
```

#### View lifecycle (own methods)

`ViewTable` owns **view maintenance**, not table DDL:

- **`createViewQuery()`** — `CREATE VIEW` (or `CREATE OR REPLACE` where dialect allows) from `defineView()`.
- **`replaceViewQuery()` / maintain** — recreate when SQL or deps change (what `db:migrate` on a `ViewTable` should do). Prefer one clear name so “update” is not confused with row `UPDATE`.
- **`dropViewQuery()`** — `DROP VIEW` for teardown / CLI / tests.

`db:migrate UserAccessTable` and `db:migrate --all` call these under the hood; apps can also call them from helpers/tests when needed.

#### Row writes: omit (do not “maybe” leave update/delete)

**Decision for Phase 1: no row insert, update, or delete on `ViewTable`.**

Reasons:

- JOIN views (the main use case) are not portably updatable; MySQL/Postgres/SQLite rules differ and fail in confusing ways.
- Writes belong on **base Tables** (`UserTable`, `RoleTable`, …); the view is a read model.
- Leaving `updateSingleQuery` / `deleteByIdQuery` “half working” invites silent bugs (wrong table, partial updates).

Implementation options (pick one, PHPStan-friendly):

1. **Override** inherited write methods to always set error / throw a dedicated exception (e.g. `ViewTableReadOnlyException`), or  
2. **Do not inherit** the write trait — `ViewTable` only mixes in select/query traits.

Prefer (2) if the Table base is trait-split cleanly; otherwise (1) is fine for v1.

**Out of scope:** “updatable single-table views” (row UPDATE/DELETE through the view). Revisit only if a real product need appears; not Phase 1.

#### Naming note

Avoid calling view maintain **`updateSingleQuery`**. That name means row UPDATE on normal Tables. Use **`replaceViewQuery`** / **`createViewQuery`** / **`dropViewQuery`** (or similar) so docs and autocomplete stay unambiguous.

### Rules

- **`instanceof ViewTable`** (or subclass) ⇒ view migrate path; never `CREATE TABLE` from public (column) props.
- `defineView(): string` required / abstract on `ViewTable`; empty SQL is a migrate error; **SELECT aliases match public column properties + `$_type_map`**.
- **No first-class 1:n** on `ViewTable` — flat columns only; payments/accounts via Model + base Tables (or a separate flat view).
- Migrate runs dialect-appropriate create/replace/drop view via `ViewTable` lifecycle methods (SQLite limitations documented).
- Row write APIs are unavailable or hard-fail; never silent no-op that looks like success.
- For `--all` / ordering: prefer explicit `viewDependsOn(): list<class-string<Table>>` (see example above).

### Runtime PK (Phase 1b)

- During Table construction, if `defineSchema()` contains `Schema::primary('col')` (single column), call the same path as `setPrimaryKey('col', …)` using `$_type_map` / property type.
- Prefer also emitting PK DDL from Schema when creating tables (closes the honesty gap in [database.md](../guides/database.md#primary-keys-ddl--runtime)).

### `db:migrate --all`

```bash
gemvc db:migrate --all
# optional later: --force --sync-schema (same semantics as single-class migrate)
```

Discovery:

- Scan `app/table/*Table.php` (or project table namespace).
- Classify: `ViewTable` subclasses vs normal `Table`.
- Build table graph from `Schema::foreignKey()`; view graph from `viewDependsOn()` (or equivalent).
- Order: tables with no FK deps first; then dependents; **all ViewTables after** their depended-on base tables exist.
- Fail clearly on cycles / missing referenced tables.

Single-class migrate: `gemvc db:migrate UserTable` unchanged; `gemvc db:migrate UserAccessTable` runs **view** path when class extends `ViewTable`.

---

## Impact analysis — library only

**Phase 1 ships entirely in `gemvc/library`.** No PR to `gemvc/cli-dev`, `gemvc/helper`, connection packages, http-client, or APM is required for acceptance.

App 4-layer code (API / Controller / Model) does not need framework changes to *consume* views — only new app `ViewTable` subclasses + Models that read them.

### Framework areas in this repo

| Area | Role in Phase 1 | Change? |
|------|-----------------|--------|
| **Database ORM** (`src/database/`) | `ViewTable`, select path, block row writes, dialects for VIEW DDL | **Primary** |
| **CLI** (`src/CLI/`) | `db:migrate` single + `--all`, classify Table vs ViewTable | **Primary** |
| **Schema / migrate generators** | Never CREATE TABLE for views; optional Phase 1b PK | **Update** |
| **Startup / assistant UI** | `GemvcAssistant` migrate UX uses view path for `ViewTable` | **Update** |
| **Docs + tests + skills** (this repo) | Guides, AI pack, unit/integration tests | **Update** |
| **HTTP / API / Controller / JWT / APM** | Unrelated | **None** |
| **Sibling composer packages** | Out of Phase 1 | **None** (see [Future package suggestions](#future-package-suggestions-not-phase-1)) |

```text
App:     API → Controller → Model → ViewTable (select) / Table (writes)
Library: ViewTable + ViewGenerator + dialects + DbMigrate (--all)
         Table select path reused; row writes blocked on ViewTable
         Discovery for --all stays IN library (not gemvc/helper yet)
```

---

### New classes (all in `gemvc/library`)

| Class | Path | Purpose |
|-------|------|---------|
| **`ViewTable`** | `src/database/ViewTable.php` | Abstract: `defineView()`, `viewDependsOn()`, `createViewQuery` / `replaceViewQuery` / `dropViewQuery`; extends `Table` (reuse select); no usable row CUD |
| **`ViewGenerator`** | `src/database/ViewGenerator.php` | CREATE/REPLACE/DROP VIEW from `ViewTable` (parallel to `TableGenerator`; **no** column inference from props) |
| **`ViewTableReadOnlyException`** (optional) | `src/database/` or `src/core/` | Typed failure if write methods overridden |
| **`ViewQueryBuilder`** (stretch, still library) | `src/database/ViewQueryBuilder.php` | Fluent `from(Table)->join(Table)->select()->toSql()` |
| **Migrate `--all` helpers** | Prefer private methods / small class under `src/CLI/` or `src/database/` | Scan `app/table/*`, FK graph + `viewDependsOn()`, ordered migrate — **keep in library** |

Keep **one** owner for view DDL (`ViewGenerator`) so `TableGenerator` stays table-only.

---

### Classes / files to update (`gemvc/library` only)

| File | Why |
|------|-----|
| **`src/CLI/commands/DbMigrate.php`** | `instanceof ViewTable` → view path; else table path. Add `--all`. Never `TableGenerator::createTableFromObject` on views. |
| **`src/CLI/CommandCategories.php`** | Usage for `db:migrate --all`. |
| **`src/database/Dialect/SqlDialectInterface.php`** | `createOrReplaceViewSql()`, `dropViewSql()`, `viewExists()` (+ engine honesty). |
| **`MysqlDialect` / `PostgresDialect` / `SqliteDialect`** | Implement view DDL / exists (SQLite: often DROP+CREATE). |
| **`src/database/Table.php`** | Minimal reuse for select/hydration/PK; do not put view SQL here. |
| **`CrudOperationsTrait` / `SoftDeleteOperationsTrait`** | Block writes when host is `ViewTable`, or rely on overrides in `ViewTable`. |
| **`TableGenerator.php`** | Guard: refuse if object is `ViewTable`. |
| **`SchemaGenerator.php`** | Skip table constraints on views; **Phase 1b:** `applyPrimaryKeyConstraint` + runtime PK from `defineSchema()`. |
| **`Schema.php`** | Phase 1b clarity only; no `Schema::view()` required if `defineView()` is the API. |
| **Startup assistant** (`GemvcAssistant*`, `tables.php`, spa migrate) | Detect `ViewTable` → view migrate; fix “create table” wording. |
| **Startup examples** (optional) | Sample `ViewTable` under `init_example`. |
| **`docs/guides/*`, `docs/ai/*`, `docs/releases/*`, skills** | Document library behavior. |
| **`tests/Unit/Database/*`** | ViewTable / ViewGenerator / dialect / `--all` tests. |

**Unchanged in library:** `QueryBuilder`, `Select`/`WhereTrait`, `PdoQuery`, connection managers, Request/Response, ApiService, Controller, JWT, APM wiring.

**Do not touch for Phase 1:** any code under `vendor/gemvc/*` sibling package sources (those are separate repos).

---

### Future package suggestions (not Phase 1)

Comment for later — **out of Phase 1 acceptance**. Implement only after library ViewTable + migrate are stable.

| Suggestion | Package (future) | Why later |
|------------|------------------|-----------|
| **`db:list` include VIEWs** + show type (TABLE vs VIEW) | `gemvc/cli-dev` (`DbList`) | Today lists BASE TABLE only; polish DX, not required to create/use views via `db:migrate` |
| **`db:describe` label VIEW** and describe view columns | `gemvc/cli-dev` (`DbDescribe`) | Same; library migrate can still succeed without it |
| **Shared project table scanner** (`app/table` discovery) | Optionally extract to `gemvc/helper` (`ProjectHelper`) | Fine to keep private in library `DbMigrate` for Phase 1; extract if cli-dev / other tools need the same scan |
| **Fluent view builder as shared util** | Unlikely — keep in library; only split if a non-library consumer needs SQL-only builders without Table | Prefer stay in `gemvc/library` |
| Connection / http-client / APM changes | — | **Not needed** for views; APM already wraps Table queries if enabled |

Phase 1 acceptance must **not** depend on releasing a new `cli-dev` or `helper` version.

---

### What apps change (consumer services)

When library Phase 1 ships:

- `class X extends ViewTable` in `app/table/`
- Models read flat rows; reshape JSON in Model
- Writes on normal `*Table`
- `gemvc db:migrate` / `--all` from **library** CLI

---

### Ownership (Phase 1)

| Workstream | Owner |
|------------|--------|
| Everything above (ViewTable, dialects, ViewGenerator, DbMigrate, docs, tests, startup UI) | **`gemvc/library` only** |
| cli-dev list/describe polish | Future — see suggestions |
| helper extraction | Future — optional |

---

## Work packages

All work packages are **`gemvc/library`**:

1. **`ViewTable` base** — `defineView()`, lifecycle methods, `viewDependsOn()`, block row CUD + tests.
2. **`ViewGenerator` + dialect view DDL** — create/replace/drop/exists (Mysql / Postgres / Sqlite honesty).
3. **`DbMigrate`** — detect `ViewTable` vs `Table`; single-class view migrate; guard `TableGenerator`.
4. **`db:migrate --all`** — discovery + FK order + views last (helpers stay in library).
5. **Startup assistant** — migrate UI safe for views.
6. **Docs + AI skills + changelog** (this repo).
7. **Phase 1b (optional same library release):** Schema primary → runtime (+ DDL if feasible).
8. **Stretch (library):** fluent `viewQuery()` builder.

---

## Acceptance criteria

Library-only — no sibling package release required:

- [x] `ViewTable` + column props / `$_type_map` / `defineView()` ship in `gemvc/library`; PHPStan 9.
- [x] `db:migrate ViewTableClass` creates/replaces a **VIEW** (never a physical table from props).
- [x] View lifecycle helpers exist; migrate uses them.
- [x] Row insert/update/delete on `ViewTable` unavailable or hard-fail.
- [x] `db:migrate --all` (library CLI) migrates tables then views via FK + `viewDependsOn`.
- [x] Existing `db:migrate UserTable` unchanged for normal tables.
- [x] Dialect `viewExists` usable from library tests; documented in database guide.
- [x] Library guides + tests updated; Phase 1b claims only if shipped.
- [x] **No** required changes to `cli-dev` / `helper` / connections for this release.

---

## Out of scope (Phase 1)

- Any **sibling package** code changes (cli-dev, helper, …) — see [Future package suggestions](#future-package-suggestions-not-phase-1)
- First-class **1:n / nested collections** on `ViewTable`
- Internal service trust / `serviceCall` (Phase 2)
- Redis/Kafka family secrets
- Password schema type, renewToken hooks, first-admin install

---

## Suggested implementation order

1. `ViewTable` + blocked row writes + tests (library)
2. Dialects + `ViewGenerator` + `DbMigrate` single-class view path
3. `db:migrate --all` in library
4. Startup assistant + docs
5. Phase 1b Schema PK if capacity remains
6. Optional fluent view builder (library)
7. *(Later, other repos)* cli-dev list/describe — not blocking

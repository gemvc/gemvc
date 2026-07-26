# GEMVC Documentation

Single entry point for humans and AI assistants. Framework version: **5.9.1**.

## For AI assistants (read first)

Mandatory path (3 files only):

1. [`ai/INDEX.md`](ai/INDEX.md) — reading order and hard rules  
2. [`ai/CANONICAL.md`](ai/CANONICAL.md) — 4-layer architecture, auth, CRUD, Do/Don’t  
3. [`ai/API_REFERENCE.md`](ai/API_REFERENCE.md) — Request / Response / Table / Controller signatures  

Then open topical guides only when needed.

---

## The 4 layers (read in this order)

Every request walks the same stack. **Do not skip layers.**

```
HTTP → API → Controller → Model → Table → DB
         ↑         ↑          ↑        ↑
      schema    map +      rules +   SQL /
      + auth    createList  types or   CRUD
                            JsonResponse
```

| Layer | Folder | Job |
|-------|--------|-----|
| **1. API** | `app/api/` | Validate input, authenticate/authorize, call Controller |
| **2. Controller** | `app/controller/` | Orchestrate: map request → Model, call Model / `createList` |
| **3. Model** | `app/model/` | Business rules, transforms, domain ops; returns `JsonResponse` **or** PHP types (style choice) |
| **4. Table** | `app/table/` | Columns, schema, queries, insert/update/delete only |

URL mapping is automatic: `/api/{Service}/{method}` → `App\Api\{Service}::{method}()`.

---

### 1. API layer

**What it does:** Thin HTTP boundary. Schema (`definePostSchema` / `defineGetSchema`), auth (`auth()` / `requireAuth()`), list allowlists (`findable` / `sortable`), then `callController(...)` (Apache) or bare `new` (Swoole). No business rules here.

There is no single `api.md` yet — use these for API work:

| Need | Guide |
|------|--------|
| Request/Response, server adapters | [http-lifecycle.md](guides/http-lifecycle.md) |
| Schema validation, JWT, 401 vs 403 | [security.md](guides/security.md) |
| HTML docs + Postman (`@http`, mocks) | [api-documentation.md](guides/api-documentation.md) |
| Patterns + CRUD skeleton | [ai/CANONICAL.md](ai/CANONICAL.md) |

---

### 2. Controller layer — [controller.md](guides/controller.md)

**What it does:** Orchestration only. Map POST/PUT/PATCH onto Model (`mapPostToObject`, …), wrap with `createModel()` for APM, call Model methods, or `createList()` for paginated lists. Apache uses `callController`; Swoole does not.

Covers: 4-layer role; mapping; `createModel` / `createList` + list GET params; protected columns; errors; CLI templates; Do/Don’t.

---

### 3. Model layer — [model.md](guides/model.md)

**What it does:** Where **logic** lives. `XModel extends XTable`. Simple Models are thin CRUD wrappers; domain Models add validation, setters (`setPassword`), login, aggregations (`_profile`), multi-step ops. Return style is a **developer choice**: Model may return `JsonResponse`, or return objects/`null`/other PHP types while Controller builds `JsonResponse`.

Covers: return style A vs B; simple vs domain; CRUD; transforms; validation; `_` aggregations; SQL views; APM; Do/Don’t.

---

### 4. Table layer — [database.md](guides/database.md)

**What it does:** Database only. Properties = columns, `defineSchema()`, query builder, CRUD helpers. Prefer **SQL views as Table classes** for JOIN-heavy reads instead of inventing ORM relations.

Covers: what `Table` abstracts; skeleton; types/PKs; CRUD; soft delete; views; multi-DB; connection packages under the hood.

---

## Supporting guides

Use after the layer guides, or when onboarding / ops / tooling.

### [architecture.md](guides/architecture.md)
Framework internals and request flow: `src/` tree; design principles; Apache vs OpenSwoole diagrams; Bootstrap, ApiService, Controller, Security, Request/Response, Table; URL mapping. *Where code lives* — not Table schemas or JWT deep-dives.

### [installation.md](guides/installation.md)
Empty folder → first API call: Composer, `gemvc init`, `.env`, Docker vs bare metal, sample User, Product CRUD, troubleshooting.

### [ecosystem.md](guides/ecosystem.md)
Multi-package map under `vendor/gemvc/` (library, connections, APM, helper, http-client, cli-base / cli-dev). Read before inventing replacements.

### [cli.md](guides/cli.md)
CLI reference: `cli-base` vs require-dev **`cli-dev`** (`create:*`, `db:*`, `admin:*`). Commands, workflows, custom commands. **Do not assume `create:crud` without cli-dev.**

### [templates.md](guides/templates.md)
Customizing `create:*` output via `{project}/templates/cli/`. Needs **cli-dev**.

### [apm.md](guides/apm.md)
TraceKit (and others): root span, env flags, **`callController` / `createModel`**, exceptions, `ApmTracingTrait`, troubleshooting.

### [http-lifecycle.md](guides/http-lifecycle.md) · [security.md](guides/security.md) · [api-documentation.md](guides/api-documentation.md)
Also listed under [API layer](#1-api-layer) — full detail for Request adapters, hardening, and auto docs.

---

## Ops & history

### [ops/mysql-production.md](ops/mysql-production.md)
Init MySQL Docker is **dev-only**. Production: InnoDB durability, binlog, passwords, buffer sizing, go-live checklist.

### [releases/RELEASE_NOTES.md](releases/RELEASE_NOTES.md)
Narrative notes (what/why/migration), including 5.9.x multi-DB, decimal, cli-dev, `requireAuth`.

### [releases/CHANGELOG.md](releases/CHANGELOG.md)
Keep-a-Changelog bullets for “is feature X in version Y?”

---

## Sibling packages (not duplicated here)

- `gemvc/cli-dev` — `create:*`, `db:init|list|describe|drop|unique`, `admin:*`
- `gemvc/helper` — TypeChecker, CryptHelper, FileHelper, …
- `gemvc/cli-base` — `vendor/gemvc/cli-base/AI-Assistant.md`
- `gemvc/connection-pdo` — runtime DB connections (`DB_DRIVER`)

# GEMVC Documentation

Single entry point for humans and AI assistants. Framework version: **5.9.1**.

## For AI assistants (read first)

Mandatory path (3 files only):

1. [`ai/INDEX.md`](ai/INDEX.md) — reading order and hard rules  
2. [`ai/CANONICAL.md`](ai/CANONICAL.md) — 4-layer architecture, auth, CRUD, Do/Don’t  
3. [`ai/CORE_REFERENCE.md`](ai/CORE_REFERENCE.md) — framework class signatures (Request / Response / Table / Controller)  

Then open topical guides only when needed.

---

## The 4 layers (read in this order)

Every HTTP request should walk the same stack. **Strongly recommended — do not skip layers** (runtime allows it; architecture does not benefit).

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
| **3. Model** | `app/model/` | Business rules / workflows; Table-backed **or** composition (no Table); `JsonResponse` **or** PHP types |
| **4. Table** | `app/table/` | Columns, schema, queries, insert/update/delete only |

URL mapping is automatic: `/api/{Service}/{method}` → `App\Api\{Service}::{method}()`.

---

### 1. API layer — [api.md](guides/api.md)

**What it does:** Thin HTTP boundary. Schema (`definePostSchema` / `defineGetSchema`), auth (`auth()` / `requireAuth()`), list allowlists (`findable` / `sortable`), then `callController(...)` (Apache) or bare `new` (Swoole). No business rules here.

Covers: `ApiService` vs `SwooleApiService`; auth; schemas; list allowlists; Controller invoke; `@http` docs; CLI; Do/Don’t.

Deeper: [http-lifecycle.md](guides/http-lifecycle.md) · [security.md](guides/security.md) · [api-documentation.md](guides/api-documentation.md)

---

### 2. Controller layer — [controller.md](guides/controller.md)

**What it does:** Orchestration only. Map POST/PUT/PATCH onto Model (`mapPostToObject`, …), wrap with `createModel()` for APM, call Model methods, or `createList()` for paginated lists. Apache uses `callController`; Swoole does not.

Covers: 4-layer role; mapping; `createModel` / `createList` + list GET params; protected columns; errors; CLI templates; Do/Don’t.

---

### 3. Model layer — [model.md](guides/model.md)

**What it does:** Where **logic** lives. Two shapes: **Table-backed** (`XModel extends XTable`) or **composition** (plain class holding other Models — inter-model workflows, façades, typed result objects). Simple vs domain; return Style A/B (`JsonResponse` or PHP types). Aggregations, views, APM.

Covers: return style; composition Models; simple vs domain; CRUD; transforms; validation; `_` aggregations; SQL views; APM; Do/Don’t.

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

### [cli.md](guides/cli.md) · [cli-reference.md](guides/cli-reference.md)
CLI entry (package split, workflows) + full command catalog. **AI: start with cli.md**; open cli-reference only for a specific flag. **Do not assume `create:crud` without cli-dev.**

### [templates.md](guides/templates.md)
Customizing `create:*` output via `{project}/templates/cli/`. Needs **cli-dev**.

### [apm.md](guides/apm.md)
TraceKit (and others): root span, env flags, **`callController` / `createModel`**, exceptions, `ApmTracingTrait`, troubleshooting.

### [http-lifecycle.md](guides/http-lifecycle.md) · [security.md](guides/security.md) · [api-documentation.md](guides/api-documentation.md)
Also linked from [API layer](#1-api-layer--apimd) — adapters, hardening, auto docs. Prefer [api.md](guides/api.md) first for writing `app/api`.

---

## Ops & history

### [ops/mysql-production.md](ops/mysql-production.md)
Init MySQL Docker is **dev-only**. Production: InnoDB durability, binlog, passwords, buffer sizing, go-live checklist.

### [releases/README.md](releases/README.md)
When to open release notes vs changelog. **AI: skip unless version/migration task.**

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

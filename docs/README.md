# GEMVC Documentation

Single entry point for humans and AI assistants. Framework version: **5.13.0**.

## For AI assistants (read first)

**Tool front doors** (same truth; use the one your agent loads):

| File | Audience |
|------|----------|
| [`../AGENTS.md`](../AGENTS.md) | Universal (Copilot, …) |
| [`../CLAUDE.md`](../CLAUDE.md) | Claude Code |
| [`../GEMINI.md`](../GEMINI.md) | Antigravity (overrides `AGENTS.md` on conflict) |
| [`../.cursorrules`](../.cursorrules) | Cursor |
| [`../llms.txt`](../llms.txt) | LLM / crawler map |

Mandatory path (3 files only):

1. [`ai/INDEX.md`](ai/INDEX.md) — reading order and hard rules  
2. [`ai/CANONICAL.md`](ai/CANONICAL.md) — 4-layer architecture, auth, CRUD, Do/Don’t  
3. [`ai/CORE_REFERENCE.md`](ai/CORE_REFERENCE.md) — framework class signatures (Request / Response / Table / ViewTable / Controller)  

Then open topical guides only when needed.

---

## The 4 layers (read in this order)

Every HTTP request should walk the same stack. **Strongly recommended — do not skip layers** (runtime allows it; architecture does not benefit).

```
HTTP → API → Controller → Model → Table / ViewTable → DB
         ↑         ↑          ↑              ↑
      schema    map +      rules +      SQL / CRUD
      + auth    createList  types or    or VIEW select
                            JsonResponse
```

| Layer | Folder | Job |
|-------|--------|-----|
| **1. API** | `app/api/` | Validate input, authenticate/authorize, call Controller |
| **2. Controller** | `app/controller/` | Orchestrate: map request → Model, call Model / `createList` |
| **3. Model** | `app/model/` | Business rules / workflows; Table-backed **or** composition (no Table); `JsonResponse` **or** PHP types |
| **4. Table** | `app/table/` | Physical tables (`Table`) or SQL views (`ViewTable`); columns, schema/view SQL, queries |

URL mapping (Apache/Nginx): `/api/{Service}/{method}` → `App\Api\{Service}::{method}()`.  
OpenSwoole: configure `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (no automatic `api` hop) — see [architecture.md](guides/architecture.md).

---

### 1. API layer — [api.md](guides/api.md)

**What it does:** Thin HTTP boundary. Schema, auth, **list allowlists** (`findable` / `filterable` / `sortable` — flagship with `createList`), then `callController(...)`. Prefer `ApiService` / `ProtectedApiService` on all servers. No business rules here.

Covers: unified `ApiService` / `ProtectedApiService` (deprecated `Swoole*` aliases); auth; schemas; list allowlists; Controller invoke; `@http` docs; CLI; Do/Don’t.

Deeper: [http-lifecycle.md](guides/http-lifecycle.md) · [security.md](guides/security.md) · [api-documentation.md](guides/api-documentation.md)

---

### 2. Controller layer — [controller.md](guides/controller.md)

**What it does:** Orchestration only. Map POST/PUT/PATCH onto Model, `createModel()` for APM, Model methods, or **flagship `createList()`** (filter / LIKE / sort / paginate from API allowlists). Prefer `callController` on all servers.

Covers: 4-layer role; mapping; `createModel` / `createList` + list GET params; protected columns; errors; CLI templates; Do/Don’t.

---

### 3. Model layer — [model.md](guides/model.md)

**What it does:** Where **logic** lives. Two shapes: **Table-backed** (`XModel extends XTable`) or **composition** (plain class holding other Models — inter-model workflows, façades, typed result objects). Simple vs domain; return Style A/B (`JsonResponse` or PHP types). Aggregations, views, APM.

Covers: return style; composition Models; simple vs domain; CRUD; transforms; validation; `_` aggregations; SQL views; APM; **atomic money transfers** (`beginTransaction` + `forUpdate` + BCMath); Do/Don’t.

---

### 4. Table layer — [database.md](guides/database.md)

**What it does:** Database only. Properties = columns (or view aliases). Physical tables via `Table` + `defineSchema()`; **SQL views via `ViewTable`** + `defineView()` / `viewDependsOn()`. Fluent select; transactions / `forUpdate`; row CRUD on tables only (views are read-only). Migrate: `gemvc db:migrate ClassName` or `gemvc db:migrate --all`.

Covers: what `Table` / `ViewTable` abstract; skeleton; types/PKs; CRUD; soft delete; views; multi-DB; connection packages under the hood.

---

## Supporting guides

Use after the layer guides, or when onboarding / ops / tooling.

### [architecture.md](guides/architecture.md)
Framework internals and request flow: `src/` tree; design principles; Apache vs OpenSwoole diagrams; Bootstrap, ApiService, Controller, Security, Request/Response, Table; URL mapping. *Where code lives* — not Table schemas or JWT deep-dives.

### [installation.md](guides/installation.md)
Empty folder → first API call: Composer, `gemvc init`, `.env`, Docker vs bare metal, sample User, Product CRUD, troubleshooting.

### [ecosystem.md](guides/ecosystem.md)
Multi-package map under `vendor/gemvc/`. **Core:** **`gemvc/helper`**, **`gemvc/http-client`**, plus connections, APM, cli-base / cli-dev. Read before inventing replacements.

### [helper.md](guides/helper.md)
**`gemvc/helper`** — TypeChecker (schema types), CryptHelper, ProjectHelper, File/Image helpers. Powers `define*Schema`. See also `vendor/gemvc/helper/README.md`.

### [http-client.md](guides/http-client.md)
**`gemvc/http-client`** — outbound sync/async/Swoole HTTP. Not inbound Request. See also `vendor/gemvc/http-client/README.md`.

### [cli.md](guides/cli.md) · [cli-reference.md](guides/cli-reference.md)
CLI entry (package split, workflows) + full command catalog. **AI: start with cli.md**; open cli-reference only for a specific flag. **Do not assume `create:crud` without cli-dev.**

### [templates.md](guides/templates.md)
Customizing `create:*` output via `{project}/templates/cli/`. Needs **cli-dev**.

### [apm.md](guides/apm.md)
**`gemvc/apm-contracts`** (`ApmFactory` / `ApmInterface`) + providers (e.g. TraceKit): root span, unified `APM_*` flags, **`callController` / `createModel`**, exceptions, `ApmTracingTrait`.

### [http-lifecycle.md](guides/http-lifecycle.md) · [openswoole.md](guides/openswoole.md) · [security.md](guides/security.md) · [api-documentation.md](guides/api-documentation.md)
Adapters and hardening; **[openswoole.md](guides/openswoole.md)** is the canonical answer for OpenSwoole isolation, pooling, no-`die()`, and production FAQ. Prefer [api.md](guides/api.md) first for writing `app/api`.

---

## Releases

### [releases/README.md](releases/README.md)
When to open release notes vs changelog. **AI: skip unless version/migration task.**

### [releases/RELEASE_NOTES.md](releases/RELEASE_NOTES.md)
Narrative notes (what/why/migration), including **5.13.0** unified `ApiService`, **5.12.0** rate-limit drivers / Protected API / `forUpdate`, **5.11.0 ViewTable** / `db:migrate --all`, 5.9.x multi-DB, decimal, cli-dev, `requireAuth`.

### [releases/CHANGELOG.md](releases/CHANGELOG.md)
Keep-a-Changelog bullets for “is feature X in version Y?”

---

## Sibling packages (not duplicated here)

- **`gemvc/helper`** — TypeChecker, CryptHelper, ProjectHelper, FileHelper, … → [guides/helper.md](guides/helper.md)
- **`gemvc/http-client`** — outbound HttpClient / AsyncHttpClient → [guides/http-client.md](guides/http-client.md)
- **`gemvc/apm-contracts`** — `ApmFactory` / `ApmInterface` (required with library) → [guides/apm.md](guides/apm.md)
- `gemvc/apm-tracekit` — one APM provider (example); not the abstraction
- `gemvc/cli-dev` — `create:*`, `db:init|list|describe|drop|unique`, `admin:*`
- `gemvc/cli-base` — `vendor/gemvc/cli-base/AI-Assistant.md`
- `gemvc/connection-pdo` — runtime DB connections (`DB_DRIVER`)

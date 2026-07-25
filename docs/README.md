# GEMVC Documentation

Single entry point for humans and AI assistants. Framework version: **5.9.1**.

## For AI assistants (read first)

Mandatory path (3 files only):

1. [`ai/INDEX.md`](ai/INDEX.md) — reading order and hard rules  
2. [`ai/CANONICAL.md`](ai/CANONICAL.md) — 4-layer architecture, `requireAuth()`, CRUD patterns, decimal, multi-DB, CLI split, Do/Don’t  
3. [`ai/API_REFERENCE.md`](ai/API_REFERENCE.md) — Request/Response/Table/Controller signatures and schema types  

Then open topical guides only when needed. Each guide summary below lists **exactly** what that file contains.

## Guides

### [ecosystem.md](guides/ecosystem.md)
**GEMVC is a multi-package ecosystem.** Maps every `gemvc/*` module in `vendor/gemvc/`: `library` hub; DB contracts + PDO/OpenSwoole pools; APM contracts + TraceKit; `helper` (TypeChecker/CryptHelper); outbound `http-client`; `cli-base` vs require-dev `cli-dev`; diagrams and “need X → package Y” table. Read this before inventing replacements for packages that already exist.

### [architecture.md](guides/architecture.md)
Map of framework internals and request flow. Covers the `src/` tree (`CLI/`, `core/`, `http/`, `database/`, `helper/`, `startup/`, `stubs/`); design principles (webserver-agnostic `app/` code, automatic security, environment-aware adapters, CLI codegen); full **Apache/Nginx vs OpenSwoole** flow diagrams; component breakdown of Bootstrap, ApiService, Controller, SecurityManager, Request/Response/JWT, Table/QueryBuilder/migrations; where APM hooks sit; URL-to-class mapping (`/api/{Service}/{method}`); design patterns; short CLI cheat sheet. Use to learn *where* code lives — not for Table schemas (see database) or JWT details (see security).

### [installation.md](guides/installation.md)
From empty folder to first API call. Prerequisites (PHP 8.2+, Composer, MySQL/Postgres/SQLite, optional Docker/OpenSwoole/Redis); `composer require`; interactive/non-interactive `gemvc init` (server, **DB driver**, PHPStan, Docker); what init creates; `.env` per driver; Docker Compose vs bare-metal start; optional sample User migrate; Product `create:crud` (needs `cli-dev`); verification checklist; troubleshooting (port 9501, DB, class not found, permissions); Docker commands; Swoole/Apache/Nginx notes; next-steps links.

### [database.md](guides/database.md)
Writing and migrating Table classes. Rules: extend `Table`; required `getTable()`, `defineSchema()`, `$_type_map`; type vocabulary including **`decimal` / `decimal:p,s`**; Schema helpers (`primary`, `autoIncrement`, `unique`, `foreignKey`, `index`, `check`, `fullText`); property visibility (`public` / `protected` / `_`); nullable types; full UserTable example; best practices; `db:migrate` workflow; PHP↔SQL map; **multi-DB** dialect limits (SQLite ALTER, no FULLTEXT on PG/SQLite); **soft delete** (`safeDeleteQuery` / `restoreQuery`). Read before any new table or column change.

### [http-lifecycle.md](guides/http-lifecycle.md)
How HTTP becomes unified `Request` and leaves as `JsonResponse` without changing `app/` when switching servers. Architecture diagram; Apache/Nginx and OpenSwoole lifecycles; `ApacheRequest` / `SwooleRequest` adapters (sanitization, cookies, body); Request object fields (`post`/`get`/`put`/`patch`/`files`, auth, pagination); Response `show()` vs `showSwoole()`; flow diagrams; XSS sanitization examples; server-agnostic app examples. For adapters/Request/Response — not JWT (security) or ORM (database).

### [cli.md](guides/cli.md)
Full CLI reference and package split: `gemvc/cli-base` (foundation), `gemvc/library` (`init*`, `db:migrate`, Docker), require-dev **`gemvc/cli-dev`** (`create:*`, `db:init|list|describe|drop|unique`, `admin:*`). Architecture of Command/AbstractInit/Init*/CommandCategories/Docker helpers; command discovery; every command with flags; workflows; troubleshooting (missing cli-dev, templates, DB, colors); writing custom commands / new webserver init. **Do not assume `create:crud` without cli-dev.**

### [security.md](guides/security.md)
Automatic vs developer-called security across eight layers: path blocking, header sanitization, XSS cleaning, schema validation (`define*Schema`, optional fields, types), JWT (`auth()` / **`requireAuth()`**, **401 vs 403**), file name/MIME/signature/encryption, prepared-statement SQL; attack-flow example; CryptHelper passwords; `.env` security vars; production checklist; best practices; incident notes. For auth/uploads/hardening — not ORM migrations.

### [apm.md](guides/apm.md)
APM (TraceKit and others). Root span from Bootstrap; env (`APM_NAME`, keys, sample rate, `APM_TRACE_CONTROLLER`, `APM_TRACE_DB_QUERY`); **`callController()`** / **`createModel()`** for controller and DB spans; exception recording; `ApmTracingTrait` (`traceApm`, start/end span); examples; best practices; sample-rate/performance; troubleshooting missing traces / split traceIds; custom provider and CLI jobs. For enabling/debugging APM only.

### [api-documentation.md](guides/api-documentation.md)
HTML docs at `/api/index/document` + Postman export. `ApiDocGenerator` reflection; directives `@http`, `@description`, `@example`, `@hidden`; auto parameter tables from schemas / `findable` / `sortable`; `mockResponse()`; full annotated example; UI contents; AI codegen best practices (always add directives + mocks). For documenting endpoints — not auth or Table schema.

### [templates.md](guides/templates.md)
Customizing `create:*` output. How init copies `{project}/templates/cli/`; editing service/controller/model/table templates; variables and replacement; project-vs-vendor lookup; customization examples; version-control tips; advanced variables; troubleshooting missing templates / unreplaced placeholders. Needs **`gemvc/cli-dev`** for create commands.

## Ops, design & history

### [ops/mysql-production.md](ops/mysql-production.md)
Why init MySQL Docker settings are **dev-only**, and production changes: InnoDB durability, binary logging, auth/passwords, buffer sizing; ~8GB RAM example; go-live checklist; managed DB / HA notes. Skip for local SQLite or default demos.

### [design/primary-key.md](design/primary-key.md)
ADR for flexible PKs (`int` default, `string`, `uuid` auto-generate, future composites): proposed API, sketches, examples, compatibility. For extending ORM PKs — **not** everyday `Schema::primary('id')`.

### [releases/RELEASE_NOTES.md](releases/RELEASE_NOTES.md)
Narrative release notes (what/why/migration samples), including 5.9.x multi-DB, decimal, cli-dev, `requireAuth`, docs reorg.

### [releases/CHANGELOG.md](releases/CHANGELOG.md)
Keep-a-Changelog bullets (Added/Fixed/Changed) for quick “is feature X in version Y?” checks.

## Sibling packages (not duplicated here)

- `gemvc/cli-dev` — `create:*`, `db:init|list|describe|drop|unique`, `admin:*`
- `gemvc/helper` — TypeChecker, CryptHelper, FileHelper, …
- `gemvc/cli-base` — `vendor/gemvc/cli-base/AI-Assistant.md`
- `gemvc/connection-pdo` — runtime DB connections (`DB_DRIVER`)

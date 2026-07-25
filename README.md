![gemvc-tracekit](https://github.com/user-attachments/assets/c730d3b8-877f-4793-9261-34ca392cf692)

# [GEMVC](https://www.gemvc.de) — PHP multi-platform REST API framework

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/releases/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Swoole](https://img.shields.io/badge/Swoole-Supported-green.svg?style=flat-square&logo=swoole&logoColor=white)](https://openswoole.com/)
[![Apache](https://img.shields.io/badge/Apache-Supported-D22128.svg?style=flat-square&logo=apache&logoColor=white)](https://httpd.apache.org/)
[![Nginx](https://img.shields.io/badge/Nginx-Supported-009639.svg?style=flat-square&logo=nginx&logoColor=white)](https://nginx.org/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg?style=flat-square)](https://phpstan.org/)

**Latest:** 5.9.1 — MySQL / PostgreSQL / SQLite, `requireAuth()`, decimal types, modular CLI (`gemvc/cli-dev`).

**GEMVC is an ecosystem** of Composer packages (`gemvc/library` + connection, APM, helper, HTTP client, CLI modules). See [docs/guides/ecosystem.md](docs/guides/ecosystem.md).

## Start in 30 seconds

```bash
composer require gemvc/library
php vendor/bin/gemvc init
# optional codegen + db introspection:
composer require --dev gemvc/cli-dev
```

Same application code runs on **OpenSwoole**, **Apache**, and **Nginx**.

## Documentation (all under `docs/`)

**Index:** [docs/README.md](docs/README.md)

### For AI assistants

GEMVC is **not** Laravel or Symfony. Do **not** invent routes files or Eloquent patterns.

Read these three files in order (mandatory):

1. [docs/ai/INDEX.md](docs/ai/INDEX.md) — reading order and hard rules  
2. [docs/ai/CANONICAL.md](docs/ai/CANONICAL.md) — 4-layer architecture, `requireAuth()`, CRUD patterns, decimal, multi-DB, CLI split, Do/Don’t  
3. [docs/ai/API_REFERENCE.md](docs/ai/API_REFERENCE.md) — Request/Response/Table/Controller method signatures and schema types  

Cursor also loads [`.cursorrules`](.cursorrules), which points at the same AI pack.

Optional mirrors: [docs/ai/api-reference.jsonc](docs/ai/api-reference.jsonc), [docs/ai/phpdoc-reference.php](docs/ai/phpdoc-reference.php).

### Guides (humans + deep dives)

Open a guide only when you need that topic. Each summary lists **exactly** what the file contains.

#### [docs/guides/ecosystem.md](docs/guides/ecosystem.md)
**Start here if you think GEMVC is “just one package.”** Catalog of every `gemvc/*` Composer module under `vendor/gemvc/`: how `library` is the hub; **contracts vs implementations** for DB (`connection-contracts` + `connection-pdo` / `connection-openswoole`) and APM (`apm-contracts` + `apm-tracekit`); `helper` (TypeChecker, CryptHelper, ProjectHelper); `http-client` (outbound sync/async calls); CLI split (`cli-base` foundation vs require-dev `cli-dev` codegen); dependency diagram; decision table (“need X → which package”); hard rules so AIs do not reinvent Laravel helpers or ignore `vendor/gemvc/*/README.md`.

#### [docs/guides/architecture.md](docs/guides/architecture.md)
Map of the framework internals and how a request moves through GEMVC. Covers the `src/` tree (`CLI/`, `core/`, `http/`, `database/`, `helper/`, `startup/`, `stubs/`); the four design principles (webserver-agnostic `app/` code, automatic security, environment-aware adapters, CLI codegen); full **Apache/Nginx vs OpenSwoole** request-flow diagrams; component breakdown of Bootstrap, ApiService, Controller, SecurityManager, Request/Response/JWT, Table/QueryBuilder/migrations; where APM hooks sit; URL-to-class mapping (`/api/{Service}/{method}`); design patterns used; and a short CLI command cheat sheet. Use this to understand *where* code lives and *which* bootstrap path runs — not for writing Table schemas (see database guide).

#### [docs/guides/installation.md](docs/guides/installation.md)
End-to-end “from empty folder to first API call”. Prerequisites (PHP 8.2+, Composer, MySQL/Postgres/SQLite, optional Docker/OpenSwoole/Redis); `composer require gemvc/library`; interactive and non-interactive `gemvc init` (server type, **database driver**, PHPStan, Docker); what files/folders init creates; `.env` database configuration per driver; **Option A** Docker Compose start vs **Option B** bare-metal OpenSwoole/Apache/Nginx; optional `db:init` / `db:migrate` for the sample User; generating a Product service with `create:crud` (needs `cli-dev`); verification checklist; troubleshooting (port 9501, DB connection, class not found, permissions); Docker command reference; server-specific notes for Swoole/Apache/Nginx; next-steps links into the rest of the docs.

#### [docs/guides/database.md](docs/guides/database.md)
How to write and migrate Table-layer classes. Rules: every table extends `Table`; required `getTable()`, `defineSchema()`, and `$_type_map`; full type-map vocabulary (`int`, `string`, `bool`, `float`, **`decimal` / `decimal:p,s`**, dates, etc.); Schema helpers with examples — `primary`, `autoIncrement`, `unique`, `foreignKey` (+ onDelete/onUpdate), `index`, `check`, `fullText` (MySQL); property rules (`public` vs `protected` vs `_` aggregation, nullable `?types`); complete `UserTable` example; best practices; migration workflow (`gemvc db:migrate`); PHP↔SQL type mapping table; **multi-DB** notes (MySQL/Postgres/SQLite dialects, SQLite ALTER limits, no FULLTEXT on PG/SQLite); **soft delete** via `safeDeleteQuery()` / `restoreQuery()`. Use this before creating any new table or changing columns.

#### [docs/guides/http-lifecycle.md](docs/guides/http-lifecycle.md)
How HTTP arrives as a unified `Gemvc\Http\Request` and leaves as `JsonResponse`, without changing `app/` code when switching servers. Server-agnostic architecture diagram; step-by-step **Apache/Nginx** and **OpenSwoole** lifecycles; deep dive into `ApacheRequest` and `SwooleRequest` adapters (what they sanitize, cookies, body parsing); structure of the unified Request object (`post`/`get`/`put`/`patch`/`files`, auth flags, pagination helpers); Response abstraction (`show()` vs `showSwoole()`); complete flow diagrams; automatic XSS/input sanitization examples; application-level examples that stay identical across servers. Use this to understand adapters and Request/Response — not JWT details (see security) or Table ORM (see database).

#### [docs/guides/cli.md](docs/guides/cli.md)
Authoritative CLI reference and package split. Explains **three packages**: `gemvc/cli-base` (Command foundation), `gemvc/library` (`init*`, `db:migrate`, Docker helpers), and require-dev **`gemvc/cli-dev`** (`create:*`, `db:init|list|describe|drop|unique`, `admin:*`). Architecture of Command / AbstractInit / InitProject / InitApache|Swoole|Nginx / CommandCategories / DockerComposeInit / ProjectHelper; how commands are discovered; full command docs with flags for `init`, `create:service|controller|model|table|crud`, every `db:*` and `admin:*` command; examples and workflows; troubleshooting (command not found without cli-dev, templates, DB errors, macOS colors); tips; how to write a custom command or add a webserver init strategy. **Do not assume `create:crud` exists unless cli-dev is installed.**

#### [docs/guides/security.md](docs/guides/security.md)
Full security model: what is automatic vs what you must call. Eight layers — path blocking (`SecurityManager`), header sanitization, XSS input cleaning, schema validation (`definePostSchema` / optional `?fields` / type checks), JWT auth (`auth()` and **`requireAuth()`**, token create/verify, roles, **401 Unauthorized vs 403 Forbidden**), file security (name/MIME/signature/encryption), prepared-statement SQL protection; complete attack-flow example; layer summary table; CryptHelper password hashing; `.env` security-related vars; production checklist; best practices; incident response notes. Use this when implementing auth, uploads, or hardening — not for ORM migration syntax.

#### [docs/guides/apm.md](docs/guides/apm.md)
Application Performance Monitoring integration (TraceKit and other providers). Zero-config root request span from Bootstrap; env vars (`APM_NAME`, API keys, sample rate, `APM_TRACE_CONTROLLER`, `APM_TRACE_DB_QUERY`); how **`callController()`** creates controller spans and **`createModel()`** wires Request for DB query spans; exception recording; `ApmTracingTrait` API (`traceApm`, `startApmSpan`/`endApmSpan`, …); CRUD and custom-tracing examples; best practices (always use `createModel`, meaningful attributes, span kinds); performance/sample-rate guidance; troubleshooting missing traces / split traceIds; advanced custom provider and CLI/job tracing. Use this when enabling or debugging APM — not for basic CRUD without tracing.

#### [docs/guides/api-documentation.md](docs/guides/api-documentation.md)
Built-in HTML API docs at `/api/index/document` (and Postman export). How `ApiDocGenerator` reflects `app/api` classes; PHPDoc directives `@http`, `@description`, `@example`, `@hidden`, optional `@param`; **automatic** parameter tables from `definePostSchema` / `defineGetSchema` / `findable` / `sortable`; `mockResponse(string $method): array` for sample payloads; complete annotated service example; what the generated UI includes; best practices for AI-generated code (always add directives + mocks). Use this when documenting endpoints or generating Postman collections — not for Request auth or Table schema.

#### [docs/guides/templates.md](docs/guides/templates.md)
Customizing what `gemvc create:*` / `create:crud` emit. How `gemvc init` copies templates to `{project}/templates/cli/`; editing `service.template`, `controller.template`, `model.template`, `table.template`; template variables (`{{ServiceName}}`, etc.) and replacement rules; lookup order (project templates override vendor); customization examples (comments, structure, helper methods); best practices (version-control templates, test after edit); advanced custom variables; troubleshooting “template not found” / unreplaced placeholders. Requires **`gemvc/cli-dev`** for the create commands that consume these templates.

### Ops, design & history

#### [docs/ops/mysql-production.md](docs/ops/mysql-production.md)
Why `gemvc init` MySQL Docker settings are **dev-only**, and what DevOps must change for production: InnoDB flush durability, binary logging, auth plugins/passwords, buffer/pool sizing; full example config for ~8GB RAM; pre-go-live checklist and monitoring metrics; notes on managed DB / HA alternatives. Not needed for local SQLite or default init demos.

#### [docs/design/primary-key.md](docs/design/primary-key.md)
Design/ADR document for flexible primary keys (`int` default, `string`, `uuid` with auto-generate, future composite keys). Proposed config API, implementation sketches, usage examples, migration/compatibility guarantees. Read if extending the ORM PK system — **not** a tutorial for everyday `Schema::primary('id')` tables.

#### [docs/releases/RELEASE_NOTES.md](docs/releases/RELEASE_NOTES.md)
Long-form release narratives: what shipped, why, migration notes, code samples (e.g. 5.9.0 multi-DB/decimal/cli-dev, 5.9.1 `requireAuth` + docs reorg). Prefer this when you need context; use CHANGELOG for a short bullet scan.

#### [docs/releases/CHANGELOG.md](docs/releases/CHANGELOG.md)
Keep-a-Changelog list (Added/Fixed/Changed) from recent versions back through older releases. Best for “did version X include feature Y?” without reading full release essays.

## What GEMVC is

- **4-layer** API → Controller → Model → Table (recommended, not forced)
- **No routes file** — `/api/{Service}/{method}` maps automatically
- **~90% security automatic** — sanitize inputs, prepared statements, path protection; you add schema + auth
- **Sanitization IS documentation** — `definePostSchema()` feeds `/api/index/document` + Postman export
- **Native APM** — `callController()` / `createModel()` + env flags
- **Library or framework** — migrate gradually or `gemvc init` for a full app

Not a Laravel/Symfony replacement — a focused scalpel for REST microservices.

## Architecture (quick)

```
app/api/          → endpoints + validation
app/controller/   → orchestration
app/model/        → business rules
app/table/        → database
```

## License

[MIT License](LICENSE) · [gemvc.de](https://www.gemvc.de)

![gemvc-tracekit](https://github.com/user-attachments/assets/c730d3b8-877f-4793-9261-34ca392cf692)

# [GEMVC](https://www.gemvc.de) — PHP multi-platform REST API framework

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/releases/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Swoole](https://img.shields.io/badge/Swoole-Supported-green.svg?style=flat-square&logo=swoole&logoColor=white)](https://openswoole.com/)
[![Apache](https://img.shields.io/badge/Apache-Supported-D22128.svg?style=flat-square&logo=apache&logoColor=white)](https://httpd.apache.org/)
[![Nginx](https://img.shields.io/badge/Nginx-Supported-009639.svg?style=flat-square&logo=nginx&logoColor=white)](https://nginx.org/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg?style=flat-square)](https://phpstan.org/)

**Latest:** 5.13.0 — unified `ApiService` / `ProtectedApiService` for all servers (`validateOrFail`, `ApiServiceSharedTrait`, deprecated `Swoole*` aliases). Also **5.12** rate-limit drivers + `Protected*` + `forUpdate()`, **ViewTable**, multi-DB, `requireAuth()`, decimal types, modular CLI.

## Before You Continue

GEMVC is an opinionated framework.

Before evaluating the framework or reading the API documentation, read:

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — *why* GEMVC is shaped this way (philosophy)
- [`docs/guides/architecture.md`](docs/guides/architecture.md) — *how* requests flow through the code

Understanding the architectural assumptions behind GEMVC is essential.

> **AI coding agents (Claude Code, Antigravity, Cursor, Copilot, …):** start at [`AGENTS.md`](AGENTS.md) (Claude: [`CLAUDE.md`](CLAUDE.md); Antigravity: [`GEMINI.md`](GEMINI.md)), then **mandatory** [`docs/ai/INDEX.md`](docs/ai/INDEX.md) → [`CANONICAL.md`](docs/ai/CANONICAL.md) → [`CORE_REFERENCE.md`](docs/ai/CORE_REFERENCE.md). GEMVC is **not** Laravel/Symfony — do not invent routes or Eloquent. Machine map: [`llms.txt`](llms.txt).

**GEMVC is an ecosystem** of Composer packages (`gemvc/library` + connection, APM, helper, HTTP client, CLI modules). See [docs/guides/ecosystem.md](docs/guides/ecosystem.md).

## Start in 30 seconds

```bash
composer require gemvc/library
php vendor/bin/gemvc init
# optional codegen + db introspection:
composer require --dev gemvc/cli-dev
```

Same application code runs on **OpenSwoole**, **Apache**, and **Nginx**.

## What GEMVC is

- **Server-agnostic** — your code works the same on OpenSwoole, Nginx, and Apache
- **4-layer** API → Controller → Model → Table / **ViewTable** — **strongly recommended**. You *can* bypass a layer and the runtime still works; do that only with a clear reason. Skipping layers is how services become hard to test, secure, and reason about.
- **Modular ecosystem** — **`gemvc/helper`** (types, crypto, paths) + **`gemvc/http-client`** (outbound HTTP) + connections, APM, CLI — not one monolith package
- **No routes file** — Apache/Nginx: `/api/{Service}/{method}` maps automatically; OpenSwoole uses `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (see [architecture.md](docs/guides/architecture.md))
- **~90% security automatic** — sanitize inputs, prepared statements, path protection; you add schema + auth
- **Schema is documentation** — `definePostSchema()` feeds `/api/index/document` + Postman export (types from **`gemvc/helper` → TypeChecker**)
- **Powerful lists** — API allowlists + Controller `createList()` (see below)
- **Outbound HTTP** — **`gemvc/http-client`** sync/async/Swoole-aware (do not invent curl wrappers)
- **Native APM** — **`gemvc/apm-contracts`** + provider (`APM_NAME`); app uses `callController()` / `createModel()` + `APM_*` flags
- **Library or framework** — migrate gradually or `gemvc init` for a full app

Not a Laravel/Symfony replacement — a focused scalpel for REST microservices.

## Architecture (quick)

After the request reaches the server, Bootstrap sanitizes the incoming request and payload, then builds a **single cross-server `Request` object**. From the URL it resolves the target class and method (or returns 404). It instantiates the **API** layer class, injects `Request`, and calls the method.

```
app/api/          → endpoints + validation
app/controller/   → orchestration
app/model/        → business rules / workflows
app/table/        → database (Table or ViewTable)
```

### `app/api/` — endpoints + validation

Strong request sanitization lives here. As a developer you can:

- Guard a whole service with **`ProtectedApiService`** / **`ProtectedSwooleApiService`** (preferred), or `$this->requireAuth(['role'])` on `ApiService`, or `$this->request->auth(['role'])` per method
- Optional rate limit: global `REQUEST_RATE_LIMIT_PER_SEC` + `REQUEST_RATE_LIMIT_DRIVER` (`apcu`|`redis`|`both`|`none`), or `$this->requireRateLimit()` / `requireRateLimitApcu|Redis|Both()` (IP and/or JWT → 429). No automatic store fallback.
- Define exact POST / GET / PUT / PATCH schemas on each endpoint with powerful types (`string`, `email`, `url`, `ip`, …)
- Then call the Controller — Apache: `callController(...)`; OpenSwoole: `new XController($this->request)` — and pass the sanitized `Request`

No business rules here. Details: [api.md](docs/guides/api.md) · [security](docs/guides/security.md) · [http-lifecycle](docs/guides/http-lifecycle.md) · [api docs](docs/guides/api-documentation.md)

### `app/controller/` — orchestration

Map the sanitized request onto a Model with powerful `mapPostToObject` / `mapPutToObject` / `mapPatchToObject`, prefer `createModel()` so Request/APM reach DB work, call Model methods or `createList()`, and **return `JsonResponse`**.

Keep Controllers thin on domain rules. Details: [controller.md](docs/guides/controller.md)

### `app/model/` — business rules

Where logic lives. Two shapes:

1. **Table-backed** — `UserModel extends UserTable`: CRUD, setters (`setPassword`), uniqueness, login, `_` aggregations
2. **Composition** — plain class that holds other Models as properties: inter-model workflows, façades, typed result objects; you expose or hide child methods as you wish

Return style is yours: Model may return `JsonResponse`, or any PHP type (`?self`, DTO, `array`, `bool`, …) while Controller builds `Response::*`.

Details: [model.md](docs/guides/model.md)

### `app/table/` — database

Columns as typed properties, `$_type_map`. Physical tables: `extends Table` + `defineSchema()`. **SQL views: `extends ViewTable`** + `defineView()` / `viewDependsOn()` — migrate with `gemvc db:migrate` or `--all` (read-only for row writes). Multi-DB via connection packages under the hood.

Details: [database.md](docs/guides/database.md)

### Flagship: lists (`createList`)

One of GEMVC’s strongest DX + security features. **No free-form query SQL** — you allowlist fields in the API; the Controller applies them.

```php
// API — allowlist + type-check GET params
$this->request->findable(['name' => 'string', 'email' => 'email']);   // find_like=
$this->request->filterable(['role' => 'string']);                    // filter_by=
$this->request->sortable(['id', 'name', 'created_at']);              // sort_by / sort_by_asc
return $this->callController(new UserController($this->request))->list();

// Controller — one call: filter + LIKE + sort + page + columns + total count + APM
return $this->createList(
    $this->createModel(new UserModel()),
    'id,name,email,role,created_at'
);
```

| GET param | API method | Effect |
|-----------|------------|--------|
| `find_like=name=ali` | `findable` | `WHERE … LIKE` |
| `filter_by=role=admin` | `filterable` | exact `WHERE` |
| `sort_by` / `sort_by_asc` | `sortable` | `ORDER BY` |
| `page_number` | (built-in) | pagination + `getTotalCounts()` |

Full detail: [controller.md — Lists](docs/guides/controller.md#lists-createlist) · [api.md — List allowlists](docs/guides/api.md#list-allowlists)

### Core packages: `helper` + `http-client`

Two of the most important GEMVC packages (required with `gemvc/library`):

| Package | Job | Guide |
|---------|-----|--------|
| **`gemvc/helper`** | `TypeChecker` (schema types), `CryptHelper` (passwords), `ProjectHelper`, File/Image helpers | [helper.md](docs/guides/helper.md) · `vendor/gemvc/helper/README.md` |
| **`gemvc/http-client`** | Outbound sync/async HTTP (Apache curl / Swoole coroutines) — **not** inbound Request | [http-client.md](docs/guides/http-client.md) · `vendor/gemvc/http-client/README.md` |

**AI:** Prefer these packages over inventing validators, `password_hash` wrappers, or Guzzle/curl clones. Full map: [ecosystem.md](docs/guides/ecosystem.md).

---

## Documentation (all under `docs/`)

**Index:** [docs/README.md](docs/README.md)

### For AI assistants

GEMVC is **not** Laravel or Symfony. Do **not** invent routes files or Eloquent patterns.

**Front doors (pick your tool, then the same pack):**

| Tool | Start here |
|------|------------|
| Any agent | [`AGENTS.md`](AGENTS.md) |
| Claude Code | [`CLAUDE.md`](CLAUDE.md) |
| Antigravity | [`GEMINI.md`](GEMINI.md) (overrides `AGENTS.md` on conflict) |
| Cursor | [`.cursorrules`](.cursorrules) |
| Catalog / crawlers | [`llms.txt`](llms.txt) |

Then read these three files in order (mandatory):

1. [docs/ai/INDEX.md](docs/ai/INDEX.md) — reading order and hard rules  
2. [docs/ai/CANONICAL.md](docs/ai/CANONICAL.md) — 4-layer architecture, `requireAuth()`, CRUD patterns, decimal, multi-DB, CLI split, Do/Don’t  
3. [docs/ai/CORE_REFERENCE.md](docs/ai/CORE_REFERENCE.md) — framework class signatures (Request/Response/Table/ViewTable/Controller) — not HTTP endpoint docs  

Optional mirrors: [docs/ai/core-reference.jsonc](docs/ai/core-reference.jsonc), [docs/ai/phpdoc-reference.php](docs/ai/phpdoc-reference.php).

### Guides (humans + deep dives)

Open a guide only when you need that topic. Prefer the **layer order**: API → controller → model → database, then supporting topics.

| Layer / topic | Guide |
|---------------|--------|
| Ecosystem (not one package) | [ecosystem.md](docs/guides/ecosystem.md) |
| **`gemvc/helper`** | [helper.md](docs/guides/helper.md) |
| **`gemvc/http-client`** | [http-client.md](docs/guides/http-client.md) |
| Internals / request flow | [architecture.md](docs/guides/architecture.md) |
| Install → first API call | [installation.md](docs/guides/installation.md) |
| **API** | [api.md](docs/guides/api.md) |
| **Controller** | [controller.md](docs/guides/controller.md) |
| **Model** | [model.md](docs/guides/model.md) |
| **Table / DB** | [database.md](docs/guides/database.md) |
| HTTP Request lifecycle | [http-lifecycle.md](docs/guides/http-lifecycle.md) |
| Security / JWT | [security.md](docs/guides/security.md) |
| CLI + cli-dev | [cli.md](docs/guides/cli.md) · [cli-reference.md](docs/guides/cli-reference.md) |
| APM | [apm.md](docs/guides/apm.md) |
| Auto API docs | [api-documentation.md](docs/guides/api-documentation.md) |
| Codegen templates | [templates.md](docs/guides/templates.md) |

Summaries of what each file contains: [docs/README.md](docs/README.md).

### Releases

- [docs/releases/README.md](docs/releases/README.md) — when to read notes vs changelog (AI: skip unless version task)  
- [docs/releases/RELEASE_NOTES.md](docs/releases/RELEASE_NOTES.md) — narrative what/why/migration  
- [docs/releases/CHANGELOG.md](docs/releases/CHANGELOG.md) — short “is feature X in version Y?”

## License

[MIT License](LICENSE) · [gemvc.de](https://www.gemvc.de)

# GEMVC architecture (code-grounded)

Read after CANONICAL. Confirm in `src/` when changing behavior.
Verified against `docs/` + `src/` / `vendor/gemvc/` (2026-08).

## Request flows

### Apache / Nginx

```
HTTP
 → src/startup/apache|nginx/index.php
 → StandardHttpRequest (sanitize headers/body → shared Request)
 → Bootstrap (APM root; setRequestedService)
 → load app/api/{Service}.php → App\Api\{Service} extends ApiService
 → method (schema / requireAuth / requireRateLimit)
 → callController → ControllerTracingProxy → Controller
 → createModel → Model → Table (DB span if APM_TRACE_DB_QUERY=1)
 → JsonResponse::show() → APM flush → die
```

Routing in `Bootstrap::setRequestedService()` (`src/core/Bootstrap.php`):

- Segments from URL path; index = `SERVICE_IN_URL_SECTION` (default **1**)
- If that segment is `"api"`: next = Service (`ucfirst`), then method → **API**
- Else → **web** (`App\Web\…`)
- Root `/` → `Index` / `index`
- **`METHOD_IN_URL_SECTION` is ignored here** (Swoole-only)

Example: `/api/User/create` → `App\Api\User::create()`.

### OpenSwoole

```
HTTP
 → OpenSwooleServer
 → SecurityManager::isRequestAllowed (path normalize; block /app, /vendor, .env, .php, …)
 → SwooleRequest → SwooleBootstrap (APM; extractRouteInfo)
 → processRequest() → App\Api\{Service} extends ApiService (or deprecated SwooleApiService)
 → callController → ControllerTracingProxy → Controller

Full OpenSwoole contract (isolation, pool, no-die, FAQ): [docs/guides/openswoole.md](../../../docs/guides/openswoole.md).
 → Model → Table (pooled connection)
 → showSwoole() → APM flush
```

Routing in `SwooleBootstrap::extractRouteInfo()`:

- **No** automatic `"api"` hop
- Service = `SERVICE_IN_URL_SECTION` (default 1), method = `METHOD_IN_URL_SECTION` (default 2)
- Dev root `/` → `Developer` / `app`
- Missing service file → `Response::notFound` **return** (worker stays alive)
- With defaults `1`/`2`, path `/User/create` is correct; `/api/User/create` wrongly resolves service=`Api`

## Dual bases (public API)

| Concern | `ApiService` (recommended) | `SwooleApiService` (deprecated) |
|---------|--------------|-------------------|
| File | `src/core/ApiService.php` | `src/core/SwooleApiService.php` extends `ApiService` |
| Shared | `ApiServiceSharedTrait` | inherited |
| APM controller wrap | `callController()`, `__get` | inherited |
| Auth default | Prefer `ProtectedApiService` | Prefer `ProtectedApiService` (or deprecated `ProtectedSwooleApiService`) |
| `validatePosts` / `validateStringPosts` | throws `ValidationException` | same (inherited throws) |
| Legacy return-style | — | `safeValidatePosts` / `safeValidateStringPosts` |
| `requireAuth` / `requireRateLimit*` | throw; Bootstrap / SwooleBootstrap catch | inherited |

Schema API shared: `Request::definePostSchema` / `defineGetSchema` → `bool` (no throw). Prefer that, or `validateOrFail()`, for portable code.

## Auth status codes (`Request::auth` → `authenticate` / `authorize`)

| Situation | HTTP |
|-----------|------|
| No / unextractable `Authorization` | **401** |
| Token present but `verify()` fails | **403** |
| Valid token, wrong role | **403** |

Roles: comma-separated names on JWT; `requireAuth(['admin'])` needs one match. `requireAuth(null|[])` = any authenticated user.

Constructor `requireAuth` works because Bootstrap wraps construct + invoke in try/catch. Returning a `JsonResponse` from the constructor does **not** stop the method — must throw.

## Rate limit

**Global (automatic):** `enforceFromEnv()` when `REQUEST_RATE_LIMIT_PER_SEC` > 0. Env: `DRIVER` (`apcu`|`redis`|`both`|`none`), `PER_SEC`, `BLOCK_SECONDS`, `SCOPE`, `FAIL_MODE`.

**Per-service:** `requireRateLimit()` uses global driver; `requireRateLimitApcu|Redis|Both()` force a store. **No auto-fallback.** Unavailable chosen backend → FAIL_MODE (default closed / 429).

**Production:** match `REQUEST_RATE_LIMIT_DRIVER` to infra; use `redis`/`both` for multi-node; keep `FAIL_MODE=closed`; prefer edge/proxy limits too.

## Uploads (Apache vs Swoole)

- **Apache:** `$request->files` = `$_FILES['file']` only (field name must be `file`); name/MIME **not** sanitized
- **Swoole:** `SwooleRequest` normalizes uploads into PHP-like shape and sanitizes name/MIME
- Signatures / encryption: developer calls (`ImageHelper` / `FileHelper`)

## Table ORM (invariants)

- `getTable(): string` required; columns = public properties; `$_type_map` for casting
- Keys starting with `_` skipped on insert/update (aggregations)
- `protected` still DB columns; often hidden from list/API defaults — prefer explicit `createList` columns
- **Create-table PK/AI:** property named **`id`** → dialect `idColumnDefinition()` (MySQL `INT… PRIMARY KEY`, Postgres `SERIAL PRIMARY KEY`)
- **`Schema::primary` / `autoIncrement`:** API exists; `SchemaGenerator::applyPrimaryKeyConstraint` is a **no-op today** — not DDL
- **Runtime ORM identity:** `_detectPrimaryKey()` prefers `id`; else call `setPrimaryKey($col, 'int'|'string'|'uuid')` **after** `parent::__construct()`
- SQL views: extend **`ViewTable`**, `defineView()` + column props; `db:migrate` / `--all` creates VIEW; row writes blocked

## Query path vs QueryBuilder

- Normal app path: `Table::select()->where()->run()` → `UniversalQueryExecuter` (+ optional DB APM via Request)
- Transactions / locks: same Table instance — `beginTransaction()` → `forUpdate()` on SELECT → updates on `$this` → `commit`/`rollback`. Never raw `DatabaseManagerFactory…->getPdo()` for multi-step money — [model.md — Atomic money transfers](../../../docs/guides/model.md#atomic-money-transfers-pessimistic-lock)
- `QueryBuilder` (`src/database/QueryBuilder.php`): separate ad-hoc Select/Insert/Update/Delete; check `getError()` after build
- Migrate DDL: `TableGenerator` + `ViewGenerator` + `SchemaGenerator` + `DialectResolver` (PDO driver → mysql/pgsql/sqlite; unknown/mock → Mysql)

## APM

- `ApmFactory` from **`gemvc/apm-contracts`** — `APM_NAME` → `Gemvc\Core\Apm\Providers\{Name}\{Name}Provider`
- TraceKit package autoloads that NS from `vendor/gemvc/apm-tracekit/src/` (`TraceKitProvider`)
- Env: unified `APM_*`; TraceKit also `TRACEKIT_ENDPOINT` (not `TRACEKIT_API_URL`), `TRACEKIT_API_KEY`, …
- Root span: Bootstrap / SwooleBootstrap
- Controller spans: `APM_TRACE_CONTROLLER=1` **and** `callController` (Apache **and** OpenSwoole)
- DB spans: `APM_TRACE_DB_QUERY=1` via `createModel` → Request on Table / `UniversalQueryExecuter`
- Never hardcode TraceKit in app or library app-facing APIs

## Connections

- `DatabaseManagerFactory::getManager()` picks PDO vs OpenSwoole pool via `WebserverDetector`
- Table → `UniversalQueryExecuter` → getConnection(`default`) → always release
- Apache/Nginx/FrankenPHP classic: `PdoConnection` (cached; persistent default on)
- OpenSwoole: Hyperf pool (`MIN_DB_CONNECTION_POOL` / `MAX_DB_CONNECTION_POOL`); per-worker; must release
- FrankenPHP edge security: **Caddyfile** only — never ship `.htaccess` for path denies

## Helper / outbound HTTP

- Validation types: **`Gemvc\Helper\TypeChecker`** (`decimal`, `uuid`, `slug`, `positive_int`, …)
- Crypto: `CryptHelper::hashPassword` (Argon2i)
- Outbound: `Gemvc\Http\Client\HttpClient` (sync), `AsyncHttpClient::fireAndForget` (FPM), `SwooleHttpClient` (Swoole)

## CLI + templates

| | |
|--|--|
| Library | `init`, `db:migrate` (`--default <value>` space-separated, `--force`, `--sync-schema`, …) |
| cli-dev | `create:*`, `db:init\|list\|describe\|drop\|unique`, `admin:*` |
| Flags | `create:service User -cmt` (chars `c`/`m`/`t`); `create:crud` → `-cmt`; `db:drop --force`; `db:unique table/col` |
| Templates | `{project}/templates/cli/*.template` then `vendor/gemvc/cli-dev/templates/cli/` |
| Init copy | copies **library** `src/CLI/templates` (often views) — **not** create stubs; copy cli-dev templates once if needed |

## Auto docs

- `ApiDocGenerator` reflects `app/api`, reads `@http`, `@description`, `@example`, `@hidden`, regexes `define*Schema` / list allowlists
- UI: `/api/index/document` via `Documentation`
- Default HTTP method if `@http` missing: **POST**; generated paths may omit `/api` prefix — keep examples accurate

## Further reading

- [source-map.md](source-map.md) — class → file map + footguns
- [docs/guides/architecture.md](../../../docs/guides/architecture.md) — full diagrams
- [docs/guides/http-lifecycle.md](../../../docs/guides/http-lifecycle.md)
- [docs/guides/database.md](../../../docs/guides/database.md) — PK DDL vs runtime
- [docs/guides/templates.md](../../../docs/guides/templates.md) — codegen template order

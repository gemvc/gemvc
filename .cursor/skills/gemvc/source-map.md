# GEMVC source map (must-know)

Follow [protocol.md](protocol.md): examine `vendor/gemvc` **and** `src/` before proposing changes. Confirm in these files before inventing APIs. Paths relative to repo root.
Verified against `docs/` + source (2026-08).

## Lifecycle / routing

| Class | Path | Must know |
|-------|------|-----------|
| `Bootstrap` | `src/core/Bootstrap.php` | Apache/Nginx; `setRequestedService`; **`api` hop**; ignores `METHOD_IN_URL_SECTION`; may `die` |
| `SwooleBootstrap` | `src/core/SwooleBootstrap.php` | `extractRouteInfo`; **no** `api` hop; uses `SERVICE_IN_URL_SECTION` + `METHOD_IN_URL_SECTION`; `processRequest()` returns |
| `OpenSwooleServer` | `src/core/OpenSwooleServer.php` | Security → SwooleRequest → bootstrap → showSwoole → APM flush |
| `WebserverDetector` | `src/core/WebserverDetector.php` | apache / nginx / swoole detection |
| Apache entry | `src/startup/apache/index.php` | Dotenv → ApacheRequest → Bootstrap |
| Nginx entry | `src/startup/nginx/index.php` | Same pattern as Apache |
| Swoole entry | `src/startup/swoole/index.php` | `OpenSwooleServer::start()` |

## API / controller

| Class | Path | Must know |
|-------|------|-----------|
| `ApiService` | `src/core/ApiService.php` | `requireAuth`, `requireRateLimit`, `callController`, magic `__get` |
| `SwooleApiService` | `src/core/SwooleApiService.php` | No callController; validate* returns `?JsonResponse` |
| `Controller` | `src/core/Controller.php` | `createModel`, `createList`, `listJsonResponse` |
| `ControllerTracingProxy` | (in ApiService file) | Optional controller spans |

## HTTP / auth / uploads

| Class | Path | Must know |
|-------|------|-----------|
| `Request` | `src/http/Request.php` | schemas, `auth`/`authenticate`/`authorize`, findable/filterable/sortable, map*ToObject |
| `ApacheRequest` | `src/http/ApacheRequest.php` | Sanitizes GET/POST/PUT/PATCH/headers; **`$files` = `$_FILES['file']` only**; no upload MIME sanitize |
| `SwooleRequest` | `src/http/SwooleRequest.php` | Normalizes files; sanitizes upload name/MIME |
| `JWTToken` | `src/http/JWTToken.php` | HS256 create/verify; roles, claims |
| `Response` / `JsonResponse` | `src/http/` | Factories; `show` vs `showSwoole`; `tooManyRequests` (429) |
| `AuthException` | `src/core/AuthException.php` | From `requireAuth` |
| `RateLimiter` / `RateLimitException` | `src/core/` | APCu; 429; fail-open if missing; fail-closed after full-cache purge+retry |
| `ValidationException` | `src/core/ValidationException.php` | Apache validate* path → 400 |

## Security / errors

| Class | Path | Must know |
|-------|------|-----------|
| `SecurityManager` | `src/core/SecurityManager.php` | **OpenSwoole only** early path/extension deny + normalize |
| Apache `.htaccess` | `src/startup/apache/.htaccess` | Rewrite + deny sensitive paths |
| `GemvcError` / `GEMVCErrorHandler` | `src/core/` | Structured JSON errors |

## ORM / DB

| Class | Path | Must know |
|-------|------|-----------|
| `Table` | `src/database/Table.php` | Fluent select/where/run; `_detectPrimaryKey`; `setPrimaryKey` after parent ctor |
| `CrudOperationsTrait` | `src/database/TableComponents/` | insert/update/delete; skip `_` props |
| `PropertyCaster` | `src/database/TableComponents/` | `$_type_map` incl. decimal-as-string |
| `Schema` | `src/database/Schema.php` | Migrate constraints DSL |
| `SchemaGenerator` | `src/database/SchemaGenerator.php` | Applies unique/index/FK/check; **`applyPrimaryKeyConstraint` is no-op** |
| `TableGenerator` | `src/database/TableGenerator.php` | Create/sync columns; **`id` → `idColumnDefinition()`** |
| `DialectResolver` + dialects | `src/database/Dialect/` | mysql / pgsql / sqlite; unknown → Mysql |
| `UniversalQueryExecuter` | `src/database/UniversalQueryExecuter.php` | PDO exec + DB APM from Request |
| `QueryBuilder` | `src/database/QueryBuilder.php` | Ad-hoc SQL builder (**not** Table fluent) |

Connections live in **`gemvc/connection-pdo`** / **`connection-openswoole`** (not under `src/`).

## Docs / CLI / APM

| Piece | Path | Must know |
|-------|------|-----------|
| `ApiDocGenerator` | `src/core/ApiDocGenerator.php` | Reflect API + `@http` + schema regex |
| `Documentation` | `src/core/Documentation.php` | HTML/JSON doc UI |
| CLI entry | `bin/gemvc` | Dispatch; library has `init`, `db:migrate`; cli-dev commands via class_exists |
| Init / migrate | `src/CLI/commands/` | InitProject, DbMigrate (`--default <value>`, `--force`, …) |
| APM trait / test | `src/core/Apm/` | Tracing helpers; providers are external packages |
| `ApmFactory` | `vendor/gemvc/apm-contracts/.../ApmFactory.php` | `APM_NAME` → `Providers\{Name}\{Name}Provider` |
| TraceKit | `vendor/gemvc/apm-tracekit/src/TraceKitProvider.php` | NS `Gemvc\Core\Apm\Providers\TraceKit\`; `TRACEKIT_ENDPOINT` |

## Ecosystem (`vendor/gemvc/` — critical scan)

Installed here (this repo has **no** top-level `packages/`). Library engine is `src/`, not under `vendor/gemvc/library`.

| Package path | Job / entry points |
|--------------|-------------------|
| `vendor/gemvc/helper/src/` | `TypeChecker`, `CryptHelper`, `ProjectHelper`, File/Image/Json helpers |
| `vendor/gemvc/http-client/src/` | `HttpClient`, `AsyncHttpClient` (`fireAndForget`), `SwooleHttpClient` |
| `vendor/gemvc/apm-contracts/src/` | `ApmInterface`, `ApmFactory`, abstract APM |
| `vendor/gemvc/apm-tracekit/src/` | `TraceKitProvider`, `TraceKitToolkit` |
| `vendor/gemvc/connection-contracts/src/` | Connection / manager interfaces |
| `vendor/gemvc/connection-pdo/src/` | PDO adapter (Apache/Nginx/CLI) |
| `vendor/gemvc/connection-openswoole/src/` | Pooled Swoole connections |
| `vendor/gemvc/cli-base/src/` | `Command`, generators base; `FileSystemManager::copyTemplatesFolder` → library `src/CLI/templates` |
| `vendor/gemvc/cli-dev/src/Commands/` | `CreateCrud`, `Create*`, `DbList`/`DbDrop`/…, `Admin*` |
| `vendor/gemvc/cli-dev/templates/cli/` | Default `*.template` for codegen |

## Connections (vendor)

| Piece | Path | Must know |
|-------|------|-----------|
| `DatabaseManagerFactory` | `src/database/DatabaseManagerFactory.php` | `WebserverDetector` → `SwooleConnection` or `PdoConnection` |
| `WebserverDetector` | `src/core/WebserverDetector.php` | `APP_ENV_SERVER` / extension / `SERVER_SOFTWARE` → swoole\|apache\|nginx |
| `PdoConnection` | `vendor/gemvc/connection-pdo/...` | Cached PDO (not a pool); `DB_PERSISTENT_CONNECTIONS` default **1** |
| `SwooleConnection` | `vendor/gemvc/connection-openswoole/...` | Hyperf pool; `MIN/MAX_DB_CONNECTION_POOL`; always **release** |
| Contracts | `vendor/gemvc/connection-contracts/...` | `ConnectionInterface` + `ConnectionManagerInterface` |

## Helper / HTTP client (vendor)

| Package | NS | Must know |
|---------|-----|-----------|
| `gemvc/helper` | `Gemvc\Helper\` | `TypeChecker` (decimal, uuid, slug, …); `CryptHelper` Argon2i; `ProjectHelper::loadEnv` |
| `gemvc/http-client` | `Gemvc\Http\Client\` | `HttpClient` sync; `AsyncHttpClient::fireAndForget` (FPM); `SwooleHttpClient::fireAndForget` (coroutines) |

## Footguns (agent checklist)

1. Apache expects `/api/...` + `api` hop; Swoole does **not** auto-skip `api` — configure sections or drop prefix
2. `METHOD_IN_URL_SECTION` = **SwooleBootstrap only**
3. Never `die`/`exit` on Swoole request path
4. `callController` / magic controllers = `ApiService` only
5. `requireAuth` must **throw**; JsonResponse from constructor does not abort method
6. Auth: missing token **401**; bad verify / wrong role **403**
7. Rate limit fails open without APCu; full cache → purge `gemvc:rl:*` → retry → else 429
8. Apache uploads: only field name **`file`**; raw (no MIME sanitize)
9. Table `_` props skipped on write; call `setPrimaryKey` after `parent::__construct()`
10. `Schema::primary` / `autoIncrement` ≠ migrate DDL today — prefer property `id`
11. Do not `db:migrate` SQL-view Tables (until first-class views ship)
12. Money = string + decimal type_map
13. Doc generator defaults `@http` to POST; URL examples may lack `/api`
14. Prefer contracts (`APM_NAME`, connection packages) over hardcoding TraceKit / PDO pools
15. `create:*` needs **cli-dev**; templates: project `templates/cli/` then cli-dev vendor; init does **not** ship create stubs from library
16. CLI: `--default <value>` (space, not `=`); `db:drop --force`; `db:unique table/column`; `create:service X -cmt`
17. DB: never invent pools — use `DatabaseManagerFactory`; Swoole must `releaseConnection`; PDO persistent default on
18. Outbound HTTP: use `gemvc/http-client`, not raw curl; FAF = Async (Apache) or SwooleHttpClient (Swoole)
19. Schema types live in **helper** `TypeChecker` — money = string + `decimal`, never float

## Related

- [architecture.md](architecture.md)
- [improve.md](improve.md)
- [docs/ai/CORE_REFERENCE.md](../../../docs/ai/CORE_REFERENCE.md)

# GEMVC source map (must-know)

Follow [protocol.md](protocol.md): examine `vendor/gemvc` **and** `src/` before proposing changes. Confirm in these files before inventing APIs. Paths relative to repo root.

## Lifecycle / routing

| Class | Path | Must know |
|-------|------|-----------|
| `Bootstrap` | `src/core/Bootstrap.php` | Apache/Nginx lifecycle; `setRequestedService`; may `die` |
| `SwooleBootstrap` | `src/core/SwooleBootstrap.php` | `extractRouteInfo`; `processRequest()` returns response |
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

## HTTP / auth

| Class | Path | Must know |
|-------|------|-----------|
| `Request` | `src/http/Request.php` | schemas, `auth`/`authenticate`/`authorize`, findable/filterable/sortable, map*ToObject |
| `ApacheRequest` / `SwooleRequest` | `src/http/` | Adapters → shared Request |
| `JWTToken` | `src/http/JWTToken.php` | HS256 create/verify; roles, claims |
| `Response` / `JsonResponse` | `src/http/` | Factories; `show` vs `showSwoole` |
| `AuthException` | `src/core/AuthException.php` | From `requireAuth` |
| `RateLimiter` / `RateLimitException` | `src/core/` | APCu; 429 |
| `ValidationException` | `src/core/ValidationException.php` | Apache validate* path → 400 |

## Security / errors

| Class | Path | Must know |
|-------|------|-----------|
| `SecurityManager` | `src/core/SecurityManager.php` | Swoole early path/extension deny + normalize |
| Apache `.htaccess` | `src/startup/apache/.htaccess` | Rewrite + deny sensitive paths |
| `GemvcError` / `GEMVCErrorHandler` | `src/core/` | Structured JSON errors |

## ORM / DB

| Class | Path | Must know |
|-------|------|-----------|
| `Table` | `src/database/Table.php` | Fluent select/where/run; PK detect; setPrimaryKey |
| `CrudOperationsTrait` | `src/database/TableComponents/` | insert/update/delete; skip `_` props |
| `PropertyCaster` | `src/database/TableComponents/` | `$_type_map` incl. decimal-as-string |
| `Schema` | `src/database/Schema.php` | Migrate constraints DSL |
| `DialectResolver` + dialects | `src/database/Dialect/` | mysql / pgsql / sqlite |
| `UniversalQueryExecuter` | `src/database/UniversalQueryExecuter.php` | PDO exec + DB APM |
| `QueryBuilder` | `src/database/QueryBuilder.php` | Ad-hoc SQL builder (not Table fluent) |

Connections live in **`gemvc/connection-pdo`** / **`connection-openswoole`** (not under `src/`).

## Docs / CLI / APM

| Piece | Path | Must know |
|-------|------|-----------|
| `ApiDocGenerator` | `src/core/ApiDocGenerator.php` | Reflect API + `@http` + schema regex |
| `Documentation` | `src/core/Documentation.php` | HTML/JSON doc UI |
| CLI entry | `bin/gemvc` | Dispatch; library has `init`, `db:migrate` only |
| Init / migrate | `src/CLI/commands/` | InitProject, DbMigrate, … |
| APM trait / test | `src/core/Apm/` | Tracing helpers; providers are external packages |
| `ApmFactory` | `vendor/gemvc/apm-contracts` | `APM_NAME` → provider |

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
| `vendor/gemvc/cli-base/src/` | `Command`, generators base |
| `vendor/gemvc/cli-dev/src/Commands/` | `CreateCrud`, `Create*`, `DbList`/`DbDrop`/…, `Admin*` |

## Footguns (agent checklist)

1. Apache expects `/api/...`; Swoole does not auto-skip `api`
2. Never `die`/`exit` on Swoole request path
3. `callController` / magic controllers = `ApiService` only
4. `requireAuth` must **throw**; JsonResponse from constructor does not abort method
5. Auth: missing token **401**; bad token / wrong role **403**
6. Rate limit fails open without APCu
7. Table `_` props skipped on write; call `setPrimaryKey` after `parent::__construct()`
8. `Schema::primary` ≠ automatic runtime PK today
9. Do not `db:migrate` SQL-view Tables (until first-class views ship)
10. Money = string + decimal type_map
11. Doc generator defaults `@http` to POST; URL examples may lack `/api`
12. Prefer contracts (`APM_NAME`, connection packages) over hardcoding TraceKit / PDO pools in app code

## Related

- [architecture.md](architecture.md)
- [improve.md](improve.md)
- [docs/ai/CORE_REFERENCE.md](../../../docs/ai/CORE_REFERENCE.md)

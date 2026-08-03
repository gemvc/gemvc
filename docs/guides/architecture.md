# GEMVC Architecture Overview

**Audience:** understanding framework internals and request flow (`src/`).

**Related:** [api.md](api.md) · [http-lifecycle.md](http-lifecycle.md) · [openswoole.md](openswoole.md) · [ecosystem.md](ecosystem.md) · [CANONICAL.md](../ai/CANONICAL.md)

> App layer how-tos: [api](api.md) · [controller](controller.md) · [model](model.md) · [database](database.md).
> 4-layer stack is **strongly recommended** (bypass works; don’t for normal HTTP services).

## Reading map (AI)

| Need | Jump to |
|------|---------|
| Request flows | [Request Flow Architecture](#request-flow-architecture) |
| `src/` components | [Component Breakdown](#component-breakdown) |
| helper / http-client packages | [Ecosystem packages](#ecosystem-packages-not-under-librarysrchelper) |
| APM hooks | [APM Integration Architecture](#apm-integration-architecture) · [apm.md](apm.md) |
| URL mapping | [URL-to-Code Mapping](#url-to-code-mapping) |

**AI rule:** Prefer layer guides + [helper.md](helper.md) / [http-client.md](http-client.md) for writing `app/` code; use this file for framework internals and diagrams. Do not ingest security/APM/CLI catalogs here — open [security.md](security.md), [apm.md](apm.md), [cli.md](cli.md). Helpers are **not** under `src/helper/`.

## Directory Structure

```
src/ (gemvc/library — this repo)
├── CLI/ # Framework CLI (init, db:migrate, Docker)
├── core/ # Bootstrap, ApiService, Controller, Security
├── http/ # Inbound Request/Response/JWT (+ ApiCall facades)
├── database/ # Table ORM, migrations, query builders
├── startup/ # Platform-specific init (Apache/Swoole/Nginx)
└── stubs/ # IDE type stubs (OpenSwoole, Redis, APCu)

# NOT in library src/ anymore — separate Composer packages:
# vendor/gemvc/helper/ TypeChecker, CryptHelper, ProjectHelper, …
# vendor/gemvc/http-client/ outbound HttpClient / AsyncHttpClient
# vendor/gemvc/connection-* DB connections
# vendor/gemvc/apm-* APM
# vendor/gemvc/cli-base|cli-dev
```

See [ecosystem.md](ecosystem.md) · [helper.md](helper.md) · [http-client.md](http-client.md).

---

## Core Design Principles

### 1. **Webserver-Agnostic Application Code**
- `app/` folder code **never changes** when switching webservers
- Framework handles all platform differences
- Same API endpoints work on Apache, OpenSwoole, and Nginx

### 2. **Automatic Security (90% Automatic)**
- **No configuration needed** - Security works out of the box
- Path protection, input sanitization, SQL injection prevention all automatic
- Developers only call `definePostSchema()` and `auth()` methods

### 3. **Environment-Aware Architecture**
- Automatic webserver detection (`WebserverDetector`)
- Automatic database manager selection (`DatabaseManagerFactory`)
- Startup-specific request adapter: `ApacheRequest` for Apache/Nginx PHP-FPM and FrankenPHP classic; `SwooleRequest` for OpenSwoole (no separate NginxRequest/FrankenPhpRequest; `WebserverDetector` does not pick the adapter)

### 4. **Code Generation CLI**
- Generate Services, Controllers, Models, Tables, CRUD operations
- Template-based generation system
- Docker-compose generation with optional services

---

## Request Flow Architecture

### Apache/Nginx Flow:
```
HTTP Request
 → startup/apache|nginx|frankenphp/index.php → ApacheRequest (sanitize headers/body)
 → Bootstrap (APM root) → route /api/{Service}/{method}
 → ApiService (schema + auth) → callController → Controller
 → Model → Table (DB span if APM_TRACE_DB_QUERY=1)
 → JsonResponse → APM flush
```
Controller spans need `APM_TRACE_CONTROLLER=1` **and** `callController`.

### OpenSwoole Flow:
```
HTTP Request
 → OpenSwooleServer (SecurityManager path check — Swoole only)
 → SwooleRequest (sanitize; upload name/MIME)
 → SwooleBootstrap (APM root; SERVICE_IN_URL_SECTION / METHOD_IN_URL_SECTION)
 → ApiService (schema + auth; deprecated SwooleApiService still works) → `callController` → Controller
 → Model → Table (pool + DB span if APM_TRACE_DB_QUERY=1)
 → JsonResponse|HtmlResponse showSwoole → APM flush
```

**Canonical OpenSwoole behavior** (isolation, pooling, no `die()`, developer rules, FAQ): [openswoole.md](openswoole.md).

---

## Component Breakdown

### **CLI/** - Code Generation & Project Management
- `Command.php` - Base command class
- `AbstractInit.php` - Template method for project initialization
- `InitProject.php` - Main init orchestrator
- `InitApache.php` / `InitSwoole.php` - Platform-specific init
- `CreateService.php`, `CreateController.php`, etc. - Code generators
- `DockerComposeInit.php` - Docker setup wizard

**Key Features**:
- Template-based code generation
- Interactive project setup
- Database migration commands
- File system management with overwrite protection

### **core/** - Framework Core
- `Bootstrap.php` / `SwooleBootstrap.php` - Request routing, **APM initialization (early tracing)**
- `ApiService.php` — unified API base (all servers); deprecated `SwooleApiService.php` thin subclass
 - `ApiService::callController()` for controller tracing (all servers)
 - Uses `$request->apm` for trace context propagation
- `Controller.php` - Base controller with pagination, filtering, sanitization
 - `createModel()` helper for automatic Request propagation
 - Uses `$request->apm` for trace context
- `ApmTracingTrait.php` - Unified APM tracing methods (reusable across layers)
- `SecurityManager.php` - Path access protection
- `WebserverDetector.php` - Environment detection (cached)
- `OpenSwooleServer.php` - OpenSwoole server lifecycle
- `HotReloadManager.php` - Development hot reload (watches app dir only via ProjectHelper; dev-only; 5s interval)
- `RedisManager.php` - Redis connection singleton (also used by RateLimiter when `REQUEST_RATE_LIMIT_DRIVER=redis` or `both`)
- `RateLimiter.php` - APCu / Redis / both / none drivers; Bootstrap `enforceFromEnv`; no auto-fallback
- `ApiDocGenerator.php` - Auto-generate API documentation

**Key Features**:
- Automatic security enforcement
- Environment-aware routing
- Developer-friendly base classes
- Built-in documentation generation
- **Native APM integration** - Automatic tracing with zero configuration
 - Early APM initialization in Bootstrap/SwooleBootstrap
 - Controller tracing via `callController()` (environment-controlled)
 - Database query tracing (environment-controlled)
 - Trace context propagation through all layers

### **http/** - HTTP Layer
- `Request.php` - Unified request object (all inputs sanitized)
- `ApacheRequest.php` - Apache, Nginx PHP-FPM, and FrankenPHP classic request adapter (sanitizes headers + inputs)
- `SwooleRequest.php` - OpenSwoole request adapter (sanitizes headers + inputs)
- `Response.php` - Response factory
- `JsonResponse.php` - JSON response handler (show() vs showSwoole())
- `JWTToken.php` - JWT creation, verification, renewal
- `NoCors.php` - CORS handler
- `SwooleWebSocketHandler.php` - WebSocket support

**Key Features**:
- **Automatic input sanitization** (XSS prevention)
- **Automatic header sanitization** (injection prevention)
- **Cookie filtering** (dangerous cookie blocking)
- **JWT authentication/authorization**
- **Schema validation** (mass assignment prevention)

### **database/** - Database Layer
- `Table.php` - Main ORM class (fluent interface)
 - `setRequest()` method for APM trace context propagation
- `UniversalQueryExecuter.php` - **Enforces prepared statements**
 - **APM query tracing** (if `APM_TRACE_DB_QUERY=1`)
 - Captures query type, execution time, rows affected
 - Uses `$request->apm` for trace context
- `ConnectionManager.php` - Connection management
 - `setRequest()` method for Request propagation
- `PdoQuery.php` - PDO query wrapper
 - `setRequest()` method for Request propagation
- `DatabaseManagerFactory.php` - Auto-selects DB manager
- `SwooleDatabaseManager.php` - Connection pooling (OpenSwoole)
- `SimplePdoDatabaseManager.php` - Standard PDO (Apache/Nginx)
- `EnhancedPdoDatabaseManager.php` - Persistent PDO (optional)
- `QueryBuilder.php` - Lower-level query builder
- `Schema.php` / `SchemaGenerator.php` - Schema management (`Schema::primary` **not** DDL today)
- `TableGenerator.php` - Table create/sync (refuses `ViewTable`)
- `ViewTable.php` / `ViewGenerator.php` - SQL views; `db:migrate` CREATE/REPLACE VIEW
- `TableMigrateOrder.php` - FK + `viewDependsOn` order for `db:migrate --all`

**Key Features**:
- **100% SQL injection prevention** (all queries use prepared statements)
- **Connection pooling** for OpenSwoole (performance)
- **Environment-aware connection management**
- **Migration system** — tables + **ViewTable** views; `db:migrate --all`
- **Schema generation**
- **APM query tracing** - Automatic spans for all database queries (optional)

### Ecosystem packages (not under `library/src/helper`)

Helpers and outbound HTTP **moved out** of the library tree. Use Composer packages:

| Package | Classes | Guide |
|---------|---------|--------|
| **`gemvc/helper`** | `TypeChecker`, `CryptHelper`, `ProjectHelper`, `FileHelper`, `ImageHelper`, `TypeHelper`, `JsonHelper`, `StringHelper`, `WebHelper`, `ServerMonitorHelper`, `NetworkHelper`, … | [helper.md](helper.md) · `vendor/gemvc/helper/README.md` |
| **`gemvc/http-client`** | `HttpClient`, `AsyncHttpClient`, `SwooleHttpClient` | [http-client.md](http-client.md) · `vendor/gemvc/http-client/README.md` |

Library still **requires** these packages. Namespace `Gemvc\Helper\` is unchanged. Do not look for `src/helper/` in this repo.

### **startup/** - Platform Initialization
```
startup/
├── apache/
│   ├── index.php
│   ├── example.env
│   └── Dockerfile …
├── swoole/
│   ├── index.php
│   ├── example.env      # SERVICE_IN_URL_SECTION, SWOOLE_*, pools
│   └── Dockerfile …
├── nginx/
│   ├── index.php
│   └── …
└── common/
    └── user/            # sample User layers
```

**Key Features**:
- Platform-specific entry points
- Platform-specific dependencies
- Shared common files
- Docker configurations

---

## Security Architecture

Brief pointer only — full detail: [security.md](security.md).

- **Automatic:** path protection, header/input sanitization, prepared statements, file-name sanitization, cookie filtering  
- **You call:** `define*Schema()`, `auth()` / `requireAuth()`, optional FileHelper / ImageHelper  

---

## APM Integration Architecture

Contracts-first: library → **`gemvc/apm-contracts`** (`ApmFactory` / `ApmInterface`) → provider (`APM_NAME=…`). TraceKit is one provider.

- Root span: Bootstrap / SwooleBootstrap → `$request->apm`  
- Controller spans: `APM_TRACE_CONTROLLER=1` + Apache `callController()`  
- DB spans: `APM_TRACE_DB_QUERY=1` + `createModel()`  

Full guide: [apm.md](apm.md) · `vendor/gemvc/apm-contracts/README.md`.

```
Bootstrap → $request->apm
  → ApiService (`callController` span if enabled; deprecated Swoole* aliases OK)
  → Controller → createModel() → Model/Table → UniversalQueryExecuter (query span if enabled)
  → Response → provider flush (non-blocking where supported)
```

---

## Performance Features

- **OpenSwoole:** connection pooling, persistent workers, hot reload, WebSockets  
- **Apache/Nginx:** optional persistent PDO (`DB_ENHANCED_CONNECTION=1`), cached env, prepared statement reuse  

---

## URL-to-Code Mapping

**Apache/Nginx:** URL contains a literal `api` segment; service/method are the next two parts → `/api/User/create` → `App\Api\User::create()`.

**OpenSwoole:** **no** automatic `api` hop. Indices come from `.env` (defaults `SERVICE_IN_URL_SECTION=1`, `METHOD_IN_URL_SECTION=2`). Those vars are read by **`SwooleBootstrap` only** (Apache `Bootstrap` ignores `METHOD_IN_URL_SECTION`).

Example with Swoole defaults and path `/User/create` → segments `['', 'User', 'create']` → service `User`, method `create`.  
If you keep a leading `/api/...` on Swoole with defaults `1`/`2`, segment 1 is `api` → wrong service name — raise indices or drop the `api` prefix.

```
# Apache/Nginx example
URL: /api/User/create
 → Service = User, Method = create
 → app/api/User.php::create()
 → Controller → Model → Table
```

Dev OpenSwoole: request path `/` may route to `Developer` / `app` when `APP_ENV=dev`.

**Swoole `.env` live in** `src/startup/swoole/example.env` (also `SWOOLE_*`, pools, `IS_OPENSWOOLE`).

---

## Design Patterns Used

1. **Template Method** - `AbstractInit.php` → `InitApache.php` / `InitSwoole.php`
2. **Strategy** - `DatabaseManagerFactory` → Different DB managers
3. **Factory** - `DatabaseManagerFactory`, `Response` factory
4. **Adapter** - `ApacheRequest` (Apache/Nginx), `SwooleRequest` (OpenSwoole) adapt to unified `Request`
5. **Singleton** - `RedisManager`, cached `DatabaseManagerFactory`
6. **Builder** - `Table` fluent interface, `QueryBuilder`
7. **Dependency Injection** - `Request` injected into services/controllers

---

## CLI Commands

Library: `gemvc init`, `gemvc db:migrate ClassName`, `gemvc db:migrate --all`.  
Dev (`gemvc/cli-dev`): `create:*`, most `db:*`, `admin:*`.  

Details: [cli.md](cli.md) · [cli-reference.md](cli-reference.md).

---

## Key Files Reference

### **Entry Points**:
- `startup/apache/index.php` - Apache entry (`ApacheRequest` + `Bootstrap`)
- `startup/nginx/index.php` - Nginx entry (same `ApacheRequest` + `Bootstrap` PHP-FPM path)
- `startup/frankenphp/index.php` - FrankenPHP classic entry (same `ApacheRequest` + `Bootstrap`; edge security in `Caddyfile`)
- `startup/swoole/index.php` - OpenSwoole entry
- `bin/gemvc` - CLI entry point

### **Core Classes**:
- `src/core/Bootstrap.php` - Apache/Nginx request router
- `src/core/SwooleBootstrap.php` - OpenSwoole request router
- `src/core/OpenSwooleServer.php` - OpenSwoole server manager
- `src/http/Request.php` - Unified request object
- `src/database/Table.php` - Main ORM class
- `src/database/ViewTable.php` - SQL VIEW read models
- `src/database/ViewGenerator.php` - VIEW DDL for migrate

### **Security**:
- `src/core/SecurityManager.php` - Path protection
- `src/http/ApacheRequest.php` - Input sanitization (Apache/Nginx PHP-FPM / FrankenPHP classic)
- `src/http/SwooleRequest.php` - Input sanitization (OpenSwoole)
- `src/database/UniversalQueryExecuter.php` - SQL injection prevention

---

## Summary

GEMVC is a server-agnostic PHP REST framework (`gemvc/library` + ecosystem packages):

- Automatic path / input / SQL hardening; schema + auth are developer calls ([security.md](security.md))
- Same `app/` code on Apache, OpenSwoole, Nginx
- APM via **`gemvc/apm-contracts`** + provider ([apm.md](apm.md))
- Codegen / DB tooling via CLI ([cli.md](cli.md); **cli-dev** for `create:*`)


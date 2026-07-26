# GEMVC Architecture Overview

**Audience:** understanding framework internals and request flow (`src/`).

**Related:** [api.md](api.md) · [http-lifecycle.md](http-lifecycle.md) · [ecosystem.md](ecosystem.md) · [CANONICAL.md](../ai/CANONICAL.md)

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
└── stubs/ # IDE type stubs (OpenSwoole, Redis)

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
- Automatic request adapter selection (`ApacheRequest` vs `SwooleRequest`)

### 4. **Code Generation CLI**
- Generate Services, Controllers, Models, Tables, CRUD operations
- Template-based generation system
- Docker-compose generation with optional services

---

## Request Flow Architecture

### Apache/Nginx Flow:
```
HTTP Request
 ↓
index.php (startup/apache/index.php)
 ↓
Bootstrap.php → APM initialized (early tracing) → Security check (automatic)
 ↓
ApacheRequest.php → Sanitize all inputs (automatic)
 ↓
app/api/User.php → schema validation + auth (thin)
 ↓
UserController.php → orchestration / map request → Model (traced if APM_TRACE_CONTROLLER=1)
 ↓
UserModel.php → business rules / transforms
 ↓
UserTable.php → Database operations (traced if APM_TRACE_DB_QUERY=1, prepared statements - automatic)
 ↓
JsonResponse.php → Return JSON
 ↓
APM traces sent (fire-and-forget, non-blocking)
```

### OpenSwoole Flow:
```
HTTP Request
 ↓
OpenSwooleServer.php → Security check (automatic)
 ↓
SwooleRequest.php → Sanitize all inputs (automatic)
 ↓
SwooleBootstrap.php → APM initialized (early tracing) → Route to API service
 ↓
app/api/User.php → schema validation + auth (thin)
 ↓
UserController.php → orchestration / map request → Model (traced if APM_TRACE_CONTROLLER=1)
 ↓
UserModel.php → business rules / transforms
 ↓
UserTable.php → Database operations (traced if APM_TRACE_DB_QUERY=1, connection pooling - automatic)
 ↓
JsonResponse.php → Return JSON (via showSwoole())
 ↓
APM traces sent (fire-and-forget, non-blocking)
```

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
- `ApiService.php` / `SwooleApiService.php` - Base API service classes
 - `ApiService::callController()` for controller tracing (**Apache/Nginx only** — not on `SwooleApiService`)
 - Uses `$request->apm` for trace context propagation
- `Controller.php` - Base controller with pagination, filtering, sanitization
 - `createModel()` helper for automatic Request propagation
 - Uses `$request->apm` for trace context
- `ApmTracingTrait.php` - Unified APM tracing methods (reusable across layers)
- `SecurityManager.php` - Path access protection
- `WebserverDetector.php` - Environment detection (cached)
- `OpenSwooleServer.php` - OpenSwoole server lifecycle
- `HotReloadManager.php` - Development hot reload (watches app dir only via ProjectHelper; dev-only; 5s interval)
- `RedisManager.php` - Redis connection singleton
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
- `ApacheRequest.php` - Apache request adapter (sanitizes headers + inputs)
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
- `Schema.php` / `SchemaGenerator.php` - Schema management
- `TableGenerator.php` - Table class generation

**Key Features**:
- **100% SQL injection prevention** (all queries use prepared statements)
- **Connection pooling** for OpenSwoole (performance)
- **Environment-aware connection management**
- **Migration system**
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
├── apache/ # Apache-specific files
│ ├── index.php # Apache entry point
│ ├── appIndex.php # Application bootstrap
│ ├── composer.json # Apache dependencies
│ └── docker-compose.yml
├── swoole/ # OpenSwoole-specific files
│ ├── index.php # OpenSwoole entry point
│ ├── appIndex.php # Application bootstrap
│ ├── composer.json # OpenSwoole dependencies (Hyperf)
│ └── docker-compose.yml
├── nginx/            # Nginx init / startup files
└── common/ # Shared files for all platforms
 └── user/ # Example User files
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
  → ApiService (callController span if enabled; Swoole: bare new Controller)
  → Controller → createModel() → Model/Table → UniversalQueryExecuter (query span if enabled)
  → Response → provider flush (non-blocking where supported)
```

---

## Performance Features

- **OpenSwoole:** connection pooling, persistent workers, hot reload, WebSockets  
- **Apache/Nginx:** optional persistent PDO (`DB_ENHANCED_CONNECTION=1`), cached env, prepared statement reuse  

---

## URL-to-Code Mapping

**Apache/Nginx:** `/api/{Service}/{method}` (literal `api` hop).  
**OpenSwoole:** `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (defaults `1`/`2`) — no automatic `api` hop.

```
URL: /api/User/create
 ↓
Extracts: Service = "User", Method = "create"
 ↓
Loads: app/api/User.php
 ↓
Calls: User::create()
 ↓
User::create() validates schema → delegates to UserController
 ↓
UserController::create() maps request → Model (createModel / map*ToObject)
 ↓
UserModel::createModel() (or domain method) applies business rules
 ↓
UserTable insert/update via Model (Table CRUD)
```

**Configuration** (via `.env`):
- `SERVICE_IN_URL_SECTION=1` (default: 1)
- `METHOD_IN_URL_SECTION=2` (default: 2)

---

## Design Patterns Used

1. **Template Method** - `AbstractInit.php` → `InitApache.php` / `InitSwoole.php`
2. **Strategy** - `DatabaseManagerFactory` → Different DB managers
3. **Factory** - `DatabaseManagerFactory`, `Response` factory
4. **Adapter** - `ApacheRequest`, `SwooleRequest` adapt to unified `Request`
5. **Singleton** - `RedisManager`, cached `DatabaseManagerFactory`
6. **Builder** - `Table` fluent interface, `QueryBuilder`
7. **Dependency Injection** - `Request` injected into services/controllers

---

## CLI Commands

Library: `gemvc init`, `gemvc db:migrate`.  
Dev (`gemvc/cli-dev`): `create:*`, most `db:*`, `admin:*`.  

Details: [cli.md](cli.md) · [cli-reference.md](cli-reference.md).

---

## Key Files Reference

### **Entry Points**:
- `startup/apache/index.php` - Apache entry
- `startup/swoole/index.php` - OpenSwoole entry
- `bin/gemvc` - CLI entry point

### **Core Classes**:
- `src/core/Bootstrap.php` - Apache request router
- `src/core/SwooleBootstrap.php` - OpenSwoole request router
- `src/core/OpenSwooleServer.php` - OpenSwoole server manager
- `src/http/Request.php` - Unified request object
- `src/database/Table.php` - Main ORM class

### **Security**:
- `src/core/SecurityManager.php` - Path protection
- `src/http/ApacheRequest.php` - Input sanitization (Apache)
- `src/http/SwooleRequest.php` - Input sanitization (OpenSwoole)
- `src/database/UniversalQueryExecuter.php` - SQL injection prevention

---

## Summary

GEMVC is a server-agnostic PHP REST framework (`gemvc/library` + ecosystem packages):

- Automatic path / input / SQL hardening; schema + auth are developer calls ([security.md](security.md))
- Same `app/` code on Apache, OpenSwoole, Nginx
- APM via **`gemvc/apm-contracts`** + provider ([apm.md](apm.md))
- Codegen / DB tooling via CLI ([cli.md](cli.md); **cli-dev** for `create:*`)


# 🏗️ GEMVC Architecture Overview

## 📦 Directory Structure

```
src/
├── CLI/              # Command-line interface & code generation
├── core/             # Core framework classes (Bootstrap, ApiService, Security)
├── http/             # HTTP layer (Request, Response, JWT)
├── database/         # Database layer (ORM, migrations, query builders)
├── helper/           # Utility classes (TypeChecker, FileHelper, CryptHelper)
├── startup/          # Platform-specific initialization files
└── stubs/            # IDE type stubs (OpenSwoole, Redis)
```

---

## 🎯 Core Design Principles

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

## 🔄 Request Flow Architecture

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
app/api/User.php → Developer schema validation (optional)
    ↓
UserController.php → Business logic (traced if APM_TRACE_CONTROLLER=1)
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
app/api/User.php → Developer schema validation (optional)
    ↓
UserController.php → Business logic (traced if APM_TRACE_CONTROLLER=1)
    ↓
UserTable.php → Database operations (traced if APM_TRACE_DB_QUERY=1, connection pooling - automatic)
    ↓
JsonResponse.php → Return JSON (via showSwoole())
    ↓
APM traces sent (fire-and-forget, non-blocking)
```

---

## 🗂️ Component Breakdown

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
  - `callController()` method for automatic controller tracing
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

### **helper/** - Utility Classes
- `TypeChecker.php` - Runtime type validation (advanced options)
- `ProjectHelper.php` - Path resolution (finds composer.lock), env/base URL/system paths, APM detection, `disableOpcacheIfDev()` for dev
- `FileHelper.php` - File operations + encryption
- `ImageHelper.php` - Image processing + signature detection
- `CryptHelper.php` - Password hashing (Argon2I) + AES-256-CBC encryption
- `StringHelper.php` - String manipulation utilities
- `TypeHelper.php` - Type utilities (guid, timestamp, etc.)
- `JsonHelper.php` - JSON validation
- `WebHelper.php` - Webserver detection
- `ChatGptClient.php` - OpenAI integration
- `ServerMonitorHelper.php` - Server resource monitoring (RAM, CPU)
- `NetworkHelper.php` - Network statistics collection

**Key Features**:
- **File signature detection** (MIME type verification)
- **File encryption** (AES-256-CBC + HMAC)
- **Password security** (Argon2I)
- **Type validation** (email, string length, regex, dates, etc.)
- **Server monitoring** (cross-platform RAM, CPU, network metrics)

### **startup/** - Platform Initialization
```
startup/
├── apache/           # Apache-specific files
│   ├── index.php     # Apache entry point
│   ├── appIndex.php  # Application bootstrap
│   ├── composer.json # Apache dependencies
│   └── docker-compose.yml
├── swoole/           # OpenSwoole-specific files
│   ├── index.php     # OpenSwoole entry point
│   ├── appIndex.php  # Application bootstrap
│   ├── composer.json # OpenSwoole dependencies (Hyperf)
│   └── docker-compose.yml
├── nginx/            # Nginx files (coming soon)
└── common/           # Shared files for all platforms
    └── user/         # Example User files
```

**Key Features**:
- Platform-specific entry points
- Platform-specific dependencies
- Shared common files
- Docker configurations

---

## 🔐 Security Architecture

### **Automatic Security (No Developer Action)**:
1. ✅ **Path Protection** - Blocks `/app`, `/vendor`, `.env`, etc.
2. ✅ **Header Sanitization** - All HTTP headers sanitized
3. ✅ **Input Sanitization** - All GET/POST/PUT/PATCH sanitized (XSS prevention)
4. ✅ **SQL Injection Prevention** - All queries use prepared statements
5. ✅ **File Name Sanitization** - Uploaded file names sanitized
6. ✅ **Cookie Filtering** - Dangerous cookies blocked

### **Developer-Enabled Security (Simple Method Calls)**:
1. ⚙️ **Schema Validation** - Call `definePostSchema()` (prevents mass assignment)
2. ⚙️ **Authentication** - Call `$request->auth()` (JWT validation)
3. ⚙️ **Authorization** - Call `$request->auth(['role'])` (role checking)
4. ⚙️ **File Signature Detection** - Use `ImageHelper` methods
5. ⚙️ **File Encryption** - Use `FileHelper::encrypt()`

---

## 📊 APM Integration Architecture

### **Automatic APM Tracing (Zero Configuration)**:
1. ✅ **Root Trace** - Automatically created in Bootstrap/SwooleBootstrap
   - Captures full request lifecycle
   - Initialized early (before routing)
   - Stored in `$request->apm` for trace context propagation
2. ✅ **Exception Tracking** - All exceptions automatically recorded
3. ✅ **Trace Context Propagation** - All spans share the same `traceId`
   - Bootstrap → ApiService → Controller → Table → UniversalQueryExecuter
4. ✅ **Fire-and-Forget Pattern** - Traces sent after HTTP response (non-blocking)

### **Environment-Controlled Tracing (Optional)**:
1. ⚙️ **Controller Tracing** - Enable via `APM_TRACE_CONTROLLER=1`
   - Use `callController()` in API services
   - Automatic spans for controller method calls
   - Captures method name, response code, execution time
2. ⚙️ **Database Query Tracing** - Enable via `APM_TRACE_DB_QUERY=1`
   - Use `createModel()` in controllers (sets Request on models)
   - Automatic spans for all SQL queries
   - Captures query type, execution time, rows affected, SQL statement

### **APM Architecture Flow**:
```
Bootstrap/SwooleBootstrap
    ↓ (APM initialized, root trace started)
    ↓ ($request->apm set)
ApiService
    ↓ (uses $request->apm)
    ↓ (callController() creates controller span if enabled)
Controller
    ↓ (uses $request->apm)
    ↓ (createModel() sets Request on model)
Table → ConnectionManager → PdoQuery
    ↓ (Request propagated through all layers)
UniversalQueryExecuter
    ↓ (uses $request->apm for query span if enabled)
Database Query Executed
    ↓
Response Sent
    ↓
APM Traces Sent (fire-and-forget, non-blocking)
```

### **APM Components**:
- `Bootstrap.php` / `SwooleBootstrap.php` - Early APM initialization
- `ApiService::callController()` - Controller tracing proxy
- `Controller::createModel()` - Request propagation helper
- `Table::setRequest()` - Request propagation to database layer
- `UniversalQueryExecuter` - Database query tracing
- `ApmTracingTrait` - Unified tracing methods for custom spans

### **APM Provider Support**:
- Works with any APM provider via `gemvc/apm-contracts` package
- TraceKit (`gemvc/apm-tracekit`)
- Datadog, New Relic, Elastic APM (custom providers)
- Provider-agnostic design

### **Performance**:
- **Zero overhead when disabled** - Environment flags control tracing
- **Minimal overhead when enabled** - ~0.25ms per request
- **Non-blocking** - Traces sent after HTTP response
- **Sample rate support** - Control trace volume via `TRACEKIT_SAMPLE_RATE`

---

## 🚀 Performance Features

### **OpenSwoole Optimizations**:
- Connection pooling (database)
- Persistent processes (no PHP bootstrap overhead)
- Hot reload (development)
- Async capabilities
- WebSocket support

### **Apache/Nginx Optimizations**:
- Optional persistent PDO connections (`DB_ENHANCED_CONNECTION=1`)
- Cached environment detection
- Singleton patterns for managers
- Prepared statement reuse

---

## 📊 URL-to-Code Mapping

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
UserController::create() handles business logic
    ↓
UserTable::create() performs database operation
```

**Configuration** (via `.env`):
- `SERVICE_IN_URL_SECTION=1` (default: 1)
- `METHOD_IN_URL_SECTION=2` (default: 2)

---

## 🎨 Design Patterns Used

1. **Template Method** - `AbstractInit.php` → `InitApache.php` / `InitSwoole.php`
2. **Strategy** - `DatabaseManagerFactory` → Different DB managers
3. **Factory** - `DatabaseManagerFactory`, `Response` factory
4. **Adapter** - `ApacheRequest`, `SwooleRequest` adapt to unified `Request`
5. **Singleton** - `RedisManager`, cached `DatabaseManagerFactory`
6. **Builder** - `Table` fluent interface, `QueryBuilder`
7. **Dependency Injection** - `Request` injected into services/controllers

---

## 🛠️ CLI Commands

### **Project Management**:
- `gemvc init` - Initialize new project (select webserver)
- `gemvc create:service` - Generate API service
- `gemvc create:controller` - Generate controller
- `gemvc create:model` - Generate model
- `gemvc create:table` - Generate table class
- `gemvc create:crud` - Generate full CRUD

### **Database Management**:
- `gemvc db:init` - Initialize database
- `gemvc db:migrate` - Run migrations
- `gemvc db:list` - List tables
- `gemvc db:describe` - Describe table structure
- `gemvc db:drop` - Drop table
- `gemvc db:unique` - Add unique constraint

### **Docker**:
- `gemvc docker:init` - Generate docker-compose.yml

---

## 📝 Key Files Reference

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

## 🎯 Summary

**GEMVC is a production-ready, multi-platform PHP REST API framework** that:

✅ **Automatically secures** 90% of common vulnerabilities  
✅ **Works identically** on Apache, OpenSwoole, and Nginx  
✅ **Generates code** via CLI commands  
✅ **Prevents SQL injection** with 100% prepared statement coverage  
✅ **Sanitizes all inputs** automatically (XSS prevention)  
✅ **Provides JWT authentication** out of the box  
✅ **Native APM integration** - Automatic performance monitoring with zero configuration  
✅ **Supports WebSockets** on OpenSwoole  
✅ **Includes hot reload** for development  
✅ **Auto-generates API docs** from docblocks  
✅ **Manages database** with migrations and schema generation  

**Result**: Developers write clean, secure API code without worrying about webserver differences or most security concerns!


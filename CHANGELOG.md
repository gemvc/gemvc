# Changelog

All notable changes to GEMVC Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.6.3] - 2026-01-29

### Changed
- **Apache entrypoint (index.php)** - Dotenv: `load()` → `overload()` for Docker compatibility
  - File copied to application root; `.env` can now override container-set environment variables when needed
  - Location: `src/startup/apache/index.php`
- **ProjectHelper::loadEnv()** - Dotenv: `load()` → `overload()` for root and app `.env`
  - Root `.env`: `$dotenv->overload($rootEnvFile)`
  - App `.env`: `$dotenv->overload($appEnvFile)`
  - Location: `src/helper/ProjectHelper.php` (lines 41–52)

### Benefits
- ✅ Docker compatibility: `.env` can override container-provided environment variables
- ✅ Consistent behavior between Apache entrypoint and ProjectHelper
- ✅ Backward compatible for apps without pre-set env vars

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)

## [5.6.2] - 2026-01-27

### Added
- **Request::setApm() Method** - New helper method for cleaner APM assignment
  - Provides a more precise and type-safe way to set APM instance on Request object
  - Method signature: `setApm(\Gemvc\Core\Apm\ApmInterface $apm): void`
  - Centralizes APM assignment logic with proper type checking
  - Location: `src/http/Request.php`

### Changed
- **Bootstrap.php** - Updated to use `Request::setApm()` method
  - `initializeApm()` now uses `$this->request->setApm($this->apm)` instead of direct assignment
  - `recordExceptionInApm()` fallback code also uses `setApm()` method
  - Provides cleaner, more maintainable code
  - Location: `src/core/Bootstrap.php`

- **SwooleBootstrap.php** - Updated to use `Request::setApm()` method
  - `initializeApm()` now uses `$this->request->setApm($this->apm)` instead of direct assignment
  - Consistent with Bootstrap.php implementation
  - Location: `src/core/SwooleBootstrap.php`

### Benefits
- ✅ Cleaner API for APM assignment
- ✅ Better type safety with explicit method signature
- ✅ Centralized logic for APM assignment
- ✅ More maintainable codebase
- ✅ Consistent pattern across Bootstrap classes

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)

## [5.6.1] - 2026-01-26

### Fixed
- **PHPStan Level 9 Compliance** - Resolved all static analysis errors across multiple files
  - **AsyncApiCall.php** - Fixed return type issues and removed unused properties
    - Removed unused `$responseCallbacks` property (never read, only written)
    - Fixed `getInternalClient()` return type to exclude null (guaranteed non-null after initialization)
    - Removed unnecessary `method_exists()` checks for `setMaxConcurrency()` and `setUserAgent()` (both clients implement these methods)
  - **ApiCall.php** - Fixed return type and removed unused properties
    - Removed unused `$rawBody` property (never read, only written)
    - Removed unused `$formFields` property (never read, only written)
    - Fixed `getInternalClient()` return type with proper PHPStan type assertion
  - **Controller.php** - Fixed method signature conflict with trait
    - Updated `recordApmException()` to support both single-parameter (backward compatible) and two-parameter (trait usage) calls
    - Resolves PHPStan error: "Method invoked with 2 parameters, 1 required"
  - **ApmModel.php** - Fixed mixed type access and dead catch warnings
    - Added proper type checks for payload array access (is_array, is_string)
    - Fixed `strlen()` call with mixed type by ensuring string type before usage
    - Added PHPStan ignore comment for valid dead catch (constructor can throw even after class_exists check)

### Changed
- **Type Safety Improvements** - Enhanced type safety across HTTP client classes
  - Better null handling in lazy-loaded client instances
  - Improved type assertions for PHPStan Level 9 compliance
  - Cleaner code with removed unused properties

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)

## [5.5.0] - 2026-01-22

### Added
- **gemvc/http-client Package Integration** - HTTP client package now integrated into framework core
  - `ApiCall` class now uses `Gemvc\Http\Client\HttpClient` internally
  - `AsyncApiCall` class now uses `AsyncHttpClient` or `SwooleHttpClient` based on environment
  - Automatic environment detection via `WebserverDetector::isSwoole()`
  - Enhanced error handling with exception classification
  - Better retry mechanisms and SSL support
  - Package version: `^1.2`

- **Automatic Environment Detection** - AsyncApiCall automatically selects optimal client
  - Uses `SwooleHttpClient` (native coroutines) when running in Swoole environment
  - Uses `AsyncHttpClient` (curl_multi) when running in Apache/Nginx environment
  - Zero configuration required - detection happens automatically
  - Performance optimized for each environment

### Changed
- **ApiCall Class** - Refactored to use `HttpClient` internally
  - All public methods delegate to internal client
  - Configuration automatically synced between wrapper and internal client
  - Response data automatically synced back to wrapper
  - All existing public properties and methods remain unchanged
  - Location: `src/http/ApiCall.php`

- **AsyncApiCall Class** - Refactored to use `AsyncHttpClient` or `SwooleHttpClient`
  - Automatic environment detection via `WebserverDetector::isSwoole()`
  - Swoole: Uses native coroutines for optimal performance
  - Apache/Nginx: Uses curl_multi for concurrent execution
  - All public methods delegate to internal client
  - Configuration automatically synced
  - Handles method differences between clients (e.g., `addPostForm`, `addPostMultipart`, `addPostRaw`)
  - Location: `src/http/AsyncApiCall.php`

- **composer.json** - Added `gemvc/http-client` as required dependency
  - Version constraint: `^1.2`
  - Automatically installed with framework

### Benefits
- ✅ Automatic environment detection for optimal performance
- ✅ Optimized Swoole performance (native coroutines)
- ✅ Better error handling and retry mechanisms
- ✅ Cleaner codebase architecture (delegation pattern)
- ✅ Future-proof design (package can be updated independently)
- ✅ 100% backward compatible - all existing code continues to work

### Fixed
- No breaking changes - All existing code continues to work without modification
- All 41 ApiCall tests passing
- All 149 AsyncApiCall tests passing
- No linting errors introduced

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)
- HTTP client package uses same security mechanisms
- SSL/TLS support maintained and enhanced

## [5.4.4] - 2026-01-14

### Added
- **Framework Services in Core** - Moved framework-specific services to `src/core/`
  - `ApmController` and `ApmModel` → `src/core/Apm/`
  - `GemvcAssistantController` and `GemvcAssistantModel` → `src/core/Assistant/`
  - `DeveloperController`, `DeveloperModel`, `DeveloperTable` → `src/core/Developer/`
  - `GemvcMonitoringController` and `GemvcMonitoringModel` → `src/core/Monitoring/`
  - Framework services are now properly encapsulated in core framework
  - Initial project structure is much cleaner with only user-facing examples

### Changed
- **Initial Project Structure** - Significantly cleaner `init_example/` directory
  - Removed framework implementation files from user projects
  - API files (`Apm.php`, `GemvcAssistant.php`, `GemvcMonitoring.php`) now delegate to core controllers
  - Users now see only `User` service as complete example (API, Controller, Model, Table)
  - Framework services work via thin API wrappers that delegate to core
  - Better separation: framework code in `src/core/`, user examples in `app/`

### Benefits
- ✅ Cleaner initial app - users see only User service as complete example
- ✅ Framework services hidden - implementation details in core, not copied to user projects
- ✅ Better separation - framework code in `src/core/`, user examples in `app/`
- ✅ Easier maintenance - framework services updated in one place
- ✅ Focused learning - users see one complete example instead of multiple services

### Fixed
- No breaking changes - All API endpoints remain functional
- Framework services continue to work exactly as before
- API wrappers maintain backward compatibility

### Security
- No security vulnerabilities reported
- All existing security features maintained
- Framework services maintain same security standards

## [5.4.3] - 2026-01-14

### Added
- **APM Batch Sending Mechanism** - Time-based batch sending for APM traces
  - Replaces `AsyncApiCall` with synchronous `ApiCall()` for better reliability
  - Automatic batch queue management with 5-second intervals
  - Shutdown handler ensures all traces are sent on application termination
  - Significantly improved trace delivery reliability
  - Location: `gemvc/apm-tracekit` package (v2.0+)

### Changed
- **Bootstrap.php** - Added APM flush before response
  - `apm->flush()` call before `die()` statement
  - Captures HTTP response code for APM tracing
  - Stores response code in `Request::$_http_response_code` property
  - Added APM flush in error handling path
  - Location: `src/core/Bootstrap.php`

- **OpenSwooleServer.php** - Added APM flush after response
  - `apm->flush()` call after response is sent
  - Captures HTTP response code before sending response (Swoole limitation)
  - Stores response code in `Request::$_http_response_code` property
  - Ensures traces are added to batch queue after response
  - Location: `src/core/OpenSwooleServer.php`

- **Request.php** - Added HTTP response code property
  - `$_http_response_code` property for APM tracing
  - Required for Swoole environment where `http_response_code()` is unreliable
  - Used to pass response code to APM `flush()` method
  - Location: `src/http/Request.php`

### Fixed
- **PHPStan Level 9 Compliance** - Resolved all static analysis errors
  - Removed unnecessary `@phpstan-ignore-next-line` comments
  - Fixed `DatabaseManagerFactory` PHPDoc type annotation (`class-string` to `class-string<\Gemvc\Database\Connection\OpenSwoole\SwooleConnection>`)
  - Simplified null checks in `OpenSwooleServer.php`
  - All files now pass PHPStan Level 9 analysis

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)
- APM batch sending uses same security mechanisms as before

## [5.4.2] - 2026-06-05

### Fixed
- **OpenSwoole Dockerfile build failure** - Fixed `.dockerignore` preventing `composer.json` and `composer.lock` from being copied
  - Removed `composer.json` and `composer.lock` from `.dockerignore` file
  - Fixes `docker compose up -d --build` command failures
  - Dockerfile now correctly copies composer files needed for dependency installation
  - Location: `src/startup/swoole/.dockerignore`

### Removed
- **Unnecessary composer.json** - Removed redundant `composer.json` from `src/startup/swoole/` directory
  - File was not needed as the main project `composer.json` is used
  - Simplifies project structure and prevents confusion
  - Location: `src/startup/swoole/composer.json`

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)

## [5.4.1] - 2026-06-05

### Fixed
- **OpenSwoole Dockerfile healthcheck endpoint** - Fixed healthcheck URL from `/index/index` to `/api`
  - Healthcheck now correctly uses the standard GEMVC API healthcheck endpoint
  - Fixes Docker healthcheck failures in OpenSwoole containers
  - Aligns with GEMVC documentation and standard API endpoint structure
  - Location: `src/startup/swoole/Dockerfile`

### Added
- **TraceKit as default dependency** - `gemvc/apm-tracekit` is now included as a required dependency in `composer.json`
  - TraceKit is the default APM provider for GEMVC Framework
  - No additional `composer require` needed for TraceKit integration
  - Simplifies APM setup for new projects
  - Zero-configuration APM setup with TraceKit out of the box

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)

## [5.4.0] - 2026-01-04

### Added
- **Native APM Integration with Request Propagation** 🚀
  - APM initialization moved to `Bootstrap.php` and `SwooleBootstrap.php` for early tracing
  - Root trace automatically created at request start (captures full request lifecycle)
  - Trace context propagation via `$request->apm` through all layers
  - All spans share the same `traceId` for complete request visibility
  - Exception tracking automatically records all exceptions in traces
  - Fire-and-forget pattern for non-blocking trace sending (after HTTP response)

- **Controller Tracing** (Environment-Controlled)
  - `ApiService::callController()` method for automatic controller method tracing
  - Controlled via `APM_TRACE_CONTROLLER=1` environment variable
  - Automatic spans for controller method calls
  - Captures method name, HTTP response code, execution time, and response data
  - Zero code changes needed - same code works with/without tracing
  - `callWithTracing()` deprecated in favor of `callController()` (better naming)

- **Database Query Tracing** (Environment-Controlled)
  - Automatic APM spans for all database queries via `UniversalQueryExecuter`
  - Controlled via `APM_TRACE_DB_QUERY=1` environment variable
  - Captures query type (SELECT, INSERT, UPDATE, DELETE), execution time, rows affected, and SQL statement
  - Request propagation chain: `Table` → `ConnectionManager` → `PdoQuery` → `UniversalQueryExecuter`
  - `Table::setRequest()` method for trace context propagation
  - `Controller::createModel()` helper automatically sets Request on models

- **ApmTracingTrait** - Unified APM Tracing Methods
  - Centralized APM tracing logic for reuse across all layers
  - `startApmSpan()` - Start a new APM span with attributes
  - `endApmSpan()` - End a span with status and attributes
  - `recordApmException()` - Record exceptions in spans
  - `traceApm()` - Convenience method for wrapping operations in spans
  - Used by `ApiService` and available for custom tracing in Models/Controllers

- **Comprehensive APM Documentation**
  - `GEMVC_APM_INTEGRATION.md` - Complete APM integration guide
  - Architecture overview, configuration, usage examples, best practices
  - Performance considerations, troubleshooting, and advanced usage
  - Sample rate documentation (`TRACEKIT_SAMPLE_RATE` environment variable)
  - Updated `README.md` with prominent APM integration section
  - Updated `ARCHITECTURE.md` with APM architecture flow diagrams

- **Unit Tests for APM Integration**
  - `ControllerCreateModelTest` - Tests Request propagation to models via `createModel()`
  - `TableRequestPropagationTest` - Tests Request propagation through database layers
  - `UniversalQueryExecuterApmTest` - Tests APM tracing in database queries
  - Comprehensive coverage of APM tracing functionality

### Changed
- **Bootstrap.php** / **SwooleBootstrap.php** - APM initialization moved to constructor
  - Early APM initialization ensures root trace captures full request lifecycle
  - Explicit `$request->apm` setting for guaranteed trace context propagation
  - Exception handlers updated to set `$request->apm` for fallback APM instances

- **ApiService** - APM initialization removed (now handled by Bootstrap)
  - `callController()` method introduced as recommended way to invoke controllers
  - `callWithTracing()` deprecated (kept for backward compatibility)
  - Uses `ApmTracingTrait` for unified tracing methods
  - Fixed static method calls to use `AbstractApm` instead of interface

- **Controller** - APM initialization removed (now retrieved from `$request->apm`)
  - `getApm()` method retrieves APM from Request object
  - `createModel()` helper method for automatic Request propagation to models
  - `createList()` and `_listObjects()` updated to use `createModel()`

- **Table** - Request propagation support
  - `setRequest()` method for trace context propagation
  - Request automatically propagated to `ConnectionManager` when set

- **ConnectionManager** - Request propagation support
  - `setRequest()` method for trace context propagation
  - Request automatically propagated to `PdoQuery` when set

- **PdoQuery** - Request propagation support
  - `setRequest()` method for trace context propagation
  - Request automatically propagated to `UniversalQueryExecuter` when set

- **UniversalQueryExecuter** - Database query tracing
  - Constructor accepts optional `Request` parameter for trace context
  - APM tracing implemented in `execute()` method (controlled by `APM_TRACE_DB_QUERY`)
  - Captures comprehensive query metadata (type, time, rows, SQL)

- **JsonResponse** - Implements `JsonSerializable` interface
  - Internal APM properties (`_apm_span`, `_apm_model_name`, `_apm_method_name`) excluded from JSON output
  - Clean API responses without internal tracing metadata
  - `jsonSerialize()` method explicitly controls serialized properties

- **Property Naming Convention** - All new Request properties follow `$_request` pattern
  - `Table::$_request` (was `$request`)
  - `ConnectionManager::$_request` (was `$request`)
  - `PdoQuery::$_request` (was `$request`)
  - `UniversalQueryExecuter::$_request` (was `$request`)

### Fixed
- **ApiService::callController()** - Fixed static method calls
  - Changed `ApmInterface::determineStatusFromHttpCode()` to inline status determination
  - Changed `ApmInterface::limitStringForTracing()` to `AbstractApm::limitStringForTracing()`
  - Resolves "Cannot call abstract method" errors

- **JsonResponse JSON Output** - Removed internal APM properties from responses
  - `_apm_span`, `_apm_model_name`, `_apm_method_name` no longer appear in API responses
  - Clean JSON output for all endpoints

### Testing
- Added comprehensive test coverage for APM integration
  - `ControllerCreateModelTest` - Tests Request propagation to models
  - `TableRequestPropagationTest` - Tests Request propagation through database layers
  - `UniversalQueryExecuterApmTest` - Tests APM tracing in database queries
  - All tests passing with PHPUnit

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)
- APM tracing is read-only (no data modification)
- Trace data sent asynchronously (fire-and-forget pattern)

## [5.3.0] - 2026-01-03

### Added
- **Developer Assistant - Server Monitoring Page** 📊
  - Real-time server monitoring dashboard with interactive charts
  - RAM Usage chart with system memory metrics (used, total, free)
  - Docker Container RAM chart with container memory limits and PHP memory usage
  - Docker Container CPU chart with CPU usage percentage and throttling detection
  - CPU Usage chart with load averages (1min, 5min, 15min) and core count
  - Network Bandwidth chart showing received and sent data
  - Database Latency chart with min/max/average latency metrics
  - Expandable Database Connections table showing active connections and process list
  - Configurable refresh intervals (2s, 3s, 5s, 10s, or custom)
  - Pause/Resume functionality to temporarily stop monitoring
  - Manual refresh button for immediate data updates
  - Page Visibility API integration (pauses when tab is hidden)
  - Real-time chart updates with circular buffer (60 data points)
  - Color-coded charts (green/orange/red) based on usage thresholds
  - Canvas-based line charts with smooth rendering
  - Accessible at `/index/developer#monitoring` (dev environment only)
  - Requires developer/admin authentication
  - Uses existing `/api/GemvcMonitoring/*` endpoints for data

- **Monitoring JavaScript Module** (`monitoring.js`)
  - Self-contained IIFE module for monitoring functionality
  - Chart rendering with HTML5 Canvas
  - Data fetching and processing from GemvcMonitoring API
  - Event listener management with proper cleanup
  - LocalStorage integration for saving refresh interval preferences
  - Automatic canvas dimension initialization and resize handling
  - Error handling and graceful degradation

### Changed
- **SPA (spa.php)** - Added monitoring page route and rendering
  - New `renderMonitoring()` function for monitoring page
  - Monitoring module initialization and cleanup
  - Navigation link added to Developer Assistant menu

- **GemvcMonitoring API** - Enhanced with Docker container metrics
  - `dockerRam()` endpoint for Docker container memory usage
  - `dockerCpu()` endpoint for Docker container CPU usage and throttling detection

### Fixed
- **Database Latency Chart** - Changed line color to consistent orange for better visibility

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)
- Monitoring endpoints require proper authentication (`['developer','admin']` roles)

## [5.2.5] - 2026-01-02

### Fixed
- **Critical: URL Routing with Query Parameters** - Fixed routing bug that prevented API endpoints with query parameters from working
  - Modified `Bootstrap.php` and `SwooleBootstrap.php` to strip query strings before parsing URL segments
  - Query parameters remain fully accessible via `$request->get` array
  - Fixes error: "API method 'database?table=users' does not exist"
  - All API endpoints with query parameters now work correctly
  - Developer Assistant "View Structure" button now functional

- **Developer Assistant SPA Endpoint Updates** - Fixed all SPA endpoint references after service refactoring
  - Updated 10 endpoint references from `/index/*` to `/GemvcAssistant/*`
  - Welcome page, Services page, Tables page, and Database page now fully functional
  - All interactive features (export, import, create service, migrate) working
  - Improved error handling with actual server error messages

- **PHPStan Level 9 Compliance** - Resolved 78 PHPStan Level 9 errors across entire codebase
  - **Type Assertions** (25 fixes) - Added proper type checking for mixed parameters from request arrays
  - **Return Type Annotations** (12 fixes) - Fixed incorrect PHPDoc return types and added generic types
  - **Null Safety** (15 fixes) - Removed redundant null checks and added proper null guards
  - **Array Offset Access** (10 fixes) - Added type checking before accessing array offsets
  - **Property Type Issues** (8 fixes) - Fixed type covariance and corrected property types
  - **ReflectionClass Generic Types** (5 fixes) - Added proper generic type annotations
  - **Other Type Issues** (3 fixes) - Fixed dead catch blocks, string operations, and ternary operators

### Changed
- **Bootstrap.php** - Enhanced URL parsing to strip query strings before routing
- **SwooleBootstrap.php** - Enhanced URL parsing to strip query strings before routing
- **SPA (spa.php)** - Updated all API endpoint references to new modular service structure
- **Error Handling** - Improved error message extraction in SPA for better debugging
- **Type Safety** - All controllers, models, and tables now have proper type assertions

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)
- Type safety improvements reduce potential runtime errors

## [5.2.4] - 2026-01-01

### Changed
- **CLI Initialization Optimization** - Cleaned up startup file structure
  - Removed unused duplicate files from root `src/startup/` directory
  - Centralized `appIndex.php` in `src/startup/common/` (already done in 5.2.3)
  - All webserver-specific files now properly organized in `src/startup/{apache|nginx|swoole}/`
  - Common files centralized in `src/startup/common/`
  - Improved code maintainability and reduced duplication

### Fixed
- **Startup File Structure** - Removed redundant root-level startup files
  - Deleted unused `src/startup/index.php` (duplicate of `swoole/index.php`)
  - Deleted unused `src/startup/Dockerfile` (webserver-specific ones exist)
  - Deleted unused `src/startup/.gitignore` (webserver-specific ones exist)
  - Deleted unused `src/startup/.dockerignore` (webserver-specific ones exist)
  - Deleted unused `src/startup/docker-compose.yml` (created dynamically)
  - Deleted unused `src/startup/example.env` (webserver-specific ones exist)
  - Deleted unused `src/startup/phpstan.neon` (already in `common/`)
  - All files were unused fallbacks that were never accessed due to webserver-specific directories existing

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)

## [5.2.3] - 2025-12-31

### Added
- **APM Contracts System** - Pluggable Application Performance Monitoring
  - Integration with `gemvc/apm-contracts` package
  - `ApmFactory` for creating APM instances
  - `ApmInterface` for provider-agnostic APM operations (tracing)
  - `ApmToolkitInterface` for provider-agnostic toolkit operations (management)
  - `AbstractApmToolkit` base class with common toolkit functionality
  - Automatic APM initialization in `ApiService` and `Controller`
  - Request tracing with root spans and child spans
  - Database query tracing via `UniversalQueryExecuter`
  - Exception recording for error tracking
  - Support for multiple APM providers (TraceKit, Datadog, etc.)

- **Apm Service** (`/api/Apm/*`) - Complete APM provider management
  - Test endpoints for APM tracing
  - Status and configuration endpoints
  - Provider-agnostic registration and verification (works with any APM provider)
  - Health check heartbeat
  - Metrics, alerts, and webhook management
  - Subscription and plans information
  - Uses `ApmToolkitInterface` and `AbstractApmToolkit` for provider-agnostic operations

- **GemvcAssistant Service** (`/api/GemvcAssistant/*`) - Developer/admin tools
  - Table data export/import (CSV/SQL)
  - Database management interface
  - Configuration management
  - Service creation and listing
  - Table migration management
  - Moved from `Index.php` for better separation of concerns

- **GemvcMonitoring Service** (`/api/GemvcMonitoring/*`) - Server monitoring
  - RAM/memory usage metrics
  - CPU usage and load metrics
  - Network interface statistics
  - Database connection monitoring
  - Database pool statistics
  - Database latency metrics

- **ServerMonitorHelper** - Cross-platform server resource monitoring
  - `getMemoryUsage()` - RAM metrics (current, peak, system)
  - `getCpuLoad()` - CPU load average (1min, 5min, 15min)
  - `getCpuCores()` - CPU core count
  - `getCpuUsage()` - CPU usage percentage
  - Supports Linux, Windows, and macOS

- **NetworkHelper** - Network interface statistics
  - `getNetworkStats()` - All network interfaces with totals
  - `getNetworkInterfaces()` - List of available interfaces
  - `getInterfaceStats()` - Statistics for specific interface
  - Cross-platform support (Linux, Windows, macOS)

- **ProjectHelper::isApmEnabled()** - Check if APM is enabled
  - Returns APM provider name or null
  - Replaces deprecated `isTraceKitEnabled()`

### Changed
- **ApiService** - Now automatically initializes APM via `ApmFactory`
- **Controller** - Retrieves APM instance from Request for shared tracing
- **JsonResponse** - Uses APM contracts for response tracing
- **Bootstrap** / **SwooleBootstrap** - Updated exception recording to use APM contracts
- **UniversalQueryExecuter** - Database query tracing via APM contracts
- **Request Object** - Added `$apm` property (deprecated `$tracekit` for backward compatibility)
- **Example Files** - Moved from `src/startup/user/` to `src/startup/common/init_example/`
- **Documentation** - Comprehensive updates across all documentation files

### Deprecated
- `ProjectHelper::isTraceKitEnabled()` - Use `ProjectHelper::isApmEnabled()` instead
- `Request::$tracekit` - Use `Request::$apm` instead

### Fixed
- **Resource Leak in AsyncApiCall** - Fixed `curl_close()` missing after `curl_multi_remove_handle()`
  - Prevents file descriptor exhaustion in long-running processes
  - Critical fix for Swoole environments

- **PHPStan Level 9 Compliance** - Fixed all remaining type errors
  - Added type checks for `NetworkHelper` and `ServerMonitorHelper`
  - Fixed ternary operator warnings in `JsonResponse`
  - Added `@phpstan-ignore-next-line` for abstract static method calls
  - Enhanced `ProjectHelper` type safety

### Security
- No security vulnerabilities reported
- All existing security features maintained (90% automatic security)
- New services require proper authentication (`['developer','admin']` roles)

## [5.2.0] - 2024-12-27

### Added
- **AsyncApiCall class** - High-performance concurrent HTTP client for PHP 8.4
  - Concurrent request execution using `curl_multi`
  - Fire-and-forget mode for non-blocking background tasks (perfect for APM logging)
  - Connection pooling and automatic connection management
  - Batch request processing with configurable concurrency limits
  - Support for GET, POST, PUT, form data, multipart, and raw body requests
  - Response callbacks for individual request handling
  - Full PHPStan Level 9 compliance
  - Comprehensive documentation with usage examples

- **DockerContainerBuilder class** - Intelligent Docker container management
  - Pre-flight checks for Docker Desktop status
  - Automatic port availability detection and conflict resolution
  - Container and image name conflict detection
  - Smart port suggestion system
  - Interactive CLI for conflict resolution
  - Support for OpenSwoole, Apache, and Nginx webservers
  - Automatic environment variable configuration
  - Port mapping updates in docker-compose.yml

- **Apache PHP 8.4 Alpine support**
  - New base image `gemvc/apache:latest` with PHP 8.4 FPM
  - Alpine Linux base for minimal image size (~116MB vs ~500MB)
  - Pre-configured Apache with essential modules (rewrite, deflate, ssl, proxy)
  - Optimized PHP configuration (opcache, performance settings)
  - Composer pre-installed
  - Security hardening included

- **OpenSwoole Dockerfile multi-stage optimization**
  - Multi-stage build process for optimized production images
  - Classmap-authoritative autoloader for faster performance
  - Improved layer caching for faster builds
  - Reduced final image size
  - Production-ready optimizations

- **Enhanced ProjectHelper**
  - Improved type safety with PHPStan Level 9 compliance
  - Better error handling for composer.lock parsing
  - Enhanced version detection from composer.lock

- **Developer Assistant - Services Management Page** 🎨
  - Web-based API Services Manager interface
  - View all API services and their endpoints in a beautiful UI
  - Create new services directly from the web interface
  - Service creation options:
    - Full CRUD (Service + Controller + Model + Table)
    - Service Only
    - Service + Controller
    - Service + Model
  - Real-time endpoint listing with method types (GET, POST, PUT, DELETE)
  - Parameter documentation display (required/optional fields)
  - Endpoint count per service
  - Accessible at `/index/developer#services` (dev environment only)
  - Requires developer/admin authentication

- **Developer Assistant - Tables Layer Management Page** 🗄️
  - Web-based Tables Layer management interface
  - View all table classes and their database table names
  - Migration status display (Migrated/Not Migrated)
  - Foreign key relationship visualization
  - One-click table migration/update from web interface
  - Table class descriptions and metadata
  - Accessible at `/index/developer#tables` (dev environment only)
  - Requires developer/admin authentication
  - Automatic database connection handling for Docker/web environments

### Changed
- **PHPStan Level 9 compliance** - All code now passes the highest static analysis level
  - Fixed 27 type safety issues across the codebase
  - Enhanced property type declarations
  - Improved return type annotations
  - Better null safety handling
  - Resolved type inference problems in Controller, ProjectHelper, SetAdmin, and DockerContainerBuilder

- **Docker container management**
  - Enhanced port detection logic for Apache/Nginx webservers
  - Improved service name detection (prioritizes 'web' for Apache/Nginx)
  - Better port mapping updates in docker-compose.yml
  - Smarter conflict resolution workflow

- **Controller layer**
  - Fixed return type from `array<object>` to `array<array<string, mixed>>` for better type safety
  - Removed redundant type checks
  - Improved object-to-array conversion for PHP 8.4+ compatibility

- **DockerContainerBuilder**
  - Enhanced Apache port handling (correctly handles 8080:80 mappings)
  - Improved service name detection for better port updates
  - Better error messages and user feedback

### Fixed
- Fixed PHPStan Level 9 type errors in `ProjectHelper::getVersion()`
  - Added proper type checks for packages array
  - Enhanced version string validation

- Fixed PHPStan Level 9 type errors in `Controller::_listObjects()`
  - Corrected return type annotation
  - Removed unreachable code
  - Fixed object-to-array conversion type safety

- Fixed PHPStan Level 9 type errors in `DockerContainerBuilder`
  - Added proper null checks for string operations
  - Fixed port update logic for Apache service
  - Enhanced service name detection

- Fixed PHPStan Level 9 type errors in `SetAdmin` command
  - Added proper type checks for database results
  - Enhanced null safety for database queries
  - Fixed mixed type casting issues

- Fixed port detection in `DockerContainerBuilder::setInitialServerPort()`
  - Now correctly identifies Apache 'web' service
  - Properly extracts public port from docker-compose.yml
  - Better handling of 8080:80 port mappings

- Fixed Apache Dockerfile compatibility
  - Ensured compatibility with PHP 8.4 Alpine base image
  - Verified all commands and paths work correctly

### Security
- No security vulnerabilities reported in this release
- All existing security features maintained (90% automatic security)

---

## Migration Notes

### Upgrading from 5.3.0 to 5.4.0

This release is **fully backward compatible**. No action required.

**What's New**:
- Native APM integration with automatic request tracing
- Controller tracing via `callController()` method (environment-controlled)
- Database query tracing (environment-controlled)
- Request propagation through all layers for complete trace visibility
- `ApmTracingTrait` for unified APM tracing across all layers
- `Controller::createModel()` helper for automatic Request propagation
- Comprehensive APM integration documentation

**Benefits**:
- Zero-configuration APM tracing (works out of the box)
- Complete request visibility from Bootstrap to Database
- Environment-controlled tracing (enable/disable via env vars)
- Non-blocking trace sending (no performance impact)
- Same code works with/without tracing (no code changes needed)

**Optional Configuration**:
- Enable controller tracing: Set `APM_TRACE_CONTROLLER=1` in `.env`
- Enable database tracing: Set `APM_TRACE_DB_QUERY=1` in `.env`
- Configure sample rate: Set `TRACEKIT_SAMPLE_RATE=1.0` (0.0 to 1.0)

**Usage**:
- Use `$this->callController()` in API services for controller tracing
- Use `$this->createModel()` in controllers for automatic Request propagation
- See `GEMVC_APM_INTEGRATION.md` for complete documentation

**Breaking Changes**:
- None - `callWithTracing()` still works but is deprecated (use `callController()` instead)

### Upgrading from 5.2.5 to 5.3.0

This release is **fully backward compatible**. No action required.

**What's New**:
- Server Monitoring Dashboard in Developer Assistant
- Real-time charts for RAM, CPU, Network, and Database metrics
- Docker container metrics (RAM and CPU)
- Configurable refresh intervals and pause/resume functionality
- Database connections table

**Benefits**:
- Real-time server performance monitoring
- Visual insights into system resources
- Docker container-specific metrics
- Better debugging and performance analysis capabilities

**Usage**:
- Access monitoring at `/index/developer#monitoring` (dev environment only)
- Configure refresh intervals as needed
- Use pause/resume for detailed analysis

### Upgrading from 5.2.4 to 5.2.5

This release is **fully backward compatible**. No action required.

**What Changed**:
- Internal routing improvements (transparent to users)
- Type safety improvements (no API changes)
- SPA endpoint updates (automatic, no user action needed)

**Benefits**:
- Fixed routing bugs affecting endpoints with query parameters
- Better type safety and error detection
- Improved error messages in Developer Assistant
- Full PHPStan Level 9 compliance

### Upgrading from 5.1.4 to 5.2.0

This release maintains **full backward compatibility** with 5.1.4. No breaking changes were introduced.

#### Optional New Features

1. **AsyncApiCall** (New - Optional)
   - If you want to use concurrent API calls, you can now use `AsyncApiCall`
   - Existing `ApiCall` class continues to work as before
   - See `src/http/AsyncApiCall.php` for usage examples

2. **DockerContainerBuilder** (Enhanced)
   - Existing docker-compose.yml files work without changes
   - New pre-flight checks provide better conflict resolution
   - No action required unless you want to use new features

3. **Apache Base Image** (Updated)
   - If using Apache, ensure you're using `gemvc/apache:latest`
   - Base image now uses PHP 8.4 Alpine
   - Existing Dockerfiles continue to work

4. **PHPStan Level 9** (Now Supported)
   - You can now safely use PHPStan Level 9 in your projects
   - Framework code is fully compliant
   - No changes required unless you want to enable Level 9

5. **Developer Assistant Web Interface** (New - Dev Environment)
   - Access the new Services page at `/index/developer#services`
   - Access the new Tables page at `/index/developer#tables`
   - Requires `APP_ENV=dev` and developer/admin authentication
   - Provides visual interface for service creation and table management
   - No CLI changes required - existing CLI commands still work

#### No Action Required

- All existing code continues to work
- No API changes
- No configuration changes needed
- No database migrations required

---

## Links

- [GitHub Repository](https://github.com/gemvc/library)
- [Documentation](https://gemvc.de)
- [Issue Tracker](https://github.com/gemvc/library/issues)

---

**Note**: This changelog follows the [Keep a Changelog](https://keepachangelog.com/) standard.


# Changelog

All notable changes to GEMVC Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.2.1] - 2025-12-31

### Added
- **APM Contracts System** - Pluggable Application Performance Monitoring
  - Integration with `gemvc/apm-contracts` package
  - `ApmFactory` for creating APM instances
  - `ApmInterface` for provider-agnostic APM operations
  - Automatic APM initialization in `ApiService` and `Controller`
  - Request tracing with root spans and child spans
  - Database query tracing via `UniversalQueryExecuter`
  - Exception recording for error tracking
  - Support for multiple APM providers (TraceKit, Datadog, etc.)

- **Apm Service** (`/api/Apm/*`) - Complete APM provider management
  - Test endpoints for APM tracing
  - Status and configuration endpoints
  - TraceKit-specific registration and verification
  - Health check heartbeat
  - Metrics, alerts, and webhook management
  - Subscription and plans information

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

## [5.1.4] - Previous Stable Release

### Summary
Previous stable release before the major optimization cycle that led to 5.2.0.

---

## Development Versions (Alpha 1-19)

### Alpha 19
- Final alpha version before release
- OpenSwoole Dockerfile optimization
- Final PHPStan Level 9 fixes
- Comprehensive testing

### Alpha 18
- Docker container builder enhancements
- Port conflict resolution improvements

### Alpha 17
- AsyncApiCall fire-and-forget implementation
- Background task execution support

### Alpha 16
- Apache PHP 8.4 Alpine base image development
- Base image optimization

### Alpha 15
- DockerContainerBuilder pre-flight checks
- Container conflict detection

### Alpha 14
- AsyncApiCall concurrent execution
- Connection pooling implementation

### Alpha 13
- PHPStan Level 9 compliance work
- Type safety improvements across codebase

### Alpha 12
- Controller layer optimizations
- Return type fixes

### Alpha 11
- ProjectHelper enhancements
- Version detection improvements

### Alpha 10
- Docker port mapping improvements
- Service name detection enhancements

### Alpha 9
- AsyncApiCall initial implementation
- Basic concurrent request support

### Alpha 8
- Docker container builder foundation
- Initial conflict detection

### Alpha 7
- Performance optimizations
- Memory management improvements

### Alpha 6
- Type safety enhancements
- PHPStan compliance work

### Alpha 5
- Documentation improvements
- Code examples enhancement

### Alpha 4
- Error handling improvements
- Better user feedback

### Alpha 3
- CLI enhancements
- Better error messages

### Alpha 2
- Initial optimization work
- Foundation improvements

### Alpha 1
- Started optimization cycle from 5.1.4
- Initial planning and architecture review

---

## Migration Notes

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
# GEMVC Framework - Release Notes

## Version 5.2.1 - APM Contracts & Server Monitoring

**Release Date**: 2025-12-31  
**Type**: Minor Release (Backward Compatible)

---

## 🎉 Overview

This release introduces a major architectural improvement with the **APM Contracts System**, making GEMVC compatible with multiple APM providers (TraceKit, Datadog, etc.) through a pluggable interface. Additionally, we've added comprehensive server monitoring capabilities and new developer tools, all while maintaining full backward compatibility.

---

## ✨ New Features

### 🔌 APM Contracts System

**Pluggable Application Performance Monitoring**

GEMVC now uses a contract-based APM system that supports multiple providers:

- **`gemvc/apm-contracts` Package Integration**
  - `ApmFactory` - Factory pattern for creating APM instances
  - `ApmInterface` - Standard interface for all APM providers (tracing)
  - `ApmToolkitInterface` - Standard interface for APM toolkit operations (management)
  - `AbstractApmToolkit` - Base class for provider toolkits with common functionality
  - Automatic provider detection and initialization
  - Backward compatible with existing TraceKit implementations

- **Automatic APM Integration**
  - `ApiService` and `Controller` automatically initialize APM instances
  - Request tracing with root spans and child spans
  - Database query tracing via `UniversalQueryExecuter`
  - Exception recording for error tracking
  - Shared trace IDs across all layers

- **Provider-Agnostic Design**
  - Switch between APM providers without code changes
  - Support for TraceKit, Datadog, and future providers
  - Environment-based configuration via `.env`

**Migration Path**: Existing TraceKit code continues to work. New projects can use any APM provider by installing the corresponding package (e.g., `composer require gemvc/apm-tracekit`).

### 📊 New Built-in Services

#### 1. **Apm Service** (`/api/Apm/*`)

Complete APM provider management and testing endpoints. **Fully provider-agnostic** - works with any APM provider that implements `ApmToolkitInterface`:

- `GET /api/Apm/test` - Test APM tracing with nested spans
- `GET /api/Apm/testError` - Test exception tracing
- `GET /api/Apm/status` - Get APM provider status and configuration
- `POST /api/Apm/register` - Register APM service (Provider-agnostic)
- `POST /api/Apm/verify` - Verify APM email code (Provider-agnostic)
- `POST /api/Apm/heartbeat` - Send health check heartbeat
- `GET /api/Apm/metrics` - Get service metrics
- `GET /api/Apm/alertsSummary` - Get alerts summary
- `GET /api/Apm/activeAlerts` - Get active alerts
- `GET /api/Apm/subscription` - Get subscription information
- `GET /api/Apm/plans` - List available plans
- `GET /api/Apm/webhooks` - List webhooks
- `POST /api/Apm/createWebhook` - Create webhook

**Authentication**: Requires `['developer','admin']` roles

#### 2. **GemvcAssistant Service** (`/api/GemvcAssistant/*`)

Developer and admin tools for project management:

- `POST /api/GemvcAssistant/export` - Export table data (CSV/SQL)
- `POST /api/GemvcAssistant/import` - Import table data from file
- `GET /api/GemvcAssistant/database` - Database management page data
- `GET /api/GemvcAssistant/config` - Configuration page data
- `GET /api/GemvcAssistant/isDbReady` - Check if database is ready
- `POST /api/GemvcAssistant/initDatabase` - Initialize database
- `GET /api/GemvcAssistant/services` - List all API services
- `POST /api/GemvcAssistant/createService` - Create new service
- `GET /api/GemvcAssistant/tables` - List all tables
- `POST /api/GemvcAssistant/migrateTable` - Migrate table

**Authentication**: Requires `['developer','admin']` roles

**Note**: These endpoints were moved from `Index.php` to provide better separation of concerns.

#### 3. **GemvcMonitoring Service** (`/api/GemvcMonitoring/*`)

Real-time server monitoring metrics:

- `GET /api/GemvcMonitoring/ram` - Get RAM/memory usage metrics
- `GET /api/GemvcMonitoring/cpu` - Get CPU usage and load metrics
- `GET /api/GemvcMonitoring/network` - Get network interface statistics
- `GET /api/GemvcMonitoring/databaseConnections` - Get database connection count
- `GET /api/GemvcMonitoring/databasePool` - Get database pool statistics
- `GET /api/GemvcMonitoring/databaseLatency` - Get database latency metrics

**Authentication**: Requires `['developer','admin']` roles

**Cross-Platform Support**: All monitoring endpoints work on Linux, Windows, and macOS.

### 🛠️ New Helper Classes

#### **ServerMonitorHelper**

Cross-platform server resource monitoring:

```php
// RAM metrics
$ram = ServerMonitorHelper::getMemoryUsage();
// Returns: ['current', 'peak', 'system_total', 'system_free', 'system_used', 'usage_percent']

// CPU metrics
$load = ServerMonitorHelper::getCpuLoad();      // Load average (1min, 5min, 15min)
$cores = ServerMonitorHelper::getCpuCores();     // CPU core count
$usage = ServerMonitorHelper::getCpuUsage();     // CPU usage percentage
```

**Platforms Supported**: Linux, Windows, macOS

#### **NetworkHelper**

Network interface statistics collection:

```php
// All network interfaces
$network = NetworkHelper::getNetworkStats();
// Returns: ['interfaces' => [...], 'totals' => [...]]

// List interfaces
$interfaces = NetworkHelper::getNetworkInterfaces();

// Specific interface stats
$stats = NetworkHelper::getInterfaceStats('eth0');
// Returns: ['bytes_received', 'bytes_sent', 'packets_received', 'packets_sent', ...]
```

**Platforms Supported**: Linux, Windows, macOS

---

## 🔄 Changes

### Core Framework

- **`ApiService`** - Now automatically initializes APM via `ApmFactory`
- **`Controller`** - Retrieves APM instance from Request object for shared tracing
- **`JsonResponse`** - Uses APM contracts for response tracing
- **`Bootstrap`** / **`SwooleBootstrap`** - Updated exception recording to use APM contracts
- **`UniversalQueryExecuter`** - Database query tracing via APM contracts
- **`ProjectHelper`** - Added `isApmEnabled()` method (replaces deprecated `isTraceKitEnabled()`)

### Request Object

- **`Request::$apm`** - New property for APM instance (replaces `$tracekit`)
- **`Request::$tracekit`** - Deprecated but maintained for backward compatibility

### Example Files

- **Directory Structure**: Example files moved from `src/startup/user/` to `src/startup/common/init_example/`
- **New Examples**: Added complete examples for Apm, GemvcAssistant, and GemvcMonitoring services

---

## 🐛 Bug Fixes

### Resource Management

- **Fixed `curl_close()` Resource Leak in `AsyncApiCall`**
  - Added explicit `curl_close($ch)` after `curl_multi_remove_handle()`
  - Prevents file descriptor exhaustion in long-running processes
  - Critical fix for Swoole environments

### PHPStan Compliance

- **Fixed PHPStan Level 9 Errors**
  - Added type checks for `NetworkHelper` and `ServerMonitorHelper`
  - Fixed ternary operator warnings in `JsonResponse`
  - Added `@phpstan-ignore-next-line` for abstract static method calls
  - All framework code now passes PHPStan Level 9

### Type Safety

- **Enhanced `ProjectHelper` Type Safety**
  - Improved type checking for `trim()`, `preg_match()`, `preg_replace()`
  - Better null handling and type narrowing
  - Removed redundant type checks

---

## 📚 Documentation Updates

### Comprehensive Documentation Refresh

All documentation files have been updated to reflect the new architecture:

- **ARCHITECTURE.md** - Added APM integration, new helper classes, and example services
- **README.md** - Added APM and monitoring features, updated example paths
- **AI_API_REFERENCE.md** - Complete API reference for new classes and services
- **AI_CONTEXT.md** - Added APM and server monitoring examples
- **GEMVC_GUIDE.md** - Added APM and monitoring helper methods
- **GEMVC_PHPDOC_REFERENCE.php** - Added PHPDoc for new helper classes
- **COPILOT_INSTRUCTIONS.md** - Added APM and monitoring references
- **HTTP_REQUEST_LIFE_CYCLE.md** - No changes (already up-to-date)
- **DATABASE_LAYER.md** - No changes (already up-to-date)

### Path Updates

- Updated all references from `src/startup/user/` to `src/startup/common/init_example/`

---

## 🔧 Dependencies

### New Dependencies

- **`gemvc/apm-contracts`** (^1.0) - APM contracts interface package

### Updated Dependencies

- No breaking changes to existing dependencies
- All dependencies remain compatible

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)
- New services require proper authentication (`['developer','admin']` roles)

---

## ⚙️ Configuration

### Environment Variables

New optional environment variables for APM:

```env
# APM Configuration
APM_NAME=TraceKit          # APM provider name (TraceKit, Datadog, etc.)
APM_ENABLED=true           # Enable/disable APM

# Provider-specific (e.g., TraceKit)
TRACEKIT_API_KEY=your_key
TRACEKIT_BASE_URL=https://app.tracekit.dev
TRACEKIT_SERVICE_NAME=gemvc-app
```

**Note**: APM is optional. If not configured, the framework works normally without APM.

---

## 🚀 Migration Guide

### From 5.2.0 to 5.2.1

This release is **fully backward compatible**. No breaking changes.

#### Optional: Enable APM

1. **Install APM Provider Package**
   ```bash
   composer require gemvc/apm-tracekit
   # or
   composer require gemvc/apm-datadog  # When available
   ```

2. **Configure Environment**
   ```env
   APM_NAME=TraceKit
   APM_ENABLED=true
   TRACEKIT_API_KEY=your_api_key
   ```

3. **No Code Changes Required**
   - APM is automatically initialized in `ApiService` and `Controller`
   - Existing code continues to work without modifications

#### Optional: Use New Services

The new services (Apm, GemvcAssistant, GemvcMonitoring) are included as examples during `gemvc init`. They are automatically copied to your `app/` directory.

**No action required** - they're ready to use if you need them.

#### Deprecated Methods

- `ProjectHelper::isTraceKitEnabled()` - Use `ProjectHelper::isApmEnabled()` instead
- `Request::$tracekit` - Use `Request::$apm` instead

**Note**: Deprecated methods still work but will be removed in a future version.

---

## 📊 Performance

- **No performance regressions** - All changes maintain or improve performance
- **APM overhead** - Minimal when enabled, zero when disabled
- **Monitoring helpers** - Efficient cross-platform implementation

---

## 🧪 Testing

- All existing tests pass
- PHPStan Level 9 compliance verified
- Cross-platform testing for monitoring helpers (Linux, Windows, macOS)

---

## 🙏 Acknowledgments

Special thanks to the community for feedback and contributions that helped shape this release.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/library
- **Issues**: https://github.com/gemvc/library/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: `ProjectHelper::isTraceKitEnabled()`, `Request::$tracekit`  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+


**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.4.0...5.4.1
# GEMVC Framework - Release Notes

## Version 5.4.1 - Docker Healthcheck Fix and TraceKit Default

**Release Date**: 2026-06-05  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release fixes the OpenSwoole Dockerfile healthcheck endpoint and officially includes TraceKit as a default dependency in the GEMVC package. All changes are backward compatible and require no code modifications.

---

## 🐛 Bug Fixes

### OpenSwoole Dockerfile Healthcheck

- **Fixed healthcheck endpoint URL** - Changed from `/index/index` to `/api`
  - Healthcheck now correctly uses the standard GEMVC API healthcheck endpoint
  - Fixes Docker healthcheck failures in OpenSwoole containers
  - Aligns with GEMVC documentation and standard API endpoint structure
  - Location: `src/startup/swoole/Dockerfile`

**Before:**
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9501/index/index || exit 1
```

**After:**
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9501/api || exit 1
```

---

## ✨ Added

### TraceKit as Default Dependency

- **TraceKit APM Provider** - Now included as a default dependency in `composer.json`
  - `gemvc/apm-tracekit` is now a required dependency (not optional)
  - TraceKit is the default APM provider for GEMVC Framework
  - No additional `composer require` needed for TraceKit integration
  - Simplifies APM setup for new projects

**Benefits:**
- Zero-configuration APM setup with TraceKit
- Automatic APM tracing out of the box
- Consistent APM experience across all GEMVC projects
- Reduced setup steps for developers

**Note:** TraceKit was previously available but required manual installation. It is now included by default in the GEMVC package.

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

**For TraceKit Users:**
- TraceKit is now automatically installed with GEMVC
- Configure TraceKit in your `.env` file as before:
  ```env
  APM_NAME=TraceKit
  TRACEKIT_API_KEY=your-api-key
  TRACEKIT_API_URL=https://app.tracekit.dev/v1/traces
  ```

---

## 🚀 Performance

- No performance impact from these changes
- Healthcheck fix improves Docker container reliability
- TraceKit inclusion has no runtime overhead when not configured

---

## 🔄 Migration Guide

### From 5.4.0 to 5.4.1

This release is **fully backward compatible**. No action required.

**What Changed**:
- OpenSwoole Dockerfile healthcheck endpoint fixed
- TraceKit now included as default dependency

**Benefits**:
- Docker healthchecks now work correctly
- Simplified APM setup with TraceKit included by default
- Better container monitoring and reliability

**Action Required**:
- **None** - automatic upgrade recommended
- If you manually installed TraceKit, you can remove it from your `composer.json` (it's now included by default)
- Rebuild Docker containers to get the healthcheck fix

**Breaking Changes**:
- None

---

## 🙏 Acknowledgments

Thank you to the community for reporting the Docker healthcheck issue and providing feedback.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.3.0...5.4.0
## Version 5.4.0 - Native APM Integration

**Release Date**: 2026-01-04  
**Type**: Minor Release (Backward Compatible)

---

##  Overview

This release introduces **Native APM (Application Performance Monitoring) Integration** with automatic request tracing, controller method tracing, and database query tracing. The APM system provides complete visibility into your application's performance with zero configuration required. All tracing is environment-controlled, allowing you to enable or disable features without code changes.

---

##  New Features

### 🚀 Native APM Integration

A complete Application Performance Monitoring solution built directly into the framework, providing automatic tracing of requests, controllers, and database queries with zero configuration required.

#### **Key Features**:

1. **Automatic Root Tracing**:
   - APM initialized early in `Bootstrap.php` and `SwooleBootstrap.php`
   - Root trace automatically created at request start
   - Captures full request lifecycle from start to finish
   - All spans share the same `traceId` for complete request visibility
   - Exception tracking automatically records all exceptions

2. **Controller Tracing** (Environment-Controlled):
   - Use `$this->callController()` in API services for automatic tracing
   - Controlled via `APM_TRACE_CONTROLLER=1` environment variable
   - Automatic spans for controller method calls
   - Captures method name, HTTP response code, execution time, and response data
   - Zero code changes needed - same code works with/without tracing

3. **Database Query Tracing** (Environment-Controlled):
   - Automatic APM spans for all database queries
   - Controlled via `APM_TRACE_DB_QUERY=1` environment variable
   - Captures query type (SELECT, INSERT, UPDATE, DELETE), execution time, rows affected, and SQL statement
   - Request propagation through all database layers
   - Use `$this->createModel()` in controllers for automatic Request propagation

4. **Trace Context Propagation**:
   - Request object carries APM instance (`$request->apm`)
   - Automatic propagation: Bootstrap → ApiService → Controller → Table → Database
   - All spans automatically linked to root trace
   - Fire-and-forget pattern for non-blocking trace sending

#### **Access**:
- Works automatically when APM provider is configured
- No special endpoints or URLs required
- Traces sent asynchronously after HTTP response

#### **Technical Implementation**:
- **Early Initialization**: APM created in Bootstrap/SwooleBootstrap constructors
- **Request Propagation**: `$request->apm` carries trace context through all layers
- **Environment Control**: Tracing enabled/disabled via environment variables
- **Provider Agnostic**: Works with any APM provider via `gemvc/apm-contracts`
- **Performance**: Non-blocking trace sending (fire-and-forget pattern)
- **Compatibility**: Works with all webserver types (Apache, Nginx, OpenSwoole)

### 🔧 Developer Tools

#### **ApmTracingTrait**
- Unified APM tracing methods for reuse across all layers
- `startApmSpan()` - Start a new APM span with attributes
- `endApmSpan()` - End a span with status and attributes
- `recordApmException()` - Record exceptions in spans
- `traceApm()` - Convenience method for wrapping operations in spans

#### **Controller::createModel()**
- Helper method for automatic Request propagation to models
- Ensures trace context is available for database query tracing
- Simple one-line usage: `$model = $this->createModel(new UserModel());`

#### **ApiService::callController()**
- Recommended method for invoking controllers with automatic tracing
- Better naming than deprecated `callWithTracing()`
- Environment-controlled (respects `APM_TRACE_CONTROLLER` flag)

---

## 🔄 Changes

### Bootstrap / SwooleBootstrap

- **APM Initialization** - Moved to constructor for early tracing
- **Root Trace Creation** - Automatic root trace at request start
- **Request APM Setting** - Explicit `$request->apm` assignment for trace context
- **Exception Handling** - Updated to set `$request->apm` for fallback APM instances

### ApiService

- **APM Initialization Removed** - Now handled by Bootstrap (earlier in lifecycle)
- **callController() Method** - New recommended method for controller invocation with tracing
- **callWithTracing() Deprecated** - Kept for backward compatibility, use `callController()` instead
- **ApmTracingTrait Integration** - Uses unified tracing methods

### Controller

- **APM Retrieval** - Gets APM instance from `$request->apm` (shared trace context)
- **createModel() Helper** - New method for automatic Request propagation to models
- **createList() Updated** - Now uses `createModel()` for Request propagation

### Database Layer

- **Table::setRequest()** - New method for trace context propagation
- **ConnectionManager::setRequest()** - Propagates Request to PdoQuery
- **PdoQuery::setRequest()** - Propagates Request to UniversalQueryExecuter
- **UniversalQueryExecuter** - Database query tracing implementation
- **Request Propagation Chain** - Complete trace context flow through all database layers

### JsonResponse

- **JsonSerializable Implementation** - Excludes internal APM properties from JSON output
- **Clean API Responses** - `_apm_span`, `_apm_model_name`, `_apm_method_name` no longer appear in responses

---

## 🐛 Bug Fixes

- **ApiService::callController()** - Fixed static method calls to use `AbstractApm` instead of interface
  - Resolves "Cannot call abstract method" errors
  - Changed `ApmInterface::determineStatusFromHttpCode()` to inline status determination
  - Changed `ApmInterface::limitStringForTracing()` to `AbstractApm::limitStringForTracing()`

- **JsonResponse JSON Output** - Removed internal APM properties from API responses
  - `_apm_span`, `_apm_model_name`, `_apm_method_name` no longer appear in JSON
  - Clean API responses for all endpoints

---

## 📚 Documentation Updates

- **GEMVC_APM_INTEGRATION.md** - Comprehensive APM integration guide
  - Architecture overview and trace flow diagrams
  - Configuration instructions and environment variables
  - Usage examples and best practices
  - Performance considerations and troubleshooting
  - Advanced usage patterns

- **README.md** - Updated with prominent APM integration section
  - Key features highlighting native APM
  - Quick setup instructions
  - What gets traced automatically
  - Performance notes and documentation links

- **ARCHITECTURE.md** - Added APM Integration Architecture section
  - Request flow diagrams with APM tracing
  - APM architecture flow diagram
  - Component breakdown with APM features
  - Performance characteristics

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)
- APM tracing is read-only (no data modification)
- Trace data sent asynchronously (fire-and-forget pattern)
- No sensitive data exposed in traces (configurable via APM provider)

---

## ⚙️ Configuration

### Required Configuration

1. **Install APM Provider Package**:
   ```bash
   composer require gemvc/apm-tracekit
   # or your preferred APM provider
   ```

2. **Set APM Provider in `.env`**:
   ```env
   APM_NAME=TraceKit
   TRACEKIT_API_KEY=your-api-key
   TRACEKIT_API_URL=https://app.tracekit.dev/v1/traces
   ```

### Optional Configuration

1. **Enable Controller Tracing**:
   ```env
   APM_TRACE_CONTROLLER=1
   ```

2. **Enable Database Query Tracing**:
   ```env
   APM_TRACE_DB_QUERY=1
   ```

3. **Configure Sample Rate** (TraceKit):
   ```env
   TRACEKIT_SAMPLE_RATE=1.0  # 0.0 to 1.0 (1.0 = 100%)
   ```

### Usage in Code

1. **In API Services** - Use `callController()`:
   ```php
   return $this->callController(new UserController($this->request))->create();
   ```

2. **In Controllers** - Use `createModel()`:
   ```php
   $model = $this->createModel(new UserModel());
   ```

---

## 🚀 Performance

- **Zero Overhead When Disabled** - Environment flags control tracing (no performance impact when off)
- **Minimal Overhead When Enabled** - ~0.25ms per request when tracing is active
- **Non-Blocking Trace Sending** - Traces sent after HTTP response (fire-and-forget pattern)
- **Sample Rate Support** - Control trace volume via `TRACEKIT_SAMPLE_RATE` (reduce overhead)
- **Efficient Span Management** - Spans created and closed efficiently with minimal memory footprint
- **Request Propagation** - Single APM instance shared across all layers (no duplication)

---

## 🧪 Testing

- **ControllerCreateModelTest** - Tests Request propagation to models via `createModel()`
- **TableRequestPropagationTest** - Tests Request propagation through database layers
- **UniversalQueryExecuterApmTest** - Tests APM tracing in database queries
- All tests passing with PHPUnit
- Comprehensive coverage of APM tracing functionality

---

## 🔄 Migration Guide

### From 5.3.0 to 5.4.0

This release is **fully backward compatible**. No action required.

**What's New**:
- Native APM integration with automatic request tracing
- Controller tracing via `callController()` method (environment-controlled)
- Database query tracing (environment-controlled)
- Request propagation through all layers for complete trace visibility
- `ApmTracingTrait` for unified APM tracing
- `Controller::createModel()` helper for automatic Request propagation

**Benefits**:
- Zero-configuration APM tracing (works out of the box)
- Complete request visibility from Bootstrap to Database
- Environment-controlled tracing (enable/disable via env vars)
- Non-blocking trace sending (no performance impact)
- Same code works with/without tracing (no code changes needed)

**Optional Configuration**:
1. Install APM provider: `composer require gemvc/apm-tracekit`
2. Set `APM_NAME=TraceKit` in `.env`
3. Enable controller tracing: `APM_TRACE_CONTROLLER=1`
4. Enable database tracing: `APM_TRACE_DB_QUERY=1`

**Optional Code Changes**:
- Replace `callWithTracing()` with `callController()` (better naming)
- Use `createModel()` in controllers for automatic Request propagation
- See `GEMVC_APM_INTEGRATION.md` for complete documentation

**Breaking Changes**:
- None - `callWithTracing()` still works but is deprecated

---

## 🙏 Acknowledgments

Special thanks to the community for feedback and feature requests that led to this comprehensive monitoring solution.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

## [Gemvc PHP Framework built for Microservices](https://gemvc.de)
### Made with ❤️ by Ali Khorsandfard


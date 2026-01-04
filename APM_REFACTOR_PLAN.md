# APM Initialization Refactoring Plan

## Overview

This document outlines the plan to refactor APM (Application Performance Monitoring) initialization in the GEMVC framework. The primary goal is to move APM initialization from `ApiService` constructor to `Bootstrap` and `SwooleBootstrap` constructors, ensuring APM is initialized early in the request lifecycle to capture the full request trace.

**⚠️ IMPORTANT:** This plan has been reviewed and updated. See `APM_IMPLEMENTATION_REVIEW.md` for critical fixes and recommendations that have been incorporated into this plan.

## Current Architecture

### Current Flow

```
Request Created → Bootstrap Created → Routing → ApiService Created → APM Initialized
```

### Problems with Current Architecture

1. **Late Initialization**: APM is initialized in `ApiService` constructor, which happens after routing
2. **Multiple Factory Calls**: `ApmFactory::create()` is called in multiple places (ApiService, Controller, Bootstrap exception handlers)
3. **Incomplete Tracing**: Root trace doesn't capture full request lifecycle (routing, service resolution)
4. **Tight Coupling**: ApiService depends on APM initialization

## Proposed Architecture

### New Flow

```
Request Created → APM Initialized (in Bootstrap) → Routing → ApiService Created → Uses Existing APM
```

### Benefits

1. **Early Initialization**: APM initialized immediately after Request creation, before routing
2. **Single Initialization Point**: Bootstrap/SwooleBootstrap are the only places that initialize APM
3. **Cleaner Separation**: Better separation of concerns
4. **Better Performance**: No redundant factory calls
5. **Complete Tracing**: Root trace captures everything from request start

## Fire-and-Forget Pattern (CRITICAL - Must Preserve)

**IMPORTANT**: APM traces are sent using a **fire-and-forget** pattern to ensure non-blocking trace sending.

### How It Works

- APM provider's `flush()` method is called via `register_shutdown_function` (handled automatically by `ApmFactory::create()`)
- `flush()` uses `AsyncApiCall::fireAndForget()` for non-blocking HTTP requests
- For **Apache/Nginx**: Uses `fastcgi_finish_request()` to send HTTP response first, then sends traces in background
- For **OpenSwoole**: Executes in background task
- Traces are sent **AFTER** HTTP response is sent to client (prevents empty response body issues)

### Why It Matters

- Traces don't block the HTTP response
- Client gets response immediately
- Traces are sent asynchronously in background
- No performance impact on request handling

### Preservation During Refactor

- Moving APM initialization to Bootstrap does **NOT** affect this behavior
- `ApmFactory::create()` automatically registers shutdown function (handled by APM provider implementation)
- The shutdown function registration happens in the APM provider's constructor (e.g., `TraceKitModel`)
- **No changes needed** - fire-and-forget pattern is preserved automatically

### Verification

- After refactor, verify that traces are still sent after HTTP response
- Check that `fastcgi_finish_request()` is called (Apache/Nginx)
- Check that Swoole background tasks work (OpenSwoole)
- Ensure no blocking occurs during trace sending

## Implementation Plan

### 1. Initialize APM in Bootstrap Constructor

**File:** `src/Core/Bootstrap.php`

- Add APM initialization in constructor **after** Request is set but **before** routing
- Initialize after `$this->request = $request;` and before `$this->setRequestedService();`
- Store APM instance in `$this->request->apm` (ApmFactory already does this)
- Add private property `private ?ApmInterface $apm = null;` for Bootstrap's own use

**Implementation:**

```php
public function __construct(Request $request)
{
    $this->request = $request;
    
    // Initialize APM early to capture full request lifecycle
    $this->initializeApm();
    
    $this->setRequestedService();
    $this->runApp();
}

private function initializeApm(): void
{
    $apmName = ApmFactory::isEnabled();
    if (!$apmName) {
        return;
    }
    $this->apm = ApmFactory::create($this->request);
    // ApmFactory::create() already sets $this->request->apm
}
```

### 2. Initialize APM in SwooleBootstrap Constructor

**File:** `src/Core/SwooleBootstrap.php`

- Add APM initialization in constructor **after** Request is set but **before** routing
- Initialize after `$this->request = $request;` and before `$this->extractRouteInfo();`
- Add private property `private ?ApmInterface $apm = null;` for SwooleBootstrap's own use

**Implementation:**

```php
public function __construct(Request $request)
{
    $this->request = $request;
    
    // Initialize APM early to capture full request lifecycle
    $this->initializeApm();
    
    $this->extractRouteInfo();
}

private function initializeApm(): void
{
    $apmName = ApmFactory::isEnabled();
    if (!$apmName) {
        return;
    }
    $this->apm = ApmFactory::create($this->request);
    // ApmFactory::create() already sets $this->request->apm
}
```

### 3. Remove APM Initialization from ApiService

**File:** `src/Core/ApiService.php`

- Remove APM initialization from constructor
- Remove `private ?ApmInterface $apm = null;` property
- Update `callWithTracing()` to use `$this->request->apm` instead of `$this->apm`
- Update `ControllerTracingProxy` instantiation to use `$this->request->apm`

**Implementation:**

```php
public function __construct(Request $request)
{
    $this->errors = [];
    $this->request = $request;
    
    // APM is now initialized in Bootstrap, available via $request->apm
    // No need to initialize here
}

protected function callWithTracing(Controller $controller): ControllerTracingProxy
{
    return new ControllerTracingProxy($controller, $this->request->apm);
}
```

### 4. Update Controller to Use Request APM

**File:** `src/Core/Controller.php`

- Remove `initializeApm()` method
- Remove `private ?ApmInterface $apm = null;` property
- Update `getApm()` to return `$this->request->apm`
- Update all APM-related methods to use `$this->request->apm` instead of `$this->apm`

**Implementation:**

```php
public function __construct(Request $request)
{
    $this->errors = [];
    $this->request = $request;
    
    // APM is now initialized in Bootstrap, available via $request->apm
    // No need to initialize here
}

protected function getApm(): ?ApmInterface
{
    return $this->request->apm;
}

protected function startTraceSpan(...): array
{
    if ($this->request->apm === null) {
        return [];
    }
    // ... rest of method using $this->request->apm
}
```

### 5. Update Bootstrap Exception Handlers

**File:** `src/Core/Bootstrap.php`

- Update `recordExceptionInApm()` to use `$this->request->apm` instead of creating new instance
- Remove redundant `ApmFactory::create()` call

**Implementation:**

```php
private function recordExceptionInApm(\Throwable $exception): void
{
    // Use APM instance already initialized in constructor
    if ($this->request->apm !== null) {
        $this->request->apm->recordException([], $exception);
    }
}
```

### 6. Update SwooleBootstrap Exception Handler

**File:** `src/Core/SwooleBootstrap.php`

- Update `recordExceptionInApm()` to use `$this->request->apm` instead of creating new instance
- Remove redundant `ApmFactory::create()` call and duplicate logic

**Implementation:**

```php
private function recordExceptionInApm(\Throwable $exception): void
{
    // Use APM instance already initialized in constructor
    if ($this->request->apm !== null) {
        $this->request->apm->recordException([], $exception);
    }
}
```

### 7. Update UniversalQueryExecuter to Use Request APM

**File:** `src/database/UniversalQueryExecuter.php`

**Current Issue:**
- Test file shows APM integration using `ApmFactory::create(null)` (see `tests/Unit/Database/UniversalQueryExecuter.php` lines 280-360)
- This creates a separate APM instance that won't share the same traceId as the request
- Database query spans should be part of the same trace as the request
- **Source file doesn't have APM tracing yet** - needs to be added

**Solution:**
- Add optional `?Request $request = null` parameter to constructor
- Store Request as private property
- In `execute()` method, use `$this->request->apm` if Request is provided, otherwise fall back to `ApmFactory::create(null)`
- This ensures database query spans share the same traceId when called from request context
- **Reference test file** for implementation pattern

**Implementation:**

```php
class UniversalQueryExecuter
{
    private ?Request $request = null;
    
    public function __construct(?Request $request = null)
    {
        $this->startExecutionTime = microtime(true);
        $this->dbManager = DatabaseManagerFactory::getManager();
        $this->request = $request;
    }
    
    // NO static cache - env vars are fast, and static cache causes OpenSwoole bugs
    private static function shouldTraceDbQuery(): bool
    {
        $value = $_ENV['APM_TRACE_DB_QUERY'] ?? null;
        return ($value === '1' || $value === 'true');
    }
    
    public function execute(): bool
    {
        // ... existing code before execution ...
        
        // APM integration: Use Request APM if available, otherwise create standalone
        $dbSpan = [];
        $shouldTrace = false;
        $apm = null;
        
        // Try to use APM from Request first (shares same traceId)
        if ($this->request !== null && $this->request->apm !== null) {
            $apm = $this->request->apm;
        } elseif (ProjectHelper::isApmEnabled() !== null) {
            // Fallback: create standalone APM instance (for CLI, background jobs, etc.)
            $apm = ApmFactory::create(null);
        }
        
        // Check if tracing is enabled (no static cache - see review)
        if ($apm !== null && $apm->isEnabled() && self::shouldTraceDbQuery() && $apm->shouldTraceDbQuery()) {
            $shouldTrace = true;
            
            // Optimize query type detection (only first 10 chars)
            $queryStart = substr($this->query, 0, 10);
            $queryUpper = strtoupper(ltrim($queryStart));
            $queryType = match(true) {
                str_starts_with($queryUpper, 'SELECT') => 'SELECT',
                str_starts_with($queryUpper, 'INSERT') => 'INSERT',
                str_starts_with($queryUpper, 'UPDATE') => 'UPDATE',
                str_starts_with($queryUpper, 'DELETE') => 'DELETE',
                default => 'UNKNOWN'
            };
            
            // Start span with error handling
            try {
                $dbSpan = $apm->startSpan('database-query', [
                    'db.system' => 'mysql',  // TODO: Detect from connection
                    'db.operation' => $queryType,
                    'db.statement' => $this->query,
                    'db.parameter_count' => count($this->bindings),  // Performance: count only
                    'db.in_transaction' => $this->inTransaction ? 'true' : 'false',
                ], ApmInterface::SPAN_KIND_CLIENT);
            } catch (\Throwable $e) {
                // Graceful degradation
                if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                    error_log("APM tracing error: " . $e->getMessage());
                }
                $dbSpan = [];
                $shouldTrace = false;
            }
        }
        
        try {
            $this->statement->execute();
            $this->affectedRows = $this->statement->rowCount();
            // ... existing code ...
            $this->endExecutionTime = microtime(true);
            
            // End span BEFORE connection release (has all data: affectedRows, lastInsertedId, executionTime)
            if ($shouldTrace && !empty($dbSpan) && $apm !== null) {
                try {
                    $executionTime = $this->getExecutionTime();
                    $apm->endSpan($dbSpan, [
                        'db.rows_affected' => (string)$this->affectedRows,
                        'db.execution_time_ms' => (string)$executionTime,
                        'db.last_insert_id' => $this->lastInsertedId !== false ? (string)$this->lastInsertedId : 'none',
                    ], ApmInterface::STATUS_OK);
                } catch (\Throwable $e) {
                    // Graceful degradation
                    if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                        error_log("APM endSpan error: " . $e->getMessage());
                    }
                }
            }
            
            // ... existing connection release code ...
            return true;
        } catch (\PDOException $e) {
            // Record exception and end span with error
            if ($shouldTrace && !empty($dbSpan) && $apm !== null) {
                try {
                    $apm->recordException($dbSpan, $e);
                    $apm->endSpan($dbSpan, [
                        'db.execution_time_ms' => (string)$this->getExecutionTime(),
                    ], ApmInterface::STATUS_ERROR);
                } catch (\Throwable $apmError) {
                    // Graceful degradation
                    if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                        error_log("APM error handling failed: " . $apmError->getMessage());
                    }
                }
            }
            
            // ... existing error handling ...
            return false;
        }
    }
}
```

**Request Propagation:**
- Table class can accept optional Request via setter and pass to ConnectionManager
- ConnectionManager passes to PdoQuery
- PdoQuery passes to UniversalQueryExecuter
- Controller already has Request, so it can pass it when creating Model instances
- **All optional** - works without Request (falls back to standalone APM)

**Critical Fixes Applied:**
- ✅ No static cache (OpenSwoole safe)
- ✅ Error handling around all APM calls (graceful degradation)
- ✅ Performance optimizations (query type detection, parameter count only)
- ✅ Tracing in UniversalQueryExecuter (not PdoQuery) - has all execution data

### 8. Create ApmTracingTrait for DRY APM Tracing

**File:** `src/Core/Apm/ApmTracingTrait.php` (new file)

**Why Trait Instead of Helper Class:**
- **DRY Principle**: Single source of truth for APM tracing logic
- **Reusable**: Can be used by Bootstrap, ApiService, Controller, UniversalQueryExecuter, Models
- **No New Helper Class**: Keeps architecture clean
- **Consistent API**: Same methods available everywhere

**Purpose:**
- Provide unified APM tracing methods for ALL layers (Bootstrap, ApiService, Controller, UniversalQueryExecuter, Models)
- Works for Models that extend Table AND Models that don't
- Automatically uses Request APM when available (shares traceId)
- Falls back to standalone APM for CLI/background jobs

**Design:**
- Trait that can be used by any class
- Methods: `startApmSpan()`, `endApmSpan()`, `recordApmException()`, `traceApm()`
- Automatically detects Request APM from `$this->request->apm` or creates standalone
- Developer-friendly API, consistent across all layers

**Implementation:**

```php
<?php

namespace Gemvc\Core\Apm;

use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Http\Request;

/**
 * APM Tracing Trait
 * 
 * Provides unified APM tracing methods for all framework layers.
 * Use this trait in Bootstrap, ApiService, Controller, UniversalQueryExecuter, and Models.
 * 
 * Automatically uses Request APM when available (shares traceId),
 * falls back to standalone APM for CLI/background jobs.
 * 
 * Usage:
 * ```php
 * class UserModel extends UserTable
 * {
 *     use ApmTracingTrait;
 *     
 *     public function complexOperation(): JsonResponse
 *     {
 *         return $this->traceApm('complex-calculation', function() {
 *             return $this->doComplexWork();
 *         }, ['model' => 'UserModel']);
 *     }
 * }
 * ```
 */
trait ApmTracingTrait
{
    /**
     * Get APM instance (Request APM if available, otherwise standalone)
     * 
     * Tries to get APM from:
     * 1. $this->request->apm (if class has Request property)
     * 2. ApmFactory::create(null) (standalone, for CLI/background jobs)
     * 
     * @return ApmInterface|null
     */
    protected function getApm(): ?ApmInterface
    {
        // Try to use Request APM first (shares traceId)
        if (property_exists($this, 'request') && $this->request instanceof Request) {
            if ($this->request->apm !== null) {
                return $this->request->apm;
            }
        }
        
        // Fallback: create standalone APM (for CLI, background jobs, etc.)
        $apmName = ApmFactory::isEnabled();
        if ($apmName === null) {
            return null;
        }
        
        return ApmFactory::create(null);
    }
    
    /**
     * Start an APM span
     * 
     * @param string $operationName Name of the operation
     * @param array<string, mixed> $attributes Additional span attributes
     * @param int $kind Span kind (default: SPAN_KIND_INTERNAL)
     * @return array<string, mixed> Span data (empty array if APM disabled or error)
     */
    protected function startApmSpan(
        string $operationName,
        array $attributes = [],
        int $kind = ApmInterface::SPAN_KIND_INTERNAL
    ): array {
        $apm = $this->getApm();
        if ($apm === null || !$apm->isEnabled()) {
            return [];
        }
        
        try {
            return $apm->startSpan($operationName, $attributes, $kind);
        } catch (\Throwable $e) {
            // Silently fail - don't break operations if APM has issues
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("ApmTracingTrait::startApmSpan() error: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * End an APM span
     * 
     * @param array<string, mixed> $spanData Span data from startApmSpan()
     * @param array<string, mixed> $attributes Final span attributes
     * @param string $status Status: 'OK' or 'ERROR'
     * @return void
     */
    protected function endApmSpan(
        array $spanData,
        array $attributes = [],
        string $status = 'OK'
    ): void {
        if (empty($spanData)) {
            return; // Span was not started (APM disabled or error)
        }
        
        $apm = $this->getApm();
        if ($apm === null) {
            return;
        }
        
        try {
            $statusValue = ($status === 'ERROR') ? ApmInterface::STATUS_ERROR : ApmInterface::STATUS_OK;
            $apm->endSpan($spanData, $attributes, $statusValue);
        } catch (\Throwable $e) {
            // Silently fail - don't break operations if APM has issues
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("ApmTracingTrait::endApmSpan() error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Record an exception in an APM span
     * 
     * @param array<string, mixed> $spanData Span data from startApmSpan()
     * @param \Throwable $exception The exception to record
     * @return void
     */
    protected function recordApmException(array $spanData, \Throwable $exception): void
    {
        if (empty($spanData)) {
            return; // Span was not started (APM disabled or error)
        }
        
        $apm = $this->getApm();
        if ($apm === null) {
            return;
        }
        
        try {
            $apm->recordException($spanData, $exception);
        } catch (\Throwable $e) {
            // Silently fail - don't break operations if APM has issues
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("ApmTracingTrait::recordApmException() error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Trace an operation with automatic span management
     * 
     * Executes a callback within an APM span, automatically handling start/end/exception.
     * 
     * @param string $operationName Name of the operation
     * @param callable $callback The operation to trace
     * @param array<string, mixed> $attributes Additional span attributes
     * @return mixed The return value of the callback
     * @throws \Throwable Re-throws any exception from callback
     */
    protected function traceApm(string $operationName, callable $callback, array $attributes = []): mixed
    {
        $span = $this->startApmSpan($operationName, $attributes);
        
        try {
            $result = $callback();
            $this->endApmSpan($span, [], 'OK');
            return $result;
        } catch (\Throwable $e) {
            $this->recordApmException($span, $e);
            $this->endApmSpan($span, [], 'ERROR');
            throw $e;
        }
    }
}
```

**Usage in Different Layers:**

```php
// In Model (with or without extending Table)
class UserModel extends UserTable
{
    use ApmTracingTrait;
    
    public function complexOperation(): JsonResponse
    {
        return $this->traceApm('complex-calculation', function() {
            return $this->doComplexWork();
        }, ['model' => 'UserModel']);
    }
}

// In Controller
class UserController extends Controller
{
    use ApmTracingTrait; // Already has $this->request
    
    public function customMethod(): JsonResponse
    {
        $span = $this->startApmSpan('custom-controller-operation');
        // ... do work ...
        $this->endApmSpan($span, [], 'OK');
    }
}

// In UniversalQueryExecuter (when Request is passed)
class UniversalQueryExecuter
{
    use ApmTracingTrait;
    private ?Request $request = null;
    
    public function __construct(?Request $request = null)
    {
        $this->request = $request;
        // ... rest of constructor
    }
}

// In Bootstrap (has $this->request)
class Bootstrap
{
    use ApmTracingTrait;
    private Request $request;
    // ... can now use $this->startApmSpan(), etc.
}
```

## Files to Modify

1. `src/Core/Bootstrap.php` - Add APM initialization in constructor
2. `src/Core/SwooleBootstrap.php` - Add APM initialization in constructor
3. `src/Core/ApiService.php` - Remove APM initialization, use `$request->apm`
4. `src/Core/Controller.php` - Remove APM initialization, use `$request->apm`, optionally use ApmTracingTrait
5. `src/database/UniversalQueryExecuter.php` - Add Request parameter and use `$request->apm` for tracing
6. `src/Core/Apm/ApmTracingTrait.php` - **NEW FILE** - Create trait for unified APM tracing across all layers
7. Update exception handlers in both Bootstrap classes

## Testing Considerations

### 1. Verify APM is initialized before routing
- Check that `$request->apm` is set in Bootstrap constructor
- Verify root trace includes routing operations

### 2. Verify backward compatibility
- Ensure `$request->apm` is still accessible from ApiService, Controller, and other components
- Verify `$request->tracekit` (deprecated) still works

### 3. Test exception handling
- Verify exceptions are still recorded in APM
- Test both Bootstrap and SwooleBootstrap exception paths

### 4. Test APM disabled scenario
- Verify graceful handling when APM is not enabled
- Ensure no errors when `$request->apm` is null

### 5. Verify fire-and-forget pattern
- Verify that traces are still sent after HTTP response
- Check that `fastcgi_finish_request()` is called (Apache/Nginx)
- Check that Swoole background tasks work (OpenSwoole)
- Ensure no blocking occurs during trace sending

## Migration Notes

- This is a **breaking change** for internal architecture but **backward compatible** for API consumers
- All existing code using `$request->apm` will continue to work
- The change is transparent to application developers (ApiService, Controller users)
- APM providers (TraceKit, Datadog, etc.) don't need changes

## Dependencies

- Requires `gemvc/apm-contracts` package (already in composer.json)
- ApmFactory must support singleton pattern (reusing same instance via Request)

## Task Checklist

- [ ] Add APM initialization to Bootstrap constructor (after Request, before routing)
- [ ] Add APM initialization to SwooleBootstrap constructor (after Request, before routing)
- [ ] Remove APM initialization from ApiService constructor and update to use `$request->apm`
- [ ] Update Controller to remove `initializeApm()` and use `$request->apm` instead
- [ ] Update `Bootstrap::recordExceptionInApm()` to use `$request->apm` instead of `ApmFactory::create()`
- [ ] Update `SwooleBootstrap::recordExceptionInApm()` to use `$request->apm` instead of `ApmFactory::create()`
- [ ] Update UniversalQueryExecuter to accept optional Request and use `$request->apm` for database query tracing
- [ ] Create ApmTracingTrait for unified APM tracing across all layers
- [ ] Test APM initialization timing and verify root trace captures full request lifecycle
- [ ] Verify backward compatibility - ensure `$request->apm` is accessible from all components
- [ ] Verify fire-and-forget pattern still works - traces sent after HTTP response (non-blocking)


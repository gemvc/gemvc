# GEMVC APM Integration Guide

**Audience:** enabling / debugging APM via **`gemvc/apm-contracts`** (any provider). TraceKit is one implementation.

**Related:** [api.md](api.md) · [controller.md](controller.md) · [ecosystem.md](ecosystem.md) · [openswoole.md](openswoole.md) · [CANONICAL.md](../ai/CANONICAL.md) · `vendor/gemvc/apm-contracts/README.md`

## Reading map (AI)

| Need | Jump to |
|------|---------|
| Env flags (contracts `APM_*`) | [Environment Configuration](#environment-configuration) |
| Provider example (TraceKit) | [TraceKit provider (example)](#tracekit-provider-example) |
| Root / controller / DB spans | [Automatic Tracing](#automatic-tracing) |
| Apache `callController` vs Swoole | [Controller Operation Tracing](#2-controller-operation-tracing) |
| `createModel` / Request wire | [Best Practices](#best-practices) |
| New provider | [Custom APM Provider](#custom-apm-provider) |
| Missing traces | [Troubleshooting](#troubleshooting) |

**AI rule:** Do not ingest this whole file for routine CRUD (~900+ lines). Framework talks to **`ApmFactory` / `ApmInterface`** (`gemvc/apm-contracts`) — never hardcode TraceKit in app code. Root tracing needs no app code. Controller spans need `callController` on **`ApiService`** (all servers; deprecated `SwooleApiService` inherits it). Prefer [api.md](api.md) / [controller.md](controller.md) for invoke style; jump via the map above.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Environment Configuration](#environment-configuration)
- [Automatic Tracing](#automatic-tracing)
- [Manual Tracing](#manual-tracing)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)
- [Performance Considerations](#performance-considerations)
- [Troubleshooting](#troubleshooting)

## Overview

GEMVC provides **automatic Application Performance Monitoring** through **`gemvc/apm-contracts`** (required by `gemvc/library`). The library never depends on a specific vendor SDK in app code — Bootstrap / `callController` / DB tracing talk to **`ApmFactory`** → **`ApmInterface`**.

Providers (TraceKit, Datadog, …) are **separate Composer packages** that implement the contracts. Switch providers with `APM_NAME` + install the matching package — no changes to `app/`.

- **Contracts-first**: `ApmInterface`, `AbstractApm`, `ApmFactory`, toolkit contracts — see `vendor/gemvc/apm-contracts/README.md`
- **Environment-controlled**: unified `APM_*` flags; providers may add their own env keys
- **Non-blocking**: traces are sent after the HTTP response where possible
- **Pluggable**: any provider that follows the naming/autoload convention works

### Key Features

- **Automatic Root Trace**: Captures full request lifecycle from Bootstrap  
- **Controller Tracing**: Optional spans for controller operations (`APM_TRACE_CONTROLLER`)  
- **Database Query Tracing**: Optional spans for SQL (`APM_TRACE_DB_QUERY`)  
- **Exception Tracking**: Automatic exception recording in traces  
- **Trace Context Propagation**: All spans share the same `traceId` via `$request->apm`  
- **Fire-and-Forget**: Non-blocking send where the provider supports it

## Architecture

### Request Lifecycle with APM

```
1. Request Created
   ↓
2. Bootstrap/SwooleBootstrap Created
   ↓
3. APM Initialized (root trace started) ← EARLY INITIALIZATION
   ↓
4. Routing & Service Resolution
   ↓
5. ApiService Created (uses existing APM)
   ↓
6. Controller Called (uses existing APM)
   ↓
7. Model/Table Operations (uses existing APM)
   ↓
8. Database Queries (traced if enabled)
   ↓
9. Response Sent
   ↓
10. Traces Sent (fire-and-forget, non-blocking)
```

### Trace Context Propagation

All layers share the **same traceId** through the Request object:

```
Bootstrap → $request->apm (root trace)
    ↓
ApiService → $request->apm (same traceId)
    ↓
Controller → $request->apm (same traceId)
    ↓
Table → $request->apm (via setRequest())
    ↓
UniversalQueryExecuter → $request->apm (same traceId)
```

### APM stack (contracts + providers)

```
app / library (Bootstrap, ApiService, Controller, UniversalQueryExecuter)
        │  uses ApmFactory::create() — does not import TraceKit types
        ▼
gemvc/apm-contracts   ← always required with library
  ApmInterface · AbstractApm · ApmFactory · toolkit contracts
        │  APM_NAME=YourName → Gemvc\Core\Apm\Providers\YourName\YourNameProvider
        ▼
gemvc/apm-tracekit (example) | Datadog | New Relic | … (your package)
```

Same pattern as DB connections: **contracts** + **swappable implementations**. New vendors implement contracts; they do **not** fork `library`.

### APM Provider Support

Any package that implements `ApmInterface` (typically extends `AbstractApm`) and is autoloadable as `Gemvc\Core\Apm\Providers\{Name}\{Name}Provider` works when `APM_NAME={Name}` (e.g. `APM_NAME=TraceKit` → `…\TraceKit\TraceKitProvider`):

- **TraceKit** — `gemvc/apm-tracekit` (ships with library today; one provider among many)
- Datadog / New Relic / Elastic / OpenTelemetry — custom packages following the same contracts

Deep provider authoring: `vendor/gemvc/apm-contracts/README.md`.

## Environment Configuration

Config is **contracts-first**. Unified `APM_*` variables are read by `AbstractApm` / `ApmFactory` in **`gemvc/apm-contracts`**. Provider packages may add their own keys (e.g. TraceKit’s `TRACEKIT_*`) and often also accept the unified names as fallbacks.

### Required (any provider)

```env
# Select provider class via ApmFactory (must match installed package)
APM_NAME=TraceKit
```

Without `APM_NAME`, APM stays off. Install the matching package (e.g. `gemvc/apm-tracekit` for TraceKit).

### Unified flags (`gemvc/apm-contracts` / `AbstractApm`)

```env
APM_ENABLED=true
APM_SAMPLE_RATE=1.0
APM_TRACE_RESPONSE=false
APM_TRACE_REQUEST_BODY=false

# Library feature flags (any provider)
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

| Variable | Values | Default | Description |
|----------|--------|---------|-------------|
| `APM_NAME` | Provider short name (`TraceKit`, `Datadog`, …) | unset (disabled) | Selects `Providers\{Name}\{Name}Provider` via `ApmFactory` |
| `APM_ENABLED` | `true`, `1`, `false`, `0` | `true` | Master enable when `APM_NAME` is set |
| `APM_SAMPLE_RATE` | `0.0`–`1.0` | `1.0` | Fraction of requests to sample (errors still recorded) |
| `APM_TRACE_RESPONSE` | `true`/`1`/`false`/`0` | `false` | Include response payload attrs when supported |
| `APM_TRACE_REQUEST_BODY` | `true`/`1`/`false`/`0` | `false` | Include request body attrs when supported |
| `APM_TRACE_CONTROLLER` | `1`, `true`, or unset | disabled | Controller spans via `callController` (both API bases) |
| `APM_TRACE_DB_QUERY` | `1`, `true`, or unset | disabled | SQL spans via Request/`createModel` wire |
| `APM_API_KEY` | string | — | Optional unified API key (providers may prefer their own key) |
| `APM_SEND_INTERVAL` | int | provider default | Batch send interval (contracts) |
| `APM_MAX_STRING_LENGTH` | int | `2000` | Truncate long string attrs |

**Performance note:** Prefer `1` over `"true"` for boolean-ish flags (faster string compare).

### TraceKit provider (example)

TraceKit is **one** implementation (`gemvc/apm-tracekit`). Prefer unified `APM_*` where possible; TraceKit also reads provider-specific env (and often falls back to `APM_*`):

```env
APM_NAME=TraceKit
TRACEKIT_API_KEY=your-api-key
TRACEKIT_ENDPOINT=https://app.tracekit.dev/v1/traces
# Optional TraceKit overrides (also accept APM_* equivalents in many cases):
# TRACEKIT_SAMPLE_RATE=1.0
# TRACEKIT_SERVICE_NAME=my-service
# TRACEKIT_ENABLED=true
# TRACEKIT_TRACE_RESPONSE=false
# TRACEKIT_TRACE_REQUEST_BODY=false
```

| Variable | Description |
|----------|-------------|
| `TRACEKIT_API_KEY` | TraceKit API key (also tries `APM_API_KEY`) |
| `TRACEKIT_ENDPOINT` | Ingest URL (default `https://app.tracekit.dev/v1/traces`) |
| `TRACEKIT_SERVICE_NAME` | Service name in TraceKit UI |
| `TRACEKIT_*` flags | Provider overrides for sample/enable/trace body — see `vendor/gemvc/apm-tracekit` |

Other providers use their own `YOURPROVIDER_*` keys the same way. Do **not** treat `TRACEKIT_*` as framework-wide env names.

## Automatic Tracing

### 1. Root Request Trace

**Automatic** - No configuration needed.

The root trace is automatically created in `Bootstrap` or `SwooleBootstrap` and captures:
- Full request lifecycle
- Routing operations
- Service resolution
- Total request duration

**No code changes required** - works automatically.

### 2. Controller Operation Tracing

**Optional** - Enable via `APM_TRACE_CONTROLLER=1`.

When enabled, automatic spans are created for controller method calls **when you use `callController()`** on `ApiService` (all servers; deprecated `SwooleApiService` inherits it).

```php
// Apache / Nginx / OpenSwoole
public function create(): JsonResponse
{
    return $this->callController(new UserController($this->request))->create();
    // ↑ Creates span "controller-operation" when APM_TRACE_CONTROLLER=1
}
```

Bare `new XController($this->request)` still works but skips the automatic controller span.

**Span Attributes** (when `callController` is used):
- `controller.name`: Controller class name
- `controller.method`: Method name (create, read, update, delete, etc.)
- `http.status_code`: HTTP response code
- Optional `response.*` fields when response tracing is enabled

Set `APM_TRACE_CONTROLLER=1` in `.env`, and use `callController` on both API bases.

### 3. Database Query Tracing

**Optional** - Enable via `APM_TRACE_DB_QUERY=1`.

When enabled, automatic spans are created for all SQL queries:

```php
// Model Layer
$user = new UserModel();
$user->setRequest($this->request);  // ← Important: Set Request for trace context
$user->select()->where('id', 1)->run();
// ↑ Automatically creates span: "database-query"
```

**Span Attributes:**
- `db.system`: Database system (mysql, postgresql, etc.)
- `db.operation`: Query type (SELECT, INSERT, UPDATE, DELETE, etc.)
- `db.statement`: Full SQL query
- `db.parameter_count`: Number of bound parameters
- `db.in_transaction`: Whether query is in a transaction
- `db.rows_affected`: Number of affected rows
- `db.execution_time_ms`: Query execution time
- `db.last_insert_id`: Last inserted ID (for INSERT queries)

**Important**: Models must have Request set for trace context propagation. Use `Controller::createModel()` helper:

```php
// In Controller
$model = $this->createModel(new UserModel());  // Request automatically set
```

### 4. Exception Tracking

**Automatic** - No configuration needed.

All exceptions are automatically recorded in APM traces:
- Exceptions in Bootstrap
- Exceptions in ApiService
- Exceptions in Controller
- Exceptions in Database queries
- PDO exceptions

**No code changes required** - works automatically.

## Manual Tracing

### Using ApmTracingTrait

For custom tracing in Models or other classes, use the `ApmTracingTrait`:

```php
use Gemvc\Core\Apm\ApmTracingTrait;

class UserModel extends UserTable
{
    use ApmTracingTrait;
    
    public function complexOperation(): JsonResponse
    {
        // Method 1: Using traceApm() helper (recommended)
        return $this->traceApm('complex-calculation', function() {
            return $this->doComplexWork();
        }, ['model' => 'UserModel']);
    }
    
    public function anotherOperation(): JsonResponse
    {
        // Method 2: Manual span management
        $span = $this->startApmSpan('custom-operation', [
            'operation_type' => 'data-processing',
            'record_count' => 100
        ]);
        
        try {
            $result = $this->processData();
            $this->endApmSpan($span, ['processed' => count($result)], 'OK');
            return Response::success($result);
        } catch (\Throwable $e) {
            $this->recordApmException($span, $e);
            $this->endApmSpan($span, [], 'ERROR');
            throw $e;
        }
    }
}
```

### ApmTracingTrait Methods

#### `getApm(): ?ApmInterface`

Gets the APM instance (Request APM if available, otherwise standalone).

```php
$apm = $this->getApm();
if ($apm !== null && $apm->isEnabled()) {
    // APM is available
}
```

#### `startApmSpan(string $operationName, array $attributes = [], int $kind = SPAN_KIND_INTERNAL): array`

Starts a new APM span.

```php
$span = $this->startApmSpan('my-operation', [
    'custom.attribute' => 'value',
    'operation.id' => 123
], ApmInterface::SPAN_KIND_INTERNAL);
```

**Span Kinds:**
- `ApmInterface::SPAN_KIND_INTERNAL` (default) - Internal operations
- `ApmInterface::SPAN_KIND_SERVER` - Server operations
- `ApmInterface::SPAN_KIND_CLIENT` - Client operations (e.g., database queries)
- `ApmInterface::SPAN_KIND_PRODUCER` - Message producer
- `ApmInterface::SPAN_KIND_CONSUMER` - Message consumer

#### `endApmSpan(array $spanData, array $attributes = [], string $status = 'OK'): void`

Ends an APM span.

```php
$this->endApmSpan($span, [
    'result.count' => 10,
    'execution_time_ms' => 150
], 'OK');
```

**Status Values:**
- `'OK'` - Operation succeeded
- `'ERROR'` - Operation failed

#### `recordApmException(array $spanData, \Throwable $exception): void`

Records an exception in a span.

```php
try {
    $result = $this->riskyOperation();
} catch (\Throwable $e) {
    $this->recordApmException($span, $e);
    throw $e;
}
```

#### `traceApm(string $operationName, callable $callback, array $attributes = []): mixed`

Convenience method that automatically handles span lifecycle.

```php
$result = $this->traceApm('operation-name', function() {
    return $this->doWork();
}, ['attribute' => 'value']);
// Automatically handles: start span → execute → end span → exception handling
```

## Usage Examples

### Example 1: Basic CRUD with Automatic Tracing

```php
// app/api/User.php
class User extends ApiService
{
    public function create(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        // callController → controller span if APM_TRACE_CONTROLLER=1 (both bases)
        return $this->callController(new UserController($this->request))->create();
    }
}

// app/controller/UserController.php
class UserController extends Controller
{
    public function create(): JsonResponse
    {
        // Automatic Request propagation (via createModel helper)
        $model = $this->createModel(new UserModel());
        
        // Map POST data to model
        $model = $this->request->mapPostToObject($model, [
            'email' => 'email',
            'name' => 'name',
            'password' => 'setPassword()'
        ]);
        
        if (!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }
        
        // Automatic database tracing (if APM_TRACE_DB_QUERY=1)
        return $model->createModel();
    }
}

// app/model/UserModel.php
class UserModel extends UserTable
{
    public function createModel(): JsonResponse
    {
        // Database query automatically traced
        $this->insertSingleQuery();
        
        if ($this->getError()) {
            return Response::internalError($this->getError());
        }
        
        return Response::created($this, 1, "User created successfully");
    }
}
```

**Trace Structure:**
```
Root Trace (Bootstrap)
  └─ controller-operation (UserController::create)
      └─ database-query (INSERT INTO users ...)
```

### Example 2: Custom Model Tracing

```php
// app/model/OrderModel.php
class OrderModel extends OrderTable
{
    use ApmTracingTrait;
    
    public function processOrder(int $orderId): JsonResponse
    {
        // Custom tracing for complex operation
        return $this->traceApm('order-processing', function() use ($orderId) {
            // Step 1: Load order
            $order = $this->selectById($orderId);
            if (!$order) {
                return Response::notFound("Order not found");
            }
            
            // Step 2: Validate inventory
            $inventory = $this->checkInventory($order);
            if (!$inventory) {
                return Response::unprocessableEntity("Insufficient inventory");
            }
            
            // Step 3: Process payment
            $payment = $this->processPayment($order);
            if (!$payment) {
                return Response::unprocessableEntity("Payment failed");
            }
            
            // Step 4: Update order status
            $this->id = $orderId;
            $this->status = 'processed';
            $this->updateSingleQuery();
            
            return Response::success($this, 1, "Order processed successfully");
        }, [
            'order.id' => $orderId,
            'operation.type' => 'order-processing'
        ]);
    }
}
```

**Trace Structure:**
```
Root Trace (Bootstrap)
  └─ controller-operation (OrderController::process)
      └─ order-processing (OrderModel::processOrder)
          ├─ database-query (SELECT order ...)
          ├─ database-query (SELECT inventory ...)
          ├─ database-query (UPDATE payment ...)
          └─ database-query (UPDATE orders ...)
```

### Example 3: Manual Span Management

```php
// app/model/ReportModel.php
class ReportModel extends ReportTable
{
    use ApmTracingTrait;
    
    public function generateReport(array $filters): JsonResponse
    {
        $span = $this->startApmSpan('report-generation', [
            'report.type' => 'monthly',
            'filters.count' => count($filters)
        ]);
        
        try {
            // Step 1: Collect data
            $data = $this->collectData($filters);
            $this->endApmSpan($span, ['data.records' => count($data)], 'OK');
            
            // Step 2: Generate report
            $reportSpan = $this->startApmSpan('report-formatting', [
                'format' => 'pdf'
            ]);
            $report = $this->formatReport($data);
            $this->endApmSpan($reportSpan, ['report.size_kb' => strlen($report) / 1024], 'OK');
            
            return Response::success(['report' => $report], 1, "Report generated");
            
        } catch (\Throwable $e) {
            $this->recordApmException($span, $e);
            $this->endApmSpan($span, [], 'ERROR');
            return Response::internalError($e->getMessage());
        }
    }
}
```

### Example 4: Controller with Custom Tracing

```php
// app/controller/ProductController.php
class ProductController extends Controller
{
    public function complexSearch(): JsonResponse
    {
        // Use Controller's built-in tracing methods
        $span = $this->startTraceSpan('product-search', [
            'search.query' => $this->request->stringValueGet('q'),
            'filters.count' => count($this->request->getFilterable())
        ]);
        
        try {
            $model = $this->createModel(new ProductModel());
            $results = $model->complexSearch($this->request->getFilterable());
            
            $this->endTraceSpan($span, [
                'results.count' => count($results)
            ], 'OK');
            
            return Response::success($results, count($results), "Search completed");
            
        } catch (\Throwable $e) {
            $this->recordApmException($e);
            $this->endTraceSpan($span, [], 'ERROR');
            return Response::internalError($e->getMessage());
        }
    }
}
```

## Best Practices

### 0. Prefer `callController()` on every server

Use `callController()` on `ApiService` (all servers) so `APM_TRACE_CONTROLLER=1` creates controller spans. Bare `new XController(...)` still works (no automatic controller span). Deprecated `SwooleApiService` inherits `callController`.

### 1. Always Set Request on Models

** Good:**
```php
// In Controller
$model = $this->createModel(new UserModel());  // Request automatically set
```

** Bad:**
```php
// Missing Request - database queries won't share traceId
$model = new UserModel();
$model->select()->run();
```

### 2. Use createModel() Helper

The `Controller::createModel()` helper automatically sets Request:

```php
//  Recommended
$model = $this->createModel(new UserModel());

//  Manual (works but verbose)
$model = new UserModel();
$model->setRequest($this->request);
```

### 3. Use traceApm() for Simple Operations

For operations that fit in a single method, use `traceApm()`:

```php
//  Simple and clean
return $this->traceApm('operation-name', function() {
    return $this->doWork();
}, ['attribute' => 'value']);
```

### 4. Use Manual Spans for Complex Operations

For multi-step operations, use manual span management:

```php
//  Better for complex flows
$span1 = $this->startApmSpan('step-1');
// ... do step 1 ...
$this->endApmSpan($span1, [], 'OK');

$span2 = $this->startApmSpan('step-2');
// ... do step 2 ...
$this->endApmSpan($span2, [], 'OK');
```

### 5. Add Meaningful Attributes

Always add relevant attributes to spans:

```php
//  Good - meaningful attributes
$span = $this->startApmSpan('user-registration', [
    'user.email' => $email,
    'registration.source' => 'web',
    'campaign.id' => $campaignId
]);

//  Bad - no context
$span = $this->startApmSpan('operation');
```

### 6. Handle Exceptions Properly

Always record exceptions in spans:

```php
//  Good - exception recorded
try {
    $result = $this->riskyOperation();
    $this->endApmSpan($span, [], 'OK');
} catch (\Throwable $e) {
    $this->recordApmException($span, $e);
    $this->endApmSpan($span, [], 'ERROR');
    throw $e;
}

//  Bad - exception not recorded
try {
    $result = $this->riskyOperation();
} catch (\Throwable $e) {
    // Exception not in trace!
    throw $e;
}
```

### 7. Use Appropriate Span Kinds

Choose the correct span kind:

```php
// Internal operation (default)
$span = $this->startApmSpan('data-processing');  // SPAN_KIND_INTERNAL

// Database query
$span = $this->startApmSpan('database-query', [], ApmInterface::SPAN_KIND_CLIENT);

// Server operation
$span = $this->startApmSpan('api-call', [], ApmInterface::SPAN_KIND_SERVER);
```

## Performance Considerations

### Sample Rate

**Sample rate** controls what percentage of requests are traced. This is useful for high-traffic applications to reduce APM costs and overhead.

```env
# Trace 100% of requests (default) — unified contracts flag
APM_SAMPLE_RATE=1.0

# Trace 10% of requests (recommended for high traffic)
APM_SAMPLE_RATE=0.1

# Trace 5% of requests (for very high traffic)
APM_SAMPLE_RATE=0.05

# Trace 0% of requests (disabled sampling; errors still logged)
APM_SAMPLE_RATE=0.0
```

**Important Notes:**
- **Errors are ALWAYS logged** regardless of sample rate
- Sample rate range: `0.0` (0%) to `1.0` (100%)
- Default: `1.0` (100% - all requests traced)
- Sampling is random per request (not deterministic)
- Providers may also expose `YOURPROVIDER_SAMPLE_RATE` (e.g. TraceKit `TRACEKIT_SAMPLE_RATE`) that overrides when set

**When to Use Sample Rate:**
- **High Traffic** (>1000 req/sec): Use `0.1` (10%) or `0.05` (5%)
- **Medium Traffic** (100-1000 req/sec): Use `0.5` (50%) or `1.0` (100%)
- **Low Traffic** (<100 req/sec): Use `1.0` (100%)

**Example:**
```env
# Production: High traffic, sample 10% of requests
APM_SAMPLE_RATE=0.1

# Staging: Medium traffic, sample 50% of requests
APM_SAMPLE_RATE=0.5

# Development: Low traffic, trace everything
APM_SAMPLE_RATE=1.0
```

### Environment Variable Checks

**Fastest**: Use `1` (no quotes) in `.env` files:

```env
# Fastest (recommended)
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1

# Also works (slower)
APM_TRACE_CONTROLLER=true
```

**Why**: Single character comparison (`'1'`) is faster than multi-character comparison (`'true'`).

### Tracing Overhead

- **Root Trace**: ~0.1ms overhead (one-time per request)
- **Controller Span**: ~0.05ms per span (if enabled)
- **Database Span**: ~0.1ms per query (if enabled)
- **Trace Sending**: **Zero overhead** (fire-and-forget, after response)

**At 1000 requests/second:**
- With tracing disabled: **0ms overhead**
- With all tracing enabled: **~0.25ms overhead per request**

### When to Enable Tracing

**Development/Staging:**
```env
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

**Production (High Traffic):**
```env
# Enable only what you need
APM_TRACE_CONTROLLER=1      # Usually enabled
APM_TRACE_DB_QUERY=0        # Disable if too many queries
```

**Production (Low Traffic):**
```env
# Enable everything for full visibility
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

## Troubleshooting

### Issue: No Traces Appearing

**Checklist:**
1. APM provider installed? (`composer show gemvc/apm-tracekit`)
2. `APM_NAME` set in `.env`?
3. APM provider configured correctly?
4. APM provider API key valid?
5. Check APM provider logs/console

### Issue: Traces Missing Database Queries

**Solution:** Ensure Request is set on models:

```php
//  Correct
$model = $this->createModel(new UserModel());

//  Missing Request
$model = new UserModel();
```

### Issue: Multiple TraceIds (Not Sharing Context)

**Cause:** Request not propagated through layers.

**Solution:** 
- Use `Controller::createModel()` helper
- Ensure `setRequest()` is called on models
- Check that Request flows: Controller → Model → Table → ConnectionManager → PdoQuery → UniversalQueryExecuter

### Issue: Traces Not Sent

**Check:**
1. `register_shutdown_function` working? (check APM provider logs)
2. `fastcgi_finish_request()` available? (Apache/Nginx)
3. Background tasks working? (OpenSwoole)
4. Network connectivity to APM provider?

### Issue: Performance Degradation

**Solutions:**
1. Disable unnecessary tracing:
   ```env
   APM_TRACE_CONTROLLER=0
   APM_TRACE_DB_QUERY=0
   ```
2. Use `1` instead of `true` in `.env` files
3. Check APM provider configuration (batch size, flush interval)

### Issue: Exceptions Not Recorded

**Check:**
1. Exception occurs after APM initialization?
2. APM provider supports exception recording?
3. Check APM provider logs for errors

## Advanced Usage

### Custom APM Provider

`gemvc/apm-contracts` is **already required** by `gemvc/library` — do not reinvent factory logic in the app.

1. Create a Composer package that **requires** `gemvc/apm-contracts` and autoloads:
   `Gemvc\Core\Apm\Providers\YourName\YourNameProvider`
2. Extend `AbstractApm` / implement `ApmInterface` (and toolkit contracts if needed) — follow `vendor/gemvc/apm-contracts/README.md`
3. Install the package in the app; set `APM_NAME=YourName`
4. **No** changes to `library` or `ApmFactory` — factory discovers by name (`{Name}Provider`)

App code stays on `callController` / `createModel` / `$request->apm` regardless of provider.

### CLI/Background Jobs

For CLI scripts or background jobs (no Request object):

```php
use Gemvc\Core\Apm\ApmTracingTrait;

class BackgroundJob
{
    use ApmTracingTrait;
    
    public function run(): void
    {
        // Trait automatically creates standalone APM (no Request needed)
        $this->traceApm('background-job', function() {
            $this->processData();
        });
    }
}
```

The trait automatically falls back to standalone APM when Request is not available.

### Conditional Tracing

Enable/disable tracing based on conditions:

```php
class UserController extends Controller
{
    public function expensiveOperation(): JsonResponse
    {
        // Only trace if in production
        if (($_ENV['APP_ENV'] ?? '') === 'production') {
            $span = $this->startTraceSpan('expensive-operation');
            try {
                $result = $this->doExpensiveWork();
                $this->endTraceSpan($span, [], 'OK');
                return Response::success($result);
            } catch (\Throwable $e) {
                $this->recordApmException($e);
                $this->endTraceSpan($span, [], 'ERROR');
                throw $e;
            }
        }
        
        // No tracing in dev
        return Response::success($this->doExpensiveWork());
    }
}
```

## Summary

### Architecture reminder

Library → **`gemvc/apm-contracts`** (`ApmFactory` / `ApmInterface`) → provider package (`APM_NAME=…`). TraceKit is an example provider, not the abstraction.

### What's Automatic

- Root request trace  
- Exception tracking  
- Controller spans (if `APM_TRACE_CONTROLLER=1` + `callController` on either API base)  
- Database query spans (if `APM_TRACE_DB_QUERY=1` + Request wired)

### What Requires Code

- Custom spans in Models (use `ApmTracingTrait`)  
- Setting Request on models (use `Controller::createModel()`)

### Key Takeaways

1. **Contracts-first** — app/library use `ApmFactory` / `ApmInterface`, not TraceKit types
2. **`APM_NAME` + provider package** selects the backend; TraceKit is one option
3. **Unified `APM_*` flags** in contracts; providers may add their own env keys
4. **Non-blocking** send where supported; sample with `APM_SAMPLE_RATE`
5. **Automatic trace context** — all spans share `traceId` via `$request->apm`

---

**For more information:**
- APM Contracts: `vendor/gemvc/apm-contracts/README.md`
- Provider example: `vendor/gemvc/apm-tracekit/README.md`
- [cli.md](cli.md) · [ecosystem.md](ecosystem.md)


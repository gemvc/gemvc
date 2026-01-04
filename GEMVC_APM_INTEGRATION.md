# GEMVC APM Integration Guide

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

GEMVC framework provides **automatic Application Performance Monitoring (APM)** integration that captures the full request lifecycle without requiring code changes. The APM system is designed to be:

- **Zero-Configuration**: Works automatically once APM provider is installed
- **Environment-Controlled**: Enable/disable tracing via environment variables
- **Non-Blocking**: Traces are sent asynchronously after HTTP response
- **Framework-Agnostic**: Works with any APM provider (TraceKit, Datadog, New Relic, etc.)

### Key Features

✅ **Automatic Root Trace**: Captures full request lifecycle from Bootstrap  
✅ **Controller Tracing**: Automatic spans for controller operations (optional)  
✅ **Database Query Tracing**: Automatic spans for all SQL queries (optional)  
✅ **Exception Tracking**: Automatic exception recording in traces  
✅ **Trace Context Propagation**: All spans share the same traceId  
✅ **Fire-and-Forget**: Non-blocking trace sending (no performance impact)

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

### APM Provider Support

GEMVC uses the `gemvc/apm-contracts` package which provides a universal abstraction layer. Any APM provider that implements `ApmInterface` will work:

- **TraceKit** (`gemvc/apm-tracekit`)
- **Datadog** (custom provider)
- **New Relic** (custom provider)
- **Elastic APM** (custom provider)
- **OpenTelemetry** (custom provider)

## Environment Configuration

### Required Configuration

```env
# Enable APM provider (required)
APM_NAME=TraceKit

# APM provider-specific configuration
TRACEKIT_API_KEY=your-api-key
TRACEKIT_API_URL=https://api.tracekit.io
```

### Optional Tracing Flags

```env
# Enable controller operation tracing (default: disabled)
APM_TRACE_CONTROLLER=1

# Enable database query tracing (default: disabled)
APM_TRACE_DB_QUERY=1
```

**Performance Note**: Use `1` (no quotes) instead of `"true"` for faster string comparison. Both formats are supported.

### Environment Variable Values

| Variable | Values | Default | Description |
|----------|--------|---------|-------------|
| `APM_NAME` | `TraceKit`, `Datadog`, etc. | `null` (disabled) | APM provider name |
| `APM_TRACE_CONTROLLER` | `1`, `true`, or not set | `disabled` | Enable controller tracing |
| `APM_TRACE_DB_QUERY` | `1`, `true`, or not set | `disabled` | Enable database query tracing |

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

When enabled, automatic spans are created for all controller method calls:

```php
// API Layer
public function create(): JsonResponse
{
    return $this->callController(new UserController($this->request))->create();
    // ↑ Automatically creates span: "controller-operation"
}
```

**Span Attributes:**
- `controller.name`: Controller class name
- `controller.method`: Method name (create, read, update, delete, etc.)
- `http.status_code`: HTTP response code
- `http.response_size`: Response data size (if enabled)

**No code changes required** - just set `APM_TRACE_CONTROLLER=1` in `.env`.

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
        
        // Automatic controller tracing (if APM_TRACE_CONTROLLER=1)
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

### 1. Always Set Request on Models

**✅ Good:**
```php
// In Controller
$model = $this->createModel(new UserModel());  // Request automatically set
```

**❌ Bad:**
```php
// Missing Request - database queries won't share traceId
$model = new UserModel();
$model->select()->run();
```

### 2. Use createModel() Helper

The `Controller::createModel()` helper automatically sets Request:

```php
// ✅ Recommended
$model = $this->createModel(new UserModel());

// ❌ Manual (works but verbose)
$model = new UserModel();
$model->setRequest($this->request);
```

### 3. Use traceApm() for Simple Operations

For operations that fit in a single method, use `traceApm()`:

```php
// ✅ Simple and clean
return $this->traceApm('operation-name', function() {
    return $this->doWork();
}, ['attribute' => 'value']);
```

### 4. Use Manual Spans for Complex Operations

For multi-step operations, use manual span management:

```php
// ✅ Better for complex flows
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
// ✅ Good - meaningful attributes
$span = $this->startApmSpan('user-registration', [
    'user.email' => $email,
    'registration.source' => 'web',
    'campaign.id' => $campaignId
]);

// ❌ Bad - no context
$span = $this->startApmSpan('operation');
```

### 6. Handle Exceptions Properly

Always record exceptions in spans:

```php
// ✅ Good - exception recorded
try {
    $result = $this->riskyOperation();
    $this->endApmSpan($span, [], 'OK');
} catch (\Throwable $e) {
    $this->recordApmException($span, $e);
    $this->endApmSpan($span, [], 'ERROR');
    throw $e;
}

// ❌ Bad - exception not recorded
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

### Environment Variable Checks

**Fastest**: Use `1` (no quotes) in `.env` files:

```env
# ✅ Fastest (recommended)
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1

# ✅ Also works (slower)
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
1. ✅ APM provider installed? (`composer show gemvc/apm-tracekit`)
2. ✅ `APM_NAME` set in `.env`?
3. ✅ APM provider configured correctly?
4. ✅ APM provider API key valid?
5. ✅ Check APM provider logs/console

### Issue: Traces Missing Database Queries

**Solution:** Ensure Request is set on models:

```php
// ✅ Correct
$model = $this->createModel(new UserModel());

// ❌ Missing Request
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
1. ✅ `register_shutdown_function` working? (check APM provider logs)
2. ✅ `fastcgi_finish_request()` available? (Apache/Nginx)
3. ✅ Background tasks working? (OpenSwoole)
4. ✅ Network connectivity to APM provider?

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
1. ✅ Exception occurs after APM initialization?
2. ✅ APM provider supports exception recording?
3. ✅ Check APM provider logs for errors

## Advanced Usage

### Custom APM Provider

To create a custom APM provider:

1. Install `gemvc/apm-contracts`:
   ```bash
   composer require gemvc/apm-contracts
   ```

2. Implement `ApmInterface`:
   ```php
   class MyApmProvider extends AbstractApm implements ApmInterface
   {
       // Implement required methods
   }
   ```

3. Register in `ApmFactory` (or use auto-discovery)

4. Set `APM_NAME=MyApmProvider` in `.env`

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

### What's Automatic

✅ Root request trace  
✅ Exception tracking  
✅ Controller spans (if `APM_TRACE_CONTROLLER=1`)  
✅ Database query spans (if `APM_TRACE_DB_QUERY=1`)

### What Requires Code

🔧 Custom spans in Models (use `ApmTracingTrait`)  
🔧 Setting Request on models (use `Controller::createModel()`)

### Key Takeaways

1. **Zero configuration** for basic tracing
2. **Environment-controlled** - enable/disable via `.env`
3. **Non-blocking** - no performance impact
4. **Automatic trace context** - all spans share traceId
5. **Fire-and-forget** - traces sent after HTTP response

---

**For more information:**
- APM Contracts: `vendor/gemvc/apm-contracts/README.md`
- Framework Documentation: `README.md`
- CLI Commands: `CLI.md`

**Author:** Ali Khorsandfard  
**Framework:** [GEMVC PHP Framework](https://gemvc.de)  
**Version:** 5.3.0+


# APM Tracing for Database Queries

## Overview

This document outlines the implementation of APM tracing for database query execution. Based on review findings, tracing will be implemented at a **single point** (`UniversalQueryExecuter::execute()`) to avoid duplication and ensure all execution data is available.

**⚠️ IMPORTANT:** This plan has been updated based on implementation review findings. See `APM_IMPLEMENTATION_REVIEW.md` for details.

## Environment Flags

The following environment flags control APM tracing:

```env
APM_TRACE_CONTROLLER=1    # Trace Controller operations (already implemented)
APM_TRACE_DB_QUERY=1      # Trace database queries (NEW - maps to TRACEKIT_TRACE_DB_QUERY)
```

**Note:** Table-level tracing (`APM_TRACE_TABLE`) has been **removed** to avoid duplication. All database operations are traced at the query execution level in `UniversalQueryExecuter::execute()`.

**Performance Note:** Using `1` (no quotes) in `.env` is recommended. `$_ENV` always returns strings, so `1` becomes `"1"` in PHP. The implementation checks for `"1"` and `"true"` for compatibility.

## Architecture

### Request Flow

```
Table (business logic) 
  → ConnectionManager 
    → PdoQuery (query execution)
      → UniversalQueryExecuter (actual SQL execution)
```

### Tracing Point

**Single Point Tracing** (`UniversalQueryExecuter::execute()`):
- **Single point** where ALL queries execute (INSERT, UPDATE, DELETE, SELECT)
- Has direct access to `affectedRows`, `lastInsertedId`, `executionTime`
- Uses `$request->apm` if available (shares traceId), falls back to standalone APM
- Test file already shows the pattern (`tests/Unit/Database/UniversalQueryExecuter.php`)

**Why Not Table Level?**
- Avoids duplicate spans (Table + Query would create 2 spans per operation)
- Query level has all execution data (affectedRows, lastInsertedId, executionTime)
- Simpler to maintain (single point vs multiple points)

## Implementation Plan

### 1. Add Request Support to UniversalQueryExecuter

**File:** `src/database/UniversalQueryExecuter.php`

- Add optional `?Request $request = null` parameter to constructor
- Store Request as private property
- Use `$this->request->apm` in `execute()` method if Request is available
- Fall back to `ApmFactory::create(null)` for CLI/background jobs

### 2. Add APM Tracing to UniversalQueryExecuter::execute()

**File:** `src/database/UniversalQueryExecuter.php`

- Add APM tracing in `execute()` method (around line 274-325)
- Check `APM_TRACE_DB_QUERY` environment flag (no static cache - see Fix 1)
- Use `$this->request->apm` if Request is available, otherwise standalone APM
- Trace query type, SQL statement, execution time, affectedRows, lastInsertedId, errors
- **Reference:** Test file shows the pattern (`tests/Unit/Database/UniversalQueryExecuter.php` lines 280-360)

### 3. Add Request Support to PdoQuery

**File:** `src/database/PdoQuery.php`

- Add optional `?Request $request = null` property (setter method)
- Pass Request to UniversalQueryExecuter when creating it (lazy initialization)

### 4. Add Request Support to ConnectionManager

**File:** `src/database/TableComponents/ConnectionManager.php`

- Add optional `?Request $request = null` property (setter method)
- Pass Request to PdoQuery when creating it

### 5. Add Request Support to Table

**File:** `src/database/Table.php`

- Add optional `?Request $request = null` property (setter method)
- Pass Request to ConnectionManager when setting it

## Code Examples

### UniversalQueryExecuter::execute() with APM Tracing

```php
// NO static cache - env vars are fast enough, and static cache causes issues in OpenSwoole
private static function shouldTraceDbQuery(): bool
{
    $value = $_ENV['APM_TRACE_DB_QUERY'] ?? null;
    // $_ENV always returns strings, so only check strings
    return ($value === '1' || $value === 'true');
}

public function execute(): bool
{
    // ... existing code before execution ...
    
    // APM tracing for database queries
    $dbSpan = [];
    $shouldTrace = false;
    $apm = null;
    
    // Try to use Request APM first (shares traceId)
    if ($this->request !== null && $this->request->apm !== null) {
        $apm = $this->request->apm;
    } elseif (ProjectHelper::isApmEnabled() !== null) {
        // Fallback: standalone APM for CLI/background jobs
        $apm = ApmFactory::create(null);
    }
    
    // Check if tracing is enabled (no static cache - see Fix 1 in review)
    if ($apm !== null && $apm->isEnabled() && self::shouldTraceDbQuery() && $apm->shouldTraceDbQuery()) {
        $shouldTrace = true;
        
        // Optimize query type detection (only check first 10 chars)
        $queryStart = substr($this->query, 0, 10);
        $queryUpper = strtoupper(ltrim($queryStart));
        $queryType = match(true) {
            str_starts_with($queryUpper, 'SELECT') => 'SELECT',
            str_starts_with($queryUpper, 'INSERT') => 'INSERT',
            str_starts_with($queryUpper, 'UPDATE') => 'UPDATE',
            str_starts_with($queryUpper, 'DELETE') => 'DELETE',
            str_starts_with($queryUpper, 'CREATE') => 'CREATE',
            str_starts_with($queryUpper, 'ALTER') => 'ALTER',
            str_starts_with($queryUpper, 'DROP') => 'DROP',
            default => 'UNKNOWN'
        };
        
        // Start database query span with error handling
        try {
            $dbSpan = $apm->startSpan('database-query', [
                'db.system' => 'mysql',  // TODO: Detect from connection
                'db.operation' => $queryType,
                'db.statement' => $this->query,
                'db.parameter_count' => count($this->bindings),  // Performance: count only, not json_encode
                'db.in_transaction' => $this->inTransaction ? 'true' : 'false',
            ], ApmInterface::SPAN_KIND_CLIENT);
        } catch (\Throwable $e) {
            // Graceful degradation - don't break queries if APM fails
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
        
        // ... existing code for INSERT detection and lastInsertedId ...
        
        $this->endExecutionTime = microtime(true);
        
        // APM: End span with success details (BEFORE connection release)
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
        // APM: Record exception and end span with error
        if ($shouldTrace && !empty($dbSpan) && $apm !== null) {
            try {
                $apm->recordException($dbSpan, $e);
                $executionTime = $this->getExecutionTime();
                $apm->endSpan($dbSpan, [
                    'db.execution_time_ms' => (string)$executionTime,
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
```

### Request Propagation Through Chain

```php
// Table
class Table
{
    private ?Request $request = null;
    
    public function setRequest(?Request $request): void
    {
        $this->request = $request;
        // Propagate to ConnectionManager
        if ($this->_connectionManager !== null) {
            $this->_connectionManager->setRequest($request);
        }
    }
}

// ConnectionManager
class ConnectionManager
{
    private ?Request $request = null;
    
    public function setRequest(?Request $request): void
    {
        $this->request = $request;
        // Propagate to existing PdoQuery if already created
        if ($this->pdoQuery !== null) {
            $this->pdoQuery->setRequest($request);
        }
    }
    
    public function getPdoQuery(): PdoQuery
    {
        if ($this->pdoQuery === null) {
            $this->pdoQuery = new PdoQuery();
            // Pass Request to new PdoQuery
            if ($this->request !== null) {
                $this->pdoQuery->setRequest($this->request);
            }
        }
        return $this->pdoQuery;
    }
}

// PdoQuery
class PdoQuery
{
    private ?Request $request = null;
    
    public function setRequest(?Request $request): void
    {
        $this->request = $request;
        // Propagate to existing UniversalQueryExecuter if already created
        if ($this->executer !== null) {
            $this->executer->setRequest($request);
        }
    }
    
    private function getExecuter(): UniversalQueryExecuter
    {
        if ($this->executer === null) {
            $this->executer = new UniversalQueryExecuter($this->request);
        }
        return $this->executer;
    }
}

// Controller Usage (Optional)
$model = new UserModel();
$model->setRequest($this->request);  // Optional, but recommended for traceId sharing
```

## Benefits

1. **Single Point Tracing**: All SQL queries traced at `UniversalQueryExecuter::execute()` - single point, all data available
2. **No Duplication**: Avoids duplicate spans (Table + Query would create 2 spans)
3. **Complete Data**: Direct access to `affectedRows`, `lastInsertedId`, `executionTime`
4. **Request Context**: Uses `$request->apm` to share traceId across all operations
5. **Backward Compatible**: Optional Request parameter, works without it (falls back to standalone APM)
6. **Error Resilient**: Graceful degradation if APM fails (doesn't break queries)
7. **Performance Optimized**: No static cache (OpenSwoole safe), optimized query type detection, parameter count only

## Migration Path

1. Add Request support to UniversalQueryExecuter constructor (non-breaking)
2. Add APM tracing to `UniversalQueryExecuter::execute()` (where test code shows it)
3. Add Request setter to PdoQuery and pass to UniversalQueryExecuter (optional, non-breaking)
4. Add Request setter to ConnectionManager and pass to PdoQuery (optional, non-breaking)
5. Add Request setter to Table and pass to ConnectionManager (optional, non-breaking)
6. Update Controller/Model to pass Request to Table instances (optional, but recommended)
7. Update .env files to use `1` (no quotes) instead of `"true"` for better performance

## Performance Optimization

### No Static Caching (OpenSwoole Safe)

**Why No Static Cache?**
- Static properties persist across requests in OpenSwoole (long-running processes)
- First request sets cache, subsequent requests use stale cache
- Environment variables are already fast to read (~0.1μs)
- Not worth the complexity and OpenSwoole bugs

```php
// Simple, fast, OpenSwoole-safe
private static function shouldTraceDbQuery(): bool
{
    $value = $_ENV['APM_TRACE_DB_QUERY'] ?? null;
    // $_ENV always returns strings, so only check strings
    return ($value === '1' || $value === 'true');
}
```

**Performance:**
- Direct `$_ENV` check: ~0.1μs (fast enough)
- No cache complexity
- No OpenSwoole bugs
- Environment variables don't change per request anyway

### Query Type Detection Optimization

```php
// Optimize: Only check first 10 chars (query type is always at start)
$queryStart = substr($this->query, 0, 10);
$queryUpper = strtoupper(ltrim($queryStart));  // Much faster than full query!
```

### Parameter Logging Optimization

```php
// Performance: Only count, don't json_encode (can be 1ms+ for large arrays)
'db.parameter_count' => count($this->bindings),  // Instead of json_encode($this->bindings)
```

### Recommended .env Format

```env
# Use 1 (no quotes) - recommended for production
APM_TRACE_CONTROLLER=1
APM_TRACE_DB_QUERY=1
```

**Note:** 
- `$_ENV` always returns strings, so `1` in .env becomes `"1"` string in PHP
- The implementation checks for `"1"` and `"true"` for compatibility
- No quotes needed (simpler, cleaner)
- Still supports `"true"` for backward compatibility

## Critical Fixes Applied

Based on `APM_IMPLEMENTATION_REVIEW.md`, the following fixes have been incorporated:

1. ✅ **No Static Cache** - Removed to avoid OpenSwoole bugs
2. ✅ **Tracing in UniversalQueryExecuter** - Not PdoQuery (has all execution data)
3. ✅ **Request Propagation** - Setter pattern (non-breaking, optional)
4. ✅ **No Table-Level Tracing** - Avoids duplication
5. ✅ **Error Handling** - Graceful degradation if APM fails
6. ✅ **Performance Optimizations** - Query type detection, parameter count only
7. ✅ **Environment Variable Checks** - Only check strings (no wasted integer checks)


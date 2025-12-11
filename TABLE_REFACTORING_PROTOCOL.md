# Table Class Refactoring Protocol

## Executive Summary

This protocol outlines the strategic refactoring of the `Table` class (1,447 lines, 57 methods) into smaller, focused classes following SOLID principles. The goal is to improve maintainability, testability, and prepare for future enhancements (primary key configuration system).

---

## Current State Analysis

### Statistics
- **File**: `src/database/Table.php`
- **Lines**: 1,447
- **Methods**: 57
- **Properties**: 20+
- **Responsibilities**: 10+ mixed concerns

### Identified Responsibilities

1. **Connection Management** - PdoQuery lifecycle, lazy loading, error storage
2. **Error Handling** - Error storage and retrieval
3. **CRUD Operations** - Insert, Update, Delete operations
4. **Query Building** - SELECT, WHERE, JOIN, ORDER BY, LIMIT (internal SQL string building)
5. **Pagination** - Page calculation, offset/limit management
6. **Property Validation** - validateProperties(), validateId()
7. **Type Casting** - castValue(), fetchRow(), hydrateResults()
8. **Soft Delete** - safeDeleteQuery(), restoreQuery(), activateQuery(), deactivateQuery()
9. **Transaction Management** - beginTransaction(), commit(), rollback() (delegates to PdoQuery)
10. **Result Hydration** - Converting database rows to objects

### Existing Classes (DO NOT DUPLICATE)

- ✅ `QueryBuilder` - Creates Select/Insert/Update/Delete query objects
- ✅ `PdoQuery` - Handles database operations (insertQuery, updateQuery, etc.)

**Note**: `Table` has its own internal SQL string building logic (different from `QueryBuilder`). This will remain for now to maintain backward compatibility.

---

## Refactoring Strategy

### Phase 0: Make Table Abstract (BEFORE Refactoring) ⭐ DO FIRST

**Decision**: Make `Table` class abstract before starting refactoring.

**Rationale**:
- All child classes already implement `getTable()` (verified in codebase)
- No direct instantiation of `Table` (verified earlier)
- Simplifies `_internalTable()` method immediately
- Makes contract explicit (PHP enforces it)
- Cleaner refactoring (no special handling needed)

**Changes Required**:
1. Change `class Table` → `abstract class Table`
2. Add `abstract public function getTable(): string;`
3. Simplify `_internalTable()` to: `return $this->getTable();`
4. Remove `@method` annotation (no longer needed)
5. Remove `method_exists()` and `is_string()` checks

**Risk**: ⭐ Lowest - Simple change, all child classes already implement it

**Time Estimate**: 15-30 minutes

**Verification**:
- Run full test suite
- Verify PHPStan passes
- Check all child classes compile

---

### Folder Structure

**Decision**: Use `src/database/TableComponents/` instead of `Table/` to avoid naming conflicts.

```
src/database/
├── Table.php (Facade/Coordinator - ~300 lines after refactoring)
├── TableComponents/
│   ├── ConnectionManager.php (PdoQuery lifecycle)
│   ├── PaginationManager.php (Pagination logic)
│   ├── PropertyCaster.php (Type casting)
│   ├── TableValidator.php (Validation)
│   ├── CrudOperations.php (Insert/Update/Delete)
│   └── SoftDeleteManager.php (Soft delete)
├── QueryBuilder.php (EXISTING - don't touch)
└── PdoQuery.php (EXISTING - don't touch)
```

### Extraction Order (Safest to Riskiest)

**Phase 0: Make Table Abstract** ⭐ DO FIRST (15-30 min)
- **Status**: Ready to implement
- **Risk Level**: ⭐ Lowest
- **Dependencies**: None
- **Impact**: Simplifies code, makes contract explicit

**Phase 1: PropertyCaster** ⭐ START REFACTORING HERE
- **Risk Level**: ⭐ Lowest
- **Dependencies**: Only needs `$_type_map` array
- **Impact**: Isolated, no side effects
- **Methods**: `castValue()`, `fetchRow()`, `hydrateResults()`

**Phase 2: TableValidator**
- **Risk Level**: ⭐⭐ Low
- **Dependencies**: Needs table name (via `_internalTable()`)
- **Impact**: Simple validation logic
- **Methods**: `validateProperties()`, `validateId()`

**Phase 3: PaginationManager**
- **Risk Level**: ⭐⭐ Low
- **Dependencies**: Pure calculation, no database operations
- **Impact**: No side effects, easy to test
- **Methods**: `setPage()`, `getCurrentPage()`, `getCount()`, `getTotalCounts()`, `getLimit()`

**Phase 4: ConnectionManager**
- **Risk Level**: ⭐⭐⭐ Medium
- **Dependencies**: PdoQuery (existing class)
- **Impact**: Core functionality, but well-isolated
- **Methods**: `getPdoQuery()`, `setError()`, `getError()`, `isConnected()`, `disconnect()`

**Phase 5: CrudOperations**
- **Risk Level**: ⭐⭐⭐⭐ Medium-High
- **Dependencies**: ConnectionManager, TableValidator, table name
- **Impact**: Core CRUD functionality
- **Methods**: `insert()`, `update()`, `delete()`, `buildUpdateQuery()`

**Phase 6: SoftDeleteManager**
- **Risk Level**: ⭐⭐⭐ Medium
- **Dependencies**: ConnectionManager, TableValidator
- **Impact**: Specialized feature, less critical
- **Methods**: `safeDelete()`, `restore()`, `activate()`, `deactivate()`

---

## Phase 1: PropertyCaster (START HERE)

### Overview
Extract type casting and result hydration logic into a dedicated class.

### Current Code Location
- `castValue()`: Lines 1089-1206
- `fetchRow()`: Lines 1076-1083
- `hydrateResults()`: Lines 1394-1433

### New Class Structure

```php
namespace Gemvc\Database\TableComponents;

class PropertyCaster
{
    /**
     * @param array<string, string> $typeMap Type mapping for properties
     */
    public function __construct(
        private array $typeMap
    ) {}
    
    /**
     * Cast database value to appropriate PHP type
     */
    public function castValue(string $property, mixed $value): mixed
    
    /**
     * Hydrate model properties from database row
     */
    public function fetchRow(object $instance, array $row): void
    
    /**
     * Convert query results to array of model instances
     */
    public function hydrateResults(string $className, array $queryResult): array
}
```

### Dependencies
- **Input**: `$_type_map` array from Table
- **Output**: Casted values, hydrated objects
- **No side effects**: Pure transformation logic

### Migration Steps

1. **Create new class**
   - Create `src/database/TableComponents/PropertyCaster.php`
   - Copy `castValue()`, `fetchRow()`, `hydrateResults()` logic
   - Add constructor to accept `$typeMap`

2. **Update Table class**
   - Add property: `private ?PropertyCaster $_propertyCaster = null;`
   - Add method: `private function getPropertyCaster(): PropertyCaster`
   - Replace calls to `$this->castValue()` with `$this->getPropertyCaster()->castValue()`
   - Replace calls to `$this->fetchRow()` with `$this->getPropertyCaster()->fetchRow()`
   - Replace calls to `$this->hydrateResults()` with `$this->getPropertyCaster()->hydrateResults()`

3. **Testing**
   - Run existing tests - should all pass
   - Add unit tests for `PropertyCaster` class
   - Verify no behavior changes

4. **Cleanup**
   - Remove old methods from Table
   - Update PHPDoc

### Risk Assessment
- **Risk**: ⭐ Lowest
- **Breaking Changes**: None (internal refactoring)
- **Test Coverage**: High (existing tests cover this functionality)
- **Rollback**: Easy (just revert changes)

### Success Criteria
- ✅ All existing tests pass
- ✅ No performance degradation
- ✅ Code is cleaner and more maintainable
- ✅ PropertyCaster is independently testable

---

## Phase 2: TableValidator

### Overview
Extract validation logic into a dedicated class.

### Current Code Location
- `validateProperties()`: Lines 123-136
- `validateId()`: Lines 142-152

### New Class Structure

```php
namespace Gemvc\Database\TableComponents;

class TableValidator
{
    public function __construct(
        private string $tableName
    ) {}
    
    public function validateProperties(object $instance, array $properties): bool
    
    public function validateId(int $id, string $operation = 'operation'): bool
}
```

### Migration Steps
1. Create `src/database/TableComponents/TableValidator.php`
2. Extract validation methods
3. Update Table to use validator
4. Test and verify

---

## Phase 3: PaginationManager

### Overview
Extract pagination logic into a dedicated class.

### Current Code Location
- `setPage()`: Lines 985-989
- `getCurrentPage()`: Lines 996-999
- `getCount()`: Lines 1006-1009
- `getTotalCounts()`: Lines 1016-1019
- `getLimit()`: Lines 1026-1029

### New Class Structure

```php
namespace Gemvc\Database\TableComponents;

class PaginationManager
{
    private int $limit;
    private int $offset = 0;
    private int $totalCount = 0;
    private int $countPages = 0;
    
    public function __construct(int $defaultLimit = 10) {}
    
    public function setPage(int $page): void
    public function getCurrentPage(): int
    public function getCount(): int
    public function getTotalCounts(): int
    public function getLimit(): int
    public function calculatePages(int $totalCount): void
}
```

---

## Phase 4: ConnectionManager

### Overview
Extract PdoQuery lifecycle management.

### Current Code Location
- `getPdoQuery()`: Lines 72-83
- `setError()`: Lines 88-96
- `getError()`: Lines 101-107
- `isConnected()`: Lines 112-115
- `disconnect()`: Lines 1211-1217

### New Class Structure

```php
namespace Gemvc\Database\TableComponents;

use Gemvc\Database\PdoQuery;

class ConnectionManager
{
    private ?PdoQuery $pdoQuery = null;
    private ?string $storedError = null;
    
    public function getPdoQuery(): PdoQuery
    public function setError(?string $error): void
    public function getError(): ?string
    public function isConnected(): bool
    public function disconnect(): void
}
```

---

## Phase 5: CrudOperations

### Overview
Extract CRUD operation logic.

### Current Code Location
- `insertSingleQuery()`: Lines 162-223
- `updateSingleQuery()`: Lines 230-252
- `deleteByIdQuery()`: Lines 260-280
- `deleteSingleQuery()`: Lines 372-384
- `buildUpdateQuery()`: Lines 1306-1326

### New Class Structure

```php
namespace Gemvc\Database\TableComponents;

class CrudOperations
{
    public function __construct(
        private ConnectionManager $connection,
        private TableValidator $validator,
        private string $tableName
    ) {}
    
    public function insert(object $instance): ?object
    public function update(object $instance, string $primaryKeyColumn, mixed $primaryKeyValue): ?object
    public function deleteById(string $primaryKeyColumn, int $id): ?int
    public function delete(object $instance, string $primaryKeyColumn, mixed $primaryKeyValue): ?int
}
```

---

## Phase 6: SoftDeleteManager

### Overview
Extract soft delete operations.

### Current Code Location
- `safeDeleteQuery()`: Lines 286-327
- `restoreQuery()`: Lines 334-365
- `activateQuery()`: Lines 852-872
- `deactivateQuery()`: Lines 883-906

### New Class Structure

```php
namespace Gemvc\Database\TableComponents;

class SoftDeleteManager
{
    public function __construct(
        private ConnectionManager $connection,
        private TableValidator $validator,
        private string $tableName
    ) {}
    
    public function safeDelete(object $instance, string $primaryKeyColumn, mixed $primaryKeyValue): ?object
    public function restore(object $instance, string $primaryKeyColumn, mixed $primaryKeyValue): ?object
    public function activate(string $primaryKeyColumn, int $id): ?int
    public function deactivate(string $primaryKeyColumn, int $id): ?int
}
```

---

## Final Table Class Structure

After all phases, `Table` becomes a facade/coordinator:

```php
abstract class Table
{
    // Composed services
    private ConnectionManager $connection;
    private PaginationManager $pagination;
    private PropertyCaster $caster;
    private TableValidator $validator;
    private CrudOperations $crud;
    private SoftDeleteManager $softDelete;
    
    // Internal query building (remains for backward compatibility)
    private string $_query = null;
    private array $_arr_where = [];
    // ... other query building properties
    
    // Public API - delegates to services
    public function insertSingleQuery(): ?static
    {
        return $this->crud->insert($this);
    }
    
    // ... other public methods delegate to services
}
```

---

## Testing Strategy

### For Each Phase

1. **Unit Tests**
   - Test extracted class in isolation
   - Mock dependencies
   - Test edge cases

2. **Integration Tests**
   - Test Table class with new component
   - Verify behavior unchanged
   - Test error handling

3. **Regression Tests**
   - Run full test suite
   - Verify no breaking changes
   - Check performance benchmarks

### Test Coverage Requirements

- **PropertyCaster**: 100% (pure functions, easy to test)
- **TableValidator**: 100% (simple logic)
- **PaginationManager**: 100% (pure calculations)
- **ConnectionManager**: 95%+ (wraps PdoQuery)
- **CrudOperations**: 90%+ (database operations)
- **SoftDeleteManager**: 90%+ (database operations)

---

## Risk Mitigation

### General Principles

1. **One Phase at a Time** - Complete and test before moving to next
2. **Backward Compatibility** - Public API unchanged
3. **Incremental Changes** - Small, focused commits
4. **Comprehensive Testing** - Test after each change
5. **Easy Rollback** - Keep old code until new code is proven

### Rollback Plan

If any phase fails:
1. Revert the phase's changes
2. Analyze the issue
3. Fix and retry
4. Don't proceed to next phase until current is stable

---

## Timeline Estimate

- **Phase 0 (Make Abstract)**: 15-30 minutes
- **Phase 1 (PropertyCaster)**: 2-3 hours
- **Phase 2 (TableValidator)**: 1-2 hours
- **Phase 3 (PaginationManager)**: 1-2 hours
- **Phase 4 (ConnectionManager)**: 2-3 hours
- **Phase 5 (CrudOperations)**: 4-6 hours
- **Phase 6 (SoftDeleteManager)**: 2-3 hours

**Total**: 12-20 hours (including Phase 0)

---

## Success Metrics

### Code Quality
- ✅ Table class reduced from 1,447 to ~300 lines
- ✅ Each component class < 200 lines
- ✅ Single Responsibility Principle followed
- ✅ High test coverage (>90%)

### Maintainability
- ✅ Easier to understand
- ✅ Easier to test
- ✅ Easier to extend
- ✅ Clear separation of concerns

### Performance
- ✅ No performance degradation
- ✅ Same or better execution time
- ✅ Same memory usage

---

## Future Enhancements (After Refactoring)

Once refactoring is complete, we can:
1. **Primary Key Configuration System** - Add to `TableValidator` and `CrudOperations`
2. **Migrate to QueryBuilder** - Optionally replace internal SQL building with `QueryBuilder`
3. **Add Caching Layer** - Easier with separated concerns
4. **Add Query Logging** - Centralized in `ConnectionManager`

---

## Approval

- [ ] Protocol reviewed and approved
- [ ] Phase 0 (Make Abstract) ready to start
- [ ] Phase 1 (PropertyCaster) ready to start
- [ ] Test suite ready
- [ ] Rollback plan understood

---

**Protocol Version**: 1.0  
**Created**: 2024  
**Status**: Ready for Phase 1 Implementation


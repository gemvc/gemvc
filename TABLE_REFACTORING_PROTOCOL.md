# Table Class Refactoring Protocol

## Executive Summary

This protocol outlines the strategic refactoring of the `Table` class (1,447 lines, 57 methods) into smaller, focused classes following SOLID principles. The goal is to improve maintainability, testability, and prepare for future enhancements (primary key configuration system).

---

## Current State Analysis

### Statistics
- **File**: `src/database/Table.php`
- **Lines**: 1,599 (reduced from 1,664 after component extraction)
- **Methods**: 71 (includes component delegation methods)
- **Properties**: 20+ (reduced after component extraction)
- **Responsibilities**: 6+ (reduced from 10+ after component extraction)
- **Components Extracted**: 4 (PropertyCaster, TableValidator, PaginationManager, ConnectionManager)

### Completed Work ✅

1. **Phase 0: Make Table Abstract** ✅ COMPLETED
   - Table class is now `abstract`
   - `getTable()` is now `abstract public function getTable(): string;`
   - `_internalTable()` method removed (replaced with direct `$this->getTable()` calls)
   - All child classes verified to implement `getTable()`

2. **Phase 0.5: Primary Key Configuration System** ✅ COMPLETED
   - Automatic primary key detection in constructor (`_detectPrimaryKey()`)
   - Primary key configuration via `setPrimaryKey()` method
   - Support for 'int', 'string', and 'uuid' types
   - UUID auto-generation support
   - Cached primary key properties: `$_primaryKeyColumn`, `$_primaryKeyType`, `$_primaryKeyAutoGenerate`
   - Helper methods: `getPrimaryKeyColumn()`, `getPrimaryKeyType()`, `isPrimaryKeyAutoGenerate()`
   - All CRUD methods refactored to use primary key system instead of hardcoded 'id'

3. **Method Refactoring** ✅ COMPLETED
   - `whereEqual()` method added (preferred over deprecated `where()`)
   - `where()` method deprecated (now calls `whereEqual()` internally)
   - `whereOr()` updated to use `whereEqual()`
   - All methods using hardcoded 'id' refactored to use `getPrimaryKeyColumn()`
   - Methods updated: `insertSingleQuery()`, `updateSingleQuery()`, `deleteByIdQuery()`, `deleteSingleQuery()`, `safeDeleteQuery()`, `restoreQuery()`, `activateQuery()`, `deactivateQuery()`, `selectById()`, `orderBy()`

4. **Phase 1: PropertyCaster** ✅ COMPLETED
   - Created `src/database/TableComponents/PropertyCaster.php`
   - Extracted `castValue()`, `fetchRow()`, and `hydrateResults()` methods
   - Table class now delegates to lazy-loaded `PropertyCaster` instance
   - All existing tests pass, new component tests created

5. **Phase 2: TableValidator** ✅ COMPLETED
   - Created `src/database/TableComponents/TableValidator.php`
   - Extracted `validateProperties()`, `validateId()`, and `validatePrimaryKey()` methods
   - Table class now delegates to lazy-loaded `TableValidator` instance
   - All existing tests pass, new component tests created

6. **Phase 3: PaginationManager** ✅ COMPLETED
   - Created `src/database/TableComponents/PaginationManager.php`
   - Extracted pagination properties and methods (`setPage()`, `getCurrentPage()`, `getCount()`, `getTotalCounts()`, `getLimit()`, `limit()`, `noLimit()`, `all()`)
   - Table class now uses `PaginationManager` instance (initialized in constructor)
   - Controller integration verified, all existing tests pass, new component tests created

7. **Phase 4: ConnectionManager** ✅ COMPLETED
   - Created `src/database/TableComponents/ConnectionManager.php`
   - Extracted connection-related properties (`_pdoQuery`, `_storedError`) and methods (`getPdoQuery()`, `setError()`, `getError()`, `isConnected()`, `disconnect()`, `beginTransaction()`, `commit()`, `rollback()`)
   - Table class now delegates to lazy-loaded `ConnectionManager` instance
   - All existing tests pass, new component tests created

8. **Test Extraction Phase** ✅ COMPLETED
   - Extracted component-specific unit tests from `TableTest.php` into dedicated test files:
     - `PropertyCasterTest.php` (23 tests)
     - `TableValidatorTest.php` (20 tests)
     - `PaginationManagerTest.php` (27 tests)
     - `ConnectionManagerTest.php` (16 tests)
   - `TableTest.php` now contains only integration tests (114 tests)
   - Total test count: 200 tests (86 component + 114 integration)
   - All tests passing

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

### Phase 0: Make Table Abstract ✅ COMPLETED

**Status**: ✅ COMPLETED

**Changes Made**:
1. ✅ Changed `class Table` → `abstract class Table`
2. ✅ Added `abstract public function getTable(): string;`
3. ✅ Removed `_internalTable()` method (replaced with direct `$this->getTable()` calls)
4. ✅ Removed unnecessary `@method` annotations
5. ✅ Removed `method_exists()` and `is_string()` checks

**Verification**:
- ✅ All tests pass (149 tests, 363 assertions)
- ✅ PHPStan Level 9 compliance
- ✅ All child classes compile successfully

**Impact**: Table class is now properly abstract, making the contract explicit and simplifying future refactoring.

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

**Phase 0: Make Table Abstract** ✅ COMPLETED
- **Status**: ✅ COMPLETED
- **Risk Level**: ⭐ Lowest
- **Dependencies**: None
- **Impact**: ✅ Code simplified, contract explicit

**Phase 0.5: Primary Key Configuration System** ✅ COMPLETED
- **Status**: ✅ COMPLETED
- **Risk Level**: ⭐⭐ Low
- **Dependencies**: Phase 0
- **Impact**: ✅ All CRUD methods now use flexible primary key system

**Phase 1: PropertyCaster** ✅ COMPLETED
- **Status**: ✅ COMPLETED
- **Risk Level**: ⭐ Lowest
- **Dependencies**: Only needs `$_type_map` array
- **Impact**: ✅ Isolated, no side effects, successfully extracted
- **Methods**: `castValue()`, `fetchRow()`, `hydrateResults()`

**Phase 2: TableValidator** ✅ COMPLETED
- **Status**: ✅ COMPLETED
- **Risk Level**: ⭐⭐ Low
- **Dependencies**: Needs table name (via `getTable()`)
- **Impact**: ✅ Simple validation logic, successfully extracted
- **Methods**: `validateProperties()`, `validateId()`, `validatePrimaryKey()`

**Phase 3: PaginationManager** ✅ COMPLETED
- **Status**: ✅ COMPLETED
- **Risk Level**: ⭐⭐ Low
- **Dependencies**: Pure calculation, no database operations
- **Impact**: ✅ No side effects, easy to test, successfully extracted
- **Methods**: `setPage()`, `getCurrentPage()`, `getCount()`, `getTotalCounts()`, `getLimit()`, `limit()`, `noLimit()`, `all()`

**Phase 4: ConnectionManager** ✅ COMPLETED
- **Status**: ✅ COMPLETED
- **Risk Level**: ⭐⭐⭐ Medium
- **Dependencies**: PdoQuery (existing class)
- **Impact**: ✅ Core functionality, well-isolated, successfully extracted
- **Methods**: `getPdoQuery()`, `setError()`, `getError()`, `isConnected()`, `disconnect()`, `beginTransaction()`, `commit()`, `rollback()`

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

## Phase 1: PropertyCaster ✅ COMPLETED

**Status**: ✅ COMPLETED
**Prerequisites**: Phase 0 ✅, Phase 0.5 ✅

### Overview
Extract type casting and result hydration logic into a dedicated class.

### Current Code Location
- `castValue()`: Lines 1150-1264 (114 lines)
- `fetchRow()`: Lines 1134-1141 (8 lines)
- `hydrateResults()`: Lines 1434-1473 (40 lines)

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

## Phase 2: TableValidator ✅ COMPLETED

**Status**: ✅ COMPLETED

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

## Phase 3: PaginationManager ✅ COMPLETED

**Status**: ✅ COMPLETED

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

## Phase 4: ConnectionManager ✅ COMPLETED

**Status**: ✅ COMPLETED

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

## Phase 5: CrudOperations ⭐ NEXT

**Status**: Ready to implement
**Prerequisites**: Phase 1 ✅, Phase 2 ✅, Phase 4 ✅

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

**Status**: Pending
**Prerequisites**: Phase 5 ✅

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

- **Phase 0 (Make Abstract)**: ✅ COMPLETED (15-30 minutes)
- **Phase 0.5 (Primary Key System)**: ✅ COMPLETED (2-3 hours)
- **Phase 1 (PropertyCaster)**: ✅ COMPLETED (2-3 hours)
- **Phase 2 (TableValidator)**: ✅ COMPLETED (1-2 hours)
- **Phase 3 (PaginationManager)**: ✅ COMPLETED (1-2 hours)
- **Phase 4 (ConnectionManager)**: ✅ COMPLETED (2-3 hours)
- **Test Extraction Phase**: ✅ COMPLETED (2-3 hours)
- **Phase 5 (CrudOperations)**: 4-6 hours ⭐ NEXT
- **Phase 6 (SoftDeleteManager)**: 2-3 hours

**Total**: 14-23 hours
**Completed**: ~11-16 hours (Phases 0-4 + Test Extraction)
**Remaining**: ~6-7 hours (Phases 5-6)

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
1. ✅ **Primary Key Configuration System** - ✅ COMPLETED (integrated into Table class)
2. **Migrate to QueryBuilder** - Optionally replace internal SQL building with `QueryBuilder`
3. **Add Caching Layer** - Easier with separated concerns
4. **Add Query Logging** - Centralized in `ConnectionManager`
5. **Composite Primary Keys** - Extend primary key system to support multiple columns

---

## Approval

- [x] Protocol reviewed and approved
- [x] Phase 0 (Make Abstract) ✅ COMPLETED
- [x] Phase 0.5 (Primary Key System) ✅ COMPLETED
- [x] Phase 1 (PropertyCaster) ✅ COMPLETED
- [x] Phase 2 (TableValidator) ✅ COMPLETED
- [x] Phase 3 (PaginationManager) ✅ COMPLETED
- [x] Phase 4 (ConnectionManager) ✅ COMPLETED
- [x] Test Extraction Phase ✅ COMPLETED
- [x] Phase 5 (CrudOperations) ready to start ⭐ NEXT
- [x] Test suite ready
- [x] Rollback plan understood

---

## Current Status Summary

### ✅ Completed
- Phase 0: Table is now abstract
- Phase 0.5: Primary key configuration system implemented
- All CRUD methods refactored to use primary key system
- `whereEqual()` method added, `where()` deprecated
- Phase 1: PropertyCaster extracted (type casting and result hydration)
- Phase 2: TableValidator extracted (validation logic)
- Phase 3: PaginationManager extracted (pagination logic)
- Phase 4: ConnectionManager extracted (connection lifecycle)
- Test Extraction: Component-specific tests extracted into dedicated test files
- **Current Table.php**: 1,599 lines (down from 1,664)
- **Test Coverage**: 200 tests (86 component + 114 integration), all passing

### ⭐ Next Steps
- Phase 5: Extract CrudOperations (insert, update, delete operations)
- Phase 6: Extract SoftDeleteManager (soft delete operations)

---

**Protocol Version**: 3.0  
**Created**: 2024  
**Last Updated**: 2024 (after Phases 1-4 and Test Extraction completion)  
**Status**: Ready for Phase 5 Implementation ⭐


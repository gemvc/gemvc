# Code Coverage Improvement Plan

## Current Status
- **Overall Coverage**: 5.43% classes, 8.23% methods, 6.19% lines
- **Tests**: 98 tests, 288 assertions
- **Coverage Driver**: PCOV 1.0.12 ✅

## Priority Areas for Testing

### 🔴 CRITICAL (High Impact, Low Coverage)

#### 1. Table Class (10.17% methods, 8.72% lines)
**Current Tests**: Basic structure tests only
**Missing Coverage**:
- ✅ `select()`, `where()`, `limit()`, `orderBy()` - Basic query builder (partially tested)
- ❌ `insertSingleQuery()` - Full CRUD operation
- ❌ `updateSingleQuery()` - Full CRUD operation  
- ❌ `deleteByIdQuery()` - Full CRUD operation
- ❌ `whereIn()`, `whereLike()`, `whereOr()` - Advanced WHERE clauses
- ❌ `join()` - JOIN operations
- ❌ `run()` - Query execution
- ❌ `getTotalCounts()`, `getCountPages()` - Pagination
- ❌ `offset()`, `page()` - Pagination helpers
- ❌ `hydrateResults()` - Result object hydration
- ❌ `buildInsertQuery()`, `buildUpdateQuery()` - Query building
- ❌ `getInsertBindings()`, `getUpdateBindings()` - Parameter binding
- ❌ Property type mapping and casting

**Action**: Create comprehensive integration tests with real database

#### 2. Request Class (6.98% methods, 17.61% lines)
**Current Tests**: Basic schema validation only
**Missing Coverage**:
- ❌ `auth()` - Authentication and authorization
- ❌ `authenticate()` - JWT token verification
- ❌ `authorize()` - Role-based authorization
- ❌ `intValuePost()`, `floatValuePost()` - Type-safe POST getters
- ❌ `intValueGet()`, `floatValueGet()` - Type-safe GET getters
- ❌ `defineGetSchema()`, `definePutSchema()`, `definePatchSchema()` - Schema validation
- ❌ `validateStringPosts()` - String length validation
- ❌ `findable()` - Search/filter functionality
- ❌ `sortable()` - Sorting functionality
- ❌ `mapPostToObject()` - Object mapping
- ❌ `forwardRequest()` - External API forwarding
- ❌ `returnResponse()` - Response handling

**Action**: Create comprehensive Request tests with mock JWT tokens

#### 3. Controller Class (10% methods, 2.56% lines)
**Current Tests**: Constructor only
**Missing Coverage**:
- ❌ `ListObjects()` - List with pagination, filtering, sorting
- ❌ `createList()` - List JSON response
- ❌ `listJsonResponse()` - List JSON response
- ❌ `_handlePagination()` - Pagination logic
- ❌ `_handleSearchable()` - Search functionality
- ❌ `_handleFindable()` - Filter functionality
- ❌ `_handleSortable()` - Sort functionality

**Action**: Create Controller tests with mock models

#### 4. TypeChecker Class (0% methods, 28.05% lines)
**Current Tests**: None
**Missing Coverage**:
- ❌ All 6 methods completely untested
- This is a helper class used throughout the framework

**Action**: Create TypeChecker unit tests

### 🟡 MEDIUM PRIORITY (Good Coverage, Can Improve)

#### 5. JWTToken (41.67% methods, 59.63% lines)
**Current Tests**: Basic token creation and verification
**Missing Coverage**:
- ❌ Token refresh logic
- ❌ Token renewal edge cases
- ❌ Multiple token types (access, refresh, login)
- ❌ Token payload extraction

#### 6. SecurityManager (40% methods, 72.92% lines)
**Current Tests**: Path blocking only
**Missing Coverage**:
- ❌ `sendSecurityResponse()` - Response handling
- ❌ `addBlockedPath()` - Dynamic path blocking
- ❌ `addBlockedExtension()` - Dynamic extension blocking
- ❌ `getBlockedPaths()`, `getBlockedExtensions()` - Getters

#### 7. ApacheRequest (15.38% methods, 68.47% lines)
**Current Tests**: Basic sanitization only
**Missing Coverage**:
- ❌ PUT request sanitization
- ❌ PATCH request sanitization
- ❌ JSON POST parsing
- ❌ File upload handling
- ❌ Auth header extraction

### 🟢 LOW PRIORITY (Well Covered)

- ✅ Schema classes: 100% coverage
- ✅ Response: 66.67% methods (good)
- ✅ CryptHelper: 94.29% lines (excellent)

## Recommended Testing Order

### Phase 1: Core Functionality (Highest Impact)
1. **Request Class** - Authentication, authorization, schema validation
2. **Table Class** - CRUD operations, query builder
3. **Controller Class** - List operations, pagination

### Phase 2: Supporting Classes
4. **TypeChecker** - Type validation helpers
5. **JWTToken** - Advanced token operations
6. **SecurityManager** - Dynamic security rules

### Phase 3: Integration & Edge Cases
7. **ApacheRequest** - Advanced request handling
8. **Table** - Complex queries, joins, aggregations
9. **Request** - External API forwarding

## Target Coverage Goals

- **Short-term**: 20% overall coverage
- **Medium-term**: 40% overall coverage  
- **Long-term**: 60%+ overall coverage

## Test Strategy

### For Table Class
- Use in-memory SQLite database
- Test each CRUD operation independently
- Test query builder methods in isolation
- Test error handling and edge cases

### For Request Class
- Mock JWT tokens for auth tests
- Test all schema validation methods
- Test type-safe getters with various inputs
- Test filtering and sorting logic

### For Controller Class
- Mock Table/Model objects
- Test pagination with various page sizes
- Test filtering and sorting combinations
- Test error handling

## Quick Wins

1. **TypeChecker** - Simple helper class, easy to test (0% → 80%+)
2. **SecurityManager** - Add tests for remaining methods (40% → 80%+)
3. **Request type getters** - Simple methods (6.98% → 30%+)
4. **Response edge cases** - Already well covered, add edge cases (66.67% → 80%+)

## Notes

- Focus on **methods** coverage first (easier to measure)
- **Lines** coverage will improve as methods are tested
- Integration tests will provide the biggest coverage boost
- Use coverage report to identify specific untested methods


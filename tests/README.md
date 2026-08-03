# GEMVC Test Suite

This directory contains the PHPUnit test suite for the GEMVC framework.

## 📁 Test Structure

```
tests/
├── Unit/                          # Unit tests (70%)
│   ├── Core/                      # Core framework tests
│   │   ├── SecurityManagerTest.php
│   │   └── RateLimiterTest.php
│   ├── Database/                  # ORM / migrate / dialects
│   │   ├── ViewTableTest.php      # ViewTable, ViewGenerator, --all order
│   │   ├── SchemaGeneratorTest.php
│   │   ├── SqliteIntegrationTest.php
│   │   └── Dialect/
│   └── Http/                      # HTTP layer tests
│       ├── ApacheRequestTest.php
│       ├── RequestTest.php
│       ├── ResponseTest.php
│       └── JWTTokenTest.php
│
├── Integration/                   # Integration tests (25%)
│   └── Security/                  # Security integration tests
│       └── InputSanitizationTest.php
│
├── Helpers/                       # Test helpers
│   ├── DatabaseTestCase.php
│   └── ApiTestCase.php
│
└── bootstrap.php                  # Test bootstrap
```

## 🚀 Running Tests

### Run All Tests
```bash
vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# Unit tests only
vendor/bin/phpunit tests/Unit

# Integration tests only
vendor/bin/phpunit tests/Integration

# Database tests
vendor/bin/phpunit tests/Unit/Database
vendor/bin/phpunit tests/Unit/Database/ViewTableTest.php
```

### Run Specific Test Class
```bash
vendor/bin/phpunit tests/Unit/Http/ApacheRequestTest.php
```

### Run with Coverage
```bash
vendor/bin/phpunit --coverage-html coverage/html
```

## ✅ PHPStan Validation

### Check Framework Code
```bash
vendor/bin/phpstan analyse src --configuration=phpstan.neon
```

### Check Test Code
```bash
vendor/bin/phpstan analyse tests --configuration=phpstan-tests.neon
```

### Check Both
```bash
vendor/bin/phpstan analyse src --configuration=phpstan.neon
vendor/bin/phpstan analyse tests --configuration=phpstan-tests.neon
```

## 📊 Test Coverage

Current test coverage focuses on:

### ✅ Completed
- **Security Tests**: XSS prevention, input sanitization, path protection
- **HTTP Layer**: Request sanitization, Response factory, JWT tokens
- **Core Framework (5.15.0)**: Family trust (`requireInternalService` / `InternalTrust`); SecurityManager path blocking; RateLimiter (apcu/redis/both/none); Protected / unified API bases; `validateOrFail` / `ApiServiceSharedTrait`
- **Database**: `forUpdate()` (5.12.0); `ViewTable` / `ViewGenerator` / dialect view DDL / `TableMigrateOrder` (5.11.0, `ViewTableTest`)

### 🚧 In Progress
- Core framework classes (ApiService, Controller)
- Broader database layer / CLI command tests

## 🧪 Test Examples

### Security Test Example
```php
public function testXssInputSanitizationInPost(): void
{
    $_POST['name'] = '<script>alert("XSS")</script>';
    $ar = new StandardHttpRequest();
    $request = $ar->request;
    
    // XSS should be sanitized
    $this->assertStringNotContainsString('<script>', $request->post['name']);
}
```

### JWT Test Example
```php
public function testCreateAccessToken(): void
{
    $token = $this->jwtToken->createAccessToken(123);
    $this->assertIsString($token);
    $this->assertEquals('access', $this->jwtToken->type);
}
```

## 📝 Writing New Tests

### Test Naming Convention
- Use descriptive names: `testXssInputSanitizationInPost()`
- Follow pattern: `test[What][When][Expected]()`

### Type Safety
- Always use `declare(strict_types=1);`
- Add full type hints to all methods
- Use PHPDoc for complex types: `@param array<string, mixed>`

### Test Structure
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup
    }
    
    public function testSomething(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

## 🔍 Test Helpers

### DatabaseTestCase
Base class for database-related tests with in-memory SQLite:
```php
use Tests\Helpers\DatabaseTestCase;

class MyDatabaseTest extends DatabaseTestCase
{
    // Has access to $this->pdo
}
```

### ApiTestCase
Base class for API-related tests:
```php
use Tests\Helpers\ApiTestCase;

class MyApiTest extends ApiTestCase
{
    protected function createMockRequest(array $post = []): Request
    {
        return parent::createMockRequest($post);
    }
}
```

## 🎯 Next Steps

1. **Add Core Framework Tests**:
   - ApiService tests
   - Controller tests
   - Bootstrap tests

2. **Add Database Layer Tests**:
   - Table operations
   - Query builder
   - Schema generation

3. **Add CLI Command Tests**:
   - Code generation
   - Database commands

4. **Add Integration Tests**:
   - Complete CRUD workflows
   - Authentication flows

## 📚 Resources

- [TEST_STRATEGY.md](../TEST_STRATEGY.md) - Complete test strategy
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPStan Documentation](https://phpstan.org/)


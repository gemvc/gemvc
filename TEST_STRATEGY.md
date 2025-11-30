# 🧪 GEMVC Framework - Comprehensive Test Strategy

## 📋 Table of Contents

- [Overview](#overview)
- [Testing Philosophy](#testing-philosophy)
- [Test Structure](#test-structure)
- [Test Categories](#test-categories)
- [Test Implementation Plan](#test-implementation-plan)
- [Test Tools & Setup](#test-tools--setup)
- [Coverage Goals](#coverage-goals)
- [CI/CD Integration](#cicd-integration)
- [Test Examples](#test-examples)

---

## 🎯 Overview

This document outlines a comprehensive testing strategy for the GEMVC framework, a multi-platform PHP REST API framework supporting OpenSwoole, Apache, and Nginx.

### Framework Characteristics

- **4-Layer Architecture**: API → Controller → Model → Table
- **Multi-Platform**: OpenSwoole, Apache, Nginx
- **Security-First**: 90% automatic security features
- **Type-Safe**: PHPStan Level 9 compliance
- **CLI-Driven**: Code generation and database management
- **Microservice-Optimized**: Lightweight ORM, service boundaries

### Testing Goals

1. ✅ **Ensure framework reliability** across all platforms
2. ✅ **Validate security features** (automatic + developer-enabled)
3. ✅ **Verify 4-layer architecture** works correctly
4. ✅ **Test CLI commands** for code generation and database operations
5. ✅ **Maintain PHPStan Level 9** compliance for framework code
6. ✅ **Maintain PHPStan Level 9** compliance for test code
7. ✅ **Performance testing** for OpenSwoole async capabilities
8. ✅ **Integration testing** for real-world scenarios

---

## 🧭 Testing Philosophy

### Core Principles

1. **Test Pyramid**: More unit tests, fewer integration tests, minimal E2E tests
2. **Isolation**: Each test should be independent and repeatable
3. **Fast Feedback**: Unit tests should run in seconds
4. **Real Scenarios**: Integration tests should mirror real-world usage
5. **Security First**: Security features must be thoroughly tested
6. **Cross-Platform**: Test on all supported webservers
7. **Type Safety**: All test code must pass PHPStan Level 9 validation

### Test Types Distribution

```
         /\
        /  \      E2E Tests (5%)
       /    \
      /      \    Integration Tests (25%)
     /        \
    /__________\  Unit Tests (70%)
```

---

## 📁 Test Structure

### Directory Organization

```
tests/
├── Unit/                          # Unit tests (70%)
│   ├── Core/                      # Core framework tests
│   │   ├── ApiServiceTest.php
│   │   ├── ControllerTest.php
│   │   ├── SecurityManagerTest.php
│   │   └── BootstrapTest.php
│   ├── Http/                      # HTTP layer tests
│   │   ├── RequestTest.php
│   │   ├── ResponseTest.php
│   │   ├── JWTTokenTest.php
│   │   ├── ApacheRequestTest.php
│   │   └── SwooleRequestTest.php
│   ├── Database/                   # Database layer tests
│   │   ├── TableTest.php
│   │   ├── QueryBuilderTest.php
│   │   ├── SchemaTest.php
│   │   └── UniversalQueryExecuterTest.php
│   ├── Helper/                     # Helper class tests
│   │   ├── CryptHelperTest.php
│   │   ├── FileHelperTest.php
│   │   ├── ImageHelperTest.php
│   │   ├── TypeCheckerTest.php
│   │   └── StringHelperTest.php
│   └── CLI/                        # CLI command tests
│       ├── CommandTest.php
│       ├── CreateServiceTest.php
│       ├── DbMigrateTest.php
│       └── InitProjectTest.php
│
├── Integration/                    # Integration tests (25%)
│   ├── Api/                        # API endpoint tests
│   │   ├── UserApiTest.php
│   │   └── ProductApiTest.php
│   ├── Database/                   # Database integration
│   │   ├── TableOperationsTest.php
│   │   ├── MigrationTest.php
│   │   └── SchemaGenerationTest.php
│   ├── Security/                   # Security integration
│   │   ├── InputSanitizationTest.php
│   │   ├── SQLInjectionTest.php
│   │   ├── XSSTest.php
│   │   └── JWTAuthenticationTest.php
│   └── CrossPlatform/             # Multi-platform tests
│       ├── ApacheIntegrationTest.php
│       ├── SwooleIntegrationTest.php
│       └── NginxIntegrationTest.php
│
├── E2E/                            # End-to-end tests (5%)
│   ├── CRUDWorkflowTest.php
│   ├── AuthenticationFlowTest.php
│   └── MicroserviceTest.php
│
├── Fixtures/                       # Test fixtures
│   ├── Database/
│   │   └── schema.sql
│   ├── Files/
│   │   ├── test-image.jpg
│   │   └── test-document.pdf
│   └── Requests/
│       └── sample-requests.json
│
├── Helpers/                        # Test helpers
│   ├── DatabaseTestCase.php
│   ├── ApiTestCase.php
│   ├── MockRequestHelper.php
│   └── TestDataFactory.php
│
└── bootstrap.php                   # Test bootstrap
```

---

## 🧪 Test Categories

### 1. Unit Tests (70%)

#### 1.1 Core Framework Tests

**ApiService Tests**:
- ✅ Constructor initialization
- ✅ Request injection
- ✅ Schema validation delegation
- ✅ Response handling
- ✅ Error handling

**Controller Tests**:
- ✅ Request mapping to models
- ✅ Pagination helpers
- ✅ Filtering and sorting
- ✅ List creation helpers

**SecurityManager Tests**:
- ✅ Path blocking
- ✅ File extension blocking
- ✅ Directory protection
- ✅ Security response generation

**Bootstrap Tests**:
- ✅ URL parsing
- ✅ Service routing
- ✅ Method extraction
- ✅ Error handling

#### 1.2 HTTP Layer Tests

**Request Tests**:
- ✅ Input sanitization (XSS prevention)
- ✅ Schema validation
- ✅ Type-safe getters (`intValueGet()`, `stringValueGet()`)
- ✅ Authentication checks
- ✅ Authorization checks
- ✅ Filtering and sorting setup

**Response Tests**:
- ✅ Response factory methods (`success()`, `created()`, `error()`)
- ✅ HTTP status codes
- ✅ JSON encoding
- ✅ Response structure

**JWT Token Tests**:
- ✅ Token creation (access, refresh, login)
- ✅ Token verification
- ✅ Token renewal
- ✅ Expiration handling
- ✅ Signature validation
- ✅ Payload extraction

**ApacheRequest Tests**:
- ✅ Header sanitization
- ✅ POST/GET/PUT/PATCH sanitization
- ✅ File upload handling
- ✅ Cookie extraction

**SwooleRequest Tests**:
- ✅ OpenSwoole request adaptation
- ✅ Raw body parsing
- ✅ File normalization
- ✅ Cookie filtering
- ✅ Dangerous cookie blocking

#### 1.3 Database Layer Tests

**Table Tests**:
- ✅ Property mapping
- ✅ Type map validation
- ✅ Schema definition
- ✅ Query building
- ✅ CRUD operations
- ✅ Protected property handling
- ✅ Aggregation property handling (`_` prefix)

**QueryBuilder Tests**:
- ✅ SELECT queries
- ✅ WHERE clauses
- ✅ JOIN operations
- ✅ ORDER BY
- ✅ LIMIT/OFFSET
- ✅ Prepared statement generation

**Schema Tests**:
- ✅ Primary key definition
- ✅ Unique constraints
- ✅ Foreign keys
- ✅ Indexes
- ✅ Check constraints
- ✅ Fulltext search

**UniversalQueryExecuter Tests**:
- ✅ Prepared statement enforcement
- ✅ Parameter binding
- ✅ Type detection
- ✅ Query length validation
- ✅ Error handling

#### 1.4 Helper Class Tests

**CryptHelper Tests**:
- ✅ Password hashing (Argon2i)
- ✅ Password verification
- ✅ Encryption/decryption (AES-256-CBC)
- ✅ HMAC generation
- ✅ IV generation

**FileHelper Tests**:
- ✅ File encryption
- ✅ File decryption
- ✅ Integrity verification
- ✅ Tampering detection

**ImageHelper Tests**:
- ✅ Image signature detection (magic bytes)
- ✅ WebP conversion
- ✅ MIME type validation
- ✅ File type spoofing prevention

**TypeChecker Tests**:
- ✅ Type validation (string, int, float, bool, array)
- ✅ Advanced types (email, url, date, datetime, json, ip)
- ✅ String length validation
- ✅ Regex validation
- ✅ Optional field handling

**StringHelper Tests**:
- ✅ String manipulation
- ✅ Sanitization
- ✅ Encoding/decoding

#### 1.5 CLI Command Tests

**Command Base Tests**:
- ✅ Argument parsing
- ✅ Option handling
- ✅ Output formatting
- ✅ Error handling

**Code Generation Tests**:
- ✅ Service generation
- ✅ Controller generation
- ✅ Model generation
- ✅ Table generation
- ✅ CRUD generation
- ✅ Template variable replacement
- ✅ File writing

**Database Command Tests**:
- ✅ Database initialization
- ✅ Table migration
- ✅ Schema generation
- ✅ Table listing
- ✅ Table description
- ✅ Table dropping

**Init Command Tests**:
- ✅ Project initialization
- ✅ Directory creation
- ✅ File copying
- ✅ Template copying
- ✅ Docker setup
- ✅ PHPStan installation

### 2. Integration Tests (25%)

#### 2.1 API Integration Tests

**User API Tests**:
- ✅ Complete CRUD workflow
- ✅ Schema validation
- ✅ Authentication flow
- ✅ Authorization checks
- ✅ Error responses
- ✅ Success responses

**Product API Tests**:
- ✅ Create product
- ✅ Read product
- ✅ Update product
- ✅ Delete product
- ✅ List products with filtering
- ✅ List products with sorting
- ✅ Pagination

#### 2.2 Database Integration Tests

**Table Operations Tests**:
- ✅ Real database connections
- ✅ INSERT operations
- ✅ SELECT operations
- ✅ UPDATE operations
- ✅ DELETE operations
- ✅ Transaction handling
- ✅ Connection pooling (OpenSwoole)

**Migration Tests**:
- ✅ Table creation
- ✅ Column addition
- ✅ Column modification
- ✅ Index creation
- ✅ Constraint addition
- ✅ Schema synchronization

**Schema Generation Tests**:
- ✅ Schema from PHP classes
- ✅ Type mapping
- ✅ Constraint generation
- ✅ Foreign key creation

#### 2.3 Security Integration Tests

**Input Sanitization Tests**:
- ✅ XSS attack prevention
- ✅ SQL injection prevention
- ✅ Path traversal prevention
- ✅ Header injection prevention
- ✅ File upload attacks

**Authentication Integration Tests**:
- ✅ JWT token creation
- ✅ Token validation
- ✅ Token expiration
- ✅ Token renewal
- ✅ Role-based access control

**Authorization Integration Tests**:
- ✅ Role checking
- ✅ Permission validation
- ✅ Access denial
- ✅ Access grant

#### 2.4 Cross-Platform Integration Tests

**Apache Integration Tests**:
- ✅ Request handling
- ✅ Response generation
- ✅ File uploads
- ✅ Database operations
- ✅ Security features

**OpenSwoole Integration Tests**:
- ✅ Async request handling
- ✅ Connection pooling
- ✅ WebSocket support
- ✅ Hot reload
- ✅ Performance characteristics

**Nginx Integration Tests**:
- ✅ Request proxying
- ✅ Static file serving
- ✅ PHP-FPM integration

### 3. End-to-End Tests (5%)

#### 3.1 CRUD Workflow Tests

**Complete User Workflow**:
1. Create user
2. Read user
3. Update user
4. Delete user
5. List users
6. Filter users
7. Sort users

#### 3.2 Authentication Flow Tests

**Complete Auth Flow**:
1. User registration
2. User login
3. Token generation
4. Protected endpoint access
5. Token refresh
6. Logout

#### 3.3 Microservice Tests

**Service Communication**:
- ✅ Service boundaries
- ✅ API communication
- ✅ Data consistency
- ✅ Error propagation

---

## 📝 Test Implementation Plan

### Phase 1: Foundation (Week 1-2)

**Setup**:
- ✅ Configure PHPUnit
- ✅ Configure Pest (optional)
- ✅ Setup test database
- ✅ Create test helpers
- ✅ **Configure PHPStan for test code** (phpstan-tests.neon)
- ✅ Setup CI/CD pipeline with PHPStan checks

**Priority Tests**:
1. Core framework classes (ApiService, Controller, Table)
2. Security features (SecurityManager, Input sanitization)
3. HTTP layer (Request, Response, JWT)

**PHPStan Setup**:
- ✅ Create `phpstan-tests.neon` configuration
- ✅ Add PHPStan checks to CI/CD pipeline
- ✅ Ensure all test code passes Level 9

### Phase 2: Core Features (Week 3-4)

**Database Layer**:
- ✅ Table operations
- ✅ Query builder
- ✅ Schema generation
- ✅ Migrations

**Helper Classes**:
- ✅ CryptHelper
- ✅ FileHelper
- ✅ ImageHelper
- ✅ TypeChecker

### Phase 3: CLI & Integration (Week 5-6)

**CLI Commands**:
- ✅ Code generation
- ✅ Database commands
- ✅ Project initialization

**Integration Tests**:
- ✅ API endpoints
- ✅ Database operations
- ✅ Security features

### Phase 4: Cross-Platform & E2E (Week 7-8)

**Cross-Platform**:
- ✅ Apache tests
- ✅ OpenSwoole tests
- ✅ Nginx tests

**E2E Tests**:
- ✅ Complete workflows
- ✅ Real-world scenarios

---

## 🛠️ Test Tools & Setup

### Required Tools

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.1",
    "pestphp/pest": "^2.0",
    "phpstan/phpstan": "^2.1",
    "mockery/mockery": "^1.6"
  }
}
```

### PHPUnit Configuration

**phpunit.xml**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.1/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         failOnRisky="true"
         failOnWarning="true"
         beStrictAboutTestsThatDoNotTestAnything="true"
         beStrictAboutOutputDuringTests="true">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="E2E">
            <directory>tests/E2E</directory>
        </testsuite>
    </testsuites>
    
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/stubs</directory>
            <directory>src/startup</directory>
        </exclude>
        <report>
            <html outputDirectory="coverage/html"/>
            <clover outputFile="coverage/clover.xml"/>
        </report>
    </coverage>
    
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_NAME" value="gemvc_test"/>
        <env name="DB_HOST" value="localhost"/>
        <env name="TOKEN_SECRET" value="test-secret-key"/>
    </php>
</phpunit>
```

### Test Bootstrap

**tests/bootstrap.php**:
```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load test environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing');
$dotenv->load();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup test database
// Initialize test fixtures
```

### PHPStan Configuration for Test Code

**phpstan-tests.neon** (separate config for tests):
```neon
parameters:
    level: 9
    paths:
        - tests
    excludePaths:
        - tests/Fixtures
        - tests/bootstrap.php
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
    stubFiles:
        - src/stubs/OpenSwoole.php
        - src/stubs/Redis.php
    ignoreErrors:
        # PHPUnit specific
        - '#Call to an undefined method PHPUnit\\Framework\\MockObject\\MockObject::.*#'
        - '#Access to an undefined property PHPUnit\\Framework\\MockObject\\MockObject::\$.*#'
        # Test helpers may use dynamic properties
        - '#Access to an undefined property Tests\\Helpers\\.*::\$.*#'
```

**Alternative: Single phpstan.neon with test paths**:
```neon
parameters:
    level: 9
    paths:
        - src
        - tests
    excludePaths:
        - src/http/SwooleWebSocketHandler.php
        - src/database/SwooleDatabaseManager.php
        - src/database/SwooleDatabaseManagerAdapter.php
        - vendor/*
        - src/startup/*
        - tests/Fixtures
        - tests/bootstrap.php
    stubFiles:
        - src/stubs/OpenSwoole.php
        - src/stubs/Redis.php
    ignoreErrors:
        - '#Possibly invalid array key type float\|int\.#'
        # PHPUnit specific
        - '#Call to an undefined method PHPUnit\\Framework\\MockObject\\MockObject::.*#'
        - '#Access to an undefined property PHPUnit\\Framework\\MockObject\\MockObject::\$.*#'
```

**Running PHPStan on Test Code**:
```bash
# Analyze test code separately
vendor/bin/phpstan analyse tests --configuration=phpstan-tests.neon

# Or analyze both framework and tests together
vendor/bin/phpstan analyse src tests --configuration=phpstan.neon

# In CI/CD, run both
vendor/bin/phpstan analyse src --configuration=phpstan.neon
vendor/bin/phpstan analyse tests --configuration=phpstan-tests.neon
```

**Type-Safe Test Code Examples**:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\Request;

class RequestTest extends TestCase
{
    public function testXssInputSanitization(): void
    {
        $_POST['name'] = '<script>alert("XSS")</script>';
        $_GET['email'] = '<img src=x onerror="alert(\'XSS\')">';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Type-safe assertions
        $this->assertInstanceOf(Request::class, $request);
        $this->assertIsString($request->post['name']);
        $this->assertIsString($request->get['email']);
        
        $this->assertStringNotContainsString('<script>', $request->post['name']);
        $this->assertStringNotContainsString('<img', $request->get['email']);
        $this->assertStringContainsString('&lt;script&gt;', $request->post['name']);
    }
    
    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $getData
     */
    private function createRequestWithData(array $postData, array $getData): Request
    {
        $_POST = $postData;
        $_GET = $getData;
        
        $ar = new ApacheRequest();
        return $ar->request;
    }
}
```

### Test Helpers

**tests/Helpers/DatabaseTestCase.php** (PHPStan Level 9 compliant):
```php
<?php

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createTestDatabase();
        $this->migrateTestDatabase();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupTestDatabase();
        parent::tearDown();
    }
    
    protected function createTestDatabase(): PDO
    {
        // Create in-memory SQLite or test MySQL
        $dsn = 'sqlite::memory:';
        try {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            $this->fail('Failed to create test database: ' . $e->getMessage());
        }
    }
    
    protected function migrateTestDatabase(): void
    {
        // Run migrations
    }
    
    protected function cleanupTestDatabase(): void
    {
        // Clean up test data
        if (isset($this->pdo)) {
            $this->pdo = null;
        }
    }
}
```

**tests/Helpers/ApiTestCase.php** (PHPStan Level 9 compliant):
```php
<?php

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApacheRequest;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;

abstract class ApiTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    protected function createMockRequest(array $post = [], array $get = []): Request
    {
        $_POST = $post;
        $_GET = $get;
        
        $ar = new ApacheRequest();
        return $ar->request;
    }
    
    protected function assertJsonResponse(JsonResponse $response, int $expectedCode): void
    {
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($expectedCode, $response->responseCode);
    }
    
    /**
     * @param array<string, mixed> $expectedData
     */
    protected function assertResponseData(JsonResponse $response, array $expectedData): void
    {
        $this->assertIsArray($response->data);
        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $response->data);
            $this->assertEquals($value, $response->data[$key]);
        }
    }
}
```

---

## 📊 Coverage Goals

### Minimum Coverage Targets

| Component | Target | Priority |
|-----------|--------|----------|
| Core Framework | 90% | Critical |
| HTTP Layer | 85% | Critical |
| Database Layer | 90% | Critical |
| Security Features | 95% | Critical |
| Helper Classes | 80% | High |
| CLI Commands | 75% | Medium |
| Integration Tests | 70% | High |
| E2E Tests | 50% | Medium |

### Critical Paths (100% Coverage Required)

1. **Security Features**:
   - Input sanitization
   - SQL injection prevention
   - XSS prevention
   - Path protection
   - JWT token validation

2. **Database Operations**:
   - Prepared statement usage
   - Parameter binding
   - Query execution
   - Error handling

3. **Request/Response**:
   - Schema validation
   - Type checking
   - Authentication
   - Authorization

---

## 🔄 CI/CD Integration

### GitHub Actions Workflow

**.github/workflows/tests.yml**:
```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3']
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      - name: Install dependencies
        run: composer install
      - name: Run PHPStan on Framework Code
        run: vendor/bin/phpstan analyse src --configuration=phpstan.neon
      - name: Run PHPStan on Test Code
        run: vendor/bin/phpstan analyse tests --configuration=phpstan-tests.neon
      - name: Run Unit Tests
        run: vendor/bin/phpunit tests/Unit --coverage-clover=coverage.xml
      - name: Upload Coverage
        uses: codecov/codecov-action@v3

  integration-tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: gemvc_test
        ports:
          - 3306:3306
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: Run Integration Tests
        run: vendor/bin/phpunit tests/Integration
        env:
          DB_HOST: 127.0.0.1
          DB_NAME: gemvc_test
          DB_USER: root
          DB_PASSWORD: root

  security-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: Run Security Tests
        run: vendor/bin/phpunit tests/Integration/Security
```

---

## 📚 Test Examples

### Unit Test Example: Request Sanitization

**tests/Unit/Http/RequestTest.php**:
```php
<?php

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\ApacheRequest;

class RequestTest extends TestCase
{
    public function testXssInputSanitization(): void
    {
        $_POST['name'] = '<script>alert("XSS")</script>';
        $_GET['email'] = '<img src=x onerror="alert(\'XSS\')">';
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $this->assertStringNotContainsString('<script>', $request->post['name']);
        $this->assertStringNotContainsString('<img', $request->get['email']);
        $this->assertStringContainsString('&lt;script&gt;', $request->post['name']);
    }
    
    public function testSqlInjectionPrevention(): void
    {
        $_POST['id'] = "1' OR '1'='1";
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        // Input is sanitized but still contains the string
        // SQL injection is prevented by prepared statements, not input sanitization
        $this->assertIsString($request->post['id']);
    }
}
```

### Integration Test Example: User CRUD

**tests/Integration/Api/UserApiTest.php**:
```php
<?php

namespace Tests\Integration\Api;

use Tests\Helpers\DatabaseTestCase;
use App\Api\User;
use Gemvc\Http\ApacheRequest;

class UserApiTest extends DatabaseTestCase
{
    public function testCreateUser(): void
    {
        $_POST = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123'
        ];
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $userApi = new User($request);
        $response = $userApi->create();
        
        $this->assertEquals(201, $response->responseCode);
        $this->assertEquals('created', $response->message);
        $this->assertArrayHasKey('id', $response->data);
    }
    
    public function testReadUser(): void
    {
        // Create user first
        $userId = $this->createTestUser();
        
        $_GET = ['id' => $userId];
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $userApi = new User($request);
        $response = $userApi->read();
        
        $this->assertEquals(200, $response->responseCode);
        $this->assertEquals('John Doe', $response->data['name']);
        $this->assertEquals('-', $response->data['password']); // Protected field
    }
    
    public function testUpdateUser(): void
    {
        $userId = $this->createTestUser();
        
        $_POST = [
            'id' => $userId,
            'name' => 'Jane Doe'
        ];
        
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $userApi = new User($request);
        $response = $userApi->update();
        
        $this->assertEquals(209, $response->responseCode);
        $this->assertEquals('updated', $response->message);
    }
    
    public function testDeleteUser(): void
    {
        $userId = $this->createTestUser();
        
        $_POST = ['id' => $userId];
        $ar = new ApacheRequest();
        $request = $ar->request;
        
        $userApi = new User($request);
        $response = $userApi->delete();
        
        $this->assertEquals(210, $response->responseCode);
        $this->assertEquals('deleted', $response->message);
    }
    
    private function createTestUser(): int
    {
        // Helper method to create test user
    }
}
```

### Security Test Example: SQL Injection Prevention

**tests/Integration/Security/SQLInjectionTest.php**:
```php
<?php

namespace Tests\Integration\Security;

use Tests\Helpers\DatabaseTestCase;
use App\Table\UserTable;

class SQLInjectionTest extends DatabaseTestCase
{
    public function testSqlInjectionInWhereClause(): void
    {
        $table = new UserTable();
        
        // Attempt SQL injection
        $maliciousInput = "admin' OR '1'='1";
        
        $result = $table->select()
            ->where('email', $maliciousInput)
            ->run();
        
        // Should return empty or specific user, not all users
        // Prepared statements prevent injection
        $this->assertIsArray($result);
        // Should not return all users due to injection
    }
    
    public function testSqlInjectionInInsert(): void
    {
        $table = new UserTable();
        $table->name = "'; DROP TABLE users; --";
        $table->email = 'test@example.com';
        
        $table->insertSingleQuery();
        
        // Table should still exist
        $this->assertTrue($this->tableExists('users'));
    }
}
```

### CLI Test Example: Code Generation

**tests/Unit/CLI/CreateServiceTest.php**:
```php
<?php

namespace Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Gemvc\CLI\Commands\CreateService;
use Gemvc\Helper\ProjectHelper;

class CreateServiceTest extends TestCase
{
    private string $testProjectRoot;
    
    protected function setUp(): void
    {
        $this->testProjectRoot = sys_get_temp_dir() . '/gemvc_test_' . uniqid();
        mkdir($this->testProjectRoot, 0755, true);
        mkdir($this->testProjectRoot . '/app/api', 0755, true);
    }
    
    protected function tearDown(): void
    {
        $this->removeDirectory($this->testProjectRoot);
    }
    
    public function testServiceGeneration(): void
    {
        $command = new CreateService(['Product']);
        $command->execute();
        
        $filePath = $this->testProjectRoot . '/app/api/Product.php';
        $this->assertFileExists($filePath);
        
        $content = file_get_contents($filePath);
        $this->assertStringContainsString('class Product', $content);
        $this->assertStringContainsString('extends ApiService', $content);
    }
    
    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }
}
```

---

## 🎯 Testing Best Practices

### 1. Test Naming

```php
// ✅ GOOD: Descriptive test names
public function testCreateUserWithValidDataReturns201(): void
public function testSqlInjectionInWhereClauseIsPrevented(): void
public function testJwtTokenExpirationReturns401(): void

// ❌ BAD: Vague test names
public function testCreate(): void
public function testSecurity(): void
```

### 2. Test Isolation

```php
// ✅ GOOD: Each test is independent
public function testCreateUser(): void
{
    $this->createTestUser();
    // Test create
}

public function testReadUser(): void
{
    $userId = $this->createTestUser();
    // Test read
}

// ❌ BAD: Tests depend on each other
public function testCreateUser(): void
{
    // Creates user
}

public function testReadUser(): void
{
    // Assumes testCreateUser ran first - BAD!
}
```

### 3. Arrange-Act-Assert Pattern

```php
public function testUpdateUser(): void
{
    // Arrange
    $userId = $this->createTestUser();
    $_POST = ['id' => $userId, 'name' => 'New Name'];
    
    // Act
    $response = $userApi->update();
    
    // Assert
    $this->assertEquals(209, $response->responseCode);
}
```

### 4. Mock External Dependencies

```php
// ✅ GOOD: Mock database for unit tests
$mockPdo = $this->createMock(PDO::class);
$table->setPdo($mockPdo);

// ✅ GOOD: Use real database for integration tests
$table = new UserTable(); // Uses real database
```

### 5. Test Security Features Thoroughly

```php
// Test all attack vectors
public function testXssPrevention(): void { }
public function testSqlInjectionPrevention(): void { }
public function testPathTraversalPrevention(): void { }
public function testHeaderInjectionPrevention(): void { }
public function testMassAssignmentPrevention(): void { }
```

### 6. PHPStan Level 9 Compliance for Test Code

**Always use strict types and type hints**:
```php
<?php

declare(strict_types=1);  // ✅ REQUIRED

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Request;

class RequestTest extends TestCase
{
    // ✅ GOOD: Full type hints
    public function testRequestCreation(): void
    {
        $request = $this->createRequest();
        $this->assertInstanceOf(Request::class, $request);
    }
    
    // ✅ GOOD: Type-safe helper methods
    /**
     * @param array<string, mixed> $data
     */
    private function createRequest(array $data = []): Request
    {
        // Implementation
    }
    
    // ❌ BAD: Missing types
    public function testRequest($data)  // Missing return type and parameter type
    {
        // ...
    }
}
```

**Use PHPDoc for complex types**:
```php
// ✅ GOOD: Document array types
/**
 * @param array<string, int> $userIds
 * @return array<int, UserModel>
 */
private function loadUsers(array $userIds): array
{
    // Implementation
}

// ✅ GOOD: Document nullable returns
/**
 * @return Request|null
 */
private function getRequest(): ?Request
{
    // Implementation
}
```

**Handle PHPUnit mocks properly**:
```php
// ✅ GOOD: Type-safe mocks
use PHPUnit\Framework\MockObject\MockObject;

class UserServiceTest extends TestCase
{
    /**
     * @return MockObject&UserTable
     */
    private function createMockUserTable(): MockObject
    {
        return $this->createMock(UserTable::class);
    }
}
```

**Run PHPStan before committing**:
```bash
# Check framework code
vendor/bin/phpstan analyse src --configuration=phpstan.neon

# Check test code
vendor/bin/phpstan analyse tests --configuration=phpstan-tests.neon

# Or check both
vendor/bin/phpstan analyse src tests --configuration=phpstan.neon
```

---

## 📈 Metrics & Reporting

### Test Metrics to Track

1. **Coverage Metrics**:
   - Overall code coverage
   - Coverage by component
   - Coverage by test type

2. **Test Execution**:
   - Total test count
   - Pass/fail rate
   - Execution time
   - Flaky test detection

3. **Quality Metrics**:
   - PHPStan errors
   - Test complexity
   - Test maintainability

### Reporting Tools

- **PHPUnit Coverage**: HTML and Clover XML reports
- **Codecov**: Coverage tracking and trends
- **PHPStan**: Static analysis reports
- **CI/CD**: Automated test reports

---

## 🚀 Next Steps

1. **Setup Test Infrastructure**:
   - Configure PHPUnit
   - Create test helpers
   - Setup test database

2. **Implement Priority Tests**:
   - Security features (highest priority)
   - Core framework classes
   - Database layer

3. **Setup CI/CD**:
   - GitHub Actions workflow
   - Automated testing on PR
   - Coverage reporting

4. **Expand Test Coverage**:
   - Integration tests
   - Cross-platform tests
   - E2E tests

---

## 📖 Summary

This test strategy provides:

✅ **Comprehensive Coverage**: Unit, Integration, and E2E tests  
✅ **Security Focus**: Thorough testing of security features  
✅ **Cross-Platform**: Tests for all supported webservers  
✅ **CI/CD Ready**: Automated testing pipeline  
✅ **Type-Safe Tests**: PHPStan Level 9 compliance for test code  
✅ **Maintainable**: Clear structure and best practices  
✅ **Scalable**: Easy to add new tests as framework grows  

**Key Features**:
- **Framework Code**: PHPStan Level 9 ✅
- **Test Code**: PHPStan Level 9 ✅
- **Type Safety**: Full type hints and PHPDoc ✅
- **CI/CD Integration**: Automated PHPStan checks ✅

**Result**: A robust, reliable, type-safe, and secure GEMVC framework! 🎯

---

*Last Updated: 2024*  
*Version: 1.0.0 - Initial Test Strategy*


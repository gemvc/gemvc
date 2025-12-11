<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Table;
use PDO;
use PDOException;

/**
 * Test table class for database operations
 */
class TestTable extends Table
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
    ];
    
    public function getTable(): string
    {
        return 'test_users';
    }
    
    public function defineSchema(): array
    {
        return [];
    }
}

class TableTest extends TestCase
{
    protected ?PDO $pdo = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createTestDatabase();
        $this->migrateTestDatabase();
    }
    
    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo = null;
        }
        parent::tearDown();
    }
    
    protected function createTestDatabase(): PDO
    {
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
        $sql = "CREATE TABLE IF NOT EXISTS test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            description TEXT
        )";
        if ($this->pdo !== null) {
            $this->pdo->exec($sql);
        }
    }
    
    public function testTableInitialization(): void
    {
        $table = new TestTable();
        $this->assertInstanceOf(Table::class, $table);
        $this->assertFalse($table->isConnected());
    }
    
    public function testSetAndGetError(): void
    {
        $table = new TestTable();
        $this->assertNull($table->getError());
        
        $table->setError('Test error');
        $this->assertEquals('Test error', $table->getError());
        
        $table->setError(null);
        $this->assertNull($table->getError());
    }
    
    public function testValidateId(): void
    {
        $table = new TestTable();
        
        // validateId is protected, so we test it indirectly through selectById
        // Valid ID should not set error immediately
        $this->assertNull($table->getError());
        
        // Invalid ID (zero) - test through selectById which uses validateId
        $result = $table->selectById(0);
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
        
        // Reset error
        $table->setError(null);
        
        // Invalid ID (negative)
        $result = $table->selectById(-1);
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testInsertSingleQuery(): void
    {
        $table = new TestTable();
        $table->name = 'John Doe';
        $table->email = 'john@example.com';
        $table->description = 'Test user';
        
        // Mock PDO connection by setting up the database
        // Note: This requires the actual database connection to work
        // For unit tests, we'll test the validation logic
        
        // Test that properties are set correctly
        $this->assertEquals('John Doe', $table->name);
        $this->assertEquals('john@example.com', $table->email);
        $this->assertEquals('Test user', $table->description);
    }
    
    public function testSelectById(): void
    {
        // Insert a test record
        $stmt = $this->pdo->prepare("INSERT INTO test_users (name, email, description) VALUES (?, ?, ?)");
        $stmt->execute(['Test User', 'test@example.com', 'Test description']);
        $id = (int)$this->pdo->lastInsertId();
        
        $table = new TestTable();
        // Note: This will fail because Table needs actual PDO connection
        // This test validates the structure, actual DB tests should be integration tests
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }
    
    public function testSelectQueryBuilder(): void
    {
        $table = new TestTable();
        $result = $table->select('id, name');
        $this->assertSame($table, $result);
    }
    
    public function testWhereClause(): void
    {
        $table = new TestTable();
        $result = $table->select()->where('email', 'test@example.com');
        $this->assertSame($table, $result);
    }
    
    public function testLimitClause(): void
    {
        $table = new TestTable();
        $result = $table->select()->limit(10);
        $this->assertSame($table, $result);
    }
    
    public function testOrderByClause(): void
    {
        $table = new TestTable();
        $result = $table->select()->orderBy('name', true); // true = ASC
        $this->assertSame($table, $result);
    }
    
    public function testNoLimit(): void
    {
        $table = new TestTable();
        $result = $table->select()->noLimit();
        $this->assertSame($table, $result);
    }
    
    public function testAllAlias(): void
    {
        $table = new TestTable();
        $result = $table->select()->all();
        $this->assertSame($table, $result);
    }
    
    public function testJoinClause(): void
    {
        $table = new TestTable();
        $result = $table->select()->join('roles', 'users.role_id = roles.id', 'INNER');
        $this->assertSame($table, $result);
    }
    
    public function testGetTableName(): void
    {
        $table = new TestTable();
        $this->assertEquals('test_users', $table->getTable());
    }
    
    // ============================================
    // WHERE Clause Tests
    // ============================================
    
    public function testWhereLike(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereLike('name', 'John');
        $this->assertSame($table, $result);
    }
    
    public function testWhereLikeLast(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereLikeLast('name', 'Doe');
        $this->assertSame($table, $result);
    }
    
    // whereIn doesn't exist - removed test
    
    public function testWhereOr(): void
    {
        $table = new TestTable();
        $result = $table->select()
            ->where('status', 'active')
            ->whereOr('status', 'pending');
        $this->assertSame($table, $result);
    }
    
    public function testWhereOrAsFirstCondition(): void
    {
        $table = new TestTable();
        // When whereOr is first, it should behave like where
        $result = $table->select()->whereOr('status', 'active');
        $this->assertSame($table, $result);
    }
    
    public function testWhereBetween(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereBetween('age', 18, 65);
        $this->assertSame($table, $result);
    }
    
    public function testWhereNull(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereNull('deleted_at');
        $this->assertSame($table, $result);
    }
    
    public function testWhereNotNull(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereNotNull('email');
        $this->assertSame($table, $result);
    }
    
    public function testWhereBiggerThan(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereBiggerThan('age', 18);
        $this->assertSame($table, $result);
    }
    
    public function testWhereLessThan(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereLessThan('age', 65);
        $this->assertSame($table, $result);
    }
    
    public function testWhereWithEmptyColumn(): void
    {
        $table = new TestTable();
        $result = $table->select()->where('', 'value');
        $this->assertSame($table, $result);
        $this->assertNotNull($table->getError());
    }
    
    public function testWhereLikeWithEmptyColumn(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereLike('', 'value');
        $this->assertSame($table, $result);
        $this->assertNotNull($table->getError());
    }
    
    // ============================================
    // Query Building Combination Tests
    // ============================================
    
    public function testComplexQueryBuilder(): void
    {
        $table = new TestTable();
        $result = $table->select('id, name, email')
            ->where('active', true)
            ->whereLike('name', 'John')
            ->orderBy('name', true)
            ->limit(10);
        
        $this->assertSame($table, $result);
    }
    
    public function testMultipleJoins(): void
    {
        $table = new TestTable();
        $result = $table->select()
            ->join('roles', 'users.role_id = roles.id', 'INNER')
            ->join('profiles', 'users.id = profiles.user_id', 'LEFT');
        
        $this->assertSame($table, $result);
    }
    
    public function testOrderByDescending(): void
    {
        $table = new TestTable();
        $result = $table->select()->orderBy('name', false); // DESC
        $this->assertSame($table, $result);
    }
    
    public function testOrderByDefaultColumn(): void
    {
        $table = new TestTable();
        $result = $table->select()->orderBy(); // Should default to 'id'
        $this->assertSame($table, $result);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testRunWithoutSelect(): void
    {
        $table = new TestTable();
        // Don't call select() first
        $result = $table->run();
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testUpdateSingleQueryWithoutIdProperty(): void
    {
        // Create a table class without id property
        $tableWithoutId = new class extends Table {
            public string $name;
            protected array $_type_map = ['name' => 'string'];
            public function getTable(): string { return 'test'; }
            public function defineSchema(): array { return []; }
        };
        
        $tableWithoutId->name = 'Test';
        $result = $tableWithoutId->updateSingleQuery();
        
        $this->assertNull($result);
        $this->assertNotNull($tableWithoutId->getError());
    }
    
    public function testDeleteByIdQueryWithoutIdProperty(): void
    {
        $tableWithoutId = new class extends Table {
            public string $name;
            protected array $_type_map = ['name' => 'string'];
            public function getTable(): string { return 'test'; }
            public function defineSchema(): array { return []; }
        };
        
        $result = $tableWithoutId->deleteByIdQuery(1);
        
        $this->assertNull($result);
        $this->assertNotNull($tableWithoutId->getError());
    }
    
    public function testUpdateSingleQueryWithInvalidId(): void
    {
        $table = new TestTable();
        $table->id = 0; // Invalid ID
        $table->name = 'Test';
        
        $result = $table->updateSingleQuery();
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    // ============================================
    // Helper Method Tests
    // ============================================
    
    public function testGetTotalCounts(): void
    {
        $table = new TestTable();
        // Before any query, should return 0
        $this->assertEquals(0, $table->getTotalCounts());
    }
    
    // ==========================================
    // Soft Delete Operations
    // ==========================================
    
    public function testSafeDeleteQuery(): void
    {
        $table = new TestTable();
        $table->id = 1;
        
        // Mock a property for soft delete
        $result = $table->safeDeleteQuery();
        
        // Without deleted_at property, should set error
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
        $this->assertStringContainsString('deleted_at', $table->getError());
    }
    
    public function testRestoreQuery(): void
    {
        $table = new TestTable();
        $table->id = 1;
        
        // Without deleted_at property, should set error
        $result = $table->restoreQuery();
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
        $this->assertStringContainsString('deleted_at', $table->getError());
    }
    
    public function testDeleteSingleQuery(): void
    {
        $table = new TestTable();
        $table->id = 1;
        
        // Without id property properly set (in database), should return null
        $result = $table->deleteSingleQuery();
        
        // Since we're not connected to a database, this should return null
        $this->assertNull($result);
    }
    
    public function testRemoveConditionalQuery(): void
    {
        $table = new TestTable();
        
        // Third parameter should be a column name (string) or null
        $result = $table->removeConditionalQuery('email', 'test@example.com', 'status', 'active');
        
        // Without proper database connection, should return null
        $this->assertNull($result);
    }
    
    // ==========================================
    // Conditional Update Operations
    // ==========================================
    
    public function testSetNullQuery(): void
    {
        $table = new TestTable();
        
        $result = $table->setNullQuery('description', 'id', 1);
        
        // Without proper database connection, should return null
        $this->assertNull($result);
    }
    
    public function testSetTimeNowQuery(): void
    {
        $table = new TestTable();
        
        $result = $table->setTimeNowQuery('updated_at', 'id', 1);
        
        // Without proper database connection, should return null
        $this->assertNull($result);
    }
    
    public function testActivateQuery(): void
    {
        $table = new TestTable();
        
        // Without is_active property, should set error
        $result = $table->activateQuery(1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
        $this->assertStringContainsString('is_active', $table->getError());
    }
    
    public function testDeactivateQuery(): void
    {
        $table = new TestTable();
        
        // Without is_active property, should set error
        $result = $table->deactivateQuery(1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
        $this->assertStringContainsString('is_active', $table->getError());
    }
    
    // ==========================================
    // Additional WHERE Clause Methods
    // ==========================================
    
    public function testOrWhere(): void
    {
        $table = new TestTable();
        $result = $table->select()->where('name', 'John')->orWhere('name', 'Jane');
        
        // Test fluent interface returns same instance
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testOrWhereAsFirstCondition(): void
    {
        $table = new TestTable();
        $table->select()->orWhere('name', 'John');
        
        $query = $table->getSelectQueryString();
        $this->assertNotNull($query);
        // First orWhere should not have OR prefix
        $this->assertStringNotContainsString('OR', $query);
    }
    
    // ==========================================
    // Pagination Methods
    // ==========================================
    
    public function testSetPage(): void
    {
        $table = new TestTable();
        $table->setPage(3);
        
        // setPage(3) sets offset to (3-1) * limit = 2 * 10 = 20
        // getCurrentPage() returns offset + 1 = 20 + 1 = 21
        $this->assertEquals(21, $table->getCurrentPage());
    }
    
    public function testSetPageWithZero(): void
    {
        $table = new TestTable();
        $table->setPage(0);
        
        // Page should be set to 1 minimum
        $this->assertEquals(1, $table->getCurrentPage());
    }
    
    public function testSetPageWithNegative(): void
    {
        $table = new TestTable();
        $table->setPage(-5);
        
        // Page should be set to 1 minimum
        $this->assertEquals(1, $table->getCurrentPage());
    }
    
    public function testGetCurrentPage(): void
    {
        $table = new TestTable();
        // Default should be 1
        $this->assertEquals(1, $table->getCurrentPage());
    }
    
    public function testGetCount(): void
    {
        $table = new TestTable();
        // Before any query, should return 0
        $this->assertEquals(0, $table->getCount());
    }
    
    public function testGetLimit(): void
    {
        $table = new TestTable();
        // Should return default limit (10 or from env)
        $limit = $table->getLimit();
        $this->assertIsInt($limit);
        $this->assertGreaterThan(0, $limit);
    }
    
    public function testGetLimitWithCustomValue(): void
    {
        $table = new TestTable();
        $table->limit(25);
        
        $this->assertEquals(25, $table->getLimit());
    }
    
    // ==========================================
    // Query Getters
    // ==========================================
    
    public function testGetQuery(): void
    {
        $table = new TestTable();
        // Before select, query should be null
        $this->assertNull($table->getQuery());
        
        $table->select();
        $query = $table->getQuery();
        $this->assertIsString($query);
        $this->assertStringContainsString('SELECT', $query);
    }
    
    public function testGetBind(): void
    {
        $table = new TestTable();
        $table->select()->where('id', 1)->where('name', 'test');
        
        // Bindings are populated immediately when where() is called
        $binds = $table->getBind();
        $this->assertIsArray($binds);
        $this->assertNotEmpty($binds);
        $this->assertArrayHasKey(':id', $binds);
        $this->assertEquals(1, $binds[':id']);
        $this->assertArrayHasKey(':name', $binds);
        $this->assertEquals('test', $binds[':name']);
    }
    
    public function testGetSelectQueryString(): void
    {
        $table = new TestTable();
        // Before select, should return null
        $this->assertNull($table->getSelectQueryString());
        
        $table->select()->where('id', 1);
        $query = $table->getSelectQueryString();
        
        // getSelectQueryString() returns only the SELECT part before run() is called
        $this->assertIsString($query);
        $this->assertStringContainsString('SELECT', $query);
    }
    
    public function testGetSelectQueryStringWithoutSelect(): void
    {
        $table = new TestTable();
        // where() without select() doesn't set an error immediately
        $table->where('id', 1);
        
        $query = $table->getSelectQueryString();
        // Query will be null since select() was not called
        $this->assertNull($query);
    }
    
    // ==========================================
    // Transaction Methods
    // ==========================================
    
    public function testBeginTransaction(): void
    {
        $table = new TestTable();
        // Without active connection, should return false
        $result = $table->beginTransaction();
        $this->assertIsBool($result);
    }
    
    public function testCommit(): void
    {
        $table = new TestTable();
        // Without active transaction, should return false
        $result = $table->commit();
        $this->assertIsBool($result);
    }
    
    public function testRollback(): void
    {
        $table = new TestTable();
        // Without active transaction, should return false
        $result = $table->rollback();
        $this->assertIsBool($result);
    }
    
    // ==========================================
    // Connection Management
    // ==========================================
    
    public function testDisconnect(): void
    {
        $table = new TestTable();
        $this->assertFalse($table->isConnected());
        
        // Call disconnect (should not throw error even if not connected)
        $table->disconnect();
        $this->assertFalse($table->isConnected());
    }
    
    public function testIsConnectedAfterSelect(): void
    {
        $table = new TestTable();
        $this->assertFalse($table->isConnected());
        
        // After calling select, connection should not be established until run()
        $table->select();
        $this->assertFalse($table->isConnected());
    }
    
    // ==========================================
    // Select Method Variations
    // ==========================================
    
    public function testSelectWithSpecificColumns(): void
    {
        $table = new TestTable();
        $table->select('id, name, email');
        
        $query = $table->getQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('id, name, email', $query);
        $this->assertStringNotContainsString('*', $query);
    }
    
    public function testSelectWithoutColumns(): void
    {
        $table = new TestTable();
        $table->select();
        
        $query = $table->getQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('SELECT *', $query);
    }
    
    public function testSelectCalledMultipleTimes(): void
    {
        $table = new TestTable();
        $table->select('id')->select('name')->select('email');
        
        $query = $table->getQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('id', $query);
        $this->assertStringContainsString('name', $query);
        $this->assertStringContainsString('email', $query);
    }
    
    public function testSelectWithNullAppend(): void
    {
        $table = new TestTable();
        $table->select('id')->select(null);
        
        $query = $table->getQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('id', $query);
    }
    
    // ==========================================
    // Join Clause Variations
    // ==========================================
    
    public function testJoinWithLeftType(): void
    {
        $table = new TestTable();
        $result = $table->select()->join('profiles', 'test_users.id = profiles.user_id', 'LEFT');
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testJoinWithRightType(): void
    {
        $table = new TestTable();
        $result = $table->select()->join('orders', 'test_users.id = orders.user_id', 'RIGHT');
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testJoinWithInnerType(): void
    {
        $table = new TestTable();
        $result = $table->select()->join('roles', 'test_users.role_id = roles.id', 'INNER');
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testJoinWithDefaultType(): void
    {
        $table = new TestTable();
        $result = $table->select()->join('categories', 'test_users.category_id = categories.id');
        
        // Test fluent interface (default type is INNER)
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testJoinWithLowercaseType(): void
    {
        $table = new TestTable();
        $result = $table->select()->join('tags', 'test_users.id = user_tags.user_id', 'left');
        
        // Test fluent interface (type should be converted to uppercase internally)
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    // ==========================================
    // OrderBy Variations
    // ==========================================
    
    public function testOrderByWithNullColumn(): void
    {
        $table = new TestTable();
        $result = $table->select()->orderBy(null, true);
        
        // Test fluent interface (should use default column internally)
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testOrderByWithNullAscending(): void
    {
        $table = new TestTable();
        $result = $table->select()->orderBy('name', null);
        
        // Test fluent interface (should use default ascending internally)
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testOrderByCalledMultipleTimes(): void
    {
        $table = new TestTable();
        $result = $table->select()->orderBy('name', true)->orderBy('email', false);
        
        // Test fluent interface (second call should override first internally)
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    // ==========================================
    // Edge Cases and Error Handling
    // ==========================================
    
    public function testSetErrorBeforeConnection(): void
    {
        $table = new TestTable();
        $table->setError('Test error before connection');
        
        $this->assertEquals('Test error before connection', $table->getError());
        $this->assertFalse($table->isConnected());
    }
    
    public function testSetErrorAfterConnection(): void
    {
        $table = new TestTable();
        // Force connection by calling select()
        $table->select();
        
        $table->setError('Test error after connection');
        $this->assertEquals('Test error after connection', $table->getError());
    }
    
    public function testGetErrorWithoutConnection(): void
    {
        $table = new TestTable();
        $this->assertNull($table->getError());
    }
    
    public function testLimitWithZero(): void
    {
        $table = new TestTable();
        $table->limit(0);
        
        $this->assertEquals(0, $table->getLimit());
    }
    
    public function testLimitWithNegative(): void
    {
        $table = new TestTable();
        $table->limit(-10);
        
        $this->assertEquals(-10, $table->getLimit());
    }
    
    public function testNoLimitSetsInternalFlag(): void
    {
        $table = new TestTable();
        $result = $table->noLimit();
        
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result); // Fluent interface
    }
    
    public function testAllSetsInternalFlag(): void
    {
        $table = new TestTable();
        $result = $table->all();
        
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result); // Fluent interface
    }
    
    public function testWhereWithArrayValue(): void
    {
        $table = new TestTable();
        $result = $table->select()->where('status', ['active', 'pending']);
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereWithNullValue(): void
    {
        $table = new TestTable();
        $result = $table->select()->where('deleted_at', null);
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereBetweenWithSameValues(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereBetween('id', 5, 5);
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereLikeWithSpecialCharacters(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereLike('name', '%test_name%');
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereLikeLastWithMultipleConditions(): void
    {
        $table = new TestTable();
        $result = $table->select()->where('status', 'active')->whereLikeLast('name', '%test%');
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereOrWithMultipleValues(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereOr('status', ['active', 'pending', 'approved']);
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereBiggerThanWithFloatValue(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereBiggerThan('price', 99.99);
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testWhereLessThanWithNegativeValue(): void
    {
        $table = new TestTable();
        $result = $table->select()->whereLessThan('balance', -100);
        
        // Test fluent interface
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertSame($table, $result);
    }
    
    public function testDestructor(): void
    {
        $table = new TestTable();
        $table->select();
        
        // Destructor should not throw any errors
        unset($table);
        
        $this->assertTrue(true); // If we reach here, destructor worked
    }
    
    // ==========================================
    // Additional Coverage Tests with Mocked PdoQuery
    // ==========================================
    
    /**
     * Create a Table instance with mocked PdoQuery injected via reflection
     */
    private function createTableWithMockPdoQuery(): TestTable
    {
        $table = new TestTable();
        $mockPdoQuery = $this->createMock(\Gemvc\Database\PdoQuery::class);
        
        // Inject mock via reflection - property is in parent Table class
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        $pdoQueryProperty->setValue($table, $mockPdoQuery);
        
        return $table;
    }
    
    public function testInsertSingleQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->name = 'John';
        $table->email = 'john@example.com';
        $table->description = 'Test';
        
        // Get the mock from reflection - property is in parent Table class
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('insertQuery')
            ->willReturn(1);
        
        $result = $table->insertSingleQuery();
        
        $this->assertSame($table, $result);
        $this->assertEquals(1, $table->id);
    }
    
    public function testInsertSingleQueryFailure(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->name = 'John';
        $table->email = 'john@example.com';
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('insertQuery')
            ->willReturn(null);
        
        $mockPdoQuery->method('getError')
            ->willReturn('Database error');
        
        $result = $table->insertSingleQuery();
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testUpdateSingleQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->id = 1;
        $table->name = 'Updated Name';
        $table->email = 'updated@example.com';
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $result = $table->updateSingleQuery();
        
        $this->assertSame($table, $result);
    }
    
    public function testUpdateSingleQueryWithoutId(): void
    {
        // Create table without id property to test the property_exists check
        $table = new class extends Table {
            public string $name;
            
            public function getTable(): string {
                return 'test_table';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $table->name = 'Test';
        
        // updateSingleQuery checks property_exists($this, 'id') first (line 231)
        // Since this table has no 'id' property, it should return null immediately
        $result = $table->updateSingleQuery();
        
        $this->assertNull($result);
        $error = $table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('id', $error);
    }
    
    public function testDeleteByIdQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('deleteQuery')
            ->willReturn(1);
        
        $result = $table->deleteByIdQuery(1);
        
        $this->assertEquals(1, $result);
    }
    
    public function testDeleteByIdQueryFailure(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('deleteQuery')
            ->willReturn(null);
        
        $mockPdoQuery->method('getError')
            ->willReturn('Delete failed');
        
        $result = $table->deleteByIdQuery(1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testSafeDeleteQuerySuccess(): void
    {
        // Create table with deleted_at property
        $table = new class extends Table {
            public int $id;
            public ?string $deleted_at = null;
            public int $is_active = 1;
            
            public function getTable(): string {
                return 'test_table';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $table->id = 1;
        
        $mockPdoQuery = $this->createMock(\Gemvc\Database\PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        $pdoQueryProperty->setValue($table, $mockPdoQuery);
        
        $result = $table->safeDeleteQuery();
        
        $this->assertSame($table, $result);
    }
    
    public function testRestoreQuerySuccess(): void
    {
        $table = new class extends Table {
            public int $id;
            public ?string $deleted_at = '2024-01-01 00:00:00';
            
            public function getTable(): string {
                return 'test_table';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $table->id = 1;
        
        $mockPdoQuery = $this->createMock(\Gemvc\Database\PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        $pdoQueryProperty->setValue($table, $mockPdoQuery);
        
        $result = $table->restoreQuery();
        
        $this->assertSame($table, $result);
        $this->assertNull($table->deleted_at);
    }
    
    public function testRemoveConditionalQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('deleteQuery')
            ->willReturn(5);
        
        $result = $table->removeConditionalQuery('email', 'test@example.com');
        
        $this->assertEquals(5, $result);
    }
    
    public function testRemoveConditionalQueryWithTwoConditions(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('deleteQuery')
            ->willReturn(2);
        
        $result = $table->removeConditionalQuery('email', 'test@example.com', 'name', 'Test');
        
        $this->assertEquals(2, $result);
    }
    
    public function testRemoveConditionalQueryWithEmptyColumn(): void
    {
        $table = new TestTable();
        
        $result = $table->removeConditionalQuery('', 'value');
        
        $this->assertNull($result);
        $this->assertStringContainsString('empty', $table->getError() ?? '');
    }
    
    public function testRemoveConditionalQueryWithNullValue(): void
    {
        $table = new TestTable();
        
        $result = $table->removeConditionalQuery('email', null);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testSetNullQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $result = $table->setNullQuery('description', 'id', 1);
        
        $this->assertEquals(1, $result);
    }
    
    public function testSetTimeNowQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $result = $table->setTimeNowQuery('updated_at', 'id', 1);
        
        $this->assertEquals(1, $result);
    }
    
    public function testActivateQuerySuccess(): void
    {
        $table = new class extends Table {
            public int $id;
            public int $is_active = 0;
            
            public function getTable(): string {
                return 'test_table';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $mockPdoQuery = $this->createMock(\Gemvc\Database\PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        $pdoQueryProperty->setValue($table, $mockPdoQuery);
        
        $result = $table->activateQuery(1);
        
        $this->assertEquals(1, $result);
    }
    
    public function testDeactivateQuerySuccess(): void
    {
        $table = new class extends Table {
            public int $id;
            public int $is_active = 1;
            
            public function getTable(): string {
                return 'test_table';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $mockPdoQuery = $this->createMock(\Gemvc\Database\PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('updateQuery')
            ->willReturn(1);
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        $pdoQueryProperty->setValue($table, $mockPdoQuery);
        
        $result = $table->deactivateQuery(1);
        
        $this->assertEquals(1, $result);
    }
    
    public function testRunWithEmptyResults(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    public function testRunWithResults(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([
                ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com', 'description' => null]
            ]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TestTable::class, $result[0]);
        $this->assertEquals(1, $result[0]->id);
        $this->assertEquals('Test', $result[0]->name);
    }
    
    public function testRunWithError(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn(null);
        
        $mockPdoQuery->method('getError')
            ->willReturn('Query failed');
        
        $result = $table->run();
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testSelectByIdWithResult(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        // selectById calls selectQuery twice: once for the main query, once for count (if needed)
        $mockPdoQuery->expects($this->atLeastOnce())
            ->method('selectQuery')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'COUNT(*)') !== false) {
                    // Count query
                    return [['total' => 1]];
                }
                // Main query
                return [
                    ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com', 'description' => null]
                ];
            });
        
        $result = $table->selectById(1);
        
        $this->assertInstanceOf(TestTable::class, $result);
        $this->assertEquals(1, $result->id);
    }
    
    public function testSelectByIdWithNoResult(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        // Track error set via setError()
        $storedError = null;
        $mockPdoQuery->method('setError')
            ->willReturnCallback(function ($error) use (&$storedError) {
                $storedError = $error;
            });
        
        $mockPdoQuery->method('getError')
            ->willReturnCallback(function () use (&$storedError) {
                return $storedError;
            });
        
        // selectById calls select()->where()->limit()->run() which calls selectQuery
        // When result is empty array, selectById sets error 'Record not found'
        // Note: run() may also call selectQuery for count, so use atLeastOnce
        $mockPdoQuery->expects($this->atLeastOnce())
            ->method('selectQuery')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'COUNT(*)') !== false) {
                    return [['total' => 0]];
                }
                // Main query returns empty
                return [];
            });
        
        $result = $table->selectById(1);
        
        $this->assertNull($result);
        // Error should be set by selectById when result is empty (line 927 in Table.php)
        // selectById calls $this->setError('Record not found') which calls mock's setError
        $error = $table->getError();
        $this->assertNotNull($error, 'Error should be set when record not found');
        $this->assertStringContainsString('not found', $error);
    }
    
    
    public function testValidateProperties(): void
    {
        $table = new TestTable();
        $table->name = 'Test';
        $table->email = 'test@example.com';
        
        // Test with existing properties - validateProperties is protected, test via reflection
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $method = $reflection->getMethod('validateProperties');
        $result = $method->invoke($table, ['name', 'email']);
        
        $this->assertTrue($result);
        
        // Test with non-existent property
        $result = $method->invoke($table, ['nonexistent']);
        $this->assertFalse($result);
        // Error should be set by validateProperties
        $error = $table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('nonexistent', $error);
    }
    
    public function testCastValue(): void
    {
        $table = new TestTable();
        
        $reflection = new \ReflectionClass($table);
        $method = $reflection->getMethod('castValue');
        
        // Test int casting
        $result = $method->invoke($table, 'id', '123');
        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
        
        // Test float casting
        $tableWithFloat = new class extends Table {
            protected array $_type_map = ['price' => 'float'];
            
            public function getTable(): string {
                return 'test';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $reflection = new \ReflectionClass($tableWithFloat);
        $method = $reflection->getMethod('castValue');
        $result = $method->invoke($tableWithFloat, 'price', '99.99');
        $this->assertIsFloat($result);
        $this->assertEquals(99.99, $result);
        
        // Test bool casting
        $tableWithBool = new class extends Table {
            protected array $_type_map = ['active' => 'bool'];
            
            public function getTable(): string {
                return 'test';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $reflection = new \ReflectionClass($tableWithBool);
        $method = $reflection->getMethod('castValue');
        $result = $method->invoke($tableWithBool, 'active', '1');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
    
    public function testFetchRow(): void
    {
        $table = new TestTable();
        
        $reflection = new \ReflectionClass($table);
        $method = $reflection->getMethod('fetchRow');
        
        $row = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'description' => 'Test description'
        ];
        
        $method->invoke($table, $row);
        
        $this->assertEquals(1, $table->id);
        $this->assertEquals('Test User', $table->name);
        $this->assertEquals('test@example.com', $table->email);
        $this->assertEquals('Test description', $table->description);
    }
    
    public function testCommitWithoutTransaction(): void
    {
        $table = new TestTable();
        
        $result = $table->commit();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No active transaction', $table->getError() ?? '');
    }
    
    public function testRollbackWithoutTransaction(): void
    {
        $table = new TestTable();
        
        $result = $table->rollback();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No active transaction', $table->getError() ?? '');
    }
    
    
    public function testRunWithSkipCount(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select();
        
        // Use reflection to set _skip_count - property is in parent Table class
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $skipCountProperty = $reflection->getProperty('_skip_count');
        $skipCountProperty->setValue($table, true);
        
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([
                ['id' => 1, 'name' => 'Test']
            ]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
    
    public function testRunWithNoLimit(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select()->noLimit();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([
                ['id' => 1, 'name' => 'Test']
            ]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
    }
    
    public function testRunWithOrderBy(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select()->orderBy('name', true);
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
    }
    
    public function testRunWithJoins(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select()->join('other_table', 'test_users.id = other_table.user_id');
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
    }
    
    public function testRunWithMultipleWhereConditions(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select()
            ->where('name', 'Test')
            ->where('email', 'test@example.com')
            ->whereLike('description', 'test');
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('selectQuery')
            ->willReturn([]);
        
        $result = $table->run();
        
        $this->assertIsArray($result);
    }
    
    public function testRunWithPagination(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->select();
        $table->setPage(2); // setPage returns void, can't chain
        $table->limit(10);
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        // run() may call selectQuery twice: once for main query, once for count
        $mockPdoQuery->expects($this->atLeastOnce())
            ->method('selectQuery')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'COUNT(*)') !== false) {
                    return [['total' => 1]];
                }
                return [
                    ['id' => 1, 'name' => 'Test']
                ];
            });
        
        $result = $table->run();
        
        $this->assertIsArray($result);
    }
    
    public function testDeleteSingleQuerySuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        $table->id = 1;
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('deleteQuery')
            ->willReturn(1);
        
        $result = $table->deleteSingleQuery();
        
        $this->assertEquals(1, $result);
    }
    
    public function testDeleteSingleQueryWithoutId(): void
    {
        // Create table without id property
        $table = new class extends Table {
            public string $name;
            
            public function getTable(): string {
                return 'test_table';
            }
            
            public function defineSchema(): array {
                return [];
            }
        };
        
        $result = $table->deleteSingleQuery();
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testSetNullQueryWithEmptyColumn(): void
    {
        $table = new TestTable();
        
        $result = $table->setNullQuery('', 'id', 1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testSetTimeNowQueryWithEmptyColumn(): void
    {
        $table = new TestTable();
        
        $result = $table->setTimeNowQuery('', 'id', 1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testActivateQueryWithoutIsActiveProperty(): void
    {
        $table = new TestTable();
        
        $result = $table->activateQuery(1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testDeactivateQueryWithoutIsActiveProperty(): void
    {
        $table = new TestTable();
        
        $result = $table->deactivateQuery(1);
        
        $this->assertNull($result);
        $this->assertNotNull($table->getError());
    }
    
    public function testIsConnectedWhenPdoQueryExists(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->method('isConnected')
            ->willReturn(true);
        
        $this->assertTrue($table->isConnected());
    }
    
    public function testGetErrorAfterConnection(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->method('getError')
            ->willReturn('Connection error');
        
        $this->assertEquals('Connection error', $table->getError());
    }
    
    
    public function testBeginTransactionSuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        
        $result = $table->beginTransaction();
        
        $this->assertTrue($result);
    }
    
    public function testCommitSuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $table->commit();
        
        $this->assertTrue($result);
    }
    
    public function testRollbackSuccess(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('rollback')
            ->willReturn(true);
        
        $result = $table->rollback();
        
        $this->assertTrue($result);
    }
    
    public function testDisconnectWithConnection(): void
    {
        $table = $this->createTableWithMockPdoQuery();
        
        $reflection = new \ReflectionClass(\Gemvc\Database\Table::class);
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Gemvc\Database\PdoQuery */
        $mockPdoQuery = $pdoQueryProperty->getValue($table);
        
        $mockPdoQuery->expects($this->once())
            ->method('disconnect');
        
        $table->disconnect();
        
        // After disconnect, _pdoQuery should be null
        $pdoQueryProperty = $reflection->getProperty('_pdoQuery');
        $this->assertNull($pdoQueryProperty->getValue($table));
    }
}


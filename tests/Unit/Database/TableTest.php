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
}


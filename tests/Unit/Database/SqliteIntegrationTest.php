<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\TableGenerator;
use Gemvc\Database\SchemaGenerator;
use Gemvc\Database\Schema;
use PDO;

/**
 * Test object mirroring a typical Table class, used to exercise TableGenerator/
 * SchemaGenerator end-to-end against a real SQLite connection (not mocks).
 */
class SqliteIntegrationTestUser
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $bio;
    public bool $isActive;

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'bio' => 'string',
        'isActive' => 'bool',
    ];

    public function getTable(): string
    {
        return 'integration_users';
    }

    /**
     * @return array<\Gemvc\Database\SchemaConstraint>
     */
    public function defineSchema(): array
    {
        return [
            Schema::unique('email'),
            Schema::index('name'),
        ];
    }
}

/**
 * End-to-end test exercising TableGenerator + SchemaGenerator against a real
 * SQLite in-memory database (not PDO mocks) - the strongest signal that the
 * dialect abstraction produces genuinely valid, runnable SQL for a non-MySQL
 * engine, not just correctly-shaped strings.
 */
class SqliteIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testCreateTableFromObject(): void
    {
        $generator = new TableGenerator($this->pdo);
        $user = new SqliteIntegrationTestUser();

        $this->assertTrue($generator->createTableFromObject($user), $generator->getError());

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='integration_users'");
        $this->assertNotFalse($stmt);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        // Table is actually usable for real inserts/selects, not just structurally present.
        $this->pdo->exec("INSERT INTO integration_users (name, email, bio, isActive) VALUES ('Alice', 'alice@example.com', NULL, 1)");
        $row = $this->pdo->query('SELECT * FROM integration_users')->fetch();
        $this->assertSame('Alice', $row['name']);
        $this->assertSame('alice@example.com', $row['email']);
    }

    public function testUpdateTableAddsNewColumn(): void
    {
        $generator = new TableGenerator($this->pdo);
        $user = new SqliteIntegrationTestUser();
        $this->assertTrue($generator->createTableFromObject($user), $generator->getError());

        $extended = new class extends SqliteIntegrationTestUser {
            public string $phone = '';

            /** @var array<string, string> */
            protected array $_type_map = [
                'id' => 'int',
                'name' => 'string',
                'email' => 'string',
                'bio' => 'string',
                'isActive' => 'bool',
                'phone' => 'string',
            ];
        };

        $this->assertTrue($generator->updateTable($extended), $generator->getError());

        $stmt = $this->pdo->query("PRAGMA table_info(integration_users)");
        $columns = array_column($stmt->fetchAll(), 'name');
        $this->assertContains('phone', $columns);
    }

    public function testUpdateTableRemovesExtraColumnWhenForced(): void
    {
        $generator = new TableGenerator($this->pdo);
        $user = new SqliteIntegrationTestUser();
        $this->assertTrue($generator->createTableFromObject($user), $generator->getError());

        $reduced = new class {
            public int $id;
            public string $name;

            /** @var array<string, string> */
            protected array $_type_map = [
                'id' => 'int',
                'name' => 'string',
            ];

            public function getTable(): string
            {
                return 'integration_users';
            }
        };

        $this->assertTrue($generator->updateTable($reduced, null, true), $generator->getError());

        $stmt = $this->pdo->query("PRAGMA table_info(integration_users)");
        $columns = array_column($stmt->fetchAll(), 'name');
        $this->assertNotContains('email', $columns);
        $this->assertNotContains('bio', $columns);
        $this->assertNotContains('isActive', $columns);
    }

    public function testRemoveColumn(): void
    {
        $generator = new TableGenerator($this->pdo);
        $user = new SqliteIntegrationTestUser();
        $this->assertTrue($generator->createTableFromObject($user), $generator->getError());

        $this->assertTrue($generator->removeColumn('integration_users', 'bio'), $generator->getError());

        $stmt = $this->pdo->query("PRAGMA table_info(integration_users)");
        $columns = array_column($stmt->fetchAll(), 'name');
        $this->assertNotContains('bio', $columns);
    }

    public function testMakeColumnUnique(): void
    {
        $generator = new TableGenerator($this->pdo);
        $user = new SqliteIntegrationTestUser();
        $this->assertTrue($generator->createTableFromObject($user), $generator->getError());

        $this->assertTrue($generator->makeColumnUnique('integration_users', 'bio'), $generator->getError());

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND tbl_name='integration_users' AND name='uidx_integration_users_bio'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testSchemaGeneratorAppliesUniqueAndIndexConstraints(): void
    {
        $generator = new TableGenerator($this->pdo);
        $user = new SqliteIntegrationTestUser();
        $this->assertTrue($generator->createTableFromObject($user), $generator->getError());

        $schemaGenerator = new SchemaGenerator($this->pdo, 'integration_users', $user->defineSchema());
        $this->assertTrue($schemaGenerator->applyConstraints(), $schemaGenerator->getError());

        // Unique constraint is enforced for real - a duplicate email insert must fail.
        $this->pdo->exec("INSERT INTO integration_users (name, email, isActive) VALUES ('Bob', 'bob@example.com', 1)");
        $threw = false;
        try {
            $this->pdo->exec("INSERT INTO integration_users (name, email, isActive) VALUES ('Bobby', 'bob@example.com', 1)");
        } catch (\PDOException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected a UNIQUE constraint violation on duplicate email');

        // The plain (non-unique) index on 'name' must also exist.
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND tbl_name='integration_users' AND sql LIKE '%name%'");
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn());
    }
}

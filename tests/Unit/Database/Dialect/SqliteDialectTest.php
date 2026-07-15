<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Dialect;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Dialect\SqliteDialect;

class SqliteDialectTest extends TestCase
{
    private SqliteDialect $dialect;

    protected function setUp(): void
    {
        $this->dialect = new SqliteDialect();
    }

    public function testGetName(): void
    {
        $this->assertSame('sqlite', $this->dialect->getName());
    }

    public function testQuoteIdentifier(): void
    {
        $this->assertSame('"users"', $this->dialect->quoteIdentifier('users'));
    }

    /**
     * @dataProvider engineTypeProvider
     */
    public function testToEngineType(string $canonical, string $expected): void
    {
        $this->assertSame($expected, $this->dialect->toEngineType($canonical));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function engineTypeProvider(): array
    {
        return [
            'int' => ['int', 'INTEGER'],
            'float' => ['float', 'REAL'],
            'bool' => ['bool', 'INTEGER'],
            'string' => ['string', 'VARCHAR(255)'],
            'text' => ['text', 'TEXT'],
            'longtext' => ['longtext', 'TEXT'],
            'datetime' => ['datetime', 'DATETIME'],
            'json' => ['json', 'TEXT'],
            'unknown' => ['some_unknown_type', 'TEXT'],
            'decimal default' => ['decimal', 'NUMERIC'],
            'decimal explicit' => ['decimal:8,3', 'NUMERIC'],
        ];
    }

    /**
     * @dataProvider canonicalTypeProvider
     */
    public function testToCanonicalType(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->dialect->toCanonicalType($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function canonicalTypeProvider(): array
    {
        return [
            'integer' => ['INTEGER', 'int'],
            'real' => ['REAL', 'double'],
            'varchar' => ['VARCHAR(255)', 'string'],
            'text' => ['TEXT', 'text'],
            'numeric' => ['NUMERIC', 'decimal:10,2'],
            'datetime' => ['DATETIME', 'datetime'],
        ];
    }

    public function testIdColumnDefinition(): void
    {
        $this->assertSame('INTEGER PRIMARY KEY AUTOINCREMENT', $this->dialect->idColumnDefinition());
    }

    public function testForeignKeyColumnType(): void
    {
        $this->assertSame('INTEGER', $this->dialect->foreignKeyColumnType());
    }

    public function testCreateTableSql(): void
    {
        $sql = $this->dialect->createTableSql('users', ['"id" INTEGER PRIMARY KEY AUTOINCREMENT', '"name" VARCHAR(255) NOT NULL']);
        $this->assertSame('CREATE TABLE IF NOT EXISTS "users" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" VARCHAR(255) NOT NULL);', $sql);
    }

    public function testAddColumnSql(): void
    {
        $sql = $this->dialect->addColumnSql('users', 'age', 'INTEGER NOT NULL');
        $this->assertSame('ALTER TABLE "users" ADD COLUMN "age" INTEGER NOT NULL', $sql);
    }

    public function testAlterColumnSqlIsUnsupported(): void
    {
        $statements = $this->dialect->alterColumnSql('users', 'age', 'INTEGER', '', true, null);
        $this->assertSame([], $statements);
    }

    public function testDropColumnSql(): void
    {
        $this->assertSame('ALTER TABLE "users" DROP COLUMN "age"', $this->dialect->dropColumnSql('users', 'age'));
    }

    public function testDropIndexSql(): void
    {
        $this->assertSame('DROP INDEX "idx_name"', $this->dialect->dropIndexSql('users', 'idx_name'));
    }

    public function testDropPrimaryKeySqlIsUnsupported(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $this->assertNull($this->dialect->dropPrimaryKeySql($pdo, 'users'));
    }

    public function testCreateUniqueIndexSql(): void
    {
        $sql = $this->dialect->createUniqueIndexSql('users', 'idx_email', ['email']);
        $this->assertSame('CREATE UNIQUE INDEX "idx_email" ON "users" ("email")', $sql);
    }

    public function testAddUniqueConstraintSqlDelegatesToCreateUniqueIndex(): void
    {
        $sql = $this->dialect->addUniqueConstraintSql('users', 'uq_email', ['email']);
        $this->assertSame('CREATE UNIQUE INDEX "uq_email" ON "users" ("email")', $sql);
    }

    public function testCreateIndexSql(): void
    {
        $sql = $this->dialect->createIndexSql('users', 'idx_name', ['name'], false);
        $this->assertSame('CREATE INDEX "idx_name" ON "users" ("name")', $sql);
    }

    public function testAddForeignKeySqlReturnsCommentNotDdl(): void
    {
        $sql = $this->dialect->addForeignKeySql('orders', 'fk_user', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->assertStringStartsWith('--', $sql);
    }

    public function testAddCheckConstraintSqlReturnsCommentNotDdl(): void
    {
        $sql = $this->dialect->addCheckConstraintSql('users', 'chk_age', 'age >= 18');
        $this->assertStringStartsWith('--', $sql);
    }

    public function testCreateFulltextIndexSqlReturnsNull(): void
    {
        $this->assertNull($this->dialect->createFulltextIndexSql('articles', 'ft_content', ['title']));
    }

    public function testConstraintExistsAlwaysFalse(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $this->assertFalse($this->dialect->constraintExists($pdo, 'users', 'anything'));
    }

    public function testGetExistingConstraintsAlwaysEmpty(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $this->assertSame([], $this->dialect->getExistingConstraints($pdo, 'users'));
    }

    public function testDropConstraintSqlDelegatesToDropIndex(): void
    {
        $sql = $this->dialect->dropConstraintSql('users', 'uq_email');
        $this->assertSame('DROP INDEX "uq_email"', $sql);
    }
}

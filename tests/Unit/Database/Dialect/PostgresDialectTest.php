<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Dialect;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Dialect\PostgresDialect;

class PostgresDialectTest extends TestCase
{
    private PostgresDialect $dialect;

    protected function setUp(): void
    {
        $this->dialect = new PostgresDialect();
    }

    public function testGetName(): void
    {
        $this->assertSame('pgsql', $this->dialect->getName());
    }

    public function testQuoteIdentifier(): void
    {
        $this->assertSame('"users"', $this->dialect->quoteIdentifier('users'));
    }

    public function testQuoteIdentifierEscapesEmbeddedQuotes(): void
    {
        $this->assertSame('"weird""name"', $this->dialect->quoteIdentifier('weird"name'));
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
            'float' => ['float', 'DOUBLE PRECISION'],
            'bool' => ['bool', 'BOOLEAN'],
            'string' => ['string', 'VARCHAR(255)'],
            'text' => ['text', 'TEXT'],
            'longtext' => ['longtext', 'TEXT'],
            'datetime' => ['datetime', 'TIMESTAMP'],
            'json' => ['json', 'JSONB'],
            'jsonb' => ['jsonb', 'JSONB'],
            'array' => ['array', 'JSONB'],
            'unknown' => ['some_unknown_type', 'TEXT'],
            'decimal default' => ['decimal', 'NUMERIC(10,2)'],
            'decimal explicit' => ['decimal:8,3', 'NUMERIC(8,3)'],
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
            'integer' => ['integer', 'integer'],
            'character varying' => ['character varying', 'string'],
            'timestamp without time zone' => ['timestamp without time zone', 'datetime'],
            'boolean' => ['boolean', 'int'],
            'numeric' => ['numeric(8,3)', 'decimal:8,3'],
            'double precision' => ['double precision', 'double'],
            'jsonb' => ['jsonb', 'string'],
            'bigint' => ['bigint', 'int'],
        ];
    }

    public function testIdColumnDefinition(): void
    {
        $this->assertSame('SERIAL PRIMARY KEY', $this->dialect->idColumnDefinition());
    }

    public function testForeignKeyColumnType(): void
    {
        $this->assertSame('INTEGER', $this->dialect->foreignKeyColumnType());
    }

    public function testCreateTableSql(): void
    {
        $sql = $this->dialect->createTableSql('users', ['"id" SERIAL PRIMARY KEY', '"name" VARCHAR(255) NOT NULL']);
        $this->assertSame('CREATE TABLE IF NOT EXISTS "users" ("id" SERIAL PRIMARY KEY, "name" VARCHAR(255) NOT NULL);', $sql);
    }

    public function testAddColumnSql(): void
    {
        $sql = $this->dialect->addColumnSql('users', 'age', 'INTEGER NOT NULL');
        $this->assertSame('ALTER TABLE "users" ADD COLUMN "age" INTEGER NOT NULL', $sql);
    }

    public function testAlterColumnSqlReturnsMultipleStatements(): void
    {
        $statements = $this->dialect->alterColumnSql('users', 'age', 'INTEGER', '', true, null);

        $this->assertCount(3, $statements);
        $this->assertSame('ALTER TABLE "users" ALTER COLUMN "age" TYPE INTEGER USING "age"::INTEGER', $statements[0]);
        $this->assertSame('ALTER TABLE "users" ALTER COLUMN "age" DROP NOT NULL', $statements[1]);
        $this->assertSame('ALTER TABLE "users" ALTER COLUMN "age" DROP DEFAULT', $statements[2]);
    }

    public function testAlterColumnSqlNotNullableSetsNotNull(): void
    {
        $statements = $this->dialect->alterColumnSql('users', 'age', 'INTEGER', '', false, null);
        $this->assertSame('ALTER TABLE "users" ALTER COLUMN "age" SET NOT NULL', $statements[1]);
    }

    public function testAlterColumnSqlWithDefaultClause(): void
    {
        $statements = $this->dialect->alterColumnSql('users', 'status', 'VARCHAR(255)', '', false, " DEFAULT 'active'");

        $this->assertCount(3, $statements);
        $this->assertSame("ALTER TABLE \"users\" ALTER COLUMN \"status\" SET DEFAULT 'active'", $statements[2]);
    }

    public function testDropColumnSql(): void
    {
        $this->assertSame('ALTER TABLE "users" DROP COLUMN "age"', $this->dialect->dropColumnSql('users', 'age'));
    }

    public function testDropIndexSql(): void
    {
        $this->assertSame('DROP INDEX "idx_name"', $this->dialect->dropIndexSql('users', 'idx_name'));
    }

    public function testCreateUniqueIndexSql(): void
    {
        $sql = $this->dialect->createUniqueIndexSql('users', 'idx_email', ['email']);
        $this->assertSame('CREATE UNIQUE INDEX "idx_email" ON "users" ("email")', $sql);
    }

    public function testAddUniqueConstraintSql(): void
    {
        $sql = $this->dialect->addUniqueConstraintSql('users', 'uq_email', ['email']);
        $this->assertSame('ALTER TABLE "users" ADD CONSTRAINT "uq_email" UNIQUE ("email")', $sql);
    }

    public function testCreateIndexSql(): void
    {
        $sql = $this->dialect->createIndexSql('users', 'idx_name', ['name'], false);
        $this->assertSame('CREATE INDEX "idx_name" ON "users" ("name")', $sql);
    }

    public function testAddForeignKeySql(): void
    {
        $sql = $this->dialect->addForeignKeySql('orders', 'fk_user', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->assertStringContainsString('ALTER TABLE "orders"', $sql);
        $this->assertStringContainsString('ADD CONSTRAINT "fk_user"', $sql);
        $this->assertStringContainsString('FOREIGN KEY ("user_id")', $sql);
        $this->assertStringContainsString('REFERENCES "users"("id")', $sql);
    }

    public function testAddCheckConstraintSql(): void
    {
        $sql = $this->dialect->addCheckConstraintSql('users', 'chk_age', 'age >= 18');
        $this->assertSame('ALTER TABLE "users" ADD CONSTRAINT "chk_age" CHECK (age >= 18)', $sql);
    }

    public function testCreateFulltextIndexSqlReturnsNull(): void
    {
        $this->assertNull($this->dialect->createFulltextIndexSql('articles', 'ft_content', ['title']));
    }

    public function testDropConstraintSql(): void
    {
        $sql = $this->dialect->dropConstraintSql('users', 'uq_email');
        $this->assertSame('ALTER TABLE "users" DROP CONSTRAINT "uq_email"', $sql);
    }
}

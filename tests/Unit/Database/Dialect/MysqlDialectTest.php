<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Dialect;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Dialect\MysqlDialect;

class MysqlDialectTest extends TestCase
{
    private MysqlDialect $dialect;

    protected function setUp(): void
    {
        $this->dialect = new MysqlDialect();
    }

    public function testGetName(): void
    {
        $this->assertSame('mysql', $this->dialect->getName());
    }

    public function testQuoteIdentifier(): void
    {
        $this->assertSame('`users`', $this->dialect->quoteIdentifier('users'));
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
            'int' => ['int', 'INT(11)'],
            'integer' => ['integer', 'INT(11)'],
            'float' => ['float', 'DOUBLE'],
            'bool' => ['bool', 'TINYINT(1)'],
            'string' => ['string', 'VARCHAR(255)'],
            'text' => ['text', 'TEXT'],
            'longtext' => ['longtext', 'LONGTEXT'],
            'datetime' => ['datetime', 'DATETIME'],
            'json' => ['json', 'JSON'],
            'array' => ['array', 'JSON'],
            'unknown' => ['some_unknown_type', 'TEXT'],
            'decimal default' => ['decimal', 'DECIMAL(10,2)'],
            'decimal explicit' => ['decimal:8,3', 'DECIMAL(8,3)'],
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
            'int(11)' => ['int(11)', 'int'],
            'bigint' => ['bigint(20)', 'int'],
            'varchar' => ['varchar(255)', 'string'],
            'tinyint(1) as bool' => ['tinyint(1)', 'int'],
            'datetime' => ['datetime', 'datetime'],
            'decimal' => ['decimal(8,3)', 'decimal:8,3'],
            'double' => ['double', 'double'],
            'json' => ['json', 'string'],
            'unsigned int' => ['int(11) unsigned', 'int'],
        ];
    }

    public function testIdColumnDefinition(): void
    {
        $this->assertSame('INT(11) AUTO_INCREMENT PRIMARY KEY', $this->dialect->idColumnDefinition());
    }

    public function testForeignKeyColumnType(): void
    {
        $this->assertSame('INT(11)', $this->dialect->foreignKeyColumnType());
    }

    public function testCreateTableSql(): void
    {
        $sql = $this->dialect->createTableSql('users', ['`id` INT(11) AUTO_INCREMENT PRIMARY KEY', '`name` VARCHAR(255) NOT NULL']);
        $this->assertSame('CREATE TABLE IF NOT EXISTS `users` (`id` INT(11) AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL);', $sql);
    }

    public function testAddColumnSql(): void
    {
        $sql = $this->dialect->addColumnSql('users', 'age', 'INT(11) NOT NULL');
        $this->assertSame('ALTER TABLE `users` ADD COLUMN `age` INT(11) NOT NULL', $sql);
    }

    public function testAlterColumnSqlReturnsSingleStatement(): void
    {
        $statements = $this->dialect->alterColumnSql('users', 'age', 'INT(11)', '', true, null);
        $this->assertCount(1, $statements);
        $this->assertSame('ALTER TABLE `users` MODIFY COLUMN `age` INT(11) NULL', $statements[0]);
    }

    public function testAlterColumnSqlWithDefaultAndNotNull(): void
    {
        $statements = $this->dialect->alterColumnSql('users', 'status', 'VARCHAR(255)', '', false, " DEFAULT 'active'");
        $this->assertSame(["ALTER TABLE `users` MODIFY COLUMN `status` VARCHAR(255) NOT NULL DEFAULT 'active'"], $statements);
    }

    public function testDropColumnSql(): void
    {
        $this->assertSame('ALTER TABLE `users` DROP COLUMN `age`', $this->dialect->dropColumnSql('users', 'age'));
    }

    public function testDropIndexSql(): void
    {
        $this->assertSame('DROP INDEX `idx_name` ON `users`', $this->dialect->dropIndexSql('users', 'idx_name'));
    }

    public function testDropPrimaryKeySql(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $this->assertSame('ALTER TABLE `users` DROP PRIMARY KEY', $this->dialect->dropPrimaryKeySql($pdo, 'users'));
    }

    public function testCreateUniqueIndexSql(): void
    {
        $sql = $this->dialect->createUniqueIndexSql('users', 'idx_email', ['email']);
        $this->assertSame('CREATE UNIQUE INDEX `idx_email` ON `users` (`email`)', $sql);
    }

    public function testAddUniqueConstraintSql(): void
    {
        $sql = $this->dialect->addUniqueConstraintSql('users', 'uq_email', ['email']);
        $this->assertSame('ALTER TABLE `users` ADD CONSTRAINT `uq_email` UNIQUE (`email`)', $sql);
    }

    public function testCreateIndexSqlNonUnique(): void
    {
        $sql = $this->dialect->createIndexSql('users', 'idx_name', ['name'], false);
        $this->assertSame('CREATE INDEX `idx_name` ON `users` (`name`)', $sql);
    }

    public function testCreateIndexSqlUnique(): void
    {
        $sql = $this->dialect->createIndexSql('users', 'idx_name', ['name'], true);
        $this->assertSame('CREATE UNIQUE INDEX `idx_name` ON `users` (`name`)', $sql);
    }

    public function testAddForeignKeySql(): void
    {
        $sql = $this->dialect->addForeignKeySql('orders', 'fk_user', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->assertStringContainsString('ALTER TABLE `orders`', $sql);
        $this->assertStringContainsString('ADD CONSTRAINT `fk_user`', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `users`(`id`)', $sql);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
        $this->assertStringContainsString('ON UPDATE CASCADE', $sql);
    }

    public function testAddCheckConstraintSql(): void
    {
        $sql = $this->dialect->addCheckConstraintSql('users', 'chk_age', 'age >= 18');
        $this->assertSame('ALTER TABLE `users` ADD CONSTRAINT `chk_age` CHECK (age >= 18)', $sql);
    }

    public function testCreateFulltextIndexSql(): void
    {
        $sql = $this->dialect->createFulltextIndexSql('articles', 'ft_content', ['title', 'body']);
        $this->assertSame('CREATE FULLTEXT INDEX `ft_content` ON `articles` (`title`, `body`)', $sql);
    }

    public function testDropConstraintSql(): void
    {
        $sql = $this->dialect->dropConstraintSql('users', 'uq_email');
        $this->assertSame('ALTER TABLE `users` DROP CONSTRAINT `uq_email`', $sql);
    }
}

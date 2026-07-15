<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Dialect;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Dialect\DialectResolver;
use Gemvc\Database\Dialect\MysqlDialect;
use Gemvc\Database\Dialect\PostgresDialect;
use Gemvc\Database\Dialect\SqliteDialect;
use PDO;

class DialectResolverTest extends TestCase
{
    public function testResolveReturnsSqliteDialectForRealSqliteConnection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $dialect = DialectResolver::resolve($pdo);
        $this->assertInstanceOf(SqliteDialect::class, $dialect);
    }

    public function testResolveFallsBackToMysqlForMockedPdoWithUnstubbedGetAttribute(): void
    {
        // PHPUnit's createMock(PDO::class) returns null for getAttribute() unless stubbed -
        // this is exactly the scenario the entire existing TableGeneratorTest/SchemaGeneratorTest
        // suite relies on to keep passing unmodified with the new dialect abstraction in place.
        $pdo = $this->createMock(PDO::class);
        $dialect = DialectResolver::resolve($pdo);
        $this->assertInstanceOf(MysqlDialect::class, $dialect);
    }

    public function testResolveFallsBackToMysqlForUnrecognizedDriverName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('some_unknown_driver');
        $dialect = DialectResolver::resolve($pdo);
        $this->assertInstanceOf(MysqlDialect::class, $dialect);
    }

    public function testResolveReturnsMysqlDialectForMysqlDriverName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('mysql');
        $dialect = DialectResolver::resolve($pdo);
        $this->assertInstanceOf(MysqlDialect::class, $dialect);
    }

    public function testResolveReturnsPostgresDialectForPgsqlDriverName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('pgsql');
        $dialect = DialectResolver::resolve($pdo);
        $this->assertInstanceOf(PostgresDialect::class, $dialect);
    }

    public function testResolveReturnsSqliteDialectForSqliteDriverName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $dialect = DialectResolver::resolve($pdo);
        $this->assertInstanceOf(SqliteDialect::class, $dialect);
    }
}

<?php

namespace Gemvc\Database\Dialect;

use PDO;

/**
 * Resolves the correct SqlDialectInterface implementation for a given PDO connection.
 *
 * Falls back to MysqlDialect whenever PDO::ATTR_DRIVER_NAME is unavailable or
 * unrecognized. This is a deliberate design choice, not just a safe default: PHPUnit's
 * `createMock(PDO::class)` returns null for unstubbed `getAttribute()` calls, so
 * resolving to MysqlDialect in that case guarantees TableGenerator/SchemaGenerator's
 * pre-existing (mock-based) test suites keep passing unmodified when no dialect is
 * explicitly injected.
 */
class DialectResolver
{
    public static function resolve(PDO $pdo): SqlDialectInterface
    {
        $driver = null;
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            // Some mock/stub PDO instances throw on getAttribute() for unconfigured
            // attributes - treat that the same as "unknown driver" and fall back to MySQL.
            $driver = null;
        }

        return match (is_string($driver) ? $driver : 'mysql') {
            'pgsql' => new PostgresDialect(),
            'sqlite' => new SqliteDialect(),
            default => new MysqlDialect(),
        };
    }
}

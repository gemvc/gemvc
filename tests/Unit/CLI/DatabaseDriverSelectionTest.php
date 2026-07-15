<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Gemvc\CLI\AbstractInit;
use Gemvc\CLI\DockerComposeInit;
use Gemvc\CLI\Commands\InitSwoole;
use ReflectionClass;

/**
 * Tests for the database driver selection feature added to `gemvc init`
 * (mysql / postgres / sqlite), covering:
 * - AbstractInit::resolveDatabaseDriver() flag parsing
 * - AbstractInit::applyDatabaseDriverToEnv() DB_* rewriting
 * - DockerComposeInit::getAvailableServices() driver-aware service list
 */
class DatabaseDriverSelectionTest extends TestCase
{
    private function invokeProtected(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        // resolveDatabaseDriver()/etc. write CLI status messages via $this->info(); swallow
        // them here so PHPUnit doesn't flag the test as risky for unexpected output.
        ob_start();
        try {
            return $reflectionMethod->invokeArgs($object, $args);
        } finally {
            ob_end_clean();
        }
    }

    private function setDatabaseDriver(object $object, string $driver): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty('databaseDriver');
        $property->setAccessible(true);
        $property->setValue($object, $driver);
    }

    /**
     * Force $nonInteractive=true without running the full initializeProject() (which touches
     * the filesystem); this is what guarantees resolveDatabaseDriver() never blocks on stdin
     * in these tests, mirroring how `--non-interactive`/`-n` behaves at runtime.
     */
    private function forceNonInteractive(object $object): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty('nonInteractive');
        $property->setAccessible(true);
        $property->setValue($object, true);
    }

    // ------------------------------------------------------------------
    // resolveDatabaseDriver()
    // ------------------------------------------------------------------

    public function testResolveDatabaseDriverDefaultsToMysqlInNonInteractiveMode(): void
    {
        $init = new InitSwoole(['--non-interactive']);
        $this->forceNonInteractive($init);
        $driver = $this->invokeProtected($init, 'resolveDatabaseDriver');
        $this->assertSame('mysql', $driver, 'Non-interactive mode with no --db flag must default to mysql for backward compatibility');
    }

    public function testResolveDatabaseDriverHonorsShorthandPostgresFlag(): void
    {
        $init = new InitSwoole(['--non-interactive', '--postgres']);
        $this->forceNonInteractive($init);
        $driver = $this->invokeProtected($init, 'resolveDatabaseDriver');
        $this->assertSame('postgres', $driver);
    }

    public function testResolveDatabaseDriverHonorsShorthandSqliteFlag(): void
    {
        $init = new InitSwoole(['--non-interactive', '--sqlite']);
        $this->forceNonInteractive($init);
        $driver = $this->invokeProtected($init, 'resolveDatabaseDriver');
        $this->assertSame('sqlite', $driver);
    }

    public function testResolveDatabaseDriverHonorsDbEqualsFlag(): void
    {
        $init = new InitSwoole(['--non-interactive', '--db=postgres']);
        $this->forceNonInteractive($init);
        $driver = $this->invokeProtected($init, 'resolveDatabaseDriver');
        $this->assertSame('postgres', $driver);
    }

    public function testResolveDatabaseDriverIgnoresInvalidDbFlagValue(): void
    {
        $init = new InitSwoole(['--non-interactive', '--db=oracle']);
        $this->forceNonInteractive($init);
        $driver = $this->invokeProtected($init, 'resolveDatabaseDriver');
        $this->assertSame('mysql', $driver, 'Invalid --db value should fall back to the mysql default');
    }

    // ------------------------------------------------------------------
    // applyDatabaseDriverToEnv()
    // ------------------------------------------------------------------

    private function sampleEnvTemplate(): string
    {
        return <<<'ENV'
# Database Configuration
DB_HOST_CLI_DEV="localhost"
DB_HOST="db"
DB_PORT=3306
DB_NAME="gemvc_db"
DB_CHARSET="utf8mb4"
DB_USER="root"
DB_PASSWORD=rootpassword
QUERY_LIMIT=10
ENV;
    }

    public function testApplyDatabaseDriverToEnvMysqlIsMinimallyInvasive(): void
    {
        $init = new InitSwoole(['--non-interactive']);
        $this->setDatabaseDriver($init, 'mysql');

        $result = $this->invokeProtected($init, 'applyDatabaseDriverToEnv', [$this->sampleEnvTemplate()]);

        $this->assertMatchesRegularExpression('/^DB_DRIVER="mysql"$/m', $result);
        // Existing MySQL defaults must remain untouched (only DB_DRIVER is newly inserted)
        $this->assertStringContainsString('DB_PORT=3306', $result);
        $this->assertStringContainsString('DB_USER="root"', $result);
        $this->assertStringContainsString('DB_NAME="gemvc_db"', $result);
    }

    public function testApplyDatabaseDriverToEnvPostgres(): void
    {
        $init = new InitSwoole(['--non-interactive']);
        $this->setDatabaseDriver($init, 'postgres');

        $result = $this->invokeProtected($init, 'applyDatabaseDriverToEnv', [$this->sampleEnvTemplate()]);

        $this->assertMatchesRegularExpression('/^DB_DRIVER="pgsql"$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_PORT="5432"$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_USER="postgres"$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_CHARSET="UTF8"$/m', $result);
    }

    public function testApplyDatabaseDriverToEnvSqlite(): void
    {
        $init = new InitSwoole(['--non-interactive']);
        $this->setDatabaseDriver($init, 'sqlite');

        $result = $this->invokeProtected($init, 'applyDatabaseDriverToEnv', [$this->sampleEnvTemplate()]);

        $this->assertMatchesRegularExpression('/^DB_DRIVER="sqlite"$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_NAME="database\/gemvc\.sqlite"$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_HOST=$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_PORT=$/m', $result);
        $this->assertMatchesRegularExpression('/^DB_USER=$/m', $result);
    }

    // ------------------------------------------------------------------
    // DockerComposeInit::getAvailableServices()
    // ------------------------------------------------------------------

    private function getAvailableServicesFor(string $driver): array
    {
        $dockerInit = new DockerComposeInit('/tmp/gemvc-test', true, 'openswoole', 9501, $driver);
        return $this->invokeProtected($dockerInit, 'getAvailableServices');
    }

    public function testGetAvailableServicesForMysql(): void
    {
        $services = $this->getAvailableServicesFor('mysql');

        $this->assertArrayHasKey('db', $services);
        $this->assertArrayHasKey('phpmyadmin', $services);
        $this->assertArrayHasKey('redis', $services);
        $this->assertArrayNotHasKey('pgadmin', $services);
        $this->assertSame('mysql:8.0', $services['db']['image']);
    }

    public function testGetAvailableServicesForPostgres(): void
    {
        $services = $this->getAvailableServicesFor('postgres');

        $this->assertArrayHasKey('db', $services);
        $this->assertArrayHasKey('pgadmin', $services);
        $this->assertArrayHasKey('redis', $services);
        $this->assertArrayNotHasKey('phpmyadmin', $services);
        $this->assertSame('postgres:16-alpine', $services['db']['image']);
    }

    public function testGetAvailableServicesForSqliteHasNoDbService(): void
    {
        $services = $this->getAvailableServicesFor('sqlite');

        $this->assertArrayNotHasKey('db', $services);
        $this->assertArrayNotHasKey('phpmyadmin', $services);
        $this->assertArrayNotHasKey('pgadmin', $services);
        $this->assertArrayHasKey('redis', $services, 'Redis is an independent axis and should still be offered for sqlite');
    }
}

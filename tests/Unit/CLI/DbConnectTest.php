<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\Helper\ProjectHelper;
use ReflectionClass;
use PDO;

/**
 * Tests for DbConnect's driver-aware DSN construction (mysql/pgsql/sqlite).
 *
 * DSN branches for mysql/pgsql cannot be exercised end-to-end here without a live
 * server, so the private pure-logic helpers (resolveDriver/defaultPort/defaultUser/
 * defaultCharset) are tested directly via reflection - this mirrors the pattern
 * connection-pdo's own PdoConnectionClassTest uses for DSN assertions. The sqlite
 * path is exercised end-to-end via a real in-memory connection since sqlite requires
 * no external server.
 */
class DbConnectTest extends TestCase
{
    private function invokeStatic(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass(DbConnect::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs(null, $args);
    }

    protected function tearDown(): void
    {
        unset($_ENV['DB_DRIVER'], $_ENV['DB_NAME'], $_ENV['DB_HOST_CLI_DEV'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_PORT'], $_ENV['DB_CHARSET'], $_ENV['DB_SSLMODE']);
        $this->removeTempRootEnvFile();
    }

    /**
     * connect()/connectAsRoot() call ProjectHelper::loadEnv() first, which throws unless a
     * '.env' exists at the project root (found via composer.lock) - so a temporary root .env
     * is required here to exercise those methods end-to-end. overload() (used by loadEnv())
     * takes precedence over pre-set $_ENV values, so the driver/config must be written into
     * this file rather than only set on $_ENV.
     */
    private function writeTempRootEnvFile(string $contents): void
    {
        file_put_contents($this->tempRootEnvPath(), $contents);
    }

    private function removeTempRootEnvFile(): void
    {
        $path = $this->tempRootEnvPath();
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function tempRootEnvPath(): string
    {
        return ProjectHelper::rootDir() . DIRECTORY_SEPARATOR . '.env';
    }

    // ------------------------------------------------------------------
    // resolveDriver()
    // ------------------------------------------------------------------

    public function testResolveDriverDefaultsToMysqlWhenUnset(): void
    {
        unset($_ENV['DB_DRIVER']);
        $this->assertSame('mysql', $this->invokeStatic('resolveDriver'));
    }

    public function testResolveDriverAcceptsPgsql(): void
    {
        $_ENV['DB_DRIVER'] = 'pgsql';
        $this->assertSame('pgsql', $this->invokeStatic('resolveDriver'));
    }

    public function testResolveDriverAcceptsSqlite(): void
    {
        $_ENV['DB_DRIVER'] = 'sqlite';
        $this->assertSame('sqlite', $this->invokeStatic('resolveDriver'));
    }

    public function testResolveDriverIsCaseInsensitive(): void
    {
        $_ENV['DB_DRIVER'] = 'PgSQL';
        $this->assertSame('pgsql', $this->invokeStatic('resolveDriver'));
    }

    public function testResolveDriverFallsBackToMysqlForUnrecognizedValue(): void
    {
        $_ENV['DB_DRIVER'] = 'oracle';
        $this->assertSame('mysql', $this->invokeStatic('resolveDriver'));
    }

    // ------------------------------------------------------------------
    // defaultPort()/defaultUser()/defaultCharset()
    // ------------------------------------------------------------------

    public function testDefaultPortForMysql(): void
    {
        $this->assertSame(3306, $this->invokeStatic('defaultPort', ['mysql']));
    }

    public function testDefaultPortForPgsql(): void
    {
        $this->assertSame(5432, $this->invokeStatic('defaultPort', ['pgsql']));
    }

    public function testDefaultUserForMysql(): void
    {
        $this->assertSame('root', $this->invokeStatic('defaultUser', ['mysql']));
    }

    public function testDefaultUserForPgsql(): void
    {
        $this->assertSame('postgres', $this->invokeStatic('defaultUser', ['pgsql']));
    }

    public function testDefaultCharsetForMysql(): void
    {
        $this->assertSame('utf8mb4', $this->invokeStatic('defaultCharset', ['mysql']));
    }

    public function testDefaultCharsetForPgsql(): void
    {
        $this->assertSame('UTF8', $this->invokeStatic('defaultCharset', ['pgsql']));
    }

    // ------------------------------------------------------------------
    // connect() - sqlite (real, in-memory; no external server required)
    // ------------------------------------------------------------------

    public function testConnectReturnsWorkingPdoForSqliteInMemory(): void
    {
        $this->writeTempRootEnvFile("DB_DRIVER=\"sqlite\"\nDB_NAME=\":memory:\"\n");

        ob_start();
        $pdo = DbConnect::connect();
        ob_end_clean();

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        // Connection is genuinely usable, not just constructed.
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $stmt = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='t'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // NOTE: connectAsRoot()'s sqlite branch (Command::error() -> exit(1)) cannot be exercised
    // in-process - Command::error() terminates the PHP process, which would kill the PHPUnit
    // runner itself. That branch is covered instead by code review / manual CLI verification.
}

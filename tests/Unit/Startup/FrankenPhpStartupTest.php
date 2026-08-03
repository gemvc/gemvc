<?php

declare(strict_types=1);

namespace Tests\Unit\Startup;

use PHPUnit\Framework\TestCase;

/**
 * FrankenPHP startup template must ship Caddyfile edge security and must not ship .htaccess.
 */
final class FrankenPhpStartupTest extends TestCase
{
    private function startupDir(): string
    {
        return dirname(__DIR__, 3) . '/src/startup/frankenphp';
    }

    public function testFrankenPhpStartupHasRequiredFiles(): void
    {
        $dir = $this->startupDir();
        $this->assertDirectoryExists($dir);
        $this->assertFileExists($dir . '/index.php');
        $this->assertFileExists($dir . '/worker.php');
        $this->assertFileExists($dir . '/Caddyfile');
        $this->assertFileExists($dir . '/Caddyfile.worker');
        $this->assertFileExists($dir . '/example.env');
        $this->assertFileExists($dir . '/Dockerfile');
        $this->assertFileExists($dir . '/composer.json');
    }

    public function testWorkerEntryUsesFrankenPhpWorker(): void
    {
        $worker = (string) file_get_contents($this->startupDir() . '/worker.php');
        $this->assertStringContainsString('FrankenPhpWorker', $worker);
        $this->assertStringNotContainsString('new Bootstrap', $worker);
    }

    public function testWorkerCaddyfileDefinesWorkerBlock(): void
    {
        $caddy = (string) file_get_contents($this->startupDir() . '/Caddyfile.worker');
        $this->assertStringContainsString('worker ./worker.php', $caddy);
        $this->assertStringContainsString('match *', $caddy);
    }

    public function testFrankenPhpStartupHasNoHtaccess(): void
    {
        $this->assertFileDoesNotExist($this->startupDir() . '/.htaccess');
    }

    public function testCaddyfileDeniesSensitivePaths(): void
    {
        $caddy = (string) file_get_contents($this->startupDir() . '/Caddyfile');
        $this->assertStringContainsString('/app/*', $caddy);
        $this->assertStringContainsString('/vendor/*', $caddy);
        $this->assertStringContainsString('php_server', $caddy);
        $this->assertStringContainsString('.htaccess', $caddy); // documented as ignored / denied
    }

    public function testExampleEnvSetsFrankenPhpServer(): void
    {
        $env = (string) file_get_contents($this->startupDir() . '/example.env');
        $this->assertStringContainsString('APP_ENV_SERVER=frankenphp', $env);
    }

    public function testIndexUsesStandardHttpRequestAndBootstrap(): void
    {
        $index = (string) file_get_contents($this->startupDir() . '/index.php');
        $this->assertStringContainsString('StandardHttpRequest', $index);
        $this->assertStringContainsString('Bootstrap', $index);
        $this->assertStringNotContainsString('SwooleRequest', $index);
    }
}

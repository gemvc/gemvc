<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\WebserverDetector;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Gemvc\Core\WebserverDetector
 */
final class WebserverDetectorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $envBackup = [];

    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBackup = $_ENV;
        $this->serverBackup = $_SERVER;
        WebserverDetector::forceRefresh();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
        $_SERVER = $this->serverBackup;
        WebserverDetector::forceRefresh();
        parent::tearDown();
    }

    public function testDetectsFrankenPhpFromAppEnvServer(): void
    {
        $_ENV['APP_ENV_SERVER'] = 'frankenphp';
        unset($_ENV['WEBSERVER_TYPE']);

        $this->assertSame('frankenphp', WebserverDetector::forceRefresh());
        $this->assertTrue(WebserverDetector::isFrankenPhp());
        $this->assertFalse(WebserverDetector::isSwoole());
    }

    public function testDetectsFrankenPhpFromServerSoftware(): void
    {
        unset($_ENV['APP_ENV_SERVER'], $_ENV['WEBSERVER_TYPE']);
        $_SERVER['SERVER_SOFTWARE'] = 'FrankenPHP';

        $this->assertSame('frankenphp', WebserverDetector::forceRefresh());
    }

    public function testDoesNotTreatBareCaddyAsFrankenPhp(): void
    {
        unset($_ENV['APP_ENV_SERVER'], $_ENV['WEBSERVER_TYPE']);
        $_SERVER['SERVER_SOFTWARE'] = 'Caddy';
        // No frankenphp marker → fall through (may hit nginx heuristics or apache default)
        $result = WebserverDetector::forceRefresh();
        $this->assertNotSame('frankenphp', $result);
    }

    public function testDetectsNginxAndApacheFromEnv(): void
    {
        $_ENV['APP_ENV_SERVER'] = 'nginx';
        $this->assertSame('nginx', WebserverDetector::forceRefresh());
        $this->assertTrue(WebserverDetector::isNginx());

        $_ENV['APP_ENV_SERVER'] = 'apache';
        $this->assertSame('apache', WebserverDetector::forceRefresh());
        $this->assertTrue(WebserverDetector::isApache());
    }
}

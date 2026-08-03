<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Gemvc\Core\InternalTrust;
use Gemvc\Core\ServiceCallException;
use Gemvc\Core\ServiceMap;
use Gemvc\Http\Request;
use Gemvc\Http\ServiceCall;
use PHPUnit\Framework\TestCase;

final class ServiceCallTest extends TestCase
{
    private const SECRET = 'family-secret-for-service-call-tests';

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_ENV'] = 'development';
        $_ENV[ServiceMap::ENV_SERVICES_JSON] = '{"auth":"http://noam-auth","billing":"http://billing:8080/"}';
        $_ENV[InternalTrust::ENV_SECRET] = self::SECRET;
        putenv('APP_ENV=development');
        putenv(ServiceMap::ENV_SERVICES_JSON . '=' . $_ENV[ServiceMap::ENV_SERVICES_JSON]);
        putenv(InternalTrust::ENV_SECRET . '=' . self::SECRET);
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['APP_ENV'],
            $_ENV[ServiceMap::ENV_SERVICES_JSON],
            $_ENV[InternalTrust::ENV_SECRET]
        );
        putenv('APP_ENV');
        putenv(ServiceMap::ENV_SERVICES_JSON);
        putenv(InternalTrust::ENV_SECRET);
        parent::tearDown();
    }

    public function testServiceMapResolvesAndStripsTrailingSlash(): void
    {
        $this->assertSame('http://noam-auth', ServiceMap::baseUrl('auth'));
        $this->assertSame('http://billing:8080', ServiceMap::baseUrl('billing'));
    }

    public function testServiceMapUnknownThrows(): void
    {
        $this->expectException(ServiceCallException::class);
        $this->expectExceptionMessage(ServiceCallException::CODE_UNKNOWN_SERVICE);
        ServiceMap::baseUrl('missing');
    }

    public function testServiceMapInvalidJsonThrows(): void
    {
        $_ENV[ServiceMap::ENV_SERVICES_JSON] = '{not-json';
        $this->expectException(ServiceCallException::class);
        $this->expectExceptionMessage(ServiceCallException::CODE_MAP_INVALID);
        ServiceMap::all();
    }

    public function testEncodePayloadOnceIsStable(): void
    {
        $payload = ['b' => 2, 'a' => 1];
        $raw = ServiceCall::encodePayloadOnce($payload);
        $this->assertSame($raw, ServiceCall::encodePayloadOnce($payload));
        $this->assertSame('{"b":2,"a":1}', $raw);
        $this->assertSame('already', ServiceCall::encodePayloadOnce('already'));
        $this->assertSame('', ServiceCall::encodePayloadOnce(null));
    }

    public function testProductionRequiresTrustMode(): void
    {
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
        $this->expectException(ServiceCallException::class);
        $this->expectExceptionMessage(ServiceCallException::CODE_TRUST_REQUIRED);
        ServiceCall::to('auth')->get('/api/Auth/ping')->run();
    }

    public function testProductionAllowsWithoutInternalTrust(): void
    {
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
        $call = ServiceCall::to('auth')
            ->withoutInternalTrust()
            ->get('/api/Auth/ping');
        $ref = new \ReflectionClass($call);
        $assert = $ref->getMethod('assertTrustPolicy');
        $assert->invoke($call);
        $this->assertTrue(true);
    }

    public function testWithInternalTrustHeadersSatisfyEnforce(): void
    {
        $path = '/api/Auth/oauthLogin';
        $payload = ['code' => 'abc'];
        $raw = ServiceCall::encodePayloadOnce($payload);

        $call = ServiceCall::to('auth')
            ->post($path, $payload)
            ->withInternalTrust();

        $ref = new \ReflectionClass($call);
        $buildHeaders = $ref->getMethod('buildHeaders');
        /** @var array<string, string> $headers */
        $headers = $buildHeaders->invoke($call);

        $this->assertArrayHasKey('X-Gemvc-Internal-Timestamp', $headers);
        $this->assertArrayHasKey('X-Gemvc-Internal-Signature', $headers);

        $request = new Request();
        $request->requestMethod = 'POST';
        $request->requestedUrl = $path;
        $request->rawBody = $raw;
        $request->headers = [
            InternalTrust::HEADER_TIMESTAMP => $headers['X-Gemvc-Internal-Timestamp'],
            InternalTrust::HEADER_SIGNATURE => $headers['X-Gemvc-Internal-Signature'],
        ];

        InternalTrust::enforce($request);
        $this->assertTrue(true);
    }

    public function testFireAndForgetRequiresAsync(): void
    {
        $this->expectException(ServiceCallException::class);
        $this->expectExceptionMessage(ServiceCallException::CODE_ASYNC_REQUIRED);
        ServiceCall::to('auth')->get('/api/X')->withoutInternalTrust()->fireAndForget();
    }

    public function testDefaultModeIsSyncFluent(): void
    {
        $call = ServiceCall::to('auth')->sync()->async()->sync();
        $ref = new \ReflectionClass($call);
        $mode = $ref->getProperty('mode');
        $this->assertSame('sync', $mode->getValue($call));
    }
}

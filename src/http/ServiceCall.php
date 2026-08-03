<?php

namespace Gemvc\Http;

use Gemvc\Core\InternalTrust;
use Gemvc\Core\ServiceCallException;
use Gemvc\Core\ServiceMap;

/**
 * Family microservice caller: discovery (`GEMVC_SERVICES_JSON`) + optional HMAC trust.
 *
 * Delegates to {@see ApiCall} (default sync) or {@see AsyncApiCall} (explicit async).
 * Encodes JSON payloads **once** and signs those exact bytes when trust is enabled.
 *
 * @example
 * ServiceCall::to('auth')
 *     ->post('/api/Auth/oauthLogin', ['code' => $code])
 *     ->withInternalTrust()
 *     ->run();
 */
final class ServiceCall
{
    private const MODE_SYNC = 'sync';
    private const MODE_ASYNC = 'async';

    private string $serviceName;
    private string $baseUrl;
    private string $mode = self::MODE_SYNC;

    /** null = unset; true = withInternalTrust; false = withoutInternalTrust */
    private ?bool $trustMode = null;

    private string $httpMethod = 'GET';
    private string $path = '/';

    /** Exact body bytes for wire + HMAC (never re-encode after set). */
    private string $rawBody = '';

    /** @var array<string, string> */
    private array $queryParams = [];

    private int $connectTimeout = 0;
    private int $timeout = 0;

    /** @var array<string, string> */
    private array $extraHeaders = [];

    private string $contentType = 'application/json';

    private function __construct(string $serviceName, string $baseUrl)
    {
        $this->serviceName = $serviceName;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Resolve sibling by name from GEMVC_SERVICES_JSON.
     *
     * @throws ServiceCallException
     */
    public static function to(string $serviceName): self
    {
        return new self($serviceName, ServiceMap::baseUrl($serviceName));
    }

    public function sync(): self
    {
        $this->mode = self::MODE_SYNC;
        return $this;
    }

    public function async(): self
    {
        $this->mode = self::MODE_ASYNC;
        return $this;
    }

    public function withInternalTrust(): self
    {
        $this->trustMode = true;
        return $this;
    }

    public function withoutInternalTrust(): self
    {
        $this->trustMode = false;
        return $this;
    }

    /**
     * @param float|int $timeout Total timeout seconds
     * @param float|int $connectTimeout Connect timeout seconds (0 = use $timeout)
     */
    public function withTimeout(float|int $timeout, float|int $connectTimeout = 0): self
    {
        $this->timeout = max(0, (int) $timeout);
        $this->connectTimeout = max(0, (int) $connectTimeout);
        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->extraHeaders[$name] = $value;
        }
        return $this;
    }

    /**
     * @param array<string, string> $queryParams
     */
    public function get(string $path, array $queryParams = []): self
    {
        $this->httpMethod = 'GET';
        $this->path = self::normalizePath($path);
        $this->queryParams = $queryParams;
        $this->rawBody = '';
        return $this;
    }

    /**
     * @param array<mixed>|string|null $payload Array is JSON-encoded once; string used as-is
     */
    public function post(string $path, array|string|null $payload = null): self
    {
        return $this->prepareBody('POST', $path, $payload);
    }

    /**
     * @param array<mixed>|string|null $payload
     */
    public function put(string $path, array|string|null $payload = null): self
    {
        return $this->prepareBody('PUT', $path, $payload);
    }

    /**
     * @param array<mixed>|string|null $payload
     */
    public function patch(string $path, array|string|null $payload = null): self
    {
        return $this->prepareBody('PATCH', $path, $payload);
    }

    /**
     * Execute and return response body (string) or false on transport failure.
     *
     * @throws ServiceCallException
     * @throws \Gemvc\Core\InternalServiceException when trust headers cannot be built
     */
    public function run(): string|false
    {
        $this->assertTrustPolicy();
        $url = $this->buildUrl();
        $headers = $this->buildHeaders();

        if ($this->mode === self::MODE_SYNC && $this->httpMethod === 'GET') {
            $api = new ApiCall();
            $this->applyTimeoutsToApiCall($api);
            $api->header = array_merge($api->header, $headers);
            return $api->get($url, $this->queryParams);
        }

        if ($this->mode === self::MODE_SYNC && $this->httpMethod === 'POST') {
            $api = new ApiCall();
            $this->applyTimeoutsToApiCall($api);
            $api->header = array_merge($api->header, $headers);
            return $api->postRaw($url, $this->rawBody, $this->contentType);
        }

        // PUT/PATCH sync, or any async: single-shot AsyncApiCall (supports raw for any method)
        $async = new AsyncApiCall();
        if ($this->timeout > 0) {
            $connect = $this->connectTimeout > 0 ? $this->connectTimeout : $this->timeout;
            $async->setTimeouts($connect, $this->timeout);
        }

        $requestHeaders = $headers;
        if ($this->httpMethod !== 'GET' && $this->rawBody !== '') {
            $requestHeaders['Content-Type'] = $this->contentType;
        }

        if ($this->httpMethod === 'GET') {
            $async->addGet('service-call', $url, $this->queryParams, $requestHeaders);
        } elseif ($this->httpMethod === 'POST') {
            $async->addPostRaw('service-call', $url, $this->rawBody, $this->contentType, $requestHeaders);
        } else {
            $async->addRequest(
                'service-call',
                $url,
                $this->httpMethod,
                [],
                $requestHeaders,
                $this->rawBody !== '' ? ['raw' => $this->rawBody] : []
            );
        }

        $results = $async->executeAll();
        if (!isset($results['service-call'])) {
            return false;
        }
        $result = $results['service-call'];
        if ($result['success'] !== true) {
            return false;
        }
        $body = $result['body'];
        return is_string($body) ? $body : false;
    }

    /**
     * Fire-and-forget (async path only).
     *
     * @throws ServiceCallException
     */
    public function fireAndForget(): bool
    {
        if ($this->mode !== self::MODE_ASYNC) {
            throw new ServiceCallException(
                ServiceCallException::CODE_ASYNC_REQUIRED . ': fireAndForget() requires ->async()',
                ServiceCallException::CODE_ASYNC_REQUIRED
            );
        }

        $this->assertTrustPolicy();
        $url = $this->buildUrl();
        $headers = $this->buildHeaders();
        if ($this->httpMethod !== 'GET' && $this->rawBody !== '') {
            $headers['Content-Type'] = $this->contentType;
        }

        $async = new AsyncApiCall();
        if ($this->timeout > 0) {
            $connect = $this->connectTimeout > 0 ? $this->connectTimeout : $this->timeout;
            $async->setTimeouts($connect, $this->timeout);
        }

        if ($this->httpMethod === 'GET') {
            $async->addGet('service-call', $url, $this->queryParams, $headers);
        } elseif ($this->httpMethod === 'POST') {
            $async->addPostRaw('service-call', $url, $this->rawBody, $this->contentType, $headers);
        } else {
            $async->addRequest(
                'service-call',
                $url,
                $this->httpMethod,
                [],
                $headers,
                $this->rawBody !== '' ? ['raw' => $this->rawBody] : []
            );
        }

        return $async->fireAndForget();
    }

    /**
     * @param array<mixed>|string|null $payload
     */
    private function prepareBody(string $method, string $path, array|string|null $payload): self
    {
        $this->httpMethod = strtoupper($method);
        $this->path = self::normalizePath($path);
        $this->queryParams = [];
        $this->rawBody = self::encodePayloadOnce($payload);
        return $this;
    }

    /**
     * Encode array payload exactly once; strings pass through; null → empty body.
     *
     * @param array<mixed>|string|null $payload
     */
    public static function encodePayloadOnce(array|string|null $payload): string
    {
        if ($payload === null) {
            return '';
        }
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private static function normalizePath(string $path): string
    {
        $path = InternalTrust::canonicalPath($path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        return $path;
    }

    private function buildUrl(): string
    {
        return $this->baseUrl . $this->path;
    }

    /**
     * Path used for HMAC (no query).
     */
    private function signPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = $this->extraHeaders;
        if ($this->trustMode === true) {
            $trustHeaders = InternalTrust::callerHeaders(
                $this->httpMethod,
                $this->signPath(),
                $this->rawBody
            );
            $headers = array_merge($headers, $trustHeaders);
        }

        return $headers;
    }

    /**
     * @throws ServiceCallException
     */
    private function assertTrustPolicy(): void
    {
        if ($this->trustMode !== null) {
            return;
        }

        if (self::isProduction()) {
            throw new ServiceCallException(
                ServiceCallException::CODE_TRUST_REQUIRED
                . ': production ServiceCall to "' . $this->serviceName
                . '" requires withInternalTrust() or withoutInternalTrust()',
                ServiceCallException::CODE_TRUST_REQUIRED
            );
        }
    }

    private static function isProduction(): bool
    {
        if (isset($_ENV['APP_ENV']) && is_string($_ENV['APP_ENV'])) {
            return strtolower($_ENV['APP_ENV']) === 'production';
        }
        $fromGetenv = getenv('APP_ENV');
        if (is_string($fromGetenv) && $fromGetenv !== '') {
            return strtolower($fromGetenv) === 'production';
        }

        return true;
    }

    private function applyTimeoutsToApiCall(ApiCall $api): void
    {
        if ($this->timeout <= 0) {
            return;
        }
        $connect = $this->connectTimeout > 0 ? $this->connectTimeout : $this->timeout;
        $api->setTimeouts($connect, $this->timeout);
    }
}

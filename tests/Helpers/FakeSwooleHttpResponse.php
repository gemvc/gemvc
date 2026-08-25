<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Duck-typed OpenSwoole/Swoole HTTP response for unit tests (no extension required).
 *
 * `header()` is the response API; `$header` is incoming request headers (`NoCors::swoole()`).
 */
final class FakeSwooleHttpResponse
{
    /** @var array<string, string> Incoming request headers (Swoole uses lowercase keys) */
    public array $header = [];

    public object $request;

    /** @var list<array{name: string, value: string}> */
    public array $sentHeaders = [];

    public ?int $statusCode = null;

    public string $body = '';

    /**
     * @param array<string, string> $requestHeaders
     */
    public function __construct(array $requestHeaders = [], string $requestMethod = 'GET')
    {
        $this->header = $requestHeaders;
        $this->request = (object) [
            'server' => ['request_method' => $requestMethod],
        ];
    }

    public function header(string $name, string $value): void
    {
        $this->sentHeaders[] = ['name' => $name, 'value' => $value];
    }

    public function status(int $code, ?string $message = null): void
    {
        $this->statusCode = $code;
    }

    public function end(string $data = ''): void
    {
        $this->body = $data;
    }

    public function wasHeaderSet(string $name, string $value): bool
    {
        foreach ($this->sentHeaders as $sent) {
            if ($sent['name'] === $name && $sent['value'] === $value) {
                return true;
            }
        }

        return false;
    }
}

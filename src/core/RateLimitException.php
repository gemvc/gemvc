<?php

namespace Gemvc\Core;

/**
 * Thrown by ApiService::requireRateLimit() / SwooleApiService::requireRateLimit()
 * (and optionally Bootstrap global rate limit) when a client exceeds the limit
 * or is temporarily blocked.
 *
 * Caught by Bootstrap / SwooleBootstrap → HTTP **429 Too Many Requests**.
 */
class RateLimitException extends \Exception
{
    public function __construct(string $message = 'Too many requests', int $code = 429, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

<?php

namespace Gemvc\Core;

/**
 * Exception thrown by ApiService::requireAuth() / SwooleApiService::requireAuth()
 * when authentication or authorization fails.
 *
 * This exception is caught centrally by Bootstrap and SwooleBootstrap and
 * converted directly into the correct JSON error response — 401 Unauthorized
 * when the caller is not authenticated, or 403 Forbidden when the caller is
 * authenticated but does not have one of the required roles.
 *
 * Throwing (rather than returning a value) is what allows requireAuth() to be
 * called once from a constructor and protect an entire ApiService/SwooleApiService
 * subclass: Bootstrap builds the service object and invokes the requested method
 * inside the same try/catch block, so throwing during construction stops
 * everything before the target method ever runs.
 */
class AuthException extends \Exception
{
    public function __construct(string $message = "Unauthorized", int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

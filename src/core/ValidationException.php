<?php

namespace Gemvc\Core;

/**
 * Exception thrown when validation fails in ApiService
 * (validateOrFail, validatePosts, etc.).
 * Caught by Bootstrap and SwooleBootstrap and converted to HTTP 400 JSON.
 */
class ValidationException extends \Exception
{
    public function __construct(string $message = "", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

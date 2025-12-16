<?php

namespace Gemvc\Core;

/**
 * Exception thrown when validation fails in ApiService
 * This exception is caught by Bootstrap and converted to a GemvcError with HTTP 400 status
 */
class ValidationException extends \Exception
{
    public function __construct(string $message = "", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

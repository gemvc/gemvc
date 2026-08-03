<?php

namespace Gemvc\Core;

/**
 * Thrown by {@see \Gemvc\Http\ServiceCall} for map / trust-policy / misuse errors.
 */
class ServiceCallException extends \RuntimeException
{
    public const CODE_UNKNOWN_SERVICE = 'ERR_SERVICE_UNKNOWN';
    public const CODE_TRUST_REQUIRED = 'ERR_SERVICE_TRUST_REQUIRED';
    public const CODE_MAP_INVALID = 'ERR_SERVICE_MAP_INVALID';
    public const CODE_ASYNC_REQUIRED = 'ERR_SERVICE_ASYNC_REQUIRED';

    public readonly string $errorCode;

    public function __construct(
        string $message,
        string $errorCode = self::CODE_UNKNOWN_SERVICE,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }
}

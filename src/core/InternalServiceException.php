<?php

namespace Gemvc\Core;

/**
 * Thrown by {@see ApiServiceSharedTrait::requireInternalService()} when family
 * (machine-to-machine) trust verification fails or is misconfigured.
 *
 * Caught by Bootstrap / SwooleBootstrap / FrankenPhpBootstrap:
 * - **401** {@see self::CODE_FAILED} — missing/bad/expired HMAC
 * - **500** {@see self::CODE_MISCONFIGURED} — GEMVC_INTERNAL_SECRET missing when gate is used
 *
 * Orthogonal to {@see AuthException} (end-user JWT).
 */
class InternalServiceException extends \Exception
{
    public const CODE_FAILED = 'ERR_INTERNAL_TRUST_FAILED';
    public const CODE_MISCONFIGURED = 'ERR_INTERNAL_TRUST_MISCONFIGURED';

    public readonly string $errorCode;

    public function __construct(
        string $message = 'Internal trust verification failed',
        int $httpCode = 401,
        string $errorCode = self::CODE_FAILED,
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $httpCode, $previous);
    }
}

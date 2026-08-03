<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;

/**
 * Family (machine-to-machine) trust: HMAC-SHA256 over method + path + timestamp + body hash.
 *
 * Headers:
 * - {@see self::HEADER_TIMESTAMP} — Unix seconds
 * - {@see self::HEADER_SIGNATURE} — hex HMAC-SHA256
 *
 * Canonical string: `{METHOD}\n{path}\n{timestamp}\n{body_hash}`
 * where `body_hash` is hex SHA-256 of the raw request body.
 *
 * Env: `GEMVC_INTERNAL_SECRET` (required when gate used), optional
 * `GEMVC_INTERNAL_SECRET_PREVIOUS`, `GEMVC_INTERNAL_TRUST_SKEW_SECONDS` (default 60).
 *
 * Does not replace private networking / mTLS. Does not use end-user JWT.
 */
final class InternalTrust
{
    public const HEADER_TIMESTAMP = 'x-gemvc-internal-timestamp';
    public const HEADER_SIGNATURE = 'x-gemvc-internal-signature';

    public const ENV_SECRET = 'GEMVC_INTERNAL_SECRET';
    public const ENV_SECRET_PREVIOUS = 'GEMVC_INTERNAL_SECRET_PREVIOUS';
    public const ENV_SKEW_SECONDS = 'GEMVC_INTERNAL_TRUST_SKEW_SECONDS';

    public const DEFAULT_SKEW_SECONDS = 60;

    /**
     * Verify inbound family trust headers on the request.
     *
     * @throws InternalServiceException
     */
    public static function enforce(Request $request): void
    {
        $secrets = self::loadSecrets();
        if ($secrets === []) {
            throw new InternalServiceException(
                InternalServiceException::CODE_MISCONFIGURED . ': GEMVC_INTERNAL_SECRET is not configured',
                500,
                InternalServiceException::CODE_MISCONFIGURED
            );
        }

        $timestampHeader = $request->getHeader(self::HEADER_TIMESTAMP);
        $signatureHeader = $request->getHeader(self::HEADER_SIGNATURE);

        if ($timestampHeader === null || $timestampHeader === ''
            || $signatureHeader === null || $signatureHeader === '') {
            throw new InternalServiceException(
                InternalServiceException::CODE_FAILED . ': missing internal trust headers',
                401,
                InternalServiceException::CODE_FAILED
            );
        }

        if (!ctype_digit($timestampHeader)) {
            throw new InternalServiceException(
                InternalServiceException::CODE_FAILED . ': invalid internal trust timestamp',
                401,
                InternalServiceException::CODE_FAILED
            );
        }

        $timestamp = (int) $timestampHeader;
        $skew = self::skewSeconds();
        $now = time();
        if (abs($now - $timestamp) > $skew) {
            throw new InternalServiceException(
                InternalServiceException::CODE_FAILED . ': internal trust timestamp outside skew window',
                401,
                InternalServiceException::CODE_FAILED
            );
        }

        $method = strtoupper($request->requestMethod ?? 'GET');
        $path = self::canonicalPath($request->requestedUrl ?? '/');
        $rawBody = $request->rawBody;
        $expectedCandidates = [];
        foreach ($secrets as $secret) {
            $expectedCandidates[] = self::sign($method, $path, $timestamp, $rawBody, $secret);
        }

        $provided = self::normalizeSignature($signatureHeader);
        foreach ($expectedCandidates as $expected) {
            if (hash_equals($expected, $provided)) {
                return;
            }
        }

        throw new InternalServiceException(
            InternalServiceException::CODE_FAILED . ': internal trust signature mismatch',
            401,
            InternalServiceException::CODE_FAILED
        );
    }

    /**
     * Build hex HMAC-SHA256 signature for a family call.
     */
    public static function sign(
        string $method,
        string $path,
        int $timestamp,
        string $rawBody,
        string $secret
    ): string {
        $canonical = self::buildCanonical(
            strtoupper($method),
            self::canonicalPath($path),
            $timestamp,
            self::bodyHash($rawBody)
        );

        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Headers a caller should attach for {@see enforce()}.
     *
     * @return array{X-Gemvc-Internal-Timestamp: string, X-Gemvc-Internal-Signature: string}
     */
    public static function callerHeaders(
        string $method,
        string $path,
        string $rawBody,
        ?string $secret = null,
        ?int $timestamp = null
    ): array {
        $secret ??= self::primarySecret();
        if ($secret === null || $secret === '') {
            throw new InternalServiceException(
                InternalServiceException::CODE_MISCONFIGURED . ': GEMVC_INTERNAL_SECRET is not configured',
                500,
                InternalServiceException::CODE_MISCONFIGURED
            );
        }

        $timestamp ??= time();
        $signature = self::sign($method, $path, $timestamp, $rawBody, $secret);

        return [
            'X-Gemvc-Internal-Timestamp' => (string) $timestamp,
            'X-Gemvc-Internal-Signature' => $signature,
        ];
    }

    public static function buildCanonical(
        string $method,
        string $path,
        int $timestamp,
        string $bodyHash
    ): string {
        return strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $bodyHash;
    }

    public static function bodyHash(string $rawBody): string
    {
        return hash('sha256', $rawBody);
    }

    /**
     * Path only (no query). Leading slash preserved; empty → `/`.
     */
    public static function canonicalPath(string $urlOrPath): string
    {
        $path = parse_url($urlOrPath, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $q = strpos($urlOrPath, '?');
            $path = $q === false ? $urlOrPath : substr($urlOrPath, 0, $q);
        }
        if ($path === '') {
            return '/';
        }

        return $path;
    }

    public static function skewSeconds(): int
    {
        $raw = self::envString(self::ENV_SKEW_SECONDS);
        if ($raw === null || $raw === '') {
            return self::DEFAULT_SKEW_SECONDS;
        }
        $n = (int) $raw;

        return $n > 0 ? $n : self::DEFAULT_SKEW_SECONDS;
    }

    /**
     * @return list<string>
     */
    public static function loadSecrets(): array
    {
        $out = [];
        $current = self::envString(self::ENV_SECRET);
        if ($current !== null && $current !== '') {
            $out[] = $current;
        }
        $previous = self::envString(self::ENV_SECRET_PREVIOUS);
        if ($previous !== null && $previous !== '' && !in_array($previous, $out, true)) {
            $out[] = $previous;
        }

        return $out;
    }

    public static function primarySecret(): ?string
    {
        $secrets = self::loadSecrets();

        return $secrets[0] ?? null;
    }

    private static function normalizeSignature(string $signature): string
    {
        $signature = trim($signature);
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, 7);
        }

        return strtolower($signature);
    }

    private static function envString(string $name): ?string
    {
        if (isset($_ENV[$name]) && is_string($_ENV[$name])) {
            return $_ENV[$name];
        }
        $fromGetenv = getenv($name);
        if (is_string($fromGetenv)) {
            return $fromGetenv;
        }

        return null;
    }
}

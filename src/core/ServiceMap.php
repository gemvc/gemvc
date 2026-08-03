<?php

namespace Gemvc\Core;

/**
 * Static sibling discovery from {@see self::ENV_SERVICES_JSON}.
 *
 * Example: GEMVC_SERVICES_JSON={"auth":"http://noam-auth","billing":"http://billing"}
 */
final class ServiceMap
{
    public const ENV_SERVICES_JSON = 'GEMVC_SERVICES_JSON';

    /**
     * @return array<string, string> service name => base URL
     * @throws ServiceCallException
     */
    public static function all(): array
    {
        $raw = self::envString(self::ENV_SERVICES_JSON);
        if ($raw === null || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ServiceCallException(
                ServiceCallException::CODE_MAP_INVALID . ': GEMVC_SERVICES_JSON is not valid JSON',
                ServiceCallException::CODE_MAP_INVALID,
                0,
                $e
            );
        }

        if (!is_array($decoded)) {
            throw new ServiceCallException(
                ServiceCallException::CODE_MAP_INVALID . ': GEMVC_SERVICES_JSON must be a JSON object',
                ServiceCallException::CODE_MAP_INVALID
            );
        }

        $out = [];
        foreach ($decoded as $name => $url) {
            if (!is_string($name) || $name === '' || !is_string($url) || $url === '') {
                throw new ServiceCallException(
                    ServiceCallException::CODE_MAP_INVALID . ': GEMVC_SERVICES_JSON entries must be string name => string base URL',
                    ServiceCallException::CODE_MAP_INVALID
                );
            }
            $out[$name] = rtrim($url, '/');
        }

        return $out;
    }

    /**
     * @throws ServiceCallException
     */
    public static function baseUrl(string $serviceName): string
    {
        $map = self::all();
        if (!isset($map[$serviceName])) {
            throw new ServiceCallException(
                ServiceCallException::CODE_UNKNOWN_SERVICE . ': service "' . $serviceName . '" not found in GEMVC_SERVICES_JSON',
                ServiceCallException::CODE_UNKNOWN_SERVICE
            );
        }

        return $map[$serviceName];
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

<?php

declare(strict_types=1);

namespace Gemvc\Core\Documentation;

use Gemvc\Helper\ProjectHelper;
use ReflectionClass;

/**
 * Loads developer-owned fake JSON response examples.
 *
 * Files: {app}/response_example/{ApiShortName}.{method}.json
 * Does not capture HTTP traffic, open PDO, or execute API methods.
 */
final class ResponseExampleLoader
{
    /**
     * Directory `app/response_example` when the project has an app folder.
     */
    public static function defaultDirectory(): ?string
    {
        try {
            return ProjectHelper::appDir() . DIRECTORY_SEPARATOR . 'response_example';
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param class-string $apiClass
     */
    public static function load(string $apiClass, string $method, ?string $examplesDir = null): ResponseExampleLoadResult
    {
        if (!self::isSafeIdentifier($method)) {
            return ResponseExampleLoadResult::invalid();
        }

        try {
            $short = (new ReflectionClass($apiClass))->getShortName();
        } catch (\ReflectionException) {
            return ResponseExampleLoadResult::invalid();
        }

        if (!self::isSafeIdentifier($short)) {
            return ResponseExampleLoadResult::invalid();
        }

        $dir = $examplesDir ?? self::defaultDirectory();
        if ($dir === null || $dir === '') {
            return ResponseExampleLoadResult::missing();
        }

        $root = realpath($dir);
        if ($root === false || !is_dir($root)) {
            return ResponseExampleLoadResult::missing();
        }

        $filename = $short . '.' . $method . '.json';
        $candidate = $root . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($candidate)) {
            return ResponseExampleLoadResult::missing();
        }

        $realFile = realpath($candidate);
        if ($realFile === false || !self::pathIsInside($realFile, $root)) {
            return ResponseExampleLoadResult::invalid();
        }

        $raw = @file_get_contents($realFile);
        if (!is_string($raw) || $raw === '') {
            return ResponseExampleLoadResult::invalid();
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ResponseExampleLoadResult::invalid();
        }

        if (!self::isEnvelope($decoded)) {
            return ResponseExampleLoadResult::invalid();
        }

        /** @var array<string, mixed> $decoded */
        return ResponseExampleLoadResult::ok($decoded);
    }

    private static function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private static function pathIsInside(string $path, string $root): bool
    {
        $pathN = str_replace('\\', '/', $path);
        $rootN = rtrim(str_replace('\\', '/', $root), '/');

        return $pathN === $rootN || str_starts_with($pathN, $rootN . '/');
    }

    /**
     * @param mixed $decoded
     */
    private static function isEnvelope(mixed $decoded): bool
    {
        if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            return false;
        }

        if (!array_key_exists('response_code', $decoded) || !array_key_exists('message', $decoded) || !array_key_exists('data', $decoded)) {
            return false;
        }

        return is_int($decoded['response_code']) && is_string($decoded['message']);
    }
}

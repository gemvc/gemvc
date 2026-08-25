<?php

declare(strict_types=1);

namespace Gemvc\Core\Documentation;

use Gemvc\Database\Table;
use ReflectionClass;

/**
 * Single response-example pipeline: PHP callers use ApiService::mockResponse().
 *
 * File (if valid) > CRUD payload inference > [].
 * Invalid files do not fall through to inference.
 */
final class ResponseExampleResolver
{
    /** Methods whose success `data` is a Table payload object or list of objects. */
    private const PAYLOAD_EXAMPLE_METHODS = ['create', 'read', 'list', 'update'];

    /**
     * @param class-string $apiClass
     * @param class-string|null $tableClass
     * @return array<string, mixed>
     */
    public static function resolve(string $apiClass, string $method, ?string $examplesDir = null, ?string $tableClass = null): array
    {
        $loaded = ResponseExampleLoader::load($apiClass, $method, $examplesDir);
        if ($loaded->isOk() && is_array($loaded->data)) {
            if (!self::validateFixture($method, $loaded->data, $apiClass, $tableClass)) {
                return [];
            }

            return $loaded->data;
        }
        if ($loaded->fileFound) {
            return [];
        }

        $inferred = self::infer($apiClass, $method, $tableClass);
        return $inferred ?? [];
    }

    /**
     * @param class-string $apiClass
     * @param class-string|null $tableClass
     * @return class-string<Table>|null
     */
    public static function resolveTableClass(string $apiClass, ?string $tableClass = null): ?string
    {
        if ($tableClass !== null) {
            if (is_subclass_of($tableClass, Table::class)) {
                /** @var class-string<Table> $tableClass */
                return $tableClass;
            }

            return null;
        }

        try {
            $short = (new ReflectionClass($apiClass))->getShortName();
        } catch (\ReflectionException) {
            return null;
        }

        $candidate = 'App\\Table\\' . $short . 'Table';
        if (class_exists($candidate) && is_subclass_of($candidate, Table::class)) {
            /** @var class-string<Table> $candidate */
            return $candidate;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fixture
     * @param class-string $apiClass
     * @param class-string|null $tableClass
     */
    public static function validateFixture(string $method, array $fixture, string $apiClass, ?string $tableClass = null): bool
    {
        if (!in_array($method, self::PAYLOAD_EXAMPLE_METHODS, true)) {
            return true;
        }

        $resolved = self::resolveTableClass($apiClass, $tableClass);
        if ($resolved === null) {
            return true;
        }

        $allowed = $resolved::payloadFieldNames();
        $data = $fixture['data'] ?? null;
        if ($data === null) {
            return true;
        }

        if ($method === 'list') {
            if (!is_array($data) || !array_is_list($data)) {
                return false;
            }
            foreach ($data as $row) {
                if (!is_array($row) || !self::rowMatchesPayload($row, $allowed, $resolved)) {
                    return false;
                }
            }

            return true;
        }

        if (!is_array($data) || array_is_list($data)) {
            return true;
        }

        return self::rowMatchesPayload($data, $allowed, $resolved);
    }

    /**
     * @param class-string $apiClass
     * @param class-string|null $tableClass
     * @return array<string, mixed>|null
     */
    public static function infer(string $apiClass, string $method, ?string $tableClass = null): ?array
    {
        if (!in_array($method, self::PAYLOAD_EXAMPLE_METHODS, true)) {
            return null;
        }

        $resolved = self::resolveTableClass($apiClass, $tableClass);
        if ($resolved === null) {
            return null;
        }

        $row = self::exampleRow($resolved);
        $list = $method === 'list';

        [$code, $message] = match ($method) {
            'create' => [201, 'created'],
            'update' => [209, 'updated'],
            default => [200, 'OK'],
        };

        return [
            'response_code' => $code,
            'message' => $message,
            'count' => 1,
            'service_message' => $message,
            'data' => $list ? [$row] : $row,
        ];
    }

    /**
     * @param array<mixed> $row
     * @param list<string> $allowed
     * @param class-string<Table> $tableClass
     */
    private static function rowMatchesPayload(array $row, array $allowed, string $tableClass): bool
    {
        if (array_is_list($row)) {
            return false;
        }

        $allowedFlip = array_fill_keys($allowed, true);
        $byName = [];
        foreach ($tableClass::payloadFields() as $field) {
            $byName[$field['name']] = $field;
        }

        foreach ($row as $key => $value) {
            if (!is_string($key) || $key === '' || $key[0] === '_') {
                return false;
            }
            if (!isset($allowedFlip[$key])) {
                return false;
            }
            $field = $byName[$key];
            if (!self::valueMatchesType($value, $field['type'], $field['nullable'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param class-string<Table> $tableClass
     * @return array<string, mixed>
     */
    private static function exampleRow(string $tableClass): array
    {
        $row = [];
        foreach ($tableClass::payloadFields() as $field) {
            $row[$field['name']] = self::exampleValue($field['name'], $field['type']);
        }

        return $row;
    }

    private static function exampleValue(string $name, string $type): mixed
    {
        $lowerName = strtolower($name);
        $base = strtolower($type);
        if (str_starts_with($base, 'decimal')) {
            return '19.99';
        }

        if ($lowerName === 'email' || $base === 'email') {
            return 'developer@example.com';
        }

        return match ($base) {
            'int', 'integer', 'positive_int' => 1,
            'float', 'double', 'number' => 1.0,
            'bool', 'boolean' => true,
            'date' => '2026-08-24',
            'datetime', 'timestamp' => '2026-08-24 12:00:00',
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'json', 'jsonb', 'array' => [],
            default => 'Example',
        };
    }

    private static function valueMatchesType(mixed $value, string $type, bool $nullable): bool
    {
        if ($value === null) {
            return $nullable;
        }

        $base = strtolower($type);
        if (str_starts_with($base, 'decimal')) {
            return is_string($value) || is_int($value) || is_float($value);
        }

        return match ($base) {
            'int', 'integer', 'positive_int' => is_int($value),
            'float', 'double', 'number' => is_int($value) || is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array', 'json', 'jsonb' => is_array($value),
            default => true,
        };
    }
}

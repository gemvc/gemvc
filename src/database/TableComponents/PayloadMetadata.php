<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Static API-visible field metadata for Table / ViewTable.
 *
 * This is the payload contract (what a client may see), not SQL/select metadata.
 * Uses class-default {@see $_type_map} — does not construct Table, open PDO, or run SQL.
 *
 * If an application mutates {@see $_type_map} in a constructor, that runtime map is not visible here.
 */
final class PayloadMetadata
{
    /**
     * @param class-string $tableClass
     * @return list<array{name: string, type: string, php_type: string, nullable: bool}>
     */
    public static function fields(string $tableClass): array
    {
        $reflection = new ReflectionClass($tableClass);
        $typeMap = self::defaultTypeMap($reflection);
        $fields = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            if ($name[0] === '_') {
                continue;
            }

            $phpType = self::phpTypeString($property->getType());
            $mapType = $typeMap[$name] ?? null;
            $nullable = self::isNullable($property, $mapType);
            $type = self::canonicalType($mapType, $phpType);

            $fields[] = [
                'name' => $name,
                'type' => $type,
                'php_type' => $phpType,
                'nullable' => $nullable,
            ];
        }

        return $fields;
    }

    /**
     * @param class-string $tableClass
     * @return list<string>
     */
    public static function fieldNames(string $tableClass): array
    {
        $names = [];
        foreach (self::fields($tableClass) as $field) {
            $names[] = $field['name'];
        }

        return $names;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, string>
     */
    private static function defaultTypeMap(ReflectionClass $reflection): array
    {
        if (!$reflection->hasProperty('_type_map')) {
            return [];
        }

        $property = $reflection->getProperty('_type_map');
        if (!$property->hasDefaultValue()) {
            return [];
        }

        $default = $property->getDefaultValue();
        if (!is_array($default)) {
            return [];
        }

        $map = [];
        foreach ($default as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    private static function canonicalType(?string $mapType, string $phpType): string
    {
        if ($mapType !== null && $mapType !== '') {
            return str_starts_with($mapType, '?') ? substr($mapType, 1) : $mapType;
        }

        $base = ltrim($phpType, '?');
        $unionParts = explode('|', $base);
        $withoutNull = array_values(array_filter($unionParts, static fn(string $part): bool => $part !== 'null'));
        if ($withoutNull === []) {
            return 'mixed';
        }

        return $withoutNull[0];
    }

    private static function isNullable(ReflectionProperty $property, ?string $mapType): bool
    {
        if ($mapType !== null && str_starts_with($mapType, '?')) {
            return true;
        }

        $type = $property->getType();
        if ($type === null) {
            return false;
        }

        return $type->allowsNull();
    }

    private static function phpTypeString(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                return '?' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $inner) {
                $parts[] = self::phpTypeString($inner);
            }

            return implode('|', $parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = [];
            foreach ($type->getTypes() as $inner) {
                $parts[] = self::phpTypeString($inner);
            }

            return implode('&', $parts);
        }

        return 'mixed';
    }
}

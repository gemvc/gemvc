<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

/**
 * Property Caster for Table Class
 * 
 * Handles type casting and result hydration for database operations.
 * Extracted from Table class to follow Single Responsibility Principle.
 */
class PropertyCaster
{
    /**
     * @param array<string, string> $typeMap Type mapping for properties (e.g., ['id' => 'int', 'name' => 'string'])
     */
    public function __construct(
        private array $typeMap
    ) {}
    
    /**
     * Cast database value to appropriate PHP type
     * 
     * Handles type conversion based on the type map, including:
     * - Nullable types (e.g., '?int', '?string')
     * - Primitive types (int, float, bool, string)
     * - Complex types (datetime, date, array, json)
     * 
     * @param string $property Property name
     * @param mixed $value Database value
     * @return mixed Properly typed value
     */
    public function castValue(string $property, mixed $value): mixed
    {
        // Handle NULL values first - return as-is if no type mapping
        if ($value === null) {
            if (!isset($this->typeMap[$property])) {
                return null;
            }
            // Check if type supports nullable (starts with '?' or contains 'null')
            $type = $this->typeMap[$property];
            if (str_starts_with($type, '?') || str_contains($type, 'null')) {
                return null;
            }
            // For non-nullable types, return appropriate default
            // This prevents type errors when assigning to typed properties
            return match($type) {
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                'string' => '',
                'array' => [],
                'datetime', 'date' => date('Y-m-d H:i:s'),
                default => null,
            };
        }
        
        if (!isset($this->typeMap[$property])) {
            return $value;
        }
        
        $type = $this->typeMap[$property];
        
        // Handle nullable types (e.g., '?int', '?string')
        $isNullable = str_starts_with($type, '?');
        if ($isNullable) {
            $type = substr($type, 1); // Remove '?' prefix
        }
        
        switch ($type) {
            case 'int':
                if (!is_numeric($value)) {
                    // Log warning in dev mode, return 0 for production
                    if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                        error_log("Warning: Invalid int value for property '{$property}': " . var_export($value, true));
                    }
                    return $isNullable ? null : 0;
                }
                return (int)$value;
                
            case 'float':
                if (!is_numeric($value)) {
                    if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                        error_log("Warning: Invalid float value for property '{$property}': " . var_export($value, true));
                    }
                    return $isNullable ? null : 0.0;
                }
                return (float)$value;
                
            case 'bool':
                // Handle various boolean representations
                if (is_bool($value)) {
                    return $value;
                }
                if (is_int($value)) {
                    return $value !== 0;
                }
                if (is_string($value)) {
                    $lower = strtolower(trim($value));
                    // Handle common boolean string representations
                    if (in_array($lower, ['1', 'true', 'yes', 'on', 'y'], true)) {
                        return true;
                    }
                    if (in_array($lower, ['0', 'false', 'no', 'off', 'n', ''], true)) {
                        return false;
                    }
                }
                return (bool)$value;
                
            case 'datetime':
            case 'date':
                // Return as string (Y-m-d H:i:s format) instead of DateTime object
                // This ensures compatibility with string-typed properties and JSON serialization
                if (!is_string($value) || trim($value) === '') {
                    return $isNullable ? null : date('Y-m-d H:i:s');
                }
                // Validate it's a valid datetime string, but return as string
                try {
                    $dt = new \DateTime($value);
                    return $dt->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // Log error in dev mode
                    if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                        error_log("Warning: Invalid datetime value for property '{$property}': {$value}. Error: " . $e->getMessage());
                    }
                    return $isNullable ? null : date('Y-m-d H:i:s');
                }
                
            case 'array':
            case 'json':
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded ?? [];
                    }
                    // If JSON decode fails, try to parse as comma-separated or return empty array
                    if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                        error_log("Warning: Invalid JSON value for property '{$property}': {$value}");
                    }
                }
                return $isNullable ? null : [];
                
            case 'string':
                // Convert to string, handle null
                // Note: $value cannot be null here because null is handled earlier
                /** @var string|int|float|bool $value */
                return strval($value);
                
            default:
                return $value;
        }
    }
    
    /**
     * Hydrate model properties from database row
     * 
     * Iterates through the row array and sets properties on the instance
     * using type casting via castValue().
     * 
     * @param object $instance The object instance to hydrate
     * @param array<string, mixed> $row Database row as associative array
     * @return void
     */
    public function fetchRow(object $instance, array $row): void
    {
        foreach ($row as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->$key = $this->castValue($key, $value);
            }
        }
    }
}


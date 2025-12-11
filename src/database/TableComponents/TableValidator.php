<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

use Gemvc\Database\Table;

/**
 * Table Validator for Table Class
 * 
 * Handles validation logic for database operations.
 * Extracted from Table class to follow Single Responsibility Principle.
 */
class TableValidator
{
    /**
     * @param Table $table The table instance to validate against
     */
    public function __construct(
        private Table $table
    ) {}
    
    /**
     * Validate essential properties and show error if not valid
     * 
     * Checks if all specified properties exist on the table instance.
     * Sets error message on the table instance if validation fails.
     * 
     * @param array<string> $properties Properties to validate
     * @return bool True if all properties exist
     */
    public function validateProperties(array $properties): bool
    {
        foreach ($properties as $property) {
            if (!property_exists($this->table, $property)) {
                $this->table->setError("Property '{$property}' is not set in table");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate ID parameter
     * 
     * @deprecated This method is kept for backward compatibility.
     *             Use validatePrimaryKey() instead for flexible primary key support.
     * 
     * @param int $id ID to validate
     * @param string $operation Operation name for error message
     * @return bool True if ID is valid
     */
    public function validateId(int $id, string $operation = 'operation'): bool
    {
        if ($id < 1) {
            $this->table->setError("ID must be a positive integer for {$operation} in {$this->table->getTable()}");
            return false;
        }
        return true;
    }
    
    /**
     * Validate primary key value
     * 
     * Validates primary key based on the configured primary key type:
     * - 'int': Must be a positive integer
     * - 'string' or 'uuid': Must be a non-empty string
     * 
     * @param int|string|null $value Primary key value to validate
     * @param string $column Primary key column name
     * @param string $type Primary key type ('int', 'string', 'uuid')
     * @param string $operation Operation name for error message
     * @return bool True if primary key is valid
     */
    public function validatePrimaryKey(int|string|null $value, string $column, string $type, string $operation = 'operation'): bool
    {
        if ($value === null) {
            $this->table->setError("Primary key '{$column}' must be set for {$operation} in {$this->table->getTable()}");
            return false;
        }
        
        if ($type === 'int') {
            if (!is_int($value) || $value < 1) {
                $this->table->setError("Primary key '{$column}' must be a positive integer for {$operation} in {$this->table->getTable()}");
                return false;
            }
        } elseif ($type === 'uuid' || $type === 'string') {
            if (!is_string($value) || trim($value) === '') {
                $this->table->setError("Primary key '{$column}' must be a non-empty string for {$operation} in {$this->table->getTable()}");
                return false;
            }
        }
        
        return true;
    }
}


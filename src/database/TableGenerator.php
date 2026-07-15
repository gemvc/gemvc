<?php
namespace Gemvc\Database;

use Gemvc\Database\Dialect\DialectResolver;
use Gemvc\Database\Dialect\SqlDialectInterface;
use PDO;
use PDOException;

/**
 * TableGenerator automatically creates database tables from PHP objects using reflection.
 * 
 * This class analyzes object properties and creates appropriate database tables with
 * column types determined by the PHP property types. It provides a simple ORM-like
 * functionality for schema generation.
 *
 * All engine-specific SQL (identifier quoting, type mapping, introspection, DDL syntax)
 * is delegated to a SqlDialectInterface implementation, so this class works unmodified
 * against MySQL, PostgreSQL, and SQLite.
 */
class TableGenerator {
    private ?PDO $pdo = null;
    private SqlDialectInterface $dialect;
    /** @var array<string, mixed> */
    private array $columnProperties = [];
    /** @var array<int, string> */
    private array $indexedProperties = [];
    /** @var array<int, string> */
    private array $uniqueIndexedProperties = [];
    private string $error = '';

    public function __construct(PDO $pdo, ?SqlDialectInterface $dialect = null) {
        $this->pdo = $pdo;
        $this->dialect = $dialect ?? DialectResolver::resolve($pdo);
    }

    public function getError(): string {
        return $this->error;
    }

    /**
     * Create a table from an object
     * @param object $object The object to create a table from
     * @param string|null $tableName The name of the table to create
     * @return bool True if the table was created successfully, false otherwise
     */
    public function createTableFromObject(object $object, ?string $tableName = null): bool {
        if (!$tableName) {
            if (!method_exists($object, 'getTable')) {
                $this->error = 'public function getTable() not found in object';
                return false;
            }
            $tableName = $object->getTable();
            if (!$tableName) {
                $this->error = 'function getTable() returned null string. Please define it and give table a name';
                return false;
            }
        }
        
        // Process schema constraints to apply timestamp defaults
        $timestampColumns = $this->processSchemaConstraintsForDefaults($object);
        
        $reflection = new \ReflectionClass($object);
        $properties = $reflection->getProperties();
        $columns = [];
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if ($this->shouldSkipProperty($property)) continue;
            $propertyType = $this->getPropertyType($property, $object);
            $sqlType = $this->mapTypeToSqlType($propertyType, $propertyName);

            // Determine nullability
            $isNullable = false;
            if ($property->hasType()) {
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $isNullable = $type->allowsNull();
                }
            }
            $nullSql = $isNullable ? 'NULL' : 'NOT NULL';

            // Separate DEFAULT clause from other column properties
            $defaultClause = '';
            $otherProperties = '';
            
            if (isset($this->columnProperties[$propertyName]) && is_string($this->columnProperties[$propertyName])) {
                $properties = $this->columnProperties[$propertyName];
                
                // Extract DEFAULT clause if present - handle CURRENT_TIMESTAMP and other defaults
                // Match: DEFAULT CURRENT_TIMESTAMP, DEFAULT 'value', DEFAULT 123, etc.
                if (preg_match('/DEFAULT\s+(CURRENT_TIMESTAMP|[\'"]?[^\'"\s,]+[\'"]?)/i', $properties, $matches)) {
                    $defaultClause = ' DEFAULT ' . $matches[1];
                    // Remove DEFAULT from other properties
                    $otherProperties = preg_replace('/DEFAULT\s+(?:CURRENT_TIMESTAMP|[\'"]?[^\'"\s,]+[\'"]?)/i', '', $properties);
                    $otherProperties = trim($otherProperties ?? '');
                } else {
                    $otherProperties = $properties;
                }
            }
            
            // Build column definition: TYPE [other properties] NOT NULL [DEFAULT]
            $columnDef = $this->dialect->quoteIdentifier($propertyName) . " $sqlType";
            if (!empty($otherProperties)) {
                $columnDef .= ' ' . $otherProperties;
            }
            $columnDef .= ' ' . $nullSql;
            if (!empty($defaultClause)) {
                $columnDef .= $defaultClause;
            }
            
            $columns[] = $columnDef;
        }
        if (empty($columns)) {
            $this->error = 'No valid properties found in object to create table columns';
            return false;
        }
        $query = $this->dialect->createTableSql($tableName, $columns);
        try {
            if ($this->pdo === null) {
                $this->error = 'PDO connection is not available';
                return false;
            }
            $this->pdo->exec($query);
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Mark a column in existing table as unique
     * 
     * @param string $tableName The name of the table
     * @param string $columnName The name of the column to make unique
     * @param bool $dropExistingIndex Whether to drop any existing index on this column first
     * @return bool True if successful, false otherwise
     */
    public function makeColumnUnique(string $tableName, string $columnName, bool $dropExistingIndex = true): bool {
        
        if (!$this->isValidTableName($tableName)) {
            $this->error = "Invalid table name format: $tableName";
            return false;
        }
        
        // Generate the index name
        $indexName = "uidx_{$tableName}_{$columnName}";
        
        try {
            if ($this->pdo === null) {
                $this->error = 'PDO connection is not available';
                return false;
            }
            
            // Start transaction
            $this->pdo->beginTransaction();
            if (!$this->dialect->columnExists($this->pdo, $tableName, $columnName)) {
                $this->error = "Column '$columnName' does not exist in table '$tableName'";
                $this->pdo->rollBack();
                return false;
            }
            
            // If requested, drop any existing indexes on this column
            if ($dropExistingIndex) {
                // Find any existing indexes on this column
                $indexes = $this->dialect->getIndexesForColumn($this->pdo, $tableName, $columnName);
                
                foreach ($indexes as $existingIndexName) {
                    // Don't drop primary key
                    if ($existingIndexName !== 'PRIMARY') {
                        $this->pdo->exec($this->dialect->dropIndexSql($tableName, $existingIndexName));
                    }
                }
            }
            
            // Create the unique index
            $this->pdo->exec($this->dialect->createUniqueIndexSql($tableName, $indexName, [$columnName]));
            $this->pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Set column properties like NOT NULL, DEFAULT value, CHECK constraints, etc.
     * 
     * @param string $propertyName The name of the property
     * @param string $columnProperties SQL properties to add to the column definition
     * @return self For method chaining
     */
    public function setColumnProperties(string $propertyName, string $columnProperties): self {
        $this->columnProperties[$propertyName] = $columnProperties;
        return $this;
    }
    
    /**
     * Set a column as NOT NULL
     * 
     * @param string $propertyName The name of the property
     * @return self For method chaining
     */
    public function setNotNull(string $propertyName): self {
        $properties = $this->columnProperties[$propertyName] ?? '';
        if (is_string($properties) && strpos($properties, 'NOT NULL') === false) {
            $this->columnProperties[$propertyName] = trim($properties . ' NOT NULL');
        } elseif (!is_string($properties)) {
            $this->columnProperties[$propertyName] = 'NOT NULL';
        }
        return $this;
    }
    
    /**
     * Set a default value for a column
     * 
     * @param string $propertyName The name of the property
     * @param mixed $defaultValue The default value
     * @return self For method chaining
     */
    public function setDefault(string $propertyName, mixed $defaultValue): self {
        $properties = $this->columnProperties[$propertyName] ?? '';
        
        // Handle different types of default values
        if (is_string($defaultValue)) {
            // Special handling for SQL functions like CURRENT_TIMESTAMP
            if (strtoupper($defaultValue) === 'CURRENT_TIMESTAMP' || 
                strtoupper($defaultValue) === 'CURRENT_TIMESTAMP()' ||
                preg_match('/^[A-Z_]+\(\)$/', strtoupper($defaultValue))) {
                // SQL functions should not be quoted
                $defaultSql = "DEFAULT " . $defaultValue;
            } else {
                $defaultSql = "DEFAULT '" . $this->escapeString($defaultValue) . "'";
            }
        } elseif (is_bool($defaultValue)) {
            $defaultSql = "DEFAULT " . ($defaultValue ? '1' : '0');
        } elseif (is_null($defaultValue)) {
            $defaultSql = "DEFAULT NULL";
        } else {
            $defaultSql = "DEFAULT " . var_export($defaultValue, true);
        }
        
        // Remove any existing DEFAULT clause
        if (is_string($properties)) {
            if (preg_match('/DEFAULT\s+[^,]+/', $properties)) {
                $properties = preg_replace('/DEFAULT\s+[^,]+/', $defaultSql, $properties);
            } else {
                $properties = trim($properties . ' ' . $defaultSql);
            }
        } else {
            $properties = $defaultSql;
        }
        
        $this->columnProperties[$propertyName] = $properties;
        return $this;
    }
    
    /**
     * Add a CHECK constraint to a column
     * 
     * @param string $propertyName The name of the property
     * @param string $checkExpression The SQL expression for the check constraint
     * @return self For method chaining
     */
    public function addCheck(string $propertyName, string $checkExpression): self {
        $properties = $this->columnProperties[$propertyName] ?? '';
        $checkSql = "CHECK ($checkExpression)";
        
        if (is_string($properties)) {
            $this->columnProperties[$propertyName] = trim($properties . ' ' . $checkSql);
        } else {
            $this->columnProperties[$propertyName] = $checkSql;
        }
        return $this;
    }
    
    /**
     * Escape a string for SQL
     * 
     * @param string $value The string to escape
     * @return string The escaped string
     */
    private function escapeString(string $value): string {
        return str_replace("'", "''", $value);
    }
    
    /**
     * Mark a property to be indexed in the database
     * 
     * @param string $propertyName The name of the property to index
     * @param bool $unique Whether the index should be unique
     * @return self For method chaining
     */
    public function addIndex(string $propertyName, bool $unique = false): self {
        if ($unique) {
            $this->uniqueIndexedProperties[] = $propertyName;
        } else {
            $this->indexedProperties[] = $propertyName;
        }
        return $this;
    }
    
    /**
     * Remove indexing from a property
     * 
     * @param string $propertyName The name of the property to remove indexing from
     * @return self For method chaining
     */
    public function removeIndex(string $propertyName): self {
        $this->indexedProperties = array_filter($this->indexedProperties, function($prop) use ($propertyName) {
            return $prop !== $propertyName;
        });
        
        $this->uniqueIndexedProperties = array_filter($this->uniqueIndexedProperties, function($prop) use ($propertyName) {
            return $prop !== $propertyName;
        });
        
        return $this;
    }
    

    /**
     * Remove a column from an existing table
     * 
     * @param string $tableName The name of the table
     * @param string $columnName The name of the column to remove
     * @return bool True if successful, false otherwise
     */
    public function removeColumn(string $tableName, string $columnName): bool { 
        if (!$this->isValidTableName($tableName)) {
            $this->error = "Invalid table name format: $tableName";
            return false;
        }
        
        try {
            if ($this->pdo === null) {
                $this->error = 'PDO connection is not available';
                return false;
            }
            
            // Start transaction
            $this->pdo->beginTransaction();
            if (!$this->dialect->columnExists($this->pdo, $tableName, $columnName)) {
                $this->error = "Column '$columnName' does not exist in table '$tableName'";
                $this->pdo->rollBack();
                return false;
            }
            
            // Check if the column is part of any indexes and drop them first
            $indexes = $this->dialect->getIndexesForColumn($this->pdo, $tableName, $columnName);
            $processedIndexes = [];
            
            foreach ($indexes as $indexName) {
                // Skip already processed indexes and PRIMARY keys (dropping a primary key via
                // removeColumn() is not supported - preserved from historical behavior)
                if (in_array($indexName, $processedIndexes, true) || $indexName === 'PRIMARY') {
                    continue;
                }
                
                $this->pdo->exec($this->dialect->dropIndexSql($tableName, $indexName));
                
                $processedIndexes[] = $indexName;
            }
            
            // Remove the column
            $this->pdo->exec($this->dialect->dropColumnSql($tableName, $columnName));
            $this->pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * Update an existing table based on changes in object properties
     * 
     * This method compares the object's current properties with the existing table structure and:
     * - Adds columns for new properties
     * - Updates columns for properties with changed types
     * - Removes columns for properties that no longer exist (if removeExtraColumns is true)
     * 
     * @param object $object The object with updated properties
     * @param string|null $tableName The name of the table to update or null to use object's getTable() method
     * @param bool $removeExtraColumns Whether to remove columns that don't exist in the object
     * @param bool $enforceNotNull Whether to enforce NOT NULL constraints
     * @param mixed $defaultValue The default value to use if enforcing NOT NULL
     * @return bool True if the update was successful, false otherwise
     */
    public function updateTable(
        object $object,
        ?string $tableName = null,
        bool $removeExtraColumns = false,
        bool $enforceNotNull = false,
        $defaultValue = null
    ): bool {
        if (!$this->pdo) {
            $this->error = 'No PDO connection.';
            return false;
        }

        if (!$tableName) {
            if (!method_exists($object, 'getTable')) {
                $this->error = 'public function getTable() not found in object';
                return false;
            }
            $tableName = $object->getTable();
            if (!$tableName) {
                $this->error = 'function getTable() returned null string. Please define it and give table a name';
                return false;
            }
        }

        try {
            // @phpstan-ignore-next-line
            if ($this->pdo === null) {
                $this->error = 'PDO connection is not available';
                return false;
            }
            
            // Ensure we're not in a transaction
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Test connection before starting transaction
            $this->pdo->query('SELECT 1');
            
            // Start transaction
            $this->pdo->beginTransaction();

            // Get existing columns
            $columnMap = $this->dialect->getColumns($this->pdo, $tableName);
            if (empty($columnMap)) {
                $this->error = "Failed to describe table or table has no columns.";
                $this->pdo->rollBack();
                return false;
            }

            // Process schema constraints to apply timestamp defaults
            $timestampColumns = $this->processSchemaConstraintsForDefaults($object);
            
            $reflection = new \ReflectionClass($object);
            $properties = $reflection->getProperties();
            $objectPropertyNames = [];
            $columnsToAdd = [];
            $columnsToModify = [];
            $columnsToRemove = [];

            // Get all property names from the object
            foreach ($properties as $property) {
                $propertyName = $property->getName();
                if ($this->shouldSkipProperty($property)) continue;
                
                $objectPropertyNames[] = $propertyName;
                $propertyType = $this->getPropertyType($property, $object);
                $sqlType = $this->mapTypeToSqlType($propertyType, $propertyName);
                
                // Determine nullability
                $isNullable = false;
                if ($property->hasType()) {
                    $type = $property->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $isNullable = $type->allowsNull();
                    }
                }
                $nullSql = $isNullable ? 'NULL' : 'NOT NULL';

                // Separate DEFAULT clause from other column properties
                $defaultClause = '';
                $otherProperties = '';
                
                if (isset($this->columnProperties[$propertyName]) && is_string($this->columnProperties[$propertyName])) {
                    $properties = $this->columnProperties[$propertyName];
                    
                    // Extract DEFAULT clause if present - handle CURRENT_TIMESTAMP and other defaults
                    // Match: DEFAULT CURRENT_TIMESTAMP, DEFAULT 'value', DEFAULT 123, etc.
                    if (preg_match('/DEFAULT\s+(CURRENT_TIMESTAMP|[\'"]?[^\'"\s,]+[\'"]?)/i', $properties, $matches)) {
                        $defaultClause = ' DEFAULT ' . $matches[1];
                        // Remove DEFAULT from other properties
                        $otherProperties = preg_replace('/DEFAULT\s+(?:CURRENT_TIMESTAMP|[\'"]?[^\'"\s,]+[\'"]?)/i', '', $properties);
                        $otherProperties = trim($otherProperties ?? '');
                    } else {
                        $otherProperties = $properties;
                    }
                }
                
                // Build column definition: TYPE [other properties] NOT NULL [DEFAULT]
                $columnDef = $sqlType;
                if (!empty($otherProperties)) {
                    $columnDef .= ' ' . $otherProperties;
                }
                $columnDef .= ' ' . $nullSql;
                if (!empty($defaultClause)) {
                    $columnDef .= $defaultClause;
                }

                if (!isset($columnMap[$propertyName])) {
                    $columnsToAdd[] = [
                        'name' => $propertyName,
                        'definition' => $columnDef
                    ];
                } else {
                    if ($propertyName === 'id') continue;
                    
                    // Compare types more accurately
                    $existingType = $this->dialect->toCanonicalType($columnMap[$propertyName]['type']);
                    $newTypeToken = strtolower(preg_replace('/\s+.*$/', '', $sqlType) ?? '');
                    $newType = $this->dialect->toCanonicalType($newTypeToken);
                    
                    $existingNull = $columnMap[$propertyName]['nullable'];
                    $newNull = $isNullable; // from Reflection
                    
                    // Check if DEFAULT value needs to be updated
                    $existingDefault = $columnMap[$propertyName]['default'];
                    $needsDefaultUpdate = false;
                    
                    // Check if this column should have DEFAULT CURRENT_TIMESTAMP based on schema constraints
                    $shouldHaveTimestampDefault = in_array($propertyName, $timestampColumns, true);
                    
                    if ($shouldHaveTimestampDefault) {
                        // This column should have DEFAULT CURRENT_TIMESTAMP
                        $hasCurrentTimestamp = $existingDefault !== null && (
                            stripos((string)$existingDefault, 'CURRENT_TIMESTAMP') !== false ||
                            $existingDefault === 'CURRENT_TIMESTAMP'
                        );
                        $needsDefaultUpdate = !$hasCurrentTimestamp;
                    } elseif (!empty($defaultClause)) {
                        // Check if we need DEFAULT CURRENT_TIMESTAMP from extracted clause
                        $wantsCurrentTimestamp = stripos($defaultClause, 'CURRENT_TIMESTAMP') !== false;
                        if ($wantsCurrentTimestamp) {
                            $hasCurrentTimestamp = $existingDefault !== null && (
                                stripos((string)$existingDefault, 'CURRENT_TIMESTAMP') !== false ||
                                $existingDefault === 'CURRENT_TIMESTAMP'
                            );
                            $needsDefaultUpdate = !$hasCurrentTimestamp;
                        }
                    }

                    if ($existingType !== $newType || $existingNull !== $newNull || $needsDefaultUpdate) {
                        // If changing from NULL to NOT NULL
                        if ($existingNull && !$newNull && $enforceNotNull) {
                            // Check for NULLs in the column
                            $q = $this->dialect->quoteIdentifier($tableName);
                            $qc = $this->dialect->quoteIdentifier($propertyName);
                            $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$q} WHERE {$qc} IS NULL");
                            $nullCount = $stmt !== false ? $stmt->fetchColumn() : 0;
                            if ($nullCount > 0) {
                                if ($defaultValue !== null) {
                                    // Update NULLs to default value
                                    if (is_string($defaultValue)) {
                                        $defaultSql = $this->pdo->quote($defaultValue);
                                        $this->pdo->exec("UPDATE {$q} SET {$qc} = $defaultSql WHERE {$qc} IS NULL");
                                    } else {
                                        $defaultSql = var_export($defaultValue, true);
                                        $this->pdo->exec("UPDATE {$q} SET {$qc} = $defaultSql WHERE {$qc} IS NULL");
                                    }
                                } else {
                                    $this->error = "Cannot set `$propertyName` to NOT NULL: $nullCount NULL values exist. Use --default to set a value.";
                                    return false;
                                }
                            }
                            // Now safe to alter column
                            // Build the DEFAULT clause with proper ordering
                            $finalDefaultClause = null;
                            if (!empty($defaultClause)) {
                                $finalDefaultClause = $defaultClause;
                            } elseif ($needsDefaultUpdate && $shouldHaveTimestampDefault) {
                                $finalDefaultClause = ' DEFAULT CURRENT_TIMESTAMP';
                            }
                            $columnsToModify[] = [
                                'name' => $propertyName,
                                'sqlType' => $sqlType,
                                'otherProperties' => $otherProperties,
                                'nullable' => false,
                                'defaultClause' => $finalDefaultClause,
                            ];
                        } else {
                            // Build the DEFAULT clause with proper ordering
                            $finalDefaultClause = null;
                            if (!empty($defaultClause)) {
                                $finalDefaultClause = $defaultClause;
                            } elseif ($needsDefaultUpdate) {
                                // Add DEFAULT CURRENT_TIMESTAMP if needed (either from timestamp constraint or extracted clause)
                                if ($shouldHaveTimestampDefault) {
                                    $finalDefaultClause = ' DEFAULT CURRENT_TIMESTAMP';
                                } elseif (isset($this->columnProperties[$propertyName]) && 
                                         is_string($this->columnProperties[$propertyName]) &&
                                         stripos($this->columnProperties[$propertyName], 'DEFAULT CURRENT_TIMESTAMP') !== false) {
                                    // Fallback: check columnProperties directly
                                    $finalDefaultClause = ' DEFAULT CURRENT_TIMESTAMP';
                                }
                            }
                            $columnsToModify[] = [
                                'name' => $propertyName,
                                'sqlType' => $sqlType,
                                'otherProperties' => $otherProperties,
                                'nullable' => $newNull,
                                'defaultClause' => $finalDefaultClause,
                            ];
                        }
                    }
                }
            }

            // Find columns to remove if removeExtraColumns is true
            if ($removeExtraColumns) {
                foreach ($columnMap as $columnName => $column) {
                    // Skip 'id' column and columns that exist in the object
                    if ($columnName === 'id' || in_array($columnName, $objectPropertyNames)) {
                        continue;
                    }
                    $columnsToRemove[] = $columnName;
                }
            }

            // If no changes needed, commit and return
            if (empty($columnsToAdd) && empty($columnsToModify) && empty($columnsToRemove)) {
                $this->pdo->commit();
                return true;
            }

            // Execute all changes
            foreach ($columnsToAdd as $column) {
                $name = $column['name'];
                $definition = $column['definition'];
                $this->pdo->exec($this->dialect->addColumnSql($tableName, $name, $definition));
            }

            foreach ($columnsToModify as $column) {
                $statements = $this->dialect->alterColumnSql(
                    $tableName,
                    $column['name'],
                    $column['sqlType'],
                    $column['otherProperties'],
                    $column['nullable'],
                    $column['defaultClause']
                );
                if (empty($statements)) {
                    // Unsupported by this engine (e.g. SQLite can't ALTER COLUMN type/nullability
                    // without a full table rebuild) - skip with a clear signal rather than
                    // attempting invalid SQL.
                    $this->error = "Skipped altering column `{$column['name']}`: not supported by the '{$this->dialect->getName()}' dialect without a full table rebuild.";
                    continue;
                }
                foreach ($statements as $statement) {
                    $this->pdo->exec($statement);
                }
            }

            foreach ($columnsToRemove as $columnName) {
                $this->pdo->exec($this->dialect->dropColumnSql($tableName, $columnName));
            }

            // Commit the transaction
            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            // Rollback on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->error = $e->getMessage();
            return false;
        }
    }

    /*-------------------------------------------private methods-------------------------------------------*/

    /**
     * Determines if a property should be skipped during table creation
     * 
     * @param \ReflectionProperty $property The property to check
     * @return bool True if the property should be skipped
     */
    private function shouldSkipProperty(\ReflectionProperty $property): bool {
        // Skip static properties
        if ($property->isStatic()) {
            return true;
        }
        
        // Skip properties that start with an underscore (convention for non-persisted properties)
        if (str_starts_with($property->getName(), '_')) {
            return true;
        }
        
        // Skip constants (class constants should not be database columns)
        if ($property->isReadOnly() && $property->isPublic()) {
            // In PHP 8.1+ we can use isReadOnly() to detect constants/final properties
            return true;
        }
        
        // Could add more conditions here, like checking for specific annotations
        // or property name patterns that indicate non-database fields

        return false;
    }

    /**
     * Get the type of a property
     * 
     * @param \ReflectionProperty $property The property to get the type of
     * @param object $object The object instance (for value type detection)
     * @return string The property type
     */
    private function getPropertyType(\ReflectionProperty $property, object $object): string {
        $propertyName = $property->getName();
        
        // FIRST: Check $_type_map if it exists (this allows overriding PHP property types)
        // This is critical for types like 'text' and 'longtext' that can't be expressed as PHP property types
        try {
            $reflection = new \ReflectionClass($object);
            if ($reflection->hasProperty('_type_map')) {
                $typeMapProperty = $reflection->getProperty('_type_map');
                $typeMap = $typeMapProperty->getValue($object);
                if (is_array($typeMap) && isset($typeMap[$propertyName]) && is_string($typeMap[$propertyName])) {
                    return $typeMap[$propertyName];
                }
            }
        } catch (\Error | \Exception $e) {
            // Silently handle errors - fall through to PHP property type
        }
        
        // SECOND: Try to get type from PHP 7.4+ property type declaration
        if ($property->hasType()) {
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType) {
                return $type->getName();
            }
        }
        
        // THIRD: Fallback to runtime type detection if property is accessible and initialized
        try {
            if ($property->isInitialized($object)) {
                $value = $property->getValue($object);
                $type = gettype($value);
                
                if ($type === 'object' && $value instanceof \DateTime) {
                    return 'DateTime';
                }
                
                return $type;
            }
        } catch (\Error | \Exception $e) {
            // Silently handle errors from accessing uninitialized properties
        }
        
        // Default to text if type can't be determined
        return 'unknown';
    }

    /**
     * Map PHP type to SQL column type
     * 
     * @param string $phpType The PHP type
     * @param string $propertyName The property name (for special handling)
     * @return string The SQL column type
     */
    private function mapTypeToSqlType(string $phpType, string $propertyName): string {
        // Strip nullable prefix ('?string' → 'string', '?int' → 'int') before matching.
        // Without this, '?string' returned from $_type_map hits the default branch
        // and becomes TEXT instead of VARCHAR(255) / INT(11).
        $baseType = str_starts_with($phpType, '?') ? substr($phpType, 1) : $phpType;

        // Special handling for common field names
        if (strtolower($propertyName) === 'id') {
            return $this->dialect->idColumnDefinition();
        }
        if (str_ends_with(strtolower($propertyName), '_id')
            && in_array(strtolower($baseType), ['int', 'integer'], true)) {
            // Only force INT for _id columns whose PHP type is actually int/integer.
            // This prevents non-FK string columns that happen to end in '_id' from
            // being silently cast to INT and truncating values like "1.234567890".
            return $this->dialect->foreignKeyColumnType();
        }
        if (str_ends_with(strtolower($propertyName), 'email')) {
            // Email columns — VARCHAR(320) is the RFC maximum
            return 'VARCHAR(320)';
        }

        return $this->dialect->toEngineType($baseType);
    }

    /**
     * Process schema constraints to apply DEFAULT CURRENT_TIMESTAMP for timestamp columns
     * 
     * @param object $object The table object with defineSchema() method
     * @return array<string> Array of column names that should have DEFAULT CURRENT_TIMESTAMP
     */
    private function processSchemaConstraintsForDefaults(object $object): array {
        $timestampColumns = [];
        
        // Check if object has defineSchema method
        if (!method_exists($object, 'defineSchema')) {
            return $timestampColumns;
        }
        
        try {
            $schemaDefinition = $object->defineSchema();
            if (!is_array($schemaDefinition)) {
                return $timestampColumns;
            }
            
            // Process each constraint
            foreach ($schemaDefinition as $constraint) {
                // Check if it's an IndexConstraint with timestamp flag
                if (is_object($constraint) && method_exists($constraint, 'toArray')) {
                    $constraintData = $constraint->toArray();
                    
                    // Check if it's an index constraint with timestamp flag
                    if (isset($constraintData['type']) && $constraintData['type'] === 'index' 
                        && isset($constraintData['timestamp']) && !empty($constraintData['timestamp'])) {
                        
                        // Get the column(s) from the constraint
                        $columns = $constraintData['columns'] ?? null;
                        if ($columns === null) {
                            continue;
                        }
                        
                        // Handle both single column and array of columns
                        $columnNames = is_array($columns) ? $columns : [$columns];
                        
                        // Apply DEFAULT CURRENT_TIMESTAMP to each timestamp-indexed column
                        foreach ($columnNames as $columnName) {
                            if (is_string($columnName)) {
                                // Check if column type is datetime/timestamp compatible
                                $this->setTimestampDefault($columnName);
                                $timestampColumns[] = $columnName;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - schema processing errors shouldn't break table creation
            // The error will be caught later when applying constraints
        }
        
        return $timestampColumns;
    }
    
    /**
     * Set DEFAULT CURRENT_TIMESTAMP for a column
     * 
     * @param string $propertyName The name of the property/column
     * @return void
     */
    private function setTimestampDefault(string $propertyName): void {
        // Use setDefault() method which properly handles CURRENT_TIMESTAMP
        $this->setDefault($propertyName, 'CURRENT_TIMESTAMP');
    }

    /**
     * Validate if the table name is in a valid format
     * 
     * @param string $tableName The table name to validate
     * @return bool True if the table name is valid
     */
    private function isValidTableName(string $tableName): bool {
        // Table names should contain only alphanumeric characters, underscores, and should not start with a number
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName);
    }

    /**
     * Create a unique constraint across multiple columns
     * 
     * @param string $tableName The name of the table
     * @param array<string> $columnNames Array of column names to include in the unique constraint
     * @param string|null $indexName Optional custom name for the index (defaults to auto-generated name)
     * @param bool $dropExistingIndexes Whether to drop any existing indexes on these columns
     * @return bool True if successful, false otherwise
     */
    public function makeColumnsUniqueTogether(string $tableName, array $columnNames, ?string $indexName = null, bool $dropExistingIndexes = true): bool {
        
        if (empty($columnNames)) {
            $this->error = "No column names provided for combined unique index";
            return false;
        }
        
        if (!$this->isValidTableName($tableName)) {
            $this->error = "Invalid table name format: $tableName";
            return false;
        }
        
        // Generate default index name if not provided
        if ($indexName === null) {
            // Create a name based on table and columns, with length limitation
            $columnsStr = implode('_', array_map(function($col) {
                return substr($col, 0, 5); // Take first 5 chars of each column name
            }, $columnNames));
            
            $indexName = "uidx_{$tableName}_{$columnsStr}";
            
            // Ensure index name isn't too long (MySQL has a 64 char limit)
            if (strlen($indexName) > 64) {
                $indexName = substr($indexName, 0, 60) . '_idx';
            }
        }
        
        try {
            if ($this->pdo === null) {
                $this->error = 'PDO connection is not available';
                return false;
            }
            
            // Start transaction
            $this->pdo->beginTransaction();
            foreach ($columnNames as $columnName) {
                if (!$this->dialect->columnExists($this->pdo, $tableName, $columnName)) {
                    $this->error = "Column '$columnName' does not exist in table '$tableName'";
                    $this->pdo->rollBack();
                    return false;
                }
            }
            
            // If requested, drop any existing indexes on these columns
            if ($dropExistingIndexes) {
                $indexesToDrop = [];
                
                // For each column, find indexes that include it
                foreach ($columnNames as $columnName) {
                    $indexes = $this->dialect->getIndexesForColumn($this->pdo, $tableName, $columnName);
                    
                    foreach ($indexes as $existingIndexName) {
                        // Don't drop primary key
                        if ($existingIndexName !== 'PRIMARY') {
                            $indexesToDrop[$existingIndexName] = true; // Use associative array to avoid duplicates
                        }
                    }
                }
                
                // Drop the identified indexes
                foreach (array_keys($indexesToDrop) as $indexToDrop) {
                    $this->pdo->exec($this->dialect->dropIndexSql($tableName, $indexToDrop));
                }
            }
            
            // Create the unique index on combined columns
            $this->pdo->exec($this->dialect->createUniqueIndexSql($tableName, $indexName, $columnNames));
            $this->pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->error = $e->getMessage();
            return false;
        }
    }
} 
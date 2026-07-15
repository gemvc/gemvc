<?php   
namespace Gemvc\Database;

use Gemvc\Database\Dialect\DialectResolver;
use Gemvc\Database\Dialect\SqlDialectInterface;
use PDO;
use Exception;

/**
 * SchemaGenerator class for generating database schema from object properties
 * Used in Table classes to define schema constraints that are applied during migrations
 *
 * All engine-specific SQL (identifier quoting, introspection, DDL syntax) is delegated
 * to a SqlDialectInterface implementation, so this class works unmodified against MySQL,
 * PostgreSQL, and SQLite.
 */ 
class SchemaGenerator {
    private PDO $pdo;
    private SqlDialectInterface $dialect;
    /** @var array<SchemaConstraint> */
    private array $schema = [];
    private string $error = '';
    private string $tableName;

    /**
     * Constructor
     * 
     * @param PDO $pdo The PDO instance
     * @param string $tableName The name of the table
     * @param array<SchemaConstraint> $schema Array of SchemaConstraint objects
     * @param SqlDialectInterface|null $dialect Dialect to use (auto-resolved from $pdo if omitted)
     */
    public function __construct(PDO $pdo, string $tableName, array $schema, ?SqlDialectInterface $dialect = null) {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->schema = $schema;
        $this->dialect = $dialect ?? DialectResolver::resolve($pdo);
    }

    /**
     * Get the last error message
     */
    public function getError(): string {
        return $this->error;
    }

    /**
     * Process and apply all schema constraints
     * 
     * @param bool $removeObsolete Whether to remove constraints not in schema definition
     * @return bool True on success, false on failure
     */
    public function applyConstraints(bool $removeObsolete = false): bool {
        try {
            $processedConstraints = $this->processSchemaConstraints();
            
            if ($removeObsolete) {
                $this->removeObsoleteConstraints($processedConstraints);
            }
            
            if (empty($processedConstraints)) {
                return true;
            }

            return $this->executeConstraints($processedConstraints);
        } catch (Exception $e) {
            $this->error = "Failed to apply schema constraints: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Process schema constraints from defineSchema() method
     * 
     * @return array<array<string,mixed>> Processed constraints ready for SQL generation
     */
    private function processSchemaConstraints(): array {
        $processedConstraints = [];
        
        foreach ($this->schema as $constraint) {
            // @phpstan-ignore-next-line
            if (is_object($constraint) && method_exists($constraint, 'toArray')) {
                $constraintData = $constraint->toArray();
                $processedConstraints[] = $constraintData;
            }
        }
        
        return $processedConstraints;
    }

    /**
     * Execute all processed constraints
     * 
     * @param array<array<string,mixed>> $constraints Processed constraints
     * @return bool True on success, false on failure
     */
    private function executeConstraints(array $constraints): bool {
        foreach ($constraints as $constraint) {
            // @phpstan-ignore-next-line
            if (!is_array($constraint) || !isset($constraint['type']) || !is_string($constraint['type'])) {
                continue;
            }
            $type = $constraint['type'];
            
            try {
                switch ($type) {
                    case 'unique':
                        $this->applyUniqueConstraint($constraint);
                        break;
                    case 'index':
                        $this->applyIndexConstraint($constraint);
                        break;
                    case 'foreign_key':
                        $this->applyForeignKeyConstraint($constraint);
                        break;
                    case 'primary':
                        $this->applyPrimaryKeyConstraint($constraint);
                        break;
                    case 'check':
                        $this->applyCheckConstraint($constraint);
                        break;
                    case 'fulltext':
                        $this->applyFulltextConstraint($constraint);
                        break;
                    case 'auto_increment':
                        // Auto increment is handled during table creation
                        break;
                    default:
                        $this->error = "Unknown constraint type: " . (string) $type;
                        return false;
                }
            } catch (Exception $e) {
                $this->error = "Failed to apply " . (string) $type . " constraint: " . $e->getMessage();
                return false;
            }
        }
        
        return true;
    }

    /**
     * Apply unique constraint
     * @param array<string, mixed> $constraint
     */
    private function applyUniqueConstraint(array $constraint): void {
        $columns = is_array($constraint['columns']) ? $constraint['columns'] : [$constraint['columns']];
        $constraintName = isset($constraint['name']) && is_string($constraint['name']) ? $constraint['name'] : 'unique_' . implode('_', $columns);
        
        // Check if constraint already exists
        if ($this->constraintExists($constraintName)) {
            return;
        }
        
        $sql = $this->dialect->addUniqueConstraintSql($this->tableName, $constraintName, $columns);
        $this->pdo->exec($sql);
    }

    /**
     * Apply index constraint
     * @param array<string, mixed> $constraint
     */
    private function applyIndexConstraint(array $constraint): void {
        $columns = is_array($constraint['columns']) ? $constraint['columns'] : [$constraint['columns']];
        $indexName = isset($constraint['name']) && is_string($constraint['name']) ? $constraint['name'] : 'idx_' . implode('_', $columns);
        $unique = !empty($constraint['unique']);
        
        // Check if index already exists
        if ($this->indexExists($indexName)) {
            return;
        }
        
        $sql = $this->dialect->createIndexSql($this->tableName, $indexName, $columns, $unique);
        $this->pdo->exec($sql);
    }

    /**
     * Apply foreign key constraint
     * @param array<string, mixed> $constraint
     */
    private function applyForeignKeyConstraint(array $constraint): void {
        if (!isset($constraint['column']) || !is_string($constraint['column']) ||
            !isset($constraint['references']) || !is_string($constraint['references'])) {
            return;
        }
        
        $column = $constraint['column'];
        $references = $constraint['references'];
        $onDelete = isset($constraint['on_delete']) && is_string($constraint['on_delete']) ? $constraint['on_delete'] : 'RESTRICT';
        $onUpdate = isset($constraint['on_update']) && is_string($constraint['on_update']) ? $constraint['on_update'] : 'RESTRICT';
        $constraintName = isset($constraint['name']) && is_string($constraint['name']) ? $constraint['name'] : 'fk_' . $this->tableName . '_' . $column;

        // Schema::onDeleteSetNull()/onUpdateNoAction() etc. store their action using an
        // underscore convention (e.g. 'SET_NULL', 'NO_ACTION') - normalize to valid SQL
        // keywords ('SET NULL', 'NO ACTION') right before generating DDL.
        $onDelete = str_replace('_', ' ', $onDelete);
        $onUpdate = str_replace('_', ' ', $onUpdate);
        
        // Parse references (e.g., 'users.id' -> table: users, column: id)
        [$refTable, $refColumn] = explode('.', $references);
        
        // Check if constraint already exists
        if ($this->constraintExists($constraintName)) {
            return;
        }
        
        $sql = $this->dialect->addForeignKeySql($this->tableName, $constraintName, $column, $refTable, $refColumn, $onDelete, $onUpdate);
        
        $this->pdo->exec($sql);
    }

    /**
     * Apply primary key constraint
     * @param array<string, mixed> $constraint
     */
    private function applyPrimaryKeyConstraint(array $constraint): void {
        // Primary key is typically handled during table creation
        // This is mainly for composite primary keys or modifications
    }

    /**
     * Apply check constraint
     * @param array<string, mixed> $constraint
     */
    private function applyCheckConstraint(array $constraint): void {
        if (!isset($constraint['expression']) || !is_string($constraint['expression'])) {
            return;
        }
        
        $expression = $constraint['expression'];
        $constraintName = isset($constraint['name']) && is_string($constraint['name']) ? $constraint['name'] : 'check_' . md5($expression);
        
        // Check if constraint already exists
        if ($this->constraintExists($constraintName)) {
            return;
        }
        
        $sql = $this->dialect->addCheckConstraintSql($this->tableName, $constraintName, $expression);
        $this->pdo->exec($sql);
    }

    /**
     * Apply fulltext constraint
     * @param array<string, mixed> $constraint
     */
    private function applyFulltextConstraint(array $constraint): void {
        if (!isset($constraint['columns'])) {
            return;
        }
        
        $columns = is_array($constraint['columns']) ? $constraint['columns'] : [$constraint['columns']];
        $indexName = isset($constraint['name']) && is_string($constraint['name']) ? $constraint['name'] : 'ft_' . implode('_', $columns);
        
        // Check if index already exists
        if ($this->indexExists($indexName)) {
            return;
        }
        
        $sql = $this->dialect->createFulltextIndexSql($this->tableName, $indexName, $columns);
        if ($sql === null) {
            // Not supported by this engine in this pass (documented limitation) - skip
            // instead of emitting invalid SQL.
            $this->error = "Skipped fulltext index `{$indexName}`: not supported by the '{$this->dialect->getName()}' dialect in this version.";
            return;
        }
        $this->pdo->exec($sql);
    }

    /**
     * Check if a constraint exists
     */
    private function constraintExists(string $constraintName): bool {
        return $this->dialect->constraintExists($this->pdo, $this->tableName, $constraintName);
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $indexName): bool {
        return $this->dialect->indexExists($this->pdo, $this->tableName, $indexName);
    }

    /**
     * Get detailed information about applied constraints
     * 
     * @return array<array<string, mixed>> Information about constraints that were applied
     */
    public function getAppliedConstraints(): array {
        $applied = [];
        $processedConstraints = $this->processSchemaConstraints();
        
        foreach ($processedConstraints as $constraint) {
            // @phpstan-ignore-next-line
            if (!is_array($constraint) || !isset($constraint['type']) || !is_string($constraint['type'])) {
                continue;
            }
            $type = $constraint['type'];
            $applied[] = [
                'type' => $type,
                'applied' => true,
                'constraint' => $constraint
            ];
        }
        
        return $applied;
    }

    /**
     * Get a summary of the schema generation process
     * 
     * @return array<string, mixed> Summary information
     */
    public function getSummary(): array {
        $processedConstraints = $this->processSchemaConstraints();
        
        return [
            'table_name' => $this->tableName,
            'total_constraints' => count($processedConstraints),
            'constraint_types' => array_count_values(array_column($processedConstraints, 'type')),
            'has_errors' => !empty($this->error),
            'error' => $this->error
        ];
    }

    /**
     * Remove constraints that exist in database but not in schema definition
     * 
     * @param array<string, mixed> $currentConstraints Current schema constraints
     */
    private function removeObsoleteConstraints(array $currentConstraints): void {
        // Get all existing constraints from database
        $existingConstraints = $this->getExistingConstraints();
        $existingIndexes = $this->getExistingIndexes();
        
        // Build map of current constraints by type and columns
        $currentConstraintMap = [];
        foreach ($currentConstraints as $constraint) {
            if (!is_array($constraint) || !isset($constraint['type']) || !isset($constraint['columns']) || !is_string($constraint['type'])) {
                continue;
            }
            
            $type = $constraint['type'];
            $columns = is_array($constraint['columns']) ? $constraint['columns'] : [$constraint['columns']];
            sort($columns); // Normalize column order
            $key = $type . '_' . implode('_', $columns);
            $currentConstraintMap[$key] = $constraint;
        }
        
        // Remove obsolete constraints
        foreach ($existingConstraints as $existing) {
            if ($existing['type'] === 'UNIQUE') {
                $columns = $this->getConstraintColumns($existing['name']);
                sort($columns);
                $key = 'unique_' . implode('_', $columns);
                
                if (!isset($currentConstraintMap[$key])) {
                    $this->dropConstraint($existing['name']);
                }
            }
        }
        
        // Remove obsolete indexes (excluding PRIMARY and unique constraints)
        foreach ($existingIndexes as $existing) {
            if ($existing['name'] !== 'PRIMARY' && !$this->isUniqueConstraintIndex($existing['name'])) {
                $columns = [$existing['column']]; // Simplified for single column indexes
                sort($columns);
                $key = 'index_' . implode('_', $columns);
                
                if (!isset($currentConstraintMap[$key])) {
                    $this->dropIndex($existing['name']);
                }
            }
        }
    }

    /**
     * Get existing constraints from database
     * @return array<int, array{name: string, type: string}>
     */
    private function getExistingConstraints(): array {
        return $this->dialect->getExistingConstraints($this->pdo, $this->tableName);
    }

    /**
     * Get existing indexes from database
     * @return array<int, array{name: string, column: string}>
     */
    private function getExistingIndexes(): array {
        return $this->dialect->getExistingIndexes($this->pdo, $this->tableName);
    }

    /**
     * Get columns for a specific constraint
     * @return array<string>
     */
    private function getConstraintColumns(string $constraintName): array {
        return $this->dialect->getConstraintColumns($this->pdo, $this->tableName, $constraintName);
    }

    /**
     * Check if an index name corresponds to a unique constraint
     */
    private function isUniqueConstraintIndex(string $indexName): bool {
        return $this->dialect->isUniqueConstraintIndex($this->pdo, $this->tableName, $indexName);
    }

    /**
     * Drop a constraint
     */
    private function dropConstraint(string $constraintName): void {
        $this->pdo->exec($this->dialect->dropConstraintSql($this->tableName, $constraintName));
    }

    /**
     * Drop an index
     */
    private function dropIndex(string $indexName): void {
        $this->pdo->exec($this->dialect->dropIndexSql($this->tableName, $indexName));
    }
}

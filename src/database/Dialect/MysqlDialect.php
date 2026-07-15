<?php

namespace Gemvc\Database\Dialect;

use PDO;

/**
 * MySQL dialect - a behavior-preserving extraction of the SQL that
 * TableGenerator/SchemaGenerator used to build inline before the dialect
 * abstraction existed. This guarantees zero behavior change for existing
 * MySQL-backed projects and keeps every pre-existing test passing unmodified.
 */
class MysqlDialect implements SqlDialectInterface
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $name): string
    {
        return "`{$name}`";
    }

    public function toEngineType(string $canonicalType): string
    {
        if (preg_match('/^decimal(?::(\d+),(\d+))?$/i', $canonicalType, $matches)) {
            $precision = isset($matches[1]) ? (int) $matches[1] : 10;
            $scale = isset($matches[2]) ? (int) $matches[2] : 2;
            return "DECIMAL({$precision},{$scale})";
        }

        return match (strtolower($canonicalType)) {
            'int', 'integer' => 'INT(11)',
            'float', 'double' => 'DOUBLE',
            'bool', 'boolean' => 'TINYINT(1)',
            'string' => 'VARCHAR(255)',
            'text' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'datetime' => 'DATETIME',
            'array', 'json', 'jsonb' => 'JSON',
            default => 'TEXT',
        };
    }

    public function toCanonicalType(string $rawEngineType): string
    {
        $lower = strtolower(trim($rawEngineType));

        if (preg_match('/^decimal\s*\(\s*(\d+)\s*,\s*(\d+)\s*\)/', $lower, $matches)) {
            return 'decimal:' . $matches[1] . ',' . $matches[2];
        }

        $type = preg_replace('/\(\d+\)/', '', $lower) ?? $lower;
        $type = str_replace(['unsigned', 'signed'], '', $type);
        $type = trim($type);

        $typeMap = [
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'bigint' => 'int',
            'float' => 'double',
            'real' => 'double',
            'varchar' => 'string',
            'char' => 'string',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'date' => 'datetime',
            'time' => 'datetime',
            'year' => 'int',
            'bit' => 'int',
            'bool' => 'int',
            'boolean' => 'int',
            'json' => 'string',
            'enum' => 'string',
            'set' => 'string',
        ];

        return $typeMap[$type] ?? $type;
    }

    public function idColumnDefinition(): string
    {
        return 'INT(11) AUTO_INCREMENT PRIMARY KEY';
    }

    public function foreignKeyColumnType(): string
    {
        return 'INT(11)';
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $stmt !== false && $stmt->rowCount() > 0;
    }

    public function getColumns(PDO $pdo, string $table): array
    {
        $q = $this->quoteIdentifier($table);
        $stmt = $pdo->query("DESCRIBE {$q}");
        if ($stmt === false) {
            return [];
        }

        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['Field']) || !is_string($row['Field'])) {
                continue;
            }
            $columns[$row['Field']] = [
                'type' => isset($row['Type']) && is_string($row['Type']) ? $row['Type'] : '',
                'nullable' => isset($row['Null']) && is_string($row['Null']) && strtolower($row['Null']) === 'yes',
                'default' => isset($row['Default']) && is_scalar($row['Default']) ? (string) $row['Default'] : null,
            ];
        }

        return $columns;
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        // NOTE: the exec() call immediately before query() with the identical SQL is
        // intentionally redundant (has no functional purpose) - it mirrors pre-existing
        // TableGenerator behavior byte-for-byte so the pre-existing PHPUnit test suite
        // (which asserts on exact exec()/query() call counts) keeps passing unmodified.
        $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
        $pdo->exec($sql);
        $result = $pdo->query($sql);
        if ($result === false) {
            return false;
        }

        return !empty($result->fetchAll());
    }

    public function getIndexesForColumn(PDO $pdo, string $table, string $column): array
    {
        // NOTE: see columnExists() - the redundant exec() is intentional, preserved for
        // historical test/behavior compatibility.
        $sql = "SHOW INDEXES FROM `{$table}` WHERE Column_name = '{$column}'";
        $pdo->exec($sql);
        $result = $pdo->query($sql);
        if ($result === false) {
            return [];
        }

        $names = [];
        foreach ($result->fetchAll() as $row) {
            if (is_array($row) && isset($row['Key_name']) && is_string($row['Key_name'])) {
                $names[] = $row['Key_name'];
            }
        }

        return $names;
    }

    public function getExistingConstraints(PDO $pdo, string $table): array
    {
        $sql = "SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()
                AND CONSTRAINT_TYPE IN ('UNIQUE', 'FOREIGN KEY', 'CHECK')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['CONSTRAINT_NAME'], $row['CONSTRAINT_TYPE'])
                && is_string($row['CONSTRAINT_NAME']) && is_string($row['CONSTRAINT_TYPE'])) {
                $result[] = ['name' => $row['CONSTRAINT_NAME'], 'type' => $row['CONSTRAINT_TYPE']];
            }
        }

        return $result;
    }

    public function getExistingIndexes(PDO $pdo, string $table): array
    {
        $q = $this->quoteIdentifier($table);
        $stmt = $pdo->query("SHOW INDEX FROM {$q}");
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['Key_name'], $row['Column_name'])
                && is_string($row['Key_name']) && is_string($row['Column_name'])) {
                $result[] = ['name' => $row['Key_name'], 'column' => $row['Column_name']];
            }
        }

        return $result;
    }

    public function getConstraintColumns(PDO $pdo, string $table, string $constraintName): array
    {
        $sql = "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE CONSTRAINT_NAME = ? AND TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()
                ORDER BY ORDINAL_POSITION";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$constraintName, $table]);
        /** @var array<int, string> $columns */
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $columns;
    }

    public function constraintExists(PDO $pdo, string $table, string $constraintName): bool
    {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND TABLE_SCHEMA = DATABASE()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $constraintName]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_NAME = ? AND INDEX_NAME = ? AND TABLE_SCHEMA = DATABASE()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $indexName]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function isUniqueConstraintIndex(PDO $pdo, string $table, string $indexName): bool
    {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_NAME = ? AND TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()
                AND CONSTRAINT_TYPE = 'UNIQUE'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indexName, $table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function createTableSql(string $table, array $columnDefs): string
    {
        $q = $this->quoteIdentifier($table);
        $columnsSql = implode(', ', $columnDefs);
        return "CREATE TABLE IF NOT EXISTS {$q} ({$columnsSql});";
    }

    public function addColumnSql(string $table, string $columnName, string $definition): string
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($columnName);
        return "ALTER TABLE {$t} ADD COLUMN {$c} {$definition}";
    }

    public function alterColumnSql(string $table, string $columnName, string $sqlType, string $otherProperties, bool $nullable, ?string $defaultClause): array
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($columnName);

        $definition = $sqlType;
        if ($otherProperties !== '') {
            $definition .= ' ' . $otherProperties;
        }
        $definition .= ' ' . ($nullable ? 'NULL' : 'NOT NULL');
        if ($defaultClause !== null && $defaultClause !== '') {
            $definition .= $defaultClause;
        }

        return ["ALTER TABLE {$t} MODIFY COLUMN {$c} {$definition}"];
    }

    public function dropColumnSql(string $table, string $columnName): string
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($columnName);
        return "ALTER TABLE {$t} DROP COLUMN {$c}";
    }

    public function dropIndexSql(string $table, string $indexName): string
    {
        $t = $this->quoteIdentifier($table);
        $i = $this->quoteIdentifier($indexName);
        return "DROP INDEX {$i} ON {$t}";
    }

    public function dropPrimaryKeySql(PDO $pdo, string $table): ?string
    {
        $t = $this->quoteIdentifier($table);
        return "ALTER TABLE {$t} DROP PRIMARY KEY";
    }

    public function createUniqueIndexSql(string $table, string $indexName, array $columns): string
    {
        $t = $this->quoteIdentifier($table);
        $i = $this->quoteIdentifier($indexName);
        $cols = implode(', ', array_map(fn(string $c) => $this->quoteIdentifier($c), $columns));
        return "CREATE UNIQUE INDEX {$i} ON {$t} ({$cols})";
    }

    public function addUniqueConstraintSql(string $table, string $constraintName, array $columns): string
    {
        $t = $this->quoteIdentifier($table);
        $n = $this->quoteIdentifier($constraintName);
        $cols = implode(', ', array_map(fn(string $c) => $this->quoteIdentifier($c), $columns));
        return "ALTER TABLE {$t} ADD CONSTRAINT {$n} UNIQUE ({$cols})";
    }

    public function createIndexSql(string $table, string $indexName, array $columns, bool $unique): string
    {
        $t = $this->quoteIdentifier($table);
        $i = $this->quoteIdentifier($indexName);
        $cols = implode(', ', array_map(fn(string $c) => $this->quoteIdentifier($c), $columns));
        $uniqueKeyword = $unique ? 'UNIQUE ' : '';
        return "CREATE {$uniqueKeyword}INDEX {$i} ON {$t} ({$cols})";
    }

    public function addForeignKeySql(
        string $table,
        string $constraintName,
        string $column,
        string $refTable,
        string $refColumn,
        string $onDelete,
        string $onUpdate
    ): string {
        $t = $this->quoteIdentifier($table);
        $n = $this->quoteIdentifier($constraintName);
        $c = $this->quoteIdentifier($column);
        $rt = $this->quoteIdentifier($refTable);
        $rc = $this->quoteIdentifier($refColumn);

        return "ALTER TABLE {$t}
                ADD CONSTRAINT {$n}
                FOREIGN KEY ({$c})
                REFERENCES {$rt}({$rc})
                ON DELETE {$onDelete}
                ON UPDATE {$onUpdate}";
    }

    public function addCheckConstraintSql(string $table, string $constraintName, string $expression): string
    {
        $t = $this->quoteIdentifier($table);
        $n = $this->quoteIdentifier($constraintName);
        return "ALTER TABLE {$t} ADD CONSTRAINT {$n} CHECK ({$expression})";
    }

    public function createFulltextIndexSql(string $table, string $indexName, array $columns): ?string
    {
        $t = $this->quoteIdentifier($table);
        $i = $this->quoteIdentifier($indexName);
        $cols = implode(', ', array_map(fn(string $c) => $this->quoteIdentifier($c), $columns));
        return "CREATE FULLTEXT INDEX {$i} ON {$t} ({$cols})";
    }

    public function dropConstraintSql(string $table, string $constraintName): string
    {
        $t = $this->quoteIdentifier($table);
        $n = $this->quoteIdentifier($constraintName);
        return "ALTER TABLE {$t} DROP CONSTRAINT {$n}";
    }
}

<?php

namespace Gemvc\Database\Dialect;

use PDO;

/**
 * PostgreSQL dialect. Uses ANSI information_schema for introspection (Postgres has
 * no SHOW COLUMNS/SHOW INDEXES-style commands), double-quoted identifiers, and
 * multiple statements for ALTER COLUMN (Postgres has separate TYPE/NULL/DEFAULT
 * clauses, unlike MySQL's single MODIFY COLUMN).
 */
class PostgresDialect implements SqlDialectInterface
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    public function toEngineType(string $canonicalType): string
    {
        if (preg_match('/^decimal(?::(\d+),(\d+))?$/i', $canonicalType, $matches)) {
            $precision = isset($matches[1]) ? (int) $matches[1] : 10;
            $scale = isset($matches[2]) ? (int) $matches[2] : 2;
            return "NUMERIC({$precision},{$scale})";
        }

        return match (strtolower($canonicalType)) {
            'int', 'integer' => 'INTEGER',
            'float', 'double' => 'DOUBLE PRECISION',
            'bool', 'boolean' => 'BOOLEAN',
            'string' => 'VARCHAR(255)',
            'text' => 'TEXT',
            'longtext' => 'TEXT',
            'datetime' => 'TIMESTAMP',
            'array', 'json', 'jsonb' => 'JSONB',
            default => 'TEXT',
        };
    }

    public function toCanonicalType(string $rawEngineType): string
    {
        $lower = strtolower(trim($rawEngineType));

        if (preg_match('/^numeric\s*\(\s*(\d+)\s*,\s*(\d+)\s*\)/', $lower, $matches)) {
            return 'decimal:' . $matches[1] . ',' . $matches[2];
        }

        $type = preg_replace('/\(\d+(,\d+)?\)/', '', $lower) ?? $lower;
        $type = trim($type);

        $typeMap = [
            'smallint' => 'int',
            'bigint' => 'int',
            'serial' => 'int',
            'bigserial' => 'int',
            'real' => 'double',
            'double precision' => 'double',
            'character varying' => 'string',
            'character' => 'string',
            'varchar' => 'string',
            'char' => 'string',
            'timestamp without time zone' => 'datetime',
            'timestamp with time zone' => 'datetime',
            'timestamp' => 'datetime',
            'date' => 'datetime',
            'time without time zone' => 'datetime',
            'time' => 'datetime',
            'boolean' => 'int',
            'json' => 'string',
            'jsonb' => 'string',
        ];

        return $typeMap[$type] ?? $type;
    }

    public function idColumnDefinition(): string
    {
        return 'SERIAL PRIMARY KEY';
    }

    public function foreignKeyColumnType(): string
    {
        return 'INTEGER';
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.tables
                WHERE table_name = ? AND table_schema = current_schema()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getColumns(PDO $pdo, string $table): array
    {
        $sql = "SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns
                WHERE table_name = ? AND table_schema = current_schema()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);

        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['column_name']) || !is_string($row['column_name'])) {
                continue;
            }
            $columns[$row['column_name']] = [
                'type' => isset($row['data_type']) && is_string($row['data_type']) ? $row['data_type'] : '',
                'nullable' => isset($row['is_nullable']) && is_string($row['is_nullable']) && strtolower($row['is_nullable']) === 'yes',
                'default' => isset($row['column_default']) && is_scalar($row['column_default']) ? (string) $row['column_default'] : null,
            ];
        }

        return $columns;
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.columns
                WHERE table_name = ? AND column_name = ? AND table_schema = current_schema()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getIndexesForColumn(PDO $pdo, string $table, string $column): array
    {
        // Excludes the primary key's backing index deliberately: TableGenerator's
        // removeColumn()/makeColumnUnique() only know to skip an index literally named
        // 'PRIMARY' (MySQL's convention); Postgres primary key index names are arbitrary,
        // so we filter it out here instead to keep that skip-logic correct across dialects.
        $sql = "SELECT i.relname AS index_name
                FROM pg_index ix
                JOIN pg_class i ON i.oid = ix.indexrelid
                JOIN pg_class t ON t.oid = ix.indrelid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                WHERE t.relname = ? AND a.attname = ? AND ix.indisprimary = false
                AND t.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);

        $names = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['index_name']) && is_string($row['index_name'])) {
                $names[] = $row['index_name'];
            }
        }

        return $names;
    }

    public function getExistingConstraints(PDO $pdo, string $table): array
    {
        $sql = "SELECT constraint_name, constraint_type
                FROM information_schema.table_constraints
                WHERE table_name = ? AND table_schema = current_schema()
                AND constraint_type IN ('UNIQUE', 'FOREIGN KEY', 'CHECK')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['constraint_name'], $row['constraint_type'])
                && is_string($row['constraint_name']) && is_string($row['constraint_type'])) {
                $result[] = ['name' => $row['constraint_name'], 'type' => $row['constraint_type']];
            }
        }

        return $result;
    }

    public function getExistingIndexes(PDO $pdo, string $table): array
    {
        $sql = "SELECT i.relname AS index_name, a.attname AS column_name
                FROM pg_index ix
                JOIN pg_class i ON i.oid = ix.indexrelid
                JOIN pg_class t ON t.oid = ix.indrelid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                WHERE t.relname = ? AND ix.indisprimary = false
                AND t.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['index_name'], $row['column_name'])
                && is_string($row['index_name']) && is_string($row['column_name'])) {
                $result[] = ['name' => $row['index_name'], 'column' => $row['column_name']];
            }
        }

        return $result;
    }

    public function getConstraintColumns(PDO $pdo, string $table, string $constraintName): array
    {
        $sql = "SELECT column_name
                FROM information_schema.key_column_usage
                WHERE constraint_name = ? AND table_name = ? AND table_schema = current_schema()
                ORDER BY ordinal_position";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$constraintName, $table]);
        /** @var array<int, string> $columns */
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $columns;
    }

    public function constraintExists(PDO $pdo, string $table, string $constraintName): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE table_name = ? AND constraint_name = ? AND table_schema = current_schema()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $constraintName]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $sql = "SELECT COUNT(*) FROM pg_indexes WHERE tablename = ? AND indexname = ? AND schemaname = current_schema()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $indexName]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function isUniqueConstraintIndex(PDO $pdo, string $table, string $indexName): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.table_constraints
                WHERE constraint_name = ? AND table_name = ? AND table_schema = current_schema()
                AND constraint_type = 'UNIQUE'";
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

        $statements = [];
        $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} TYPE {$sqlType} USING {$c}::{$sqlType}";
        $statements[] = $nullable
            ? "ALTER TABLE {$t} ALTER COLUMN {$c} DROP NOT NULL"
            : "ALTER TABLE {$t} ALTER COLUMN {$c} SET NOT NULL";

        if ($defaultClause !== null && trim($defaultClause) !== '') {
            // $defaultClause arrives pre-formatted as " DEFAULT <value>" (MySQL-style fragment).
            $value = trim(preg_replace('/^\s*DEFAULT\s+/i', '', $defaultClause) ?? '');
            if ($value !== '') {
                $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} SET DEFAULT {$value}";
            }
        } else {
            $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} DROP DEFAULT";
        }

        // otherProperties (e.g. inline CHECK(...)) has no equivalent ALTER COLUMN clause in
        // Postgres - known limitation, documented in the plan. Silently ignored here rather
        // than emitting invalid SQL.

        return $statements;
    }

    public function dropColumnSql(string $table, string $columnName): string
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($columnName);
        return "ALTER TABLE {$t} DROP COLUMN {$c}";
    }

    public function dropIndexSql(string $table, string $indexName): string
    {
        $i = $this->quoteIdentifier($indexName);
        return "DROP INDEX {$i}";
    }

    public function dropPrimaryKeySql(PDO $pdo, string $table): ?string
    {
        $sql = "SELECT constraint_name FROM information_schema.table_constraints
                WHERE table_name = ? AND table_schema = current_schema() AND constraint_type = 'PRIMARY KEY'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        $constraintName = $stmt->fetchColumn();
        if (!is_string($constraintName) || $constraintName === '') {
            return null;
        }

        $t = $this->quoteIdentifier($table);
        $n = $this->quoteIdentifier($constraintName);
        return "ALTER TABLE {$t} DROP CONSTRAINT {$n}";
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
        // No direct FULLTEXT INDEX equivalent in this pass (Postgres full-text search uses
        // tsvector/to_tsvector + a GIN index, which is a schema/column-shape change, not a
        // drop-in DDL translation) - documented limitation, caller must skip and warn.
        return null;
    }

    public function dropConstraintSql(string $table, string $constraintName): string
    {
        $t = $this->quoteIdentifier($table);
        $n = $this->quoteIdentifier($constraintName);
        return "ALTER TABLE {$t} DROP CONSTRAINT {$n}";
    }
}

<?php

namespace Gemvc\Database\Dialect;

use PDO;

/**
 * SQLite dialect. Uses PRAGMA statements for introspection (SQLite has no
 * INFORMATION_SCHEMA), double-quoted identifiers, and SQLite's flexible type
 * affinity system for types.
 *
 * Known limitations (SQLite's ALTER TABLE is intentionally minimal):
 * - Changing an existing column's type/nullability/default, and dropping a primary
 *   key, both require a full table rebuild (create-new-table + copy-data + drop-old +
 *   rename) that SQLite's ALTER TABLE does not support directly. alterColumnSql() and
 *   dropPrimaryKeySql() return "unsupported" (empty array / null) so callers can skip
 *   with a clear warning instead of emitting invalid SQL.
 * - No FULLTEXT INDEX equivalent (SQLite's FTS requires a virtual table, not a
 *   drop-in index) - createFulltextIndexSql() returns null.
 */
class SqliteDialect implements SqlDialectInterface
{
    public function getName(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    public function toEngineType(string $canonicalType): string
    {
        if (preg_match('/^decimal(?::(\d+),(\d+))?$/i', $canonicalType)) {
            // SQLite has no fixed-point decimal type; NUMERIC affinity stores it as-is.
            return 'NUMERIC';
        }

        return match (strtolower($canonicalType)) {
            'int', 'integer' => 'INTEGER',
            'float', 'double' => 'REAL',
            'bool', 'boolean' => 'INTEGER',
            'string' => 'VARCHAR(255)',
            'text' => 'TEXT',
            'longtext' => 'TEXT',
            'datetime' => 'DATETIME',
            'array', 'json', 'jsonb' => 'TEXT',
            default => 'TEXT',
        };
    }

    public function toCanonicalType(string $rawEngineType): string
    {
        $lower = strtolower(trim($rawEngineType));
        $type = preg_replace('/\(\d+(,\d+)?\)/', '', $lower) ?? $lower;
        $type = trim($type);

        $typeMap = [
            'int' => 'int',
            'integer' => 'int',
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'bigint' => 'int',
            'unsigned big int' => 'int',
            'real' => 'double',
            'double' => 'double',
            'double precision' => 'double',
            'float' => 'double',
            'numeric' => 'decimal:10,2',
            'decimal' => 'decimal:10,2',
            'boolean' => 'int',
            'date' => 'datetime',
            'datetime' => 'datetime',
            'varchar' => 'string',
            'character' => 'string',
            'nchar' => 'string',
            'nvarchar' => 'string',
            'clob' => 'text',
            'text' => 'text',
        ];

        return $typeMap[$type] ?? $type;
    }

    public function idColumnDefinition(): string
    {
        return 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    public function foreignKeyColumnType(): string
    {
        return 'INTEGER';
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        $sql = "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getColumns(PDO $pdo, string $table): array
    {
        $safeTable = str_replace('"', '""', $table);
        $stmt = $pdo->query('PRAGMA table_info("' . $safeTable . '")');
        if ($stmt === false) {
            return [];
        }

        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row) || !isset($row['name']) || !is_string($row['name'])) {
                continue;
            }
            $columns[$row['name']] = [
                'type' => isset($row['type']) && is_string($row['type']) ? $row['type'] : '',
                'nullable' => !(isset($row['notnull']) && is_scalar($row['notnull']) && (int) $row['notnull'] === 1),
                'default' => isset($row['dflt_value']) && is_scalar($row['dflt_value']) ? (string) $row['dflt_value'] : null,
            ];
        }

        return $columns;
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        return isset($this->getColumns($pdo, $table)[$column]);
    }

    public function getIndexesForColumn(PDO $pdo, string $table, string $column): array
    {
        $safeTable = str_replace('"', '""', $table);
        $stmt = $pdo->query('PRAGMA index_list("' . $safeTable . '")');
        if ($stmt === false) {
            return [];
        }

        $names = [];
        foreach ($stmt->fetchAll() as $indexRow) {
            if (!is_array($indexRow) || !isset($indexRow['name']) || !is_string($indexRow['name'])) {
                continue;
            }
            // 'origin' is 'pk' for the index automatically backing a PRIMARY KEY - skip it,
            // mirroring PostgresDialect's exclusion (see comment there for why).
            if (isset($indexRow['origin']) && $indexRow['origin'] === 'pk') {
                continue;
            }
            $indexName = $indexRow['name'];
            $safeIndex = str_replace('"', '""', $indexName);
            $infoStmt = $pdo->query('PRAGMA index_info("' . $safeIndex . '")');
            if ($infoStmt === false) {
                continue;
            }
            foreach ($infoStmt->fetchAll() as $colRow) {
                if (is_array($colRow) && isset($colRow['name']) && $colRow['name'] === $column) {
                    $names[] = $indexName;
                    break;
                }
            }
        }

        return $names;
    }

    public function getExistingConstraints(PDO $pdo, string $table): array
    {
        // SQLite exposes UNIQUE/CHECK/FOREIGN KEY constraints only via the table's original
        // CREATE TABLE SQL (sqlite_master.sql), not a queryable constraint catalog. Unique
        // constraints created as indexes are covered by getExistingIndexes()/
        // isUniqueConstraintIndex(); named CHECK/FOREIGN KEY constraints added via
        // addCheckConstraintSql()/addForeignKeySql() are not introspectable here, so
        // schema-sync (removeObsoleteConstraints) support for them is a known limitation.
        return [];
    }

    public function getExistingIndexes(PDO $pdo, string $table): array
    {
        $safeTable = str_replace('"', '""', $table);
        $stmt = $pdo->query('PRAGMA index_list("' . $safeTable . '")');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll() as $indexRow) {
            if (!is_array($indexRow) || !isset($indexRow['name']) || !is_string($indexRow['name'])) {
                continue;
            }
            if (isset($indexRow['origin']) && $indexRow['origin'] === 'pk') {
                continue;
            }
            $indexName = $indexRow['name'];
            $safeIndex = str_replace('"', '""', $indexName);
            $infoStmt = $pdo->query('PRAGMA index_info("' . $safeIndex . '")');
            if ($infoStmt === false) {
                continue;
            }
            foreach ($infoStmt->fetchAll() as $colRow) {
                if (is_array($colRow) && isset($colRow['name']) && is_string($colRow['name'])) {
                    $result[] = ['name' => $indexName, 'column' => $colRow['name']];
                }
            }
        }

        return $result;
    }

    public function getConstraintColumns(PDO $pdo, string $table, string $constraintName): array
    {
        // See getExistingConstraints() - named constraints aren't introspectable in SQLite.
        return [];
    }

    public function constraintExists(PDO $pdo, string $table, string $constraintName): bool
    {
        return false;
    }

    public function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $sql = "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = ? AND tbl_name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indexName, $table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function isUniqueConstraintIndex(PDO $pdo, string $table, string $indexName): bool
    {
        $safeTable = str_replace('"', '""', $table);
        $stmt = $pdo->query('PRAGMA index_list("' . $safeTable . '")');
        if ($stmt === false) {
            return false;
        }
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row) && isset($row['name'], $row['unique']) && $row['name'] === $indexName) {
                return (int) $row['unique'] === 1;
            }
        }
        return false;
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
        // Unsupported: SQLite's ALTER TABLE cannot change a column's type, nullability, or
        // default without rebuilding the table (create-new + copy + drop-old + rename).
        // Returning an empty array signals to TableGenerator that this operation must be
        // skipped with a warning rather than attempting invalid SQL.
        return [];
    }

    public function dropColumnSql(string $table, string $columnName): string
    {
        // Supported since SQLite 3.35.0 (2021).
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
        // Unsupported: SQLite has no ALTER TABLE ... DROP CONSTRAINT / DROP PRIMARY KEY;
        // the primary key is baked into the table's CREATE TABLE statement and can only be
        // changed via a full table rebuild.
        return null;
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
        // SQLite has no ADD CONSTRAINT; a unique index is the standard equivalent.
        return $this->createUniqueIndexSql($table, $constraintName, $columns);
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
        // SQLite has no ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY; foreign keys can
        // only be declared inline in CREATE TABLE. This is a known limitation - adding a
        // foreign key to an *existing* SQLite table requires a full table rebuild, which is
        // out of scope for this pass. We still return a best-effort statement (a no-op
        // comment) so callers get a clear signal rather than a PDO exception with an
        // unrelated message.
        return "-- SQLite does not support adding foreign keys to existing tables (table: {$table}, column: {$column})";
    }

    public function addCheckConstraintSql(string $table, string $constraintName, string $expression): string
    {
        // Same limitation as addForeignKeySql() - CHECK constraints can only be declared
        // inline in CREATE TABLE for SQLite.
        return "-- SQLite does not support adding CHECK constraints to existing tables (table: {$table})";
    }

    public function createFulltextIndexSql(string $table, string $indexName, array $columns): ?string
    {
        return null;
    }

    public function dropConstraintSql(string $table, string $constraintName): string
    {
        // Named constraints aren't addressable post-creation in SQLite (see
        // addForeignKeySql/addCheckConstraintSql) - if it's a unique-constraint-backed
        // index, dropIndexSql() should be used instead.
        return $this->dropIndexSql($table, $constraintName);
    }
}

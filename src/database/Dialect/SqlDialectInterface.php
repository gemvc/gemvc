<?php

namespace Gemvc\Database\Dialect;

use PDO;

/**
 * SqlDialectInterface abstracts every engine-specific piece of SQL used by
 * TableGenerator and SchemaGenerator, so those classes can stay focused on
 * orchestration (diffing object properties against existing columns, deciding
 * which constraints to add/remove) while dialect implementations own the
 * actual SQL syntax and result-shape differences between MySQL, PostgreSQL,
 * and SQLite.
 *
 * Introspection methods return normalized PHP data (not raw driver result
 * sets) so callers never need to know about engine-specific result column
 * names (e.g. MySQL's `Field`/`Type`/`Null` vs PostgreSQL's
 * `column_name`/`data_type`/`is_nullable`).
 */
interface SqlDialectInterface
{
    /**
     * @return string One of: 'mysql', 'pgsql', 'sqlite'
     */
    public function getName(): string;

    /**
     * Quote a table or column identifier for safe inclusion in generated SQL.
     */
    public function quoteIdentifier(string $name): string;

    /**
     * Translate a canonical type token to this engine's SQL column type.
     *
     * Canonical tokens: 'int', 'float', 'bool', 'string', 'text', 'longtext',
     * 'datetime', 'json', or 'decimal:PRECISION,SCALE'.
     */
    public function toEngineType(string $canonicalType): string;

    /**
     * Translate a raw engine-reported column type (from introspection) back
     * to a canonical token, so TableGenerator can diff it against the
     * canonical type derived from the PHP object's properties.
     */
    public function toCanonicalType(string $rawEngineType): string;

    /**
     * Full column definition (type + primary key + auto-increment) used for
     * the conventional 'id' column.
     */
    public function idColumnDefinition(): string;

    /**
     * Column type used for conventional foreign-key columns (properties
     * ending in '_id' whose PHP type is int).
     */
    public function foreignKeyColumnType(): string;

    /**
     * @return bool True if the table exists
     */
    public function tableExists(PDO $pdo, string $table): bool;

    /**
     * @return array<string, array{type: string, nullable: bool, default: string|null}> Keyed by column name
     */
    public function getColumns(PDO $pdo, string $table): array;

    /**
     * @return bool True if the column exists on the table
     */
    public function columnExists(PDO $pdo, string $table, string $column): bool;

    /**
     * @return array<int, string> Names of indexes that include this column
     */
    public function getIndexesForColumn(PDO $pdo, string $table, string $column): array;

    /**
     * @return array<int, array{name: string, type: string}> Existing UNIQUE/FOREIGN KEY/CHECK constraints
     */
    public function getExistingConstraints(PDO $pdo, string $table): array;

    /**
     * @return array<int, array{name: string, column: string}> Existing non-PRIMARY indexes
     */
    public function getExistingIndexes(PDO $pdo, string $table): array;

    /**
     * @return array<int, string> Column names belonging to the named constraint, in ordinal order
     */
    public function getConstraintColumns(PDO $pdo, string $table, string $constraintName): array;

    public function constraintExists(PDO $pdo, string $table, string $constraintName): bool;

    public function indexExists(PDO $pdo, string $table, string $indexName): bool;

    public function isUniqueConstraintIndex(PDO $pdo, string $table, string $indexName): bool;

    /**
     * @param array<int, string> $columnDefs Fully-formed "name type ... NULL/NOT NULL [DEFAULT ...]" fragments
     */
    public function createTableSql(string $table, array $columnDefs): string;

    public function addColumnSql(string $table, string $columnName, string $definition): string;

    /**
     * @param string $otherProperties Extra column-level SQL fragments (e.g. inline CHECK(...)) - best effort,
     *                                 see known limitations for engines that don't support them in ALTER COLUMN.
     * @return array<int, string> One or more statements to execute in order.
     *                            Empty array means "unsupported by this engine" - caller must skip and warn.
     */
    public function alterColumnSql(string $table, string $columnName, string $sqlType, string $otherProperties, bool $nullable, ?string $defaultClause): array;

    public function dropColumnSql(string $table, string $columnName): string;

    public function dropIndexSql(string $table, string $indexName): string;

    /**
     * @return string|null The DDL statement, or null if unsupported by this engine (caller must skip and warn)
     */
    public function dropPrimaryKeySql(PDO $pdo, string $table): ?string;

    /**
     * @param array<int, string> $columns
     */
    public function createUniqueIndexSql(string $table, string $indexName, array $columns): string;

    /**
     * @param array<int, string> $columns
     */
    public function addUniqueConstraintSql(string $table, string $constraintName, array $columns): string;

    /**
     * @param array<int, string> $columns
     */
    public function createIndexSql(string $table, string $indexName, array $columns, bool $unique): string;

    public function addForeignKeySql(
        string $table,
        string $constraintName,
        string $column,
        string $refTable,
        string $refColumn,
        string $onDelete,
        string $onUpdate
    ): string;

    public function addCheckConstraintSql(string $table, string $constraintName, string $expression): string;

    /**
     * @param array<int, string> $columns
     * @return string|null The DDL statement, or null if unsupported by this engine (caller must skip and warn)
     */
    public function createFulltextIndexSql(string $table, string $indexName, array $columns): ?string;

    public function dropConstraintSql(string $table, string $constraintName): string;
}

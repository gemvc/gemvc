<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\Database\Dialect\DialectResolver;
use Gemvc\Database\Table;
use Gemvc\Database\TableGenerator;
use Gemvc\Database\TableMigrateOrder;
use Gemvc\Database\SchemaGenerator;
use Gemvc\Database\ViewGenerator;
use Gemvc\Database\ViewTable;
use Gemvc\Helper\ProjectHelper;

class DbMigrate extends Command
{
    protected string $description = "Create or update database tables/views from PHP class definitions.
    - Tables: create/sync columns and schema constraints
    - ViewTable: CREATE OR REPLACE VIEW from defineView()
    - db:migrate --all: migrate all app/table classes (FK-safe, views last)
    Flags: [--force] [--sync-schema] [--enforce-not-null] [--default <value>]";

    public function execute(): bool
    {
        try {
            ProjectHelper::loadEnv();
            $pdo = DbConnect::connect();
            if (!$pdo) {
                return false;
            }

            $force = in_array('--force', $this->args, true);
            $enforceNotNull = in_array('--enforce-not-null', $this->args, true);
            $syncSchema = in_array('--sync-schema', $this->args, true);
            $migrateAll = in_array('--all', $this->args, true);

            $defaultValue = null;
            foreach ($this->args as $i => $arg) {
                if ($arg === '--default' && isset($this->args[$i + 1])) {
                    $defaultValue = $this->args[$i + 1];
                }
            }

            if ($migrateAll) {
                return $this->migrateAll($pdo, $force, $enforceNotNull, $syncSchema, $defaultValue);
            }

            if (empty($this->args[0]) || !is_string($this->args[0]) || str_starts_with($this->args[0], '--')) {
                $this->error("Usage: gemvc db:migrate TableClassName [--force] [--sync-schema]  OR  gemvc db:migrate --all");
                return false;
            }

            $tableClass = $this->args[0];
            return $this->migrateOne($pdo, $tableClass, $force, $enforceNotNull, $syncSchema, $defaultValue);
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return false;
        }
    }

    private function migrateAll(
        \PDO $pdo,
        bool $force,
        bool $enforceNotNull,
        bool $syncSchema,
        mixed $defaultValue
    ): bool {
        $dir = ProjectHelper::rootDir() . '/app/table';
        if (!is_dir($dir)) {
            $this->error("Table directory not found: {$dir}");
            return false;
        }

        $instances = [];
        foreach (glob($dir . '/*Table.php') ?: [] as $file) {
            $short = basename($file, '.php');
            require_once $file;
            $className = "App\\Table\\{$short}";
            if (!class_exists($className)) {
                $this->error("Class not found after loading {$file}: {$className}");
                return false;
            }
            $obj = new $className();
            if (!($obj instanceof Table)) {
                $this->info("Skipping {$short}: not a Table subclass");
                continue;
            }
            $instances[$short] = $obj;
        }

        if ($instances === []) {
            $this->info('No Table classes found in app/table');
            return true;
        }

        try {
            $ordered = TableMigrateOrder::order($instances);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return false;
        }

        $this->info('Migrate --all order: ' . implode(' → ', $ordered));

        foreach ($ordered as $short) {
            $this->info("--- Migrating {$short} ---");
            if (!$this->migrateOne($pdo, $short, $force, $enforceNotNull, $syncSchema, $defaultValue)) {
                return false;
            }
        }

        $this->success('db:migrate --all completed successfully');
        return true;
    }

    private function migrateOne(
        \PDO $pdo,
        string $tableClass,
        bool $force,
        bool $enforceNotNull,
        bool $syncSchema,
        mixed $defaultValue
    ): bool {
        $tableFile = ProjectHelper::rootDir() . '/app/table/' . $tableClass . '.php';

        if (!file_exists($tableFile)) {
            $this->error("Table file not found: {$tableFile}");
            return false;
        }

        require_once $tableFile;
        $className = "App\\Table\\{$tableClass}";
        if (!class_exists($className)) {
            $this->error("Table class not found: {$className}");
            return false;
        }

        $table = new $className();
        if (!($table instanceof Table)) {
            $this->error("Class {$className} must extend Table or ViewTable");
            return false;
        }

        $dialect = DialectResolver::resolve($pdo);

        if ($table instanceof ViewTable) {
            return $this->migrateView($pdo, $table, $dialect);
        }

        $generator = new TableGenerator($pdo, $dialect);
        $tableName = $table->getTable();
        $tableExists = $dialect->tableExists($pdo, $tableName);

        if ($tableExists) {
            $this->info("Table '{$tableName}' exists. Syncing with class definition...");
            if ($force) {
                $this->info('Force flag detected. Will remove columns not in class definition.');
            }
            if ($syncSchema) {
                $this->info('Schema sync enabled. Will remove obsolete constraints.');
            }
            if ($generator->updateTable($table, null, $force, $enforceNotNull, $defaultValue)) {
                $this->success("Table '{$tableName}' synchronized successfully!");
                $this->applySchemaConstraints($pdo, $table, $tableName, $syncSchema, $dialect);
                return true;
            }
            $this->error('Failed to sync table: ' . $generator->getError());
            return false;
        }

        $this->info("Table '{$tableName}' does not exist. Creating new table...");
        if ($generator->createTableFromObject($table)) {
            $this->applySchemaConstraints($pdo, $table, $tableName, $syncSchema, $dialect);
            $this->success("Table '{$tableName}' created successfully!");
            return true;
        }
        $this->error('Failed to create table: ' . $generator->getError());
        return false;
    }

    private function migrateView(
        \PDO $pdo,
        ViewTable $view,
        \Gemvc\Database\Dialect\SqlDialectInterface $dialect
    ): bool {
        $viewName = $view->getTable();
        $this->info("Migrating ViewTable '{$viewName}' (CREATE OR REPLACE VIEW)...");

        $generator = new ViewGenerator($pdo, $dialect);
        if ($generator->replaceView($view)) {
            $this->success("View '{$viewName}' created/replaced successfully!");
            return true;
        }
        $this->error('Failed to migrate view: ' . $generator->getError());
        return false;
    }

    /**
     * @param \Gemvc\Database\Dialect\SqlDialectInterface|null $dialect
     */
    private function applySchemaConstraints(\PDO $pdo, object $table, string $tableName, bool $syncSchema = false, ?\Gemvc\Database\Dialect\SqlDialectInterface $dialect = null): bool
    {
        if (!method_exists($table, 'defineSchema')) {
            $this->error("Table '{$tableName}' has no schema constraints defined.");
            return false;
        }

        $this->info('Processing schema constraints...');
        $schemaDefinition = $table->defineSchema();

        if (empty($schemaDefinition) && !$syncSchema) {
            $this->info('No schema constraints defined.');
            return false;
        }

        $schemaGenerator = new SchemaGenerator($pdo, $tableName, $schemaDefinition, $dialect);

        if ($schemaGenerator->applyConstraints($syncSchema)) {
            $summary = $schemaGenerator->getSummary();

            if (isset($summary['total_constraints']) && is_numeric($summary['total_constraints']) && $summary['total_constraints'] > 0) {
                $totalConstraints = (int) $summary['total_constraints'];
                $this->success("Applied {$totalConstraints} schema constraints successfully!");

                if (isset($summary['constraint_types']) && is_array($summary['constraint_types'])) {
                    foreach ($summary['constraint_types'] as $type => $count) {
                        $typeStr = is_string($type) ? $type : 'unknown';
                        $countStr = is_string($count) ? $count : (is_numeric($count) ? (string) $count : '0');
                        $this->info("  ✓ {$countStr} {$typeStr} constraint(s)");
                    }
                }
            } elseif ($syncSchema) {
                $this->info('Schema synchronized successfully (no constraints to add).');
                return true;
            } else {
                $this->info('No schema constraints to apply.');
                return true;
            }
        } else {
            $this->error('Failed to apply schema constraints: ' . $schemaGenerator->getError());
            return false;
        }

        return true;
    }
}

<?php

namespace Gemvc\CLI\Commands;

use Gemvc\CLI\Command;
use Gemvc\CLI\Commands\DbConnect;
use Gemvc\Helper\ProjectHelper;

/**
 * CLI Command to add a unique constraint to a table column.
 *
 * Usage:
 *   vendor/bin/gemvc db:unique table/column
 * Example:
 *   vendor/bin/gemvc db:unique users/email,name
 *
 * This command will:
 *   - Check for duplicate values in the specified column
 *   - If no duplicates, add a unique constraint to the column
 *   - If duplicates exist, abort and list the duplicates
 */
class DbUnique extends Command
{
    /**
     * Execute the command to add a unique constraint to a table column.
     *
     * @return bool
     */
    public function execute(): bool
    {
        // Check for required argument
        if (empty($this->args[0]) || !is_string($this->args[0])) {
            $this->error("Usage: gemvc db:unique table/column");
            return false;
        }

        // Parse table and columns from argument (format: table/col1,col2,...)
        list($table, $columns) = explode('/', $this->args[0]);
        $columnList = array_map('trim', explode(',', $columns));
        // @phpstan-ignore-next-line
        if (!$table || count($columnList) === 0) {
            $this->error("Invalid format. Use: gemvc db:unique table/col1,col2,...");
            return false;
        }

        // Load environment variables and connect to the database
        ProjectHelper::loadEnv();
        $pdo = DbConnect::connect();
        if (!$pdo) {
            $this->error("Could not connect to database.");
            return false;
        }

        // Check for duplicate combinations
        $colSql = implode('`,`', $columnList);
        $sql = "SELECT $colSql, COUNT(*) as cnt FROM `$table` GROUP BY $colSql HAVING cnt > 1";
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            $this->error("Failed to check for duplicates");
            return false;
        }
        $duplicates = $stmt->fetchAll();
        if ($duplicates) {
            $this->error("Cannot add unique constraint: Duplicate value combinations found in (" . implode(', ', $columnList) . ").");
            foreach ($duplicates as $row) {
                $values = [];
                foreach ($columnList as $col) {
                    $values[] = $col . '=' . $row[$col];
                }
                $this->write("Duplicate: " . implode(', ', $values));
                return false;
            }
        }

        // Try to add the unique constraint
        $constraintName = "unique_" . implode('_', $columnList);
        $colSqlBacktick = '`' . implode('`,`', $columnList) . '`';
        try {
            $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$constraintName` UNIQUE ($colSqlBacktick)");
            $this->success("Unique constraint added to `$table` on (" . implode(', ', $columnList) . ") successfully!");
            return true;
        } catch (\PDOException $e) {
            $this->error("Failed to add unique constraint: " . $e->getMessage());
            return false;
        }
    }
}

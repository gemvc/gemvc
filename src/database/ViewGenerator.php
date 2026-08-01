<?php

declare(strict_types=1);

namespace Gemvc\Database;

use Gemvc\Database\Dialect\DialectResolver;
use Gemvc\Database\Dialect\SqlDialectInterface;
use PDO;
use PDOException;

/**
 * Creates / replaces / drops SQL VIEWs from {@see ViewTable} definitions.
 * Does not infer columns from PHP properties (unlike {@see TableGenerator}).
 */
class ViewGenerator
{
    private PDO $pdo;
    private SqlDialectInterface $dialect;
    private string $error = '';

    public function __construct(PDO $pdo, ?SqlDialectInterface $dialect = null)
    {
        $this->pdo = $pdo;
        $this->dialect = $dialect ?? DialectResolver::resolve($pdo);
    }

    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Create or replace the view defined by $view.
     */
    public function replaceView(ViewTable $view): bool
    {
        $viewName = $view->getTable();
        if ($viewName === '') {
            $this->error = 'ViewTable::getTable() returned an empty name';
            return false;
        }

        $selectSql = trim($view->defineView());
        if ($selectSql === '') {
            $this->error = 'ViewTable::defineView() returned empty SQL';
            return false;
        }

        // Prefer not to CREATE TABLE if a base table already occupies the name
        if ($this->dialect->tableExists($this->pdo, $viewName) && !$this->dialect->viewExists($this->pdo, $viewName)) {
            $this->error = "Object '{$viewName}' exists as a base table, not a view. Refusing to migrate ViewTable over it.";
            return false;
        }

        $statements = $this->dialect->createOrReplaceViewSql($viewName, $selectSql);
        try {
            foreach ($statements as $sql) {
                $trimmed = trim($sql);
                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }
                $this->pdo->exec($trimmed);
            }
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * Drop the view if it exists.
     */
    public function dropView(ViewTable $view): bool
    {
        $viewName = $view->getTable();
        if ($viewName === '') {
            $this->error = 'ViewTable::getTable() returned an empty name';
            return false;
        }

        try {
            $this->pdo->exec($this->dialect->dropViewSql($viewName));
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
}

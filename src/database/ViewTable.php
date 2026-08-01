<?php

declare(strict_types=1);

namespace Gemvc\Database;

use Gemvc\Database\DatabaseManagerFactory;
use PDO;

/**
 * Base class for SQL VIEW read models.
 *
 * - {@see defineView()} supplies the SELECT body (compose other {@see Table} classes via getTable()).
 * - Public properties + $_type_map match view column aliases (flat, 1:1) — same as Table.
 * - Migrate creates/replaces a VIEW (never a physical table from properties).
 * - Row insert/update/delete are blocked; writes belong on base Tables.
 */
abstract class ViewTable extends Table
{
    /**
     * SELECT body (or full SELECT) for CREATE … VIEW … AS.
     * Aliases must match public column properties on this class.
     */
    abstract public function defineView(): string;

    /**
     * Table classes this view depends on (for db:migrate --all ordering).
     *
     * @return list<class-string<Table>>
     */
    public function viewDependsOn(): array
    {
        return [];
    }

    /**
     * Create the view if missing, or replace its definition.
     */
    public function createViewQuery(?PDO $pdo = null): bool
    {
        return $this->replaceViewQuery($pdo);
    }

    /**
     * Create or replace the VIEW from {@see defineView()}.
     */
    public function replaceViewQuery(?PDO $pdo = null): bool
    {
        try {
            $pdo ??= $this->resolvePdo();
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $generator = new ViewGenerator($pdo);
        if (!$generator->replaceView($this)) {
            $this->setError($generator->getError());
            return false;
        }

        return true;
    }

    /**
     * DROP VIEW IF EXISTS.
     */
    public function dropViewQuery(?PDO $pdo = null): bool
    {
        try {
            $pdo ??= $this->resolvePdo();
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $generator = new ViewGenerator($pdo);
        if (!$generator->dropView($this)) {
            $this->setError($generator->getError());
            return false;
        }

        return true;
    }

    public function insertSingleQuery(): ?static
    {
        $this->rejectRowWrite('insertSingleQuery');
        return null;
    }

    public function updateSingleQuery(): ?static
    {
        $this->rejectRowWrite('updateSingleQuery');
        return null;
    }

    public function deleteByIdQuery(int|string $id): int|string|null
    {
        $this->rejectRowWrite('deleteByIdQuery');
        return null;
    }

    public function deleteSingleQuery(): ?int
    {
        $this->rejectRowWrite('deleteSingleQuery');
        return null;
    }

    public function safeDeleteQuery(): ?static
    {
        $this->rejectRowWrite('safeDeleteQuery');
        return null;
    }

    public function restoreQuery(): ?static
    {
        $this->rejectRowWrite('restoreQuery');
        return null;
    }

    public function activateQuery(int|string $id): ?int
    {
        $this->rejectRowWrite('activateQuery');
        return null;
    }

    public function deactivateQuery(int|string $id): ?int
    {
        $this->rejectRowWrite('deactivateQuery');
        return null;
    }

    private function rejectRowWrite(string $method): void
    {
        $this->setError(
            "ViewTable '{$this->getTable()}' is read-only: {$method} is not allowed. Write via base Table classes."
        );
    }

    private function resolvePdo(): PDO
    {
        $manager = DatabaseManagerFactory::getManager();
        $connection = $manager->getConnection();
        if ($connection === null) {
            throw new \RuntimeException($manager->getError() ?? 'Database connection failed');
        }
        $pdo = $connection->getConnection();
        if (!($pdo instanceof PDO)) {
            throw new \RuntimeException('Connection did not return a valid PDO instance');
        }

        return $pdo;
    }
}

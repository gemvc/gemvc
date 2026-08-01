<?php

declare(strict_types=1);

namespace Gemvc\Database;

/**
 * Orders Table / ViewTable class names for {@code db:migrate --all}.
 * Base tables by Schema::foreignKey graph; views after tables via viewDependsOn().
 */
final class TableMigrateOrder
{
    /**
     * @param array<string, object> $instances Short class name (e.g. UserTable) => instance
     * @return list<string> Ordered short class names
     * @throws \RuntimeException On cycles or missing dependencies
     */
    public static function order(array $instances): array
    {
        $tables = [];
        $views = [];

        foreach ($instances as $shortName => $instance) {
            if ($instance instanceof ViewTable) {
                $views[$shortName] = $instance;
            } elseif ($instance instanceof Table) {
                $tables[$shortName] = $instance;
            }
        }

        $orderedTables = self::orderTablesByForeignKeys($tables);
        $orderedViews = self::orderViewsByDependsOn($views, array_merge($tables, $views));

        return array_merge($orderedTables, $orderedViews);
    }

    /**
     * @param array<string, Table> $tables
     * @return list<string>
     */
    private static function orderTablesByForeignKeys(array $tables): array
    {
        /** @var array<string, string> $nameToShort table SQL name => short class name */
        $nameToShort = [];
        foreach ($tables as $short => $table) {
            $nameToShort[$table->getTable()] = $short;
        }

        /** @var array<string, list<string>> $deps short => list of short names it depends on */
        $deps = [];
        foreach ($tables as $short => $table) {
            $deps[$short] = [];
            if (!method_exists($table, 'defineSchema')) {
                continue;
            }
            /** @var mixed $schema */
            $schema = $table->defineSchema();
            if (!is_array($schema)) {
                continue;
            }
            foreach ($schema as $constraint) {
                if (!$constraint instanceof ForeignKeyConstraint) {
                    continue;
                }
                $ref = $constraint->getReferences();
                $parts = explode('.', $ref, 2);
                $refTable = $parts[0];
                if ($refTable === '' || !isset($nameToShort[$refTable])) {
                    continue;
                }
                $depShort = $nameToShort[$refTable];
                if ($depShort !== $short && !in_array($depShort, $deps[$short], true)) {
                    $deps[$short][] = $depShort;
                }
            }
        }

        return self::topoSort(array_keys($tables), $deps, 'table');
    }

    /**
     * @param array<string, ViewTable> $views
     * @param array<string, Table> $allKnown short => Table|ViewTable for resolving class-string deps
     * @return list<string>
     */
    private static function orderViewsByDependsOn(array $views, array $allKnown): array
    {
        /** @var array<class-string, string> $fqcnToShort */
        $fqcnToShort = [];
        foreach ($allKnown as $short => $instance) {
            $fqcnToShort[$instance::class] = $short;
        }

        /** @var array<string, list<string>> $deps */
        $deps = [];
        foreach ($views as $short => $view) {
            $deps[$short] = [];
            foreach ($view->viewDependsOn() as $classString) {
                if (!isset($fqcnToShort[$classString])) {
                    // Dependency may be a Table already ordered separately — only track other views
                    if (!is_subclass_of($classString, ViewTable::class)) {
                        continue;
                    }
                    throw new \RuntimeException(
                        "View '{$short}' depends on missing class {$classString} (not found in app/table)"
                    );
                }
                $depShort = $fqcnToShort[$classString];
                if (isset($views[$depShort]) && $depShort !== $short && !in_array($depShort, $deps[$short], true)) {
                    $deps[$short][] = $depShort;
                }
            }
        }

        return self::topoSort(array_keys($views), $deps, 'view');
    }

    /**
     * @param list<string> $nodes
     * @param array<string, list<string>> $deps node => prerequisites
     * @return list<string>
     */
    private static function topoSort(array $nodes, array $deps, string $kind): array
    {
        $remaining = array_fill_keys($nodes, true);
        $ordered = [];

        while ($remaining !== []) {
            $progress = false;
            foreach (array_keys($remaining) as $node) {
                $prereqs = $deps[$node] ?? [];
                $blocked = false;
                foreach ($prereqs as $p) {
                    if (isset($remaining[$p])) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked) {
                    continue;
                }
                $ordered[] = $node;
                unset($remaining[$node]);
                $progress = true;
            }
            if (!$progress) {
                throw new \RuntimeException(
                    'Circular dependency detected while ordering ' . $kind . ' classes: ' . implode(', ', array_keys($remaining))
                );
            }
        }

        return $ordered;
    }
}

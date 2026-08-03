<?php

declare(strict_types=1);

namespace App\Table;

use Gemvc\Database\ViewTable;

/**
 * LAYER 4 — ViewTable (SQL VIEW read model): NOT a physical table.
 *
 * WHY ViewTable?
 *   Complex JOINs / aggregates belong in SQL as a VIEW, not in PHP loops
 *   and not as Eloquent-style hasMany magic. Inside one microservice, prefer
 *   a view; across services, call HTTP APIs instead.
 *
 * HOW TO USE THIS FILE
 *   Copy → app/table/UserOrderSummaryTable.php
 *   Migrate bases first, then the view:
 *     gemvc db:migrate UserTable
 *     gemvc db:migrate OrderTable
 *     gemvc db:migrate UserOrderSummaryTable
 *   Or: gemvc db:migrate --all  (uses viewDependsOn() for order)
 *
 * HARD RULES
 *   - Extend ViewTable (never point a plain Table at a view name and migrate it)
 *   - Public props + $_type_map must match SELECT aliases 1:1 (flat only)
 *   - insert/update/delete/soft-delete HARD-FAIL — write via UserTable / OrderTable
 *   - Nest 1:n collections in the Model (`$_recent_orders`), not in defineView()
 *
 * RELATED
 *   → UserTable, OrderTable (sources)
 *   → UserOrderSummaryModel / Controller / api/UserOrderSummary.php
 *   → docs/guides/database.md#sql-views-via-viewtable-recommended
 */
class UserOrderSummaryTable extends ViewTable
{
    // --- View column aliases (names match AS … in defineView) -------------

    /**
     * Logical row key = users.id aliased as user_id.
     * There is no property named `id` on this class — see setPrimaryKey() below.
     */
    public int $user_id;

    public string $user_name;
    public string $email;

    /** Null when the user has no role set. */
    public ?string $role;

    /** COUNT(o.id) from the LEFT JOIN. */
    public int $order_count;

    /**
     * COALESCE(SUM(o.total), 0) — money stays a string + type `decimal`.
     */
    public string $order_total;

    /** MAX(o.created_at); null if the user has zero orders. */
    public ?string $last_order_at;

    /**
     * Properties starting with `_` are IGNORED by insert/update/select hydration.
     * Perfect for Model-layer aggregations (load after the flat view row).
     * Never select this in defineView().
     *
     * @var list<object>
     */
    public array $_recent_orders = [];

    /**
     * Alias → type. Must cover every public DB-facing property above
     * (not `$_recent_orders` — underscore props are not columns).
     *
     * @var array<string, string>
     */
    protected array $_type_map = [
        'user_id' => 'int',
        'user_name' => 'string',
        'email' => 'string',
        'role' => 'string',
        'order_count' => 'int',
        'order_total' => 'decimal',
        'last_order_at' => 'datetime',
    ];

    public function __construct()
    {
        parent::__construct();

        // Teach the ORM which column selectById() / PK helpers should use.
        // Required because this view has `user_id`, not `id`.
        $this->setPrimaryKey('user_id', 'int');

        $this->role = null;
        $this->last_order_at = null;
        $this->order_count = 0;
        $this->order_total = '0.00';
    }

    /**
     * Name of the VIEW object in the database (CREATE VIEW user_order_summary AS …).
     * Migrate creates/replaces a VIEW — it never invents a physical table from these props.
     */
    public function getTable(): string
    {
        return 'user_order_summary';
    }

    /**
     * Declares base Table classes this view reads.
     * `db:migrate --all` migrates those tables first, then this view.
     *
     * @return list<class-string<\Gemvc\Database\Table>>
     */
    public function viewDependsOn(): array
    {
        return [
            UserTable::class,
            OrderTable::class,
        ];
    }

    /**
     * SELECT body for CREATE … VIEW … AS.
     *
     * Tips:
     *   - Resolve table names via (new XTable())->getTable() so renames stay safe
     *   - Every output column needs an alias matching a public property
     *   - Keep the result flat (one row shape) — no nested JSON arrays here
     */
    public function defineView(): string
    {
        $users = (new UserTable())->getTable();   // → "users"
        $orders = (new OrderTable())->getTable(); // → "orders"

        return <<<SQL
            SELECT
                u.id AS user_id,
                u.name AS user_name,
                u.email,
                u.role AS role,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(o.total), 0) AS order_total,
                MAX(o.created_at) AS last_order_at
            FROM {$users} u
            LEFT JOIN {$orders} o ON o.user_id = u.id
            GROUP BY u.id, u.name, u.email, u.role
        SQL;
    }

    // --- Custom read helpers (writes are blocked by ViewTable) ------------

    /**
     * Same idea as selectById() after setPrimaryKey('user_id').
     */
    public function selectByUserId(int $userId): ?static
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()->whereEqual('user_id', $userId)->limit(1)->run();

        return $rows[0] ?? null;
    }

    public function selectByEmail(string $email): ?static
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()->whereEqual('email', $email)->limit(1)->run();

        return $rows[0] ?? null;
    }

    /**
     * Users who have at least one order, highest spend first.
     *
     * @return list<static>|null
     */
    public function selectTopSpenders(int $limit = 50): ?array
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()
            ->whereBiggerThan('order_count', 0)
            ->orderBy('order_total', false) // DESC
            ->limit($limit)
            ->run();

        return $rows;
    }
}

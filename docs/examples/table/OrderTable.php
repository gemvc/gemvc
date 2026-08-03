<?php

declare(strict_types=1);

namespace App\Table;

use Gemvc\Database\Schema;
use Gemvc\Database\Table;

/**
 * LAYER 4 — Table (physical): maps 1:1 to the `orders` database table.
 *
 * HOW TO USE THIS FILE
 *   Copy → app/table/OrderTable.php
 *   Migrate AFTER users → gemvc db:migrate OrderTable
 *   (or gemvc db:migrate --all — FK order is automatic)
 *
 * MONEY RULE
 *   Never use float for currency. Use `string` + $_type_map `decimal`
 *   and compare/math with BCMath in the Model when needed.
 *
 * RELATED
 *   → UserTable (parent via user_id)
 *   → UserOrderSummaryTable (aggregates SUM(total) from this table)
 *   → OrderModel / OrderController / api/Order.php
 */
class OrderTable extends Table
{
    // --- Columns ----------------------------------------------------------

    public int $id;

    /** Foreign key → users.id (see defineSchema). */
    public int $user_id;

    /**
     * Order amount as a decimal STRING, e.g. "99.50".
     * PHP float is unsafe for money — keep it as string end-to-end.
     */
    public string $total;

    /** Business status flag (pending, paid, cancelled, …). */
    public string $status;

    public string $created_at;
    public ?string $updated_at;

    /**
     * @var array<string, string>
     */
    protected array $_type_map = [
        'id' => 'int',
        'user_id' => 'int',
        'total' => 'decimal',   // migrate → DECIMAL/NUMERIC; hydrate as string
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function __construct()
    {
        parent::__construct();

        // Defaults applied before insert if the API omitted optional fields.
        $this->total = '0.00';
        $this->status = 'pending';
        $this->created_at = '';
        $this->updated_at = null;
    }

    public function getTable(): string
    {
        return 'orders';
    }

    /**
     * Indexes + FK for migrate. Cascade delete: removing a user removes their orders.
     *
     * @return array<int, mixed>
     */
    public function defineSchema(): array
    {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),

            // FK target format: "parent_table.parent_column"
            Schema::foreignKey('user_id', 'users.id')->onDeleteCascade(),

            // Speeds JOIN / listByUser / ViewTable aggregation
            Schema::index('user_id')->name('idx_orders_user_id'),
            Schema::index('status'),
            Schema::index('created_at')->name('idx_orders_created')->timestamp(),
            Schema::index('updated_at')->name('idx_orders_updated'),
        ];
    }

    // --- Custom queries ---------------------------------------------------

    /**
     * All orders for one user (newest first).
     *
     * @return list<static>|null
     */
    public function selectByUserId(int $userId): ?array
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()
            ->whereEqual('user_id', $userId)
            ->orderBy('created_at', false) // false = DESC
            ->run();

        return $rows;
    }

    /**
     * Small recent slice — used by UserOrderSummaryModel::withRecentOrders()
     * to nest 1:n data AFTER reading the flat SQL VIEW (views cannot do 1:n).
     *
     * @return list<static>|null
     */
    public function selectRecentByUserId(int $userId, int $limit = 5): ?array
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()
            ->whereEqual('user_id', $userId)
            ->orderBy('created_at', false)
            ->limit($limit)
            ->run();

        return $rows;
    }

    /**
     * @return list<static>|null
     */
    public function selectByStatus(string $status): ?array
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()->whereEqual('status', $status)->run();

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace App\Table;

use Gemvc\Database\Schema;
use Gemvc\Database\Table;

/**
 * LAYER 4 — Table (physical): maps 1:1 to the `users` database table.
 *
 * HOW TO USE THIS FILE
 *   Copy → app/table/UserTable.php
 *   Migrate → gemvc db:migrate UserTable
 *
 * RULES (GEMVC)
 *   - Each public/protected property name = a real column name
 *   - Do NOT put business rules here (that is Model)
 *   - Prefer fluent select()/where*()->run() over raw SQL strings
 *
 * RELATED
 *   → Auth API for login/JWT: docs/examples/api/Auth.php
 *   → OrderTable (FK child)
 *   → UserOrderSummaryTable (SQL VIEW that JOINs users + orders)
 *   → UserModel / UserController / api/User.php
 */
class UserTable extends Table
{
    // --- Columns (property name MUST match the DB column) -----------------

    /** Auto-increment primary key. Property named `id` is how migrate creates the PK today. */
    public int $id;

    public string $name;

    public string $email;

    /**
     * `protected` = still a real DB column and still written on insert/update,
     * but it is NOT exposed by typical list/createList public-property scans.
     * Set via UserModel::setPassword() (Argon2i) — never store plain text.
     */
    protected string $password;

    public string $created_at;

    /** Nullable columns use `?type` so PHP and the ORM agree. */
    public ?string $updated_at;
    public ?string $description;
    public ?string $role;

    /**
     * Tells migrate / hydration how to cast each column.
     * Keys MUST match property names. Include protected columns too.
     *
     * @var array<string, string>
     */
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'password' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'description' => 'text',   // longer text column
        'role' => 'string',
    ];

    public function __construct()
    {
        parent::__construct();

        // Safe defaults so PHPStan / uninitialized props do not explode before hydrate.
        $this->updated_at = null;
        $this->description = null;
        $this->role = null;
        $this->created_at = '';
    }

    /**
     * Exact table name in the database (used by queries, migrate, and views).
     */
    public function getTable(): string
    {
        return 'users';
    }

    /**
     * Constraints/indexes for `gemvc db:migrate`.
     *
     * NOTE: Schema::primary / autoIncrement are documentational today —
     * migrate still treats a property named `id` as the physical PK.
     *
     * @return array<int, mixed>
     */
    public function defineSchema(): array
    {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),

            // One email per user
            Schema::unique('email')->name('uniq_users_email'),

            // Lookups / filters
            Schema::index('email')->name('idx_users_email'),
            Schema::index('role'),

            // ->timestamp() helps dialects set DEFAULT CURRENT_TIMESTAMP where supported
            Schema::index('created_at')->name('idx_users_created')->timestamp(),
            Schema::index('updated_at')->name('idx_users_updated'),

            // MySQL FULLTEXT (dialect may no-op or adapt on other engines)
            Schema::fullText(['name', 'description']),
        ];
    }

    // --- Custom queries (return typed null|static or null|list) -----------

    /**
     * Find one user by exact email (login / uniqueness checks).
     */
    public function selectByEmail(string $email): ?static
    {
        // Fluent builder → prepared statement under the hood (never concatenate SQL).
        /** @var array<static>|null $rows */
        $rows = $this->select()->whereEqual('email', $email)->limit(1)->run();

        return $rows[0] ?? null;
    }

    /**
     * LIKE search on name (used by ad-hoc queries; lists usually use createList + findable).
     *
     * @return list<static>|null
     */
    public function selectByName(string $name): ?array
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()->whereLike('name', $name)->run();

        return $rows;
    }

    /**
     * Exact role filter.
     *
     * @return list<static>|null
     */
    public function selectByRole(string $role): ?array
    {
        /** @var array<static>|null $rows */
        $rows = $this->select()->whereEqual('role', $role)->run();

        return $rows;
    }
}

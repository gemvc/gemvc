<?php

namespace Gemvc\Database;

/**
 * Schema constraint builder for defining database table constraints
 * Used in Table classes to define schema constraints that are applied during migrations
 * also it is used in the TableGenerator class to generate the schema for the table
 * is has unique constraints, foreign key constraints, indexes, primary key, auto increment, check constraints, fulltext constraints
 */
class Schema
{
    /**
     * Create a unique constraint
     * 
     * @param string|array<string> $columns Single column name or array of column names for composite constraint
     * @return UniqueConstraint
     */
    public static function unique(string|array $columns): UniqueConstraint
    {
        return new UniqueConstraint($columns);
    }

    /**
     * Create a foreign key constraint
     * 
     * @param string $column Local column name
     * @param string $references Referenced table.column (e.g., 'users.id')
     * @return ForeignKeyConstraint
     */
    public static function foreignKey(string $column, string $references): ForeignKeyConstraint
    {
        return new ForeignKeyConstraint($column, $references);
    }

    /**
     * Create an index
     * 
     * @param string|array<string> $columns Single column name or array of column names for composite index
     * @return IndexConstraint
     */
    public static function index(string|array $columns): IndexConstraint
    {
        return new IndexConstraint($columns);
    }

    /**
     * Create a primary key constraint
     * 
     * @param string|array<string> $columns Primary key column(s)
     * @return PrimaryKeyConstraint
     */
    public static function primary(string|array $columns): PrimaryKeyConstraint
    {
        return new PrimaryKeyConstraint($columns);
    }

    /**
     * Mark a column as auto increment
     * 
     * @param string $column Column name
     * @return AutoIncrementConstraint
     */
    public static function autoIncrement(string $column): AutoIncrementConstraint
    {
        return new AutoIncrementConstraint($column);
    }

    /**
     * Create a check constraint
     * 
     * @param string $expression Check expression (e.g., 'age > 0')
     * @return CheckConstraint
     */
    public static function check(string $expression): CheckConstraint
    {
        return new CheckConstraint($expression);
    }

    /**
     * Create a fulltext index
     * 
     * @param string|array<string> $columns Column(s) for fulltext search
     * @return FulltextConstraint
     */
    public static function fullText(string|array $columns): FulltextConstraint
    {
        return new FulltextConstraint($columns);
    }

}

/**
 * Base class for all schema constraints
 */
abstract class SchemaConstraint
{
    protected string $type;
    /** @var string|array<string> */
    protected string|array $columns;
    protected ?string $name = null;

    /**
     * @param string|array<string> $columns
     */
    public function __construct(string $type, string|array $columns)
    {
        $this->type = $type;
        $this->columns = $columns;
    }

    /**
     * Set a custom name for this constraint
     * 
     * @param string $name Constraint name
     * @return static
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the constraint type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the column(s) this constraint applies to
     * @return string|array<string>
     */
    public function getColumns(): string|array
    {
        return $this->columns;
    }

    /**
     * Get the constraint name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Convert constraint to array representation for migration tool
     * 
     * @return array<string,mixed>
     */
    abstract public function toArray(): array;
}

/**
 * Unique constraint
 */
class UniqueConstraint extends SchemaConstraint
{
    /**
     * @param string|array<string> $columns
     */
    public function __construct(string|array $columns)
    {
        parent::__construct('unique', $columns);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'columns' => $this->columns,
            'name' => $this->name
        ];
    }
}

/**
 * Foreign key constraint with fluent interface for referential actions
 */
class ForeignKeyConstraint extends SchemaConstraint
{
    private string $references;
    private string $onDelete = 'RESTRICT';
    private string $onUpdate = 'RESTRICT';

    public function __construct(string $column, string $references)
    {
        parent::__construct('foreign_key', $column);
        $this->references = $references;
    }

    /**
     * Set the ON DELETE action
     * 
     * @param string $action CASCADE, RESTRICT, SET_NULL, NO_ACTION
     * @return static
     */
    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    /**
     * Set the ON UPDATE action
     * 
     * @param string $action CASCADE, RESTRICT, SET_NULL, NO_ACTION  
     * @return static
     */
    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    /**
     * Set ON DELETE CASCADE - deletes related records when parent is deleted
     * 
     * @return static
     */
    public function onDeleteCascade(): static
    {
        $this->onDelete = 'CASCADE';
        return $this;
    }

    /**
     * Set ON DELETE RESTRICT - prevents deletion of parent if children exist
     * 
     * @return static
     */
    public function onDeleteRestrict(): static
    {
        $this->onDelete = 'RESTRICT';
        return $this;
    }

    /**
     * Set ON DELETE SET NULL - sets foreign key to NULL when parent is deleted
     * 
     * @return static
     */
    public function onDeleteSetNull(): static
    {
        $this->onDelete = 'SET_NULL';
        return $this;
    }

    /**
     * Set ON DELETE NO ACTION - same as RESTRICT (MySQL default)
     * 
     * @return static
     */
    public function onDeleteNoAction(): static
    {
        $this->onDelete = 'NO_ACTION';
        return $this;
    }

    /**
     * Set ON UPDATE CASCADE - updates related records when parent key changes
     * 
     * @return static
     */
    public function onUpdateCascade(): static
    {
        $this->onUpdate = 'CASCADE';
        return $this;
    }

    /**
     * Set ON UPDATE RESTRICT - prevents update of parent key if children exist
     * 
     * @return static
     */
    public function onUpdateRestrict(): static
    {
        $this->onUpdate = 'RESTRICT';
        return $this;
    }

    /**
     * Set ON UPDATE SET NULL - sets foreign key to NULL when parent key changes
     * 
     * @return static
     */
    public function onUpdateSetNull(): static
    {
        $this->onUpdate = 'SET_NULL';
        return $this;
    }

    /**
     * Set ON UPDATE NO ACTION - same as RESTRICT (MySQL default)
     * 
     * @return static
     */
    public function onUpdateNoAction(): static
    {
        $this->onUpdate = 'NO_ACTION';
        return $this;
    }

    public function getReferences(): string
    {
        return $this->references;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'column' => $this->columns,
            'references' => $this->references,
            'on_delete' => $this->onDelete,
            'on_update' => $this->onUpdate,
            'name' => $this->name
        ];
    }
}

/**
 * Index constraint
 */
class IndexConstraint extends SchemaConstraint
{
    private bool $unique = false;
    private bool $isTimestamp = false;

    /**
     * @param string|array<string> $columns
     */
    public function __construct(string|array $columns)
    {
        parent::__construct('index', $columns);
    }

    /**
     * Make this index unique
     * 
     * @return static
     */
    public function unique(): static
    {
        $this->unique = true;
        return $this;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * Mark this index as being for a timestamp column
     * Useful for documentation and metadata purposes
     * 
     * @return static
     */
    public function timestamp(): static
    {
        $this->isTimestamp = true;
        return $this;
    }

    public function isTimestamp(): bool
    {
        return $this->isTimestamp;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'columns' => $this->columns,
            'unique' => $this->unique,
            'timestamp' => $this->isTimestamp,
            'name' => $this->name
        ];
    }
}

/**
 * Primary key constraint
 */
class PrimaryKeyConstraint extends SchemaConstraint
{
    /**
     * @param string|array<string> $columns
     */
    public function __construct(string|array $columns)
    {
        parent::__construct('primary', $columns);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'columns' => $this->columns,
            'name' => $this->name
        ];
    }
}

/**
 * Auto increment constraint
 */
class AutoIncrementConstraint extends SchemaConstraint
{
    public function __construct(string $column)
    {
        parent::__construct('auto_increment', $column);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'column' => $this->columns,
            'name' => $this->name
        ];
    }
}

/**
 * Check constraint
 */
class CheckConstraint extends SchemaConstraint
{
    private string $expression;

    public function __construct(string $expression)
    {
        parent::__construct('check', []);
        $this->expression = $expression;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'expression' => $this->expression,
            'name' => $this->name
        ];
    }
}

/**
 * Fulltext index constraint
 */
class FulltextConstraint extends SchemaConstraint
{
    /**
     * @param string|array<string> $columns
     */
    public function __construct(string|array $columns)
    {
        parent::__construct('fulltext', $columns);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'columns' => $this->columns,
            'name' => $this->name
        ];
    }
}


/**example  
 * 
 * class UserTable extends Table
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    public string $password;
    public int $role_id;
    public ?string $created_at;
    public ?string $deleted_at;
    public bool $is_active;

    protected function defineSchema(): array 
    {
        return [
            // Primary key with auto increment
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            
            // Unique constraints
            Schema::unique('email'),                    // Single column unique
            Schema::unique(['name', 'email']),          // Composite unique
            
            // Foreign keys with different actions
            Schema::foreignKey('role_id', 'roles.id')->onDeleteRestrict(),
            Schema::foreignKey('parent_id', 'users.id')->onDeleteCascade(),
            Schema::foreignKey('category_id', 'categories.id')->onDeleteSetNull(),
            
            // Indexes for performance
            Schema::index('email'),                     // Single column index
            Schema::index(['name', 'is_active']),       // Composite index
            Schema::index('created_at')->name('idx_created')->timestamp(),  // Named index for timestamp column
            
            // Check constraints for data validation
            Schema::check('age >= 18')->name('valid_age'),
            Schema::check('salary > 0'),
            
            // Full-text search
            Schema::fulltext(['name', 'description'])
        ];
    }

    public function getTable(): string
    {
        return 'users';
    }
}
 * 
 * 
 */








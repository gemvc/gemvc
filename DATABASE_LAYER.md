# 🗄️ GEMVC Database Layer Documentation

Complete guide to GEMVC's Table layer - the foundation of all database operations.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Core Requirements](#core-requirements)
- [TypeMap (`$_type_map`)](#typemap-_type_map)
- [Schema Definition (`defineSchema()`)](#schema-definition-defineschema)
- [Required Methods](#required-methods)
- [Property Mapping](#property-mapping)
- [Schema Constraints](#schema-constraints)
- [Examples](#examples)
- [Best Practices](#best-practices)

---

## 🎯 Overview

The **Table Layer** is GEMVC's Data Access Layer (DAL). All table classes **MUST extend** the `Table` class and implement two critical components:

1. **`$_type_map`** - Maps properties to database column types
2. **`defineSchema()`** - Defines database constraints (indexes, unique, foreign keys, etc.)

---

## ⚠️ Core Requirements

### 1. **All Table Classes Must Extend `Table`**

```php
<?php
namespace App\Table;

use Gemvc\Database\Table;
use Gemvc\Database\Schema;

class UserTable extends Table  // ✅ MUST extend Table
{
    // Your implementation
}
```

### 2. **Required Methods**

Every table class **MUST** implement:

- `getTable(): string` - Returns database table name
- `defineSchema(): array` - Returns schema constraints array
- `$_type_map` - Property type mapping array

---

## 📊 TypeMap (`$_type_map`)

### Purpose

The `$_type_map` array maps your class properties to database column types. It's used by:
- `TableGenerator` - For creating/updating database tables
- Query execution - For proper type casting

### Structure

```php
protected array $_type_map = [
    'property_name' => 'php_type',
    // ...
];
```

### Available Types

| PHP Type | SQL Mapping | Description |
|----------|-------------|-------------|
| `int` | `INT(11)` | Integer (auto PRIMARY KEY if property is `id`) |
| `float` | `DOUBLE` | Floating point number |
| `bool` | `TINYINT(1)` | Boolean (0/1) |
| `string` | `VARCHAR(255)` | String (VARCHAR(320) for `email` properties) |
| `array` | `JSON` | Array stored as JSON |
| `datetime` | `DATETIME` | Date and time |

### Example

```php
class UserTable extends Table
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    protected string $password;
    
    /**
     * Type mapping for database operations
     * Maps each property to its PHP type
     */
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
        'password' => 'string',
    ];
}
```

### Important Notes

- ✅ **Include ALL properties** that map to database columns
- ✅ **Match property names exactly** (case-sensitive)
- ✅ **Use PHP types** (`int`, `string`, `bool`, etc.)
- ✅ **Include protected properties** that are stored in database
- ❌ **Don't include** properties starting with `_` (aggregation properties)

---

## 🏗️ Schema Definition (`defineSchema()`)

### Purpose

The `defineSchema()` method defines database constraints and relationships. It's used by:
- `TableGenerator` - For creating indexes, unique constraints, foreign keys
- `SchemaGenerator` - For managing database schema

### Method Signature

```php
public function defineSchema(): array
{
    return [
        // Schema constraints
    ];
}
```

### Available Schema Methods

#### 1. **Primary Key**

```php
Schema::primary('id')                    // Single column
Schema::primary(['id', 'tenant_id'])     // Composite primary key
```

#### 2. **Auto Increment**

```php
Schema::autoIncrement('id')
```

#### 3. **Unique Constraints**

```php
Schema::unique('email')                          // Single column
Schema::unique(['username', 'email'])             // Composite unique
Schema::unique('email')->name('unique_email')     // Named constraint
```

#### 4. **Foreign Keys**

```php
// Basic foreign key
Schema::foreignKey('user_id', 'users.id')

// With cascade delete
Schema::foreignKey('parent_id', 'users.id')->onDeleteCascade()

// With restrict delete
Schema::foreignKey('role_id', 'roles.id')->onDeleteRestrict()

// With set null delete
Schema::foreignKey('category_id', 'categories.id')->onDeleteSetNull()
```

#### 5. **Indexes**

```php
Schema::index('email')                                 // Single column
Schema::index(['name', 'is_active'])                   // Composite index
Schema::index('created_at')->name('idx_created')       // Named index
Schema::index('created_at')->timestamp()               // Mark as timestamp index (for metadata)
Schema::index('created_at')->name('idx_created')->timestamp()  // Named timestamp index
```

**Note:** The `timestamp()` method marks an index as being for a timestamp column. This is useful for documentation and metadata purposes, helping identify indexes that are specifically for date/time columns like `created_at`, `updated_at`, etc.

#### 6. **Check Constraints**

```php
Schema::check('age >= 18')->name('valid_age')
Schema::check('salary > 0')
```

#### 7. **Fulltext Search**

```php
Schema::fullText(['name', 'description'])
```

### Complete Example

```php
public function defineSchema(): array
{
    return [
        // Primary key with auto increment
        Schema::primary('id'),
        Schema::autoIncrement('id'),
        
        // Unique constraints
        Schema::unique('email'),                        // Single column unique
        Schema::unique(['username', 'email']),          // Composite unique
        
        // Foreign keys with different actions
        Schema::foreignKey('role_id', 'roles.id')->onDeleteRestrict(),
        Schema::foreignKey('parent_id', 'users.id')->onDeleteCascade(),
        Schema::foreignKey('category_id', 'categories.id')->onDeleteSetNull(),
        
        // Indexes for performance
        Schema::index('email'),                         // Single column index
        Schema::index(['name', 'is_active']),          // Composite index
        Schema::index('created_at')->name('idx_created'),  // Named index
        Schema::index('updated_at')->name('idx_updated')->timestamp(),  // Named timestamp index
        
        // Check constraints for data validation
        Schema::check('age >= 18')->name('valid_age'),
        Schema::check('salary > 0'),
        
        // Full-text search
        Schema::fullText(['name', 'description'])
    ];
}
```

---

## 🔧 Required Methods

### `getTable(): string`

**Purpose**: Returns the database table name.

**Required**: ✅ Yes - Must be implemented

**Example**:
```php
public function getTable(): string
{
    return 'users';  // Database table name
}
```

---

### `defineSchema(): array`

**Purpose**: Returns array of schema constraints.

**Required**: ✅ Yes - Should return array (can be empty)

**Example**:
```php
public function defineSchema(): array
{
    return [
        Schema::index('email'),
        Schema::unique('email'),
    ];
}
```

---

## 🗺️ Property Mapping

### Database Column Mapping

Properties in your table class **must match** database column names:

```php
class UserTable extends Table
{
    // Property name = Database column name
    public int $id;           // Maps to: `id` column
    public string $name;      // Maps to: `name` column
    public string $email;    // Maps to: `email` column
}
```

### Property Visibility

| Visibility | Database | SELECT Queries | INSERT/UPDATE |
|------------|----------|----------------|---------------|
| `public` | ✅ Included | ✅ Returned | ✅ Included |
| `protected` | ✅ Included | ❌ Not returned | ✅ Included |
| `private` | ✅ Included | ❌ Not returned | ✅ Included |
| `_property` | ❌ Ignored | ❌ Ignored | ❌ Ignored |

**Use Cases**:
- **`public`** - Normal database columns returned in queries
- **`protected`** - Database columns NOT returned in SELECT (e.g., `password`)
- **`_property`** - Aggregation/composition properties (not in database)

### Nullable Properties

Use `?` prefix for nullable columns:

```php
public ?string $description;  // NULL allowed
public string $name;          // NOT NULL
```

---

## 📚 Complete Example

### UserTable.php

```php
<?php
namespace App\Table;

use Gemvc\Database\Table;
use Gemvc\Database\Schema;

/**
 * User table class for handling User database operations
 */
class UserTable extends Table
{
    // Database columns (properties match column names)
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    protected string $password;  // Protected = not returned in SELECT
    
    /**
     * Type mapping for properties to database columns
     * Used by TableGenerator for schema generation
     */
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
        'password' => 'string',
    ];
    
    public function __construct()
    {
        parent::__construct();
        $this->description = null;
    }
    
    /**
     * Return database table name
     * REQUIRED METHOD
     */
    public function getTable(): string
    {
        return 'users';
    }
    
    /**
     * Define database schema constraints
     * REQUIRED METHOD
     */
    public function defineSchema(): array
    {
        return [
            // Primary key with auto increment
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            
            // Unique constraint on email
            Schema::unique('email'),
            
            // Indexes for performance
            Schema::index('email'),
            Schema::index('description'),
            Schema::index('created_at')->name('idx_created')->timestamp(),  // Named timestamp index
        ];
    }
    
    /**
     * Custom query method - Get user by ID
     */
    public function selectById(int $id): null|static
    {
        $result = $this->select()->where('id', $id)->limit(1)->run();
        return $result[0] ?? null;
    }
    
    /**
     * Custom query method - Get user by email
     */
    public function selectByEmail(string $email): null|static
    {
        $arr = $this->select()->where('email', $email)->limit(1)->run();
        return $arr[0] ?? null;
    }
    
    /**
     * Custom query method - Search by name
     */
    public function selectByName(string $name): null|array
    {
        return $this->select()->whereLike('name', $name)->run();
    }
}
```

---

## 🎯 Schema Constraints Reference

### Primary Key

```php
// Single column primary key
Schema::primary('id')

// Composite primary key
Schema::primary(['id', 'tenant_id'])
```

### Auto Increment

```php
Schema::autoIncrement('id')
```

### Unique Constraints

```php
// Single column
Schema::unique('email')

// Composite unique
Schema::unique(['username', 'email'])

// Named constraint
Schema::unique('email')->name('unique_user_email')
```

### Foreign Keys

```php
// Basic
Schema::foreignKey('user_id', 'users.id')

// Cascade delete (delete children when parent deleted)
Schema::foreignKey('parent_id', 'users.id')->onDeleteCascade()

// Restrict delete (prevent delete if children exist)
Schema::foreignKey('role_id', 'roles.id')->onDeleteRestrict()

// Set null on delete
Schema::foreignKey('category_id', 'categories.id')->onDeleteSetNull()
```

### Indexes

```php
// Single column index
Schema::index('email')

// Composite index
Schema::index(['name', 'is_active'])

// Named index
Schema::index('created_at')->name('idx_created_at')

// Timestamp index (for documentation/metadata)
Schema::index('created_at')->timestamp()

// Named timestamp index
Schema::index('created_at')->name('idx_created')->timestamp()
```

### Check Constraints

```php
// Age validation
Schema::check('age >= 18')->name('valid_age')

// Salary validation
Schema::check('salary > 0')

// Status validation
Schema::check("status IN ('active', 'inactive', 'pending')")
```

### Fulltext Search

```php
// Single column
Schema::fullText('description')

// Multiple columns
Schema::fullText(['name', 'description'])
```

---

## 💡 Best Practices

### 1. Always Extend Table

```php
// ✅ CORRECT
class UserTable extends Table { }

// ❌ WRONG
class UserTable { }
```

### 2. Implement Required Methods

```php
// ✅ REQUIRED
public function getTable(): string { return 'users'; }
public function defineSchema(): array { return []; }
protected array $_type_map = [];
```

### 3. Match Property Names to Columns

```php
// ✅ CORRECT - Property matches column name
public string $email;  // Maps to `email` column

// ❌ WRONG - Property doesn't match column
public string $userEmail;  // Should be: $email
```

### 4. Include All Properties in TypeMap

```php
// ✅ CORRECT - All properties included
protected array $_type_map = [
    'id' => 'int',
    'name' => 'string',
    'email' => 'string',
];

// ❌ WRONG - Missing properties
protected array $_type_map = [
    'id' => 'int',
    // Missing 'name' and 'email'
];
```

### 5. Use Protected for Sensitive Data

```php
// ✅ CORRECT - Password not returned in SELECT
protected string $password;

// ❌ WRONG - Password exposed in queries
public string $password;
```

### 6. Define Schema for Performance

```php
// ✅ CORRECT - Indexes defined
public function defineSchema(): array
{
    return [
        Schema::index('email'),
        Schema::unique('email'),
    ];
}

// ❌ WRONG - No indexes (slow queries)
public function defineSchema(): array
{
    return [];  // Empty - no optimization
}
```

### 7. Use Nullable Types for Optional Columns

```php
// ✅ CORRECT - Description can be NULL
public ?string $description;

// ❌ WRONG - Forces NOT NULL constraint
public string $description;
```

---

## 🔄 Migration Workflow

### 1. Create Table Class

```bash
gemvc create:table Product
```

### 2. Define Properties & TypeMap

```php
class ProductTable extends Table
{
    public int $id;
    public string $name;
    public float $price;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'price' => 'float',
    ];
}
```

### 3. Implement Required Methods

```php
public function getTable(): string
{
    return 'products';
}

public function defineSchema(): array
{
    return [
        Schema::primary('id'),
        Schema::autoIncrement('id'),
        Schema::index('name'),
    ];
}
```

### 4. Migrate to Database

```bash
gemvc db:migrate ProductTable
```

---

## 📖 TypeMap Reference

### PHP to SQL Type Mapping

| PHP Type | SQL Type | Notes |
|----------|----------|-------|
| `int` | `INT(11)` | Auto PRIMARY KEY if property is `id` |
| `float` | `DOUBLE` | Floating point number |
| `bool` | `TINYINT(1)` | Boolean (0/1) |
| `string` | `VARCHAR(255)` | String |
| `string` (email) | `VARCHAR(320)` | If property name ends with `email` |
| `array` | `JSON` | Array stored as JSON |
| `datetime` | `DATETIME` | Date and time |
| `?string` | `VARCHAR(255) NULL` | Nullable string |
| `?int` | `INT(11) NULL` | Nullable integer |

---

## 🎓 Summary

**Key Points**:

1. ✅ **All table classes MUST extend `Table`**
2. ✅ **Two critical components**:
   - `$_type_map` - Property type mapping
   - `defineSchema()` - Database constraints
3. ✅ **Required methods**:
   - `getTable(): string` - Table name
   - `defineSchema(): array` - Schema constraints
4. ✅ **Properties match database columns** (exact names)
5. ✅ **Use `protected` for sensitive data** (not returned in SELECT)
6. ✅ **Use `_` prefix for aggregation** (ignored in CRUD operations)

**Result**: Clean, type-safe database operations with automatic schema management! 🚀


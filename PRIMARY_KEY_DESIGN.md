# Primary Key Configuration System Design

## Requirements

1. **Backward Compatible**: Default to `id` (int) - existing code works without changes
2. **Flexible Types**: Support `int`, `string`, `uuid`
3. **Auto-Generation**: Auto-generate UUID when type is `uuid` and value not set
4. **Optional**: Some tables may not have primary key (middle tables)
5. **Composite Keys**: Support multiple columns for middle tables (future)

## Design Proposal

### Configuration Storage

```php
// Internal configuration
private ?array $_primaryKeyConfig = null;

// Structure when configured:
[
    'column' => 'id',        // or 'uuid', or ['user_id', 'role_id'] for composite
    'type' => 'int',         // or 'string', 'uuid', or ['int', 'int'] for composite
    'auto_generate' => false // or true for UUID
]

// Default (when not configured):
null = use default 'id' (int) behavior
```

### Public API

```php
/**
 * Configure primary key column and type
 * 
 * @param string $column Column name (default: 'id')
 * @param string $type Type: 'int', 'string', 'uuid' (default: 'int')
 * @return self For method chaining
 */
public function setPrimaryKey(string $column = 'id', string $type = 'int'): self

/**
 * Get primary key configuration
 * Returns default ['id', 'int'] if not configured
 * 
 * @return array{column: string, type: string, auto_generate: bool}
 */
protected function getPrimaryKeyConfig(): array

/**
 * Get primary key value from current object
 * Auto-generates UUID if needed
 * 
 * @return int|string|null Primary key value
 */
protected function getPrimaryKeyValue(): int|string|null

/**
 * Set primary key value on current object
 * 
 * @param int|string $value Primary key value
 * @return void
 */
protected function setPrimaryKeyValue(int|string $value): void
```

### Implementation Strategy

#### 1. Configuration Method

```php
public function setPrimaryKey(string $column = 'id', string $type = 'int'): self
{
    // Validate type
    if (!in_array($type, ['int', 'string', 'uuid'], true)) {
        $this->setError("Invalid primary key type: {$type}. Must be 'int', 'string', or 'uuid'");
        return $this;
    }
    
    $this->_primaryKeyConfig = [
        'column' => $column,
        'type' => $type,
        'auto_generate' => ($type === 'uuid')
    ];
    
    return $this;
}
```

#### 2. Get Configuration (with defaults)

```php
protected function getPrimaryKeyConfig(): array
{
    if ($this->_primaryKeyConfig === null) {
        // Default: 'id' (int)
        return [
            'column' => 'id',
            'type' => 'int',
            'auto_generate' => false
        ];
    }
    return $this->_primaryKeyConfig;
}
```

#### 3. Get/Set Primary Key Value

```php
protected function getPrimaryKeyValue(): int|string|null
{
    $config = $this->getPrimaryKeyConfig();
    $column = $config['column'];
    
    // Check if property exists
    if (!property_exists($this, $column)) {
        return null;
    }
    
    $value = $this->$column;
    
    // Auto-generate UUID if needed
    if ($config['type'] === 'uuid' && ($value === null || $value === '')) {
        $value = $this->generateUuid();
        $this->setPrimaryKeyValue($value);
    }
    
    return $value;
}

protected function setPrimaryKeyValue(int|string $value): void
{
    $config = $this->getPrimaryKeyConfig();
    $column = $config['column'];
    
    if (property_exists($this, $column)) {
        $this->$column = $value;
    }
}
```

#### 4. UUID Generation

```php
protected function generateUuid(): string
{
    // Use ramsey/uuid if available, otherwise native PHP
    if (class_exists('\Ramsey\Uuid\Uuid')) {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
    
    // Native PHP UUID v4 generation (RFC 4122 compliant)
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
```

#### 5. Update insertSingleQuery()

```php
public function insertSingleQuery(): ?static
{
    $config = $this->getPrimaryKeyConfig();
    
    // Auto-generate UUID if needed
    if ($config['auto_generate'] && $config['type'] === 'uuid') {
        $pkValue = $this->getPrimaryKeyValue();
        if ($pkValue === null || $pkValue === '') {
            $this->setPrimaryKeyValue($this->generateUuid());
        }
    }
    
    // ... existing insert logic ...
    
    // After insert, set primary key value if auto-increment
    if ($result !== null && $config['type'] === 'int' && $config['column'] === 'id') {
        // For int IDs, use the returned auto-increment value
        $this->setPrimaryKeyValue($result);
    } elseif ($result !== null && $config['type'] === 'uuid') {
        // For UUID, we already set it before insert
        // Just verify it was inserted
    }
    
    return $this;
}
```

#### 6. Update methods that use `id`

Replace:
- `property_exists($this, 'id')` → `property_exists($this, $config['column'])`
- `$this->id` → `$this->getPrimaryKeyValue()`
- `'id'` in SQL → `$config['column']`
- `validateId($this->id)` → `validatePrimaryKey($this->getPrimaryKeyValue())`

### Usage Examples

#### Example 1: Default (Backward Compatible)
```php
class UserTable extends Table {
    public int $id;
    // No configuration needed - works as before!
}
```

#### Example 2: UUID Primary Key
```php
class ProductTable extends Table {
    public string $uuid;
    
    public function __construct() {
        parent::__construct();
        $this->setPrimaryKey('uuid', 'uuid'); // Auto-generates UUID
    }
}
```

#### Example 3: String Primary Key
```php
class DocumentTable extends Table {
    public string $document_id;
    
    public function __construct() {
        parent::__construct();
        $this->setPrimaryKey('document_id', 'string');
    }
}
```

### Migration Strategy

1. **Add configuration properties and methods** (non-breaking)
2. **Update insertSingleQuery()** to handle UUID generation
3. **Create helper methods** `getPrimaryKeyValue()`, `setPrimaryKeyValue()`
4. **Gradually update methods** to use configuration instead of hardcoded `id`
5. **Test thoroughly** to ensure backward compatibility

### Backward Compatibility Guarantees

- ✅ Existing code works without changes (defaults to `id`, `int`)
- ✅ `property_exists($this, 'id')` checks still work
- ✅ `$this->id` access still works
- ✅ No breaking changes to public API

### Future: Composite Keys

For Phase 2 (after refactoring), we can extend to:
```php
public function setPrimaryKey(string|array $columns, string|array $types = 'int'): self
```

But for now, let's keep it simple with single primary key support.


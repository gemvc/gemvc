<?php

declare(strict_types=1);

namespace Tests\Unit\Database\TableComponents;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\TableComponents\PropertyCaster;

/**
 * Test class for PropertyCaster
 */
class PropertyCasterTest extends TestCase
{
    private PropertyCaster $caster;
    
    protected function setUp(): void
    {
        parent::setUp();
        $typeMap = [
            'id' => 'int',
            'nullable_id' => '?int',
            'price' => 'float',
            'nullable_price' => '?float',
            'is_active' => 'bool',
            'nullable_flag' => '?bool',
            'name' => 'string',
            'description' => '?string',
            'created_at' => 'datetime',
            'deleted_at' => '?datetime',
            'tags' => 'array',
            'metadata' => '?array',
            'date_field' => 'date',
            'json_field' => 'json',
        ];
        $this->caster = new PropertyCaster($typeMap);
    }
    
    // ==========================================
    // castValue Tests
    // ==========================================
    
    public function testCastValueInt(): void
    {
        $result = $this->caster->castValue('id', '123');
        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
        
        $result = $this->caster->castValue('id', 456);
        $this->assertIsInt($result);
        $this->assertEquals(456, $result);
    }
    
    public function testCastValueNullableInt(): void
    {
        $result = $this->caster->castValue('nullable_id', '123');
        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
        
        $result = $this->caster->castValue('nullable_id', null);
        $this->assertNull($result);
    }
    
    public function testCastValueFloat(): void
    {
        $result = $this->caster->castValue('price', '99.99');
        $this->assertIsFloat($result);
        $this->assertEquals(99.99, $result);
        
        $result = $this->caster->castValue('price', 123.45);
        $this->assertIsFloat($result);
        $this->assertEquals(123.45, $result);
    }
    
    public function testCastValueNullableFloat(): void
    {
        $result = $this->caster->castValue('nullable_price', '99.99');
        $this->assertIsFloat($result);
        $this->assertEquals(99.99, $result);
        
        $result = $this->caster->castValue('nullable_price', null);
        $this->assertNull($result);
    }
    
    public function testCastValueBool(): void
    {
        // Test various boolean representations
        $this->assertTrue($this->caster->castValue('is_active', true));
        $this->assertTrue($this->caster->castValue('is_active', '1'));
        $this->assertTrue($this->caster->castValue('is_active', 'true'));
        $this->assertTrue($this->caster->castValue('is_active', 'yes'));
        $this->assertTrue($this->caster->castValue('is_active', 'on'));
        $this->assertTrue($this->caster->castValue('is_active', 'y'));
        $this->assertTrue($this->caster->castValue('is_active', 1));
        
        $this->assertFalse($this->caster->castValue('is_active', false));
        $this->assertFalse($this->caster->castValue('is_active', '0'));
        $this->assertFalse($this->caster->castValue('is_active', 'false'));
        $this->assertFalse($this->caster->castValue('is_active', 'no'));
        $this->assertFalse($this->caster->castValue('is_active', 'off'));
        $this->assertFalse($this->caster->castValue('is_active', 'n'));
        $this->assertFalse($this->caster->castValue('is_active', ''));
        $this->assertFalse($this->caster->castValue('is_active', 0));
    }
    
    public function testCastValueNullableBool(): void
    {
        $result = $this->caster->castValue('nullable_flag', true);
        $this->assertTrue($result);
        
        $result = $this->caster->castValue('nullable_flag', null);
        $this->assertNull($result);
    }
    
    public function testCastValueString(): void
    {
        $result = $this->caster->castValue('name', 'Test Name');
        $this->assertIsString($result);
        $this->assertEquals('Test Name', $result);
        
        $result = $this->caster->castValue('name', 123);
        $this->assertIsString($result);
        $this->assertEquals('123', $result);
    }
    
    public function testCastValueNullableString(): void
    {
        $result = $this->caster->castValue('description', 'Test');
        $this->assertIsString($result);
        $this->assertEquals('Test', $result);
        
        $result = $this->caster->castValue('description', null);
        $this->assertNull($result);
    }
    
    public function testCastValueDateTime(): void
    {
        $result = $this->caster->castValue('created_at', '2024-01-15 10:30:00');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertEquals('2024-01-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }
    
    public function testCastValueNullableDateTime(): void
    {
        $result = $this->caster->castValue('deleted_at', '2024-01-15 10:30:00');
        $this->assertInstanceOf(\DateTime::class, $result);
        
        $result = $this->caster->castValue('deleted_at', null);
        $this->assertNull($result);
    }
    
    public function testCastValueArray(): void
    {
        $result = $this->caster->castValue('tags', ['tag1', 'tag2']);
        $this->assertIsArray($result);
        $this->assertEquals(['tag1', 'tag2'], $result);
        
        $result = $this->caster->castValue('tags', '["tag1", "tag2"]');
        $this->assertIsArray($result);
        $this->assertEquals(['tag1', 'tag2'], $result);
    }
    
    public function testCastValueNullableArray(): void
    {
        $result = $this->caster->castValue('metadata', ['key' => 'value']);
        $this->assertIsArray($result);
        $this->assertEquals(['key' => 'value'], $result);
        
        $result = $this->caster->castValue('metadata', null);
        $this->assertNull($result);
    }
    
    public function testCastValueNullForNonNullableTypes(): void
    {
        // Non-nullable int should return 0
        $this->assertEquals(0, $this->caster->castValue('id', null));
        
        // Non-nullable float should return 0.0
        $this->assertEquals(0.0, $this->caster->castValue('price', null));
        
        // Non-nullable bool should return false
        $this->assertFalse($this->caster->castValue('is_active', null));
        
        // Non-nullable string should return ''
        $this->assertEquals('', $this->caster->castValue('name', null));
        
        // Non-nullable array should return []
        $this->assertEquals([], $this->caster->castValue('tags', null));
        
        // Non-nullable datetime should return DateTime('now')
        $result = $this->caster->castValue('created_at', null);
        $this->assertInstanceOf(\DateTime::class, $result);
    }
    
    public function testCastValuePropertyNotInTypeMap(): void
    {
        // Property not in type map should return value as-is
        $this->assertEquals('test', $this->caster->castValue('unknown_property', 'test'));
        $this->assertEquals(123, $this->caster->castValue('unknown_property', 123));
        $this->assertNull($this->caster->castValue('unknown_property', null));
    }
    
    public function testCastValueDateType(): void
    {
        $result = $this->caster->castValue('date_field', '2024-01-15');
        $this->assertInstanceOf(\DateTime::class, $result);
    }
    
    public function testCastValueJsonType(): void
    {
        $result = $this->caster->castValue('json_field', '{"key": "value"}');
        $this->assertIsArray($result);
        $this->assertEquals(['key' => 'value'], $result);
    }
    
    public function testCastValueInvalidInt(): void
    {
        // Invalid int value should return 0 (or null for nullable)
        $result = $this->caster->castValue('id', 'invalid');
        $this->assertEquals(0, $result);
        
        $result = $this->caster->castValue('nullable_id', 'invalid');
        $this->assertNull($result);
    }
    
    public function testCastValueInvalidFloat(): void
    {
        // Invalid float value should return 0.0 (or null for nullable)
        $result = $this->caster->castValue('price', 'invalid');
        $this->assertEquals(0.0, $result);
        
        $result = $this->caster->castValue('nullable_price', 'invalid');
        $this->assertNull($result);
    }
    
    public function testCastValueInvalidDateTime(): void
    {
        // Invalid datetime should return DateTime('now') (or null for nullable)
        $result = $this->caster->castValue('created_at', 'invalid-date');
        $this->assertInstanceOf(\DateTime::class, $result);
        
        $result = $this->caster->castValue('deleted_at', 'invalid-date');
        $this->assertNull($result);
    }
    
    // ==========================================
    // fetchRow Tests
    // ==========================================
    
    public function testFetchRow(): void
    {
        $instance = new class {
            public int $id = 0;
            public string $name = '';
            public ?string $description = null;
        };
        
        $row = [
            'id' => '123',
            'name' => 'Test Name',
            'description' => 'Test Description',
        ];
        
        $typeMap = [
            'id' => 'int',
            'name' => 'string',
            'description' => '?string',
        ];
        $caster = new PropertyCaster($typeMap);
        
        $caster->fetchRow($instance, $row);
        
        $this->assertIsInt($instance->id);
        $this->assertEquals(123, $instance->id);
        $this->assertIsString($instance->name);
        $this->assertEquals('Test Name', $instance->name);
        $this->assertIsString($instance->description);
        $this->assertEquals('Test Description', $instance->description);
    }
    
    public function testFetchRowWithNullValues(): void
    {
        $instance = new class {
            public int $id = 0;
            public ?string $description = null;
        };
        
        $row = [
            'id' => '123',
            'description' => null,
        ];
        
        $typeMap = [
            'id' => 'int',
            'description' => '?string',
        ];
        $caster = new PropertyCaster($typeMap);
        
        $caster->fetchRow($instance, $row);
        
        $this->assertEquals(123, $instance->id);
        $this->assertNull($instance->description);
    }
    
    public function testFetchRowWithMissingProperties(): void
    {
        $instance = new class {
            public int $id = 0;
        };
        
        $row = [
            'id' => '123',
            'unknown_field' => 'value',
        ];
        
        $typeMap = ['id' => 'int'];
        $caster = new PropertyCaster($typeMap);
        
        $caster->fetchRow($instance, $row);
        
        $this->assertEquals(123, $instance->id);
        // unknown_field should be ignored (property doesn't exist)
        $this->assertFalse(property_exists($instance, 'unknown_field'));
    }
    
    public function testFetchRowIntegrationWithCastValue(): void
    {
        $instance = new class {
            public int $id = 0;
            public ?int $nullable_id = null;
            public float $price = 0.0;
            public ?float $nullable_price = null;
            public bool $is_active = false;
            public ?bool $nullable_flag = null;
            public string $name = '';
            public ?string $description = null;
            public \DateTime $created_at;
            public ?\DateTime $deleted_at = null;
            public array $tags = [];
            public ?array $metadata = null;
        };
        
        $row = [
            'id' => '123',
            'nullable_id' => null,
            'price' => '99.99',
            'nullable_price' => null,
            'is_active' => 'true',
            'nullable_flag' => null,
            'name' => 'Test Name',
            'description' => null,
            'created_at' => '2024-01-15 10:30:00',
            'deleted_at' => null,
            'tags' => '["tag1", "tag2"]',
            'metadata' => null,
        ];
        
        $this->caster->fetchRow($instance, $row);
        
        $this->assertIsInt($instance->id);
        $this->assertEquals(123, $instance->id);
        $this->assertNull($instance->nullable_id);
        $this->assertIsFloat($instance->price);
        $this->assertEquals(99.99, $instance->price);
        $this->assertNull($instance->nullable_price);
        $this->assertIsBool($instance->is_active);
        $this->assertTrue($instance->is_active);
        $this->assertNull($instance->nullable_flag);
        $this->assertIsString($instance->name);
        $this->assertEquals('Test Name', $instance->name);
        $this->assertNull($instance->description);
        $this->assertInstanceOf(\DateTime::class, $instance->created_at);
        $this->assertNull($instance->deleted_at);
        $this->assertIsArray($instance->tags);
        $this->assertEquals(['tag1', 'tag2'], $instance->tags);
        $this->assertNull($instance->metadata);
    }
}


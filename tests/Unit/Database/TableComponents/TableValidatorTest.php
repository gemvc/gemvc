<?php

declare(strict_types=1);

namespace Tests\Unit\Database\TableComponents;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Table;
use Gemvc\Database\TableComponents\TableValidator;

/**
 * Mock Table class for testing TableValidator
 */
class MockTableForValidator extends Table
{
    public int $id = 1;
    public string $name = 'Test';
    public string $email = 'test@example.com';
    public ?string $description = null;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
    ];
    
    public function getTable(): string
    {
        return 'test_table';
    }
    
    public function defineSchema(): array
    {
        return [];
    }
}

class TableValidatorTest extends TestCase
{
    private TableValidator $validator;
    private MockTableForValidator $table;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->table = new MockTableForValidator();
        $this->validator = new TableValidator($this->table);
    }
    
    // ==========================================
    // validateProperties Tests
    // ==========================================
    
    public function testValidatePropertiesWithExistingProperties(): void
    {
        $this->table->name = 'Test';
        $this->table->email = 'test@example.com';
        
        $result = $this->validator->validateProperties(['name', 'email']);
        $this->assertTrue($result);
        $this->assertNull($this->table->getError());
    }
    
    public function testValidatePropertiesWithNonExistentProperty(): void
    {
        $result = $this->validator->validateProperties(['nonexistent']);
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('nonexistent', $error);
    }
    
    public function testValidatePropertiesWithMixedProperties(): void
    {
        $this->table->name = 'Test';
        
        $result = $this->validator->validateProperties(['name', 'nonexistent']);
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('nonexistent', $error);
    }
    
    public function testValidatePropertiesWithEmptyArray(): void
    {
        $result = $this->validator->validateProperties([]);
        $this->assertTrue($result);
    }
    
    // ==========================================
    // validateId Tests
    // ==========================================
    
    public function testValidateIdWithValidId(): void
    {
        $result = $this->validator->validateId(1, 'test operation');
        $this->assertTrue($result);
        $this->assertNull($this->table->getError());
    }
    
    public function testValidateIdWithZero(): void
    {
        $result = $this->validator->validateId(0, 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('positive integer', $error);
        $this->assertStringContainsString('test operation', $error);
    }
    
    public function testValidateIdWithNegative(): void
    {
        $result = $this->validator->validateId(-1, 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('positive integer', $error);
    }
    
    public function testValidateIdWithDefaultOperation(): void
    {
        $result = $this->validator->validateId(0);
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('operation', $error);
    }
    
    // ==========================================
    // validatePrimaryKey Tests
    // ==========================================
    
    public function testValidatePrimaryKeyIntWithValidValue(): void
    {
        $result = $this->validator->validatePrimaryKey(1, 'id', 'int', 'test operation');
        $this->assertTrue($result);
        $this->assertNull($this->table->getError());
    }
    
    public function testValidatePrimaryKeyIntWithZero(): void
    {
        $result = $this->validator->validatePrimaryKey(0, 'id', 'int', 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('positive integer', $error);
        $this->assertStringContainsString('id', $error);
    }
    
    public function testValidatePrimaryKeyIntWithNegative(): void
    {
        $result = $this->validator->validatePrimaryKey(-1, 'id', 'int', 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('positive integer', $error);
    }
    
    public function testValidatePrimaryKeyIntWithNull(): void
    {
        $result = $this->validator->validatePrimaryKey(null, 'id', 'int', 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('must be set', $error);
        $this->assertStringContainsString('id', $error);
    }
    
    public function testValidatePrimaryKeyStringWithValidValue(): void
    {
        $result = $this->validator->validatePrimaryKey('abc123', 'uuid', 'string', 'test operation');
        $this->assertTrue($result);
        $this->assertNull($this->table->getError());
    }
    
    public function testValidatePrimaryKeyStringWithEmptyString(): void
    {
        $result = $this->validator->validatePrimaryKey('', 'uuid', 'string', 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('non-empty string', $error);
        $this->assertStringContainsString('uuid', $error);
    }
    
    public function testValidatePrimaryKeyStringWithNull(): void
    {
        $result = $this->validator->validatePrimaryKey(null, 'uuid', 'string', 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('must be set', $error);
    }
    
    public function testValidatePrimaryKeyUuidWithValidValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $result = $this->validator->validatePrimaryKey($uuid, 'uuid', 'uuid', 'test operation');
        $this->assertTrue($result);
        $this->assertNull($this->table->getError());
    }
    
    public function testValidatePrimaryKeyUuidWithEmptyString(): void
    {
        $result = $this->validator->validatePrimaryKey('', 'uuid', 'uuid', 'test operation');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('non-empty string', $error);
    }
    
    public function testValidatePrimaryKeyWithDefaultOperation(): void
    {
        $result = $this->validator->validatePrimaryKey(null, 'id', 'int');
        $this->assertFalse($result);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('operation', $error);
    }
    
    // ==========================================
    // Integration Tests
    // ==========================================
    
    public function testMultipleValidations(): void
    {
        // First validation passes
        $result1 = $this->validator->validateProperties(['name', 'email']);
        $this->assertTrue($result1);
        
        // Second validation fails
        $result2 = $this->validator->validateProperties(['nonexistent']);
        $this->assertFalse($result2);
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('nonexistent', $error);
    }
    
    public function testErrorMessagesIncludeTableName(): void
    {
        $this->validator->validatePrimaryKey(null, 'id', 'int', 'test operation');
        $error = $this->table->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('test_table', $error);
    }
}


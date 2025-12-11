<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\SchemaGenerator;
use Gemvc\Database\Schema;
use Gemvc\Database\UniqueConstraint;
use Gemvc\Database\IndexConstraint;
use Gemvc\Database\ForeignKeyConstraint;
use Gemvc\Database\PrimaryKeyConstraint;
use Gemvc\Database\CheckConstraint;
use Gemvc\Database\FulltextConstraint;
use PDO;
use PDOStatement;
use PDOException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @outputBuffering enabled
 */
class SchemaGeneratorTest extends TestCase
{
    private MockObject $mockPdo;
    private MockObject $mockStatement;
    private string $tableName = 'test_table';

    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        
        // Create mock PDO
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStatement = $this->createMock(PDOStatement::class);
    }

    /**
     * Create a SchemaGenerator instance with mocked PDO
     * 
     * @param array<mixed> $schema
     * @return SchemaGenerator
     */
    private function createGenerator(array $schema = []): SchemaGenerator
    {
        /** @var PDO $pdo */
        $pdo = $this->mockPdo;
        return new SchemaGenerator($pdo, $this->tableName, $schema);
    }

    // ============================================
    // Constructor Tests
    // ============================================

    public function testConstructorInitializesProperties(): void
    {
        $schema = [];
        $generator = $this->createGenerator($schema);
        
        $reflection = new ReflectionClass($generator);
        
        $pdoProperty = $reflection->getProperty('pdo');
        $this->assertSame($this->mockPdo, $pdoProperty->getValue($generator));
        
        $tableNameProperty = $reflection->getProperty('tableName');
        $this->assertEquals($this->tableName, $tableNameProperty->getValue($generator));
        
        $schemaProperty = $reflection->getProperty('schema');
        $this->assertEquals($schema, $schemaProperty->getValue($generator));
    }

    public function testConstructorStoresSchemaConstraints(): void
    {
        $constraint1 = Schema::unique('email');
        $constraint2 = Schema::index('name');
        $schema = [$constraint1, $constraint2];
        
        $generator = $this->createGenerator($schema);
        
        $reflection = new ReflectionClass($generator);
        $schemaProperty = $reflection->getProperty('schema');
        
        $storedSchema = $schemaProperty->getValue($generator);
        $this->assertCount(2, $storedSchema);
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testGetErrorReturnsEmptyStringInitially(): void
    {
        $generator = $this->createGenerator();
        
        $this->assertEquals('', $generator->getError());
    }

    public function testGetErrorReturnsErrorAfterFailure(): void
    {
        $generator = $this->createGenerator();
        
        // Create a constraint object with toArray() that returns an unknown type
        $invalidConstraint = new class {
            public function toArray(): array {
                return ['type' => 'unknown_type'];
            }
        };
        
        $reflection = new ReflectionClass($generator);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setValue($generator, [$invalidConstraint]);
        
        $result = $generator->applyConstraints();
        
        $this->assertFalse($result);
        $this->assertNotEmpty($generator->getError());
        $this->assertStringContainsString('Unknown constraint type', $generator->getError());
    }

    // ============================================
    // Process Schema Constraints Tests
    // ============================================

    public function testProcessSchemaConstraintsReturnsArray(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('processSchemaConstraints');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testProcessSchemaConstraintsCallsToArray(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('processSchemaConstraints');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result[0]);
        $this->assertArrayHasKey('type', $result[0]);
        $this->assertEquals('unique', $result[0]['type']);
    }

    public function testProcessSchemaConstraintsHandlesNonObjectConstraints(): void
    {
        $generator = $this->createGenerator(['not_an_object', 123, null]);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('processSchemaConstraints');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProcessSchemaConstraintsHandlesObjectWithoutToArray(): void
    {
        $objectWithoutToArray = new \stdClass();
        $generator = $this->createGenerator([$objectWithoutToArray]);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('processSchemaConstraints');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // Apply Constraints Tests
    // ============================================

    public function testApplyConstraintsReturnsTrueWithEmptySchema(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->applyConstraints();
        
        $this->assertTrue($result);
        $this->assertEquals('', $generator->getError());
    }

    public function testApplyConstraintsReturnsTrueOnSuccess(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        // Mock constraintExists to return false (constraint doesn't exist)
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0); // Constraint doesn't exist
        $this->mockPdo->method('exec')->willReturn(1); // Success
        
        $result = $generator->applyConstraints();
        
        $this->assertTrue($result);
    }

    public function testApplyConstraintsSkipsExistingConstraints(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        // Mock constraintExists to return true (constraint already exists)
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1); // Constraint exists
        
        $result = $generator->applyConstraints();
        
        $this->assertTrue($result);
        // exec should not be called since constraint exists
        $this->mockPdo->expects($this->never())->method('exec');
    }

    public function testApplyConstraintsHandlesException(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        // Mock to throw exception during constraint existence check
        $this->mockPdo->method('prepare')->willThrowException(new \Exception('Database error'));
        
        $result = $generator->applyConstraints();
        
        $this->assertFalse($result);
        // Exception is caught in executeConstraints, which sets a specific error message
        $this->assertStringContainsString('Failed to apply', $generator->getError());
        $this->assertStringContainsString('Database error', $generator->getError());
    }

    public function testApplyConstraintsWithRemoveObsolete(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        // Mock all database queries
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockStatement->method('fetchAll')->willReturn([]);
        $this->mockPdo->method('exec')->willReturn(1);
        $this->mockPdo->method('query')->willReturn($this->mockStatement);
        
        $result = $generator->applyConstraints(true);
        
        $this->assertTrue($result);
    }

    // ============================================
    // Unique Constraint Tests
    // ============================================

    public function testApplyUniqueConstraintWithSingleColumn(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'unique', 'columns' => 'email'];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0); // Doesn't exist
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('ADD CONSTRAINT'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyUniqueConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true); // No exception thrown
    }

    public function testApplyUniqueConstraintWithMultipleColumns(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'unique', 'columns' => ['email', 'username']];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('`email`, `username`'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyUniqueConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyUniqueConstraintWithCustomName(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'unique', 'columns' => 'email', 'name' => 'custom_unique_name'];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('custom_unique_name'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyUniqueConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyUniqueConstraintSkipsIfExists(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'unique', 'columns' => 'email'];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1); // Exists
        $this->mockPdo->expects($this->never())->method('exec');
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyUniqueConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    // ============================================
    // Index Constraint Tests
    // ============================================

    public function testApplyIndexConstraintWithSingleColumn(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'index', 'columns' => 'name'];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('CREATE INDEX'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyIndexConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyIndexConstraintWithUniqueFlag(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'index', 'columns' => 'email', 'unique' => true];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('CREATE UNIQUE INDEX'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyIndexConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyIndexConstraintSkipsIfExists(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'index', 'columns' => 'name'];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1); // Exists
        $this->mockPdo->expects($this->never())->method('exec');
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyIndexConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    // ============================================
    // Foreign Key Constraint Tests
    // ============================================

    public function testApplyForeignKeyConstraint(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'foreign_key',
            'column' => 'user_id',
            'references' => 'users.id'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('FOREIGN KEY'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyForeignKeyConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyForeignKeyConstraintWithOnDeleteAndOnUpdate(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'foreign_key',
            'column' => 'user_id',
            'references' => 'users.id',
            'on_delete' => 'CASCADE',
            'on_update' => 'SET NULL'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->logicalAnd(
                $this->stringContains('ON DELETE CASCADE'),
                $this->stringContains('ON UPDATE SET NULL')
            ))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyForeignKeyConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyForeignKeyConstraintWithInvalidData(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'foreign_key']; // Missing column and references
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyForeignKeyConstraint');
        
        $method->invoke($generator, $constraint);
        
        // Should return early without executing SQL
        $this->mockPdo->expects($this->never())->method('exec');
        $this->assertTrue(true);
    }

    public function testApplyForeignKeyConstraintSkipsIfExists(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'foreign_key',
            'column' => 'user_id',
            'references' => 'users.id'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1); // Exists
        $this->mockPdo->expects($this->never())->method('exec');
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyForeignKeyConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    // ============================================
    // Primary Key Constraint Tests
    // ============================================

    public function testApplyPrimaryKeyConstraint(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'primary', 'columns' => 'id'];
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyPrimaryKeyConstraint');
        
        // Primary key is typically handled during table creation, so this should do nothing
        $method->invoke($generator, $constraint);
        
        $this->mockPdo->expects($this->never())->method('exec');
        $this->assertTrue(true);
    }

    // ============================================
    // Check Constraint Tests
    // ============================================

    public function testApplyCheckConstraint(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'check',
            'expression' => 'age >= 18'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('CHECK'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyCheckConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyCheckConstraintWithCustomName(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'check',
            'expression' => 'age >= 18',
            'name' => 'check_age'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('check_age'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyCheckConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyCheckConstraintWithInvalidData(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'check']; // Missing expression
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyCheckConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->mockPdo->expects($this->never())->method('exec');
        $this->assertTrue(true);
    }

    public function testApplyCheckConstraintSkipsIfExists(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'check',
            'expression' => 'age >= 18'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1); // Exists
        $this->mockPdo->expects($this->never())->method('exec');
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyCheckConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    // ============================================
    // Fulltext Constraint Tests
    // ============================================

    public function testApplyFulltextConstraint(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'fulltext',
            'columns' => ['title', 'content']
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('FULLTEXT INDEX'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyFulltextConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyFulltextConstraintWithSingleColumn(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'fulltext',
            'columns' => 'content'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('FULLTEXT INDEX'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyFulltextConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    public function testApplyFulltextConstraintWithInvalidData(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = ['type' => 'fulltext']; // Missing columns
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyFulltextConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->mockPdo->expects($this->never())->method('exec');
        $this->assertTrue(true);
    }

    public function testApplyFulltextConstraintSkipsIfExists(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = [
            'type' => 'fulltext',
            'columns' => ['title', 'content']
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1); // Exists
        $this->mockPdo->expects($this->never())->method('exec');
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('applyFulltextConstraint');
        
        $method->invoke($generator, $constraint);
        
        $this->assertTrue(true);
    }

    // ============================================
    // Constraint/Index Existence Tests
    // ============================================

    public function testConstraintExistsReturnsTrue(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('constraintExists');
        
        $result = $method->invoke($generator, 'test_constraint');
        
        $this->assertTrue($result);
    }

    public function testConstraintExistsReturnsFalse(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('constraintExists');
        
        $result = $method->invoke($generator, 'test_constraint');
        
        $this->assertFalse($result);
    }

    public function testIndexExistsReturnsTrue(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('indexExists');
        
        $result = $method->invoke($generator, 'test_index');
        
        $this->assertTrue($result);
    }

    public function testIndexExistsReturnsFalse(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('indexExists');
        
        $result = $method->invoke($generator, 'test_index');
        
        $this->assertFalse($result);
    }

    // ============================================
    // Execute Constraints Tests
    // ============================================

    public function testExecuteConstraintsHandlesUnknownType(): void
    {
        $generator = $this->createGenerator();
        
        $constraints = [['type' => 'unknown_type']];
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('executeConstraints');
        
        $result = $method->invoke($generator, $constraints);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Unknown constraint type', $generator->getError());
    }

    public function testExecuteConstraintsHandlesInvalidConstraint(): void
    {
        $generator = $this->createGenerator();
        
        $constraints = ['not_an_array', 123, null];
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('executeConstraints');
        
        $result = $method->invoke($generator, $constraints);
        
        // Should skip invalid constraints and return true
        $this->assertTrue($result);
    }

    public function testExecuteConstraintsHandlesException(): void
    {
        $generator = $this->createGenerator();
        
        $constraint = Schema::unique('email');
        $constraints = [$constraint->toArray()];
        
        // Mock to throw exception during constraint application
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->method('exec')->willThrowException(new \Exception('SQL error'));
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('executeConstraints');
        
        $result = $method->invoke($generator, $constraints);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Failed to apply', $generator->getError());
    }

    public function testExecuteConstraintsSkipsAutoIncrement(): void
    {
        $generator = $this->createGenerator();
        
        $constraints = [['type' => 'auto_increment', 'column' => 'id']];
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('executeConstraints');
        
        $result = $method->invoke($generator, $constraints);
        
        // Auto increment is handled during table creation, so should skip
        $this->assertTrue($result);
        $this->mockPdo->expects($this->never())->method('exec');
    }

    // ============================================
    // Get Applied Constraints Tests
    // ============================================

    public function testGetAppliedConstraintsReturnsArray(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        $result = $generator->getAppliedConstraints();
        
        $this->assertIsArray($result);
    }

    public function testGetAppliedConstraintsReturnsConstraintInfo(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        $result = $generator->getAppliedConstraints();
        
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('type', $result[0]);
        $this->assertArrayHasKey('applied', $result[0]);
        $this->assertArrayHasKey('constraint', $result[0]);
        $this->assertEquals('unique', $result[0]['type']);
        $this->assertTrue($result[0]['applied']);
    }

    public function testGetAppliedConstraintsHandlesInvalidConstraints(): void
    {
        $generator = $this->createGenerator(['not_an_object']);
        
        $result = $generator->getAppliedConstraints();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // Get Summary Tests
    // ============================================

    public function testGetSummaryReturnsArray(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->getSummary();
        
        $this->assertIsArray($result);
    }

    public function testGetSummaryIncludesTableName(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->getSummary();
        
        $this->assertArrayHasKey('table_name', $result);
        $this->assertEquals($this->tableName, $result['table_name']);
    }

    public function testGetSummaryIncludesConstraintCount(): void
    {
        $constraint1 = Schema::unique('email');
        $constraint2 = Schema::index('name');
        $generator = $this->createGenerator([$constraint1, $constraint2]);
        
        $result = $generator->getSummary();
        
        $this->assertArrayHasKey('total_constraints', $result);
        $this->assertEquals(2, $result['total_constraints']);
    }

    public function testGetSummaryIncludesConstraintTypes(): void
    {
        $constraint1 = Schema::unique('email');
        $constraint2 = Schema::index('name');
        $generator = $this->createGenerator([$constraint1, $constraint2]);
        
        $result = $generator->getSummary();
        
        $this->assertArrayHasKey('constraint_types', $result);
        $this->assertIsArray($result['constraint_types']);
        $this->assertEquals(1, $result['constraint_types']['unique']);
        $this->assertEquals(1, $result['constraint_types']['index']);
    }

    public function testGetSummaryIncludesErrorState(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->getSummary();
        
        $this->assertArrayHasKey('has_errors', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertFalse($result['has_errors']);
        $this->assertEquals('', $result['error']);
    }

    public function testGetSummaryReflectsErrorState(): void
    {
        $generator = $this->createGenerator();
        
        // Create a constraint object with toArray() that returns an unknown type
        $invalidConstraint = new class {
            public function toArray(): array {
                return ['type' => 'unknown_type'];
            }
        };
        
        $reflection = new ReflectionClass($generator);
        $schemaProperty = $reflection->getProperty('schema');
        $schemaProperty->setValue($generator, [$invalidConstraint]);
        
        $generator->applyConstraints();
        
        $result = $generator->getSummary();
        
        $this->assertTrue($result['has_errors']);
        $this->assertNotEmpty($result['error']);
    }

    // ============================================
    // Remove Obsolete Constraints Tests
    // ============================================

    public function testRemoveObsoleteConstraintsCallsGetExistingConstraints(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchAll')->willReturn([]);
        $this->mockPdo->method('query')->willReturn($this->mockStatement);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('removeObsoleteConstraints');
        
        $method->invoke($generator, []);
        
        $this->assertTrue(true); // No exception thrown
    }

    public function testRemoveObsoleteConstraintsDropsObsoleteUniqueConstraint(): void
    {
        $generator = $this->createGenerator();
        
        // Mock existing constraint in database
        $existingConstraint = [
            'CONSTRAINT_NAME' => 'old_unique',
            'CONSTRAINT_TYPE' => 'UNIQUE'
        ];
        
        // Create separate statement mocks for different queries
        $constraintsStmt = $this->createMock(PDOStatement::class);
        $columnsStmt = $this->createMock(PDOStatement::class);
        $indexesStmt = $this->createMock(PDOStatement::class);
        
        // Setup prepare() to return different statements based on SQL
        $this->mockPdo->method('prepare')
            ->willReturnCallback(function ($sql) use ($constraintsStmt, $columnsStmt) {
                if (strpos($sql, 'TABLE_CONSTRAINTS') !== false) {
                    return $constraintsStmt;
                }
                if (strpos($sql, 'KEY_COLUMN_USAGE') !== false) {
                    return $columnsStmt;
                }
                if (strpos($sql, 'STATISTICS') !== false) {
                    return $this->mockStatement;
                }
                return $this->mockStatement;
            });
        
        $constraintsStmt->method('execute')->willReturn(true);
        $constraintsStmt->method('fetchAll')->willReturn([$existingConstraint]);
        
        $columnsStmt->method('execute')->willReturn(true);
        $columnsStmt->method('fetchAll')
            ->with(\PDO::FETCH_COLUMN)
            ->willReturn(['email']);
        
        $this->mockPdo->method('query')->willReturn($indexesStmt);
        $indexesStmt->method('fetchAll')->willReturn([]);
        
        $this->mockPdo->expects($this->atLeastOnce())
            ->method('exec')
            ->with($this->stringContains('DROP CONSTRAINT'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('removeObsoleteConstraints');
        
        // Current schema is empty, so old_unique should be removed
        $method->invoke($generator, []);
    }

    public function testRemoveObsoleteConstraintsKeepsMatchingConstraints(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        // Mock existing constraint that matches current schema
        $existingConstraint = [
            'CONSTRAINT_NAME' => 'unique_email',
            'CONSTRAINT_TYPE' => 'UNIQUE'
        ];
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [$existingConstraint],
                ['email'], // getConstraintColumns returns email
                []
            );
        $this->mockPdo->method('query')->willReturn($this->mockStatement);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('removeObsoleteConstraints');
        
        $processedConstraints = $constraint->toArray();
        $method->invoke($generator, [$processedConstraints]);
        
        // Should not drop matching constraint
        $this->mockPdo->expects($this->never())
            ->method('exec')
            ->with($this->stringContains('DROP CONSTRAINT'));
    }

    // ============================================
    // Helper Method Tests
    // ============================================

    public function testGetExistingConstraintsReturnsArray(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchAll')->willReturn([]);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('getExistingConstraints');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result);
    }

    public function testGetExistingIndexesReturnsArray(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('query')->willReturn($this->mockStatement);
        $this->mockStatement->method('fetchAll')->willReturn([]);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('getExistingIndexes');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result);
    }

    public function testGetExistingIndexesHandlesQueryFailure(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('query')->willReturn(false);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('getExistingIndexes');
        
        $result = $method->invoke($generator);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetConstraintColumnsReturnsArray(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchAll')
            ->with(\PDO::FETCH_COLUMN)
            ->willReturn(['email', 'username']);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('getConstraintColumns');
        
        $result = $method->invoke($generator, 'test_constraint');
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testIsUniqueConstraintIndexReturnsTrue(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('isUniqueConstraintIndex');
        
        $result = $method->invoke($generator, 'unique_index');
        
        $this->assertTrue($result);
    }

    public function testIsUniqueConstraintIndexReturnsFalse(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('isUniqueConstraintIndex');
        
        $result = $method->invoke($generator, 'regular_index');
        
        $this->assertFalse($result);
    }

    public function testDropConstraintExecutesSQL(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('DROP CONSTRAINT'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('dropConstraint');
        
        $method->invoke($generator, 'test_constraint');
        
        $this->assertTrue(true);
    }

    public function testDropIndexExecutesSQL(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('DROP INDEX'))
            ->willReturn(1);
        
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('dropIndex');
        
        $method->invoke($generator, 'test_index');
        
        $this->assertTrue(true);
    }

    // ============================================
    // Edge Cases and Error Scenarios
    // ============================================

    public function testApplyConstraintsWithMixedConstraintTypes(): void
    {
        $constraints = [
            Schema::unique('email'),
            Schema::index('name'),
            Schema::foreignKey('user_id', 'users.id')
        ];
        
        $generator = $this->createGenerator($constraints);
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->method('exec')->willReturn(1);
        
        $result = $generator->applyConstraints();
        
        $this->assertTrue($result);
    }

    public function testApplyConstraintsHandlesConstraintApplicationException(): void
    {
        $constraint = Schema::unique('email');
        $generator = $this->createGenerator([$constraint]);
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStatement);
        $this->mockStatement->method('execute')->willReturn(true);
        $this->mockStatement->method('fetchColumn')->willReturn(0);
        $this->mockPdo->method('exec')->willThrowException(new \Exception('SQL error'));
        
        $result = $generator->applyConstraints();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Failed to apply unique constraint', $generator->getError());
    }
}


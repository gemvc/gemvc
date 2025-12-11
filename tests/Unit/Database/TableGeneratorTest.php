<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\TableGenerator;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Test object class for table generation
 */
class TestTableGeneratorObject
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $description;
    public bool $isActive;
    public float $price;
    public array $tags;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'string',
        'isActive' => 'bool',
        'price' => 'float',
        'tags' => 'array',
    ];
    
    public function getTable(): string
    {
        return 'test_table';
    }
}

/**
 * Test object without getTable method
 */
class TestTableGeneratorObjectWithoutGetTable
{
    public int $id;
    public string $name;
}

/**
 * Test object with underscore prefix property (should be skipped)
 */
class TestTableGeneratorObjectWithUnderscore
{
    public int $id;
    public string $name;
    public ?string $_internal; // Should be skipped
    
    public function getTable(): string
    {
        return 'test_table';
    }
}

/**
 * @outputBuffering enabled
 */
class TableGeneratorTest extends TestCase
{
    /** @var MockObject&PDO|null */
    private $mockPdo = null;
    /** @var MockObject&PDOStatement|null */
    private $mockStatement = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStatement = $this->createMock(PDOStatement::class);
    }

    /**
     * Create a TableGenerator instance with mocked PDO
     */
    private function createGenerator(): TableGenerator
    {
        /** @var PDO $pdo */
        $pdo = $this->mockPdo;
        return new TableGenerator($pdo);
    }

    // ============================================
    // Constructor Tests
    // ============================================

    public function testConstructor(): void
    {
        $generator = $this->createGenerator();
        $this->assertInstanceOf(TableGenerator::class, $generator);
        $this->assertEquals('', $generator->getError());
    }

    // ============================================
    // getError Tests
    // ============================================

    public function testGetErrorReturnsEmptyStringInitially(): void
    {
        $generator = $this->createGenerator();
        $this->assertEquals('', $generator->getError());
    }

    // ============================================
    // createTableFromObject Tests
    // ============================================

    public function testCreateTableFromObjectWithValidObject(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('CREATE TABLE IF NOT EXISTS'))
            ->willReturn(1);
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertTrue($result);
        $this->assertEquals('', $generator->getError());
    }

    public function testCreateTableFromObjectWithTableNameParameter(): void
    {
        $object = new TestTableGeneratorObjectWithoutGetTable();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('CREATE TABLE IF NOT EXISTS `custom_table`'))
            ->willReturn(1);
        
        $result = $generator->createTableFromObject($object, 'custom_table');
        
        $this->assertTrue($result);
    }

    public function testCreateTableFromObjectWithoutGetTableMethod(): void
    {
        $object = new TestTableGeneratorObjectWithoutGetTable();
        $generator = $this->createGenerator();
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertFalse($result);
        $this->assertEquals('public function getTable() not found in object', $generator->getError());
    }

    public function testCreateTableFromObjectWithNullGetTableReturn(): void
    {
        $object = new class {
            public int $id;
            public function getTable(): ?string {
                return null;
            }
        };
        
        $generator = $this->createGenerator();
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertFalse($result);
        $this->assertEquals('function getTable() returned null string. Please define it and give table a name', $generator->getError());
    }

    public function testCreateTableFromObjectSkipsUnderscoreProperties(): void
    {
        $object = new TestTableGeneratorObjectWithUnderscore();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->callback(function ($query) {
                // Should not contain _internal column
                return strpos($query, '_internal') === false && 
                       strpos($query, 'id') !== false && 
                       strpos($query, 'name') !== false;
            }))
            ->willReturn(1);
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertTrue($result);
    }

    public function testCreateTableFromObjectWithNoValidProperties(): void
    {
        $object = new class {
            public function getTable(): string {
                return 'empty_table';
            }
        };
        
        $generator = $this->createGenerator();
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertFalse($result);
        $this->assertEquals('No valid properties found in object to create table columns', $generator->getError());
    }

    public function testCreateTableFromObjectWithPdoException(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->willThrowException(new PDOException('Table already exists'));
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertFalse($result);
        $this->assertEquals('Table already exists', $generator->getError());
    }

    public function testCreateTableFromObjectHandlesNullableProperties(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->callback(function ($query) {
                // description should be NULL, name should be NOT NULL
                return strpos($query, '`description`') !== false && 
                       strpos($query, 'NOT NULL') !== false;
            }))
            ->willReturn(1);
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertTrue($result);
    }

    public function testCreateTableFromObjectWithIdColumnAutoIncrement(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('AUTO_INCREMENT PRIMARY KEY'))
            ->willReturn(1);
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertTrue($result);
    }

    // ============================================
    // setColumnProperties Tests
    // ============================================

    public function testSetColumnProperties(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->setColumnProperties('name', 'DEFAULT \'test\'');
        
        $this->assertSame($generator, $result);
    }

    public function testSetColumnPropertiesChaining(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator
            ->setColumnProperties('name', 'DEFAULT \'test\'')
            ->setColumnProperties('email', 'UNIQUE');
        
        $this->assertSame($generator, $result);
    }

    // ============================================
    // setNotNull Tests
    // ============================================

    public function testSetNotNull(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->setNotNull('description');
        
        $this->assertSame($generator, $result);
    }

    public function testSetNotNullDoesNotDuplicate(): void
    {
        $generator = $this->createGenerator();
        $generator->setColumnProperties('name', 'NOT NULL');
        
        $result = $generator->setNotNull('name');
        
        $this->assertSame($generator, $result);
    }

    // ============================================
    // setDefault Tests
    // ============================================

    public function testSetDefaultWithString(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->setDefault('name', 'John');
        
        $this->assertSame($generator, $result);
    }

    public function testSetDefaultWithBoolean(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->setDefault('isActive', true);
        
        $this->assertSame($generator, $result);
    }

    public function testSetDefaultWithNull(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->setDefault('description', null);
        
        $this->assertSame($generator, $result);
    }

    public function testSetDefaultReplacesExisting(): void
    {
        $generator = $this->createGenerator();
        $generator->setDefault('name', 'Old');
        
        $result = $generator->setDefault('name', 'New');
        
        $this->assertSame($generator, $result);
    }

    // ============================================
    // addCheck Tests
    // ============================================

    public function testAddCheck(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->addCheck('price', 'price > 0');
        
        $this->assertSame($generator, $result);
    }

    // ============================================
    // addIndex Tests
    // ============================================

    public function testAddIndex(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->addIndex('email');
        
        $this->assertSame($generator, $result);
    }

    public function testAddIndexUnique(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->addIndex('email', true);
        
        $this->assertSame($generator, $result);
    }

    // ============================================
    // removeIndex Tests
    // ============================================

    public function testRemoveIndex(): void
    {
        $generator = $this->createGenerator();
        $generator->addIndex('email');
        
        $result = $generator->removeIndex('email');
        
        $this->assertSame($generator, $result);
    }

    public function testRemoveIndexFromUnique(): void
    {
        $generator = $this->createGenerator();
        $generator->addIndex('email', true);
        
        $result = $generator->removeIndex('email');
        
        $this->assertSame($generator, $result);
    }

    // ============================================
    // makeColumnUnique Tests
    // ============================================

    public function testMakeColumnUniqueSuccess(): void
    {
        $generator = $this->createGenerator();
        
        // Mock SHOW COLUMNS query
        $columnsResult = $this->createMock(PDOStatement::class);
        $columnsResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['Field' => 'email', 'Type' => 'varchar(255)']]);
        
        // Mock SHOW INDEXES query
        $indexesResult = $this->createMock(PDOStatement::class);
        $indexesResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);
        
        // exec() is called: SHOW COLUMNS, SHOW INDEXES, CREATE UNIQUE INDEX
        $this->mockPdo->expects($this->exactly(3))
            ->method('exec')
            ->willReturn(1);
        
        // query() is called: SHOW COLUMNS, SHOW INDEXES
        $this->mockPdo->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function ($query) use ($columnsResult, $indexesResult) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return $columnsResult;
                }
                if (strpos($query, 'SHOW INDEXES') !== false) {
                    return $indexesResult;
                }
                return $this->createMock(PDOStatement::class);
            });
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $generator->makeColumnUnique('test_table', 'email');
        
        $this->assertTrue($result);
        $this->assertEquals('', $generator->getError());
    }

    public function testMakeColumnUniqueWithInvalidTableName(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->makeColumnUnique('123invalid', 'email');
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Invalid table name format', $generator->getError());
    }

    public function testMakeColumnUniqueWithNonExistentColumn(): void
    {
        $generator = $this->createGenerator();
        
        $columnsResult = $this->createMock(PDOStatement::class);
        $columnsResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);
        
        $this->mockPdo->expects($this->once())
            ->method('query')
            ->willReturn($columnsResult);
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('rollBack');
        
        $result = $generator->makeColumnUnique('test_table', 'nonexistent');
        
        $this->assertFalse($result);
        $this->assertStringContainsString('does not exist', $generator->getError());
    }

    public function testMakeColumnUniqueWithPdoException(): void
    {
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('query')
            ->willThrowException(new PDOException('Connection failed'));
        
        $this->mockPdo->expects($this->once())
            ->method('rollBack');
        
        $result = $generator->makeColumnUnique('test_table', 'email');
        
        $this->assertFalse($result);
        $this->assertEquals('Connection failed', $generator->getError());
    }

    // ============================================
    // removeColumn Tests
    // ============================================

    public function testRemoveColumnSuccess(): void
    {
        $generator = $this->createGenerator();
        
        // Mock SHOW COLUMNS query
        $columnsResult = $this->createMock(PDOStatement::class);
        $columnsResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['Field' => 'description', 'Type' => 'text']]);
        
        // Mock SHOW INDEXES query
        $indexesResult = $this->createMock(PDOStatement::class);
        $indexesResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);
        
        $this->mockPdo->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function ($query) use ($columnsResult, $indexesResult) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return $columnsResult;
                }
                return $indexesResult;
            });
        
        // exec() is called: SHOW COLUMNS, SHOW INDEXES, DROP COLUMN
        $this->mockPdo->expects($this->exactly(3))
            ->method('exec')
            ->willReturn(1);
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $generator->removeColumn('test_table', 'description');
        
        $this->assertTrue($result);
        $this->assertEquals('', $generator->getError());
    }

    public function testRemoveColumnWithInvalidTableName(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->removeColumn('123invalid', 'email');
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Invalid table name format', $generator->getError());
    }

    public function testRemoveColumnWithNonExistentColumn(): void
    {
        $generator = $this->createGenerator();
        
        $columnsResult = $this->createMock(PDOStatement::class);
        $columnsResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);
        
        $this->mockPdo->expects($this->once())
            ->method('query')
            ->willReturn($columnsResult);
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('rollBack');
        
        $result = $generator->removeColumn('test_table', 'nonexistent');
        
        $this->assertFalse($result);
        $this->assertStringContainsString('does not exist', $generator->getError());
    }

    public function testRemoveColumnDropsIndexesFirst(): void
    {
        $generator = $this->createGenerator();
        
        $columnsResult = $this->createMock(PDOStatement::class);
        $columnsResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['Field' => 'email', 'Type' => 'varchar(255)']]);
        
        $indexesResult = $this->createMock(PDOStatement::class);
        $indexesResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['Key_name' => 'idx_email', 'Column_name' => 'email']
            ]);
        
        // exec() is called: SHOW COLUMNS, SHOW INDEXES, DROP INDEX, DROP COLUMN
        $this->mockPdo->expects($this->exactly(4))
            ->method('exec')
            ->willReturn(1);
        
        // query() is called: SHOW COLUMNS, SHOW INDEXES
        $this->mockPdo->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function ($query) use ($columnsResult, $indexesResult) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return $columnsResult;
                }
                if (strpos($query, 'SHOW INDEXES') !== false) {
                    return $indexesResult;
                }
                return $this->createMock(PDOStatement::class);
            });
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $generator->removeColumn('test_table', 'email');
        
        $this->assertTrue($result);
    }

    // ============================================
    // updateTable Tests
    // ============================================

    public function testUpdateTableWithNoChanges(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $describeResult = $this->createMock(PDOStatement::class);
        $describeResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['Field' => 'id', 'Type' => 'int(11)', 'Null' => 'NO'],
                ['Field' => 'name', 'Type' => 'varchar(255)', 'Null' => 'NO'],
                ['Field' => 'email', 'Type' => 'varchar(255)', 'Null' => 'NO'],
            ]);
        
        // query() is called: SELECT 1 (connection test), DESCRIBE
        $this->mockPdo->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function ($query) use ($describeResult) {
                if (strpos($query, 'SELECT 1') !== false) {
                    return $this->createMock(PDOStatement::class);
                }
                if (strpos($query, 'DESCRIBE') !== false) {
                    return $describeResult;
                }
                return $this->createMock(PDOStatement::class);
            });
        
        $this->mockPdo->expects($this->once())
            ->method('inTransaction')
            ->willReturn(false);
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $generator->updateTable($object);
        
        $this->assertTrue($result);
    }

    public function testUpdateTableAddsNewColumn(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $describeResult = $this->createMock(PDOStatement::class);
        $describeResult->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['Field' => 'id', 'Type' => 'int(11)', 'Null' => 'NO'],
                ['Field' => 'name', 'Type' => 'varchar(255)', 'Null' => 'NO'],
            ]);
        
        // query() is called: SELECT 1 (connection test), DESCRIBE
        $this->mockPdo->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function ($query) use ($describeResult) {
                if (strpos($query, 'SELECT 1') !== false) {
                    return $this->createMock(PDOStatement::class);
                }
                if (strpos($query, 'DESCRIBE') !== false) {
                    return $describeResult;
                }
                return $this->createMock(PDOStatement::class);
            });
        
        $this->mockPdo->expects($this->once())
            ->method('inTransaction')
            ->willReturn(false);
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        // exec() is called for each column to add (email, description, isActive, price, tags)
        $this->mockPdo->expects($this->atLeastOnce())
            ->method('exec')
            ->with($this->stringContains('ADD COLUMN'))
            ->willReturn(1);
        
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $generator->updateTable($object);
        
        $this->assertTrue($result);
    }

    public function testUpdateTableWithPdoException(): void
    {
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        $this->mockPdo->expects($this->atLeastOnce())
            ->method('inTransaction')
            ->willReturn(false);
        
        $this->mockPdo->expects($this->once())
            ->method('query')
            ->willThrowException(new PDOException('Connection failed'));
        
        $result = $generator->updateTable($object);
        
        $this->assertFalse($result);
        $this->assertEquals('Connection failed', $generator->getError());
    }

    // ============================================
    // makeColumnsUniqueTogether Tests
    // ============================================

    public function testMakeColumnsUniqueTogetherSuccess(): void
    {
        $generator = $this->createGenerator();
        
        $columnsResult = $this->createMock(PDOStatement::class);
        $columnsResult->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturn([['Field' => 'email', 'Type' => 'varchar(255)']]);
        
        $indexesResult = $this->createMock(PDOStatement::class);
        $indexesResult->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturn([]);
        
        // exec() is called: SHOW COLUMNS (2x), SHOW INDEXES (2x), CREATE UNIQUE INDEX
        $this->mockPdo->expects($this->exactly(5))
            ->method('exec')
            ->willReturn(1);
        
        // query() is called: SHOW COLUMNS (2x), SHOW INDEXES (2x)
        $this->mockPdo->expects($this->exactly(4))
            ->method('query')
            ->willReturnCallback(function ($query) use ($columnsResult, $indexesResult) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return $columnsResult;
                }
                if (strpos($query, 'SHOW INDEXES') !== false) {
                    return $indexesResult;
                }
                return $this->createMock(PDOStatement::class);
            });
        
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction');
        
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);
        
        $result = $generator->makeColumnsUniqueTogether('test_table', ['email', 'name']);
        
        $this->assertTrue($result);
    }

    public function testMakeColumnsUniqueTogetherWithEmptyColumns(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->makeColumnsUniqueTogether('test_table', []);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No column names provided', $generator->getError());
    }

    public function testMakeColumnsUniqueTogetherWithInvalidTableName(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->makeColumnsUniqueTogether('123invalid', ['email']);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('Invalid table name format', $generator->getError());
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testGetErrorAfterFailure(): void
    {
        $generator = $this->createGenerator();
        
        $result = $generator->createTableFromObject(new TestTableGeneratorObjectWithoutGetTable());
        
        $this->assertFalse($result);
        $this->assertNotEmpty($generator->getError());
    }

    public function testCreateTableFromObjectWithNullPdo(): void
    {
        // This test would require reflection to set PDO to null
        // Since PDO is required in constructor, we'll test error handling differently
        $object = new TestTableGeneratorObject();
        $generator = $this->createGenerator();
        
        // Simulate PDO being null by making exec throw an exception
        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->willThrowException(new PDOException('PDO connection is not available'));
        
        $result = $generator->createTableFromObject($object);
        
        $this->assertFalse($result);
        $this->assertStringContainsString('PDO connection is not available', $generator->getError());
    }
}


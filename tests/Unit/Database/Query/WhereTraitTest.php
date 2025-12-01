<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Query\WhereTrait;

/**
 * Test class using WhereTrait for testing
 */
class TestClassWithWhereTrait
{
    use WhereTrait;
    
    public array $whereConditions = [];
    public array $arrayBindValues = [];
}

class WhereTraitTest extends TestCase
{
    private TestClassWithWhereTrait $testObject;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testObject = new TestClassWithWhereTrait();
    }
    
    // ==========================================
    // whereEqual() Method Tests
    // ==========================================
    
    public function testWhereEqualWithString(): void
    {
        $result = $this->testObject->whereEqual('name', 'John');
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertSame($this->testObject, $result); // Fluent interface
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertEquals('name = :name', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':name', $this->testObject->arrayBindValues);
        $this->assertEquals('John', $this->testObject->arrayBindValues[':name']);
    }
    
    public function testWhereEqualWithInteger(): void
    {
        $result = $this->testObject->whereEqual('id', 42);
        
        $this->assertEquals('id = :id', $this->testObject->whereConditions[0]);
        $this->assertEquals(42, $this->testObject->arrayBindValues[':id']);
    }
    
    public function testWhereEqualWithFloat(): void
    {
        $result = $this->testObject->whereEqual('price', 99.99);
        
        $this->assertEquals('price = :price', $this->testObject->whereConditions[0]);
        $this->assertEquals(99.99, $this->testObject->arrayBindValues[':price']);
    }
    
    public function testWhereEqualWithTableDotColumn(): void
    {
        $result = $this->testObject->whereEqual('users.id', 1);
        
        // Should replace . with _ in parameter name
        $this->assertEquals('users.id = :users_id', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':users_id', $this->testObject->arrayBindValues);
    }
    
    public function testWhereEqualWithEmptyColumn(): void
    {
        $result = $this->testObject->whereEqual('', 'value');
        
        // Should skip empty column names silently
        $this->assertCount(0, $this->testObject->whereConditions);
        $this->assertEmpty($this->testObject->arrayBindValues);
    }
    
    public function testWhereEqualWithWhitespaceColumn(): void
    {
        $result = $this->testObject->whereEqual('   ', 'value');
        
        // Should skip whitespace-only column names
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereNull() Method Tests
    // ==========================================
    
    public function testWhereNullWithValidColumn(): void
    {
        $result = $this->testObject->whereNull('deleted_at');
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertEquals('deleted_at IS NULL ', $this->testObject->whereConditions[0]);
        $this->assertEmpty($this->testObject->arrayBindValues); // No bindings for NULL
    }
    
    public function testWhereNullWithEmptyColumn(): void
    {
        $result = $this->testObject->whereNull('');
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    public function testWhereNullWithWhitespaceColumn(): void
    {
        $result = $this->testObject->whereNull('   ');
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereNotNull() Method Tests
    // ==========================================
    
    public function testWhereNotNullWithValidColumn(): void
    {
        $result = $this->testObject->whereNotNull('email');
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertEquals('email IS NOT NULL ', $this->testObject->whereConditions[0]);
        $this->assertEmpty($this->testObject->arrayBindValues);
    }
    
    public function testWhereNotNullWithEmptyColumn(): void
    {
        $result = $this->testObject->whereNotNull('');
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereLike() Method Tests
    // ==========================================
    
    public function testWhereLikeWithValidValue(): void
    {
        $result = $this->testObject->whereLike('name', 'John');
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertEquals('name LIKE :name', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':name', $this->testObject->arrayBindValues);
        $this->assertEquals('%John%', $this->testObject->arrayBindValues[':name']); // Wrapped with %
    }
    
    public function testWhereLikeWithTableDotColumn(): void
    {
        $result = $this->testObject->whereLike('users.name', 'test');
        
        $this->assertEquals('users.name LIKE :users_name', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':users_name', $this->testObject->arrayBindValues);
        $this->assertEquals('%test%', $this->testObject->arrayBindValues[':users_name']);
    }
    
    public function testWhereLikeWithEmptyColumn(): void
    {
        $result = $this->testObject->whereLike('', 'value');
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    public function testWhereLikeWithSpecialCharacters(): void
    {
        $result = $this->testObject->whereLike('name', 'O\'Brien');
        
        // Should preserve special characters in value
        $this->assertEquals('%O\'Brien%', $this->testObject->arrayBindValues[':name']);
    }
    
    // ==========================================
    // whereLess() Method Tests
    // ==========================================
    
    public function testWhereLessWithInteger(): void
    {
        $result = $this->testObject->whereLess('age', 18);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertEquals('age < :age', $this->testObject->whereConditions[0]);
        $this->assertEquals(18, $this->testObject->arrayBindValues[':age']);
    }
    
    public function testWhereLessWithFloat(): void
    {
        $result = $this->testObject->whereLess('price', 99.99);
        
        $this->assertEquals('price < :price', $this->testObject->whereConditions[0]);
        $this->assertEquals(99.99, $this->testObject->arrayBindValues[':price']);
    }
    
    public function testWhereLessWithString(): void
    {
        $result = $this->testObject->whereLess('date', '2024-01-01');
        
        $this->assertEquals('date < :date', $this->testObject->whereConditions[0]);
        $this->assertEquals('2024-01-01', $this->testObject->arrayBindValues[':date']);
    }
    
    public function testWhereLessWithTableDotColumn(): void
    {
        $result = $this->testObject->whereLess('products.price', 100);
        
        $this->assertEquals('products.price < :products_price', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':products_price', $this->testObject->arrayBindValues);
    }
    
    public function testWhereLessWithEmptyColumn(): void
    {
        $result = $this->testObject->whereLess('', 100);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereLessEqual() Method Tests
    // ==========================================
    
    public function testWhereLessEqualWithInteger(): void
    {
        $result = $this->testObject->whereLessEqual('age', 21);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertEquals('age <= :age', $this->testObject->whereConditions[0]);
        $this->assertEquals(21, $this->testObject->arrayBindValues[':age']);
    }
    
    public function testWhereLessEqualWithFloat(): void
    {
        $result = $this->testObject->whereLessEqual('price', 49.99);
        
        $this->assertEquals('price <= :price', $this->testObject->whereConditions[0]);
        $this->assertEquals(49.99, $this->testObject->arrayBindValues[':price']);
    }
    
    public function testWhereLessEqualWithEmptyColumn(): void
    {
        $result = $this->testObject->whereLessEqual('', 100);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereBigger() Method Tests
    // ==========================================
    
    public function testWhereBiggerWithInteger(): void
    {
        $result = $this->testObject->whereBigger('age', 65);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertEquals('age > :age', $this->testObject->whereConditions[0]);
        $this->assertEquals(65, $this->testObject->arrayBindValues[':age']);
    }
    
    public function testWhereBiggerWithFloat(): void
    {
        $result = $this->testObject->whereBigger('salary', 50000.00);
        
        $this->assertEquals('salary > :salary', $this->testObject->whereConditions[0]);
        $this->assertEquals(50000.00, $this->testObject->arrayBindValues[':salary']);
    }
    
    public function testWhereBiggerWithNegativeValue(): void
    {
        $result = $this->testObject->whereBigger('balance', -100);
        
        $this->assertEquals('balance > :balance', $this->testObject->whereConditions[0]);
        $this->assertEquals(-100, $this->testObject->arrayBindValues[':balance']);
    }
    
    public function testWhereBiggerWithEmptyColumn(): void
    {
        $result = $this->testObject->whereBigger('', 100);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereBiggerEqual() Method Tests
    // ==========================================
    
    public function testWhereBiggerEqualWithInteger(): void
    {
        $result = $this->testObject->whereBiggerEqual('votes', 100);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertEquals('votes >= :votes', $this->testObject->whereConditions[0]);
        $this->assertEquals(100, $this->testObject->arrayBindValues[':votes']);
    }
    
    public function testWhereBiggerEqualWithFloat(): void
    {
        $result = $this->testObject->whereBiggerEqual('rating', 4.5);
        
        $this->assertEquals('rating >= :rating', $this->testObject->whereConditions[0]);
        $this->assertEquals(4.5, $this->testObject->arrayBindValues[':rating']);
    }
    
    public function testWhereBiggerEqualWithEmptyColumn(): void
    {
        $result = $this->testObject->whereBiggerEqual('', 100);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereBetween() Method Tests
    // ==========================================
    
    public function testWhereBetweenWithIntegers(): void
    {
        $result = $this->testObject->whereBetween('age', 18, 65);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertStringContainsString('age BETWEEN', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':age_lowerBand', $this->testObject->arrayBindValues);
        $this->assertArrayHasKey(':age_higherBand', $this->testObject->arrayBindValues);
        $this->assertEquals(18, $this->testObject->arrayBindValues[':age_lowerBand']);
        $this->assertEquals(65, $this->testObject->arrayBindValues[':age_higherBand']);
    }
    
    public function testWhereBetweenWithFloats(): void
    {
        $result = $this->testObject->whereBetween('price', 10.50, 99.99);
        
        $this->assertEquals(10.50, $this->testObject->arrayBindValues[':price_lowerBand']);
        $this->assertEquals(99.99, $this->testObject->arrayBindValues[':price_higherBand']);
    }
    
    public function testWhereBetweenWithStrings(): void
    {
        $result = $this->testObject->whereBetween('date', '2024-01-01', '2024-12-31');
        
        $this->assertEquals('2024-01-01', $this->testObject->arrayBindValues[':date_lowerBand']);
        $this->assertEquals('2024-12-31', $this->testObject->arrayBindValues[':date_higherBand']);
    }
    
    public function testWhereBetweenWithTableDotColumn(): void
    {
        $result = $this->testObject->whereBetween('products.price', 10, 100);
        
        $this->assertStringContainsString('products.price BETWEEN', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':products_price_lowerBand', $this->testObject->arrayBindValues);
        $this->assertArrayHasKey(':products_price_higherBand', $this->testObject->arrayBindValues);
    }
    
    public function testWhereBetweenWithSameValues(): void
    {
        $result = $this->testObject->whereBetween('score', 50, 50);
        
        $this->assertEquals(50, $this->testObject->arrayBindValues[':score_lowerBand']);
        $this->assertEquals(50, $this->testObject->arrayBindValues[':score_higherBand']);
    }
    
    public function testWhereBetweenWithEmptyColumn(): void
    {
        $result = $this->testObject->whereBetween('', 10, 100);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereIn() Method Tests
    // ==========================================
    
    public function testWhereInWithIntegerArray(): void
    {
        $result = $this->testObject->whereIn('id', [1, 2, 3, 4, 5]);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertStringContainsString('id IN (', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':id_in_0', $this->testObject->arrayBindValues);
        $this->assertArrayHasKey(':id_in_4', $this->testObject->arrayBindValues);
        $this->assertEquals(1, $this->testObject->arrayBindValues[':id_in_0']);
        $this->assertEquals(5, $this->testObject->arrayBindValues[':id_in_4']);
    }
    
    public function testWhereInWithStringArray(): void
    {
        $result = $this->testObject->whereIn('status', ['active', 'pending', 'approved']);
        
        $this->assertStringContainsString('status IN (', $this->testObject->whereConditions[0]);
        $this->assertEquals('active', $this->testObject->arrayBindValues[':status_in_0']);
        $this->assertEquals('pending', $this->testObject->arrayBindValues[':status_in_1']);
        $this->assertEquals('approved', $this->testObject->arrayBindValues[':status_in_2']);
    }
    
    public function testWhereInWithSingleValue(): void
    {
        $result = $this->testObject->whereIn('id', [42]);
        
        $this->assertStringContainsString('id IN (', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':id_in_0', $this->testObject->arrayBindValues);
        $this->assertEquals(42, $this->testObject->arrayBindValues[':id_in_0']);
    }
    
    public function testWhereInWithTableDotColumn(): void
    {
        $result = $this->testObject->whereIn('users.role_id', [1, 2, 3]);
        
        $this->assertStringContainsString('users.role_id IN (', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':users_role_id_in_0', $this->testObject->arrayBindValues);
    }
    
    public function testWhereInWithEmptyArray(): void
    {
        $result = $this->testObject->whereIn('id', []);
        
        // Should skip empty arrays
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    public function testWhereInWithEmptyColumn(): void
    {
        $result = $this->testObject->whereIn('', [1, 2, 3]);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // whereNotIn() Method Tests
    // ==========================================
    
    public function testWhereNotInWithIntegerArray(): void
    {
        $result = $this->testObject->whereNotIn('id', [10, 20, 30]);
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertCount(1, $this->testObject->whereConditions);
        $this->assertStringContainsString('id NOT IN (', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':id_not_in_0', $this->testObject->arrayBindValues);
        $this->assertArrayHasKey(':id_not_in_2', $this->testObject->arrayBindValues);
        $this->assertEquals(10, $this->testObject->arrayBindValues[':id_not_in_0']);
        $this->assertEquals(30, $this->testObject->arrayBindValues[':id_not_in_2']);
    }
    
    public function testWhereNotInWithStringArray(): void
    {
        $result = $this->testObject->whereNotIn('status', ['deleted', 'banned']);
        
        $this->assertStringContainsString('status NOT IN (', $this->testObject->whereConditions[0]);
        $this->assertEquals('deleted', $this->testObject->arrayBindValues[':status_not_in_0']);
        $this->assertEquals('banned', $this->testObject->arrayBindValues[':status_not_in_1']);
    }
    
    public function testWhereNotInWithSingleValue(): void
    {
        $result = $this->testObject->whereNotIn('id', [99]);
        
        $this->assertStringContainsString('id NOT IN (', $this->testObject->whereConditions[0]);
        $this->assertEquals(99, $this->testObject->arrayBindValues[':id_not_in_0']);
    }
    
    public function testWhereNotInWithTableDotColumn(): void
    {
        $result = $this->testObject->whereNotIn('orders.status_id', [5, 6]);
        
        $this->assertStringContainsString('orders.status_id NOT IN (', $this->testObject->whereConditions[0]);
        $this->assertArrayHasKey(':orders_status_id_not_in_0', $this->testObject->arrayBindValues);
    }
    
    public function testWhereNotInWithEmptyArray(): void
    {
        $result = $this->testObject->whereNotIn('id', []);
        
        // Should skip empty arrays
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    public function testWhereNotInWithEmptyColumn(): void
    {
        $result = $this->testObject->whereNotIn('', [1, 2, 3]);
        
        $this->assertCount(0, $this->testObject->whereConditions);
    }
    
    // ==========================================
    // Multiple Conditions Tests
    // ==========================================
    
    public function testMultipleConditions(): void
    {
        $result = $this->testObject
            ->whereEqual('status', 'active')
            ->whereBigger('age', 18)
            ->whereLike('name', 'John');
        
        $this->assertCount(3, $this->testObject->whereConditions);
        $this->assertCount(3, $this->testObject->arrayBindValues);
    }
    
    public function testMixedConditionTypes(): void
    {
        $this->testObject
            ->whereEqual('id', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->whereIn('role_id', [1, 2, 3])
            ->whereBetween('age', 18, 65);
        
        $this->assertCount(5, $this->testObject->whereConditions);
        
        // whereNull and whereNotNull don't add bindings
        $this->assertGreaterThan(0, count($this->testObject->arrayBindValues));
    }
    
    // ==========================================
    // Method Chaining Tests
    // ==========================================
    
    public function testFluentInterfaceReturnsself(): void
    {
        $result = $this->testObject
            ->whereEqual('id', 1)
            ->whereNull('deleted_at')
            ->whereLike('name', 'test');
        
        $this->assertInstanceOf(TestClassWithWhereTrait::class, $result);
        $this->assertSame($this->testObject, $result);
    }
    
    // ==========================================
    // Edge Cases
    // ==========================================
    
    public function testWhereEqualWithZeroValue(): void
    {
        $result = $this->testObject->whereEqual('count', 0);
        
        $this->assertEquals(0, $this->testObject->arrayBindValues[':count']);
    }
    
    public function testWhereEqualWithEmptyString(): void
    {
        $result = $this->testObject->whereEqual('description', '');
        
        $this->assertEquals('', $this->testObject->arrayBindValues[':description']);
    }
    
    public function testWhereLikeWithEmptyString(): void
    {
        $result = $this->testObject->whereLike('name', '');
        
        // Should wrap empty string with %
        $this->assertEquals('%%', $this->testObject->arrayBindValues[':name']);
    }
    
    public function testWhereBetweenWithReversedValues(): void
    {
        $result = $this->testObject->whereBetween('price', 100, 10);
        
        // Should accept reversed values (database will handle)
        $this->assertEquals(100, $this->testObject->arrayBindValues[':price_lowerBand']);
        $this->assertEquals(10, $this->testObject->arrayBindValues[':price_higherBand']);
    }
    
    public function testWhereInWithMixedTypes(): void
    {
        $result = $this->testObject->whereIn('data', [1, 'two', 3.0, true]);
        
        $this->assertEquals(1, $this->testObject->arrayBindValues[':data_in_0']);
        $this->assertEquals('two', $this->testObject->arrayBindValues[':data_in_1']);
        $this->assertEquals(3.0, $this->testObject->arrayBindValues[':data_in_2']);
        $this->assertTrue($this->testObject->arrayBindValues[':data_in_3']);
    }
}


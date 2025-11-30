<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\TypeHelper;

class TypeHelperTest extends TestCase
{
    // ============================================
    // justInt Tests
    // ============================================
    
    public function testJustIntWithInteger(): void
    {
        $result = TypeHelper::justInt(123);
        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }
    
    public function testJustIntWithZero(): void
    {
        $result = TypeHelper::justInt(0);
        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }
    
    public function testJustIntWithNegativeInteger(): void
    {
        $result = TypeHelper::justInt(-456);
        $this->assertIsInt($result);
        $this->assertEquals(-456, $result);
    }
    
    public function testJustIntWithString(): void
    {
        $result = TypeHelper::justInt('123');
        $this->assertNull($result);
    }
    
    public function testJustIntWithFloat(): void
    {
        $result = TypeHelper::justInt(123.45);
        $this->assertNull($result);
    }
    
    public function testJustIntWithNull(): void
    {
        $result = TypeHelper::justInt(null);
        $this->assertNull($result);
    }
    
    public function testJustIntWithBoolean(): void
    {
        $result = TypeHelper::justInt(true);
        $this->assertNull($result);
    }
    
    public function testJustIntWithArray(): void
    {
        $result = TypeHelper::justInt([1, 2, 3]);
        $this->assertNull($result);
    }
    
    // ============================================
    // justIntPositive Tests
    // ============================================
    
    public function testJustIntPositiveWithPositiveInteger(): void
    {
        $result = TypeHelper::justIntPositive(123);
        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }
    
    public function testJustIntPositiveWithOne(): void
    {
        $result = TypeHelper::justIntPositive(1);
        $this->assertIsInt($result);
        $this->assertEquals(1, $result);
    }
    
    public function testJustIntPositiveWithZero(): void
    {
        $result = TypeHelper::justIntPositive(0);
        $this->assertNull($result);
    }
    
    public function testJustIntPositiveWithNegativeInteger(): void
    {
        $result = TypeHelper::justIntPositive(-5);
        $this->assertNull($result);
    }
    
    public function testJustIntPositiveWithString(): void
    {
        $result = TypeHelper::justIntPositive('123');
        $this->assertNull($result);
    }
    
    public function testJustIntPositiveWithFloat(): void
    {
        $result = TypeHelper::justIntPositive(123.45);
        $this->assertNull($result);
    }
    
    public function testJustIntPositiveWithNull(): void
    {
        $result = TypeHelper::justIntPositive(null);
        $this->assertNull($result);
    }
    
    // ============================================
    // guid Tests
    // ============================================
    
    public function testGuidReturnsString(): void
    {
        $guid = TypeHelper::guid();
        $this->assertIsString($guid);
    }
    
    public function testGuidIsNotEmpty(): void
    {
        $guid = TypeHelper::guid();
        $this->assertNotEmpty($guid);
    }
    
    public function testGuidHasCorrectFormat(): void
    {
        $guid = TypeHelper::guid();
        // GUID format: 8 groups of 4 hex characters (32 hex characters total, no dashes)
        // Example: 12345678123412341234123412345678
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/i', $guid);
    }
    
    public function testGuidIsUnique(): void
    {
        $guid1 = TypeHelper::guid();
        $guid2 = TypeHelper::guid();
        $guid3 = TypeHelper::guid();
        
        $this->assertNotEquals($guid1, $guid2);
        $this->assertNotEquals($guid2, $guid3);
        $this->assertNotEquals($guid1, $guid3);
    }
    
    public function testGuidLength(): void
    {
        $guid = TypeHelper::guid();
        // GUID should be 32 characters (8 groups of 4 hex characters, no dashes)
        $this->assertEquals(32, strlen($guid));
    }
    
    // ============================================
    // timeStamp Tests
    // ============================================
    
    public function testTimeStampReturnsString(): void
    {
        $timestamp = TypeHelper::timeStamp();
        $this->assertIsString($timestamp);
    }
    
    public function testTimeStampHasCorrectFormat(): void
    {
        $timestamp = TypeHelper::timeStamp();
        // Format: Y-m-d H:i:s (e.g., 2024-01-15 14:30:45)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp);
    }
    
    public function testTimeStampIsCurrentTime(): void
    {
        $timestamp1 = TypeHelper::timeStamp();
        sleep(1);
        $timestamp2 = TypeHelper::timeStamp();
        
        // Timestamps should be different (at least 1 second apart)
        $this->assertNotEquals($timestamp1, $timestamp2);
    }
    
    public function testTimeStampIsValidDateTime(): void
    {
        $timestamp = TypeHelper::timeStamp();
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
        
        $this->assertInstanceOf(\DateTime::class, $dateTime);
        $this->assertEquals($timestamp, $dateTime->format('Y-m-d H:i:s'));
    }
    
    // ============================================
    // getNonNullableProperties Tests
    // ============================================
    
    public function testGetNonNullablePropertiesWithTypedProperties(): void
    {
        $object = new class {
            public int $id = 1;
            public string $name = 'Test';
            public string $email = 'test@example.com';
            public ?string $description = null;
        };
        
        $result = TypeHelper::getNonNullableProperties($object);
        
        $this->assertIsArray($result);
        $this->assertContains('name', $result);
        $this->assertContains('email', $result);
        $this->assertNotContains('id', $result); // id is excluded
        $this->assertNotContains('description', $result); // nullable property
    }
    
    public function testGetNonNullablePropertiesExcludesId(): void
    {
        $object = new class {
            public int $id = 1;
            public string $name = 'Test';
        };
        
        $result = TypeHelper::getNonNullableProperties($object);
        
        $this->assertIsArray($result);
        $this->assertNotContains('id', $result);
        $this->assertContains('name', $result);
    }
    
    public function testGetNonNullablePropertiesWithOnlyNullableProperties(): void
    {
        $object = new class {
            public int $id = 1;
            public ?string $description = null;
            public ?int $count = null;
        };
        
        $result = TypeHelper::getNonNullableProperties($object);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    public function testGetNonNullablePropertiesWithMixedTypes(): void
    {
        $object = new class {
            public int $id = 1;
            public string $name = 'Test';
            public int $age = 25;
            public bool $active = true;
            public ?string $notes = null;
            public float $price = 19.99;
        };
        
        $result = TypeHelper::getNonNullableProperties($object);
        
        $this->assertIsArray($result);
        $this->assertContains('name', $result);
        $this->assertContains('age', $result);
        $this->assertContains('active', $result);
        $this->assertContains('price', $result);
        $this->assertNotContains('id', $result);
        $this->assertNotContains('notes', $result);
    }
    
    public function testGetNonNullablePropertiesWithNoProperties(): void
    {
        $object = new class {
            // No properties
        };
        
        $result = TypeHelper::getNonNullableProperties($object);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    public function testGetNonNullablePropertiesWithProtectedProperties(): void
    {
        $object = new class {
            public int $id = 1;
            public string $name = 'Test';
            protected string $password = 'secret';
        };
        
        $result = TypeHelper::getNonNullableProperties($object);
        
        $this->assertIsArray($result);
        $this->assertContains('name', $result);
        // Protected properties are included in reflection
        $this->assertContains('password', $result);
    }
    
    // ============================================
    // getClassPublicFunctions Tests
    // ============================================
    
    public function testGetClassPublicFunctionsReturnsArray(): void
    {
        $methods = TypeHelper::getClassPublicFunctions(self::class);
        
        $this->assertIsArray($methods);
    }
    
    public function testGetClassPublicFunctionsIncludesPublicMethods(): void
    {
        $methods = TypeHelper::getClassPublicFunctions(self::class);
        
        $this->assertContains('testGetClassPublicFunctionsReturnsArray', $methods);
    }
    
    public function testGetClassPublicFunctionsExcludesPrivateMethods(): void
    {
        $testClass = new class {
            public function publicMethod(): void {}
            private function privateMethod(): void {}
        };
        
        $methods = TypeHelper::getClassPublicFunctions(get_class($testClass));
        
        $this->assertContains('publicMethod', $methods);
        $this->assertNotContains('privateMethod', $methods);
    }
    
    public function testGetClassPublicFunctionsExcludesProtectedMethods(): void
    {
        $testClass = new class {
            public function publicMethod(): void {}
            protected function protectedMethod(): void {}
        };
        
        $methods = TypeHelper::getClassPublicFunctions(get_class($testClass));
        
        $this->assertContains('publicMethod', $methods);
        $this->assertNotContains('protectedMethod', $methods);
    }
    
    public function testGetClassPublicFunctionsWithExclude(): void
    {
        $testClass = new class {
            public function method1(): void {}
            public function method2(): void {}
            public function method3(): void {}
        };
        
        $methods = TypeHelper::getClassPublicFunctions(get_class($testClass), 'method2');
        
        $this->assertContains('method1', $methods);
        $this->assertNotContains('method2', $methods);
        $this->assertContains('method3', $methods);
    }
    
    public function testGetClassPublicFunctionsWithNullExclude(): void
    {
        $testClass = new class {
            public function method1(): void {}
            public function method2(): void {}
        };
        
        $methods = TypeHelper::getClassPublicFunctions(get_class($testClass), null);
        
        $this->assertContains('method1', $methods);
        $this->assertContains('method2', $methods);
    }
    
    public function testGetClassPublicFunctionsWithStdClass(): void
    {
        $methods = TypeHelper::getClassPublicFunctions(\stdClass::class);
        
        $this->assertIsArray($methods);
        // stdClass has no public methods by default
        $this->assertEmpty($methods);
    }
    
    public function testGetClassPublicFunctionsWithTypeHelper(): void
    {
        $methods = TypeHelper::getClassPublicFunctions(TypeHelper::class);
        
        $this->assertIsArray($methods);
        $this->assertContains('justInt', $methods);
        $this->assertContains('justIntPositive', $methods);
        $this->assertContains('guid', $methods);
        $this->assertContains('timeStamp', $methods);
        $this->assertContains('getNonNullableProperties', $methods);
        $this->assertContains('getClassPublicFunctions', $methods);
    }
    
    public function testGetClassPublicFunctionsWithExcludeForTypeHelper(): void
    {
        $methods = TypeHelper::getClassPublicFunctions(TypeHelper::class, 'guid');
        
        $this->assertIsArray($methods);
        $this->assertContains('justInt', $methods);
        $this->assertNotContains('guid', $methods);
        $this->assertContains('timeStamp', $methods);
    }
}


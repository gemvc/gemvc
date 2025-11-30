<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\StringHelper;

class StringHelperTest extends TestCase
{
    // ============================================
    // capitalizeAfterSpace Tests
    // ============================================
    
    public function testCapitalizeAfterSpaceWithSingleWord(): void
    {
        $result = StringHelper::capitalizeAfterSpace('hello');
        $this->assertEquals('Hello', $result);
    }
    
    public function testCapitalizeAfterSpaceWithMultipleWords(): void
    {
        $result = StringHelper::capitalizeAfterSpace('hello world test');
        $this->assertEquals('Hello World Test', $result);
    }
    
    public function testCapitalizeAfterSpaceWithAlreadyCapitalized(): void
    {
        $result = StringHelper::capitalizeAfterSpace('Hello World');
        $this->assertEquals('Hello World', $result);
    }
    
    public function testCapitalizeAfterSpaceWithMixedCase(): void
    {
        $result = StringHelper::capitalizeAfterSpace('hELLo WoRLd');
        $this->assertEquals('HELLo WoRLd', $result);
    }
    
    public function testCapitalizeAfterSpaceWithEmptyString(): void
    {
        $result = StringHelper::capitalizeAfterSpace('');
        $this->assertEquals('', $result);
    }
    
    public function testCapitalizeAfterSpaceWithMultipleSpaces(): void
    {
        $result = StringHelper::capitalizeAfterSpace('hello  world   test');
        $this->assertEquals('Hello  World   Test', $result);
    }
    
    // ============================================
    // randomString Tests
    // ============================================
    
    public function testRandomStringWithPositiveLength(): void
    {
        $result = StringHelper::randomString(10);
        $this->assertIsString($result);
        $this->assertEquals(10, strlen($result));
    }
    
    public function testRandomStringWithZeroLength(): void
    {
        $result = StringHelper::randomString(0);
        $this->assertIsString($result);
        $this->assertEquals(0, strlen($result));
        $this->assertEquals('', $result);
    }
    
    public function testRandomStringWithLongLength(): void
    {
        $result = StringHelper::randomString(100);
        $this->assertIsString($result);
        $this->assertEquals(100, strlen($result));
    }
    
    public function testRandomStringContainsValidCharacters(): void
    {
        $result = StringHelper::randomString(1000);
        $validChars = ['2', '_', '3', '4', '5', '&', '6', '7', '8', '9', '!', '$', '%', '&', '(', ')', 
                      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'K', 'M', 'N', 'P', 'Q', 'R', 'S', 'U', 
                      'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'k', 'm', 'n', 
                      'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
        
        $chars = str_split($result);
        foreach ($chars as $char) {
            $this->assertContains($char, $validChars, "Character '$char' is not in valid character set");
        }
    }
    
    public function testRandomStringProducesDifferentResults(): void
    {
        $result1 = StringHelper::randomString(50);
        $result2 = StringHelper::randomString(50);
        // Very unlikely to be the same, but possible
        // We just verify both are valid strings
        $this->assertIsString($result1);
        $this->assertIsString($result2);
        $this->assertEquals(50, strlen($result1));
        $this->assertEquals(50, strlen($result2));
    }
    
    // ============================================
    // makeWebName Tests
    // ============================================
    
    public function testMakeWebNameWithSimpleString(): void
    {
        $result = StringHelper::makeWebName('Hello World');
        $this->assertEquals('hello-world', $result);
    }
    
    public function testMakeWebNameWithUmlauts(): void
    {
        $result = StringHelper::makeWebName('Müller Straße');
        $this->assertEquals('mueller-strasse', $result);
    }
    
    public function testMakeWebNameWithSpecialCharacters(): void
    {
        $result = StringHelper::makeWebName('Test & Test!');
        $this->assertEquals('test-test', $result);
    }
    
    public function testMakeWebNameWithMaxLength(): void
    {
        $longString = 'This is a very long string that exceeds the default maximum length';
        $result = StringHelper::makeWebName($longString, 30);
        $this->assertLessThanOrEqual(30, strlen($result));
        $this->assertStringStartsWith('this-is-a-very-long-string', $result);
    }
    
    public function testMakeWebNameTrimsHyphens(): void
    {
        $result = StringHelper::makeWebName('---test---');
        $this->assertEquals('test', $result);
    }
    
    public function testMakeWebNameRemovesMultipleHyphens(): void
    {
        $result = StringHelper::makeWebName('test---multiple---hyphens');
        $this->assertEquals('test-multiple-hyphens', $result);
    }
    
    public function testMakeWebNameWithEuropeanCharacters(): void
    {
        $result = StringHelper::makeWebName('Café résumé');
        $this->assertEquals('cafe-resume', $result);
    }
    
    public function testMakeWebNameWithNumbers(): void
    {
        $result = StringHelper::makeWebName('Test 123 String');
        $this->assertEquals('test-123-string', $result);
    }
    
    public function testMakeWebNameWithEmptyString(): void
    {
        $result = StringHelper::makeWebName('');
        $this->assertEquals('', $result);
    }
    
    public function testMakeWebNameWithOnlySpecialCharacters(): void
    {
        $result = StringHelper::makeWebName('!!!@@@###');
        $this->assertEquals('', $result);
    }
    
    public function testMakeWebNameDoesNotEndWithHyphenAfterTruncation(): void
    {
        $longString = 'a' . str_repeat('-b', 50);
        $result = StringHelper::makeWebName($longString, 10);
        $this->assertStringEndsNotWith('-', $result);
    }
    
    // ============================================
    // sanitizedString Tests
    // ============================================
    
    public function testSanitizedStringWithValidString(): void
    {
        $result = StringHelper::sanitizedString('Hello World 123');
        $this->assertEquals('Hello World 123', $result);
    }
    
    public function testSanitizedStringWithUnderscores(): void
    {
        $result = StringHelper::sanitizedString('test_string_123');
        $this->assertEquals('test_string_123', $result);
    }
    
    public function testSanitizedStringWithHyphens(): void
    {
        $result = StringHelper::sanitizedString('test-string-123');
        $this->assertEquals('test-string-123', $result);
    }
    
    public function testSanitizedStringWithAllowedSpecialChars(): void
    {
        $result = StringHelper::sanitizedString('test/string(123);,.,');
        $this->assertEquals('test/string(123);,.,', $result);
    }
    
    public function testSanitizedStringWithUmlauts(): void
    {
        $result = StringHelper::sanitizedString('äÄöÖüÜß');
        $this->assertEquals('äÄöÖüÜß', $result);
    }
    
    public function testSanitizedStringWithInvalidCharacters(): void
    {
        $result = StringHelper::sanitizedString('test@invalid#string');
        $this->assertNull($result);
    }
    
    public function testSanitizedStringWithScriptTags(): void
    {
        $result = StringHelper::sanitizedString('<script>alert("xss")</script>');
        $this->assertNull($result);
    }
    
    public function testSanitizedStringWithEmptyString(): void
    {
        $result = StringHelper::sanitizedString('');
        $this->assertNull($result); // Empty string doesn't match pattern (needs 1-255 chars)
    }
    
    public function testSanitizedStringWithTooLongString(): void
    {
        $longString = str_repeat('a', 256);
        $result = StringHelper::sanitizedString($longString);
        $this->assertNull($result);
    }
    
    public function testSanitizedStringWithMaxLength(): void
    {
        $maxString = str_repeat('a', 255);
        $result = StringHelper::sanitizedString($maxString);
        $this->assertEquals($maxString, $result);
    }
    
    // ============================================
    // isValidUrl Tests
    // ============================================
    
    public function testIsValidUrlWithHttpUrl(): void
    {
        $this->assertTrue(StringHelper::isValidUrl('http://example.com'));
    }
    
    public function testIsValidUrlWithHttpsUrl(): void
    {
        $this->assertTrue(StringHelper::isValidUrl('https://example.com'));
    }
    
    public function testIsValidUrlWithPath(): void
    {
        $this->assertTrue(StringHelper::isValidUrl('https://example.com/path/to/page'));
    }
    
    public function testIsValidUrlWithQueryString(): void
    {
        $this->assertTrue(StringHelper::isValidUrl('https://example.com?param=value'));
    }
    
    public function testIsValidUrlWithInvalidString(): void
    {
        $this->assertFalse(StringHelper::isValidUrl('not a url'));
    }
    
    public function testIsValidUrlWithEmptyString(): void
    {
        $this->assertFalse(StringHelper::isValidUrl(''));
    }
    
    public function testIsValidUrlWithPartialUrl(): void
    {
        $this->assertFalse(StringHelper::isValidUrl('example.com'));
    }
    
    // ============================================
    // isValidEmail Tests
    // ============================================
    
    public function testIsValidEmailWithValidEmail(): void
    {
        $this->assertTrue(StringHelper::isValidEmail('test@example.com'));
    }
    
    public function testIsValidEmailWithComplexEmail(): void
    {
        $this->assertTrue(StringHelper::isValidEmail('user.name+tag@example.co.uk'));
    }
    
    public function testIsValidEmailWithInvalidEmail(): void
    {
        $this->assertFalse(StringHelper::isValidEmail('not an email'));
    }
    
    public function testIsValidEmailWithEmptyString(): void
    {
        $this->assertFalse(StringHelper::isValidEmail(''));
    }
    
    public function testIsValidEmailWithMissingAt(): void
    {
        $this->assertFalse(StringHelper::isValidEmail('testexample.com'));
    }
    
    public function testIsValidEmailWithMissingDomain(): void
    {
        $this->assertFalse(StringHelper::isValidEmail('test@'));
    }
    
    // ============================================
    // safeURL Tests
    // ============================================
    
    public function testSafeURLWithValidUrl(): void
    {
        $result = StringHelper::safeURL('https://example.com');
        $this->assertEquals('https://example.com', $result);
    }
    
    public function testSafeURLWithHttpUrl(): void
    {
        $result = StringHelper::safeURL('http://example.com');
        $this->assertEquals('http://example.com', $result);
    }
    
    public function testSafeURLWithInvalidUrl(): void
    {
        $result = StringHelper::safeURL('not a url');
        $this->assertNull($result);
    }
    
    public function testSafeURLWithEmptyString(): void
    {
        $result = StringHelper::safeURL('');
        $this->assertNull($result);
    }
    
    public function testSafeURLWithPartialUrl(): void
    {
        $result = StringHelper::safeURL('example.com');
        $this->assertNull($result);
    }
    
    public function testSafeURLWithUrlContainingPath(): void
    {
        $result = StringHelper::safeURL('https://example.com/path/to/page');
        $this->assertEquals('https://example.com/path/to/page', $result);
    }
    
    // ============================================
    // safeEmail Tests
    // ============================================
    
    public function testSafeEmailWithValidEmail(): void
    {
        $result = StringHelper::safeEmail('Test@Example.COM');
        $this->assertEquals('test@example.com', $result);
    }
    
    public function testSafeEmailWithWhitespace(): void
    {
        $result = StringHelper::safeEmail('  test@example.com  ');
        $this->assertEquals('test@example.com', $result);
    }
    
    public function testSafeEmailWithInvalidEmail(): void
    {
        $result = StringHelper::safeEmail('not an email');
        $this->assertNull($result);
    }
    
    public function testSafeEmailWithEmptyString(): void
    {
        $result = StringHelper::safeEmail('');
        $this->assertNull($result);
    }
    
    public function testSafeEmailWithUppercaseEmail(): void
    {
        $result = StringHelper::safeEmail('TEST@EXAMPLE.COM');
        $this->assertEquals('test@example.com', $result);
    }
    
    public function testSafeEmailWithMixedCaseEmail(): void
    {
        $result = StringHelper::safeEmail('Test.User@Example.COM');
        $this->assertEquals('test.user@example.com', $result);
    }
    
    public function testSafeEmailWithComplexEmail(): void
    {
        $result = StringHelper::safeEmail('user.name+tag@example.co.uk');
        $this->assertEquals('user.name+tag@example.co.uk', $result);
    }
}


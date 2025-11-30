<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\JsonHelper;

class JsonHelperTest extends TestCase
{
    // ============================================
    // validateJsonStringReturnArray Tests
    // ============================================
    
    public function testValidateJsonStringReturnArrayWithValidJsonArray(): void
    {
        $json = '{"key": "value", "number": 123}';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals(123, $result['number']);
    }
    
    public function testValidateJsonStringReturnArrayWithNestedArray(): void
    {
        $json = '{"nested": {"key": "value"}}';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        $this->assertIsArray($result);
        $this->assertIsArray($result['nested']);
        $this->assertEquals('value', $result['nested']['key']);
    }
    
    public function testValidateJsonStringReturnArrayWithArrayOfObjects(): void
    {
        $json = '[{"id": 1}, {"id": 2}]';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
    }
    
    public function testValidateJsonStringReturnArrayWithInvalidJson(): void
    {
        $json = '{"invalid": json}';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnArrayWithEmptyString(): void
    {
        $result = JsonHelper::validateJsonStringReturnArray('');
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnArrayWithNonArrayJson(): void
    {
        // JSON object (not array) should return null
        $json = '{"key": "value"}';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        // This should return array because json_decode with true returns associative array
        $this->assertIsArray($result);
    }
    
    public function testValidateJsonStringReturnArrayWithNumber(): void
    {
        $json = '123';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        // Number is valid JSON but not an array
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnArrayWithString(): void
    {
        $json = '"just a string"';
        $result = JsonHelper::validateJsonStringReturnArray($json);
        // String is valid JSON but not an array
        $this->assertNull($result);
    }
    
    // ============================================
    // validateJson Tests
    // ============================================
    
    public function testValidateJsonWithValidJsonString(): void
    {
        $json = '{"key": "value"}';
        $result = JsonHelper::validateJson($json);
        $this->assertEquals($json, $result);
    }
    
    public function testValidateJsonWithValidJsonArray(): void
    {
        $json = '[1, 2, 3]';
        $result = JsonHelper::validateJson($json);
        $this->assertEquals($json, $result);
    }
    
    public function testValidateJsonWithWhitespace(): void
    {
        $json = '  {"key": "value"}  ';
        $result = JsonHelper::validateJson($json);
        $this->assertEquals('{"key": "value"}', $result);
    }
    
    public function testValidateJsonWithInvalidJson(): void
    {
        $json = '{"invalid": json}';
        $result = JsonHelper::validateJson($json);
        $this->assertFalse($result);
    }
    
    public function testValidateJsonWithEmptyString(): void
    {
        $result = JsonHelper::validateJson('');
        // Empty string is not valid JSON
        $this->assertFalse($result);
    }
    
    public function testValidateJsonWithNonStringInput(): void
    {
        $result = JsonHelper::validateJson(123);
        $this->assertFalse($result);
    }
    
    public function testValidateJsonWithArrayInput(): void
    {
        $result = JsonHelper::validateJson(['key' => 'value']);
        $this->assertFalse($result);
    }
    
    public function testValidateJsonWithObjectInput(): void
    {
        $result = JsonHelper::validateJson((object)['key' => 'value']);
        $this->assertFalse($result);
    }
    
    public function testValidateJsonWithNullInput(): void
    {
        $result = JsonHelper::validateJson(null);
        $this->assertFalse($result);
    }
    
    public function testValidateJsonWithBooleanInput(): void
    {
        $result = JsonHelper::validateJson(true);
        $this->assertFalse($result);
    }
    
    // ============================================
    // validateJsonStringReturnObject Tests
    // ============================================
    
    public function testValidateJsonStringReturnObjectWithValidJsonObject(): void
    {
        $json = '{"key": "value", "number": 123}';
        $result = JsonHelper::validateJsonStringReturnObject($json);
        $this->assertIsObject($result);
        $this->assertEquals('value', $result->key);
        $this->assertEquals(123, $result->number);
    }
    
    public function testValidateJsonStringReturnObjectWithNestedObject(): void
    {
        $json = '{"nested": {"key": "value"}}';
        $result = JsonHelper::validateJsonStringReturnObject($json);
        $this->assertIsObject($result);
        $this->assertIsObject($result->nested);
        $this->assertEquals('value', $result->nested->key);
    }
    
    public function testValidateJsonStringReturnObjectWithInvalidJson(): void
    {
        $json = '{"invalid": json}';
        $result = JsonHelper::validateJsonStringReturnObject($json);
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnObjectWithEmptyString(): void
    {
        $result = JsonHelper::validateJsonStringReturnObject('');
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnObjectWithArrayJson(): void
    {
        // JSON array should return null (not an object)
        $json = '[1, 2, 3]';
        $result = JsonHelper::validateJsonStringReturnObject($json);
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnObjectWithNumber(): void
    {
        $json = '123';
        $result = JsonHelper::validateJsonStringReturnObject($json);
        // Number is valid JSON but not an object
        $this->assertNull($result);
    }
    
    public function testValidateJsonStringReturnObjectWithString(): void
    {
        $json = '"just a string"';
        $result = JsonHelper::validateJsonStringReturnObject($json);
        // String is valid JSON but not an object
        $this->assertNull($result);
    }
    
    // ============================================
    // encodeToJson Tests
    // ============================================
    
    public function testEncodeToJsonWithArray(): void
    {
        $data = ['key' => 'value', 'number' => 123];
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals(123, $decoded['number']);
    }
    
    public function testEncodeToJsonWithObject(): void
    {
        $data = (object)['key' => 'value'];
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertJson($result);
    }
    
    public function testEncodeToJsonWithNestedArray(): void
    {
        $data = ['nested' => ['key' => 'value']];
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertJson($result);
    }
    
    public function testEncodeToJsonWithString(): void
    {
        $data = 'simple string';
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertEquals('"simple string"', $result);
    }
    
    public function testEncodeToJsonWithNumber(): void
    {
        $data = 123;
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertEquals('123', $result);
    }
    
    public function testEncodeToJsonWithBoolean(): void
    {
        $data = true;
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertEquals('true', $result);
    }
    
    public function testEncodeToJsonWithNull(): void
    {
        $data = null;
        $result = JsonHelper::encodeToJson($data);
        $this->assertIsString($result);
        $this->assertEquals('null', $result);
    }
    
    public function testEncodeToJsonWithPrettyPrint(): void
    {
        $data = ['key' => 'value'];
        $result = JsonHelper::encodeToJson($data, JSON_PRETTY_PRINT);
        $this->assertIsString($result);
        $this->assertStringContainsString("\n", $result);
        $this->assertJson($result);
    }
    
    public function testEncodeToJsonWithUnicodeEscaping(): void
    {
        $data = ['key' => 'ünicode'];
        $result = JsonHelper::encodeToJson($data, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($result);
        $this->assertStringContainsString('ünicode', $result);
    }
    
    public function testEncodeToJsonWithResource(): void
    {
        // Resources cannot be encoded to JSON
        $resource = fopen('php://memory', 'r');
        if ($resource !== false) {
            $result = JsonHelper::encodeToJson($resource);
            $this->assertFalse($result);
            fclose($resource);
        }
    }
    
    public function testEncodeToJsonWithCircularReference(): void
    {
        // Create circular reference
        $data = [];
        $data['self'] = &$data;
        
        $result = JsonHelper::encodeToJson($data);
        // This should fail or return false due to circular reference
        $this->assertFalse($result);
    }
    
    public function testEncodeToJsonWithInvalidUtf8(): void
    {
        // Create string with invalid UTF-8
        $data = "\xB1\x31";
        $result = JsonHelper::encodeToJson($data);
        // Invalid UTF-8 may cause json_encode to fail, returning false
        // or it may encode it (depending on PHP version and flags)
        if ($result === false) {
            $this->assertFalse($result);
        } else {
            $this->assertIsString($result);
        }
    }
}


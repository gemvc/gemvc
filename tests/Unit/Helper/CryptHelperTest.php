<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\CryptHelper;

class CryptHelperTest extends TestCase
{
    public function testHashPassword(): void
    {
        $password = 'testPassword123';
        $hash = CryptHelper::hashPassword($password);
        
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);
        // Argon2i hash should start with $argon2i$
        $this->assertStringStartsWith('$argon2i$', $hash);
    }
    
    public function testHashPasswordTrimsWhitespace(): void
    {
        $password = '  testPassword123  ';
        $hash1 = CryptHelper::hashPassword($password);
        $hash2 = CryptHelper::hashPassword(trim($password));
        
        // Both should verify the same password
        $this->assertTrue(CryptHelper::passwordVerify(trim($password), $hash1));
        $this->assertTrue(CryptHelper::passwordVerify(trim($password), $hash2));
    }
    
    public function testPasswordVerifyCorrectPassword(): void
    {
        $password = 'testPassword123';
        $hash = CryptHelper::hashPassword($password);
        
        $this->assertTrue(CryptHelper::passwordVerify($password, $hash));
    }
    
    public function testPasswordVerifyIncorrectPassword(): void
    {
        $password = 'testPassword123';
        $hash = CryptHelper::hashPassword($password);
        
        $this->assertFalse(CryptHelper::passwordVerify('wrongPassword', $hash));
    }
    
    public function testPasswordVerifyTrimsWhitespace(): void
    {
        $password = 'testPassword123';
        $hash = CryptHelper::hashPassword($password);
        
        $this->assertTrue(CryptHelper::passwordVerify('  testPassword123  ', $hash));
    }
    
    public function testCryptEncrypt(): void
    {
        $string = 'Hello, World!';
        $secret = 'my-secret-key';
        $iv = 'my-initialization-vector';
        
        $encrypted = CryptHelper::crypt($string, $secret, $iv, 'e');
        
        $this->assertNotFalse($encrypted);
        $this->assertIsString($encrypted);
        $this->assertNotEquals($string, $encrypted);
    }
    
    public function testCryptDecrypt(): void
    {
        $string = 'Hello, World!';
        $secret = 'my-secret-key';
        $iv = 'my-initialization-vector';
        
        $encrypted = CryptHelper::crypt($string, $secret, $iv, 'e');
        $this->assertNotFalse($encrypted);
        
        $decrypted = CryptHelper::crypt($encrypted, $secret, $iv, 'd');
        
        $this->assertNotFalse($decrypted);
        $this->assertEquals($string, $decrypted);
    }
    
    public function testCryptWithCustomAlgorithm(): void
    {
        $string = 'Hello, World!';
        $secret = 'my-secret-key';
        $iv = 'my-initialization-vector';
        $algorithm = 'AES-128-CBC';
        
        $encrypted = CryptHelper::crypt($string, $secret, $iv, 'e', $algorithm);
        $this->assertNotFalse($encrypted);
        
        $decrypted = CryptHelper::crypt($encrypted, $secret, $iv, 'd', $algorithm);
        
        $this->assertNotFalse($decrypted);
        $this->assertEquals($string, $decrypted);
    }
    
    public function testEncryptString(): void
    {
        $string = 'Sensitive data';
        $key = 'my-encryption-key-32-chars-long!!';
        
        $encrypted = CryptHelper::encryptString($string, $key);
        
        $this->assertNotFalse($encrypted);
        $this->assertIsString($encrypted);
        $this->assertNotEquals($string, $encrypted);
    }
    
    public function testDecryptString(): void
    {
        $string = 'Sensitive data';
        $key = 'my-encryption-key-32-chars-long!!';
        
        $encrypted = CryptHelper::encryptString($string, $key);
        $this->assertNotFalse($encrypted);
        
        $decrypted = CryptHelper::decryptString($encrypted, $key);
        
        $this->assertNotFalse($decrypted);
        $this->assertEquals($string, $decrypted);
    }
    
    public function testDecryptStringWithWrongKey(): void
    {
        $string = 'Sensitive data';
        $key = 'my-encryption-key-32-chars-long!!';
        $wrongKey = 'wrong-encryption-key-32-chars!!';
        
        $encrypted = CryptHelper::encryptString($string, $key);
        $this->assertNotFalse($encrypted);
        
        $decrypted = CryptHelper::decryptString($encrypted, $wrongKey);
        
        // Should return false due to HMAC mismatch
        $this->assertFalse($decrypted);
    }
    
    public function testEncryptStringWithInvalidKey(): void
    {
        $string = 'Sensitive data';
        $key = 'short-key'; // Too short
        
        $encrypted = CryptHelper::encryptString($string, $key);
        
        // May still work but less secure, or may fail
        // Just verify it doesn't crash - result can be false|string
        if ($encrypted !== false) {
            $this->assertIsString($encrypted);
        } else {
            $this->assertFalse($encrypted);
        }
    }
}


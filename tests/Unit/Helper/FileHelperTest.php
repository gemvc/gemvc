<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\FileHelper;
use Gemvc\Helper\CryptHelper;

class FileHelperTest extends TestCase
{
    private string $tempDir;
    private array $tempFiles = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gemvc_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }
    
    protected function tearDown(): void
    {
        // Clean up temp files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }
        
        parent::tearDown();
    }
    
    private function createTempFile(string $content = 'test content'): string
    {
        $file = $this->tempDir . DIRECTORY_SEPARATOR . 'test_' . uniqid() . '.txt';
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructorWithValidSourceFile(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        
        $this->assertEquals($sourceFile, $fileHelper->sourceFile);
        $this->assertEquals($sourceFile, $fileHelper->outputFile);
        $this->assertNull($fileHelper->error);
    }
    
    public function testConstructorWithCustomOutputFile(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'output.txt';
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $this->assertEquals($sourceFile, $fileHelper->sourceFile);
        $this->assertEquals($outputFile, $fileHelper->outputFile);
    }
    
    public function testConstructorSetsErrorWhenSourceFileNotFound(): void
    {
        $nonExistentFile = $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
        $fileHelper = new FileHelper($nonExistentFile);
        
        $this->assertNotNull($fileHelper->error);
        $this->assertStringContainsString('Source-file not found', $fileHelper->error);
    }
    
    public function testConstructorSetsErrorWhenDestinationDirectoryNotExists(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = '/nonexistent/directory/file.txt';
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $this->assertNotNull($fileHelper->error);
        $this->assertStringContainsString('Destination directory does not exists', $fileHelper->error);
    }
    
    // ============================================
    // readFileContents Tests
    // ============================================
    
    public function testReadFileContentsWithValidFile(): void
    {
        $content = 'Test file content';
        $sourceFile = $this->createTempFile($content);
        $fileHelper = new FileHelper($sourceFile);
        
        $result = $fileHelper->readFileContents();
        $this->assertEquals($content, $result);
    }
    
    public function testReadFileContentsReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->readFileContents();
        $this->assertFalse($result);
    }
    
    public function testReadFileContentsSetsErrorWhenFileNotFound(): void
    {
        $nonExistentFile = $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
        // Create FileHelper with non-existent file to set error
        $fileHelper = new FileHelper($nonExistentFile);
        
        $result = $fileHelper->readFileContents();
        $this->assertFalse($result);
    }
    
    // ============================================
    // isDestinationDirectoryExists Tests
    // ============================================
    
    public function testIsDestinationDirectoryExistsWithValidDirectory(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        
        $result = $fileHelper->isDestinationDirectoryExists();
        $this->assertTrue($result);
        $this->assertNull($fileHelper->error);
    }
    
    public function testIsDestinationDirectoryExistsWithInvalidDirectory(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = '/nonexistent/directory/file.txt';
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $result = $fileHelper->isDestinationDirectoryExists();
        $this->assertFalse($result);
        $this->assertNotNull($fileHelper->error);
    }
    
    // ============================================
    // deleteSourceFile Tests
    // ============================================
    
    public function testDeleteSourceFileWithExistingFile(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        
        $result = $fileHelper->deleteSourceFile();
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($sourceFile);
    }
    
    public function testDeleteSourceFileWithNonExistentFile(): void
    {
        $sourceFile = $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
        $fileHelper = new FileHelper($sourceFile);
        // File doesn't exist, so delete should return false
        $result = $fileHelper->deleteSourceFile();
        $this->assertFalse($result);
    }
    
    public function testDeleteSourceFileReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->deleteSourceFile();
        $this->assertFalse($result);
    }
    
    // ============================================
    // deleteDestinationFile Tests
    // ============================================
    
    public function testDeleteDestinationFileWithExistingFile(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $result = $fileHelper->deleteDestinationFile();
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($outputFile);
    }
    
    public function testDeleteDestinationFileWithNonExistentFile(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $result = $fileHelper->deleteDestinationFile();
        $this->assertFalse($result);
    }
    
    // ============================================
    // getFileSize Tests
    // ============================================
    
    public function testGetFileSizeWithSmallFile(): void
    {
        $sourceFile = $this->createTempFile('small content');
        $fileHelper = new FileHelper($sourceFile);
        
        $result = $fileHelper->getFileSize($sourceFile);
        $this->assertIsString($result);
        $this->assertStringEndsWith('KB', $result);
    }
    
    public function testGetFileSizeReturnsString(): void
    {
        $sourceFile = $this->createTempFile('test');
        $fileHelper = new FileHelper($sourceFile);
        
        $result = $fileHelper->getFileSize($sourceFile);
        $this->assertIsString($result);
    }
    
    // ============================================
    // toBase64File Tests
    // ============================================
    
    public function testToBase64FileWithValidFile(): void
    {
        $content = 'test content';
        $sourceFile = $this->createTempFile($content);
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'base64_output.txt';
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $result = $fileHelper->toBase64File();
        $this->assertEquals($outputFile, $result);
        $this->assertFileExists($outputFile);
        
        $encodedContent = file_get_contents($outputFile);
        $this->assertEquals(base64_encode($content), $encodedContent);
        
        // Clean up
        @unlink($outputFile);
    }
    
    public function testToBase64FileReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->toBase64File();
        $this->assertFalse($result);
    }
    
    // ============================================
    // fromBase64ToOrigin Tests
    // ============================================
    
    public function testFromBase64ToOriginWithValidBase64File(): void
    {
        $originalContent = 'test content';
        $base64Content = base64_encode($originalContent);
        $sourceFile = $this->createTempFile($base64Content);
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'decoded_output.txt';
        $fileHelper = new FileHelper($sourceFile, $outputFile);
        
        $result = $fileHelper->fromBase64ToOrigin();
        $this->assertEquals($outputFile, $result);
        $this->assertFileExists($outputFile);
        
        $decodedContent = file_get_contents($outputFile);
        $this->assertEquals($originalContent, $decodedContent);
        
        // Clean up
        @unlink($outputFile);
    }
    
    public function testFromBase64ToOriginReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->fromBase64ToOrigin();
        $this->assertFalse($result);
    }
    
    // ============================================
    // encrypt/decrypt Tests
    // ============================================
    
    public function testEncryptRequiresSecret(): void
    {
        $sourceFile = $this->createTempFile('test content');
        $fileHelper = new FileHelper($sourceFile);
        // Explicitly set secret to null to avoid uninitialized property error
        $fileHelper->secret = null;
        
        $result = $fileHelper->encrypt();
        $this->assertFalse($result);
        $this->assertNotNull($fileHelper->error);
        $this->assertStringContainsString('secret', $fileHelper->error);
    }
    
    public function testDecryptRequiresSecret(): void
    {
        $sourceFile = $this->createTempFile('test content');
        $fileHelper = new FileHelper($sourceFile);
        // Explicitly set secret to null to avoid uninitialized property error
        $fileHelper->secret = null;
        
        $result = $fileHelper->decrypt();
        $this->assertFalse($result);
        $this->assertNotNull($fileHelper->error);
        $this->assertStringContainsString('secret', $fileHelper->error);
    }
    
    public function testEncryptAndDecryptRoundTrip(): void
    {
        $originalContent = 'test content to encrypt';
        $sourceFile = $this->createTempFile($originalContent);
        $encryptedFile = $this->tempDir . DIRECTORY_SEPARATOR . 'encrypted.txt';
        $decryptedFile = $this->tempDir . DIRECTORY_SEPARATOR . 'decrypted.txt';
        
        $secret = 'test-secret-key';
        
        // Encrypt
        $fileHelper = new FileHelper($sourceFile, $encryptedFile);
        $fileHelper->secret = $secret;
        $encryptResult = $fileHelper->encrypt();
        $this->assertNotFalse($encryptResult);
        $this->assertFileExists($encryptedFile);
        
        // Verify encrypted content is different
        $encryptedContent = file_get_contents($encryptedFile);
        $this->assertNotEquals($originalContent, $encryptedContent);
        
        // Decrypt
        $fileHelper2 = new FileHelper($encryptedFile, $decryptedFile);
        $fileHelper2->secret = $secret;
        $decryptResult = $fileHelper2->decrypt();
        $this->assertNotFalse($decryptResult);
        $this->assertFileExists($decryptedFile);
        
        // Verify decrypted content matches original
        $decryptedContent = file_get_contents($decryptedFile);
        $this->assertEquals($originalContent, $decryptedContent);
        
        // Clean up
        @unlink($encryptedFile);
        @unlink($decryptedFile);
    }
    
    public function testEncryptReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        $fileHelper->secret = 'test-secret';
        
        $result = $fileHelper->encrypt();
        $this->assertFalse($result);
    }
    
    // Note: copy(), move(), delete(), and moveAndEncrypt() methods use shell_exec
    // which may not work in all test environments. These would require integration tests
    // or mocking shell_exec, which is complex. For now, we test the structure and
    // error handling paths.
    
    public function testCopyReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->copy();
        $this->assertFalse($result);
    }
    
    public function testMoveReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->move();
        $this->assertFalse($result);
    }
    
    public function testDeleteReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->delete();
        $this->assertFalse($result);
    }
    
    public function testMoveAndEncryptReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $fileHelper = new FileHelper($sourceFile);
        $fileHelper->error = 'Test error';
        
        $result = $fileHelper->moveAndEncrypt();
        $this->assertFalse($result);
    }
}


<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\ImageHelper;
use Gemvc\Helper\CryptHelper;

class ImageHelperTest extends TestCase
{
    private string $tempDir;
    private array $tempFiles = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gemvc_image_test_' . uniqid();
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
    
    private function createTestImage(string $format = 'png'): string
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $file = $this->tempDir . DIRECTORY_SEPARATOR . 'test_' . uniqid() . '.' . $format;
        
        $image = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        
        switch ($format) {
            case 'png':
                imagepng($image, $file);
                break;
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $file);
                break;
            case 'gif':
                imagegif($image, $file);
                break;
        }
        
        imagedestroy($image);
        $this->tempFiles[] = $file;
        return $file;
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructorWithValidSourceFile(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        
        $this->assertEquals($sourceFile, $imageHelper->sourceFile);
        $this->assertEquals($sourceFile, $imageHelper->outputFile);
        $this->assertNull($imageHelper->error);
    }
    
    public function testConstructorWithCustomOutputFile(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'output.txt';
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $this->assertEquals($sourceFile, $imageHelper->sourceFile);
        $this->assertEquals($outputFile, $imageHelper->outputFile);
    }
    
    public function testConstructorSetsErrorWhenSourceFileNotFound(): void
    {
        $nonExistentFile = $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
        $imageHelper = new ImageHelper($nonExistentFile);
        
        $this->assertNotNull($imageHelper->error);
        $this->assertStringContainsString('Source file not found', $imageHelper->error);
    }
    
    public function testConstructorSetsErrorWhenDestinationDirectoryNotExists(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = '/nonexistent/directory/file.txt';
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $this->assertNotNull($imageHelper->error);
        $this->assertStringContainsString('Destination directory does not exist', $imageHelper->error);
    }
    
    // ============================================
    // isDestinationDirectoryExists Tests
    // ============================================
    
    public function testIsDestinationDirectoryExistsWithValidDirectory(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        
        $result = $imageHelper->isDestinationDirectoryExists();
        $this->assertTrue($result);
    }
    
    public function testIsDestinationDirectoryExistsWithInvalidDirectory(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = '/nonexistent/directory/file.txt';
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->isDestinationDirectoryExists();
        $this->assertFalse($result);
    }
    
    // ============================================
    // readFileContents Tests
    // ============================================
    
    public function testReadFileContentsWithValidFile(): void
    {
        $content = 'Test file content';
        $sourceFile = $this->createTempFile($content);
        $imageHelper = new ImageHelper($sourceFile);
        
        $result = $imageHelper->readFileContents();
        $this->assertEquals($content, $result);
    }
    
    public function testReadFileContentsReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->readFileContents();
        $this->assertFalse($result);
    }
    
    // ============================================
    // deleteSourceFile Tests
    // ============================================
    
    public function testDeleteSourceFileWithExistingFile(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        
        $result = $imageHelper->deleteSourceFile();
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($sourceFile);
    }
    
    public function testDeleteSourceFileWithNonExistentFile(): void
    {
        $sourceFile = $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.txt';
        $imageHelper = new ImageHelper($sourceFile);
        
        $result = $imageHelper->deleteSourceFile();
        $this->assertFalse($result);
    }
    
    public function testDeleteSourceFileReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->deleteSourceFile();
        $this->assertFalse($result);
    }
    
    // ============================================
    // deleteDestinationFile Tests
    // ============================================
    
    public function testDeleteDestinationFileWithExistingFile(): void
    {
        $sourceFile = $this->createTempFile();
        $outputFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->deleteDestinationFile();
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($outputFile);
    }
    
    // ============================================
    // getFileSize Tests
    // ============================================
    
    public function testGetFileSizeWithSmallFile(): void
    {
        $sourceFile = $this->createTempFile('small content');
        $imageHelper = new ImageHelper($sourceFile);
        
        $result = $imageHelper->getFileSize($sourceFile);
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2} (B|KB|MB|GB)$/', $result);
    }
    
    public function testGetFileSizeReturnsString(): void
    {
        $sourceFile = $this->createTempFile('test');
        $imageHelper = new ImageHelper($sourceFile);
        
        $result = $imageHelper->getFileSize($sourceFile);
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
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->toBase64File();
        $this->assertEquals($outputFile, $result);
        $this->assertFileExists($outputFile);
        
        $encodedContent = file_get_contents($outputFile);
        $this->assertEquals(base64_encode($content), $encodedContent);
        
        @unlink($outputFile);
    }
    
    public function testToBase64FileReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->toBase64File();
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
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->fromBase64ToOrigin();
        $this->assertEquals($outputFile, $result);
        $this->assertFileExists($outputFile);
        
        $decodedContent = file_get_contents($outputFile);
        $this->assertEquals($originalContent, $decodedContent);
        
        @unlink($outputFile);
    }
    
    // ============================================
    // encrypt/decrypt Tests
    // ============================================
    
    public function testEncryptRequiresSecret(): void
    {
        $sourceFile = $this->createTempFile('test content');
        $imageHelper = new ImageHelper($sourceFile);
        // Explicitly set secret to null to avoid uninitialized property error
        $imageHelper->secret = null;
        
        $result = $imageHelper->encrypt();
        $this->assertFalse($result);
        $this->assertNotNull($imageHelper->error);
        $this->assertStringContainsString('secret', $imageHelper->error);
    }
    
    public function testDecryptRequiresSecret(): void
    {
        $sourceFile = $this->createTempFile('test content');
        $imageHelper = new ImageHelper($sourceFile);
        // Explicitly set secret to null to avoid uninitialized property error
        $imageHelper->secret = null;
        
        $result = $imageHelper->decrypt();
        $this->assertFalse($result);
        $this->assertNotNull($imageHelper->error);
        $this->assertStringContainsString('secret', $imageHelper->error);
    }
    
    public function testEncryptAndDecryptRoundTrip(): void
    {
        $originalContent = 'test content to encrypt';
        $sourceFile = $this->createTempFile($originalContent);
        $encryptedFile = $this->tempDir . DIRECTORY_SEPARATOR . 'encrypted.txt';
        $decryptedFile = $this->tempDir . DIRECTORY_SEPARATOR . 'decrypted.txt';
        
        $secret = 'test-secret-key';
        
        // Encrypt
        $imageHelper = new ImageHelper($sourceFile, $encryptedFile);
        $imageHelper->secret = $secret;
        $encryptResult = $imageHelper->encrypt();
        $this->assertNotFalse($encryptResult);
        $this->assertFileExists($encryptedFile);
        
        // Decrypt
        $imageHelper2 = new ImageHelper($encryptedFile, $decryptedFile);
        $imageHelper2->secret = $secret;
        $decryptResult = $imageHelper2->decrypt();
        $this->assertNotFalse($decryptResult);
        $this->assertFileExists($decryptedFile);
        
        // Verify decrypted content matches original
        $decryptedContent = file_get_contents($decryptedFile);
        $this->assertEquals($originalContent, $decryptedContent);
        
        @unlink($encryptedFile);
        @unlink($decryptedFile);
    }
    
    // ============================================
    // convertToWebP Tests
    // ============================================
    
    public function testConvertToWebPRequiresGdExtension(): void
    {
        if (!extension_loaded('gd')) {
            $sourceFile = $this->createTempFile();
            $imageHelper = new ImageHelper($sourceFile);
            
            $result = $imageHelper->convertToWebP();
            $this->assertFalse($result);
            $this->assertStringContainsString('GD extension', $imageHelper->error);
        } else {
            $this->markTestSkipped('GD extension is loaded');
        }
    }
    
    public function testConvertToWebPWithValidPngImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $sourceFile = $this->createTestImage('png');
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'output.webp';
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->convertToWebP();
        $this->assertTrue($result);
        $this->assertFileExists($outputFile);
        $this->assertStringEndsWith('.webp', $imageHelper->outputFile);
        
        @unlink($outputFile);
    }
    
    public function testConvertToWebPReturnsFalseWhenErrorSet(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $sourceFile = $this->createTestImage('png');
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->convertToWebP();
        $this->assertFalse($result);
    }
    
    // ============================================
    // setJpegQuality Tests
    // ============================================
    
    public function testSetJpegQualityRequiresGdExtension(): void
    {
        if (!extension_loaded('gd')) {
            $sourceFile = $this->createTempFile();
            $imageHelper = new ImageHelper($sourceFile);
            
            $result = $imageHelper->setJpegQuality();
            $this->assertFalse($result);
            $this->assertStringContainsString('GD extension', $imageHelper->error);
        } else {
            $this->markTestSkipped('GD extension is loaded');
        }
    }
    
    public function testSetJpegQualityWithValidJpegImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $sourceFile = $this->createTestImage('jpg');
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'output.jpg';
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->setJpegQuality(85);
        $this->assertTrue($result);
        $this->assertFileExists($outputFile);
        
        @unlink($outputFile);
    }
    
    // ============================================
    // setPngQuality Tests
    // ============================================
    
    public function testSetPngQualityRequiresGdExtension(): void
    {
        if (!extension_loaded('gd')) {
            $sourceFile = $this->createTempFile();
            $imageHelper = new ImageHelper($sourceFile);
            
            $result = $imageHelper->setPngQuality();
            $this->assertFalse($result);
            $this->assertStringContainsString('GD extension', $imageHelper->error);
        } else {
            $this->markTestSkipped('GD extension is loaded');
        }
    }
    
    public function testSetPngQualityWithValidPngImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $sourceFile = $this->createTestImage('png');
        $outputFile = $this->tempDir . DIRECTORY_SEPARATOR . 'output.png';
        $imageHelper = new ImageHelper($sourceFile, $outputFile);
        
        $result = $imageHelper->setPngQuality(6);
        $this->assertTrue($result);
        $this->assertFileExists($outputFile);
        
        @unlink($outputFile);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testCopyReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->copy();
        $this->assertFalse($result);
    }
    
    public function testMoveReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->move();
        $this->assertFalse($result);
    }
    
    public function testDeleteReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->delete();
        $this->assertFalse($result);
    }
    
    public function testMoveAndEncryptReturnsFalseWhenErrorSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->error = 'Test error';
        
        $result = $imageHelper->moveAndEncrypt();
        $this->assertFalse($result);
    }
    
    // ============================================
    // Additional Coverage Tests
    // ============================================
    
    public function testConstructorWithNullOutputFileUsesSourceFile(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile, null);
        
        $this->assertEquals($sourceFile, $imageHelper->outputFile);
        $this->assertEquals($sourceFile, $imageHelper->sourceFile);
    }
    
    public function testErrorPropertyInitialization(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        
        // Error should be null for valid file
        $this->assertNull($imageHelper->error);
    }
    
    public function testSecretPropertyCanBeSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        $imageHelper->secret = 'test-secret-key-123';
        
        $this->assertEquals('test-secret-key-123', $imageHelper->secret);
    }
    
    public function testSourceFilePropertyIsSet(): void
    {
        $sourceFile = $this->createTempFile();
        $imageHelper = new ImageHelper($sourceFile);
        
        $this->assertEquals($sourceFile, $imageHelper->sourceFile);
    }
    
    public function testOutputFilePropertyIsSet(): void
    {
        $sourceFile = $this->createTempFile();
        $destFile = $this->tempDir . DIRECTORY_SEPARATOR . 'output.txt';
        $imageHelper = new ImageHelper($sourceFile, $destFile);
        
        $this->assertEquals($destFile, $imageHelper->outputFile);
    }
}


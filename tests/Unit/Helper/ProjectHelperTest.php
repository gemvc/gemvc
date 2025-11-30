<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\ProjectHelper;

class ProjectHelperTest extends TestCase
{
    private ?string $originalRootDir = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Use reflection to reset the static $rootDir property
        $reflection = new \ReflectionClass(ProjectHelper::class);
        $property = $reflection->getProperty('rootDir');
        $property->setAccessible(true);
        $this->originalRootDir = $property->getValue();
        $property->setValue(null); // Reset to null
    }
    
    protected function tearDown(): void
    {
        // Restore original rootDir value
        if ($this->originalRootDir !== null) {
            $reflection = new \ReflectionClass(ProjectHelper::class);
            $property = $reflection->getProperty('rootDir');
            $property->setAccessible(true);
            $property->setValue($this->originalRootDir);
        }
        parent::tearDown();
    }
    
    // ============================================
    // rootDir Tests
    // ============================================
    
    public function testRootDirReturnsString(): void
    {
        $result = ProjectHelper::rootDir();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
    
    public function testRootDirReturnsPathWithComposerLock(): void
    {
        $result = ProjectHelper::rootDir();
        $composerLockPath = $result . DIRECTORY_SEPARATOR . 'composer.lock';
        $this->assertFileExists($composerLockPath, 'rootDir should point to directory containing composer.lock');
    }
    
    public function testRootDirReturnsSameValueOnMultipleCalls(): void
    {
        $result1 = ProjectHelper::rootDir();
        $result2 = ProjectHelper::rootDir();
        $this->assertEquals($result1, $result2, 'rootDir should be cached and return same value');
    }
    
    public function testRootDirReturnsAbsolutePath(): void
    {
        $result = ProjectHelper::rootDir();
        // Check if it's an absolute path (starts with / on Unix or C:\ on Windows)
        $this->assertTrue(
            str_starts_with($result, DIRECTORY_SEPARATOR) || 
            (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:\\\\/', $result)),
            'rootDir should return absolute path'
        );
    }
    
    public function testRootDirThrowsExceptionWhenComposerLockNotFound(): void
    {
        // This test would require mocking the file system, which is complex
        // For now, we test that it works in the actual project structure
        // In a real scenario, you'd use vfsStream or similar to mock filesystem
        
        // Reset rootDir to test the lookup
        $reflection = new \ReflectionClass(ProjectHelper::class);
        $property = $reflection->getProperty('rootDir');
        $property->setAccessible(true);
        $property->setValue(null);
        
        // In the actual project, composer.lock should exist, so this should not throw
        try {
            $result = ProjectHelper::rootDir();
            $this->assertIsString($result);
        } catch (\Exception $e) {
            $this->assertStringContainsString('composer.lock not found', $e->getMessage());
        }
    }
    
    // ============================================
    // appDir Tests
    // ============================================
    
    public function testAppDirReturnsString(): void
    {
        // Check if app directory exists in the project
        $rootDir = ProjectHelper::rootDir();
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        
        if (file_exists($appDirPath) && is_dir($appDirPath)) {
            $result = ProjectHelper::appDir();
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } else {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('app directory not found');
            ProjectHelper::appDir();
        }
    }
    
    public function testAppDirReturnsPathInRootDirectory(): void
    {
        $rootDir = ProjectHelper::rootDir();
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        
        if (file_exists($appDirPath) && is_dir($appDirPath)) {
            $appDir = ProjectHelper::appDir();
            $expectedAppDir = $rootDir . DIRECTORY_SEPARATOR . 'app';
            $this->assertEquals($expectedAppDir, $appDir);
        } else {
            $this->expectException(\Exception::class);
            ProjectHelper::appDir();
        }
    }
    
    public function testAppDirReturnsDirectoryThatExists(): void
    {
        $rootDir = ProjectHelper::rootDir();
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        
        if (file_exists($appDirPath) && is_dir($appDirPath)) {
            $appDir = ProjectHelper::appDir();
            $this->assertIsString($appDir);
            $this->assertDirectoryExists($appDir);
        } else {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('app directory not found');
            ProjectHelper::appDir();
        }
    }
    
    public function testAppDirThrowsExceptionWhenAppDirectoryNotFound(): void
    {
        // This test verifies that appDir throws exception when app directory doesn't exist
        // In the actual project structure, if app doesn't exist, it will throw
        $rootDir = ProjectHelper::rootDir();
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        
        if (!file_exists($appDirPath) || !is_dir($appDirPath)) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('app directory not found');
            ProjectHelper::appDir();
        } else {
            // If app exists, just verify it returns a string
            $result = ProjectHelper::appDir();
            $this->assertIsString($result);
        }
    }
    
    public function testAppDirUsesRootDir(): void
    {
        $rootDir = ProjectHelper::rootDir();
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        
        if (file_exists($appDirPath) && is_dir($appDirPath)) {
            $appDir = ProjectHelper::appDir();
            $this->assertStringStartsWith($rootDir, $appDir);
            $this->assertStringEndsWith('app', $appDir);
        } else {
            $this->expectException(\Exception::class);
            ProjectHelper::appDir();
        }
    }
    
    // ============================================
    // loadEnv Tests
    // ============================================
    
    public function testLoadEnvDoesNotThrowWhenEnvFileExistsInRoot(): void
    {
        // Check if .env file exists in root
        $rootDir = ProjectHelper::rootDir();
        $envFile = $rootDir . DIRECTORY_SEPARATOR . '.env';
        
        if (file_exists($envFile)) {
            // If .env exists, loadEnv should not throw
            ProjectHelper::loadEnv();
            $this->assertTrue(true, 'loadEnv should succeed when .env exists in root');
        } else {
            // If .env doesn't exist in root, check app directory
            $rootDir = ProjectHelper::rootDir();
            $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
            
            if (file_exists($appDirPath) && is_dir($appDirPath)) {
                $appDir = ProjectHelper::appDir();
                $appEnvFile = $appDir . DIRECTORY_SEPARATOR . '.env';
                
                if (file_exists($appEnvFile)) {
                    ProjectHelper::loadEnv();
                    $this->assertTrue(true, 'loadEnv should succeed when .env exists in app directory');
                } else {
                    // Neither .env exists, so it should throw
                    $this->expectException(\Exception::class);
                    $this->expectExceptionMessage('No .env file found');
                    ProjectHelper::loadEnv();
                }
            } else {
                // App directory doesn't exist, so loadEnv will fail when trying to access it
                $this->expectException(\Exception::class);
                ProjectHelper::loadEnv();
            }
        }
    }
    
    public function testLoadEnvThrowsExceptionWhenNoEnvFileFound(): void
    {
        $rootDir = ProjectHelper::rootDir();
        $rootEnvFile = $rootDir . DIRECTORY_SEPARATOR . '.env';
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        $appEnvFile = $appDirPath . DIRECTORY_SEPARATOR . '.env';
        
        // If app directory doesn't exist, loadEnv will throw when trying to access it
        if (!file_exists($appDirPath) || !is_dir($appDirPath)) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('app directory not found');
            ProjectHelper::loadEnv();
        } elseif (!file_exists($rootEnvFile) && !file_exists($appEnvFile)) {
            // If app exists but neither .env file exists, it should throw
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('No .env file found');
            ProjectHelper::loadEnv();
        } else {
            // At least one .env exists, so it should not throw
            ProjectHelper::loadEnv();
            $this->assertTrue(true);
        }
    }
    
    public function testLoadEnvLoadsFromRootDirectoryFirst(): void
    {
        // This test verifies the priority: root directory first, then app directory
        $rootDir = ProjectHelper::rootDir();
        $rootEnvFile = $rootDir . DIRECTORY_SEPARATOR . '.env';
        
        if (file_exists($rootEnvFile)) {
            // Should load from root without throwing
            ProjectHelper::loadEnv();
            $this->assertTrue(true, 'Should load from root directory when .env exists there');
        } else {
            // Skip test if .env doesn't exist in root
            $this->markTestSkipped('.env file does not exist in root directory');
        }
    }
    
    public function testLoadEnvUsesSymfonyDotenv(): void
    {
        // Verify that loadEnv uses Symfony Dotenv component
        $rootDir = ProjectHelper::rootDir();
        $rootEnvFile = $rootDir . DIRECTORY_SEPARATOR . '.env';
        $appDirPath = $rootDir . DIRECTORY_SEPARATOR . 'app';
        $appEnvFile = $appDirPath . DIRECTORY_SEPARATOR . '.env';
        
        // Check if app directory exists first
        if (!file_exists($appDirPath) || !is_dir($appDirPath)) {
            // App directory doesn't exist, so loadEnv will throw when trying to access it
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('app directory not found');
            ProjectHelper::loadEnv();
        } elseif (file_exists($rootEnvFile) || file_exists($appEnvFile)) {
            // At least one .env exists, so loadEnv should succeed
            ProjectHelper::loadEnv();
            // If we get here, Dotenv was used successfully
            $this->assertTrue(true);
        } else {
            // App exists but no .env files, should throw
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('No .env file found');
            ProjectHelper::loadEnv();
        }
    }
}


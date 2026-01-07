<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Gemvc\CLI\AbstractInit;
use Gemvc\CLI\Commands\InitSwoole;
use Gemvc\CLI\Commands\InitApache;
use Gemvc\CLI\Commands\InitNginx;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test AbstractInit refactored methods
 * 
 * Tests the optimization where:
 * - getStartupTemplatePath() is no longer abstract but has default implementation
 * - isPackageInstalled() and installPackage() are shared methods
 */
class AbstractInitTest extends TestCase
{
    /**
     * Test that getStartupTemplatePath() is no longer abstract
     */
    public function testGetStartupTemplatePathIsNotAbstract(): void
    {
        $reflection = new ReflectionClass(AbstractInit::class);
        $method = $reflection->getMethod('getStartupTemplatePath');
        
        $this->assertFalse($method->isAbstract(), 'getStartupTemplatePath should not be abstract');
        $this->assertTrue($method->isProtected(), 'getStartupTemplatePath should be protected');
    }
    
    /**
     * Test that getStartupTemplatePath() calls findStartupPath()
     */
    public function testGetStartupTemplatePathCallsFindStartupPath(): void
    {
        // Create a mock InitSwoole instance
        $initSwoole = new InitSwoole(['--non-interactive']);
        
        // Use reflection to access protected methods
        $reflection = new ReflectionClass($initSwoole);
        
        // Get the getStartupTemplatePath method
        $getStartupTemplatePathMethod = $reflection->getMethod('getStartupTemplatePath');
        $getStartupTemplatePathMethod->setAccessible(true);
        
        // Get the findStartupPath method
        $findStartupPathMethod = $reflection->getMethod('findStartupPath');
        $findStartupPathMethod->setAccessible(true);
        
        // Call both methods
        $templatePath = $getStartupTemplatePathMethod->invoke($initSwoole);
        $startupPath = $findStartupPathMethod->invoke($initSwoole);
        
        // They should return the same value since getStartupTemplatePath() calls findStartupPath()
        $this->assertEquals($startupPath, $templatePath, 'getStartupTemplatePath should return same value as findStartupPath');
    }
    
    /**
     * Test that InitSwoole uses inherited getStartupTemplatePath()
     */
    public function testInitSwooleUsesInheritedGetStartupTemplatePath(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        
        // InitSwoole should have access to getStartupTemplatePath (inherited)
        $this->assertTrue($reflection->hasMethod('getStartupTemplatePath'), 'InitSwoole should have access to getStartupTemplatePath');
        
        $method = $reflection->getMethod('getStartupTemplatePath');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit, not InitSwoole
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'InitSwoole should use inherited getStartupTemplatePath from AbstractInit');
    }
    
    /**
     * Test that InitApache uses inherited getStartupTemplatePath()
     */
    public function testInitApacheUsesInheritedGetStartupTemplatePath(): void
    {
        $reflection = new ReflectionClass(InitApache::class);
        
        // InitApache should have access to getStartupTemplatePath (inherited)
        $this->assertTrue($reflection->hasMethod('getStartupTemplatePath'), 'InitApache should have access to getStartupTemplatePath');
        
        $method = $reflection->getMethod('getStartupTemplatePath');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit, not InitApache
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'InitApache should use inherited getStartupTemplatePath from AbstractInit');
    }
    
    /**
     * Test that InitNginx uses inherited getStartupTemplatePath()
     */
    public function testInitNginxUsesInheritedGetStartupTemplatePath(): void
    {
        $reflection = new ReflectionClass(InitNginx::class);
        
        // InitNginx should have access to getStartupTemplatePath (inherited)
        $this->assertTrue($reflection->hasMethod('getStartupTemplatePath'), 'InitNginx should have access to getStartupTemplatePath');
        
        $method = $reflection->getMethod('getStartupTemplatePath');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit, not InitNginx
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'InitNginx should use inherited getStartupTemplatePath from AbstractInit');
    }
    
    /**
     * Test that isPackageInstalled() exists in AbstractInit
     */
    public function testIsPackageInstalledExistsInAbstractInit(): void
    {
        $reflection = new ReflectionClass(AbstractInit::class);
        $this->assertTrue($reflection->hasMethod('isPackageInstalled'), 'AbstractInit should have isPackageInstalled method');
        
        $method = $reflection->getMethod('isPackageInstalled');
        $this->assertTrue($method->isProtected(), 'isPackageInstalled should be protected');
        $this->assertFalse($method->isAbstract(), 'isPackageInstalled should not be abstract');
    }
    
    /**
     * Test that installPackage() exists in AbstractInit
     */
    public function testInstallPackageExistsInAbstractInit(): void
    {
        $reflection = new ReflectionClass(AbstractInit::class);
        $this->assertTrue($reflection->hasMethod('installPackage'), 'AbstractInit should have installPackage method');
        
        $method = $reflection->getMethod('installPackage');
        $this->assertTrue($method->isProtected(), 'installPackage should be protected');
        $this->assertFalse($method->isAbstract(), 'installPackage should not be abstract');
        
        // Verify return type is bool
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'installPackage should have return type');
        $this->assertEquals('bool', $returnType->getName(), 'installPackage should return bool');
    }
    
    /**
     * Test that InitSwoole does NOT have its own isPackageInstalled()
     */
    public function testInitSwooleDoesNotHaveOwnIsPackageInstalled(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        
        // InitSwoole should have access to isPackageInstalled (inherited)
        $this->assertTrue($reflection->hasMethod('isPackageInstalled'), 'InitSwoole should have access to isPackageInstalled');
        
        $method = $reflection->getMethod('isPackageInstalled');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit, not InitSwoole
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'InitSwoole should use inherited isPackageInstalled from AbstractInit');
    }
    
    /**
     * Test that InitSwoole does NOT have its own installPackage()
     */
    public function testInitSwooleDoesNotHaveOwnInstallPackage(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        
        // InitSwoole should have access to installPackage (inherited)
        $this->assertTrue($reflection->hasMethod('installPackage'), 'InitSwoole should have access to installPackage');
        
        $method = $reflection->getMethod('installPackage');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit, not InitSwoole
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'InitSwoole should use inherited installPackage from AbstractInit');
    }
    
    /**
     * Test that InitSwoole still has installSwooleDependencies()
     */
    public function testInitSwooleHasInstallSwooleDependencies(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        $this->assertTrue($reflection->hasMethod('installSwooleDependencies'), 'InitSwoole should have installSwooleDependencies method');
        
        $method = $reflection->getMethod('installSwooleDependencies');
        $this->assertTrue($method->isPrivate(), 'installSwooleDependencies should be private');
        
        // Verify it's declared in InitSwoole, not inherited
        $this->assertEquals(InitSwoole::class, $method->getDeclaringClass()->getName(), 'installSwooleDependencies should be declared in InitSwoole');
    }
    
    /**
     * Test isPackageInstalled() with existing package
     */
    public function testIsPackageInstalledReturnsTrueForInstalledPackage(): void
    {
        // Create InitSwoole instance
        $initSwoole = new InitSwoole(['--non-interactive']);
        
        $reflection = new ReflectionClass($initSwoole);
        $method = $reflection->getMethod('isPackageInstalled');
        $method->setAccessible(true);
        
        // Check if composer.lock exists
        $composerLockFile = getcwd() . '/composer.lock';
        if (!file_exists($composerLockFile)) {
            $this->markTestSkipped('composer.lock not found, cannot test isPackageInstalled');
            return;
        }
        
        // Try to check for a package that should exist (like the framework itself)
        // We'll check for a common package that might be installed
        $lockContent = file_get_contents($composerLockFile);
        if ($lockContent === false) {
            $this->markTestSkipped('Could not read composer.lock');
            return;
        }
        
        // Extract first package name from composer.lock for testing
        if (preg_match('/"name":\s*"([^"]+)"/', $lockContent, $matches)) {
            $packageName = $matches[1];
            $result = $method->invoke($initSwoole, $packageName);
            $this->assertTrue($result, "isPackageInstalled should return true for installed package: {$packageName}");
        } else {
            $this->markTestSkipped('Could not find package name in composer.lock');
        }
    }
    
    /**
     * Test isPackageInstalled() with non-existent package
     */
    public function testIsPackageInstalledReturnsFalseForNonExistentPackage(): void
    {
        // Create InitSwoole instance
        $initSwoole = new InitSwoole(['--non-interactive']);
        
        $reflection = new ReflectionClass($initSwoole);
        $method = $reflection->getMethod('isPackageInstalled');
        $method->setAccessible(true);
        
        // Check for a package that definitely doesn't exist
        $result = $method->invoke($initSwoole, 'non-existent/package-name-that-will-never-exist-12345');
        $this->assertFalse($result, 'isPackageInstalled should return false for non-existent package');
    }
    
    /**
     * Test isPackageInstalled() when composer.lock doesn't exist
     */
    public function testIsPackageInstalledReturnsFalseWhenComposerLockMissing(): void
    {
        // Create InitSwoole instance
        $initSwoole = new InitSwoole(['--non-interactive']);
        
        $reflection = new ReflectionClass($initSwoole);
        $method = $reflection->getMethod('isPackageInstalled');
        $method->setAccessible(true);
        
        // Temporarily change directory to a location without composer.lock
        $originalCwd = getcwd();
        $tempDir = sys_get_temp_dir() . '/gemvc-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        chdir($tempDir);
        
        try {
            $result = $method->invoke($initSwoole, 'any/package');
            $this->assertFalse($result, 'isPackageInstalled should return false when composer.lock does not exist');
        } finally {
            chdir($originalCwd);
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }
    
    /**
     * Test that InitSwoole constructor is properly positioned
     */
    public function testInitSwooleConstructorPosition(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE);
        
        // Find constructor
        $constructorIndex = -1;
        foreach ($methods as $index => $method) {
            if ($method->getName() === '__construct') {
                $constructorIndex = $index;
                break;
            }
        }
        
        $this->assertNotEquals(-1, $constructorIndex, 'Constructor should exist in InitSwoole');
        
        // Constructor should be early in the class (after constants, before most methods)
        // We'll just verify it exists and is callable
        $constructor = $reflection->getMethod('__construct');
        $this->assertTrue($constructor->isPublic(), 'Constructor should be public');
    }
    
    /**
     * Test that InitSwoole can access inherited package methods
     */
    public function testInitSwooleCanAccessInheritedPackageMethods(): void
    {
        $initSwoole = new InitSwoole(['--non-interactive']);
        $reflection = new ReflectionClass($initSwoole);
        
        // Verify isPackageInstalled is accessible (inherited from AbstractInit)
        $isPackageInstalledMethod = $reflection->getMethod('isPackageInstalled');
        $isPackageInstalledMethod->setAccessible(true);
        $this->assertEquals(AbstractInit::class, $isPackageInstalledMethod->getDeclaringClass()->getName(), 'isPackageInstalled should be inherited from AbstractInit');
        
        // Verify installPackage is accessible (inherited from AbstractInit)
        $installPackageMethod = $reflection->getMethod('installPackage');
        $installPackageMethod->setAccessible(true);
        $this->assertEquals(AbstractInit::class, $installPackageMethod->getDeclaringClass()->getName(), 'installPackage should be inherited from AbstractInit');
    }
}


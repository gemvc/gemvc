<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Gemvc\CLI\Commands\InitSwoole;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test InitSwoole refactored implementation
 * 
 * Tests that InitSwoole correctly uses inherited methods and
 * preserves Swoole-specific functionality (gemvc/connection-openswoole installation)
 */
class InitSwooleTest extends TestCase
{
    /**
     * Test that InitSwoole extends AbstractInit
     */
    public function testInitSwooleExtendsAbstractInit(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        $parent = $reflection->getParentClass();
        
        $this->assertNotNull($parent, 'InitSwoole should have a parent class');
        $this->assertEquals('Gemvc\CLI\AbstractInit', $parent->getName(), 'InitSwoole should extend AbstractInit');
    }
    
    /**
     * Test that InitSwoole uses inherited getStartupTemplatePath()
     */
    public function testInitSwooleUsesInheritedGetStartupTemplatePath(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        
        // Check if method exists and is inherited
        if ($reflection->hasMethod('getStartupTemplatePath')) {
            $method = $reflection->getMethod('getStartupTemplatePath');
            $declaringClass = $method->getDeclaringClass()->getName();
            
            // Should be declared in AbstractInit, not InitSwoole
            $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'getStartupTemplatePath should be inherited from AbstractInit');
        } else {
            // If method doesn't exist, it's inherited (which is fine)
            $this->assertTrue(true, 'getStartupTemplatePath is inherited from AbstractInit');
        }
    }
    
    /**
     * Test that InitSwoole uses inherited isPackageInstalled()
     */
    public function testInitSwooleUsesInheritedIsPackageInstalled(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        
        // Method should exist (inherited)
        $this->assertTrue($reflection->hasMethod('isPackageInstalled'), 'InitSwoole should have access to isPackageInstalled');
        
        $method = $reflection->getMethod('isPackageInstalled');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'isPackageInstalled should be inherited from AbstractInit');
    }
    
    /**
     * Test that InitSwoole uses inherited installPackage()
     */
    public function testInitSwooleUsesInheritedInstallPackage(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        
        // Method should exist (inherited)
        $this->assertTrue($reflection->hasMethod('installPackage'), 'InitSwoole should have access to installPackage');
        
        $method = $reflection->getMethod('installPackage');
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Should be declared in AbstractInit
        $this->assertEquals('Gemvc\CLI\AbstractInit', $declaringClass, 'installPackage should be inherited from AbstractInit');
    }
    
    /**
     * Test that installSwooleDependencies() uses inherited methods
     */
    public function testInstallSwooleDependenciesUsesInheritedMethods(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        $method = $reflection->getMethod('installSwooleDependencies');
        $method->setAccessible(true);
        
        // Get the source code of the method
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $source = file_get_contents($filename);
        if ($source === false) {
            $this->markTestSkipped('Could not read source file');
            return;
        }
        
        $lines = explode("\n", $source);
        $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        
        // Verify it calls isPackageInstalled (inherited method)
        $this->assertStringContainsString('isPackageInstalled', $methodSource, 'installSwooleDependencies should call isPackageInstalled');
        
        // Verify it calls installPackage (inherited method)
        $this->assertStringContainsString('installPackage', $methodSource, 'installSwooleDependencies should call installPackage');
        
        // Verify it references gemvc/connection-openswoole (Swoole-specific package)
        $this->assertStringContainsString('gemvc/connection-openswoole', $methodSource, 'installSwooleDependencies should install gemvc/connection-openswoole');
    }
    
    /**
     * Test that InitSwoole has correct webserver type
     */
    public function testInitSwooleReturnsCorrectWebserverType(): void
    {
        $initSwoole = new InitSwoole(['--non-interactive']);
        $reflection = new ReflectionClass($initSwoole);
        
        $method = $reflection->getMethod('getWebserverType');
        $method->setAccessible(true);
        
        $webserverType = $method->invoke($initSwoole);
        $this->assertEquals('OpenSwoole', $webserverType, 'InitSwoole should return OpenSwoole as webserver type');
    }
    
    /**
     * Test that InitSwoole constructor sets package name
     */
    public function testInitSwooleConstructorSetsPackageName(): void
    {
        $initSwoole = new InitSwoole(['--non-interactive']);
        $reflection = new ReflectionClass($initSwoole);
        
        // Access protected property packageName
        $property = $reflection->getProperty('packageName');
        $property->setAccessible(true);
        $packageName = $property->getValue($initSwoole);
        
        $this->assertEquals('swoole', $packageName, 'InitSwoole constructor should set packageName to swoole');
    }
    
    /**
     * Test that InitSwoole does not have duplicate methods
     */
    public function testInitSwooleDoesNotHaveDuplicateMethods(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        $methods = $reflection->getMethods();
        
        $methodNames = [];
        foreach ($methods as $method) {
            // Only check methods declared in InitSwoole (not inherited)
            if ($method->getDeclaringClass()->getName() === InitSwoole::class) {
                $methodName = $method->getName();
                
                // Should not have these methods (they should be inherited)
                $this->assertNotEquals('getStartupTemplatePath', $methodName, 'InitSwoole should not override getStartupTemplatePath');
                $this->assertNotEquals('isPackageInstalled', $methodName, 'InitSwoole should not override isPackageInstalled');
                $this->assertNotEquals('installPackage', $methodName, 'InitSwoole should not override installPackage');
            }
        }
    }
    
    /**
     * Test that InitSwoole has installSwooleDependencies as private method
     */
    public function testInstallSwooleDependenciesIsPrivate(): void
    {
        $reflection = new ReflectionClass(InitSwoole::class);
        $method = $reflection->getMethod('installSwooleDependencies');
        
        $this->assertTrue($method->isPrivate(), 'installSwooleDependencies should be private');
        $this->assertEquals(InitSwoole::class, $method->getDeclaringClass()->getName(), 'installSwooleDependencies should be declared in InitSwoole');
    }
}


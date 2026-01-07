<?php

namespace Gemvc\CLI;

class InstallationTest {
    /**
     * Verify GEMVC CLI installation and core components
     * 
     * @return bool
     * @throws \RuntimeException
     */
    public static function verify(): bool {
        // Verify autoloader
        if (!class_exists('Gemvc\CLI\Command')) {
            throw new \RuntimeException('CLI autoloader not working');
        }

        // Verify core command classes
        $requiredCommands = [
            'Gemvc\CLI\Commands\CreateService',
            'Gemvc\CLI\Commands\InitProject',
            'Gemvc\CLI\Commands\InitSwoole',
            'Gemvc\CLI\Commands\InitApache',
            'Gemvc\CLI\Commands\InitNginx',
        ];
        
        foreach ($requiredCommands as $commandClass) {
            if (!class_exists($commandClass)) {
                throw new \RuntimeException("Command class not found: {$commandClass}");
            }
        }

        // Verify AbstractInit class and centralized appIndex.php method
        if (!class_exists('Gemvc\CLI\AbstractInit')) {
            throw new \RuntimeException('AbstractInit class not found');
        }
        
        if (!method_exists('Gemvc\CLI\AbstractInit', 'copyAppIndexFile')) {
            throw new \RuntimeException('copyAppIndexFile() method not found in AbstractInit - centralized appIndex.php copying may not work');
        }
        
        // Verify refactored AbstractInit methods (shared functionality)
        $abstractInitReflection = new \ReflectionClass('Gemvc\CLI\AbstractInit');
        
        // Verify getStartupTemplatePath() is not abstract (has default implementation)
        if (!$abstractInitReflection->hasMethod('getStartupTemplatePath')) {
            throw new \RuntimeException('getStartupTemplatePath() method not found in AbstractInit');
        }
        $getStartupMethod = $abstractInitReflection->getMethod('getStartupTemplatePath');
        if ($getStartupMethod->isAbstract()) {
            throw new \RuntimeException('getStartupTemplatePath() should not be abstract - it should have a default implementation');
        }
        if (!$getStartupMethod->isProtected()) {
            throw new \RuntimeException('getStartupTemplatePath() should be protected');
        }
        
        // Verify isPackageInstalled() exists in AbstractInit
        if (!$abstractInitReflection->hasMethod('isPackageInstalled')) {
            throw new \RuntimeException('isPackageInstalled() method not found in AbstractInit - shared package checking may not work');
        }
        $isPackageMethod = $abstractInitReflection->getMethod('isPackageInstalled');
        if (!$isPackageMethod->isProtected()) {
            throw new \RuntimeException('isPackageInstalled() should be protected');
        }
        
        // Verify installPackage() exists in AbstractInit
        if (!$abstractInitReflection->hasMethod('installPackage')) {
            throw new \RuntimeException('installPackage() method not found in AbstractInit - shared package installation may not work');
        }
        $installMethod = $abstractInitReflection->getMethod('installPackage');
        if (!$installMethod->isProtected()) {
            throw new \RuntimeException('installPackage() should be protected');
        }
        
        // Verify webserver init classes properly inherit methods (no duplicates)
        $webserverClasses = [
            'Gemvc\CLI\Commands\InitSwoole',
            'Gemvc\CLI\Commands\InitApache',
            'Gemvc\CLI\Commands\InitNginx',
        ];
        
        foreach ($webserverClasses as $webserverClass) {
            if (!class_exists($webserverClass)) {
                continue; // Already checked above
            }
            
            $webserverReflection = new \ReflectionClass($webserverClass);
            
            // Verify getStartupTemplatePath() is inherited (not overridden unless necessary)
            if ($webserverReflection->hasMethod('getStartupTemplatePath')) {
                $method = $webserverReflection->getMethod('getStartupTemplatePath');
                $declaringClass = $method->getDeclaringClass()->getName();
                if ($declaringClass !== 'Gemvc\CLI\AbstractInit') {
                    // If overridden, it should still be protected
                    if (!$method->isProtected()) {
                        throw new \RuntimeException("getStartupTemplatePath() in {$webserverClass} should be protected");
                    }
                }
            }
            
            // Verify isPackageInstalled() is inherited (should not be overridden)
            if ($webserverReflection->hasMethod('isPackageInstalled')) {
                $method = $webserverReflection->getMethod('isPackageInstalled');
                $declaringClass = $method->getDeclaringClass()->getName();
                if ($declaringClass !== 'Gemvc\CLI\AbstractInit') {
                    throw new \RuntimeException("isPackageInstalled() should not be overridden in {$webserverClass} - it should be inherited from AbstractInit");
                }
            }
            
            // Verify installPackage() is inherited (should not be overridden)
            if ($webserverReflection->hasMethod('installPackage')) {
                $method = $webserverReflection->getMethod('installPackage');
                $declaringClass = $method->getDeclaringClass()->getName();
                if ($declaringClass !== 'Gemvc\CLI\AbstractInit') {
                    throw new \RuntimeException("installPackage() should not be overridden in {$webserverClass} - it should be inherited from AbstractInit");
                }
            }
        }

        // Verify startup files structure
        $packagePath = dirname(dirname(__DIR__));
        $commonAppIndexPath = $packagePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'appIndex.php';
        
        if (!file_exists($commonAppIndexPath)) {
            throw new \RuntimeException("appIndex.php not found in common directory: {$commonAppIndexPath}");
        }

        // Verify no duplicate AppIndex.php files exist in webserver-specific directories
        $webserverDirs = ['apache', 'nginx', 'swoole'];
        foreach ($webserverDirs as $webserver) {
            $duplicatePath = $packagePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup' . DIRECTORY_SEPARATOR . $webserver . DIRECTORY_SEPARATOR . 'AppIndex.php';
            if (file_exists($duplicatePath)) {
                throw new \RuntimeException("Duplicate AppIndex.php found in {$webserver} directory - should be removed (centralized in common/)");
            }
        }

        // Verify directory permissions
        $dirs = ['app/api', 'app/controller', 'app/model', 'app/table'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Cannot create directory: {$dir}");
            }
        }

        return true;
    }
} 
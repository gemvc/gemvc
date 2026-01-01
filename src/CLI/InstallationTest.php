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
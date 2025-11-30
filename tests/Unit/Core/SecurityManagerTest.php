<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gemvc\Core\SecurityManager;

class SecurityManagerTest extends TestCase
{
    private SecurityManager $securityManager;
    private string $originalErrorLog;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Suppress error_log output during tests
        $this->originalErrorLog = ini_get('error_log');
        // Use 'nul' on Windows, '/dev/null' on Unix
        $nullDevice = (PHP_OS_FAMILY === 'Windows') ? 'nul' : '/dev/null';
        ini_set('error_log', $nullDevice);
        $this->securityManager = new SecurityManager();
    }
    
    protected function tearDown(): void
    {
        // Restore original error_log setting
        if ($this->originalErrorLog !== '') {
            ini_set('error_log', $this->originalErrorLog);
        } else {
            ini_restore('error_log');
        }
        parent::tearDown();
    }
    
    public function testBlocksAppDirectoryAccess(): void
    {
        $result = $this->securityManager->isRequestAllowed('/app/api/User.php');
        $this->assertFalse($result, 'Should block access to /app directory');
    }
    
    public function testBlocksVendorDirectoryAccess(): void
    {
        $result = $this->securityManager->isRequestAllowed('/vendor/autoload.php');
        $this->assertFalse($result, 'Should block access to /vendor directory');
    }
    
    public function testBlocksEnvFileAccess(): void
    {
        $result = $this->securityManager->isRequestAllowed('/.env');
        $this->assertFalse($result, 'Should block access to .env file');
    }
    
    public function testBlocksGitDirectoryAccess(): void
    {
        $result = $this->securityManager->isRequestAllowed('/.git/config');
        $this->assertFalse($result, 'Should block access to .git directory');
    }
    
    public function testAllowsApiEndpoints(): void
    {
        $result = $this->securityManager->isRequestAllowed('/api/User/create');
        $this->assertTrue($result, 'Should allow API endpoints');
    }
    
    public function testAllowsApiIndex(): void
    {
        $result = $this->securityManager->isRequestAllowed('/api');
        $this->assertTrue($result, 'Should allow /api endpoint');
    }
    
    public function testBlocksPhpFileExtension(): void
    {
        $result = $this->securityManager->isRequestAllowed('/somefile.php');
        $this->assertFalse($result, 'Should block direct PHP file access');
    }
    
    public function testBlocksConfigFileExtensions(): void
    {
        $extensions = ['.env', '.ini', '.conf', '.config', '.log', '.sql'];
        
        foreach ($extensions as $ext) {
            $result = $this->securityManager->isRequestAllowed('/file' . $ext);
            $this->assertFalse($result, "Should block access to {$ext} files");
        }
    }
    
    public function testAllowsPublicAssets(): void
    {
        $allowedPaths = [
            '/api/User/create',
            '/api/Product/list',
            '/api/index/document'
        ];
        
        foreach ($allowedPaths as $path) {
            $result = $this->securityManager->isRequestAllowed($path);
            $this->assertTrue($result, "Should allow path: {$path}");
        }
    }
    
    public function testAllowsRootPath(): void
    {
        $this->assertTrue($this->securityManager->isRequestAllowed(''));
        $this->assertTrue($this->securityManager->isRequestAllowed('/'));
    }
    
    public function testRemovesQueryStringFromPath(): void
    {
        // Path with query string should be processed correctly
        $this->assertFalse($this->securityManager->isRequestAllowed('/app/test.php?id=123'));
        $this->assertTrue($this->securityManager->isRequestAllowed('/api/User/create?id=123'));
    }
    
    public function testBlocksAllDefaultPaths(): void
    {
        $blockedPaths = [
            '/app',
            '/vendor',
            '/bin',
            '/templates',
            '/config',
            '/logs',
            '/storage',
            '/.env',
            '/.git'
        ];
        
        foreach ($blockedPaths as $path) {
            $result = $this->securityManager->isRequestAllowed($path);
            $this->assertFalse($result, "Should block path: {$path}");
        }
    }
    
    public function testBlocksAllDefaultExtensions(): void
    {
        $blockedExtensions = [
            '.php', '.env', '.ini', '.conf', '.config',
            '.log', '.sql', '.db', '.sqlite', '.md',
            '.txt', '.json', '.xml', '.yml', '.yaml'
        ];
        
        foreach ($blockedExtensions as $ext) {
            $result = $this->securityManager->isRequestAllowed('/file' . $ext);
            $this->assertFalse($result, "Should block extension: {$ext}");
        }
    }
    
    public function testExtensionCaseInsensitive(): void
    {
        $this->assertFalse($this->securityManager->isRequestAllowed('/file.PHP'));
        $this->assertFalse($this->securityManager->isRequestAllowed('/file.ENV'));
        $this->assertFalse($this->securityManager->isRequestAllowed('/file.Json'));
    }
    
    public function testGetBlockedPaths(): void
    {
        $blockedPaths = $this->securityManager->getBlockedPaths();
        
        $this->assertIsArray($blockedPaths);
        $this->assertContains('/app', $blockedPaths);
        $this->assertContains('/vendor', $blockedPaths);
        $this->assertContains('/.env', $blockedPaths);
        $this->assertContains('/.git', $blockedPaths);
    }
    
    public function testGetBlockedExtensions(): void
    {
        $blockedExtensions = $this->securityManager->getBlockedExtensions();
        
        $this->assertIsArray($blockedExtensions);
        $this->assertContains('.php', $blockedExtensions);
        $this->assertContains('.env', $blockedExtensions);
        $this->assertContains('.json', $blockedExtensions);
    }
    
    public function testAddBlockedPath(): void
    {
        $newPath = '/custom-blocked-path';
        
        // Initially should allow this path
        $this->assertTrue($this->securityManager->isRequestAllowed($newPath));
        
        // Add the path
        $this->securityManager->addBlockedPath($newPath);
        
        // Now should block it
        $this->assertFalse($this->securityManager->isRequestAllowed($newPath));
        
        // Verify it's in the blocked paths list
        $blockedPaths = $this->securityManager->getBlockedPaths();
        $this->assertContains($newPath, $blockedPaths);
    }
    
    public function testAddBlockedPathDoesNotDuplicate(): void
    {
        $newPath = '/test-path';
        
        // Add twice
        $this->securityManager->addBlockedPath($newPath);
        $this->securityManager->addBlockedPath($newPath);
        
        // Should only appear once
        $blockedPaths = $this->securityManager->getBlockedPaths();
        $count = 0;
        foreach ($blockedPaths as $path) {
            if ($path === $newPath) {
                $count++;
            }
        }
        $this->assertEquals(1, $count, 'Path should only appear once');
    }
    
    public function testAddBlockedExtension(): void
    {
        $newExtension = '.custom';
        
        // Initially should allow this extension
        $this->assertTrue($this->securityManager->isRequestAllowed('/file' . $newExtension));
        
        // Add the extension
        $this->securityManager->addBlockedExtension($newExtension);
        
        // Now should block it
        $this->assertFalse($this->securityManager->isRequestAllowed('/file' . $newExtension));
        
        // Verify it's in the blocked extensions list
        $blockedExtensions = $this->securityManager->getBlockedExtensions();
        $this->assertContains($newExtension, $blockedExtensions);
    }
    
    public function testAddBlockedExtensionDoesNotDuplicate(): void
    {
        $newExtension = '.test';
        
        // Add twice
        $this->securityManager->addBlockedExtension($newExtension);
        $this->securityManager->addBlockedExtension($newExtension);
        
        // Should only appear once
        $blockedExtensions = $this->securityManager->getBlockedExtensions();
        $count = 0;
        foreach ($blockedExtensions as $ext) {
            if ($ext === $newExtension) {
                $count++;
            }
        }
        $this->assertEquals(1, $count, 'Extension should only appear once');
    }
    
    public function testSendSecurityResponse(): void
    {
        // Create a mock response object
        $mockResponse = new class {
            public ?int $statusCode = null;
            public array $headers = [];
            public ?string $body = null;
            
            public function status(int $code): void
            {
                $this->statusCode = $code;
            }
            
            public function header(string $name, string $value): void
            {
                $this->headers[$name] = $value;
            }
            
            public function end(string $data): void
            {
                $this->body = $data;
            }
        };
        
        $this->securityManager->sendSecurityResponse($mockResponse);
        
        $this->assertEquals(403, $mockResponse->statusCode);
        $this->assertEquals('application/json', $mockResponse->headers['Content-Type']);
        $this->assertNotNull($mockResponse->body);
        
        $decoded = json_decode($mockResponse->body, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('Access Denied', $decoded['error']);
        $this->assertEquals('Direct file access is not permitted', $decoded['message']);
    }
    
    public function testPathMatchingIsPrefixBased(): void
    {
        // Should block any path starting with blocked path
        $this->assertFalse($this->securityManager->isRequestAllowed('/app'));
        $this->assertFalse($this->securityManager->isRequestAllowed('/app/'));
        $this->assertFalse($this->securityManager->isRequestAllowed('/app/api'));
        $this->assertFalse($this->securityManager->isRequestAllowed('/app/api/User.php'));
        $this->assertFalse($this->securityManager->isRequestAllowed('/app/subdirectory/file'));
    }
    
    public function testPathDoesNotMatchWhenNotPrefix(): void
    {
        // Paths containing but not starting with blocked path should be allowed
        $this->assertTrue($this->securityManager->isRequestAllowed('/myapp'));
        $this->assertTrue($this->securityManager->isRequestAllowed('/api/app'));
        $this->assertTrue($this->securityManager->isRequestAllowed('/test/app'));
    }
    
    public function testNormalizesPathByRemovingTrailingSlash(): void
    {
        // Both should be treated the same
        $this->assertEquals(
            $this->securityManager->isRequestAllowed('/app'),
            $this->securityManager->isRequestAllowed('/app/')
        );
        
        $this->assertEquals(
            $this->securityManager->isRequestAllowed('/api'),
            $this->securityManager->isRequestAllowed('/api/')
        );
    }
}


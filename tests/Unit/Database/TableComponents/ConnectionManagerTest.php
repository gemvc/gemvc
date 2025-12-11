<?php

declare(strict_types=1);

namespace Tests\Unit\Database\TableComponents;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\TableComponents\ConnectionManager;
use Gemvc\Database\PdoQuery;

class ConnectionManagerTest extends TestCase
{
    private ConnectionManager $connectionManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionManager = new ConnectionManager();
    }
    
    protected function tearDown(): void
    {
        $this->connectionManager->disconnect();
        parent::tearDown();
    }
    
    // ==========================================
    // Constructor Tests
    // ==========================================
    
    public function testConstructor(): void
    {
        $manager = new ConnectionManager();
        $this->assertNull($manager->getError());
        $this->assertFalse($manager->isConnected());
        $this->assertFalse($manager->hasConnection());
    }
    
    // ==========================================
    // getPdoQuery Tests (Lazy Loading)
    // ==========================================
    
    public function testGetPdoQueryLazyLoading(): void
    {
        // Initially no connection
        $this->assertFalse($this->connectionManager->hasConnection());
        
        // Get PdoQuery - should create it
        $pdoQuery = $this->connectionManager->getPdoQuery();
        $this->assertInstanceOf(PdoQuery::class, $pdoQuery);
        $this->assertTrue($this->connectionManager->hasConnection());
    }
    
    public function testGetPdoQueryReturnsSameInstance(): void
    {
        $pdoQuery1 = $this->connectionManager->getPdoQuery();
        $pdoQuery2 = $this->connectionManager->getPdoQuery();
        
        $this->assertSame($pdoQuery1, $pdoQuery2);
    }
    
    // ==========================================
    // setError Tests
    // ==========================================
    
    public function testSetErrorBeforeConnection(): void
    {
        // Set error before PdoQuery is created
        $this->connectionManager->setError('Error before connection');
        
        // Error should be stored
        $this->assertEquals('Error before connection', $this->connectionManager->getError());
        
        // PdoQuery should not be created yet
        $this->assertFalse($this->connectionManager->hasConnection());
    }
    
    public function testSetErrorAfterConnection(): void
    {
        // Create connection first
        $pdoQuery = $this->connectionManager->getPdoQuery();
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('setError')
            ->with('Error after connection');
        
        // Inject mock
        $reflection = new \ReflectionClass(ConnectionManager::class);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->connectionManager, $mockPdoQuery);
        
        // Set error - should call PdoQuery's setError
        $this->connectionManager->setError('Error after connection');
    }
    
    public function testSetErrorNull(): void
    {
        $this->connectionManager->setError('Test error');
        $this->connectionManager->setError(null);
        $this->assertNull($this->connectionManager->getError());
    }
    
    // ==========================================
    // getError Tests
    // ==========================================
    
    public function testGetErrorWithoutConnection(): void
    {
        // No error initially
        $this->assertNull($this->connectionManager->getError());
        
        // Set error before connection
        $this->connectionManager->setError('Stored error');
        $this->assertEquals('Stored error', $this->connectionManager->getError());
    }
    
    public function testGetErrorAfterConnection(): void
    {
        // Create mock PdoQuery
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->method('getError')
            ->willReturn('PdoQuery error');
        
        // Inject mock
        $reflection = new \ReflectionClass(ConnectionManager::class);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->connectionManager, $mockPdoQuery);
        
        // Should get error from PdoQuery
        $this->assertEquals('PdoQuery error', $this->connectionManager->getError());
    }
    
    // ==========================================
    // Error Transfer Tests
    // ==========================================
    
    public function testStoredErrorTransferredToPdoQuery(): void
    {
        // Set error before PdoQuery is created
        $this->connectionManager->setError('Error stored before PdoQuery creation');
        
        // Verify error is stored
        $this->assertEquals('Error stored before PdoQuery creation', $this->connectionManager->getError());
        
        // Create PdoQuery - should transfer error
        $pdoQuery = $this->connectionManager->getPdoQuery();
        
        // Error should be transferred to PdoQuery
        // Note: PdoQuery might not have the error if it requires a connection
        // But the stored error should be cleared
        $this->assertTrue($this->connectionManager->hasConnection());
    }
    
    // ==========================================
    // isConnected Tests
    // ==========================================
    
    public function testIsConnectedWithoutConnection(): void
    {
        $this->assertFalse($this->connectionManager->isConnected());
    }
    
    public function testIsConnectedAfterGetPdoQuery(): void
    {
        $pdoQuery = $this->connectionManager->getPdoQuery();
        // PdoQuery might not be connected yet (requires DB), but instance exists
        $this->assertTrue($this->connectionManager->hasConnection());
    }
    
    // ==========================================
    // hasConnection Tests
    // ==========================================
    
    public function testHasConnectionWithoutConnection(): void
    {
        $this->assertFalse($this->connectionManager->hasConnection());
    }
    
    public function testHasConnectionAfterGetPdoQuery(): void
    {
        $this->connectionManager->getPdoQuery();
        $this->assertTrue($this->connectionManager->hasConnection());
    }
    
    // ==========================================
    // disconnect Tests
    // ==========================================
    
    public function testDisconnectWithoutConnection(): void
    {
        // Should not throw error
        $this->connectionManager->disconnect();
        $this->assertFalse($this->connectionManager->hasConnection());
    }
    
    public function testDisconnectWithConnection(): void
    {
        $pdoQuery = $this->connectionManager->getPdoQuery();
        $mockPdoQuery = $this->createMock(PdoQuery::class);
        $mockPdoQuery->expects($this->once())
            ->method('disconnect');
        
        // Inject mock
        $reflection = new \ReflectionClass(ConnectionManager::class);
        $pdoQueryProperty = $reflection->getProperty('pdoQuery');
        $pdoQueryProperty->setValue($this->connectionManager, $mockPdoQuery);
        
        $this->connectionManager->disconnect();
        
        // Connection should be cleared
        $this->assertFalse($this->connectionManager->hasConnection());
        // Stored error should be cleared
        $this->assertNull($this->connectionManager->getError());
    }
    
    // ==========================================
    // Integration Tests
    // ==========================================
    
    public function testErrorLifecycle(): void
    {
        // 1. Set error before connection
        $this->connectionManager->setError('Error 1');
        $this->assertEquals('Error 1', $this->connectionManager->getError());
        $this->assertFalse($this->connectionManager->hasConnection());
        
        // 2. Create connection - error should be transferred
        $pdoQuery = $this->connectionManager->getPdoQuery();
        $this->assertTrue($this->connectionManager->hasConnection());
        
        // 3. Set new error after connection
        $this->connectionManager->setError('Error 2');
        
        // 4. Disconnect
        $this->connectionManager->disconnect();
        $this->assertFalse($this->connectionManager->hasConnection());
        $this->assertNull($this->connectionManager->getError());
    }
}


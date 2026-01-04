<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gemvc\Database\UniversalQueryExecuter;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;
use Gemvc\Http\Request;
use Gemvc\Http\ApacheRequest;
use Gemvc\Core\Apm\ApmInterface;
use PDO;
use PDOStatement;
use PDOException;

/**
 * Tests for UniversalQueryExecuter APM integration
 */
class UniversalQueryExecuterApmTest extends TestCase
{
    /** @var MockObject&PDO|null */
    private $mockPdo = null;
    /** @var MockObject&ConnectionInterface|null */
    private $mockConnection = null;
    /** @var MockObject&ConnectionManagerInterface|null */
    private $mockDbManager = null;
    private Request $request;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        
        // Create mock PDO
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockPdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');
        
        // Create mock ConnectionInterface
        $this->mockConnection = $this->createMock(ConnectionInterface::class);
        $this->mockConnection->method('getConnection')
            ->willReturn($this->mockPdo);
        
        // Create mock database manager
        $this->mockDbManager = $this->createMock(ConnectionManagerInterface::class);
        $this->mockDbManager->method('getConnection')
            ->willReturn($this->mockConnection);
        $this->mockDbManager->method('getError')
            ->willReturn(null);
        
        // Create Request
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/Test';
        
        $ar = new ApacheRequest();
        $this->request = $ar->request;
        
        // Set up environment for APM tracing
        $_ENV['APM_TRACE_DB_QUERY'] = '1';
    }
    
    protected function tearDown(): void
    {
        unset($_ENV['APM_TRACE_DB_QUERY']);
        parent::tearDown();
    }
    
    /**
     * Create executer with Request
     */
    private function createExecuterWithRequest(?Request $request = null): UniversalQueryExecuter
    {
        // Create executer with Request
        $executer = new UniversalQueryExecuter($request);
        
        // Use reflection to inject mock manager (since DatabaseManagerFactory is static)
        $reflection = new \ReflectionClass(UniversalQueryExecuter::class);
        $property = $reflection->getProperty('dbManager');
        $property->setAccessible(true);
        $property->setValue($executer, $this->mockDbManager);
        
        return $executer;
    }
    
    public function testConstructorAcceptsRequest(): void
    {
        $executer = $this->createExecuterWithRequest($this->request);
        
        // Verify executer was created
        $this->assertInstanceOf(UniversalQueryExecuter::class, $executer);
    }
    
    public function testConstructorAcceptsNullRequest(): void
    {
        $executer = $this->createExecuterWithRequest(null);
        
        // Verify executer was created
        $this->assertInstanceOf(UniversalQueryExecuter::class, $executer);
    }
    
    public function testExecuteWithRequestAndApmTracing(): void
    {
        // Create mock APM
        $mockApm = $this->createMock(ApmInterface::class);
        $mockApm->method('isEnabled')->willReturn(true);
        
        // Check if shouldTraceDbQuery exists (TraceKitModel has it, but interface might not)
        if (method_exists($mockApm, 'shouldTraceDbQuery')) {
            $mockApm->method('shouldTraceDbQuery')->willReturn(true);
        }
        
        // Set APM on request
        $this->request->apm = $mockApm;
        
        // Create executer with Request
        $executer = $this->createExecuterWithRequest($this->request);
        
        // Create mock statement
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('rowCount')->willReturn(1);
        
        $this->mockPdo->method('prepare')
            ->with('SELECT * FROM users WHERE id = ?')
            ->willReturn($mockStatement);
        
        // Set up APM expectations
        $mockApm->expects($this->once())
            ->method('startSpan')
            ->with(
                'database-query',
                $this->callback(function ($attributes) {
                    return isset($attributes['db.system']) &&
                           isset($attributes['db.operation']) &&
                           isset($attributes['db.statement']) &&
                           $attributes['db.operation'] === 'SELECT';
                }),
                ApmInterface::SPAN_KIND_CLIENT
            )
            ->willReturn(['span_id' => 'test-span']);
        
        $mockApm->expects($this->once())
            ->method('endSpan')
            ->with(
                ['span_id' => 'test-span'],
                $this->callback(function ($attributes) {
                    return isset($attributes['db.rows_affected']) &&
                           isset($attributes['db.execution_time_ms']);
                }),
                ApmInterface::STATUS_OK
            );
        
        // Execute query
        $executer->query('SELECT * FROM users WHERE id = ?');
        $executer->bind('id', 1);
        $result = $executer->execute();
        
        $this->assertTrue($result);
    }
    
    public function testExecuteWithRequestButApmDisabled(): void
    {
        // Create mock APM that is disabled
        $mockApm = $this->createMock(ApmInterface::class);
        $mockApm->method('isEnabled')->willReturn(false);
        
        // Set APM on request
        $this->request->apm = $mockApm;
        
        // Create executer with Request
        $executer = $this->createExecuterWithRequest($this->request);
        
        // Create mock statement
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('rowCount')->willReturn(1);
        
        $this->mockPdo->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStatement);
        
        // APM should not be called
        $mockApm->expects($this->never())->method('startSpan');
        $mockApm->expects($this->never())->method('endSpan');
        
        // Execute query
        $executer->query('SELECT * FROM users');
        $result = $executer->execute();
        
        $this->assertTrue($result);
    }
    
    public function testExecuteWithRequestButTracingDisabled(): void
    {
        // Disable tracing via environment
        $_ENV['APM_TRACE_DB_QUERY'] = '0';
        
        // Create mock APM
        $mockApm = $this->createMock(ApmInterface::class);
        $mockApm->method('isEnabled')->willReturn(true);
        
        // Check if shouldTraceDbQuery exists
        if (method_exists($mockApm, 'shouldTraceDbQuery')) {
            $mockApm->method('shouldTraceDbQuery')->willReturn(false);
        }
        
        // Set APM on request
        $this->request->apm = $mockApm;
        
        // Create executer with Request
        $executer = $this->createExecuterWithRequest($this->request);
        
        // Create mock statement
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('rowCount')->willReturn(1);
        
        $this->mockPdo->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStatement);
        
        // APM should not be called (tracing disabled)
        $mockApm->expects($this->never())->method('startSpan');
        $mockApm->expects($this->never())->method('endSpan');
        
        // Execute query
        $executer->query('SELECT * FROM users');
        $result = $executer->execute();
        
        $this->assertTrue($result);
        
        // Restore
        $_ENV['APM_TRACE_DB_QUERY'] = '1';
    }
    
    public function testExecuteWithRequestAndApmError(): void
    {
        // Create mock APM that throws error
        $mockApm = $this->createMock(ApmInterface::class);
        $mockApm->method('isEnabled')->willReturn(true);
        
        // Check if shouldTraceDbQuery exists
        if (method_exists($mockApm, 'shouldTraceDbQuery')) {
            $mockApm->method('shouldTraceDbQuery')->willReturn(true);
        }
        
        $mockApm->method('startSpan')
            ->willThrowException(new \Exception('APM error'));
        
        // Set APM on request
        $this->request->apm = $mockApm;
        
        // Create executer with Request
        $executer = $this->createExecuterWithRequest($this->request);
        
        // Create mock statement
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('rowCount')->willReturn(1);
        
        $this->mockPdo->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStatement);
        
        // Query should still execute even if APM fails
        $executer->query('SELECT * FROM users');
        $result = $executer->execute();
        
        // Query should succeed despite APM error
        $this->assertTrue($result);
    }
    
    public function testExecuteWithRequestAndPdoException(): void
    {
        // Create mock APM
        $mockApm = $this->createMock(ApmInterface::class);
        $mockApm->method('isEnabled')->willReturn(true);
        
        // Check if shouldTraceDbQuery exists
        if (method_exists($mockApm, 'shouldTraceDbQuery')) {
            $mockApm->method('shouldTraceDbQuery')->willReturn(true);
        }
        
        $mockApm->method('startSpan')
            ->willReturn(['span_id' => 'test-span']);
        
        // Set APM on request
        $this->request->apm = $mockApm;
        
        // Create executer with Request
        $executer = $this->createExecuterWithRequest($this->request);
        
        // Create mock statement that throws exception
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')
            ->willThrowException(new PDOException('SQL error'));
        
        $this->mockPdo->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStatement);
        
        // Set up APM expectations for error handling
        $mockApm->expects($this->once())
            ->method('recordException')
            ->with(
                ['span_id' => 'test-span'],
                $this->isInstanceOf(PDOException::class)
            );
        
        $mockApm->expects($this->once())
            ->method('endSpan')
            ->with(
                ['span_id' => 'test-span'],
                $this->callback(function ($attributes) {
                    return isset($attributes['db.execution_time_ms']);
                }),
                ApmInterface::STATUS_ERROR
            );
        
        // Execute query - should fail
        $executer->query('SELECT * FROM users');
        $result = $executer->execute();
        
        $this->assertFalse($result);
        $this->assertNotNull($executer->getError());
    }
    
    public function testExecuteWithoutRequest(): void
    {
        // Create executer without Request
        $executer = $this->createExecuterWithRequest(null);
        
        // Create mock statement
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('rowCount')->willReturn(1);
        
        $this->mockPdo->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStatement);
        
        // Execute query - should work without Request
        $executer->query('SELECT * FROM users');
        $result = $executer->execute();
        
        $this->assertTrue($result);
    }
}


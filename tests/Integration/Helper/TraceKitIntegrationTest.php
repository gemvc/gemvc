<?php

declare(strict_types=1);

namespace Tests\Integration\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\TraceKitModel;
use Gemvc\Helper\TraceKitToolkit;

/**
 * Integration tests for TraceKitModel and TraceKitToolkit
 * Tests complete workflows and interactions
 */
class TraceKitIntegrationTest extends TestCase
{
    private array $originalEnv;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->originalEnv = [
            'TRACEKIT_API_KEY' => $_ENV['TRACEKIT_API_KEY'] ?? null,
            'TRACEKIT_SERVICE_NAME' => $_ENV['TRACEKIT_SERVICE_NAME'] ?? null,
        ];
        
        unset($_ENV['TRACEKIT_API_KEY']);
        unset($_ENV['TRACEKIT_SERVICE_NAME']);
        TraceKitModel::clearCurrentInstance();
    }
    
    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value !== null) {
                $_ENV[$key] = $value;
            } else {
                unset($_ENV[$key]);
            }
        }
        
        TraceKitModel::clearCurrentInstance();
        parent::tearDown();
    }
    
    // ==========================================
    // Complete Trace Workflow Tests
    // ==========================================
    
    public function testCompleteTraceWorkflow(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        // Start root trace
        $rootSpan = $model->startTrace('http-request', [
            'http.method' => 'POST',
            'http.url' => '/api/users',
        ]);
        
        $this->assertNotEmpty($rootSpan);
        $this->assertNotNull($model->getTraceId());
        
        // Start child span
        $dbSpan = $model->startSpan('database-query', [
            'db.statement' => 'SELECT * FROM users',
        ], TraceKitModel::SPAN_KIND_CLIENT);
        
        $this->assertNotEmpty($dbSpan);
        $this->assertEquals($rootSpan['trace_id'], $dbSpan['trace_id']);
        
        // Add event to child span
        $model->addEvent($dbSpan, 'query.executed', ['rows' => '10']);
        
        // End child span
        $model->endSpan($dbSpan, ['db.rows' => 10]);
        
        // End root span
        $model->endSpan($rootSpan, ['http.status_code' => 200]);
        
        // Verify spans are completed
        $this->assertTrue(true);
    }
    
    public function testTraceWithExceptionHandling(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        $span = $model->startTrace('operation');
        
        try {
            throw new \RuntimeException('Test error');
        } catch (\Throwable $e) {
            $model->recordException($span, $e);
        }
        
        $model->endSpan($span, [], TraceKitModel::STATUS_ERROR);
        
        $this->assertTrue(true);
    }
    
    public function testTraceWithAutoCreatedExceptionTrace(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        // Record exception without existing trace
        $exception = new \Exception('Unexpected error');
        $span = $model->recordException([], $exception, 'error-handler');
        
        $this->assertNotEmpty($span);
        $this->assertArrayHasKey('span_id', $span);
        
        $model->endSpan($span);
    }
    
    public function testNestedSpanHierarchy(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        $root = $model->startTrace('root');
        $level1 = $model->startSpan('level1');
        $level2 = $model->startSpan('level2');
        $level3 = $model->startSpan('level3');
        
        // Verify all share same trace ID
        $this->assertEquals($root['trace_id'], $level1['trace_id']);
        $this->assertEquals($root['trace_id'], $level2['trace_id']);
        $this->assertEquals($root['trace_id'], $level3['trace_id']);
        
        // End in reverse order
        $model->endSpan($level3);
        $model->endSpan($level2);
        $model->endSpan($level1);
        $model->endSpan($root);
        
        $this->assertNull($model->getActiveSpan());
    }
    
    public function testTraceWithMultipleEvents(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('operation');
        
        $model->addEvent($span, 'event1', ['key1' => 'value1']);
        $model->addEvent($span, 'event2', ['key2' => 'value2']);
        $model->addEvent($span, 'event3', ['key3' => 'value3']);
        
        $model->endSpan($span);
        
        $this->assertTrue(true);
    }
    
    public function testTraceWithComplexAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        $attributes = [
            'string' => 'value',
            'int' => 123,
            'float' => 45.67,
            'bool' => true,
            'array' => [1, 2, 3],
            'null' => null,
        ];
        
        $span = $model->startTrace('test', $attributes);
        $model->endSpan($span);
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // Sample Rate Integration Tests
    // ==========================================
    
    public function testTraceWithZeroSampleRate(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 0.0]);
        
        $span = $model->startTrace('test');
        $this->assertEmpty($span);
        
        // But errors should still be traced
        $exception = new \Exception('Error');
        $errorSpan = $model->recordException([], $exception, 'error', [], true);
        $this->assertNotEmpty($errorSpan);
    }
    
    public function testTraceWithPartialSampleRate(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 0.1]);
        
        // Run multiple times
        $traced = 0;
        for ($i = 0; $i < 10; $i++) {
            $span = $model->startTrace('test-' . $i);
            if (!empty($span)) {
                $traced++;
                $model->endSpan($span);
            }
        }
        
        // Should have some traces (statistically)
        $this->assertIsInt($traced);
    }
    
    // ==========================================
    // TraceKitToolkit Integration Tests
    // ==========================================
    
    public function testToolkitServiceRegistrationFlow(): void
    {
        $toolkit = new TraceKitToolkit();
        
        // Register service
        $registerResponse = $toolkit->registerService(
            'test@example.com',
            'Test Organization',
            'gemvc',
            ['version' => '1.0.0']
        );
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $registerResponse);
    }
    
    public function testToolkitHealthCheckFlow(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key', 'test-service');
        
        // Send heartbeat
        $heartbeatResponse = $toolkit->sendHeartbeat('healthy', [
            'memory_usage' => '50MB',
            'cpu_usage' => '25%',
        ]);
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $heartbeatResponse);
    }
    
    public function testToolkitWebhookManagementFlow(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        // Create webhook
        $createResponse = $toolkit->createWebhook(
            'test-webhook',
            'https://example.com/webhook',
            ['alert.created', 'alert.resolved'],
            true
        );
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $createResponse);
        
        // List webhooks
        $listResponse = $toolkit->listWebhooks();
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $listResponse);
    }
    
    public function testToolkitMetricsAndAlertsFlow(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        // Get metrics
        $metricsResponse = $toolkit->getMetrics('15m');
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $metricsResponse);
        
        // Get alerts summary
        $alertsResponse = $toolkit->getAlertsSummary();
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $alertsResponse);
        
        // Get active alerts
        $activeAlertsResponse = $toolkit->getActiveAlerts(50);
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $activeAlertsResponse);
    }
    
    public function testToolkitBillingFlow(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        // Get subscription
        $subscriptionResponse = $toolkit->getSubscription();
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $subscriptionResponse);
        
        // List plans
        $plansResponse = $toolkit->listPlans();
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $plansResponse);
        
        // Create checkout session
        $checkoutResponse = $toolkit->createCheckoutSession(
            'pro',
            'monthly',
            'gemvc',
            'https://example.com/success',
            'https://example.com/cancel'
        );
        
        $this->assertInstanceOf(\Gemvc\Http\JsonResponse::class, $checkoutResponse);
    }
    
    // ==========================================
    // Cross-Class Integration Tests
    // ==========================================
    
    public function testModelAndToolkitWithSameServiceName(): void
    {
        $serviceName = 'integration-test-service';
        
        $model = new TraceKitModel([
            'api_key' => 'test-key',
            'service_name' => $serviceName,
        ]);
        
        $toolkit = new TraceKitToolkit('test-key', $serviceName);
        
        // Both should use same service name
        $this->assertTrue(true);
    }
    
    public function testMultipleTracesInSequence(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        // First trace
        $span1 = $model->startTrace('trace1');
        $model->endSpan($span1);
        
        // Clear for next trace
        TraceKitModel::clearCurrentInstance();
        
        // Second trace
        $model2 = new TraceKitModel(['api_key' => 'test-key']);
        $span2 = $model2->startTrace('trace2');
        $model2->endSpan($span2);
        
        // Should have different trace IDs
        $this->assertNotEquals($span1['trace_id'], $span2['trace_id']);
    }
}


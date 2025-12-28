<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\TraceKitModel;
use ReflectionClass;
use ReflectionMethod;

class TraceKitModelTest extends TestCase
{
    private array $originalEnv;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original environment
        $this->originalEnv = [
            'TRACEKIT_API_KEY' => $_ENV['TRACEKIT_API_KEY'] ?? null,
            'TRACEKIT_SERVICE_NAME' => $_ENV['TRACEKIT_SERVICE_NAME'] ?? null,
            'TRACEKIT_ENDPOINT' => $_ENV['TRACEKIT_ENDPOINT'] ?? null,
            'TRACEKIT_ENABLED' => $_ENV['TRACEKIT_ENABLED'] ?? null,
            'TRACEKIT_SAMPLE_RATE' => $_ENV['TRACEKIT_SAMPLE_RATE'] ?? null,
            'TRACEKIT_TRACE_RESPONSE' => $_ENV['TRACEKIT_TRACE_RESPONSE'] ?? null,
            'TRACEKIT_TRACE_DB_QUERY' => $_ENV['TRACEKIT_TRACE_DB_QUERY'] ?? null,
            'TRACEKIT_TRACE_REQUEST_BODY' => $_ENV['TRACEKIT_TRACE_REQUEST_BODY'] ?? null,
        ];
        
        // Clear environment
        unset($_ENV['TRACEKIT_API_KEY']);
        unset($_ENV['TRACEKIT_SERVICE_NAME']);
        unset($_ENV['TRACEKIT_ENDPOINT']);
        unset($_ENV['TRACEKIT_ENABLED']);
        unset($_ENV['TRACEKIT_SAMPLE_RATE']);
        unset($_ENV['TRACEKIT_TRACE_RESPONSE']);
        unset($_ENV['TRACEKIT_TRACE_DB_QUERY']);
        unset($_ENV['TRACEKIT_TRACE_REQUEST_BODY']);
        
        // Clear static instance
        TraceKitModel::clearCurrentInstance();
    }
    
    protected function tearDown(): void
    {
        // Restore original environment
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
    // Constructor Tests
    // ==========================================
    
    public function testConstructorWithConfigArray(): void
    {
        $config = [
            'api_key' => 'test-api-key',
            'service_name' => 'test-service',
            'endpoint' => 'https://test.tracekit.dev/v1/traces',
            'enabled' => true,
            'sample_rate' => 0.5,
            'trace_response' => true,
            'trace_db_query' => true,
            'trace_request_body' => true,
        ];
        
        $model = new TraceKitModel($config);
        
        $this->assertTrue($model->isEnabled());
        $this->assertEquals('test-service', $this->getPrivateProperty($model, 'serviceName'));
        $this->assertEquals(0.5, $model->getSampleRate());
        $this->assertTrue($model->shouldTraceResponse());
        $this->assertTrue($model->shouldTraceDbQuery());
        $this->assertTrue($model->shouldTraceRequestBody());
    }
    
    public function testConstructorWithEnvironmentVariables(): void
    {
        $_ENV['TRACEKIT_API_KEY'] = 'env-api-key';
        $_ENV['TRACEKIT_SERVICE_NAME'] = 'env-service';
        $_ENV['TRACEKIT_ENDPOINT'] = 'https://env.tracekit.dev/v1/traces';
        $_ENV['TRACEKIT_ENABLED'] = 'true';
        $_ENV['TRACEKIT_SAMPLE_RATE'] = '0.75';
        
        $model = new TraceKitModel();
        
        $this->assertTrue($model->isEnabled());
        $this->assertEquals('env-service', $this->getPrivateProperty($model, 'serviceName'));
        $this->assertEquals(0.75, $model->getSampleRate());
    }
    
    public function testConstructorWithDefaults(): void
    {
        $model = new TraceKitModel([]);
        
        $this->assertFalse($model->isEnabled()); // No API key
        $this->assertEquals('gemvc-app', $this->getPrivateProperty($model, 'serviceName'));
        $this->assertEquals(1.0, $model->getSampleRate());
        $this->assertFalse($model->shouldTraceResponse());
        $this->assertFalse($model->shouldTraceDbQuery());
        $this->assertFalse($model->shouldTraceRequestBody());
    }
    
    public function testConstructorDisablesWhenNoApiKey(): void
    {
        $model = new TraceKitModel(['api_key' => '']);
        
        $this->assertFalse($model->isEnabled());
    }
    
    public function testConstructorWithStringBooleans(): void
    {
        $model = new TraceKitModel([
            'api_key' => 'test-key',
            'enabled' => 'false',
            'trace_response' => 'true',
            'trace_db_query' => '1',
            'trace_request_body' => '0',
        ]);
        
        $this->assertFalse($model->isEnabled());
        $this->assertTrue($model->shouldTraceResponse());
        $this->assertTrue($model->shouldTraceDbQuery());
        $this->assertFalse($model->shouldTraceRequestBody());
    }
    
    public function testConstructorClampsSampleRate(): void
    {
        $model1 = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 2.0]);
        $this->assertEquals(1.0, $model1->getSampleRate());
        
        $model2 = new TraceKitModel(['api_key' => 'test', 'sample_rate' => -1.0]);
        $this->assertEquals(0.0, $model2->getSampleRate());
    }
    
    public function testConstructorRegistersCurrentInstance(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        
        $this->assertSame($model, TraceKitModel::getCurrentInstance());
    }
    
    // ==========================================
    // Static Methods Tests
    // ==========================================
    
    public function testGetCurrentInstance(): void
    {
        $this->assertNull(TraceKitModel::getCurrentInstance());
        
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $this->assertSame($model, TraceKitModel::getCurrentInstance());
    }
    
    public function testClearCurrentInstance(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $this->assertNotNull(TraceKitModel::getCurrentInstance());
        
        TraceKitModel::clearCurrentInstance();
        $this->assertNull(TraceKitModel::getCurrentInstance());
    }
    
    // ==========================================
    // Configuration Methods Tests
    // ==========================================
    
    public function testIsEnabled(): void
    {
        $model1 = new TraceKitModel(['api_key' => 'test-key']);
        $this->assertTrue($model1->isEnabled());
        
        $model2 = new TraceKitModel(['api_key' => '']);
        $this->assertFalse($model2->isEnabled());
    }
    
    public function testShouldTraceResponse(): void
    {
        $model = new TraceKitModel([
            'api_key' => 'test',
            'trace_response' => true,
        ]);
        
        $this->assertTrue($model->shouldTraceResponse());
    }
    
    public function testShouldTraceDbQuery(): void
    {
        $model = new TraceKitModel([
            'api_key' => 'test',
            'trace_db_query' => true,
        ]);
        
        $this->assertTrue($model->shouldTraceDbQuery());
    }
    
    public function testShouldTraceRequestBody(): void
    {
        $model = new TraceKitModel([
            'api_key' => 'test',
            'trace_request_body' => true,
        ]);
        
        $this->assertTrue($model->shouldTraceRequestBody());
    }
    
    public function testGetSampleRate(): void
    {
        $model = new TraceKitModel([
            'api_key' => 'test',
            'sample_rate' => 0.5,
        ]);
        
        $this->assertEquals(0.5, $model->getSampleRate());
    }
    
    public function testGetSampleRatePercent(): void
    {
        $model = new TraceKitModel([
            'api_key' => 'test',
            'sample_rate' => 0.5,
        ]);
        
        $this->assertEquals(50.0, $model->getSampleRatePercent());
    }
    
    // ==========================================
    // Sampling Tests
    // ==========================================
    
    public function testShouldSampleWhenDisabled(): void
    {
        $model = new TraceKitModel(['api_key' => '']);
        $this->assertFalse($model->shouldSample());
    }
    
    public function testShouldSampleWithForceSample(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 0.0]);
        $this->assertTrue($model->shouldSample(true));
    }
    
    public function testShouldSampleWithRateOne(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 1.0]);
        $this->assertTrue($model->shouldSample());
    }
    
    public function testShouldSampleWithRateZero(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 0.0]);
        $this->assertFalse($model->shouldSample());
    }
    
    // ==========================================
    // Trace Management Tests
    // ==========================================
    
    public function testStartTraceReturnsEmptyWhenNotSampled(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 0.0]);
        $result = $model->startTrace('test-operation');
        
        $this->assertEmpty($result);
    }
    
    public function testStartTraceCreatesRootSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $result = $model->startTrace('http-request', ['http.method' => 'GET']);
        
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('span_id', $result);
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertIsString($result['span_id']);
        $this->assertIsString($result['trace_id']);
        $this->assertIsInt($result['start_time']);
    }
    
    public function testStartTraceWithForceSample(): void
    {
        $model = new TraceKitModel(['api_key' => 'test', 'sample_rate' => 0.0]);
        $result = $model->startTrace('error-handler', [], true);
        
        $this->assertNotEmpty($result);
    }
    
    public function testStartTraceGeneratesTraceId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $result = $model->startTrace('test');
        
        $this->assertNotNull($model->getTraceId());
        $this->assertEquals($model->getTraceId(), $result['trace_id']);
    }
    
    public function testStartTraceActivatesSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $model->startTrace('test');
        
        $activeSpan = $model->getActiveSpan();
        $this->assertNotNull($activeSpan);
    }
    
    public function testStartSpanReturnsEmptyWhenDisabled(): void
    {
        $model = new TraceKitModel(['api_key' => '']);
        $result = $model->startSpan('test');
        
        $this->assertEmpty($result);
    }
    
    public function testStartSpanCreatesChildSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $rootSpan = $model->startTrace('root');
        $childSpan = $model->startSpan('child');
        
        $this->assertNotEmpty($childSpan);
        $this->assertEquals($rootSpan['trace_id'], $childSpan['trace_id']);
    }
    
    public function testStartSpanWithInvalidKind(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $model->startTrace('root');
        $span = $model->startSpan('child', [], 999);
        
        // Should default to INTERNAL
        $this->assertNotEmpty($span);
    }
    
    public function testStartSpanCreatesRootIfNoParent(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startSpan('orphan');
        
        $this->assertNotEmpty($span);
        $this->assertNotNull($model->getTraceId());
    }
    
    public function testEndSpanWithEmptySpanData(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $model->endSpan([]);
        
        // Should not throw
        $this->assertTrue(true);
    }
    
    public function testEndSpanUpdatesSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        
        usleep(1000); // Small delay to ensure different timestamps
        $model->endSpan($span);
        
        // Span should be completed
        $this->assertTrue(true);
    }
    
    public function testEndSpanWithFinalAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        $model->endSpan($span, ['http.status_code' => 200]);
        
        $this->assertTrue(true);
    }
    
    public function testEndSpanWithErrorStatus(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        $model->endSpan($span, [], TraceKitModel::STATUS_ERROR);
        
        $this->assertTrue(true);
    }
    
    public function testEndSpanPopsFromStack(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $root = $model->startTrace('root');
        $child = $model->startSpan('child');
        
        $this->assertNotNull($model->getActiveSpan());
        
        $model->endSpan($child);
        // Root should still be active
        $this->assertNotNull($model->getActiveSpan());
        
        $model->endSpan($root);
        $this->assertNull($model->getActiveSpan());
    }
    
    // ==========================================
    // Exception Handling Tests
    // ==========================================
    
    public function testRecordExceptionReturnsEmptyWhenDisabled(): void
    {
        $model = new TraceKitModel(['api_key' => '']);
        $exception = new \Exception('Test error');
        $result = $model->recordException([], $exception);
        
        $this->assertEmpty($result);
    }
    
    public function testRecordExceptionCreatesTraceIfEmpty(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $exception = new \Exception('Test error');
        $result = $model->recordException([], $exception);
        
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('span_id', $result);
    }
    
    public function testRecordExceptionOnExistingSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        $exception = new \Exception('Test error');
        $result = $model->recordException($span, $exception);
        
        $this->assertEquals($span['span_id'], $result['span_id']);
    }
    
    public function testRecordExceptionWithCustomAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $exception = new \Exception('Test error');
        $result = $model->recordException([], $exception, 'custom-operation', ['custom' => 'attr']);
        
        $this->assertNotEmpty($result);
    }
    
    // ==========================================
    // Event Management Tests
    // ==========================================
    
    public function testAddEventReturnsEarlyWhenDisabled(): void
    {
        $model = new TraceKitModel(['api_key' => '']);
        $model->addEvent([], 'test-event');
        
        $this->assertTrue(true);
    }
    
    public function testAddEventReturnsEarlyWhenEmptySpanData(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $model->addEvent([], 'test-event');
        
        $this->assertTrue(true);
    }
    
    public function testAddEventToSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        $model->addEvent($span, 'test-event', ['key' => 'value']);
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // Flush Tests
    // ==========================================
    
    public function testFlushReturnsEarlyWhenDisabled(): void
    {
        $model = new TraceKitModel(['api_key' => '']);
        $model->flush();
        
        $this->assertTrue(true);
    }
    
    public function testFlushReturnsEarlyWhenNoSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $model->flush();
        
        $this->assertTrue(true);
    }
    
    public function testFlushReturnsEarlyWhenNoTraceId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        // Don't start trace, so no traceId
        $model->flush();
        
        $this->assertTrue(true);
    }
    
    // ==========================================
    // Helper Methods Tests
    // ==========================================
    
    public function testGetTraceId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $this->assertNull($model->getTraceId());
        
        $model->startTrace('test');
        $this->assertNotNull($model->getTraceId());
        $this->assertIsString($model->getTraceId());
    }
    
    public function testGetActiveSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $this->assertNull($model->getActiveSpan());
        
        $model->startTrace('test');
        $activeSpan = $model->getActiveSpan();
        $this->assertNotNull($activeSpan);
        /** @var array<string, mixed> $activeSpan */
        $this->assertArrayHasKey('span_id', $activeSpan);
    }
    
    public function testNormalizeAttributesWithScalarValues(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'normalizeAttributes');
        
        $attributes = [
            'string' => 'value',
            'int' => 123,
            'float' => 45.67,
            'bool' => true,
        ];
        
        $result = $method->invoke($model, $attributes);
        
        $this->assertEquals('value', $result['string']);
        $this->assertEquals(123, $result['int']);
        $this->assertEquals(45.67, $result['float']);
        $this->assertTrue($result['bool']);
    }
    
    public function testNormalizeAttributesWithArrayValues(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'normalizeAttributes');
        
        $attributes = [
            'array' => [1, 2, 3],
        ];
        
        $result = $method->invoke($model, $attributes);
        
        $this->assertIsArray($result['array']);
    }
    
    public function testNormalizeAttributesWithObjectValues(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'normalizeAttributes');
        
        $obj = new class {
            public function __toString(): string {
                return 'object-string';
            }
        };
        
        $attributes = ['object' => $obj];
        $result = $method->invoke($model, $attributes);
        
        $this->assertIsString($result['object']);
    }
    
    public function testNormalizeAttributesWithNull(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'normalizeAttributes');
        
        $attributes = ['null' => null];
        $result = $method->invoke($model, $attributes);
        
        $this->assertEquals('', $result['null']);
    }
    
    public function testFormatStackTrace(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'formatStackTrace');
        
        $exception = new \Exception('Test exception');
        $stackTrace = $method->invoke($model, $exception);
        
        $this->assertIsString($stackTrace);
        $this->assertNotEmpty($stackTrace);
        // Stack trace should contain file and line info
        $this->assertStringContainsString(':', $stackTrace);
    }
    
    public function testGenerateTraceId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'generateTraceId');
        
        $traceId = $method->invoke($model);
        
        $this->assertIsString($traceId);
        $this->assertEquals(32, strlen($traceId)); // 16 bytes = 32 hex chars
    }
    
    public function testGenerateSpanId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'generateSpanId');
        
        $spanId = $method->invoke($model);
        
        $this->assertIsString($spanId);
        $this->assertEquals(16, strlen($spanId)); // 8 bytes = 16 hex chars
    }
    
    public function testGetMicrotime(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'getMicrotime');
        
        $time = $method->invoke($model);
        
        $this->assertIsInt($time);
        $this->assertGreaterThan(0, $time);
    }
    
    // ==========================================
    // Constants Tests
    // ==========================================
    
    public function testSpanKindConstants(): void
    {
        $this->assertEquals(0, TraceKitModel::SPAN_KIND_UNSPECIFIED);
        $this->assertEquals(1, TraceKitModel::SPAN_KIND_INTERNAL);
        $this->assertEquals(2, TraceKitModel::SPAN_KIND_SERVER);
        $this->assertEquals(3, TraceKitModel::SPAN_KIND_CLIENT);
        $this->assertEquals(4, TraceKitModel::SPAN_KIND_PRODUCER);
        $this->assertEquals(5, TraceKitModel::SPAN_KIND_CONSUMER);
    }
    
    public function testStatusConstants(): void
    {
        $this->assertEquals('OK', TraceKitModel::STATUS_OK);
        $this->assertEquals('ERROR', TraceKitModel::STATUS_ERROR);
    }
    
    // ==========================================
    // Helper Methods for Testing
    // ==========================================
    
    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
    
    private function getPrivateMethod(object $object, string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}


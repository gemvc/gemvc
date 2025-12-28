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
            'TRACEKIT_TRACE_RESPONSE_BODY' => $_ENV['TRACEKIT_TRACE_RESPONSE_BODY'] ?? null,
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
        unset($_ENV['TRACEKIT_TRACE_RESPONSE_BODY']);
        
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
    // Configuration Loading Methods Tests (Private)
    // ==========================================
    
    public function testLoadApiKeyFromConfig(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadApiKey');
        
        $result = $method->invoke($model, ['api_key' => 'test-key']);
        $this->assertEquals('test-key', $result);
    }
    
    public function testLoadApiKeyFromEnvironment(): void
    {
        $_ENV['TRACEKIT_API_KEY'] = 'env-key';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadApiKey');
        
        $result = $method->invoke($model, []);
        $this->assertEquals('env-key', $result);
    }
    
    public function testLoadApiKeyReturnsEmptyWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadApiKey');
        
        $result = $method->invoke($model, []);
        $this->assertEquals('', $result);
    }
    
    public function testLoadApiKeyPrefersConfigOverEnv(): void
    {
        $_ENV['TRACEKIT_API_KEY'] = 'env-key';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadApiKey');
        
        $result = $method->invoke($model, ['api_key' => 'config-key']);
        $this->assertEquals('config-key', $result);
    }
    
    public function testLoadServiceNameFromConfig(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadServiceName');
        
        $result = $method->invoke($model, ['service_name' => 'custom-service']);
        $this->assertEquals('custom-service', $result);
    }
    
    public function testLoadServiceNameFromEnvironment(): void
    {
        $_ENV['TRACEKIT_SERVICE_NAME'] = 'env-service';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadServiceName');
        
        $result = $method->invoke($model, []);
        $this->assertEquals('env-service', $result);
    }
    
    public function testLoadServiceNameReturnsDefaultWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadServiceName');
        
        $result = $method->invoke($model, []);
        $this->assertEquals('gemvc-app', $result);
    }
    
    public function testLoadEndpointFromConfig(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadEndpoint');
        
        $result = $method->invoke($model, ['endpoint' => 'https://custom.tracekit.dev/v1/traces']);
        $this->assertEquals('https://custom.tracekit.dev/v1/traces', $result);
    }
    
    public function testLoadEndpointFromEnvironment(): void
    {
        $_ENV['TRACEKIT_ENDPOINT'] = 'https://env.tracekit.dev/v1/traces';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadEndpoint');
        
        $result = $method->invoke($model, []);
        $this->assertEquals('https://env.tracekit.dev/v1/traces', $result);
    }
    
    public function testLoadEndpointReturnsDefaultWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'loadEndpoint');
        
        $result = $method->invoke($model, []);
        $this->assertEquals('https://app.tracekit.dev/v1/traces', $result);
    }
    
    public function testParseEnabledFlagFromConfigBoolean(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseEnabledFlag');
        
        $this->assertTrue($method->invoke($model, ['enabled' => true]));
        $this->assertFalse($method->invoke($model, ['enabled' => false]));
    }
    
    public function testParseEnabledFlagFromConfigString(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseEnabledFlag');
        
        $this->assertTrue($method->invoke($model, ['enabled' => 'true']));
        $this->assertTrue($method->invoke($model, ['enabled' => '1']));
        $this->assertFalse($method->invoke($model, ['enabled' => 'false']));
        $this->assertFalse($method->invoke($model, ['enabled' => '0']));
        $this->assertTrue($method->invoke($model, ['enabled' => 'yes'])); // Any other string is true
    }
    
    public function testParseEnabledFlagFromEnvironment(): void
    {
        $_ENV['TRACEKIT_ENABLED'] = 'false';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseEnabledFlag');
        
        $result = $method->invoke($model, []);
        $this->assertFalse($result);
    }
    
    public function testParseEnabledFlagReturnsDefaultWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseEnabledFlag');
        
        $result = $method->invoke($model, []);
        $this->assertTrue($result); // Default is true
    }
    
    public function testParseSampleRateFromConfig(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseSampleRate');
        
        $this->assertEquals(0.5, $method->invoke($model, ['sample_rate' => 0.5]));
        $this->assertEquals(0.75, $method->invoke($model, ['sample_rate' => '0.75']));
    }
    
    public function testParseSampleRateClampsToValidRange(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseSampleRate');
        
        $this->assertEquals(1.0, $method->invoke($model, ['sample_rate' => 2.0]));
        $this->assertEquals(0.0, $method->invoke($model, ['sample_rate' => -1.0]));
        $this->assertEquals(0.5, $method->invoke($model, ['sample_rate' => 0.5]));
    }
    
    public function testParseSampleRateFromEnvironment(): void
    {
        $_ENV['TRACEKIT_SAMPLE_RATE'] = '0.25';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseSampleRate');
        
        $result = $method->invoke($model, []);
        $this->assertEquals(0.25, $result);
    }
    
    public function testParseSampleRateReturnsDefaultWhenInvalid(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseSampleRate');
        
        $this->assertEquals(1.0, $method->invoke($model, ['sample_rate' => 'invalid']));
        $this->assertEquals(1.0, $method->invoke($model, ['sample_rate' => null]));
    }
    
    public function testParseTraceResponseFlagFromConfigBoolean(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceResponseFlag');
        
        $this->assertTrue($method->invoke($model, ['trace_response' => true]));
        $this->assertFalse($method->invoke($model, ['trace_response' => false]));
    }
    
    public function testParseTraceResponseFlagFromConfigString(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceResponseFlag');
        
        $this->assertTrue($method->invoke($model, ['trace_response' => 'true']));
        $this->assertTrue($method->invoke($model, ['trace_response' => '1']));
        $this->assertFalse($method->invoke($model, ['trace_response' => 'false']));
        $this->assertFalse($method->invoke($model, ['trace_response' => '0']));
    }
    
    public function testParseTraceResponseFlagFromEnvironment(): void
    {
        $_ENV['TRACEKIT_TRACE_RESPONSE'] = 'true';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceResponseFlag');
        
        $result = $method->invoke($model, []);
        $this->assertTrue($result);
    }
    
    public function testParseTraceResponseFlagReturnsDefaultWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceResponseFlag');
        
        $result = $method->invoke($model, []);
        $this->assertFalse($result); // Default is false
    }
    
    public function testParseTraceDbQueryFlagFromConfigBoolean(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceDbQueryFlag');
        
        $this->assertTrue($method->invoke($model, ['trace_db_query' => true]));
        $this->assertFalse($method->invoke($model, ['trace_db_query' => false]));
    }
    
    public function testParseTraceDbQueryFlagFromConfigString(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceDbQueryFlag');
        
        $this->assertTrue($method->invoke($model, ['trace_db_query' => 'true']));
        $this->assertTrue($method->invoke($model, ['trace_db_query' => '1']));
        $this->assertFalse($method->invoke($model, ['trace_db_query' => 'false']));
        $this->assertFalse($method->invoke($model, ['trace_db_query' => '0']));
    }
    
    public function testParseTraceDbQueryFlagFromEnvironment(): void
    {
        $_ENV['TRACEKIT_TRACE_DB_QUERY'] = '1';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceDbQueryFlag');
        
        $result = $method->invoke($model, []);
        $this->assertTrue($result);
    }
    
    public function testParseTraceDbQueryFlagReturnsDefaultWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceDbQueryFlag');
        
        $result = $method->invoke($model, []);
        $this->assertFalse($result); // Default is false
    }
    
    public function testParseTraceRequestBodyFlagFromConfigBoolean(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $this->assertTrue($method->invoke($model, ['trace_request_body' => true]));
        $this->assertFalse($method->invoke($model, ['trace_request_body' => false]));
    }
    
    public function testParseTraceRequestBodyFlagFromConfigString(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $this->assertTrue($method->invoke($model, ['trace_request_body' => 'true']));
        $this->assertTrue($method->invoke($model, ['trace_request_body' => '1']));
        $this->assertFalse($method->invoke($model, ['trace_request_body' => 'false']));
        $this->assertFalse($method->invoke($model, ['trace_request_body' => '0']));
    }
    
    public function testParseTraceRequestBodyFlagFromEnvironmentTraceResponseBody(): void
    {
        $_ENV['TRACEKIT_TRACE_RESPONSE_BODY'] = 'true';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $result = $method->invoke($model, []);
        $this->assertTrue($result);
    }
    
    public function testParseTraceRequestBodyFlagFromEnvironmentTraceRequestBody(): void
    {
        $_ENV['TRACEKIT_TRACE_REQUEST_BODY'] = '1';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $result = $method->invoke($model, []);
        $this->assertTrue($result);
    }
    
    public function testParseTraceRequestBodyFlagPrefersTraceResponseBodyOverTraceRequestBody(): void
    {
        $_ENV['TRACEKIT_TRACE_RESPONSE_BODY'] = 'true';
        $_ENV['TRACEKIT_TRACE_REQUEST_BODY'] = 'false';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $result = $method->invoke($model, []);
        $this->assertTrue($result); // Should use TRACEKIT_TRACE_RESPONSE_BODY
    }
    
    public function testParseTraceRequestBodyFlagPrefersConfigOverEnvironment(): void
    {
        $_ENV['TRACEKIT_TRACE_RESPONSE_BODY'] = 'false';
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $result = $method->invoke($model, ['trace_request_body' => 'true']);
        $this->assertTrue($result); // Config should override env
    }
    
    public function testParseTraceRequestBodyFlagReturnsDefaultWhenNotFound(): void
    {
        $model = new TraceKitModel();
        $method = $this->getPrivateMethod($model, 'parseTraceRequestBodyFlag');
        
        $result = $method->invoke($model, []);
        $this->assertFalse($result); // Default is false
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
    // createEvent() Helper Method Tests (Private)
    // ==========================================
    
    public function testCreateEventWithNameOnly(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        $event = $method->invoke($model, 'test-event');
        
        $this->assertIsArray($event);
        $this->assertEquals('test-event', $event['name']);
        $this->assertIsInt($event['time']);
        $this->assertGreaterThan(0, $event['time']);
        $this->assertIsArray($event['attributes']);
        $this->assertEmpty($event['attributes']);
    }
    
    public function testCreateEventWithAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        $attributes = [
            'key1' => 'value1',
            'key2' => 123,
            'key3' => true,
        ];
        $event = $method->invoke($model, 'test-event', $attributes);
        
        $this->assertIsArray($event);
        $this->assertEquals('test-event', $event['name']);
        $this->assertIsInt($event['time']);
        $this->assertIsArray($event['attributes']);
        $this->assertEquals('value1', $event['attributes']['key1']);
        $this->assertEquals(123, $event['attributes']['key2']);
        $this->assertTrue($event['attributes']['key3']);
    }
    
    public function testCreateEventNormalizesAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        // Test with non-scalar values that should be normalized
        $attributes = [
            'string' => 'test',
            'number' => 42,
            'float' => 3.14,
            'bool' => true,
            'array' => ['nested' => 'value'],
        ];
        $event = $method->invoke($model, 'test-event', $attributes);
        
        $this->assertIsArray($event['attributes']);
        $this->assertEquals('test', $event['attributes']['string']);
        $this->assertEquals(42, $event['attributes']['number']);
        $this->assertEquals(3.14, $event['attributes']['float']);
        $this->assertTrue($event['attributes']['bool']);
        $this->assertIsArray($event['attributes']['array']);
    }
    
    public function testCreateEventGeneratesTimestamp(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        $event1 = $method->invoke($model, 'event1');
        usleep(1000); // Sleep 1ms to ensure different timestamp
        $event2 = $method->invoke($model, 'event2');
        
        $this->assertIsInt($event1['time']);
        $this->assertIsInt($event2['time']);
        $this->assertGreaterThanOrEqual($event1['time'], $event2['time']);
    }
    
    public function testCreateEventWithEmptyName(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        $event = $method->invoke($model, '');
        
        $this->assertIsArray($event);
        $this->assertEquals('', $event['name']);
        $this->assertIsInt($event['time']);
        $this->assertIsArray($event['attributes']);
    }
    
    public function testCreateEventWithComplexAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        $attributes = [
            'simple' => 'value',
            'nested' => ['a' => 1, 'b' => 2],
            'mixed' => ['string', 123, true],
        ];
        $event = $method->invoke($model, 'complex-event', $attributes);
        
        $this->assertEquals('complex-event', $event['name']);
        $this->assertEquals('value', $event['attributes']['simple']);
        $this->assertIsArray($event['attributes']['nested']);
        $this->assertIsArray($event['attributes']['mixed']);
    }
    
    public function testCreateEventReturnsCorrectStructure(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createEvent');
        
        $event = $method->invoke($model, 'test', ['attr' => 'value']);
        
        // Verify structure matches expected format
        $this->assertArrayHasKey('name', $event);
        $this->assertArrayHasKey('time', $event);
        $this->assertArrayHasKey('attributes', $event);
        $this->assertCount(3, $event); // Should only have these 3 keys
        $this->assertIsString($event['name']);
        $this->assertIsInt($event['time']);
        $this->assertIsArray($event['attributes']);
    }
    
    // ==========================================
    // createSpanDataReturn() Helper Method Tests (Private)
    // ==========================================
    
    public function testCreateSpanDataReturnWithValidData(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        $spanId = 'abc123';
        $traceId = 'def456';
        $startTime = 1234567890;
        
        $result = $method->invoke($model, $spanId, $traceId, $startTime);
        
        $this->assertIsArray($result);
        $this->assertEquals($spanId, $result['span_id']);
        $this->assertEquals($traceId, $result['trace_id']);
        $this->assertEquals($startTime, $result['start_time']);
    }
    
    public function testCreateSpanDataReturnReturnsCorrectStructure(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        $result = $method->invoke($model, 'span1', 'trace1', 1000);
        
        // Verify structure matches expected format
        $this->assertArrayHasKey('span_id', $result);
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertCount(3, $result); // Should only have these 3 keys
        $this->assertIsString($result['span_id']);
        $this->assertIsString($result['trace_id']);
        $this->assertIsInt($result['start_time']);
    }
    
    public function testCreateSpanDataReturnWithLongIds(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        // Test with long hex strings (like actual trace/span IDs)
        $spanId = 'a1b2c3d4e5f6g7h8';
        $traceId = '1234567890abcdef1234567890abcdef';
        $startTime = 1699123456789000000; // Nanoseconds
        
        $result = $method->invoke($model, $spanId, $traceId, $startTime);
        
        $this->assertEquals($spanId, $result['span_id']);
        $this->assertEquals($traceId, $result['trace_id']);
        $this->assertEquals($startTime, $result['start_time']);
    }
    
    public function testCreateSpanDataReturnWithZeroStartTime(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        $result = $method->invoke($model, 'span1', 'trace1', 0);
        
        $this->assertEquals(0, $result['start_time']);
        $this->assertEquals('span1', $result['span_id']);
        $this->assertEquals('trace1', $result['trace_id']);
    }
    
    public function testCreateSpanDataReturnWithLargeStartTime(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        // Test with very large timestamp (nanoseconds)
        $largeTime = PHP_INT_MAX;
        $result = $method->invoke($model, 'span1', 'trace1', $largeTime);
        
        $this->assertEquals($largeTime, $result['start_time']);
    }
    
    public function testCreateSpanDataReturnPreservesExactValues(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        $spanId = 'test-span-id-123';
        $traceId = 'test-trace-id-456';
        $startTime = 987654321;
        
        $result = $method->invoke($model, $spanId, $traceId, $startTime);
        
        // Verify exact values are preserved (no transformation)
        $this->assertSame($spanId, $result['span_id']);
        $this->assertSame($traceId, $result['trace_id']);
        $this->assertSame($startTime, $result['start_time']);
    }
    
    public function testCreateSpanDataReturnWithEmptyStrings(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanDataReturn');
        
        $result = $method->invoke($model, '', '', 0);
        
        $this->assertEquals('', $result['span_id']);
        $this->assertEquals('', $result['trace_id']);
        $this->assertEquals(0, $result['start_time']);
    }
    
    // ==========================================
    // createSpanData() Helper Method Tests (Private)
    // ==========================================
    
    public function testCreateSpanDataWithRootSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $traceId = 'trace123';
        $spanId = 'span456';
        $name = 'test-operation';
        $kind = TraceKitModel::SPAN_KIND_SERVER;
        $startTime = 1234567890;
        $attributes = ['key1' => 'value1'];
        
        $result = $method->invoke($model, $traceId, $spanId, null, $name, $kind, $startTime, $attributes);
        
        $this->assertIsArray($result);
        $this->assertEquals($traceId, $result['trace_id']);
        $this->assertEquals($spanId, $result['span_id']);
        $this->assertNull($result['parent_span_id']);
        $this->assertEquals($name, $result['name']);
        $this->assertEquals($kind, $result['kind']);
        $this->assertEquals($startTime, $result['start_time']);
        $this->assertNull($result['end_time']);
        $this->assertNull($result['duration']);
        $this->assertEquals('value1', $result['attributes']['key1']);
        $this->assertEquals(TraceKitModel::STATUS_OK, $result['status']);
        $this->assertIsArray($result['events']);
        $this->assertEmpty($result['events']);
    }
    
    public function testCreateSpanDataWithChildSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $traceId = 'trace123';
        $spanId = 'span789';
        $parentSpanId = 'span456';
        $name = 'child-operation';
        $kind = TraceKitModel::SPAN_KIND_INTERNAL;
        $startTime = 1234567890;
        
        $result = $method->invoke($model, $traceId, $spanId, $parentSpanId, $name, $kind, $startTime);
        
        $this->assertEquals($traceId, $result['trace_id']);
        $this->assertEquals($spanId, $result['span_id']);
        $this->assertEquals($parentSpanId, $result['parent_span_id']);
        $this->assertEquals($name, $result['name']);
        $this->assertEquals($kind, $result['kind']);
    }
    
    public function testCreateSpanDataReturnsCorrectStructure(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $result = $method->invoke($model, 'trace1', 'span1', null, 'test', TraceKitModel::SPAN_KIND_SERVER, 1000);
        
        // Verify all required keys exist
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertArrayHasKey('span_id', $result);
        $this->assertArrayHasKey('parent_span_id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('kind', $result);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertArrayHasKey('end_time', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertCount(11, $result); // Should have exactly 11 keys
    }
    
    // ==========================================
    // parseBooleanFlag() Helper Method Tests (Private)
    // ==========================================
    
    public function testParseBooleanFlagFromConfigTrue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $result = $method->invoke($model, ['flag' => true], 'flag', 'ENV_FLAG', false);
        $this->assertTrue($result);
    }
    
    public function testParseBooleanFlagFromConfigFalse(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $result = $method->invoke($model, ['flag' => false], 'flag', 'ENV_FLAG', true);
        $this->assertFalse($result);
    }
    
    public function testParseBooleanFlagFromEnvStringTrue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $_ENV['TEST_FLAG'] = 'true';
        $result = $method->invoke($model, [], 'flag', 'TEST_FLAG', false);
        $this->assertTrue($result);
        unset($_ENV['TEST_FLAG']);
    }
    
    public function testParseBooleanFlagFromEnvStringOne(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $_ENV['TEST_FLAG'] = '1';
        $result = $method->invoke($model, [], 'flag', 'TEST_FLAG', false);
        $this->assertTrue($result);
        unset($_ENV['TEST_FLAG']);
    }
    
    public function testParseBooleanFlagFromEnvStringFalse(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $_ENV['TEST_FLAG'] = 'false';
        $result = $method->invoke($model, [], 'flag', 'TEST_FLAG', true);
        $this->assertFalse($result);
        unset($_ENV['TEST_FLAG']);
    }
    
    public function testParseBooleanFlagFromEnvStringZero(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $_ENV['TEST_FLAG'] = '0';
        $result = $method->invoke($model, [], 'flag', 'TEST_FLAG', true);
        $this->assertFalse($result);
        unset($_ENV['TEST_FLAG']);
    }
    
    public function testParseBooleanFlagUsesDefaultWhenNotSet(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $result = $method->invoke($model, [], 'flag', 'NONEXISTENT_FLAG', true);
        $this->assertTrue($result);
        
        $result = $method->invoke($model, [], 'flag', 'NONEXISTENT_FLAG', false);
        $this->assertFalse($result);
    }
    
    public function testParseBooleanFlagPrefersConfigOverEnv(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $_ENV['TEST_FLAG'] = 'false';
        $result = $method->invoke($model, ['flag' => true], 'flag', 'TEST_FLAG', false);
        $this->assertTrue($result); // Config should win
        unset($_ENV['TEST_FLAG']);
    }
    
    public function testParseBooleanFlagWithSecondaryEnvKey(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        // Test with secondary env key
        $_ENV['SECONDARY_FLAG'] = 'true';
        $result = $method->invoke($model, [], 'flag', 'PRIMARY_FLAG', false, 'SECONDARY_FLAG');
        $this->assertTrue($result);
        unset($_ENV['SECONDARY_FLAG']);
    }
    
    public function testParseBooleanFlagPrefersPrimaryEnvOverSecondary(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'parseBooleanFlag');
        
        $_ENV['PRIMARY_FLAG'] = 'true';
        $_ENV['SECONDARY_FLAG'] = 'false';
        $result = $method->invoke($model, [], 'flag', 'PRIMARY_FLAG', false, 'SECONDARY_FLAG');
        $this->assertTrue($result); // Primary should win
        unset($_ENV['PRIMARY_FLAG']);
        unset($_ENV['SECONDARY_FLAG']);
    }
    
    // ==========================================
    // validatePayloadStructure() Helper Method Tests (Private)
    // ==========================================
    
    public function testValidatePayloadStructureWithValidPayload(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            [
                                'key' => 'service.name',
                                'value' => ['stringValue' => 'test-service']
                            ]
                        ]
                    ],
                    'scopeSpans' => [
                        [
                            'spans' => [
                                [
                                    'traceId' => '1234567890abcdef1234567890abcdef',
                                    'spanId' => 'abcdef1234567890',
                                    'name' => 'test-span',
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('spans', $result);
        $this->assertArrayHasKey('spanCount', $result);
        $this->assertArrayHasKey('firstResourceSpan', $result);
        $this->assertEquals(1, $result['spanCount']);
        $this->assertCount(1, $result['spans']);
    }
    
    public function testValidatePayloadStructureWithMultipleSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [],
                    'scopeSpans' => [
                        [
                            'spans' => [
                                ['traceId' => '1', 'spanId' => '1', 'name' => 'span1'],
                                ['traceId' => '1', 'spanId' => '2', 'name' => 'span2'],
                                ['traceId' => '1', 'spanId' => '3', 'name' => 'span3'],
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNotNull($result);
        $this->assertEquals(3, $result['spanCount']);
        $this->assertCount(3, $result['spans']);
    }
    
    public function testValidatePayloadStructureWithEmptyPayload(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $result = $method->invoke($model, []);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithMissingResourceSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = ['otherKey' => 'value'];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithEmptyResourceSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = ['resourceSpans' => []];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithInvalidResourceSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = ['resourceSpans' => ['not-an-array']];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithMissingScopeSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [],
                    // Missing scopeSpans
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithEmptyScopeSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [],
                    'scopeSpans' => []
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithMissingSpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [],
                    'scopeSpans' => [
                        [
                            // Missing spans
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithEmptySpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [],
                    'scopeSpans' => [
                        [
                            'spans' => []
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructureWithNonArraySpans(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $payload = [
            'resourceSpans' => [
                [
                    'resource' => [],
                    'scopeSpans' => [
                        [
                            'spans' => 'not-an-array'
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNull($result);
    }
    
    public function testValidatePayloadStructurePreservesFirstResourceSpan(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'validatePayloadStructure');
        
        $firstResourceSpan = [
            'resource' => [
                'attributes' => [
                    ['key' => 'service.name', 'value' => ['stringValue' => 'test']]
                ]
            ],
            'scopeSpans' => [
                [
                    'spans' => [
                        ['traceId' => '1', 'spanId' => '1', 'name' => 'span1']
                    ]
                ]
            ]
        ];
        
        $payload = [
            'resourceSpans' => [$firstResourceSpan]
        ];
        
        $result = $method->invoke($model, $payload);
        
        $this->assertNotNull($result);
        $this->assertEquals($firstResourceSpan, $result['firstResourceSpan']);
    }
    
    // ==========================================
    // buildOtlpSpan() Helper Method Tests (Private)
    // ==========================================
    
    public function testBuildOtlpSpanWithValidData(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            'trace_id' => '1234567890abcdef1234567890abcdef',
            'span_id' => 'abcdef1234567890',
            'name' => 'test-span',
            'kind' => TraceKitModel::SPAN_KIND_SERVER,
            'start_time' => 1000000000,
            'end_time' => 2000000000,
            'status' => TraceKitModel::STATUS_OK,
            'attributes' => [],
        ];
        
        $otlpAttributes = [
            ['key' => 'attr1', 'value' => ['stringValue' => 'value1']]
        ];
        
        $otlpEvents = [
            ['name' => 'event1', 'timeUnixNano' => '1500000000', 'attributes' => []]
        ];
        
        $result = $method->invoke($model, $span, $otlpAttributes, $otlpEvents);
        
        $this->assertEquals('1234567890abcdef1234567890abcdef', $result['traceId']);
        $this->assertEquals('abcdef1234567890', $result['spanId']);
        $this->assertEquals('test-span', $result['name']);
        $this->assertEquals(TraceKitModel::SPAN_KIND_SERVER, $result['kind']);
        $this->assertEquals('1000000000', $result['startTimeUnixNano']);
        $this->assertEquals('2000000000', $result['endTimeUnixNano']);
        $this->assertEquals($otlpAttributes, $result['attributes']);
        $this->assertEquals($otlpEvents, $result['events']);
        $this->assertEquals('STATUS_CODE_OK', $result['status']['code']);
        $this->assertEquals('', $result['status']['message']);
        $this->assertArrayNotHasKey('parentSpanId', $result);
    }
    
    public function testBuildOtlpSpanWithParentSpanId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            'trace_id' => 'trace1',
            'span_id' => 'span1',
            'name' => 'child-span',
            'kind' => TraceKitModel::SPAN_KIND_INTERNAL,
            'start_time' => 1000,
            'end_time' => 2000,
            'status' => TraceKitModel::STATUS_OK,
            'parent_span_id' => 'parent-span-id',
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $span, [], []);
        
        $this->assertEquals('parent-span-id', $result['parentSpanId']);
    }
    
    public function testBuildOtlpSpanWithErrorStatus(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            'trace_id' => 'trace1',
            'span_id' => 'span1',
            'name' => 'error-span',
            'kind' => TraceKitModel::SPAN_KIND_SERVER,
            'start_time' => 1000,
            'end_time' => 2000,
            'status' => TraceKitModel::STATUS_ERROR,
            'attributes' => [
                'error.message' => 'Test error message',
            ],
        ];
        
        $result = $method->invoke($model, $span, [], []);
        
        $this->assertEquals('STATUS_CODE_ERROR', $result['status']['code']);
        $this->assertEquals('Test error message', $result['status']['message']);
    }
    
    public function testBuildOtlpSpanWithErrorStatusNoMessage(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            'trace_id' => 'trace1',
            'span_id' => 'span1',
            'name' => 'error-span',
            'kind' => TraceKitModel::SPAN_KIND_SERVER,
            'start_time' => 1000,
            'end_time' => 2000,
            'status' => TraceKitModel::STATUS_ERROR,
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $span, [], []);
        
        $this->assertEquals('STATUS_CODE_ERROR', $result['status']['code']);
        $this->assertEquals('Error', $result['status']['message']);
    }
    
    public function testBuildOtlpSpanWithNullParentSpanId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            'trace_id' => 'trace1',
            'span_id' => 'span1',
            'name' => 'root-span',
            'kind' => TraceKitModel::SPAN_KIND_SERVER,
            'start_time' => 1000,
            'end_time' => 2000,
            'status' => TraceKitModel::STATUS_OK,
            'parent_span_id' => null,
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $span, [], []);
        
        $this->assertArrayNotHasKey('parentSpanId', $result);
    }
    
    public function testBuildOtlpSpanWithMissingFields(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            // Missing most fields
        ];
        
        $result = $method->invoke($model, $span, [], []);
        
        $this->assertEquals('', $result['traceId']);
        $this->assertEquals('', $result['spanId']);
        $this->assertEquals('', $result['name']);
        $this->assertEquals(TraceKitModel::SPAN_KIND_INTERNAL, $result['kind']); // Default
        $this->assertEquals('0', $result['startTimeUnixNano']);
        $this->assertEquals('0', $result['endTimeUnixNano']);
        $this->assertEquals('STATUS_CODE_OK', $result['status']['code']); // Default
    }
    
    public function testBuildOtlpSpanWithAllSpanKinds(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $kinds = [
            TraceKitModel::SPAN_KIND_UNSPECIFIED,
            TraceKitModel::SPAN_KIND_INTERNAL,
            TraceKitModel::SPAN_KIND_SERVER,
            TraceKitModel::SPAN_KIND_CLIENT,
            TraceKitModel::SPAN_KIND_PRODUCER,
            TraceKitModel::SPAN_KIND_CONSUMER,
        ];
        
        foreach ($kinds as $kind) {
            $span = [
                'trace_id' => 'trace1',
                'span_id' => 'span1',
                'name' => 'test',
                'kind' => $kind,
                'start_time' => 1000,
                'end_time' => 2000,
                'status' => TraceKitModel::STATUS_OK,
                'attributes' => [],
            ];
            
            $result = $method->invoke($model, $span, [], []);
            $this->assertEquals($kind, $result['kind']);
        }
    }
    
    public function testBuildOtlpSpanPreservesOtlpAttributesAndEvents(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpSpan');
        
        $span = [
            'trace_id' => 'trace1',
            'span_id' => 'span1',
            'name' => 'test',
            'kind' => TraceKitModel::SPAN_KIND_INTERNAL,
            'start_time' => 1000,
            'end_time' => 2000,
            'status' => TraceKitModel::STATUS_OK,
            'attributes' => [],
        ];
        
        $otlpAttributes = [
            ['key' => 'attr1', 'value' => ['stringValue' => 'val1']],
            ['key' => 'attr2', 'value' => ['stringValue' => 'val2']],
        ];
        
        $otlpEvents = [
            ['name' => 'event1', 'timeUnixNano' => '1500', 'attributes' => []],
            ['name' => 'event2', 'timeUnixNano' => '1600', 'attributes' => []],
        ];
        
        $result = $method->invoke($model, $span, $otlpAttributes, $otlpEvents);
        
        $this->assertEquals($otlpAttributes, $result['attributes']);
        $this->assertEquals($otlpEvents, $result['events']);
    }
    
    // ==========================================
    // buildOtlpAttribute() Helper Method Tests (Private)
    // ==========================================
    
    public function testBuildOtlpAttributeWithStringValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpAttribute');
        
        $result = $method->invoke($model, 'test-key', 'test-value');
        
        $this->assertEquals('test-key', $result['key']);
        $this->assertEquals('test-value', $result['value']['stringValue']);
    }
    
    public function testBuildOtlpAttributeWithNumericValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpAttribute');
        
        $result = $method->invoke($model, 'count', 42);
        
        $this->assertEquals('count', $result['key']);
        $this->assertEquals('42', $result['value']['stringValue']);
    }
    
    public function testBuildOtlpAttributeWithFloatValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpAttribute');
        
        $result = $method->invoke($model, 'rate', 3.14);
        
        $this->assertEquals('rate', $result['key']);
        $this->assertEquals('3.14', $result['value']['stringValue']);
    }
    
    public function testBuildOtlpAttributeWithNonStringNonNumericValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpAttribute');
        
        $result = $method->invoke($model, 'test', null);
        
        $this->assertEquals('test', $result['key']);
        $this->assertEquals('', $result['value']['stringValue']);
    }
    
    public function testBuildOtlpAttributeWithArrayValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpAttribute');
        
        $result = $method->invoke($model, 'test', ['not', 'an', 'array']);
        
        $this->assertEquals('test', $result['key']);
        $this->assertEquals('', $result['value']['stringValue']);
    }
    
    public function testBuildOtlpAttributeWithBooleanValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpAttribute');
        
        $result = $method->invoke($model, 'enabled', true);
        
        $this->assertEquals('enabled', $result['key']);
        // Boolean values are not string or numeric, so they become empty string
        $this->assertEquals('', $result['value']['stringValue']);
    }
    
    // ==========================================
    // extractServiceNameFromPayload() Helper Method Tests (Private)
    // ==========================================
    
    public function testExtractServiceNameFromPayloadWithValidServiceName(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [
            'resource' => [
                'attributes' => [
                    [
                        'key' => 'service.name',
                        'value' => [
                            'stringValue' => 'my-service'
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('my-service', $result);
    }
    
    public function testExtractServiceNameFromPayloadWithMissingResource(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('unknown', $result);
    }
    
    public function testExtractServiceNameFromPayloadWithMissingAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [
            'resource' => []
        ];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('unknown', $result);
    }
    
    public function testExtractServiceNameFromPayloadWithEmptyAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [
            'resource' => [
                'attributes' => []
            ]
        ];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('unknown', $result);
    }
    
    public function testExtractServiceNameFromPayloadWithMissingValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [
            'resource' => [
                'attributes' => [
                    [
                        'key' => 'service.name'
                        // Missing 'value'
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('unknown', $result);
    }
    
    public function testExtractServiceNameFromPayloadWithNonStringValue(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [
            'resource' => [
                'attributes' => [
                    [
                        'key' => 'service.name',
                        'value' => [
                            'stringValue' => 12345 // Non-string
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('unknown', $result);
    }
    
    public function testExtractServiceNameFromPayloadWithComplexStructure(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'extractServiceNameFromPayload');
        
        $firstResourceSpan = [
            'resource' => [
                'attributes' => [
                    [
                        'key' => 'service.name',
                        'value' => [
                            'stringValue' => 'production-service'
                        ]
                    ],
                    [
                        'key' => 'other.attr',
                        'value' => ['stringValue' => 'other-value']
                    ]
                ]
            ],
            'scopeSpans' => []
        ];
        
        $result = $method->invoke($model, $firstResourceSpan);
        
        $this->assertEquals('production-service', $result);
    }
    
    // ==========================================
    // buildOtlpEvent() Helper Method Tests (Private)
    // ==========================================
    
    public function testBuildOtlpEventWithValidEvent(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 1000000000,
            'attributes' => [
                'attr1' => 'value1',
                'attr2' => 42,
            ],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertEquals('1000000000', $result['timeUnixNano']);
        $this->assertIsArray($result['attributes']);
        $this->assertCount(2, $result['attributes']);
        $this->assertEquals('attr1', $result['attributes'][0]['key']);
        $this->assertEquals('value1', $result['attributes'][0]['value']['stringValue']);
    }
    
    public function testBuildOtlpEventWithEmptyAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 1000000000,
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertEquals('1000000000', $result['timeUnixNano']);
        $this->assertIsArray($result['attributes']);
        $this->assertEmpty($result['attributes']);
    }
    
    public function testBuildOtlpEventWithMissingName(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'time' => 1000000000,
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('event', $result['name']); // Default name
        $this->assertEquals('1000000000', $result['timeUnixNano']);
    }
    
    public function testBuildOtlpEventWithNonStringName(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 12345, // Non-string
            'time' => 1000000000,
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('event', $result['name']); // Default name
    }
    
    public function testBuildOtlpEventWithMissingTime(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertEquals('0', $result['timeUnixNano']); // Default time
    }
    
    public function testBuildOtlpEventWithNonIntTime(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 'not-an-int',
            'attributes' => [],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertEquals('0', $result['timeUnixNano']); // Default time
    }
    
    public function testBuildOtlpEventWithMissingAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 1000000000,
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertIsArray($result['attributes']);
        $this->assertEmpty($result['attributes']);
    }
    
    public function testBuildOtlpEventWithNonArrayAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 1000000000,
            'attributes' => 'not-an-array',
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertIsArray($result['attributes']);
        $this->assertEmpty($result['attributes']);
    }
    
    public function testBuildOtlpEventWithMultipleAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 1000000000,
            'attributes' => [
                'attr1' => 'value1',
                'attr2' => 42,
                'attr3' => 3.14,
                'attr4' => 'string-value',
            ],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertCount(4, $result['attributes']);
        
        // Verify all attributes are properly formatted
        foreach ($result['attributes'] as $attr) {
            $this->assertArrayHasKey('key', $attr);
            $this->assertArrayHasKey('value', $attr);
            $this->assertArrayHasKey('stringValue', $attr['value']);
        }
    }
    
    public function testBuildOtlpEventWithNumericAttributeKeys(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'buildOtlpEvent');
        
        $event = [
            'name' => 'test-event',
            'time' => 1000000000,
            'attributes' => [
                0 => 'value1',
                1 => 'value2',
            ],
        ];
        
        $result = $method->invoke($model, $event);
        
        $this->assertEquals('test-event', $result['name']);
        $this->assertCount(2, $result['attributes']);
        $this->assertEquals('0', $result['attributes'][0]['key']);
        $this->assertEquals('1', $result['attributes'][1]['key']);
    }
    
    public function testCreateSpanDataNormalizesAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $attributes = [
            'string' => 'value',
            'number' => 42,
            'float' => 3.14,
            'bool' => true,
            'array' => ['nested' => 'data'],
        ];
        
        $result = $method->invoke($model, 'trace1', 'span1', null, 'test', TraceKitModel::SPAN_KIND_SERVER, 1000, $attributes);
        
        $this->assertIsArray($result['attributes']);
        $this->assertEquals('value', $result['attributes']['string']);
        $this->assertEquals(42, $result['attributes']['number']);
        $this->assertEquals(3.14, $result['attributes']['float']);
        $this->assertTrue($result['attributes']['bool']);
        $this->assertIsArray($result['attributes']['array']);
    }
    
    public function testCreateSpanDataWithAllSpanKinds(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $kinds = [
            TraceKitModel::SPAN_KIND_UNSPECIFIED,
            TraceKitModel::SPAN_KIND_INTERNAL,
            TraceKitModel::SPAN_KIND_SERVER,
            TraceKitModel::SPAN_KIND_CLIENT,
            TraceKitModel::SPAN_KIND_PRODUCER,
            TraceKitModel::SPAN_KIND_CONSUMER,
        ];
        
        foreach ($kinds as $kind) {
            $result = $method->invoke($model, 'trace1', 'span1', null, 'test', $kind, 1000);
            $this->assertEquals($kind, $result['kind'], "Failed for span kind: {$kind}");
        }
    }
    
    public function testCreateSpanDataInitializesDefaultValues(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $result = $method->invoke($model, 'trace1', 'span1', null, 'test', TraceKitModel::SPAN_KIND_SERVER, 1000);
        
        // Verify default values
        $this->assertNull($result['end_time']);
        $this->assertNull($result['duration']);
        $this->assertEquals(TraceKitModel::STATUS_OK, $result['status']);
        $this->assertIsArray($result['events']);
        $this->assertEmpty($result['events']);
        $this->assertIsArray($result['attributes']);
    }
    
    public function testCreateSpanDataWithEmptyAttributes(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $result = $method->invoke($model, 'trace1', 'span1', null, 'test', TraceKitModel::SPAN_KIND_SERVER, 1000, []);
        
        $this->assertIsArray($result['attributes']);
        $this->assertEmpty($result['attributes']);
    }
    
    public function testCreateSpanDataWithLongIds(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        // Test with realistic hex IDs
        $traceId = '1234567890abcdef1234567890abcdef';
        $spanId = 'a1b2c3d4e5f6g7h8';
        $startTime = 1699123456789000000; // Nanoseconds
        
        $result = $method->invoke($model, $traceId, $spanId, null, 'test', TraceKitModel::SPAN_KIND_SERVER, $startTime);
        
        $this->assertEquals($traceId, $result['trace_id']);
        $this->assertEquals($spanId, $result['span_id']);
        $this->assertEquals($startTime, $result['start_time']);
    }
    
    public function testCreateSpanDataPreservesExactValues(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'createSpanData');
        
        $traceId = 'exact-trace-id';
        $spanId = 'exact-span-id';
        $parentSpanId = 'exact-parent-id';
        $name = 'exact-operation-name';
        $kind = TraceKitModel::SPAN_KIND_CLIENT;
        $startTime = 987654321;
        
        $result = $method->invoke($model, $traceId, $spanId, $parentSpanId, $name, $kind, $startTime);
        
        // Verify exact values are preserved
        $this->assertSame($traceId, $result['trace_id']);
        $this->assertSame($spanId, $result['span_id']);
        $this->assertSame($parentSpanId, $result['parent_span_id']);
        $this->assertSame($name, $result['name']);
        $this->assertSame($kind, $result['kind']);
        $this->assertSame($startTime, $result['start_time']);
    }
    
    // ==========================================
    // getTraceIdOrGenerate() Helper Method Tests (Private)
    // ==========================================
    
    public function testGetTraceIdOrGenerateCreatesTraceIdWhenNull(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'getTraceIdOrGenerate');
        
        // Ensure traceId is null
        $reflection = new \ReflectionClass($model);
        $traceIdProperty = $reflection->getProperty('traceId');
        $traceIdProperty->setAccessible(true);
        $traceIdProperty->setValue($model, null);
        
        $result = $method->invoke($model);
        
        $this->assertIsString($result);
        $this->assertEquals(32, strlen($result)); // 32 hex characters
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $result);
    }
    
    public function testGetTraceIdOrGenerateReturnsExistingTraceId(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'getTraceIdOrGenerate');
        
        // Set existing traceId
        $existingTraceId = '1234567890abcdef1234567890abcdef';
        $reflection = new \ReflectionClass($model);
        $traceIdProperty = $reflection->getProperty('traceId');
        $traceIdProperty->setAccessible(true);
        $traceIdProperty->setValue($model, $existingTraceId);
        
        $result = $method->invoke($model);
        
        $this->assertEquals($existingTraceId, $result);
    }
    
    public function testGetTraceIdOrGenerateGeneratesUniqueIds(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $method = $this->getPrivateMethod($model, 'getTraceIdOrGenerate');
        
        // Ensure traceId is null
        $reflection = new \ReflectionClass($model);
        $traceIdProperty = $reflection->getProperty('traceId');
        $traceIdProperty->setAccessible(true);
        $traceIdProperty->setValue($model, null);
        
        $result1 = $method->invoke($model);
        $traceIdProperty->setValue($model, null);
        $result2 = $method->invoke($model);
        
        // Should generate different IDs
        $this->assertNotEquals($result1, $result2);
    }
    
    // ==========================================
    // addEventToSpan() Helper Method Tests (Private)
    // ==========================================
    
    public function testAddEventToSpanAddsEventToEmptyEventsArray(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        
        // Get span index
        $reflection = new \ReflectionClass($model);
        $spansProperty = $reflection->getProperty('spans');
        $spansProperty->setAccessible(true);
        $spans = $spansProperty->getValue($model);
        $spanIndex = 0; // First span
        
        $method = $this->getPrivateMethod($model, 'addEventToSpan');
        $event = [
            'name' => 'test-event',
            'time' => 1234567890,
            'attributes' => ['key' => 'value'],
        ];
        
        $method->invoke($model, $spanIndex, $event);
        
        $spans = $spansProperty->getValue($model);
        $this->assertIsArray($spans[$spanIndex]['events']);
        $this->assertCount(1, $spans[$spanIndex]['events']);
        $this->assertEquals('test-event', $spans[$spanIndex]['events'][0]['name']);
    }
    
    public function testAddEventToSpanAddsEventToExistingEventsArray(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        $model->addEvent($span, 'first-event');
        
        // Get span index
        $reflection = new \ReflectionClass($model);
        $spansProperty = $reflection->getProperty('spans');
        $spansProperty->setAccessible(true);
        $spans = $spansProperty->getValue($model);
        $spanIndex = 0; // First span
        
        $method = $this->getPrivateMethod($model, 'addEventToSpan');
        $event = [
            'name' => 'second-event',
            'time' => 1234567890,
            'attributes' => ['key' => 'value'],
        ];
        
        $method->invoke($model, $spanIndex, $event);
        
        $spans = $spansProperty->getValue($model);
        $this->assertCount(2, $spans[$spanIndex]['events']);
        $this->assertEquals('first-event', $spans[$spanIndex]['events'][0]['name']);
        $this->assertEquals('second-event', $spans[$spanIndex]['events'][1]['name']);
    }
    
    public function testAddEventToSpanInitializesEventsArrayIfNotSet(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        
        // Manually remove events array to test initialization
        $reflection = new \ReflectionClass($model);
        $spansProperty = $reflection->getProperty('spans');
        $spansProperty->setAccessible(true);
        $spans = $spansProperty->getValue($model);
        unset($spans[0]['events']);
        $spansProperty->setValue($model, $spans);
        
        $method = $this->getPrivateMethod($model, 'addEventToSpan');
        $event = [
            'name' => 'test-event',
            'time' => 1234567890,
            'attributes' => [],
        ];
        
        $method->invoke($model, 0, $event);
        
        $spans = $spansProperty->getValue($model);
        $this->assertIsArray($spans[0]['events']);
        $this->assertCount(1, $spans[0]['events']);
    }
    
    public function testAddEventToSpanPreservesExistingEvents(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        $model->addEvent($span, 'event1');
        $model->addEvent($span, 'event2');
        
        // Get span index
        $reflection = new \ReflectionClass($model);
        $spansProperty = $reflection->getProperty('spans');
        $spansProperty->setAccessible(true);
        $spans = $spansProperty->getValue($model);
        $spanIndex = 0;
        
        $method = $this->getPrivateMethod($model, 'addEventToSpan');
        $event = [
            'name' => 'event3',
            'time' => 1234567890,
            'attributes' => [],
        ];
        
        $method->invoke($model, $spanIndex, $event);
        
        $spans = $spansProperty->getValue($model);
        $this->assertCount(3, $spans[$spanIndex]['events']);
        $this->assertEquals('event1', $spans[$spanIndex]['events'][0]['name']);
        $this->assertEquals('event2', $spans[$spanIndex]['events'][1]['name']);
        $this->assertEquals('event3', $spans[$spanIndex]['events'][2]['name']);
    }
    
    public function testAddEventToSpanWithComplexEvent(): void
    {
        $model = new TraceKitModel(['api_key' => 'test-key']);
        $span = $model->startTrace('test');
        
        $method = $this->getPrivateMethod($model, 'addEventToSpan');
        $event = [
            'name' => 'complex-event',
            'time' => 1699123456789000000,
            'attributes' => [
                'attr1' => 'value1',
                'attr2' => 42,
                'attr3' => true,
            ],
        ];
        
        $method->invoke($model, 0, $event);
        
        $reflection = new \ReflectionClass($model);
        $spansProperty = $reflection->getProperty('spans');
        $spansProperty->setAccessible(true);
        $spans = $spansProperty->getValue($model);
        
        $addedEvent = $spans[0]['events'][0];
        $this->assertEquals('complex-event', $addedEvent['name']);
        $this->assertEquals(1699123456789000000, $addedEvent['time']);
        $this->assertIsArray($addedEvent['attributes']);
        $this->assertEquals('value1', $addedEvent['attributes']['attr1']);
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


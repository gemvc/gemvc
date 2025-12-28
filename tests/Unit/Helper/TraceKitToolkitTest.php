<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Gemvc\Helper\TraceKitToolkit;
use Gemvc\Http\JsonResponse;

class TraceKitToolkitTest extends TestCase
{
    private array $originalEnv;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original environment
        $this->originalEnv = [
            'TRACEKIT_API_KEY' => $_ENV['TRACEKIT_API_KEY'] ?? null,
            'TRACEKIT_BASE_URL' => $_ENV['TRACEKIT_BASE_URL'] ?? null,
            'TRACEKIT_SERVICE_NAME' => $_ENV['TRACEKIT_SERVICE_NAME'] ?? null,
        ];
        
        // Clear environment
        unset($_ENV['TRACEKIT_API_KEY']);
        unset($_ENV['TRACEKIT_BASE_URL']);
        unset($_ENV['TRACEKIT_SERVICE_NAME']);
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
        
        parent::tearDown();
    }
    
    // ==========================================
    // Constructor Tests
    // ==========================================
    
    public function testConstructorWithParameters(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key', 'test-service');
        
        $this->assertInstanceOf(TraceKitToolkit::class, $toolkit);
    }
    
    public function testConstructorWithDefaults(): void
    {
        $toolkit = new TraceKitToolkit();
        
        $this->assertInstanceOf(TraceKitToolkit::class, $toolkit);
    }
    
    public function testConstructorWithEnvironmentVariables(): void
    {
        $_ENV['TRACEKIT_API_KEY'] = 'env-api-key';
        $_ENV['TRACEKIT_BASE_URL'] = 'https://env.tracekit.dev';
        $_ENV['TRACEKIT_SERVICE_NAME'] = 'env-service';
        
        $toolkit = new TraceKitToolkit();
        
        $this->assertInstanceOf(TraceKitToolkit::class, $toolkit);
    }
    
    // ==========================================
    // Configuration Methods Tests
    // ==========================================
    
    public function testSetApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $result = $toolkit->setApiKey('new-api-key');
        
        $this->assertSame($toolkit, $result);
    }
    
    public function testSetServiceName(): void
    {
        $toolkit = new TraceKitToolkit();
        $result = $toolkit->setServiceName('new-service');
        
        $this->assertSame($toolkit, $result);
    }
    
    // ==========================================
    // Service Registration Tests
    // ==========================================
    
    public function testRegisterServiceReturnsJsonResponse(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->registerService('test@example.com');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testRegisterServiceWithOrganizationName(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->registerService('test@example.com', 'Test Org');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testRegisterServiceWithSource(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->registerService('test@example.com', null, 'custom-source');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testRegisterServiceWithMetadata(): void
    {
        $toolkit = new TraceKitToolkit();
        $metadata = ['version' => '1.0.0', 'environment' => 'test'];
        $response = $toolkit->registerService('test@example.com', null, 'gemvc', $metadata);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testVerifyCodeReturnsJsonResponse(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->verifyCode('session-123', '123456');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetStatusReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->getStatus();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    // ==========================================
    // Health Check Tests
    // ==========================================
    
    public function testSendHeartbeatReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->sendHeartbeat();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testSendHeartbeatWithStatus(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->sendHeartbeat('healthy');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testSendHeartbeatWithMetadata(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $metadata = ['memory_usage' => '50MB', 'cpu_usage' => '25%'];
        $response = $toolkit->sendHeartbeat('healthy', $metadata);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testSendHeartbeatAsync(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $toolkit->sendHeartbeatAsync('healthy');
        
        // Should not throw
        $this->assertTrue(true);
    }
    
    public function testSendHeartbeatAsyncWithMetadata(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $metadata = ['memory' => '100MB'];
        $toolkit->sendHeartbeatAsync('degraded', $metadata);
        
        $this->assertTrue(true);
    }
    
    public function testListHealthChecksReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->listHealthChecks();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    // ==========================================
    // Metrics & Alerts Tests
    // ==========================================
    
    public function testGetMetricsReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->getMetrics();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testGetMetricsWithWindow(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getMetrics('1h');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetAlertsSummaryReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->getAlertsSummary();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testGetActiveAlertsReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->getActiveAlerts();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testGetActiveAlertsWithLimit(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getActiveAlerts(100);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    // ==========================================
    // Webhook Management Tests
    // ==========================================
    
    public function testCreateWebhookReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->createWebhook('test-webhook', 'https://example.com/webhook', ['alert.created']);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testCreateWebhookWithEvents(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $events = ['alert.created', 'alert.resolved'];
        $response = $toolkit->createWebhook('test-webhook', 'https://example.com/webhook', $events);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateWebhookWithEnabledFlag(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createWebhook('test-webhook', 'https://example.com/webhook', ['alert.created'], false);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testListWebhooksReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->listWebhooks();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    // ==========================================
    // Subscription & Billing Tests
    // ==========================================
    
    public function testGetSubscriptionReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->getSubscription();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testListPlans(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->listPlans();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateCheckoutSessionReturnsUnauthorizedWhenNoApiKey(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->createCheckoutSession('starter', 'monthly');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->response_code);
    }
    
    public function testCreateCheckoutSessionWithAllParameters(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createCheckoutSession(
            'pro',
            'yearly',
            'gemvc',
            'https://example.com/success',
            'https://example.com/cancel'
        );
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    // ==========================================
    // Additional Edge Cases for Better Coverage
    // ==========================================
    
    public function testRegisterServiceWithEmptyEmail(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->registerService('');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testRegisterServiceWithEmptySource(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->registerService('test@example.com', null, '');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testRegisterServiceWithComplexMetadata(): void
    {
        $toolkit = new TraceKitToolkit();
        $metadata = [
            'version' => '1.0.0',
            'environment' => 'production',
            'framework' => 'gemvc',
            'php_version' => PHP_VERSION,
        ];
        $response = $toolkit->registerService('test@example.com', null, 'gemvc', $metadata);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testVerifyCodeWithEmptySessionId(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->verifyCode('', '123456');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testVerifyCodeWithEmptyCode(): void
    {
        $toolkit = new TraceKitToolkit();
        $response = $toolkit->verifyCode('session-123', '');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetStatusWithApiKey(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getStatus();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testSendHeartbeatWithEmptyStatus(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->sendHeartbeat('');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testSendHeartbeatWithComplexMetadata(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $metadata = [
            'memory_usage' => '50MB',
            'cpu_usage' => '25%',
            'disk_usage' => '75%',
            'active_connections' => 100,
        ];
        $response = $toolkit->sendHeartbeat('healthy', $metadata);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testSendHeartbeatAsyncWithEmptyStatus(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $toolkit->sendHeartbeatAsync('');
        
        $this->assertTrue(true);
    }
    
    public function testSendHeartbeatAsyncWithComplexMetadata(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $metadata = ['memory' => '100MB', 'cpu' => '50%'];
        $toolkit->sendHeartbeatAsync('degraded', $metadata);
        
        $this->assertTrue(true);
    }
    
    public function testListHealthChecksWithApiKey(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->listHealthChecks();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetMetricsWithDifferentWindows(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        $windows = ['5m', '15m', '1h', '6h', '24h', '7d'];
        foreach ($windows as $window) {
            $response = $toolkit->getMetrics($window);
            $this->assertInstanceOf(JsonResponse::class, $response);
        }
    }
    
    public function testGetMetricsWithInvalidWindow(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getMetrics('invalid-window');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetAlertsSummaryWithApiKey(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getAlertsSummary();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetActiveAlertsWithDifferentLimits(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        $limits = [10, 25, 50, 100, 200];
        foreach ($limits as $limit) {
            $response = $toolkit->getActiveAlerts($limit);
            $this->assertInstanceOf(JsonResponse::class, $response);
        }
    }
    
    public function testGetActiveAlertsWithZeroLimit(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getActiveAlerts(0);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetActiveAlertsWithNegativeLimit(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getActiveAlerts(-10);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateWebhookWithEmptyName(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createWebhook('', 'https://example.com/webhook', ['alert.created']);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateWebhookWithEmptyUrl(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createWebhook('test-webhook', '', ['alert.created']);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateWebhookWithEmptyEvents(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createWebhook('test-webhook', 'https://example.com/webhook', []);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateWebhookWithMultipleEvents(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $events = [
            'alert.created',
            'alert.resolved',
            'alert.updated',
            'service.registered',
        ];
        $response = $toolkit->createWebhook('test-webhook', 'https://example.com/webhook', $events);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateWebhookWithEnabledTrue(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createWebhook('test-webhook', 'https://example.com/webhook', ['alert.created'], true);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testListWebhooksWithApiKey(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->listWebhooks();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testGetSubscriptionWithApiKey(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->getSubscription();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateCheckoutSessionWithEmptyPlan(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createCheckoutSession('', 'monthly');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateCheckoutSessionWithEmptyBillingCycle(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createCheckoutSession('starter', '');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateCheckoutSessionWithEmptySource(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createCheckoutSession('starter', 'monthly', '');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateCheckoutSessionWithEmptyUrls(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createCheckoutSession('starter', 'monthly', 'gemvc', '', '');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    public function testCreateCheckoutSessionWithOnlySuccessUrl(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        $response = $toolkit->createCheckoutSession(
            'starter',
            'monthly',
            'gemvc',
            'https://example.com/success'
        );
        
        $this->assertInstanceOf(JsonResponse::class, $response);
    }
    
    // ==========================================
    // Constructor Edge Cases
    // ==========================================
    
    public function testConstructorWithEmptyApiKey(): void
    {
        $toolkit = new TraceKitToolkit('');
        
        $this->assertInstanceOf(TraceKitToolkit::class, $toolkit);
    }
    
    public function testConstructorWithEmptyServiceName(): void
    {
        $toolkit = new TraceKitToolkit('test-key', '');
        
        $this->assertInstanceOf(TraceKitToolkit::class, $toolkit);
    }
    
    public function testConstructorWithCustomBaseUrl(): void
    {
        $_ENV['TRACEKIT_BASE_URL'] = 'https://custom.tracekit.dev';
        $toolkit = new TraceKitToolkit();
        
        $this->assertInstanceOf(TraceKitToolkit::class, $toolkit);
    }
    
    // ==========================================
    // Setter Methods Edge Cases
    // ==========================================
    
    public function testSetApiKeyWithEmptyString(): void
    {
        $toolkit = new TraceKitToolkit('test-key');
        $result = $toolkit->setApiKey('');
        
        $this->assertSame($toolkit, $result);
    }
    
    public function testSetServiceNameWithEmptyString(): void
    {
        $toolkit = new TraceKitToolkit();
        $result = $toolkit->setServiceName('');
        
        $this->assertSame($toolkit, $result);
    }
    
    public function testSetServiceNameWithSpecialCharacters(): void
    {
        $toolkit = new TraceKitToolkit();
        $result = $toolkit->setServiceName('test-service-v1.0.0');
        
        $this->assertSame($toolkit, $result);
    }
    
    // ==========================================
    // Private Helper Methods Tests
    // ==========================================
    
    /**
     * Test requireApiKey() returns null when API key is set
     */
    public function testRequireApiKeyWithApiKeySet(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('requireApiKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($toolkit);
        
        $this->assertNull($result);
    }
    
    /**
     * Test requireApiKey() returns unauthorized response when API key is empty
     */
    public function testRequireApiKeyWithEmptyApiKey(): void
    {
        $toolkit = new TraceKitToolkit('');
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('requireApiKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($toolkit);
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(401, $result->response_code);
    }
    
    /**
     * Test createApiCall() with authentication and JSON headers
     */
    public function testCreateApiCallWithAuthAndJson(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('createApiCall');
        $method->setAccessible(true);
        
        $apiCall = $method->invoke($toolkit, true, true);
        
        $this->assertInstanceOf(\Gemvc\Http\ApiCall::class, $apiCall);
        $this->assertEquals('test-api-key', $apiCall->header['X-API-Key'] ?? null);
        $this->assertEquals('application/json', $apiCall->header['Content-Type'] ?? null);
    }
    
    /**
     * Test createApiCall() without authentication
     */
    public function testCreateApiCallWithoutAuth(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('createApiCall');
        $method->setAccessible(true);
        
        $apiCall = $method->invoke($toolkit, false, false);
        
        $this->assertInstanceOf(\Gemvc\Http\ApiCall::class, $apiCall);
        $this->assertArrayNotHasKey('X-API-Key', $apiCall->header);
        $this->assertArrayNotHasKey('Content-Type', $apiCall->header);
    }
    
    /**
     * Test createApiCall() with authentication but no JSON header
     */
    public function testCreateApiCallWithAuthNoJson(): void
    {
        $toolkit = new TraceKitToolkit('test-api-key');
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('createApiCall');
        $method->setAccessible(true);
        
        $apiCall = $method->invoke($toolkit, true, false);
        
        $this->assertInstanceOf(\Gemvc\Http\ApiCall::class, $apiCall);
        $this->assertEquals('test-api-key', $apiCall->header['X-API-Key'] ?? null);
        $this->assertArrayNotHasKey('Content-Type', $apiCall->header);
    }
    
    /**
     * Test parseJsonResponse() with valid JSON
     */
    public function testParseJsonResponseWithValidJson(): void
    {
        $toolkit = new TraceKitToolkit();
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('parseJsonResponse');
        $method->setAccessible(true);
        
        $json = json_encode(['id' => 1, 'name' => 'Test']);
        $result = $method->invoke($toolkit, $json, 'Test context');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->response_code);
        $this->assertIsArray($result->data);
        $this->assertEquals(1, $result->data['id'] ?? null);
    }
    
    /**
     * Test parseJsonResponse() with invalid JSON
     */
    public function testParseJsonResponseWithInvalidJson(): void
    {
        $toolkit = new TraceKitToolkit();
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('parseJsonResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($toolkit, 'invalid json', 'Test context');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(500, $result->response_code);
    }
    
    /**
     * Test parseJsonResponse() with false response
     */
    public function testParseJsonResponseWithFalseResponse(): void
    {
        $toolkit = new TraceKitToolkit();
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('parseJsonResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($toolkit, false, 'Test context');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(500, $result->response_code);
    }
    
    /**
     * Test parseJsonResponse() with non-array JSON
     */
    public function testParseJsonResponseWithNonArrayJson(): void
    {
        $toolkit = new TraceKitToolkit();
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('parseJsonResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($toolkit, '"string"', 'Test context');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(500, $result->response_code);
    }
    
    /**
     * Test parseJsonResponse() with null JSON
     */
    public function testParseJsonResponseWithNullJson(): void
    {
        $toolkit = new TraceKitToolkit();
        
        $reflection = new \ReflectionClass($toolkit);
        $method = $reflection->getMethod('parseJsonResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($toolkit, 'null', 'Test context');
        
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(500, $result->response_code);
    }
}


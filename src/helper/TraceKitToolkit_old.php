<?php
namespace Gemvc\Helper;

/**
 * TraceKit Toolkit - Client-Side Integration & Management
 * 
 * Provides full control over TraceKit service integration using TraceKit REST API.
 * This class handles account registration, health monitoring, metrics, alerts, and webhooks.
 * 
 * Features:
 * - Account registration and email verification
 * - Health check monitoring (heartbeats)
 * - Service status and metrics
 * - Alert management
 * - Webhook management
 * - Subscription & billing info
 * 
 * API Documentation: https://app.tracekit.dev/docs/integration/api
 * 
 * @package App\Model
 */
use Gemvc\Http\ApiCall;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class TraceKitToolkit_old
{
    private string $apiKey;
    private string $baseUrl;
    private string $serviceName;
    
    /**
     * Initialize TraceKitToolkit
     * 
     * @param string|null $apiKey TraceKit API key (optional, can be set later)
     * @param string|null $serviceName Service name (optional)
     */
    public function __construct(?string $apiKey = null, ?string $serviceName = null)
    {
        $this->apiKey = $apiKey ?? (is_string($_ENV['TRACEKIT_API_KEY'] ?? null) ? $_ENV['TRACEKIT_API_KEY'] : '');
        $this->baseUrl = is_string($_ENV['TRACEKIT_BASE_URL'] ?? null) ? $_ENV['TRACEKIT_BASE_URL'] : 'https://app.tracekit.dev';
        $this->serviceName = $serviceName ?? (is_string($_ENV['TRACEKIT_SERVICE_NAME'] ?? null) ? $_ENV['TRACEKIT_SERVICE_NAME'] : 'gemvc-app');
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Set service name
     * 
     * @param string $serviceName
     * @return self
     */
    public function setServiceName(string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }
    
    // ==========================================
    // Private Helper Methods
    // ==========================================
    
    /**
     * Check if API key is set, return unauthorized response if not
     * 
     * @return JsonResponse|null Returns unauthorized response if API key is empty, null otherwise
     */
    private function requireApiKey(): ?JsonResponse
    {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        return null;
    }
    
    /**
     * Create and configure ApiCall instance with common headers
     * 
     * @param bool $requireAuth Whether to include X-API-Key header (default: true)
     * @param bool $isJson Whether to include Content-Type: application/json header (default: false)
     * @return ApiCall Configured ApiCall instance
     */
    private function createApiCall(bool $requireAuth = true, bool $isJson = false): ApiCall
    {
        $apiCall = new ApiCall();
        
        if ($requireAuth && !empty($this->apiKey)) {
            $apiCall->header['X-API-Key'] = $this->apiKey;
        }
        
        if ($isJson) {
            $apiCall->header['Content-Type'] = 'application/json';
        }
        
        return $apiCall;
    }
    
    /**
     * Parse JSON response and handle errors
     * 
     * @param string|false $response Raw response string (can be false from ApiCall)
     * @param string $errorContext Context for error messages (e.g., 'Status check')
     * @return JsonResponse Parsed response or error response
     */
    private function parseJsonResponse(string|false $response, string $errorContext): JsonResponse
    {
        if ($response === false) {
            return Response::internalError('Invalid response from TraceKit');
        }
        
        $data = json_decode($response, true);
        if (!$data || !is_array($data)) {
            return Response::internalError('Invalid response from TraceKit');
        }
        return Response::success($data, 1, $errorContext . ' completed successfully');
    }
    
    /**
     * Make GET request with full error handling
     * 
     * @param string $endpoint API endpoint (relative to baseUrl)
     * @param bool $requireAuth Whether API key is required (default: true)
     * @param string $successMessage Success message for response
     * @return JsonResponse
     */
    private function makeGetRequest(string $endpoint, bool $requireAuth = true, string $successMessage = 'Request completed successfully'): JsonResponse
    {
        if ($requireAuth) {
            $unauthorized = $this->requireApiKey();
            if ($unauthorized !== null) {
                return $unauthorized;
            }
        }
        
        try {
            $apiCall = $this->createApiCall($requireAuth, false);
            $response = $apiCall->get($this->baseUrl . $endpoint);
            
            if ($apiCall->error) {
                return Response::badRequest($successMessage . ' failed: ' . $apiCall->error);
            }
            
            return $this->parseJsonResponse($response, $successMessage);
        } catch (\Throwable $e) {
            return Response::internalError($successMessage . ' error: ' . $e->getMessage());
        }
    }
    
    /**
     * Make POST request with full error handling
     * 
     * @param string $endpoint API endpoint (relative to baseUrl)
     * @param array<string, mixed> $payload Request payload
     * @param bool $requireAuth Whether API key is required (default: true)
     * @param string $successMessage Success message for response
     * @return JsonResponse
     */
    private function makePostRequest(string $endpoint, array $payload, bool $requireAuth = true, string $successMessage = 'Request completed successfully'): JsonResponse
    {
        if ($requireAuth) {
            $unauthorized = $this->requireApiKey();
            if ($unauthorized !== null) {
                return $unauthorized;
            }
        }
        
        try {
            $apiCall = $this->createApiCall($requireAuth, true);
            $response = $apiCall->post($this->baseUrl . $endpoint, $payload);
            
            if ($apiCall->error) {
                return Response::badRequest($successMessage . ' failed: ' . $apiCall->error);
            }
            
            return $this->parseJsonResponse($response, $successMessage);
        } catch (\Throwable $e) {
            return Response::internalError($successMessage . ' error: ' . $e->getMessage());
        }
    }
    
    // ==========================================
    // Account Registration & Verification
    // ==========================================
    
    /**
     * Register a new service in TraceKit
     * 
     * @param string $email Email address for verification
     * @param string|null $organizationName Optional organization name (auto-generated if empty)
     * @param string $source Partner/framework code (default: 'gemvc')
     * @param array<string, mixed> $sourceMetadata Optional metadata (version, environment, etc.)
     * @return JsonResponse
     */
    public function registerService(
        string $email,
        ?string $organizationName = null,
        string $source = 'gemvc',
        array $sourceMetadata = []
    ): JsonResponse {
        $payload = [
            'email' => $email,
            'service_name' => $this->serviceName,
            'source' => $source,
        ];
        
        if ($organizationName !== null) {
            $payload['organization_name'] = $organizationName;
        }
        
        if (!empty($sourceMetadata)) {
            $payload['source_metadata'] = $sourceMetadata;
        }
        
        $response = $this->makePostRequest('/v1/integrate/register', $payload, false, 'Registration');
        
        // Override success message for registration
        if ($response->response_code === 200) {
            return Response::success($response->data, 1, 'Verification code sent to email');
        }
        
        return $response;
    }
    
    /**
     * Verify email code and get API key
     * 
     * @param string $sessionId Session ID from registerService()
     * @param string $code 6-digit verification code from email
     * @return JsonResponse
     */
    public function verifyCode(string $sessionId, string $code): JsonResponse
    {
        $payload = [
            'session_id' => $sessionId,
            'code' => $code,
        ];
        
        $response = $this->makePostRequest('/v1/integrate/verify', $payload, false, 'Verification');
        
        // Update API key if provided
        if ($response->response_code === 200 && is_array($response->data)) {
            if (isset($response->data['api_key']) && is_string($response->data['api_key'])) {
                $this->apiKey = $response->data['api_key'];
            }
            return Response::success($response->data, 1, 'Service registered successfully');
        }
        
        return $response;
    }
    
    /**
     * Check integration status
     * 
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        return $this->makeGetRequest('/v1/integrate/status', true, 'Status check');
    }
    
    // ==========================================
    // Health Check Monitoring
    // ==========================================
    
    /**
     * Send heartbeat to TraceKit
     * 
     * @param string $status Service status: 'healthy', 'degraded', 'unhealthy'
     * @param array<string, mixed> $metadata Optional metadata (memory_usage, cpu_usage, etc.)
     * @return JsonResponse
     */
    public function sendHeartbeat(string $status = 'healthy', array $metadata = []): JsonResponse
    {
        $unauthorized = $this->requireApiKey();
        if ($unauthorized !== null) {
            return $unauthorized;
        }
        
        try {
            $apiCall = $this->createApiCall(true, true);
            $apiCall->setTimeouts(1, 3); // Short timeouts for heartbeats
            
            $payload = [
                'service_name' => $this->serviceName,
                'status' => $status,
            ];
            
            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }
            
            $response = $apiCall->post($this->baseUrl . '/v1/health/heartbeat', $payload);
            
            if ($apiCall->error) {
                return Response::badRequest('Heartbeat failed: ' . $apiCall->error);
            }
            
            return $this->parseJsonResponse($response, 'Heartbeat');
        } catch (\Throwable $e) {
            return Response::internalError('Heartbeat error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send heartbeat asynchronously (non-blocking)
     * 
     * @param string $status Service status
     * @param array<string, mixed> $metadata Optional metadata
     * @return void
     */
    public function sendHeartbeatAsync(string $status = 'healthy', array $metadata = []): void
    {
        register_shutdown_function(function() use ($status, $metadata) {
            try {
                $this->sendHeartbeat($status, $metadata);
            } catch (\Throwable $e) {
                error_log("TraceKit: Heartbeat failed: " . $e->getMessage());
            }
        });
    }
    
    /**
     * List health checks
     * 
     * @return JsonResponse
     */
    public function listHealthChecks(): JsonResponse
    {
        return $this->makeGetRequest('/api/health-checks', true, 'Health checks');
    }
    
    // ==========================================
    // Metrics & Alerts
    // ==========================================
    
    /**
     * Get service metrics
     * 
     * @param string $window Time window: '5m', '15m', '1h', '6h', '24h' (default: '15m')
     * @return JsonResponse
     */
    public function getMetrics(string $window = '15m'): JsonResponse
    {
        $endpoint = '/api/metrics/services/' . urlencode($this->serviceName) . '?window=' . urlencode($window);
        return $this->makeGetRequest($endpoint, true, 'Metrics');
    }
    
    /**
     * Get alerts summary
     * 
     * @return JsonResponse
     */
    public function getAlertsSummary(): JsonResponse
    {
        return $this->makeGetRequest('/v1/alerts/summary', true, 'Alerts summary');
    }
    
    /**
     * Get active alerts
     * 
     * @param int $limit Maximum number of alerts to return (default: 50)
     * @return JsonResponse
     */
    public function getActiveAlerts(int $limit = 50): JsonResponse
    {
        $endpoint = '/v1/alerts/active?limit=' . $limit;
        return $this->makeGetRequest($endpoint, true, 'Active alerts');
    }
    
    // ==========================================
    // Webhook Management
    // ==========================================
    
    /**
     * Create a webhook
     * 
     * @param string $name Webhook name
     * @param string $url Webhook URL
     * @param array<string> $events Event types to subscribe to
     * @param bool $enabled Whether webhook is enabled (default: true)
     * @return JsonResponse
     */
    public function createWebhook(
        string $name,
        string $url,
        array $events,
        bool $enabled = true
    ): JsonResponse {
        $payload = [
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'enabled' => $enabled,
        ];
        
        return $this->makePostRequest('/v1/webhooks', $payload, true, 'Webhook creation');
    }
    
    /**
     * List webhooks
     * 
     * @return JsonResponse
     */
    public function listWebhooks(): JsonResponse
    {
        return $this->makeGetRequest('/v1/webhooks', true, 'Webhooks');
    }
    
    // ==========================================
    // Subscription & Billing
    // ==========================================
    
    /**
     * Get current subscription info
     * 
     * @return JsonResponse
     */
    public function getSubscription(): JsonResponse
    {
        return $this->makeGetRequest('/v1/billing/subscription', true, 'Subscription');
    }
    
    /**
     * List available plans
     * 
     * @return JsonResponse
     */
    public function listPlans(): JsonResponse
    {
        return $this->makeGetRequest('/v1/billing/plans', false, 'Plans');
    }
    
    /**
     * Create checkout session for plan upgrade
     * 
     * @param string $planId Plan ID (e.g., 'starter', 'pro')
     * @param string $billingInterval 'monthly' or 'yearly'
     * @param string $source Source identifier (default: 'gemvc')
     * @param string|null $successUrl Optional success redirect URL
     * @param string|null $cancelUrl Optional cancel redirect URL
     * @return JsonResponse
     */
    public function createCheckoutSession(
        string $planId,
        string $billingInterval = 'monthly',
        string $source = 'gemvc',
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): JsonResponse {
        $payload = [
            'plan_id' => $planId,
            'billing_interval' => $billingInterval,
            'source' => $source,
        ];
        
        if ($successUrl !== null) {
            $payload['success_url'] = $successUrl;
        }
        
        if ($cancelUrl !== null) {
            $payload['cancel_url'] = $cancelUrl;
        }
        
        return $this->makePostRequest('/v1/billing/create-checkout-session', $payload, true, 'Checkout session creation');
    }
}


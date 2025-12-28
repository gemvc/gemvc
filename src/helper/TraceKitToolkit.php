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

class TraceKitToolkit
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
        $this->apiKey = $apiKey ?? $_ENV['TRACEKIT_API_KEY'] ?? '';
        $this->baseUrl = $_ENV['TRACEKIT_BASE_URL'] ?? 'https://app.tracekit.dev';
        $this->serviceName = $serviceName ?? $_ENV['TRACEKIT_SERVICE_NAME'] ?? 'gemvc-app';
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
    // Account Registration & Verification
    // ==========================================
    
    /**
     * Register a new service in TraceKit
     * 
     * @param string $email Email address for verification
     * @param string|null $organizationName Optional organization name (auto-generated if empty)
     * @param string $source Partner/framework code (default: 'gemvc')
     * @param array $sourceMetadata Optional metadata (version, environment, etc.)
     * @return JsonResponse
     */
    public function registerService(
        string $email,
        ?string $organizationName = null,
        string $source = 'gemvc',
        array $sourceMetadata = []
    ): JsonResponse {
        try {
            $apiCall = new ApiCall();
            $apiCall->header['Content-Type'] = 'application/json';
            
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
            
            $response = $apiCall->post($this->baseUrl . '/v1/integrate/register', $payload);
            
            if ($apiCall->error) {
                return Response::badRequest('Registration failed: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Verification code sent to email');
        } catch (\Throwable $e) {
            return Response::internalError('Registration error: ' . $e->getMessage());
        }
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
        try {
            $apiCall = new ApiCall();
            $apiCall->header['Content-Type'] = 'application/json';
            
            $payload = [
                'session_id' => $sessionId,
                'code' => $code,
            ];
            
            $response = $apiCall->post($this->baseUrl . '/v1/integrate/verify', $payload);
            
            if ($apiCall->error) {
                return Response::badRequest('Verification failed: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            // Update API key if provided
            if (isset($data['api_key'])) {
                $this->apiKey = $data['api_key'];
            }
            
            return Response::success($data, 1, 'Service registered successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Verification error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check integration status
     * 
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $response = $apiCall->get($this->baseUrl . '/v1/integrate/status');
            
            if ($apiCall->error) {
                return Response::badRequest('Status check failed: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Status retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Status check error: ' . $e->getMessage());
        }
    }
    
    // ==========================================
    // Health Check Monitoring
    // ==========================================
    
    /**
     * Send heartbeat to TraceKit
     * 
     * @param string $status Service status: 'healthy', 'degraded', 'unhealthy'
     * @param array $metadata Optional metadata (memory_usage, cpu_usage, etc.)
     * @return JsonResponse
     */
    public function sendHeartbeat(string $status = 'healthy', array $metadata = []): JsonResponse
    {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            $apiCall->header['Content-Type'] = 'application/json';
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
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Heartbeat sent successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Heartbeat error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send heartbeat asynchronously (non-blocking)
     * 
     * @param string $status Service status
     * @param array $metadata Optional metadata
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
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $response = $apiCall->get($this->baseUrl . '/api/health-checks');
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to list health checks: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Health checks retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Health checks error: ' . $e->getMessage());
        }
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
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $url = $this->baseUrl . '/api/metrics/services/' . urlencode($this->serviceName) . '?window=' . urlencode($window);
            $response = $apiCall->get($url);
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to get metrics: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Metrics retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Metrics error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get alerts summary
     * 
     * @return JsonResponse
     */
    public function getAlertsSummary(): JsonResponse
    {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $response = $apiCall->get($this->baseUrl . '/v1/alerts/summary');
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to get alerts summary: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Alerts summary retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Alerts summary error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get active alerts
     * 
     * @param int $limit Maximum number of alerts to return (default: 50)
     * @return JsonResponse
     */
    public function getActiveAlerts(int $limit = 50): JsonResponse
    {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $url = $this->baseUrl . '/v1/alerts/active?limit=' . $limit;
            $response = $apiCall->get($url);
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to get active alerts: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Active alerts retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Active alerts error: ' . $e->getMessage());
        }
    }
    
    // ==========================================
    // Webhook Management
    // ==========================================
    
    /**
     * Create a webhook
     * 
     * @param string $name Webhook name
     * @param string $url Webhook URL
     * @param array $events Event types to subscribe to
     * @param bool $enabled Whether webhook is enabled (default: true)
     * @return JsonResponse
     */
    public function createWebhook(
        string $name,
        string $url,
        array $events,
        bool $enabled = true
    ): JsonResponse {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            $apiCall->header['Content-Type'] = 'application/json';
            
            $payload = [
                'name' => $name,
                'url' => $url,
                'events' => $events,
                'enabled' => $enabled,
            ];
            
            $response = $apiCall->post($this->baseUrl . '/v1/webhooks', $payload);
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to create webhook: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Webhook created successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Webhook creation error: ' . $e->getMessage());
        }
    }
    
    /**
     * List webhooks
     * 
     * @return JsonResponse
     */
    public function listWebhooks(): JsonResponse
    {
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $response = $apiCall->get($this->baseUrl . '/v1/webhooks');
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to list webhooks: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Webhooks retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Webhooks error: ' . $e->getMessage());
        }
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
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            
            $response = $apiCall->get($this->baseUrl . '/v1/billing/subscription');
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to get subscription: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Subscription info retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Subscription error: ' . $e->getMessage());
        }
    }
    
    /**
     * List available plans
     * 
     * @return JsonResponse
     */
    public function listPlans(): JsonResponse
    {
        try {
            $apiCall = new ApiCall();
            
            $response = $apiCall->get($this->baseUrl . '/v1/billing/plans');
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to list plans: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Plans retrieved successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Plans error: ' . $e->getMessage());
        }
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
        if (empty($this->apiKey)) {
            return Response::unauthorized('API key not set');
        }
        
        try {
            $apiCall = new ApiCall();
            $apiCall->header['X-API-Key'] = $this->apiKey;
            $apiCall->header['Content-Type'] = 'application/json';
            
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
            
            $response = $apiCall->post($this->baseUrl . '/v1/billing/create-checkout-session', $payload);
            
            if ($apiCall->error) {
                return Response::badRequest('Failed to create checkout session: ' . $apiCall->error);
            }
            
            $data = json_decode($response, true);
            if (!$data) {
                return Response::internalError('Invalid response from TraceKit');
            }
            
            return Response::success($data, 1, 'Checkout session created successfully');
        } catch (\Throwable $e) {
            return Response::internalError('Checkout session error: ' . $e->getMessage());
        }
    }
}


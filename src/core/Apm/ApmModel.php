<?php
namespace Gemvc\Core\Apm;

use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Core\Apm\ApmToolkitInterface;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

/**
 * APM Model - Data logic layer for APM operations
 * 
 * This model handles APM provider operations without database dependencies.
 * It uses ApmFactory to work with any APM provider (TraceKit, Datadog, etc.)
 * and ApmToolkitInterface for provider-agnostic toolkit operations.
 */
class ApmModel
{
    /**
     * Get APM Toolkit instance for the configured provider
     * 
     * Uses ApmFactory to determine the provider name, then builds the toolkit
     * class name following the convention: Gemvc\Core\Apm\Providers\{ProviderName}\{ProviderName}Toolkit
     * 
     * @return ApmToolkitInterface|null Toolkit instance or null if not available
     */
    private function getToolkit(): ?ApmToolkitInterface
    {
        $providerName = ApmFactory::isEnabled();
        if ($providerName === null) {
            return null;
        }
        
        $toolkitClassName = "Gemvc\\Core\\Apm\\Providers\\{$providerName}\\{$providerName}Toolkit";
        
        if (!class_exists($toolkitClassName)) {
            return null;
        }
        
        try {
            /** @var ApmToolkitInterface */
            $instance = new $toolkitClassName();
            return $instance;
        } catch (\Throwable $e) {
            // Constructor can throw (e.g., if class doesn't implement interface properly or constructor fails)
            return null;
        }
    }
    /**
     * Test APM Provider with nested spans
     * 
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        $apm = ApmFactory::create(null);
        
        if ($apm === null || !$apm->isEnabled()) {
            $apmName = ApmFactory::isEnabled();
            return Response::success([
                'apm_enabled' => false,
                'message' => $apmName === null 
                    ? 'APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.'
                    : "APM provider '{$apmName}' package not installed. Install with: composer require gemvc/apm-{$apmName}",
                'trace_id' => null,
            ], 1, 'APM test - not enabled');
        }
        
        $rootSpan = $apm->startSpan('http-request', [
            'http.method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'http.url' => $_SERVER['REQUEST_URI'] ?? '/',
            'http.user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ], ApmInterface::SPAN_KIND_SERVER);
        
        if (empty($rootSpan)) {
            return Response::success([
                'apm_enabled' => true,
                'message' => 'APM test skipped (sampling or error)',
                'trace_id' => null,
            ], 1, 'APM test - skipped');
        }
        
        try {
            $dbSpan = $apm->startSpan('database-query', [
                'db.system' => 'mysql',
                'db.operation' => 'SELECT',
                'db.table' => 'users',
            ], ApmInterface::SPAN_KIND_CLIENT);
            
            usleep(50000);
            
            $apm->endSpan($dbSpan, [
                'db.rows_affected' => '5',
            ], ApmInterface::STATUS_OK);
            
            $apiSpan = $apm->startSpan('http-client-call', [
                'http.url' => 'https://api.example.com/data',
                'http.method' => 'GET',
            ], ApmInterface::SPAN_KIND_CLIENT);
            
            usleep(30000);
            
            $apm->endSpan($apiSpan, [
                'http.status_code' => '200',
                'response.size' => '1024',
            ], ApmInterface::STATUS_OK);
            
            $processSpan = $apm->startSpan('data-processing', [
                'operation' => 'transform',
                'items_count' => '5',
            ], ApmInterface::SPAN_KIND_INTERNAL);
            
            usleep(20000);
            
            $apm->endSpan($processSpan, [
                'processed_items' => '5',
            ], ApmInterface::STATUS_OK);
            
            $apm->endSpan($rootSpan, [
                'http.status_code' => '200',
            ], ApmInterface::STATUS_OK);
            
            $traceId = $apm->getTraceId();
            $apm->flush();
            
            return Response::success([
                'apm_enabled' => true,
                'trace_id' => $traceId,
                'message' => 'APM test completed successfully. Check APM dashboard for traces.',
                'spans_created' => 4,
            ], 1, 'APM test - success');
            
        } catch (\Exception $e) {
            $apm->recordException($rootSpan, $e);
            $traceId = $apm->getTraceId();
            $apm->endSpan($rootSpan, [
                'http.status_code' => '500',
            ], ApmInterface::STATUS_ERROR);
            $apm->flush();
            return Response::internalError('APM test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test APM Provider with Error
     * 
     * @return JsonResponse
     */
    public function testError(): JsonResponse
    {
        $apm = ApmFactory::create(null);
        
        if ($apm === null || !$apm->isEnabled()) {
            $apmName = ApmFactory::isEnabled();
            return Response::success([
                'apm_enabled' => false,
                'message' => $apmName === null 
                    ? 'APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.'
                    : "APM provider '{$apmName}' package not installed. Install with: composer require gemvc/apm-{$apmName}",
                'trace_id' => null,
            ], 1, 'APM error test - not enabled');
        }
        
        $rootSpan = $apm->startSpan('http-request', [
            'http.method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'http.url' => $_SERVER['REQUEST_URI'] ?? '/',
            'http.user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ], ApmInterface::SPAN_KIND_SERVER);
        
        if (empty($rootSpan)) {
            return Response::success([
                'apm_enabled' => true,
                'message' => 'APM error test skipped (sampling or error)',
                'trace_id' => null,
            ], 1, 'APM error test - skipped');
        }
        
        $processSpan = null;
        try {
            $dbSpan = $apm->startSpan('database-query', [
                'db.system' => 'mysql',
                'db.operation' => 'SELECT',
                'db.table' => 'users',
            ], ApmInterface::SPAN_KIND_CLIENT);
            
            usleep(30000);
            
            $apm->endSpan($dbSpan, [
                'db.rows_affected' => '5',
            ], ApmInterface::STATUS_OK);
            
            $apiSpan = $apm->startSpan('http-client-call', [
                'http.url' => 'https://api.example.com/data',
                'http.method' => 'GET',
            ], ApmInterface::SPAN_KIND_CLIENT);
            
            usleep(20000);
            
            $apm->endSpan($apiSpan, [
                'http.status_code' => '500',
                'error' => 'Connection timeout',
            ], ApmInterface::STATUS_ERROR);
            
            $processSpan = $apm->startSpan('data-processing', [
                'operation' => 'transform',
                'items_count' => '5',
            ], ApmInterface::SPAN_KIND_INTERNAL);
            
            usleep(10000);
            
            throw new \Exception('Processing failed: Invalid data format', 422);
            
        } catch (\Exception $e) {
            if ($processSpan !== null && !empty($processSpan)) {
                $apm->recordException($processSpan, $e);
                $apm->endSpan($processSpan, [
                    'error_code' => (string)$e->getCode(),
                ], ApmInterface::STATUS_ERROR);
            }
            
            $apm->recordException($rootSpan, $e);
            $traceId = $apm->getTraceId();
            
            $apm->endSpan($rootSpan, [
                'http.status_code' => '500',
                'error_type' => get_class($e),
                'error_code' => (string)$e->getCode(),
            ], ApmInterface::STATUS_ERROR);
            
            $apm->flush();
            
            return Response::success([
                'apm_enabled' => true,
                'trace_id' => $traceId,
                'message' => 'APM error test completed. Exception was traced. Check APM dashboard for error details.',
                'spans_created' => 4,
                'error_traced' => true,
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
            ], 1, 'APM error test - success');
        }
    }

    /**
     * Debug APM Payload - Shows what payload would be sent
     * 
     * @return JsonResponse
     */
    public function debugPayload(): JsonResponse
    {
        $apm = ApmFactory::create(null);
        
        if ($apm === null || !$apm->isEnabled()) {
            return Response::success([
                'error' => 'APM not enabled or not available',
            ], 1, 'APM debug payload');
        }

        // Create a test trace to see the payload structure
        $rootSpan = $apm->startSpan('debug-test', [
            'test' => 'debug',
        ], ApmInterface::SPAN_KIND_INTERNAL);
        
        if (empty($rootSpan)) {
            return Response::success([
                'error' => 'Failed to create test span (sampling or error)',
            ], 1, 'APM debug payload');
        }

        $apm->endSpan($rootSpan, [], ApmInterface::STATUS_OK);
        
        // Use reflection to inspect payload structure (provider-agnostic)
        $payload = null;
        $serviceName = null;
        $providerClass = get_class($apm);
        
        try {
            $reflection = new \ReflectionClass($apm);
            
            // Try to get service name from common property names (provider-agnostic)
            // PHP 8.1+ allows direct access to private/protected members without setAccessible()
            $serviceNamePropertyNames = ['serviceName', 'service_name', 'service'];
            foreach ($serviceNamePropertyNames as $propName) {
                if ($reflection->hasProperty($propName)) {
                    try {
                        $prop = $reflection->getProperty($propName);
                        // Direct access works in PHP 8.1+ without setAccessible()
                        $value = $prop->getValue($apm);
                        if (is_string($value) && !empty($value)) {
                            $serviceName = $value;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // Property access failed, continue to next
                        continue;
                    }
                }
            }
            
            // Try to get payload using common method names (provider-agnostic)
            // PHP 8.1+ allows direct invocation of private/protected methods without setAccessible()
            $payloadMethodNames = ['buildTracePayload', 'buildBatchPayload', 'getPayload'];
            foreach ($payloadMethodNames as $methodName) {
                if ($reflection->hasMethod($methodName)) {
                    try {
                        $method = $reflection->getMethod($methodName);
                        // Direct invocation works in PHP 8.1+ without setAccessible()
                        $result = $method->invoke($apm);
                        if (!empty($result)) {
                            $payload = $result;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // Method invocation failed, continue to next
                        continue;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Reflection failed, but continue with what we have
        }
        
        // Extract service name from payload to verify (provider-agnostic)
        $payloadServiceName = null;
        if ($payload !== null && is_array($payload)) {
            // Try common payload structures (OTLP format, etc.)
            if (isset($payload['resourceSpans']) && is_array($payload['resourceSpans']) 
                && isset($payload['resourceSpans'][0]) && is_array($payload['resourceSpans'][0])
                && isset($payload['resourceSpans'][0]['resource']) && is_array($payload['resourceSpans'][0]['resource'])
                && isset($payload['resourceSpans'][0]['resource']['attributes']) && is_array($payload['resourceSpans'][0]['resource']['attributes'])) {
                foreach ($payload['resourceSpans'][0]['resource']['attributes'] as $attr) {
                    if (is_array($attr) && isset($attr['key']) && $attr['key'] === 'service.name') {
                        if (isset($attr['value']) && is_array($attr['value']) && isset($attr['value']['stringValue'])) {
                            $payloadServiceName = $attr['value']['stringValue'];
                        } elseif (isset($attr['value']) && is_string($attr['value'])) {
                            $payloadServiceName = $attr['value'];
                        }
                        break;
                    }
                }
            }
            // Try alternative payload structures
            if ($payloadServiceName === null && isset($payload['service_name']) && is_string($payload['service_name'])) {
                $payloadServiceName = $payload['service_name'];
            }
            if ($payloadServiceName === null && isset($payload['serviceName']) && is_string($payload['serviceName'])) {
                $payloadServiceName = $payload['serviceName'];
            }
        }
        
        // Get service name from environment (check common env var patterns)
        $envServiceName = $_ENV['APM_SERVICE_NAME'] 
            ?? $_ENV['TRACEKIT_SERVICE_NAME'] 
            ?? $_ENV['DATADOG_SERVICE_NAME'] 
            ?? 'not set';
        
        return Response::success([
            'provider_class' => $providerClass,
            'service_name_from_property' => $serviceName,
            'service_name_from_env' => $envServiceName,
            'service_name_from_payload' => $payloadServiceName,
            'payload_structure' => $payload,
            'payload_json' => $payload !== null ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'trace_id' => $apm->getTraceId(),
            'note' => 'This shows the payload structure. The service.name should match your configured service name.',
        ], 1, 'APM debug payload');
    }

    /**
     * Get APM Status
     * 
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        $apmName = ApmFactory::isEnabled();
        
        if ($apmName === null) {
            return Response::success([
                'enabled' => false,
                'provider' => null,
                'message' => 'APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.',
            ], 1, 'APM status');
        }
        
        $apm = ApmFactory::create(null);
        $isEnabled = $apm !== null && $apm->isEnabled();
        
        // Get diagnostic information
        $diagnostics = [
            'enabled' => $isEnabled,
            'provider' => $apmName,
            'package_installed' => $apm !== null,
        ];
        
        // Add TraceKit-specific diagnostics
        if ($apmName === 'TraceKit' && $apm !== null) {
            $apiKey = $_ENV['TRACEKIT_API_KEY'] ?? $_ENV['APM_API_KEY'] ?? '';
            $apiKeyString = is_string($apiKey) ? $apiKey : '';
            $diagnostics['tracekit'] = [
                'api_key_set' => !empty($apiKeyString),
                'api_key_length' => strlen($apiKeyString),
                'service_name_env' => $_ENV['TRACEKIT_SERVICE_NAME'] ?? 'not set',
                'service_name_loaded' => $_ENV['TRACEKIT_SERVICE_NAME'] ?? null,
                'endpoint' => $_ENV['TRACEKIT_ENDPOINT'] ?? 'https://app.tracekit.dev/v1/traces (default)',
                'sample_rate' => $_ENV['APM_SAMPLE_RATE'] ?? $_ENV['TRACEKIT_SAMPLE_RATE'] ?? '1.0',
                'send_interval' => $_ENV['APM_SEND_INTERVAL'] ?? '5 (default)',
            ];
        }
        
        $diagnostics['message'] = $isEnabled 
            ? "APM provider '{$apmName}' is enabled and configured."
            : "APM provider '{$apmName}' package not installed. Install with: composer require gemvc/apm-{$apmName}";
        
        return Response::success($diagnostics, 1, 'APM status');
    }

    /**
     * Register APM Service (Provider-agnostic)
     * 
     * @param string $email
     * @param string|null $organizationName
     * @param string $source
     * @return JsonResponse
     */
    public function register(string $email, ?string $organizationName = null, string $source = 'gemvc'): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        $sourceMetadata = [
            'version' => $_ENV['APP_VERSION'] ?? '5.2.0',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
        ];

        return $toolkit->registerService($email, $organizationName, $source, $sourceMetadata);
    }

    /**
     * Verify APM Email Code (Provider-agnostic)
     * 
     * @param string $sessionId
     * @param string $code
     * @return JsonResponse
     */
    public function verify(string $sessionId, string $code): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->verifyCode($sessionId, $code);
    }

    /**
     * Send APM Heartbeat
     * 
     * @param string $status
     * @param array<string, mixed> $metadata
     * @return JsonResponse
     */
    public function sendHeartbeat(string $status = 'healthy', array $metadata = []): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        if (empty($metadata)) {
            $cpuLoad = null;
            if (function_exists('sys_getloadavg')) {
                $loadAvg = sys_getloadavg();
                if (is_array($loadAvg)) {
                    // sys_getloadavg() always returns array{float, float, float} when successful
                    $cpuLoad = $loadAvg[0];
                }
            }
            $metadata = [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'cpu_load' => $cpuLoad,
            ];
        }
        
        return $toolkit->sendHeartbeat($status, $metadata);
    }

    /**
     * Get APM Metrics
     * 
     * @param string $window
     * @return JsonResponse
     */
    public function getMetrics(string $window = '15m'): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        $allowedWindows = ['5m', '15m', '1h', '6h', '24h'];
        if (!in_array($window, $allowedWindows)) {
            return Response::badRequest('Invalid window. Allowed: ' . implode(', ', $allowedWindows));
        }
        
        return $toolkit->getMetrics($window);
    }

    /**
     * Get APM Alerts Summary
     * 
     * @return JsonResponse
     */
    public function getAlertsSummary(): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->getAlertsSummary();
    }

    /**
     * Get APM Active Alerts
     * 
     * @param int $limit
     * @return JsonResponse
     */
    public function getActiveAlerts(int $limit = 50): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->getActiveAlerts($limit);
    }

    /**
     * Get APM Subscription
     * 
     * @return JsonResponse
     */
    public function getSubscription(): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->getSubscription();
    }

    /**
     * List APM Plans
     * 
     * @return JsonResponse
     */
    public function listPlans(): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->listPlans();
    }

    /**
     * List APM Webhooks
     * 
     * @return JsonResponse
     */
    public function listWebhooks(): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->listWebhooks();
    }

    /**
     * Create APM Webhook
     * 
     * @param string $name
     * @param string $url
     * @param array<string> $events
     * @param bool $enabled
     * @return JsonResponse
     */
    public function createWebhook(string $name, string $url, array $events, bool $enabled = true): JsonResponse
    {
        $toolkit = $this->getToolkit();
        if ($toolkit === null) {
            $providerName = ApmFactory::isEnabled();
            if ($providerName === null) {
                return Response::notFound('APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.');
            }
            return Response::notFound("APM provider '{$providerName}' toolkit not available. Install with: composer require gemvc/apm-{$providerName}");
        }
        
        return $toolkit->createWebhook($name, $url, $events, $enabled);
    }
}


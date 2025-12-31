<?php
namespace App\Model;

use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Helper\ProjectHelper;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

/**
 * APM Model - Data logic layer for APM operations
 * 
 * This model handles APM provider operations without database dependencies.
 * It uses ApmFactory to work with any APM provider (TraceKit, Datadog, etc.)
 */
class ApmModel
{
    /**
     * Test APM Provider with nested spans
     * 
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        $apm = ApmFactory::create(null);
        
        if ($apm === null || !$apm->isEnabled()) {
            $apmName = ProjectHelper::isApmEnabled();
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
            $apmName = ProjectHelper::isApmEnabled();
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
     * Get APM Status
     * 
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        $apmName = ProjectHelper::isApmEnabled();
        
        if ($apmName === null) {
            return Response::success([
                'enabled' => false,
                'provider' => null,
                'message' => 'APM is not enabled. Set APM_NAME and APM_ENABLED in .env file.',
            ], 1, 'APM status');
        }
        
        $apm = ApmFactory::create(null);
        $isEnabled = $apm !== null && $apm->isEnabled();
        
        return Response::success([
            'enabled' => $isEnabled,
            'provider' => $apmName,
            'package_installed' => $apm !== null,
            'message' => $isEnabled 
                ? "APM provider '{$apmName}' is enabled and configured."
                : "APM provider '{$apmName}' package not installed. Install with: composer require gemvc/apm-{$apmName}",
        ], 1, 'APM status');
    }

    /**
     * Register TraceKit Service (TraceKit-specific)
     * 
     * @param string $email
     * @param string|null $organizationName
     * @param string $source
     * @return JsonResponse
     */
    public function register(string $email, ?string $organizationName = null, string $source = 'gemvc'): JsonResponse
    {
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        
        $sourceMetadata = [
            'version' => $_ENV['APP_VERSION'] ?? '5.2.0',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
        ];

        return $toolkit->registerService($email, $organizationName, $source, $sourceMetadata);
    }

    /**
     * Verify TraceKit Email Code (TraceKit-specific)
     * 
     * @param string $sessionId
     * @param string $code
     * @return JsonResponse
     */
    public function verify(string $sessionId, string $code): JsonResponse
    {
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
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
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        
        if (empty($metadata)) {
            $metadata = [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : null,
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
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        
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
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
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
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        return $toolkit->getActiveAlerts($limit);
    }

    /**
     * Get APM Subscription
     * 
     * @return JsonResponse
     */
    public function getSubscription(): JsonResponse
    {
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        return $toolkit->getSubscription();
    }

    /**
     * List APM Plans
     * 
     * @return JsonResponse
     */
    public function listPlans(): JsonResponse
    {
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        return $toolkit->listPlans();
    }

    /**
     * List APM Webhooks
     * 
     * @return JsonResponse
     */
    public function listWebhooks(): JsonResponse
    {
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
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
        // Try multiple possible namespaces for TraceKitToolkit
        $toolkitClass = null;
        $possibleNamespaces = [
            'Gemvc\Core\Apm\Providers\TraceKit\TraceKitToolkit',
            'Gemvc\Helper\TraceKitToolkit',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            if (class_exists($namespace)) {
                $toolkitClass = $namespace;
                break;
            }
        }
        
        if ($toolkitClass === null) {
            return Response::notFound('TraceKit package not installed. Install with: composer require gemvc/apm-tracekit');
        }
        
        $toolkit = new $toolkitClass();
        return $toolkit->createWebhook($name, $url, $events, $enabled);
    }
}


<?php
namespace App\Controller;

use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\HtmlResponse;
use Gemvc\Core\Documentation;
use App\Controller\DeveloperController;
use Gemvc\Helper\TraceKitModel;
use Gemvc\Helper\TraceKitToolkit;

class IndexController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Get server status or dev info
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        if($_ENV['APP_ENV'] !== 'dev'){
            return Response::success('server running',1,'server running');
        }
        return new DeveloperController($this->request)->devInfo();
    }

    /**
     * Get API documentation
     * 
     * @return HtmlResponse
     */
    public function document(): HtmlResponse
    {
        $doc = new Documentation();
        return $doc->htmlResponse();
    }

    /**
     * SPA Entry Point
     * 
     * @return HtmlResponse
     */
    public function developer(): HtmlResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return new HtmlResponse('Page not found', 404);
        }
        $devController = new DeveloperController($this->request);
        return $devController->app();
    }

    /**
     * Developer Welcome Page Data
     * 
     * @return JsonResponse
     */
    public function welcome(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->welcome();
    }

    /**
     * Export Table
     * 
     * @return HtmlResponse
     */
    public function export(): HtmlResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return new HtmlResponse('Page not found', 404);
        }
        return new DeveloperController($this->request)->export();
    }

    /**
     * Import Table
     * 
     * @return JsonResponse
     */
    public function import(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->import();
    }

    /**
     * Database Management Page Data
     * 
     * @return JsonResponse
     */
    public function database(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->database();
    }

    /**
     * Get Logo Data
     * 
     * @return JsonResponse
     */
    public function logo(): JsonResponse
    {
        return new DeveloperController($this->request)->logo();
    }

    /**
     * Get Favicon
     * 
     * @return HtmlResponse
     */
    public function favicon(): HtmlResponse
    {
        $faviconPath = __DIR__ . '/../../vendor/gemvc/library/src/startup/common/system_pages/favicon.ico';
        
        if (file_exists($faviconPath)) {
            $faviconContent = file_get_contents($faviconPath);
            if ($faviconContent !== false) {
                $headers = [
                    'Content-Type' => 'image/x-icon',
                    'Cache-Control' => 'public, max-age=31536000'
                ];
                return new HtmlResponse($faviconContent, 200, $headers);
            }
        }
        
        return new HtmlResponse('', 404);
    }

    /**
     * Get API Configuration
     * 
     * @return JsonResponse
     */
    public function config(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        \Gemvc\Helper\ProjectHelper::loadEnv();
        $apiBaseUrl = \Gemvc\Helper\ProjectHelper::getApiBaseUrl();
        $webserverType = \Gemvc\Core\WebserverDetector::get();
        
        return Response::success([
            'apiBaseUrl' => $apiBaseUrl,
            'webserverType' => $webserverType,
            'publicServerPort' => $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] ?? '80',
            'apiSubUrl' => $_ENV['APP_ENV_API_DEFAULT_SUB_URL'] ?? ''
        ]);
    }

    /**
     * Check Database Ready Status
     * 
     * @return JsonResponse
     */
    public function isDbReady(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->isDbReady();
    }

    /**
     * Initialize Database
     * 
     * @return JsonResponse
     */
    public function initDatabase(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->initDatabase();
    }

    /**
     * Services Management Page Data
     * 
     * @return JsonResponse
     */
    public function services(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->services();
    }

    /**
     * Create New Service
     * 
     * @return JsonResponse
     */
    public function createService(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->createService();
    }

    /**
     * Tables Layer Management Page Data
     * 
     * @return JsonResponse
     */
    public function tables(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->tables();
    }

    /**
     * Migrate or Update Table
     * 
     * @return JsonResponse
     */
    public function migrateTable(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            return Response::notFound('Page not found');
        }
        return new DeveloperController($this->request)->migrateTable();
    }

    // ==========================================
    // TraceKit Model Methods
    // ==========================================

    /**
     * Test TraceKitModel
     * 
     * @return JsonResponse
     */
    public function testTracekit(): JsonResponse
    {
        $tracekit = new TraceKitModel();
        
        if (!$tracekit->isEnabled()) {
            return Response::success([
                'tracekit_enabled' => false,
                'message' => 'TraceKit is not enabled. Set TRACEKIT_API_KEY in .env file.',
                'trace_id' => null,
            ], 1, 'TraceKit test - not enabled');
        }
        
        $rootSpan = $tracekit->startTrace('http-request', [
            'http.method' => $this->request->getMethod(),
            'http.url' => $this->request->getUri(),
            'http.user_agent' => $this->request->getHeader('User-Agent') ?? 'unknown',
        ]);
        
        try {
            $dbSpan = $tracekit->startSpan('database-query', [
                'db.system' => 'mysql',
                'db.operation' => 'SELECT',
                'db.table' => 'users',
            ]);
            
            usleep(50000);
            
            $tracekit->endSpan($dbSpan, [
                'db.rows_affected' => 5,
            ]);
            
            $apiSpan = $tracekit->startSpan('http-client-call', [
                'http.url' => 'https://api.example.com/data',
                'http.method' => 'GET',
            ], TraceKitModel::SPAN_KIND_CLIENT);
            
            usleep(30000);
            
            $tracekit->endSpan($apiSpan, [
                'http.status_code' => 200,
                'response.size' => 1024,
            ]);
            
            $processSpan = $tracekit->startSpan('data-processing', [
                'operation' => 'transform',
                'items_count' => 5,
            ]);
            
            usleep(20000);
            
            $tracekit->endSpan($processSpan, [
                'processed_items' => 5,
            ]);
            
            $tracekit->endSpan($rootSpan, [
                'http.status_code' => 200,
            ], TraceKitModel::STATUS_OK);
            
            $traceId = $tracekit->getTraceId();
            $tracekit->flush();
            
            return Response::success([
                'tracekit_enabled' => true,
                'trace_id' => $traceId,
                'message' => 'TraceKit test completed successfully. Check TraceKit dashboard for traces.',
                'spans_created' => 4,
            ], 1, 'TraceKit test - success');
            
        } catch (\Exception $e) {
            $tracekit->recordException($rootSpan, $e);
            $traceId = $tracekit->getTraceId();
            $tracekit->endSpan($rootSpan, [
                'http.status_code' => 500,
            ], TraceKitModel::STATUS_ERROR);
            $tracekit->flush();
            return Response::internalError('TraceKit test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test TraceKitModel with Error
     * 
     * @return JsonResponse
     */
    public function testTracekitError(): JsonResponse
    {
        $tracekit = new TraceKitModel();
        
        if (!$tracekit->isEnabled()) {
            return Response::success([
                'tracekit_enabled' => false,
                'message' => 'TraceKit is not enabled. Set TRACEKIT_API_KEY in .env file.',
                'trace_id' => null,
            ], 1, 'TraceKit error test - not enabled');
        }
        
        $rootSpan = $tracekit->startTrace('http-request', [
            'http.method' => $this->request->getMethod(),
            'http.url' => $this->request->getUri(),
            'http.user_agent' => $this->request->getHeader('User-Agent') ?? 'unknown',
        ]);
        
        $processSpan = null;
        try {
            $dbSpan = $tracekit->startSpan('database-query', [
                'db.system' => 'mysql',
                'db.operation' => 'SELECT',
                'db.table' => 'users',
            ]);
            
            usleep(30000);
            
            $tracekit->endSpan($dbSpan, [
                'db.rows_affected' => 5,
            ]);
            
            $apiSpan = $tracekit->startSpan('http-client-call', [
                'http.url' => 'https://api.example.com/data',
                'http.method' => 'GET',
            ], TraceKitModel::SPAN_KIND_CLIENT);
            
            usleep(20000);
            
            $tracekit->endSpan($apiSpan, [
                'http.status_code' => 500,
                'error' => 'Connection timeout',
            ], TraceKitModel::STATUS_ERROR);
            
            $processSpan = $tracekit->startSpan('data-processing', [
                'operation' => 'transform',
                'items_count' => 5,
            ]);
            
            usleep(10000);
            
            throw new \Exception('Processing failed: Invalid data format', 422);
            
        } catch (\Exception $e) {
            if ($processSpan !== null) {
                $tracekit->recordException($processSpan, $e);
                $tracekit->endSpan($processSpan, [
                    'error_code' => $e->getCode(),
                ], TraceKitModel::STATUS_ERROR);
            }
            
            $tracekit->recordException($rootSpan, $e);
            $traceId = $tracekit->getTraceId();
            
            $tracekit->endSpan($rootSpan, [
                'http.status_code' => 500,
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
            ], TraceKitModel::STATUS_ERROR);
            
            $tracekit->flush();
            
            return Response::success([
                'tracekit_enabled' => true,
                'trace_id' => $traceId,
                'message' => 'TraceKit error test completed. Exception was traced. Check TraceKit dashboard for error details.',
                'spans_created' => 4,
                'error_traced' => true,
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
            ], 1, 'TraceKit error test - success');
        }
    }

    // ==========================================
    // TraceKit Toolkit Methods
    // ==========================================

    /**
     * Register TraceKit Service
     * 
     * @return JsonResponse
     */
    public function tracekitRegister(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        
        $source = $this->request->post['source'] ?? 'gemvc';
        $sourceMetadata = [
            'version' => $_ENV['APP_VERSION'] ?? '5.2.0',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
        ];

        return $toolkit->registerService(
            $this->request->post['email'],
            $this->request->post['organization_name'] ?? null,
            $source,
            $sourceMetadata
        );
    }

    /**
     * Verify TraceKit Email Code
     * 
     * @return JsonResponse
     */
    public function tracekitVerify(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        
        return $toolkit->verifyCode(
            $this->request->post['session_id'],
            $this->request->post['code']
        );
    }

    /**
     * Get TraceKit Integration Status
     * 
     * @return JsonResponse
     */
    public function tracekitStatus(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        return $toolkit->getStatus();
    }

    /**
     * Send TraceKit Heartbeat
     * 
     * @return JsonResponse
     */
    public function tracekitHeartbeat(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        
        $status = $this->request->post['status'] ?? 'healthy';
        $metadata = $this->request->post['metadata'] ?? [];
        
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
     * Get TraceKit Service Metrics
     * 
     * @return JsonResponse
     */
    public function tracekitMetrics(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        
        $window = $this->request->get['window'] ?? '15m';
        
        $allowedWindows = ['5m', '15m', '1h', '6h', '24h'];
        if (!in_array($window, $allowedWindows)) {
            return Response::badRequest('Invalid window. Allowed: ' . implode(', ', $allowedWindows));
        }
        
        return $toolkit->getMetrics($window);
    }

    /**
     * Get TraceKit Alerts Summary
     * 
     * @return JsonResponse
     */
    public function tracekitAlertsSummary(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        return $toolkit->getAlertsSummary();
    }

    /**
     * Get TraceKit Active Alerts
     * 
     * @return JsonResponse
     */
    public function tracekitActiveAlerts(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 50;
        $limit = max(1, min(100, $limit));
        
        return $toolkit->getActiveAlerts($limit);
    }

    /**
     * Get TraceKit Subscription Info
     * 
     * @return JsonResponse
     */
    public function tracekitSubscription(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        return $toolkit->getSubscription();
    }

    /**
     * List TraceKit Available Plans
     * 
     * @return JsonResponse
     */
    public function tracekitPlans(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        return $toolkit->listPlans();
    }

    /**
     * List TraceKit Webhooks
     * 
     * @return JsonResponse
     */
    public function tracekitWebhooks(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        return $toolkit->listWebhooks();
    }

    /**
     * Create TraceKit Webhook
     * 
     * @return JsonResponse
     */
    public function tracekitCreateWebhook(): JsonResponse
    {
        $toolkit = new TraceKitToolkit();
        
        $events = $this->request->post['events'];
        if (!is_array($events)) {
            return Response::badRequest('events must be an array');
        }
        
        $enabled = $this->request->post['enabled'] ?? true;
        
        return $toolkit->createWebhook(
            $this->request->post['name'],
            $this->request->post['url'],
            $events,
            $enabled
        );
    }
}

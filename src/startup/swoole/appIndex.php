<?php
namespace App\Api;

use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\HtmlResponse;
use App\Controller\IndexController;

class Index extends ApiService
{
    /**
     * Constructor
     * 
     * @param Request $request The HTTP request object
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }


    /**
     * Create new Index
     * @return JsonResponse
     * @http GET
     * @description test if Gemvc successfully installed and Webserver Server running
     */
    public function index(): JsonResponse
    {
        return new IndexController($this->request)->index();
    }

    /**
     * Summary of document this method is special and reserved for documentation
     * @return HtmlResponse
     * @http GET
     * @hidden
     */
    public function document(): HtmlResponse
    {
        return new IndexController($this->request)->document();
    }

    /**
     * SPA Entry Point - Returns the SPA HTML shell
     * 
     * @return HtmlResponse
     * @http GET
     * @description Single Page Application shell
     * @hidden
     */
    public function developer(): HtmlResponse
    {
        return new IndexController($this->request)->developer();
    }

    /** 
    *  Developer Welcome Page Data (JSON for SPA)
    * @return JsonResponse
    * @http GET
    * @description GEMVC Framework Developer Welcome Page Data
    * @hidden
    */
    public function welcome(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return new IndexController($this->request)->welcome();
    }

    /**
     * Export Table - Exports table data as CSV or SQL
     * 
     * @return \Gemvc\Http\HtmlResponse
     * @http POST
     * @description Export table data
     * @hidden
     */
    public function export(): HtmlResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return (new \App\Controller\DeveloperController($this->request))->app();
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'table' => 'string',
            'format' => 'string'
        ])) {
            return new HtmlResponse('Invalid request', 400);
        }
        
        return new IndexController($this->request)->export();
    }

    /**
     * Import Table - Imports table data from CSV or SQL file
     * 
     * @return JsonResponse
     * @http POST
     * @description Import table data from file
     * @hidden
     */
    public function import(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return new IndexController($this->request)->import();
    }

    /**
     * Database Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Database Management Tools Data
     * @hidden
     */
    public function database(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return new IndexController($this->request)->database();
    }

    /**
     * Get Logo Data
     * 
     * @return JsonResponse
     * @http GET
     * @description Returns GEMVC logo as base64 and admin password status
     * @hidden
     */
    public function logo(): JsonResponse
    {
        return new IndexController($this->request)->logo();
    }

    /**
     * Get Favicon
     * 
     * @return \Gemvc\Http\HtmlResponse
     * @http GET
     * @description Returns favicon.ico file
     * @hidden
     */
    public function favicon(): HtmlResponse
    {
        return new IndexController($this->request)->favicon();
    }

    /**
     * Get API Configuration
     * 
     * @return JsonResponse
     * @http GET
     * @description Returns API base URL and configuration for SPA
     * @hidden
     */
    public function config(): JsonResponse
    {
        return new IndexController($this->request)->config();
    }

    /**
    * Check Database Ready Status
    * 
    * @return JsonResponse
    * @http GET
    * @description Check if database is connected and ready
    * @hidden
    */
    public function isDbReady(): JsonResponse
    {
        return new IndexController($this->request)->isDbReady();
    }

    /**
     * Initialize Database
     * 
     * @return JsonResponse
     * @http POST
     * @description Initialize database (create database if not exists)
     * @hidden
     */
    public function initDatabase(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return new IndexController($this->request)->initDatabase();
    }

    /**
     * Services Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Services management page with API endpoints list
     * @hidden
     */
    public function services(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return new IndexController($this->request)->services();
    }

    /**
     * Create New Service
     * 
     * @return JsonResponse
     * @http POST
     * @description Create a new service (CRUD, service only, service+controller, service+model)
     * @hidden
     */
    public function createService(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'serviceName' => 'string',
            'type' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        return new IndexController($this->request)->createService();
    }

    /**
     * Tables Layer Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Tables Layer management page with all table classes and migration status
     * @hidden
     */
    public function tables(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return new IndexController($this->request)->tables();
    }

    /**
     * Migrate or Update Table
     * 
     * @return JsonResponse
     * @http POST
     * @description Migrate or update a table class to database
     * @hidden
     */
    public function migrateTable(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'tableClassName' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        return new IndexController($this->request)->migrateTable();
    }

    // ==========================================
    // TraceKit Model API Endpoints
    // ==========================================

    /**
     * Test TraceKitModel - Test endpoint for TraceKit APM
     * 
     * @return JsonResponse
     * @http GET
     * @description Test TraceKitModel implementation with nested spans
     */
    public function testTracekit(): JsonResponse
    {
        return new IndexController($this->request)->testTracekit();
    }

    /**
     * Test TraceKitModel with Error - Test exception tracing
     * 
     * @return JsonResponse
     * @http GET
     * @description Test TraceKitModel error/exception tracing
     */
    public function testTracekitError(): JsonResponse
    {
        return new IndexController($this->request)->testTracekitError();
    }

    // ==========================================
    // TraceKit Toolkit API Endpoints
    // ==========================================

    /**
     * Register TraceKit Service
     * 
     * @return JsonResponse
     * @http POST
     * @description Register a new service in TraceKit (requires email verification)
     */
    public function tracekitRegister(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'email' => 'email',
            '?organization_name' => 'string',
            '?source' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return new IndexController($this->request)->tracekitRegister();
    }

    /**
     * Debug POST Data (for testing)
     * 
     * @return JsonResponse
     * @http POST
     * @description Debug endpoint to see what POST data is received
     * @hidden
     */
    public function tracekitDebugPost(): JsonResponse
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'unknown';
        $rawInput = file_get_contents('php://input');
        
        $headerContentType = $this->request->getHeader('content-type');
        
        $manualJsonParse = null;
        if (strpos($contentType, 'application/json') !== false || 
            ($headerContentType && strpos($headerContentType, 'application/json') !== false)) {
            if (!empty($rawInput)) {
                $manualJsonParse = json_decode($rawInput, true);
            }
        }
        
        return Response::success([
            'content_type_server' => $contentType,
            'content_type_header' => $headerContentType,
            'raw_input' => $rawInput,
            'raw_input_length' => strlen($rawInput),
            'parsed_post' => $this->request->post,
            'post_count' => count($this->request->post ?? []),
            'post_is_empty' => empty($this->request->post),
            'request_method' => $this->request->requestMethod,
            'is_json' => strpos($contentType, 'application/json') !== false || 
                        ($headerContentType && strpos($headerContentType, 'application/json') !== false),
            'manual_json_parse' => $manualJsonParse,
            'json_decode_error' => json_last_error_msg(),
        ], 1, 'Debug info');
    }

    /**
     * Verify TraceKit Email Code
     * 
     * @return JsonResponse
     * @http POST
     * @description Verify email code and get API key
     */
    public function tracekitVerify(): JsonResponse
    {
        $this->parseJsonPostData();
        
        if (empty($this->request->post)) {
            return Response::badRequest('POST data is required. Make sure Content-Type is application/json and body contains JSON data. Use /api/Index/tracekitDebugPost to debug.');
        }

        if (!$this->request->definePostSchema([
            'session_id' => 'string',
            'code' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return new IndexController($this->request)->tracekitVerify();
    }

    /**
     * Get TraceKit Integration Status
     * 
     * @return JsonResponse
     * @http GET
     * @description Get TraceKit integration status and service info
     */
    public function tracekitStatus(): JsonResponse
    {
        return new IndexController($this->request)->tracekitStatus();
    }

    /**
     * Send TraceKit Heartbeat
     * 
     * @return JsonResponse
     * @http POST
     * @description Send health heartbeat to TraceKit
     */
    public function tracekitHeartbeat(): JsonResponse
    {
        $this->parseJsonPostData();

        if (!$this->request->definePostSchema([
            '?status' => 'string',
            '?metadata' => 'json',
        ])) {
            return $this->request->returnResponse();
        }

        return new IndexController($this->request)->tracekitHeartbeat();
    }

    /**
     * Get TraceKit Service Metrics
     * 
     * @return JsonResponse
     * @http GET
     * @description Get service metrics (latency, error rate, throughput)
     */
    public function tracekitMetrics(): JsonResponse
    {
        return new IndexController($this->request)->tracekitMetrics();
    }

    /**
     * Get TraceKit Alerts Summary
     * 
     * @return JsonResponse
     * @http GET
     * @description Get alerts summary
     */
    public function tracekitAlertsSummary(): JsonResponse
    {
        return new IndexController($this->request)->tracekitAlertsSummary();
    }

    /**
     * Get TraceKit Active Alerts
     * 
     * @return JsonResponse
     * @http GET
     * @description Get active alerts
     */
    public function tracekitActiveAlerts(): JsonResponse
    {
        return new IndexController($this->request)->tracekitActiveAlerts();
    }

    /**
     * Get TraceKit Subscription Info
     * 
     * @return JsonResponse
     * @http GET
     * @description Get current subscription and usage info
     */
    public function tracekitSubscription(): JsonResponse
    {
        return new IndexController($this->request)->tracekitSubscription();
    }

    /**
     * List TraceKit Available Plans
     * 
     * @return JsonResponse
     * @http GET
     * @description List available subscription plans
     */
    public function tracekitPlans(): JsonResponse
    {
        return new IndexController($this->request)->tracekitPlans();
    }

    /**
     * List TraceKit Webhooks
     * 
     * @return JsonResponse
     * @http GET
     * @description List all webhooks
     */
    public function tracekitWebhooks(): JsonResponse
    {
        return new IndexController($this->request)->tracekitWebhooks();
    }

    /**
     * Create TraceKit Webhook
     * 
     * @return JsonResponse
     * @http POST
     * @description Create a new webhook
     */
    public function tracekitCreateWebhook(): JsonResponse
    {
        $this->parseJsonPostData();

        if (!$this->request->definePostSchema([
            'name' => 'string',
            'url' => 'url',
            'events' => 'json',
            '?enabled' => 'boolean',
        ])) {
            return $this->request->returnResponse();
        }

        return new IndexController($this->request)->tracekitCreateWebhook();
    }

    /**
     * Summary of mockResponse
     * @param string $method
     * @return array{count: int, data: array{description: string, id: int, name: string, message: string, response_code: int, service_message: string}|array{count: null, data: null, message: string, response_code: int, service_message: null}}
     * @hidden
     */
    public static function mockResponse(string $method): array
    {
        return match($method) {
            'index' => [
                'response_code' => 200,
                'message' => 'success',
                'count' => 1,
                'service_message' => 'Index created successfully',
                'data' => [
                    'id' => 1,
                    'name' => 'Sample Index',
                    'description' => 'Index description'
                ]
            ],
            default => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => null,
                'service_message' => null,
                'data' => null
            ]
        };
    }
}

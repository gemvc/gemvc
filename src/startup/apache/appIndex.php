<?php
namespace App\Api;


use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Core\Documentation;
use Gemvc\Http\HtmlResponse;
use App\Controller\DeveloperController;
#use Gemvc\Core\RedisManager;
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
        $info = new \stdClass();
        $info->server = \Gemvc\Core\WebserverDetector::get();
        $info->version = \Gemvc\Helper\ProjectHelper::getVersion();
        $info->environment = $_ENV['APP_ENV'];

        return Response::success($info,1,'server running');
    }

    /**
     * Summary of document this method is special and reserved for documentation
     * @return HtmlResponse
     * @http GET
     * @hidden
     */
    public function document(): HtmlResponse
    {
        $doc = new Documentation();
        return $doc->htmlResponse();
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
        $devController = new DeveloperController($this->request);
        // Delegate to Controller show the login page
        return $devController->app();
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
       // Check JWT authentication
       if (!$this->request->auth(['developer','admin'])) {
           return Response::unauthorized('Authentication required');
       }      
       return (new DeveloperController($this->request))->welcome();
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
        // Require admin authentication
        if (!$this->request->auth(['developer','admin'])) {
            return (new DeveloperController($this->request))->app();
        }    
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'table' => 'string',
            'format' => 'string'
        ])) {
            return new HtmlResponse('Invalid request', 400);
        }
        
        // Delegate to Controller (data extraction handled there)
        return (new DeveloperController($this->request))->export();
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
        // Require admin authentication
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        return (new DeveloperController($this->request))->database();
    }

    /**
     * Get Logo Data
     * 
     * @return JsonResponse
     * @http GET
     * @description Returns GEMVC logo as base64 and admin password status
     */
    public function logo(): JsonResponse
    {
        // Delegate to Controller
        return (new DeveloperController($this->request))->logo();
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
    * @http GET
    * @description Check if database is connected and ready
    * @hidden
    */
   public function isDbReady(): JsonResponse
   {
       // Delegate to Controller
       return (new DeveloperController($this->request))->isDbReady();
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
       // Require admin authentication
       if (!$this->request->auth(['developer','admin'])) {
           return Response::unauthorized('Authentication required');
       }
       
       // Delegate to Controller
       return (new DeveloperController($this->request))->initDatabase();
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
       // Require admin authentication
       if (!$this->request->auth(['developer','admin'])) {
           return Response::unauthorized('Authentication required');
       }
       
       // Delegate to Controller
       return (new DeveloperController($this->request))->services();
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
       // Require admin authentication
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
       
       // Delegate to Controller
       return (new DeveloperController($this->request))->createService();
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
       // Require admin authentication
       if (!$this->request->auth(['developer','admin'])) {
           return Response::unauthorized('Authentication required');
       }
       
       // Delegate to Controller
       return (new DeveloperController($this->request))->tables();
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
       // Require admin authentication
       if (!$this->request->auth(['developer','admin'])) {
           return Response::unauthorized('Authentication required');
       }
       
       // Validate POST schema
       if (!$this->request->definePostSchema([
           'tableClassName' => 'string'
       ])) {
           return $this->request->returnResponse();
       }
       
       // Delegate to Controller
       return (new DeveloperController($this->request))->migrateTable();
   }

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


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
        if($_ENV['APP_ENV'] !== 'dev'){
            return Response::success('server running',1,'server running');
        }
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
        if(!$_ENV['APP_ENV'] === 'dev'){
            //it is dummy page for production non dev environment
            return new HtmlResponse('Page not found', 404);
        }
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
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
        if(!$_ENV['APP_ENV'] === 'dev'){
            //it is dummy page for production non dev environment
            return new HtmlResponse('Page not found', 404);
        }
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
     * Import Table - Imports table data from CSV or SQL file
     * 
     * @return JsonResponse
     * @http POST
     * @description Import table data from file
     * @hidden
     */
    public function import(): JsonResponse
    {
        if(!$_ENV['APP_ENV'] === 'dev'){
            //it is dummy page for production non dev environment
            return Response::notFound('Page not found');
        }
        // Require admin authentication
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        // Delegate to Controller
        return (new DeveloperController($this->request))->import();
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
        if(!$_ENV['APP_ENV'] === 'dev'){
            //it is dummy page for production non dev environment
            return Response::notFound('Page not found');
        }
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
     * @hidden
     */
    public function logo(): JsonResponse
    {
        // Delegate to Controller
        return (new DeveloperController($this->request))->logo();
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
        
        // Return 404 if favicon not found
        return new HtmlResponse('', 404);
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
        if(!$_ENV['APP_ENV'] === 'dev'){
            //it is dummy page for production non dev environment
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
    * @http GET
    * @description Check if database is connected and ready
    * @hidden
    */
   public function isDbReady(): JsonResponse
   {
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
    if(!$_ENV['APP_ENV'] === 'dev'){
        //it is dummy page for production non dev environment
        return Response::notFound('Page not found');
    }
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
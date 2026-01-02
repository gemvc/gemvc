<?php
namespace App\Api;

use App\Controller\GemvcAssistantController;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\HtmlResponse;

class GemvcAssistant extends ApiService
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
        
        return (new \App\Controller\DeveloperController($this->request))->welcome();
    }

    /**
     * Export Table - Exports table data as CSV or SQL
     * 
     * @return HtmlResponse
     * @http POST
     * @description Export table data
     * @hidden
     * @example /api/GemvcAssistant/export
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
        
        return (new GemvcAssistantController($this->request))->export();
    }

    /**
     * Import Table - Imports table data from CSV or SQL file
     * 
     * @return JsonResponse
     * @http POST
     * @description Import table data from file
     * @hidden
     * @example /api/GemvcAssistant/import
     */
    public function import(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcAssistantController($this->request))->import();
    }

    /**
     * Database Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Database Management Tools Data
     * @hidden
     * @example /api/GemvcAssistant/database
     */
    public function database(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcAssistantController($this->request))->database();
    }

    /**
     * Get API Configuration
     * 
     * @return JsonResponse
     * @http GET
     * @description Returns API base URL and configuration for SPA
     * @hidden
     * @example /api/GemvcAssistant/config
     */
    public function config(): JsonResponse
    {
        return (new GemvcAssistantController($this->request))->config();
    }

    /**
     * Check Database Ready Status
     * 
     * @return JsonResponse
     * @http GET
     * @description Check if database is connected and ready
     * @hidden
     * @example /api/GemvcAssistant/isDbReady
     */
    public function isDbReady(): JsonResponse
    {
        return (new GemvcAssistantController($this->request))->isDbReady();
    }

    /**
     * Initialize Database
     * 
     * @return JsonResponse
     * @http POST
     * @description Initialize database (create database if not exists)
     * @hidden
     * @example /api/GemvcAssistant/initDatabase
     */
    public function initDatabase(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcAssistantController($this->request))->initDatabase();
    }

    /**
     * Services Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Services management page with API endpoints list
     * @hidden
     * @example /api/GemvcAssistant/services
     */
    public function services(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcAssistantController($this->request))->services();
    }

    /**
     * Create New Service
     * 
     * @return JsonResponse
     * @http POST
     * @description Create a new service (CRUD, service only, service+controller, service+model)
     * @hidden
     * @example /api/GemvcAssistant/createService
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
        
        return (new GemvcAssistantController($this->request))->createService();
    }

    /**
     * Tables Layer Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Tables Layer management page with all table classes and migration status
     * @hidden
     * @example /api/GemvcAssistant/tables
     */
    public function tables(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcAssistantController($this->request))->tables();
    }

    /**
     * Migrate or Update Table
     * 
     * @return JsonResponse
     * @http POST
     * @description Migrate or update a table class to database
     * @hidden
     * @example /api/GemvcAssistant/migrateTable
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
        
        return (new GemvcAssistantController($this->request))->migrateTable();
    }
}


<?php
namespace App\Api;

use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\HtmlResponse;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\JWTToken;
use Gemvc\Core\WebserverDetector;

/**
 * Developer System Pages API Service
 * 
 * Provides HTML pages for development tools (welcome, database, login)
 * Uses GEMVC's HtmlResponse for server-agnostic HTML output
 * Uses JWT tokens for authentication (no sessions)
 * @hidden
 */
class Developer extends ApiService
{
    private const ADMIN_USER_ID = 1; // Fixed admin user ID for developer tools

    public function __construct(Request $request)
    {
        parent::__construct($request);
        
        // Security check: Only allow in development mode
        try {
            \Gemvc\Helper\ProjectHelper::loadEnv();
        } catch (\Exception $e) {
            $this->denyAccess();
        }
        
        if (($_ENV['APP_ENV'] ?? '') !== 'dev') {
            $this->denyAccess();
        }
    }

    /**
     * Admin Login - Returns JWT token
     * 
     * @return JsonResponse
     * @http POST
     * @description Admin login for developer tools - returns JWT token
     * @hidden
     */
    public function login(): JsonResponse
    {
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'password' => 'string',
            'admin_login' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        // Delegate to Controller (CORS and password extraction handled there)
        return (new \App\Controller\DeveloperController($this->request))->login();
    }

    /**
     * SPA Entry Point - Returns the SPA HTML shell
     * 
     * @return HtmlResponse
     * @http GET
     * @description Single Page Application shell
     * @hidden
     */
    public function app(): HtmlResponse
    {
        // Delegate to Controller
        return (new \App\Controller\DeveloperController($this->request))->app();
    }

    /**
     * Developer Welcome Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description GEMVC Framework Developer Welcome Page Data
     * @hidden
     */
    public function welcome(): JsonResponse
    {
        // Check JWT authentication
        if (!$this->isAuthenticated()) {
            return Response::unauthorized('Authentication required');
        }
        
        // Delegate to Controller
        return (new \App\Controller\DeveloperController($this->request))->welcome();
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
        if (!$this->isAuthenticated()) {
            return Response::unauthorized('Authentication required');
        }
        
        // Delegate to Controller
        return (new \App\Controller\DeveloperController($this->request))->database();
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
        if (!$this->isAuthenticated()) {
            return new HtmlResponse('Unauthorized', 401);
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'table' => 'string',
            'format' => 'string'
        ])) {
            return new HtmlResponse('Invalid request', 400);
        }
        
        // Delegate to Controller (data extraction handled there)
        return (new \App\Controller\DeveloperController($this->request))->export();
    }

    /**
     * Import Table - Imports data into a table
     * 
     * @return JsonResponse
     * @http POST
     * @description Import data into table
     * @hidden
     */
    public function import(): JsonResponse
    {
        // Require admin authentication
        if (!$this->isAuthenticated()) {
            return Response::unauthorized('Authentication required');
        }
        
        // Validate POST schema (table and format are required, file is validated in Controller)
        if (!$this->request->definePostSchema([
            'table' => 'string',
            'format' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        // Delegate to Controller (file validation and data extraction handled there)
        return (new \App\Controller\DeveloperController($this->request))->import();
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
        return (new \App\Controller\DeveloperController($this->request))->logo();
    }

    /**
     * Deny access (security check failed)
     * 
     * @return void
     */
    private function denyAccess(): void
    {
        if (WebserverDetector::get() === 'swoole') {
            throw new \RuntimeException('Not Found');
        }
        http_response_code(404);
        exit('Not Found');
    }

    /**
     * Check if user is authenticated via JWT token
     * 
     * @return bool
     */
    private function isAuthenticated(): bool
    {
        // Extract and verify token using JWTToken's extractToken method
        $jwt = new JWTToken();
        if (!$jwt->extractToken($this->request)) {
            return false;
        }
        
        // Verify token
        $verified = $jwt->verify();
        if (!$verified || !$jwt->isTokenValid) {
            return false;
        }
        
        // Check if user is admin and has correct user_id
        if ($jwt->user_id !== self::ADMIN_USER_ID || $jwt->role !== 'admin') {
            return false;
        }
        
        return true;
    }



    /**
     * @hidden
     */
    public static function mockResponse(string $method): array
    {
        return [
            'response_code' => 200,
            'message' => 'OK',
            'count' => null,
            'service_message' => null,
            'data' => null
        ];
    }
}

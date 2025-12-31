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
     * Get GemvcLogo Data
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

<?php
namespace App\Api;

use App\Controller\UserController;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class User extends ApiService
{
    /**
     * Constructor
     * 
     * @param Request \$request The HTTP request object
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Create new User
     * 
     * @return JsonResponse
     * @http POST
     * @description Create new User in database
     * @example /api/User/create
     */
    public function create(): JsonResponse
    {
        if(!$this->request->definePostSchema([
            'name' => 'string',
            'description' => 'string',
            'email' => 'email',
            'password' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        return (new UserController($this->request))->create();
    }

    /**
     * Read User by ID
     * 
     * @return JsonResponse
     * @http GET
     * @description Get User by id from database
     * @example /api/User/read/?id=1
     */
    public function read(): JsonResponse
    {
        //this endpoint does not accept post parameters
        if (!$this->request->definePostSchema([])){
            return $this->request->returnResponse();
        };
        // Validate GET parameters
        if(!$this->request->defineGetSchema(["id" => "int"])) {
            return $this->request->returnResponse();
        }
        
        //get the id from the url and if not exist or not type of int return 400 die()
        $id = $this->request->intValueGet("id");
        if(!$id) {
            return $this->request->returnResponse();
        }
        
        //manually set the id to the post request
        $this->request->post['id'] = $id;
        return (new UserController($this->request))->read();
    }

    /**
     * Update User
     * 
     * @return JsonResponse
     * @http POST
     * @description Update existing User in database
     * @example /api/User/update
     */
    public function update(): JsonResponse
    {
        if(!$this->request->definePostSchema([
            'id' => 'int',
            '?name' => 'string',
            '?description' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        return (new UserController($this->request))->update();
    }

    /**
     * Delete User
     * 
     * @return JsonResponse
     * @http POST
     * @description Delete User from database
     * @example /api/User/delete
     */
    public function delete(): JsonResponse
    {
        if(!$this->request->definePostSchema([
            'id' => 'int',
        ])) {
            return $this->request->returnResponse();
        }
        return (new UserController($this->request))->delete();
    }

    /**
     * List all Users
     * 
     * @return JsonResponse
     * @http GET
     * @description Get list of all Users with filtering and sorting
     * @example /api/User/list/?sort_by=name&find_like=name=test
     */
    public function list(): JsonResponse
    {
        // Define searchable fields and their types
        $this->request->findable([
            'name' => 'string',
            'description' => 'string'
        ]);

        // Define sortable fields
        $this->request->sortable([
            'id',
            'name',
            'description'
        ]);
        
        return (new UserController($this->request))->list();
    }

        /**
     * User Login
     * 
     * @return JsonResponse
     * @http POST
     * @description User login with email and password
     * @example /api/auth/login
     */
    public function login(): JsonResponse{
        if(!$this->request->definePostSchema([
            'email' => 'email',
            'password' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        //return Response::success($this->request->post, 1,"Token is valid");
        //echo "hi".$this->request->post['email'];
        return (new UserController($this->request))->loginByEmailPassword();
    }

    /**
     * Summary of validateToken
     * @return JsonResponse
     * @http GET
     * @description Validate token , just send get request with token in Authorization header Bearer <token>
     * @example /api/auth/validateToken
     */
    public function validateToken(): JsonResponse{
        if(!$this->request->auth()){
            return Response::unauthorized("Invalid or missing token");
        }
        return Response::success(null, 1,"Token is valid");
    }

    /**
     * Summary of renewToken
     * @return JsonResponse
     * @http GET
     * @description Renew token , send get request with token in Authorization header Bearer <token>
     * @example /api/auth/renewToken
     */
    public function renewToken(): JsonResponse{
        if(!$this->request->auth()){
            return Response::unauthorized("Invalid or missing token");
        }
        return (new UserController($this->request))->renewToken();
    }

    /**
     * Generates mock responses for API documentation
     * 
     * @param string \$method The API method name
     * @return array<mixed> Example response data for the specified method
     * @hidden
     */
    public static function mockResponse(string $method): array
    {
        return match($method) {
            'create' => [
                'response_code' => 201,
                'message' => 'created',
                'count' => 1,
                'service_message' => 'User created successfully',
                'data' => [
                    'id' => 1,
                    'name' => 'Sample User',
                    'description' => 'User description'
                ]
            ],
            'read' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'User retrieved successfully',
                'data' => [
                    'id' => 1,
                    'name' => 'Sample User',
                    'description' => 'User description'
                ]
            ],
            'update' => [
                'response_code' => 209,
                'message' => 'updated',
                'count' => 1,
                'service_message' => 'User updated successfully',
                'data' => [
                    'id' => 1,
                    'name' => 'Updated User',
                    'description' => 'Updated description'
                ]
            ],
            'delete' => [
                'response_code' => 210,
                'message' => 'deleted',
                'count' => 1,
                'service_message' => 'User deleted successfully',
                'data' => null
            ],
            'list' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 2,
                'service_message' => 'Users retrieved successfully',
                'data' => [
                    [
                        'id' => 1,
                        'name' => 'User 1',
                        'description' => 'Description 1'
                    ],
                    [
                        'id' => 2,
                        'name' => 'User 2',
                        'description' => 'Description 2'
                    ]
                ]
            ],
            default => [
                'success' => false,
                'message' => 'Unknown method'
            ]
        };
    }
} 
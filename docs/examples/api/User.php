<?php

declare(strict_types=1);

namespace App\Api;

use App\Controller\UserController;
use Gemvc\Core\ProtectedApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 1 — API: public service contract for User (CRUD + register).
 *
 * Login / register / JWT live on {@see Auth}
 * (`/api/Auth/login`, `/api/Auth/register`) — keep credentials
 * off the User CRUD surface and rate-limit Auth separately.
 *
 * Copy → app/api/User.php
 */
class User extends ProtectedApiService
{
    public function __construct(Request $request)
    {
        // ProtectedApiService = public by default. Per-method auth() where needed (update/delete).
        // For "everything requires login", extend ProtectedApiService instead.
        parent::__construct($request, ['admin']); // or null / omitted = any authenticated user , now only admin has access to this service
    }

    /**
     * @http POST
     * @description Create a new user
     * @example /api/User/create
     */
    public function create(): JsonResponse
    {
        // Mass-assignment gate: only listed keys accepted. `?` = optional.
        if (!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string',
            '?description' => 'string',
            '?role' => 'string',
        ])) {
            // Schema failed → Request already built a 400 JsonResponse
            return $this->request->returnResponse();
        }

        // Extra string length rules (min|max)
        if (!$this->request->validateStringPosts([
            'name' => '2|100',
            'password' => '8|128',
        ])) {
            return $this->request->returnResponse();
        }

        // callController = invoke + optional APM controller span
        return $this->callController(new UserController($this->request))->create();
    }

    /**
     * @http GET
     * @description Get user by id
     * @example /api/User/read/?id=1
     */
    public function read(): JsonResponse
    {
        if (!$this->request->defineGetSchema(['id' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new UserController($this->request))->read();
    }

    /**
     * @http POST
     * @description Update user (admin)
     * @example /api/User/update
     */
    public function update(): JsonResponse
    {
        // Role check: 401 if no/invalid token, 403 if wrong role
        if (!$this->request->auth(['admin'])) {
            return $this->request->returnResponse();
        }

        if (!$this->request->definePostSchema([
            'id' => 'int',
            '?name' => 'string',
            '?email' => 'email',
            '?description' => 'string',
            '?role' => 'string',
            '?password' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new UserController($this->request))->update();
    }

    /**
     * @http POST
     * @description Delete user (admin)
     * @example /api/User/delete
     */
    public function delete(): JsonResponse
    {
        if (!$this->request->auth(['admin'])) {
            return $this->request->returnResponse();
        }
        if (!$this->request->definePostSchema(['id' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new UserController($this->request))->delete();
    }

    /**
     * @http GET
     * @description List users with allowlisted find / filter / sort
     * @example /api/User/list/?find_like=name=ali&filter_by=role=admin&sort_by=created_at
     */
    public function list(): JsonResponse
    {
        // FLAGSHIP LISTS — only allowlisted fields can become SQL.
        // findable  → GET find_like=name=ali   (LIKE)
        // filterable → GET filter_by=role=admin (exact)
        // sortable  → GET sort_by=created_at&sort_by_asc=1
        $this->request->findable([
            'name' => 'string',
            'email' => 'email',
            'description' => 'string',
        ]);
        $this->request->filterable([
            'role' => 'string',
        ]);
        $this->request->sortable([
            'id',
            'name',
            'email',
            'role',
            'created_at',
        ]);

        return $this->callController(new UserController($this->request))->list();
    }

    /**
     * @hidden
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return match ($method) {
            'create' => [
                'response_code' => 201,
                'message' => 'created',
                'count' => 1,
                'service_message' => 'User created successfully',
                'data' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'user'],
            ],
            'read', 'list' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'OK',
                'data' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'user'],
            ],
            default => ['response_code' => 404, 'message' => 'Not found'],
        };
    }
}

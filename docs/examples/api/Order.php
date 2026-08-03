<?php

declare(strict_types=1);

namespace App\Api;

use App\Controller\OrderController;
use Gemvc\Core\ProtectedApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 1 — API for Order (authenticated by default).
 *
 * ProtectedApiService runs requireAuth() in the constructor —
 * every method below needs a valid JWT unless you override that.
 *
 * MONEY: schema type `decimal:12,2` validates precision; value stays a string.
 *
 * Copy → app/api/Order.php
 */
class Order extends ProtectedApiService
{
    public function __construct(Request $request)
    {
        // Second arg can restrict roles: parent::__construct($request, ['admin']);
        // null / omitted = any authenticated user
        parent::__construct($request);
    }

    /**
     * @http POST
     * @description Create order (total as decimal string — never float)
     * @example /api/Order/create
     */
    public function create(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'user_id' => 'int',
            'total' => 'decimal:12,2', // e.g. "99.50" — not 99.5 as float
            '?status' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->create();
    }

    /**
     * @http GET
     * @description Get order by id
     * @example /api/Order/read/?id=1
     */
    public function read(): JsonResponse
    {
        if (!$this->request->defineGetSchema(['id' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->read();
    }

    /**
     * @http POST
     * @description Update order
     * @example /api/Order/update
     */
    public function update(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'id' => 'int',
            '?user_id' => 'int',
            '?total' => 'decimal:12,2',
            '?status' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->update();
    }

    /**
     * @http POST
     * @description Delete order
     * @example /api/Order/delete
     */
    public function delete(): JsonResponse
    {
        $this->requireAuth(['admin']); // or null / omitted = any authenticated user , now only admin has access to this service
        if (!$this->request->definePostSchema(['id' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->delete();
    }

    /**
     * @http GET
     * @description List orders with allowlisted find / filter / sort
     * @example /api/Order/list/?filter_by=status=pending&sort_by=created_at
     */
    public function list(): JsonResponse
    {
        $this->requireAuth(['admin']); // or null / omitted = any authenticated user , now only admin has access to this service
        // Unlisted query fields never become WHERE clauses (SQL injection + mass-filter safety)
        $this->request->findable([
            'status' => 'string',
        ]);
        $this->request->filterable([
            'user_id' => 'int',
            'status' => 'string',
        ]);
        $this->request->sortable([
            'id',
            'user_id',
            'total',
            'status',
            'created_at',
        ]);

        return $this->callController(new OrderController($this->request))->list();
    }

    /**
     * @http GET
     * @description List orders for one user (admin). Prefer myOrders for self-service.
     * @example /api/Order/listByUser/?user_id=1
     */
    public function listByUser(): JsonResponse
    {
        $this->requireAuth(['admin']);
        if (!$this->request->defineGetSchema(['user_id' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->listByUser();
    }

    /**
     * @http GET
     * @description Current user's orders only (user_id from JWT — never from the client)
     * @example /api/Order/myOrders/?status=pending
     */
    public function myOrders(): JsonResponse
    {
        // Optional status filter only — ownership is always JWT user_id
        if (!$this->request->defineGetSchema([
            '?status' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->myOrders();
    }

    /**
     * @http GET
     * @description Read one of the current user's orders (404 if not owned)
     * @example /api/Order/myOrder/?id=1
     */
    public function myOrder(): JsonResponse
    {
        if (!$this->request->defineGetSchema(['id' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new OrderController($this->request))->myOrder();
    }

    /**
     * @hidden
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        $order = [
            'id' => 1,
            'user_id' => 1,
            'total' => '99.50',
            'status' => 'pending',
        ];

        return match ($method) {
            'create' => [
                'response_code' => 201,
                'message' => 'created',
                'count' => 1,
                'service_message' => 'Order created successfully',
                'data' => $order,
            ],
            'read', 'list', 'listByUser', 'myOrders', 'myOrder' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'OK',
                'data' => $method === 'myOrders' || $method === 'list' || $method === 'listByUser'
                    ? [$order]
                    : $order,
            ],
            default => ['response_code' => 404, 'message' => 'Not found'],
        };
    }
}

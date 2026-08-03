<?php

declare(strict_types=1);

namespace App\Api;

use App\Controller\UserOrderSummaryController;
use Gemvc\Core\ProtectedApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 1 — API for the UserOrderSummary SQL VIEW (read-only).
 *
 * There is intentionally NO create / update / delete here.
 * Mutate data via /api/User/… and /api/Order/…; this service only reads aggregates.
 *
 * Copy → app/api/UserOrderSummary.php
 */
class UserOrderSummary extends ProtectedApiService
{
    public function __construct(Request $request)
    {
        // JWT required for all methods on this service
        parent::__construct($request, ['admin']); // or null / omitted = any authenticated user , now only admin has access to this service
    }

    /**
     * @http GET
     * @description Get one user order summary (optional nested recent orders)
     * @example /api/UserOrderSummary/read/?user_id=1&with_recent_orders=1
     */
    public function read(): JsonResponse
    {
        // user_id is required (view logical key). with_recent_orders is optional flag.
        if (!$this->request->defineGetSchema([
            'user_id' => 'int',
            '?with_recent_orders' => 'string', // "1" → Model nests OrderTable rows
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new UserOrderSummaryController($this->request))->read();
    }

    /**
     * @http GET
     * @description List user order summaries (ViewTable + createList)
     * @example /api/UserOrderSummary/list/?sort_by=order_total&sort_by_asc=0
     */
    public function list(): JsonResponse
    {
        // Allowlists use VIEW aliases (user_name, order_total, …) — not underlying table columns only
        $this->request->findable([
            'user_name' => 'string',
            'email' => 'email',
        ]);
        $this->request->filterable([
            'role' => 'string',
            'user_id' => 'int',
        ]);
        $this->request->sortable([
            'user_id',
            'user_name',
            'email',
            'role',
            'order_count',
            'order_total',
            'last_order_at',
        ]);

        return $this->callController(new UserOrderSummaryController($this->request))->list();
    }

    /**
     * @http GET
     * @description Top spenders from the SQL view
     * @example /api/UserOrderSummary/topSpenders/?limit=20
     */
    public function topSpenders(): JsonResponse
    {
        if (!$this->request->defineGetSchema(['?limit' => 'int'])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new UserOrderSummaryController($this->request))->topSpenders();
    }

    /**
     * @hidden
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        $row = [
            'user_id' => 1,
            'user_name' => 'Ada',
            'email' => 'ada@example.com',
            'role' => 'user',
            'order_count' => 3,
            'order_total' => '249.90',
            'last_order_at' => '2026-08-01 12:00:00',
        ];

        return match ($method) {
            'read' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'User order summary retrieved successfully',
                // $_recent_orders only when with_recent_orders=1
                'data' => $row + ['_recent_orders' => []],
            ],
            'list', 'topSpenders' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'OK',
                'data' => [$row],
            ],
            default => ['response_code' => 404, 'message' => 'Not found'],
        };
    }
}

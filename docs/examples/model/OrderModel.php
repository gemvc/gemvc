<?php

declare(strict_types=1);

namespace App\Model;

use App\Table\OrderTable;
use App\Table\UserTable;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

/**
 * LAYER 3 — Model: order rules (FK exists, money >= 0, defaults).
 *
 * MONEY
 *   `$this->total` is a string decimal from the API schema (`decimal:12,2`).
 *   Use BCMath (`bccomp`) — never cast to float.
 *
 * Copy → app/model/OrderModel.php
 */
class OrderModel extends OrderTable
{
    public function __construct()
    {
        parent::__construct();
    }

    public function createModel(): JsonResponse
    {
        // Business check: FK must point at a real user (DB FK also enforces this)
        $user = (new UserTable())->selectById($this->user_id);
        if ($user === null) {
            return Response::unprocessableEntity('User does not exist');
        }

        // bccomp(a, b, scale): returns -1 if a < b, 0 if equal, 1 if a > b
        if ($this->total === '' || bccomp($this->total, '0', 2) < 0) {
            return Response::unprocessableEntity('Order total must be >= 0');
        }

        if ($this->status === '') {
            $this->status = 'pending';
        }

        $this->created_at = date('Y-m-d H:i:s');

        if ($this->insertSingleQuery() === null) {
            return Response::internalError('Failed to create Order: ' . $this->getError());
        }

        // After insert, UserOrderSummary VIEW aggregates update automatically on next read
        return Response::created($this, 1, 'Order created successfully');
    }

    public function readModel(): JsonResponse
    {
        $found = $this->selectById($this->id);
        if ($found === null) {
            return Response::notFound('Order not found');
        }

        return Response::success($found, 1, 'Order retrieved successfully');
    }

    public function updateModel(): JsonResponse
    {
        if ($this->selectById($this->id) === null) {
            return Response::notFound('Order not found');
        }

        if ($this->total !== '' && bccomp($this->total, '0', 2) < 0) {
            return Response::unprocessableEntity('Order total must be >= 0');
        }

        $this->updated_at = date('Y-m-d H:i:s');
        $result = $this->updateSingleQuery();
        if ($this->getError()) {
            return Response::internalError('Failed to update Order: ' . $this->getError());
        }

        return Response::updated($result, 1, 'Order updated successfully');
    }

    public function deleteModel(): JsonResponse
    {
        if ($this->selectById($this->id) === null) {
            return Response::notFound('Order not found');
        }

        $result = $this->deleteByIdQuery($this->id);
        if ($this->getError()) {
            return Response::internalError('Failed to delete Order: ' . $this->getError());
        }

        return Response::deleted($result, 1, 'Order deleted successfully');
    }

    /**
     * Dedicated endpoint helper (admin listByUser — also covered by list + filter_by=user_id=…).
     */
    public function listByUserModel(int $userId): JsonResponse
    {
        $rows = $this->selectByUserId($userId);
        if ($this->getError()) {
            return Response::internalError('Failed to list Orders: ' . $this->getError());
        }

        $count = is_array($rows) ? count($rows) : 0;

        return Response::success($rows ?? [], $count, 'Orders retrieved successfully');
    }

    /**
     * Orders for the authenticated user only.
     * $userId MUST come from Request::userId() (JWT) — never from client input.
     */
    public function myOrdersModel(int $userId, ?string $status = null): JsonResponse
    {
        $query = $this->select()->whereEqual('user_id', $userId);

        if ($status !== null && $status !== '') {
            $query->whereEqual('status', $status);
        }

        /** @var array<static>|null $rows */
        $rows = $query->orderBy('created_at', false)->run();
        if ($this->getError()) {
            return Response::internalError('Failed to list your Orders: ' . $this->getError());
        }

        $count = is_array($rows) ? count($rows) : 0;

        return Response::success($rows ?? [], $count, 'Your orders retrieved successfully');
    }

    /**
     * Single order if (and only if) it belongs to $userId from JWT.
     * Same 404 for missing and not-owned — do not leak other users' order ids.
     */
    public function myOrderModel(int $orderId, int $userId): JsonResponse
    {
        $found = $this->selectById($orderId);
        if ($found === null || $found->user_id !== $userId) {
            return Response::notFound('Order not found');
        }

        return Response::success($found, 1, 'Order retrieved successfully');
    }
}

<?php

declare(strict_types=1);

namespace App\Model;

use App\Table\OrderTable;
use App\Table\UserOrderSummaryTable;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

/**
 * LAYER 3 — Model on a ViewTable (READ-ONLY persistence).
 *
 * This class:
 *   ✓ Reads flat rows from the SQL VIEW
 *   ✓ Optionally nests recent orders into `$_recent_orders` (underscore = not a DB column)
 *   ✗ Must NOT call insert/update/delete on the view (ViewTable hard-fails)
 *
 * Writes always go through UserModel / OrderModel.
 *
 * Copy → app/model/UserOrderSummaryModel.php
 */
class UserOrderSummaryModel extends UserOrderSummaryTable
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Load a short list of OrderTable rows AFTER the view row is loaded.
     *
     * Why here (not in defineView)?
     *   SQL views stay flat. 1:n nesting is a Model concern (aggregation property `_…`).
     */
    public function withRecentOrders(int $limit = 5): self
    {
        if ($this->user_id <= 0) {
            $this->_recent_orders = [];

            return $this;
        }

        // Query the physical OrderTable — not the view
        $this->_recent_orders = (new OrderTable())->selectRecentByUserId($this->user_id, $limit) ?? [];

        return $this;
    }

    /**
     * @param bool $withRecentOrders pass true when API asked ?with_recent_orders=1
     */
    public function readModel(bool $withRecentOrders = false): JsonResponse
    {
        // selectByUserId is defined on the ViewTable (logical key = user_id)
        $found = $this->selectByUserId($this->user_id);
        if ($found === null) {
            return Response::notFound('User order summary not found');
        }

        if ($withRecentOrders) {
            // $found is the same class (static) when queried from this Model
            $found->withRecentOrders();
        }

        return Response::success($found, 1, 'User order summary retrieved successfully');
    }

    /**
     * Convenience wrapper around ViewTable::selectTopSpenders().
     */
    public function topSpendersModel(int $limit = 50): JsonResponse
    {
        $rows = $this->selectTopSpenders($limit);
        if ($this->getError()) {
            return Response::internalError('Failed to load top spenders: ' . $this->getError());
        }

        $count = is_array($rows) ? count($rows) : 0;

        return Response::success($rows ?? [], $count, 'Top spenders retrieved successfully');
    }
}

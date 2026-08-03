<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\UserOrderSummaryModel;
use Gemvc\Core\Controller;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 2 — Controller for the SQL VIEW read model.
 *
 * No mapPostToObject for writes — this surface is read-only.
 * Lists still use createList() against the ViewTable-backed Model.
 *
 * Copy → app/controller/UserOrderSummaryController.php
 */
class UserOrderSummaryController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function read(): JsonResponse
    {
        $model = $this->createModel(new UserOrderSummaryModel());

        // Logical key on the view is user_id (see setPrimaryKey on the Table)
        $userId = $this->request->intValueGet('user_id');
        if ($userId === null || $userId <= 0) {
            return $this->request->returnResponse();
        }
        $model->user_id = $userId;

        // Optional nest: ?with_recent_orders=1 → Model loads OrderTable rows into $_recent_orders
        $withRecent = $this->request->stringValueGet('with_recent_orders') === '1';

        return $model->readModel($withRecent);
    }

    /**
     * Same flagship list pattern as physical tables — filters hit VIEW aliases.
     */
    public function list(): JsonResponse
    {
        return $this->createList(
            $this->createModel(new UserOrderSummaryModel()),
            'user_id,user_name,email,role,order_count,order_total,last_order_at'
            // note: $_recent_orders is NOT listed — nest only on read/?with_recent_orders=1
        );
    }

    public function topSpenders(): JsonResponse
    {
        $model = $this->createModel(new UserOrderSummaryModel());

        $limit = $this->request->intValueGet('limit') ?? 50;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200; // hard cap so clients cannot request unbounded rows
        }

        return $model->topSpendersModel($limit);
    }
}

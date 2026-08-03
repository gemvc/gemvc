<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\OrderModel;
use Gemvc\Core\Controller;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 2 — Controller for OrderModel.
 *
 * `total` arrives as a validated decimal string from the API schema —
 * map it straight onto the Model property (still a string, never float).
 *
 * Copy → app/controller/OrderController.php
 */
class OrderController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function create(): JsonResponse
    {
        $model = $this->request->mapPostToObject(
            $this->createModel(new OrderModel()),
            [
                'user_id' => 'user_id',
                'total' => 'total',     // string decimal from definePostSchema
                'status' => 'status',
            ]
        );
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        return $model->createModel();
    }

    public function read(): JsonResponse
    {
        $model = $this->createModel(new OrderModel());
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        $id = $this->request->intValueGet('id') ?? $this->request->intValuePost('id');
        if ($id === null || $id <= 0) {
            return $this->request->returnResponse();
        }
        $model->id = $id;

        return $model->readModel();
    }

    public function update(): JsonResponse
    {
        $model = $this->request->mapPostToObject(
            $this->createModel(new OrderModel()),
            [
                'id' => 'id',
                'user_id' => 'user_id',
                'total' => 'total',
                'status' => 'status',
            ]
        );
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        return $model->updateModel();
    }

    public function delete(): JsonResponse
    {
        $model = $this->createModel(new OrderModel());
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        $id = $this->request->intValuePost('id');
        if ($id === null || $id <= 0) {
            return $this->request->returnResponse();
        }
        $model->id = $id;

        return $model->deleteModel();
    }

    public function list(): JsonResponse
    {
        // Columns returned to the client (explicit is safer than "all public props")
        return $this->createList(
            $this->createModel(new OrderModel()),
            'id,user_id,total,status,created_at,updated_at'
        );
    }

    /**
     * Dedicated filter by user (admin listByUser). Clients must use myOrders().
     */
    public function listByUser(): JsonResponse
    {
        $model = $this->createModel(new OrderModel());
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        $userId = $this->request->intValueGet('user_id');
        if ($userId === null || $userId <= 0) {
            return $this->request->returnResponse();
        }

        return $model->listByUserModel($userId);
    }

    /**
     * Self-service list: JWT user_id only (client cannot pick another user).
     */
    public function myOrders(): JsonResponse
    {
        $userId = $this->request->userId();
        if (!is_int($userId) || $userId <= 0) {
            return $this->request->returnResponse(); // 401/403 already set by userId()
        }

        $model = $this->createModel(new OrderModel());
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        $status = $this->request->stringValueGet('status');

        return $model->myOrdersModel($userId, $status);
    }

    /**
     * Self-service read: order must belong to the JWT user.
     */
    public function myOrder(): JsonResponse
    {
        $userId = $this->request->userId();
        if (!is_int($userId) || $userId <= 0) {
            return $this->request->returnResponse();
        }

        $orderId = $this->request->intValueGet('id');
        if ($orderId === null || $orderId <= 0) {
            return $this->request->returnResponse();
        }

        $model = $this->createModel(new OrderModel());
        if (!$model instanceof OrderModel) {
            return $this->request->returnResponse();
        }

        return $model->myOrderModel($orderId, $userId);
    }
}

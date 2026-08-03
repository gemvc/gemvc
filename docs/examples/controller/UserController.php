<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\UserModel;
use Gemvc\Core\Controller;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 2 — Controller: orchestration only (no business rules, no SQL).
 *
 * JOB
 *   1. Wrap Models with createModel() so Request/APM reach DB work
 *   2. Map sanitized POST/GET onto the Model
 *   3. Call Model methods or createList()
 *   4. Always return JsonResponse
 *
 * Copy → app/controller/UserController.php
 */
class UserController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function create(): JsonResponse
    {
        // mapPostToObject:
        //   'field' => 'property'     → copy request field onto model property
        //   'field' => 'setPassword()' → call setter (hashes before assign)
        $model = $this->request->mapPostToObject(
            $this->createModel(new UserModel()), // createModel = APM + request context
            [
                'name' => 'name',
                'email' => 'email',
                'password' => 'setPassword()',
                'description' => 'description',
                'role' => 'role',
            ]
        );

        // Mapping failed (type / missing) → Request already holds the error response
        if (!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }

        return $model->createModel();
    }

    public function read(): JsonResponse
    {
        $model = $this->createModel(new UserModel());

        // Prefer GET id (defineGetSchema in API); fall back to POST if present
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
            $this->createModel(new UserModel()),
            [
                'id' => 'id',
                'name' => 'name',
                'email' => 'email',
                'description' => 'description',
                'role' => 'role',
                'password' => 'setPassword()', // optional; only hashed if posted
            ]
        );
        if (!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }

        return $model->updateModel();
    }

    public function delete(): JsonResponse
    {
        $model = $this->createModel(new UserModel());
        $id = $this->request->intValuePost('id');
        if ($id === null || $id <= 0) {
            return $this->request->returnResponse();
        }
        $model->id = $id;

        return $model->deleteModel();
    }

    /**
     * Flagship list: API already called findable/filterable/sortable.
     * createList applies those allowlists + pagination + total count.
     *
     * Pass an explicit column list so `protected $password` never appears.
     */
    public function list(): JsonResponse
    {
        return $this->createList(
            $this->createModel(new UserModel()),
            'id,name,email,created_at,updated_at,description,role'
        );
    }
}

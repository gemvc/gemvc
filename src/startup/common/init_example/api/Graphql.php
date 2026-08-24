<?php

namespace App\Api;

use Gemvc\Core\ApiService;
use Gemvc\GraphQL\GraphQlRunner;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 1 — GraphQL public contract.
 *
 * POST /api/Graphql/query  (optional reverse-proxy: /graphql → this URL)
 * Does not replace REST. Resolvers must call Controller/Model — never Table.
 *
 * Authenticated GraphQL: extend ProtectedApiService instead (JWT before execute).
 */
class Graphql extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Execute a GraphQL operation.
     *
     * @return JsonResponse
     * @http POST
     * @description GraphQL query/mutation. Body: query (string), optional variables, operationName.
     * @example /api/Graphql/query
     */
    public function query(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'query' => 'string',
            '?variables' => 'array',
            '?operationName' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return GraphQlRunner::fromRequest($this->request)->execute();
    }
}

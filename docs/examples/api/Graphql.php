<?php

declare(strict_types=1);

namespace App\Api;

use Gemvc\Core\ApiService;
use Gemvc\GraphQL\GraphQlRunner;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 1 — GraphQL public contract. POST /api/Graphql/query
 *
 * Copy → app/api/Graphql.php
 * Requires: composer require webonyx/graphql-php
 */
class Graphql extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @http POST
     * @description GraphQL query. Body: { "query": "...", "variables": {}, "operationName": "" }
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

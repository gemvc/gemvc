<?php

namespace App\Controller;

use Gemvc\Core\Controller;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;

/**
 * LAYER 2 — GraphQL resolvers call this (not Table).
 *
 * Keep business rules in Model. This sample hello() has no DB.
 */
class GraphQlController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function hello(): JsonResponse
    {
        return Response::success('gemvc');
    }
}

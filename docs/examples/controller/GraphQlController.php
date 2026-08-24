<?php

declare(strict_types=1);

namespace App\Controller;

use Gemvc\Core\Controller;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;

/**
 * LAYER 2 — GraphQL field resolvers call Controller methods (not Table).
 *
 * Copy → app/controller/GraphQlController.php
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

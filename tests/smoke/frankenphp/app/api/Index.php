<?php

namespace App\Api;

use Gemvc\Core\ApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;

class Index extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function index(): JsonResponse
    {
        return $this->ping();
    }

    public function ping(): JsonResponse
    {
        $mode = $_ENV['SMOKE_MODE'] ?? 'unknown';
        return Response::success([
            'ok' => true,
            'mode' => $mode,
            'uri' => $this->request->requestedUrl,
        ], 1, 'smoke ok');
    }
}

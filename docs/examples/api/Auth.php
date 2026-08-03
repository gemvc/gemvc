<?php

declare(strict_types=1);

namespace App\Api;

use App\Controller\AuthController;
use Gemvc\Core\ApiService;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;

/**
 * LAYER 1 — Public Auth API (register / login + JWT). No ProtectedApiService here.
 *
 * WHY PUBLIC?
 *   Callers do not have a token yet when they register or log in. Keep CRUD on
 *   ProtectedApiService (Order, UserOrderSummary) and put credentials here.
 *
 * RATE LIMIT
 *   Constructor: requireRateLimit(1, 'ip') → max 1 request/second per IP
 *   for the whole Auth service (register / login / renew / validate). HTTP 429 when exceeded.
 *   Uses REQUEST_RATE_LIMIT_DRIVER from .env (apcu|redis|both|none).
 *
 * URLS
 *   POST /api/Auth/register
 *   POST /api/Auth/login
 *   GET  /api/Auth/validateToken   (Bearer JWT)
 *   GET  /api/Auth/renewToken      (Bearer JWT)
 *
 * Copy → app/api/Auth.php
 */
class Auth extends ApiService
{
    public function __construct(Request $request)
    {
        parent::__construct($request);

        // 1 request per second, scoped to client IP (brute-force / spam protection).
        // Signature: requireRateLimit(perSec, scope, blockSeconds)
        //   scope: 'ip' | 'token' | 'both'
        // Throws RateLimitException → Bootstrap returns HTTP 429
        $this->requireRateLimit(1, 'ip');
    }

    /**
     * @http POST
     * @description Register a new user (public signup); hashes password, rejects duplicate email
     * @example /api/Auth/register
     */
    public function register(): JsonResponse
    {
        // Public signup: do NOT accept `role` here — Controller forces role = 'user'
        // (admins are created via protected User/create or CLI admin:*)
        if (!$this->request->definePostSchema([
            'name' => 'string',
            'email' => 'email',
            'password' => 'string',
            '?description' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        if (!$this->request->validateStringPosts([
            'name' => '2|100',
            'password' => '8|128',
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new AuthController($this->request))->register();
    }

    /**
     * @http POST
     * @description Login with email/password; returns access, refresh, and login JWTs
     * @example /api/Auth/login
     */
    public function login(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'email' => 'email',
            'password' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        if (!$this->request->validateStringPosts([
            'password' => '8|128',
        ])) {
            return $this->request->returnResponse();
        }

        return $this->callController(new AuthController($this->request))->login();
    }

    /**
     * @http GET
     * @description Validate Bearer JWT (any authenticated token type)
     * @example /api/Auth/validateToken
     */
    public function validateToken(): JsonResponse
    {
        // Still public service class, but this method requires a valid JWT
        if (!$this->request->auth()) {
            return $this->request->returnResponse(); // 401
        }

        return $this->callController(new AuthController($this->request))->validateToken();
    }

    /**
     * @http GET
     * @description Renew JWT from Authorization Bearer token (access / refresh / login)
     * @example /api/Auth/renewToken
     */
    public function renewToken(): JsonResponse
    {
        if (!$this->request->auth()) {
            return $this->request->returnResponse();
        }

        return $this->callController(new AuthController($this->request))->renewToken();
    }

    /**
     * @hidden
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return match ($method) {
            'register' => [
                'response_code' => 201,
                'message' => 'created',
                'count' => 1,
                'service_message' => 'User created successfully',
                'data' => [
                    'id' => 1,
                    'name' => 'Ada',
                    'email' => 'ada@example.com',
                    'role' => 'user',
                ],
            ],
            'login' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => 1,
                        'name' => 'Ada',
                        'email' => 'ada@example.com',
                        'role' => 'user',
                    ],
                    'access_token' => 'eyJ…',
                    'refresh_token' => 'eyJ…',
                    'login_token' => 'eyJ…',
                ],
            ],
            'validateToken' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'Token is valid',
                'data' => null,
            ],
            'renewToken' => [
                'response_code' => 200,
                'message' => 'OK',
                'count' => 1,
                'service_message' => 'access token renewed successfully',
                'data' => ['token' => 'eyJ…'],
            ],
            default => ['response_code' => 404, 'message' => 'Not found'],
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\UserModel;
use Gemvc\Core\Controller;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;

/**
 * LAYER 2 — Auth orchestration (register / login / JWT validate / renew).
 *
 * Reuses UserModel for signup, credential check, and JWTToken creation.
 * No AuthTable — authentication is not a separate DB entity here.
 *
 * Copy → app/controller/AuthController.php
 */
class AuthController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Public signup → UserModel::createModel().
     * Forces role = 'user' so clients cannot self-promote to admin.
     */
    public function register(): JsonResponse
    {
        $model = $this->request->mapPostToObject(
            $this->createModel(new UserModel()),
            [
                'name' => 'name',
                'email' => 'email',
                'password' => 'setPassword()', // Argon2i via CryptHelper
                'description' => 'description',
            ]
        );
        if (!$model instanceof UserModel) {
            return $this->request->returnResponse();
        }

        // Never trust client-supplied role on public register
        $model->role = 'user';

        return $model->createModel();
    }

    /**
     * Email + password → UserModel::loginByEmailPassword() → JWT trio.
     */
    public function login(): JsonResponse
    {
        $model = $this->createModel(new UserModel());

        $email = $this->request->stringValuePost('email') ?? '';
        $password = $this->request->stringValuePost('password') ?? '';

        // UserModel verifies hash, builds access / refresh / login tokens
        return $model->loginByEmailPassword($email, $password);
    }

    /**
     * auth() already ran in the API — token is present and verified.
     */
    public function validateToken(): JsonResponse
    {
        $token = $this->request->getJwtToken();
        if ($token === null) {
            return Response::unauthorized('Invalid or missing token');
        }

        return Response::success(null, 1, 'Token is valid');
    }

    /**
     * Issue a new token of the same type (access / refresh / login)
     * using TTL env vars when set.
     */
    public function renewToken(): JsonResponse
    {
        $token = $this->request->getJwtToken();
        if ($token === null) {
            return Response::unauthorized('Invalid or missing token');
        }

        // verify(): false|JWTToken
        $verified = $token->verify();
        if ($verified === false) {
            return Response::unauthorized('Invalid or expired token');
        }

        $tokenType = $verified->GetType() ?? 'access';

        $seconds = match ($tokenType) {
            'refresh' => $this->envSeconds('REFRESH_TOKEN_VALIDATION_IN_SECONDS'),
            'access' => $this->envSeconds('ACCESS_TOKEN_VALIDATION_IN_SECONDS'),
            'login' => $this->envSeconds('LOGIN_TOKEN_VALIDATION_IN_SECONDS'),
            default => 0,
        };

        $newToken = $verified->renew($seconds);
        if ($newToken === false) {
            return Response::unauthorized('Unable to renew token');
        }

        $payload = new \stdClass();
        $payload->token = $newToken;
        $payload->type = $tokenType;

        return Response::success($payload, 1, $tokenType . ' token renewed successfully');
    }

    private function envSeconds(string $key): int
    {
        $raw = $_ENV[$key] ?? null;
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return 0;
    }
}

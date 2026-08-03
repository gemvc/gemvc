<?php

declare(strict_types=1);

namespace App\Model;

use App\Table\UserTable;
use Gemvc\Helper\CryptHelper;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\JWTToken;
use Gemvc\Http\Response;

/**
 * LAYER 3 — Model: business rules on top of UserTable.
 *
 * FLOW
 *   API validates → Controller maps request onto this Model → Model methods run
 *   → Table CRUD / queries → JsonResponse back up the stack
 *
 * Copy → app/model/UserModel.php
 */
class UserModel extends UserTable
{
    public function __construct()
    {
        // Always call parent so Table/connection defaults initialize.
        parent::__construct();
    }

    /**
     * Called from Controller mapPostToObject via 'password' => 'setPassword()'.
     * Hashes with Argon2i (CryptHelper) before the value ever hits insert/update.
     */
    public function setPassword(string $plainPassword): void
    {
        $this->password = CryptHelper::hashPassword($plainPassword);
    }

    /**
     * Create user: normalize email, reject duplicates, insert, return 201.
     */
    public function createModel(): JsonResponse
    {
        // Normalize before uniqueness check + insert
        $this->email = strtolower(trim($this->email));

        if ($this->selectByEmail($this->email) !== null) {
            // 422 = business rule failed (valid request shape, invalid state)
            return Response::unprocessableEntity('User already exists');
        }

        $this->created_at = date('Y-m-d H:i:s');

        // insertSingleQuery() writes public + protected column props (not `_` props)
        if ($this->insertSingleQuery() === null) {
            return Response::internalError('Failed to create User: ' . $this->getError());
        }

        return Response::created($this, 1, 'User created successfully');
    }

    public function readModel(): JsonResponse
    {
        // selectById uses the Table PK (property `id` on UserTable)
        $found = $this->selectById($this->id);
        if ($found === null) {
            return Response::notFound('User not found');
        }

        // password is protected — list payloads usually omit it; full object may still hydrate it
        return Response::success($found, 1, 'User retrieved successfully');
    }

    public function updateModel(): JsonResponse
    {
        if ($this->selectById($this->id) === null) {
            return Response::notFound('User not found');
        }

        $this->updated_at = date('Y-m-d H:i:s');

        $result = $this->updateSingleQuery();
        if ($this->getError()) {
            return Response::internalError('Failed to update User: ' . $this->getError());
        }

        // HTTP 209 in GEMVC = updated
        return Response::updated($result, 1, 'User updated successfully');
    }

    public function deleteModel(): JsonResponse
    {
        if ($this->selectById($this->id) === null) {
            return Response::notFound('User not found');
        }

        $result = $this->deleteByIdQuery($this->id);
        if ($this->getError()) {
            return Response::internalError('Failed to delete User: ' . $this->getError());
        }

        // HTTP 210 in GEMVC = deleted
        return Response::deleted($result, 1, 'User deleted successfully');
    }

    /**
     * Authenticate and issue JWT trio (access / refresh / login).
     * Role on the token (if set) is used later by requireAuth(['admin']).
     */
    public function loginByEmailPassword(string $email, string $password): JsonResponse
    {
        $user = $this->selectByEmail(strtolower(trim($email)));
        if ($user === null) {
            // Same message for missing user vs bad password (avoid email enumeration)
            return Response::unauthorized('Invalid email or password');
        }

        if (!CryptHelper::passwordVerify($password, $user->password)) {
            return Response::unauthorized('Invalid email or password');
        }

        $token = new JWTToken();
        if ($user->role !== null) {
            $token->role = $user->role;
        }

        $payload = new \stdClass();
        $payload->user = $user;
        $payload->access_token = $token->createAccessToken($user->id);
        $payload->refresh_token = $token->createRefreshToken($user->id);
        $payload->login_token = $token->createLoginToken($user->id);

        return Response::success($payload, 1, 'Login successful');
    }
}

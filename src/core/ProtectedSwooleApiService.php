<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;

/**
 * OpenSwoole API service base that requires authentication for every method.
 *
 * Prefer this over {@see SwooleApiService} for authenticated CRUD. Keep
 * {@see SwooleApiService} for public endpoints (login, register, health, docs).
 *
 *   class User extends ProtectedSwooleApiService {
 *       public function __construct(Request $request) {
 *           parent::__construct($request, ['admin']); // or null = any authenticated user
 *       }
 *   }
 *
 * Apache/Nginx: use {@see ProtectedApiService}.
 *
 * @throws AuthException from the constructor when auth fails (caught by SwooleBootstrap → 401/403)
 */
abstract class ProtectedSwooleApiService extends SwooleApiService
{
    /**
     * @param array<string>|null $roles null or [] = any authenticated user; otherwise one of these roles
     * @throws AuthException
     */
    public function __construct(Request $request, ?array $roles = null)
    {
        parent::__construct($request);
        $this->requireAuth($roles);
    }
}

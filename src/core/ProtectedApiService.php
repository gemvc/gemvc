<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;

/**
 * API service base that requires authentication for every method.
 *
 * Prefer this over {@see ApiService} for authenticated CRUD on **all** servers
 * (Apache, Nginx, OpenSwoole). Keep {@see ApiService} for public endpoints
 * (login, register, health, docs).
 *
 *   class User extends ProtectedApiService {
 *       public function __construct(Request $request) {
 *           parent::__construct($request, ['admin']); // or null = any authenticated user
 *       }
 *   }
 *
 * {@see ProtectedSwooleApiService} is a deprecated alias of this class.
 *
 * @throws AuthException from the constructor when auth fails (caught by Bootstrap / SwooleBootstrap → 401/403)
 */
abstract class ProtectedApiService extends ApiService
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

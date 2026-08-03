<?php

namespace Gemvc\Core;

/**
 * @deprecated Prefer {@see ProtectedApiService} on all servers (Apache, Nginx, OpenSwoole).
 * Thin alias kept for backward compatibility — same auth-in-constructor contract.
 *
 * @throws AuthException from the constructor when auth fails (caught by Bootstrap / SwooleBootstrap → 401/403)
 */
abstract class ProtectedSwooleApiService extends ProtectedApiService
{
}

# Unified `ApiService` (runtime) — plan of record

**Status:** Phase 0–3 **done** — shipped in **5.13.0**.  
**AI:** no further runtime-unification work unless a new plan is written.

## Goals (achieved)

- Developers write `class User extends ApiService {}` (and `ProtectedApiService`) for **all** servers.
- Backward compatibility: `SwooleApiService` / `ProtectedSwooleApiService` remain as thin deprecated subclasses.
- Never `die()` / `exit()` in shared API service code; OpenSwoole still returns responses from `SwooleBootstrap`.

## Non-goals (rejected)

- A class that **dynamically changes the parent** from `.env` (impossible / unsafe in PHP).
- Renaming public `ApiService` → `ApacheApiService` as the app-facing name.

## Facts today

| Path | Adapter | Bootstrap | Recommended API base |
|------|---------|-----------|----------------------|
| Apache | `StandardHttpRequest` | `Bootstrap` | `ApiService` / `ProtectedApiService` |
| Nginx | **same** `StandardHttpRequest` (PHP-FPM) | `Bootstrap` | same |
| OpenSwoole | `SwooleRequest` | `SwooleBootstrap` | **same** `ApiService` / `ProtectedApiService` |

Deprecated aliases (still work): `SwooleApiService` extends `ApiService`; `ProtectedSwooleApiService` extends `ProtectedApiService`.

There is **no** `NginxRequest` and none is needed.

Canonical validation (all servers):

```php
if (!$this->request->definePostSchema([...])) {
    return $this->request->returnResponse();
}
// or
$this->validateOrFail(['email' => 'email']);
$this->validateStringOrFail(['name' => '2|100']);
```

Shared helpers (all servers):

```php
$this->requireAuth(['admin']);
$this->requireRateLimit();
return $this->callController(new UserController($this->request))->create();
```

Legacy OpenSwoole return-style (only on deprecated `SwooleApiService`):

```php
if ($err = $this->safeValidatePosts([...])) {
    return $err;
}
```

`validatePosts()` on `SwooleApiService` now **throws** (inherited from `ApiService`). Migrate old return-style callers to `safeValidatePosts()` or `validateOrFail()` / `definePostSchema()`.

## Phases

### Phase 0 — Documentation (**done**)

### Phase 1 — Validation consistency (**done**)

`validateOrFail` / `validateStringOrFail`; SwooleBootstrap catches `ValidationException`.

### Phase 2 — Shared behavior (**done**)

`ApiServiceSharedTrait`: auth, rate limit, `callController`, magic controllers.

### Phase 3 — One public base (**done**)

1. `SwooleApiService extends ApiService` (deprecated thin subclass; keeps `safeValidatePosts` / `safeValidateStringPosts`).
2. `ProtectedSwooleApiService extends ProtectedApiService` (deprecated empty alias).
3. Docs/AI pack recommend `ApiService` / `ProtectedApiService` on all servers.
4. Tests updated for inheritance + throw-style `validatePosts` on Swoole path.

## Safety rules (still apply)

- Only Apache/Nginx bootstrap may terminate after sending output.
- OpenSwoole bootstrap always returns `JsonResponse` / `ResponseInterface`; convert exceptions there.
- Prefer `validateOrFail()` / `definePostSchema()` for new code; `safeValidate*` only for legacy return-style on `SwooleApiService`.

## Related

- [api.md](../guides/api.md) · [http-lifecycle.md](../guides/http-lifecycle.md) · [architecture.md](../guides/architecture.md) · [apm.md](../guides/apm.md)
- Trust/mesh is separate: [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md)
- OpenSwoole runtime behavior (isolation / pool / FAQ): [openswoole.md](../guides/openswoole.md)

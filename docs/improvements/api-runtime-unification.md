# Unified `ApiService` (runtime) — plan of record

**Status:** Phase 0 done · **Phase 1 done** (validation consistency) · Phase 2–3 planned.  
**AI:** implement Phase 2+ only when explicitly tasked. Do **not** merge inheritance until Phase 2 shared behavior is extracted.

## Goals

- Developers write `class User extends ApiService {}` (and `ProtectedApiService`) for **all** servers.
- Keep backward compatibility for apps that still extend `SwooleApiService` / `ProtectedSwooleApiService`.
- Never `die()` / `exit()` in shared API service code; OpenSwoole must always return a response from bootstrap.

## Non-goals (rejected)

- A class that **dynamically changes the parent** from `.env` (impossible / unsafe in PHP).
- Renaming public `ApiService` → `ApacheApiService` as the app-facing name.

## Facts today

| Path | Adapter | Bootstrap | API base |
|------|---------|-----------|----------|
| Apache | `ApacheRequest` | `Bootstrap` | `ApiService` / `ProtectedApiService` |
| Nginx | **same** `ApacheRequest` (PHP-FPM) | `Bootstrap` | same |
| OpenSwoole | `SwooleRequest` | `SwooleBootstrap` | `SwooleApiService` / `ProtectedSwooleApiService` |

There is **no** `NginxRequest` and none is needed.

Canonical validation (unchanged, works on both servers):

```php
if (!$this->request->definePostSchema([...])) {
    return $this->request->returnResponse();
}
```

Cross-runtime throw helpers (**Phase 1 — shipped**):

```php
$this->validateOrFail(['email' => 'email']);           // throws ValidationException
$this->validateStringOrFail(['name' => '2|100']);      // throws ValidationException
// Bootstrap + SwooleBootstrap catch → HTTP 400
```

Legacy helper mismatch (still present; do **not** unify parents until Phase 2+):

```php
// ApiService
protected function validatePosts(array $schema): void;           // throws ValidationException

// SwooleApiService
protected function validatePosts(array $schema): ?JsonResponse;  // returns 400 JsonResponse
```

## Phases

### Phase 0 — Documentation (**done**)

- State Nginx = shared `ApacheRequest` / PHP-FPM path; remove “NginxRequest coming soon”.
- Document `Table::noLimit()`, `Table::all()`, `Response::tooManyRequests()`.

### Phase 1 — Validation consistency (**done**)

1. Added `validateOrFail()` / `validateStringOrFail()` on `ApiService` and `SwooleApiService` (always throw `ValidationException`).
2. `SwooleBootstrap` catches `ValidationException` → `Response::badRequest(...)` (constructor + method).
3. Docs/AI pack prefer `definePostSchema` / `validateOrFail`; legacy `validatePosts` signatures left unchanged.
4. Kept `safeValidatePosts()` / `safeValidateStringPosts()` as return-style shims on Swoole.
5. Tests: `tests/Unit/Core/ValidateOrFailTest.php`.

### Phase 2 — Shared behavior

- Extract shared auth / rate limit / `callController` / APM wiring into a trait or internal abstract.
- Response **delivery** stays in Bootstrap / OpenSwooleServer (not in API classes).

### Phase 3 — One public base

- Make `ApiService` the unified developer-facing class.
- `SwooleApiService` / `ProtectedSwooleApiService` become thin deprecated subclasses or aliases.
- Only then treat `extends ApiService` as the single recommended path.

## Safety rules

- Only Apache/Nginx bootstrap may terminate after sending output.
- OpenSwoole bootstrap always returns `JsonResponse` / `ResponseInterface`; convert exceptions there.
- Do not change `validatePosts()` behavior silently — `validateOrFail()` is the shared throw API.

## Related

- [api.md](../guides/api.md) · [http-lifecycle.md](../guides/http-lifecycle.md) · [architecture.md](../guides/architecture.md)
- Phase 2 trust/mesh is separate: [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md)

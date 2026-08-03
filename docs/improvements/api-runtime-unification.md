# Unified `ApiService` (runtime) — plan of record

**Status:** Planned (docs-first; no inheritance merge yet).  
**AI:** implement only when explicitly tasked. Do **validation consistency before** `SwooleApiService extends ApiService`.

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

Canonical validation already works on both servers:

```php
if (!$this->request->definePostSchema([...])) {
    return $this->request->returnResponse();
}
```

Legacy helper mismatch (do **not** unify parents until reconciled):

```php
// ApiService
protected function validatePosts(array $schema): void;           // throws ValidationException

// SwooleApiService
protected function validatePosts(array $schema): ?JsonResponse;  // returns 400 JsonResponse
```

## Phases

### Phase 0 — Documentation (done / in progress)

- State Nginx = shared `ApacheRequest` / PHP-FPM path; remove “NginxRequest coming soon”.
- Document `Table::noLimit()`, `Table::all()`, `Response::tooManyRequests()`.

### Phase 1 — Validation consistency (keep both bases)

1. Add shared `validateOrFail(array $schema): void` that always throws `ValidationException`.
2. Catch `ValidationException` in `SwooleBootstrap` → `Response::badRequest(...)`.
3. Point templates + guides at `definePostSchema` / `validateOrFail`; leave existing `validatePosts` signatures unchanged for one cycle.
4. Optional: keep `safeValidatePosts()` as return-style shim for old Swoole call sites.

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
- Do not change `validatePosts()` behavior silently — introduce `validateOrFail()` first.

## Related

- [api.md](../guides/api.md) · [http-lifecycle.md](../guides/http-lifecycle.md) · [architecture.md](../guides/architecture.md)
- Phase 2 trust/mesh is separate: [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md)

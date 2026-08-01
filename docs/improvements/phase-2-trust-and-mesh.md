# Phase 2 — Trust and mesh

**Status:** Not implemented (plan only). Confirmed absent from `src/` and `vendor/gemvc` (no `requireInternalService`, `GEMVC_INTERNAL_SECRET`, `ServiceCall`, or registry).  
**Packages:** `gemvc/library` (API gate), then `gemvc/http-client` and/or thin library wrapper (caller DX).  
**Backlog origin:** [make-gemvc-better.md](../../make-gemvc-better.md) P0 #3, P1 #5.

**AI:** implement only when explicitly tasked with Phase 2. Do **2a before 2b**.

---

## Goals

### 2a — Family trust (security gate)

- Shared env secret across Aggregator + microservices + workers that call internal APIs.
- `ApiService` / `SwooleApiService::requireInternalService()` — same DX style as `requireAuth()` / `requireRateLimit()` (throw; Bootstrap catches).
- Separate from end-user JWT.

### 2b — Mesh DX (after 2a)

- Resolve sibling base URLs (static map first).
- `ServiceCall` (name TBD) built on **`gemvc/http-client`**, attaches internal trust headers automatically.
- Redis/etcd registry = later stretch, not required for v1.

---

## Current source gaps

| Capability | Today |
|------------|--------|
| `requireInternalService()` | Missing |
| `GEMVC_INTERNAL_SECRET` | Missing |
| Service discovery / `ServiceCall` | Missing — apps hardcode URLs + hand-roll http-client |
| End-user JWT | Exists (`auth` / `requireAuth`) — must stay separate |

---

## Phase 2a — Design

### Env

```env
# Identical value on every family member that sends OR receives internal calls
GEMVC_INTERNAL_SECRET=...long random...

# Optional identity for logs / future per-service HMAC
GEMVC_SERVICE_NAME=noam-auth

# Optional zero-downtime rotation
# GEMVC_INTERNAL_SECRET_PREVIOUS=...
```

### Gate API

```php
public function oauthLogin(): JsonResponse
{
    $this->requireInternalService(); // family only — not end-user JWT
    // …
}
```

Behavior:

- Verify inbound header (e.g. `X-Gemvc-Internal-Token` or `Authorization: Bearer` derived from family secret — **pick one scheme and document it**).
- Prefer HMAC of method+path+timestamp (or similar) over sending the raw secret on the wire if feasible in v1; minimum v1 may be a derived static token from the secret with constant-time compare.
- Failure → **401** or **403** (document; stay consistent with existing exception → Bootstrap patterns). Prefer a dedicated exception or reuse pattern parallel to `AuthException`.
- Missing secret in **production** → fail closed (deny / refuse to boot gate), not silent allow.
- Network: family APIs should stay off the public internet; the secret is **app-layer** trust, not a substitute for private networking / mTLS.

### Rotation sketch

- Accept `_CURRENT` and optional `_PREVIOUS` during rotate window.
- Document ops steps in this phase doc or security guide when shipping.

---

## Phase 2b — Design (depends on 2a)

### Static discovery (v1)

```env
GEMVC_INTERNAL_SECRET=...
GEMVC_SERVICE_NAME=aggregator
GEMVC_SERVICES_JSON={"auth":"http://noam-auth","billing":"http://billing"}
```

### Caller DX (conceptual)

```php
$result = ServiceCall::to('auth')
    ->post('/api/Auth/oauthLogin', $payload)
    ->withInternalTrust()   // required in prod for family routes
    ->withTimeout(2.0)
    ->run();
```

| Feature | Notes |
|---------|--------|
| Transport | Reuse `HttpClient` / async / Swoole client — no second HTTP stack |
| Trust | Always attach family credential when `withInternalTrust()`; fail if secret missing in prod |
| Discovery | `to('auth')` → `GEMVC_SERVICES_JSON` (Redis registry later) |
| Tracing | Propagate APM / correlation id when APM enabled |
| JWT | Do **not** forward end-user Bearer unless explicitly requested |

### Receiver

Same as 2a: `requireInternalService()` on protected methods.

### Stretch (not v1)

- Redis-backed registry + heartbeat TTL
- Per-service HMAC identity (compromised sibling cannot fully impersonate Aggregator)
- Derived keys for Redis/Kafka documented as patterns only

---

## Work packages

**2a**

1. Secret loading + constant-time verify helper (library or helper package — prefer library next to auth, or helper if pure crypto).
2. `requireInternalService()` on both API bases + Bootstrap/SwooleBootstrap catch if new exception type.
3. Unit tests (valid / invalid / missing secret).
4. Security + AI docs.

**2b**

1. Static service map parser.
2. `ServiceCall` facade over http-client with trust header injection.
3. Integration test: caller + stub receiver with secret.
4. Docs: ecosystem + http-client + security.

---

## Acceptance criteria

### 2a

- [ ] With correct family token, `requireInternalService()` allows the method.
- [ ] Without token / wrong token → denied (documented HTTP code).
- [ ] End-user JWT alone does **not** satisfy `requireInternalService()`.
- [ ] `requireAuth()` alone does **not** satisfy family gate (and vice versa unless app stacks both).
- [ ] PHPStan 9; dual API bases (Apache + Swoole) both expose the method.

### 2b

- [ ] `ServiceCall::to('auth')->…->withInternalTrust()->run()` hits configured base URL with trust header.
- [ ] Missing secret in prod fails loudly for trusted calls.
- [ ] No Redis required for v1.

---

## Out of scope (Phase 2)

- SQL views / `db:migrate --all` (Phase 1)
- Product IdP verification, end-user cookies, domain RBAC catalogs
- Replacing Kubernetes mTLS / service mesh products

---

## Suggested implementation order

1. **2a** secret + `requireInternalService` + tests + docs  
2. **2b** static map + `ServiceCall` + trust header  
3. Stretch: rotation polish, Redis registry, per-service HMAC

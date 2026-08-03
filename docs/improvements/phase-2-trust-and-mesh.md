# Phase 2 — Trust and mesh

**Status:** **2a implemented** (library). **2b** not started.  
**Packages:** `gemvc/library` (API gate + `InternalTrust`). **2b:** `gemvc/http-client` and/or thin library wrapper.  
**Backlog origin:** internal P0 (family trust / mesh DX). See [improvements README](README.md).

**AI:** implement **2b** only when explicitly tasked. Do **2a before 2b**.

---

## Goals

### 2a — Family trust (security gate) — **shipped design locked below**

- Shared env secret across Aggregator + microservices + workers that call internal APIs.
- `ApiService::requireInternalService()` — same DX as `requireAuth()` / `requireRateLimit()` (throw; Bootstrap catches).
- Separate from end-user JWT.

### 2b — Mesh DX (after 2a)

- Resolve sibling base URLs (static map first).
- `ServiceCall` (name TBD) built on **`gemvc/http-client`**, attaches internal trust headers automatically.
- Redis/etcd registry = later stretch, not required for v1.

---

## Phase 2a — Locked design (v1)

### Env

```env
# Identical value on every family member that sends OR receives internal calls
GEMVC_INTERNAL_SECRET=...long random...

# Optional identity for logs / future per-service HMAC
GEMVC_SERVICE_NAME=noam-auth

# Optional zero-downtime rotation (accepted during rotate window)
# GEMVC_INTERNAL_SECRET_PREVIOUS=...

# Optional skew window seconds (default 60)
# GEMVC_INTERNAL_TRUST_SKEW_SECONDS=60
```

### Scheme: HMAC-SHA256 + timestamp (not a static token on the wire)

| Item | Value |
|------|--------|
| Timestamp header | `X-Gemvc-Internal-Timestamp` (Unix seconds) |
| Signature header | `X-Gemvc-Internal-Signature` (hex HMAC-SHA256) |
| Canonical string | `{METHOD}\n{path}\n{timestamp}\n{body_hash}` |
| `METHOD` | Uppercase HTTP method (`POST`) |
| `path` | URL **path only** (no query string) |
| `body_hash` | Hex SHA-256 of **raw** request body (`""` → hash of empty string) |
| HMAC key | `GEMVC_INTERNAL_SECRET` (try `_PREVIOUS` if current fails) |
| Skew | Default **60s**; reject outside window |
| Compare | `hash_equals()` only |

```php
public function oauthLogin(): JsonResponse
{
    $this->requireInternalService(); // family only — not end-user JWT
    // …
}
```

### Failure codes

| Situation | HTTP | Machine code in `service_message` |
|-----------|------|-------------------------------------|
| Missing/empty secret when gate is used | **500** | `ERR_INTERNAL_TRUST_MISCONFIGURED` |
| Bad / missing / expired signature or timestamp | **401** | `ERR_INTERNAL_TRUST_FAILED` |

Exception: `InternalServiceException` (not `AuthException`). Caught by `Bootstrap`, `SwooleBootstrap`, `FrankenPhpBootstrap`.

### Orthogonal identities

- `requireInternalService()` = **machine** (HMAC).  
- `requireAuth()` = **end-user JWT**.  
- Never treat user Bearer as family trust.  
- Correlation / APM IDs are not auth (2b may propagate them).

### Threat-model honesty

HMAC stops raw secret on the wire, trivial static-token replay, and external callers without the secret. It does **not** stop a compromised sibling that already holds `GEMVC_INTERNAL_SECRET` (including SSRF inside that sibling). Private networking / mTLS remain complementary. Replay inside the skew window without a nonce store is accepted for v1.

### Caller helper (library; 2b will wrap this)

```php
$ts = time();
$sig = InternalTrust::sign('POST', '/api/Auth/oauthLogin', $ts, $rawBody, $secret);
// headers: X-Gemvc-Internal-Timestamp, X-Gemvc-Internal-Signature
```

---

## Phase 2b — Design (depends on 2a; not implemented)

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
    ->withInternalTrust()
    ->withTimeout(2.0)
    ->run();
```

| Feature | Notes |
|---------|--------|
| Transport | Reuse `HttpClient` / async / Swoole client — no second HTTP stack |
| Trust | Always attach HMAC headers when `withInternalTrust()` |
| Discovery | `to('auth')` → `GEMVC_SERVICES_JSON` |
| Tracing | Propagate APM / correlation id when APM enabled |
| JWT | Do **not** forward end-user Bearer unless explicitly requested |

### Stretch (not v1)

- Redis-backed registry + heartbeat TTL
- Per-service HMAC identity
- Nonce store for replay inside skew window

---

## Work packages

**2a** (done)

1. ~~Secret loading + HMAC + constant-time verify (`InternalTrust`)~~
2. ~~`requireInternalService()` + `InternalServiceException` + Bootstrap catches~~
3. ~~Unit tests~~
4. ~~Security + API + AI docs~~

**2b**

1. Static service map parser.
2. `ServiceCall` facade over http-client with trust header injection.
3. Integration test: caller + stub receiver with secret.
4. Docs: ecosystem + http-client + security.

---

## Acceptance criteria

### 2a

- [x] With correct HMAC + fresh timestamp, `requireInternalService()` allows the method.
- [x] Without / wrong / expired signature → **401** `ERR_INTERNAL_TRUST_FAILED`.
- [x] Missing secret when gate used → **500** `ERR_INTERNAL_TRUST_MISCONFIGURED` (fail-closed).
- [x] End-user JWT alone does **not** satisfy `requireInternalService()`.
- [x] PHPStan 9; all API bases via `ApiServiceSharedTrait`.

### 2b

- [ ] `ServiceCall::to('auth')->…->withInternalTrust()->run()` hits configured base URL with trust headers.
- [ ] Missing secret in prod fails loudly for trusted calls.
- [ ] No Redis required for v1.

---

## Out of scope (Phase 2)

- SQL views / `db:migrate --all`
- Product IdP verification, end-user cookies, domain RBAC catalogs
- Replacing Kubernetes mTLS / service mesh products
- gRPC

---

## Suggested implementation order

1. **2a** — done (HMAC + `requireInternalService` + tests + docs)
2. **2b** static map + `ServiceCall` + trust headers
3. Stretch: nonce store, Redis registry, per-service HMAC

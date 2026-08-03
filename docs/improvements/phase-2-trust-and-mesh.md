# Phase 2 — Trust and mesh

**Status:** **2a + 2b implemented** (library). Stretch (Redis registry, etc.) not started.  
**Packages:** `gemvc/library` only (`InternalTrust`, `ServiceMap`, `ServiceCall`).  
**Shipped:** 2a in **5.15.0**; 2b in **5.16.0**.

---

## Goals

### 2a — Family trust (security gate) — **shipped design locked below**

- Shared env secret across Aggregator + microservices + workers that call internal APIs.
- `ApiService::requireInternalService()` — same DX as `requireAuth()` / `requireRateLimit()` (throw; Bootstrap catches).
- Separate from end-user JWT.

### 2b — Mesh DX (after 2a) — **design locked; not implemented**

- Resolve sibling base URLs (`GEMVC_SERVICES_JSON` static map).
- `ServiceCall` facade over existing **`ApiCall` / `AsyncApiCall`** (not a new HTTP stack); injects `InternalTrust` headers.
- Default transport = sync `ApiCall`; explicit `->sync()` / `->async()` / `->fireAndForget()`.
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

## Phase 2b — Locked design (depends on 2a; **shipped in 5.16.0**)

**Package:** `gemvc/library` facade `ServiceCall` (name locked) that **delegates** to existing outbound facades — **no second HTTP stack**.

| Existing facade | Role |
|-----------------|------|
| `Gemvc\Http\ApiCall` | Sync outbound (wraps `HttpClient` / Swoole client via `WebserverDetector`) |
| `Gemvc\Http\AsyncApiCall` | Concurrent batches / fire-and-forget |

There is **no** class named `SyncApiCall`; sync = `ApiCall`.

### Static discovery (v1)

```env
GEMVC_INTERNAL_SECRET=...
GEMVC_SERVICE_NAME=aggregator
GEMVC_SERVICES_JSON={"auth":"http://noam-auth","billing":"http://billing"}
```

Missing name in map → fail loudly (exception / clear error). No Redis for v1.

### Caller DX

```php
// Default transport: runtime-safe (see table below)
$result = ServiceCall::to('auth')
    ->post('/api/Auth/oauthLogin', $payload)
    ->withInternalTrust()   // required for family routes in prod; injects InternalTrust::callerHeaders
    ->withTimeout(2.0)
    ->run();

// Explicit transport
ServiceCall::to('auth')->sync()->post(...)->withInternalTrust()->run();
ServiceCall::to('auth')->async()->post(...)->withInternalTrust()->run();
ServiceCall::to('auth')->async()->post(...)->withInternalTrust()->fireAndForget();
```

### Transport selection (locked)

| Mode | Behavior |
|------|----------|
| **Default** (developer says nothing) | Use **`ApiCall`** — sync wait-for-response. On OpenSwoole, `ApiCall` already picks the Swoole-aware client via detector. |
| `->sync()` | Force **`ApiCall`** |
| `->async()` | Force **`AsyncApiCall`** (batches / non-default semantics) |
| `->fireAndForget()` | Only valid on async path; non-blocking telemetry-style |

**Automatic ≠ always async.** Silent async on FPM surprises developers (response timing, error handling). Default is sync; async is opt-in.

### Feature rules

| Feature | Notes |
|---------|--------|
| Transport | **Only** via `ApiCall` / `AsyncApiCall` → `gemvc/http-client` (**library only** — do not change `http-client` for 2b) |
| Trust | **Opt-in** `withInternalTrust()`; explicit bypass `withoutInternalTrust()`. In **`APP_ENV=production`**, calling a mapped sibling **requires** one of the two (omit → throw). Non-prod: omit allowed (no HMAC). |
| Discovery | `to('auth')` → `GEMVC_SERVICES_JSON` |
| Body | Encode payload to JSON **exactly once**; store raw string; pass same bytes to `InternalTrust::sign` / `callerHeaders` and to transport (`postRaw` / async raw). Never re-encode after signing. |
| Tracing | Propagate APM / correlation id when APM enabled (stretch-ok if thin in v1) |
| JWT | Do **not** forward end-user Bearer unless explicitly requested (e.g. `->withUserToken(...)`) |
| Package | **`gemvc/library` only** |

### Assessor lock (approved)

1. Default-sync + explicit-async — **yes**
2. Trust opt-in + prod require with/without — **yes**
3. Single-encode pipeline — **yes**
4. Name `ServiceCall` — **yes**
5. Live in `gemvc/library` only — **yes**

### Stretch (not 2b v1)

- Redis-backed registry + heartbeat TTL
- Per-service HMAC identity
- Nonce store for replay inside skew window
- Default-async heuristics beyond explicit `->async()`

---

## Work packages

**2a** (done — shipped **5.15.0**)

1. ~~Secret loading + HMAC + constant-time verify (`InternalTrust`)~~
2. ~~`requireInternalService()` + `InternalServiceException` + Bootstrap catches~~
3. ~~Unit tests~~
4. ~~Security + API + AI docs + HttpClient caller example~~

**2b** (done — shipped **5.16.0**)

1. ~~Parse/validate `GEMVC_SERVICES_JSON`.~~
2. ~~`ServiceCall` facade → `ApiCall` / `AsyncApiCall` + `withInternalTrust()` / `withoutInternalTrust()` / `sync()` / `async()`.~~
3. ~~Unit tests (map resolve, trust headers, sync default, prod trust policy).~~
4. ~~Docs: http-client, security, api, AI pack, RELEASE_NOTES.~~

---

## Acceptance criteria

### 2a

- [x] With correct HMAC + fresh timestamp, `requireInternalService()` allows the method.
- [x] Without / wrong / expired signature → **401** `ERR_INTERNAL_TRUST_FAILED`.
- [x] Missing secret when gate used → **500** `ERR_INTERNAL_TRUST_MISCONFIGURED` (fail-closed).
- [x] End-user JWT alone does **not** satisfy `requireInternalService()`.
- [x] PHPStan 9; all API bases via `ApiServiceSharedTrait`.

### 2b

- [x] `ServiceCall::to('auth')->post(...)->withInternalTrust()->run()` builds mapped URL + valid HMAC headers.
- [x] Default path uses **`ApiCall`** (sync); `->async()` uses **`AsyncApiCall`**.
- [x] Missing secret when `withInternalTrust()` → fail loudly (`InternalTrust`).
- [x] Unknown service name in map → fail loudly.
- [x] Production: omit trust mode → throw unless `withInternalTrust()` or `withoutInternalTrust()`.
- [x] Single-encode: signed body bytes === wire body bytes.
- [x] No Redis required for v1.
- [x] PHPStan 9; no duplicate HTTP client stack; no `http-client` package changes.

---

## Out of scope (Phase 2)

- SQL views / `db:migrate --all`
- Product IdP verification, end-user cookies, domain RBAC catalogs
- Replacing Kubernetes mTLS / service mesh products
- gRPC

---

## Suggested implementation order

1. **2a** — done (HMAC + `requireInternalService` + tests + docs) — **5.15.0**
2. **2b** — static map + `ServiceCall` on `ApiCall`/`AsyncApiCall` + trust — **5.16.0**
3. Stretch: nonce store, Redis registry, per-service HMAC

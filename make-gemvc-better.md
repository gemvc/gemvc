# Make GEMVC better

Feedback from building **noam-auth** on **GEMVC 5.10** (`gemvc/library` + `gemvc/helper`).  
These are framework improvements — not app bugs. Use as an upstream backlog.

Source context: `docs/project.md` §9, Auth/RBAC stacks, `UserAccess` view, helpers in `app/helper/`.

---

## Priority legend

| Priority | Meaning |
| :--- | :--- |
| **P0** | High friction / security footguns we worked around |
| **P1** | Strong DX or security floor improvements |
| **P2** | Nice to have |

---

## P0 — High impact

### 1. First-class SQL views

**Problem:** GEMVC has no view generator or migrate path. Apps invent PDO helpers (`UserAccessViewHelper`) and must remember **never** to `db:migrate` a view Table (migrate would CREATE a physical table).

**Proposal:**
- `defineView(): string` (SQL) or `Schema::view(...)` on a Table class
- `db:migrate` runs `CREATE OR REPLACE VIEW` for view Tables
- Framework `readOnly` flag (or trait) that rejects `insertSingleQuery` / `updateSingleQuery` / `deleteByIdQuery` at Table level

**Why:** JOIN-heavy read paths (JWT access maps, reporting) are normal; reinventing views is wasteful and risky.

---

### 2. Runtime PK from `Schema::primary`

**Problem:** `Schema::primary('role_id')` is migrate DDL only. Runtime `selectById()` detects PK via:
1. property named `id`, else  
2. `$this->setPrimaryKey(...)` after `parent::__construct()`, else  
3. default column `id`

Easy to miss for views / alternate keys (`role_id`).

**Proposal:** Apply `defineSchema()` primary (or call `setPrimaryKey`) during Table construction so Schema and runtime stay aligned.

---

### 3. Internal family trust + service-to-service auth

**Problem:** Endpoints like `Auth/oauthLogin` are intentionally “Public*” (BFF must verify IdP first), but the framework has no first-class way to mark a caller as an **internal family member** (Aggregator, sibling microservice, worker). Anything that can reach the URL can mint sessions if claims are forged. The same gap appears for Redis, Kafka, queues, and other infra that should share one trust domain.

**Vision:** One **internally trusted family secret** (long random string). Ops **defines** it once (often in Aggregator / platform secrets), then **distributes the same value** to every family member. Sharing the secret = allowed to call; public internet does not have it.

> **Not Aggregator-only.** If only the Aggregator holds the secret, microservices cannot verify internal calls. Every participant that **sends or receives** family traffic must have `GEMVC_INTERNAL_SECRET` in its env (injected by compose / K8s secrets).

```
                    ┌─────────────────────────────────────┐
                    │  Family secret (ops source of truth)│
                    │  same value copied to all members   │
                    └─────────────────────────────────────┘
           ┌────────────┬────────────┬────────────┬─────────┐
           ▼            ▼            ▼            ▼         ▼
      Aggregator    noam-auth    other MS    Redis*    Kafka* / workers
                         (* optional: same trust domain via derived keys)
```

| Who | Holds secret? | Role |
| :--- | :--- | :--- |
| Aggregator / platform | Yes | Often **defines** / rotates; always a caller |
| Each microservice | Yes (same value) | **Verifies** inbound family calls; may call siblings |
| Workers / cron | Yes, if they call internal APIs | Caller |
| Redis / Kafka | Optional derived keys | Same trust domain — not forced into PHP registry |

**Proposal (auth gate):**
- Env: `GEMVC_INTERNAL_SECRET` — long random string, **identical** across the family
- Optional: `GEMVC_SERVICE_NAME` / `GEMVC_SERVICE_ID` per process (who is calling — for logs / later per-service HMAC)
- `ApiService::requireInternalService()` — verifies internal header (e.g. `X-Gemvc-Internal-Token` HMAC or Bearer derived from the family secret)
- Separate from end-user JWT `auth(['admin'])`
- Rotation: dual secrets (`_CURRENT` / `_PREVIOUS`) for zero-downtime rotate
- Network: keep family APIs off the public internet; the secret is app-layer trust, not a substitute for private networking

**Why one shared family secret (v1):** Ops simplicity — one value, many services; no per-pair credentials.  
**Later upgrade:** keep the family root, add **per-service identity** (HMAC includes service name, or mTLS) so a compromised sibling cannot perfectly impersonate Aggregator.

---

### 4. Rate limit across aliases

**Problem:** `Auth/register` is rate-limited; `User/create` is the same write path and is not — easy bypass.

**Proposal:**
- Shared rate-limit policy helper, or  
- Document / support tagging multiple methods under one limiter key, or  
- Framework note: rate-limit every public alias of the same mutation

---

## P1 — Strong improvements

### 5. Service registry + `serviceCall` (internal mesh DX)

**Problem:** Even with a shared family secret, services still hardcode sibling URLs, hand-roll `http-client` calls, and forget to attach internal auth. There is no standard way for microservices to **discover** each other or call with the right trust headers.

**Vision:** A lightweight **service registry** (config and/or Redis-backed) plus a first-class **`serviceCall`** client so Aggregator ↔ microservices talk securely and efficiently as one family.

#### Service registry

| Concern | Proposal |
| :--- | :--- |
| Register | On boot: service name, base URL, health path, optional version / tags |
| Discover | Resolve `auth`, `billing`, `notify`, … → base URL (static `.env` map first; Redis/etcd later) |
| Heartbeat | Optional TTL lease so dead instances drop out |
| Config source of truth | Aggregator (or platform compose) publishes the family map; services read-only |

Example env / config shape:

```env
# Same secret on Aggregator AND every microservice (required for verify)
GEMVC_INTERNAL_SECRET=...long random...
GEMVC_SERVICE_NAME=noam-auth
GEMVC_REGISTRY=redis://redis:6379/0
# or static discovery first:
GEMVC_SERVICES_JSON={"auth":"http://noam-auth","billing":"http://billing"}
```

#### `serviceCall` (DX)

Built on `gemvc/http-client`, not a second HTTP stack:

```php
// Conceptual API
$result = ServiceCall::to('auth')
    ->post('/api/Auth/oauthLogin', $payload)
    ->withInternalTrust()   // attaches family secret / derived token
    ->withTimeout(2.0)
    ->run();
```

| Feature | Notes |
| :--- | :--- |
| Auto trust header | Always send internal family credential; fail if secret missing in prod |
| Discovery | `to('auth')` resolves via registry / static map |
| Timeouts / retries | Sensible defaults for mesh calls (short, fail fast) |
| Tracing | Propagate APM / correlation id across service hops |
| Sync + async | Match existing http-client capabilities |
| No user JWT confusion | Internal call ≠ end-user Bearer unless explicitly forwarded |

#### Receiver side

```php
public function oauthLogin(): JsonResponse {
    $this->requireInternalService(); // family secret — any trusted family caller
    // … business logic
}
```

#### Scope vs mTLS / mesh products

This is **GEMVC-native DX** for PHP microservices + Aggregator (secret family + registry + `serviceCall`). It does not replace Kubernetes service mesh / mTLS for high-assurance environments — it should compose with them (secret still useful for app-layer authorization: “this caller is family”).

**Infra note:** Same family secret (or derived keys) can bootstrap Redis/`AUTH`, Kafka SASL, etc., so queue workers and caches join the same trust domain without a second secret sprawl. Document patterns; don’t force Redis/Kafka into the PHP registry.

---

### 6. Promote crypto + TOTP into `gemvc/helper`

App copies today:
- `app/helper/SecretCrypto.php` — AES-256-CBC encrypt/decrypt at rest
- `app/helper/TotpHelper.php` — RFC 6238 TOTP + `otpauth://` URI

These are generic security primitives (same class as `CryptHelper` for passwords). Every auth/BFF service will reinvent them.

**Proposed core APIs:**

#### Encrypt / decrypt (extend `CryptHelper` or add `SecretHelper`)
- Explicit key argument or env name — **no** hardcoded insecure default
- Fail hard if key missing outside `APP_ENV=dev`
- Prefer **AES-256-GCM** (auth tag) over CBC
- Optional key-rotation id prefix in ciphertext

#### `TotpHelper` (new in `gemvc/helper`)
- `generateSecret()`, `verify()`, `provisioningUri()`
- Configurable digits / period / algorithm
- Issuer and account as parameters (no product-specific default string in core)

**Keep app-specific:** env names (`TWO_FACTOR_ENCRYPTION_KEY`), when 2FA is mandatory, hard-gate policy, wiring into Models.

---

### 7. `renewToken` claim refresh hook

**Problem:** JWT renew keeps existing `role` / `payload.access`. RBAC changes stay stale until full re-login.

**Proposal:** Optional callback / interface on renew to rebuild claims (or document “short access TTL + re-login on role change”).

---

### 8. Password policy schema type

**Problem:** `'password' => 'string'` accepts anything.

**Proposal:** Schema type like `'password' => 'password:min=12'` (length, classes, breach check optional later).

---

### 9. Ordered / bulk migrate

**Problem:** `db:migrate TableClassName` is one class at a time. FK order is manual (`users` → `projects` → `role_types` → `roles` → …).

**Proposal:** `db:migrate --all` with FK-safe ordering derived from `Schema::foreignKey()` definitions.

---

### 10. Install / first-admin bootstrap

**Problem:** Chicken-egg — `seedCatalogs` and internal user create need an `admin` JWT; first admin is often model/CLI-only.

**Proposal:** Official install path (CLI or guarded one-shot HTTP) to create first admin + optional catalog seed.

---

## P2 — Nice to have

### 11. Dist docs clarity for AI

Composer dist often omits `docs/ai/`. `.cursorrules` already says to use README + `src/` — make that the single advertised agent path everywhere.

### 12. Conditional public / admin field gate

Helper for “public except when field X requires admin” (e.g. `is_internal=1`) without duplicating the check on every method.

### 13. Fail-closed rate limit in prod

Today APCu missing → fail-open (allow). Add `RATE_LIMIT_FAIL_CLOSED=1` for production.

### 14. Explicit column lists by default

Encourage / default `createList` to require allowlists so `protected` secrets never leak (docs already push this; make it harder to forget).

---

## Suggested upstream order

1. **SQL views + PK from Schema** — most framework workarounds in noam-auth  
2. **Internal family secret + `requireInternalService`** — close Aggregator → MS trust boundary  
3. **Service registry + `serviceCall`** — discover + call siblings securely/efficiently  
4. **`SecretHelper` / `TotpHelper` in `gemvc/helper`** — raise security floor for all apps  
5. **Rate-limit alias guidance** — close public bypasses  
6. **Bulk migrate + first-admin install** — ops DX  
7. Password policy, renew hook, fail-closed RL  

---

## Out of scope (app / product domain, not GEMVC core)

- Real Google/Microsoft IdP verification (Aggregator product logic)
- End-user Redis session cookies / HttpOnly cookie policy (Aggregator product)
- Domain rules: realm `is_internal`, 2FA hard gate, RBAC catalogs  

*(Internal family secret + registry **are** in scope for GEMVC — they are platform plumbing, not Noam domain.)*

---

## References in this repo

| Item | Where |
| :--- | :--- |
| View workaround | `app/helper/UserAccessViewHelper.php`, `app/table/UserAccessTable.php` |
| TOTP / encrypt-at-rest | `app/helper/TotpHelper.php`, `app/helper/SecretCrypto.php` |
| Rate limit usage | `app/api/Auth.php` |
| Trust boundary / risks (`oauthLogin`) | `docs/project.md` §9 |
| Cursor GEMVC notes | `.cursor/rules/gemvc-*.mdc`, `.cursorrules` |

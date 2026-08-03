# OpenSwoole runtime behavior (GEMVC)

**Audience:** production evaluators, architects, and developers running GEMVC under OpenSwoole / Swoole workers.  
**Purpose:** answer recurring questions about request isolation, memory, pooling, and app constraints — once, in one place.

**Related:** [http-lifecycle.md](http-lifecycle.md) · [architecture.md](architecture.md) · [database.md](database.md) · [ecosystem.md](ecosystem.md) · [apm.md](apm.md) · [api.md](api.md) · [frankenphp.md](frankenphp.md)

**Framework version:** 5.13+ (unified `ApiService` / `ProtectedApiService` on all servers).

---

## Reading map

| Question | Jump to |
|----------|---------|
| How is each HTTP request isolated? | [Request isolation model](#request-isolation-model) |
| Do you use `Coroutine::getContext()`? | [Not coroutine context](#not-coroutine-context) |
| What happens after the response? | [Lifecycle after response](#lifecycle-after-response) |
| DB / Redis pools and stale sockets | [Connection pooling](#connection-pooling) |
| What must app authors never do? | [Developer rules](#developer-rules-mandatory) |
| Apache vs OpenSwoole differences | [Runtime differences](#runtime-differences-apachenginx-vs-openswoole) |
| FAQ for common critiques | [FAQ](#faq-common-production-critiques) |

---

## Short answer

Under OpenSwoole, GEMVC keeps the **worker process alive** and isolates work by building a **new request object graph on every HTTP hit**. Auth, body, JWT, and APM live on that request — not on worker globals. Responses use `showSwoole()` (**never** `die()` / `exit()`). Database access on OpenSwoole uses a real pool via `gemvc/connection-openswoole` (Hyperf). Infrastructure singletons hold connections/config only.

GEMVC does **not** use `Swoole\Coroutine::getContext()` as an application request-context API. If your code stores user/token/`Request` in `static` properties or custom singletons, **you** can still leak state across requests on the same worker.

---

## Request isolation model

### Per HTTP request (new every time)

```
OpenSwoole HTTP request
  → SecurityManager path check (Swoole-only early deny)
  → new SwooleRequest($swooleRequest)   → new Request()
  → new SwooleBootstrap($request)       → APM root on $request->apm
  → new App\Api\{Service}($request)
  → Controller / Model / Table as invoked (typically new instances)
  → JsonResponse|HtmlResponse::showSwoole($response)
  → $request->apm->flush() (when APM enabled)
  → per-request objects become unreachable → GC
```

| Object | Per request? | Notes |
|--------|--------------|--------|
| `SwooleRequest` + `Request` | **Yes** | Constructor resets auth/token/body fields |
| `SwooleBootstrap` | **Yes** | Routes and invokes the API method |
| `App\Api\*` service | **Yes** | `new $service($this->request)` |
| Controller (via `callController` / `new`) | **Yes** when used | Receives that request |
| APM provider | **Yes** | `ApmFactory::create($request)` → `$request->apm` |
| JWT after `auth()` | On **that** `$request->token` | Not a process-global “current user” |

### Worker-level (survive across requests — by design)

| Object | Holds request user/token? |
|--------|---------------------------|
| `OpenSwooleServer`, config, hot-reload | No |
| `DatabaseManagerFactory` / OpenSwoole connection manager + pool | No — pool / connections |
| `RedisManager` | No — Redis client / config |
| `RateLimiter` backends (APCu / Redis) | Counters keyed by bucket; reads IP/token from the **passed** `Request` |
| `SecurityManager` | Path rules, not session identity |

**Isolation guarantee (framework):** request-scoped identity and payload live on the per-request `Request` graph.  
**Not guaranteed:** application `static` / custom singletons / unbounded in-process caches that hold request data.

### Not coroutine context

GEMVC does **not** implement request isolation via `Swoole\Coroutine::getContext()`.

That pattern is valid for some Swoole apps; GEMVC’s product model is:

1. Adapter at the edge (`SwooleRequest`)
2. Unified `Gemvc\Http\Request` per hit
3. Same `app/` layers as Apache/Nginx
4. Return a response from bootstrap — never kill the worker

`SWOOLE_ENABLE_COROUTINE` may default **on** for the server; that does not mean the framework stores your user in coroutine context. Prefer keeping state on `$this->request` / locals.

---

## Lifecycle after response

1. **Write response** — `showSwoole()` sets status/headers/body on the OpenSwoole response object. No `die()`.
2. **APM flush** — if APM is enabled, `OpenSwooleServer` calls `$request->apm->flush()` after send. Relying on PHP `register_shutdown_function` alone is **not** treated as request-scoped under persistent workers.
3. **GC** — request-local objects drop out of scope.
4. **Worker recycle** — `SWOOLE_MAX_REQUEST` (default often `5000`) recycles the worker after N requests. That bounds long-lived growth; it is a **safety net**, not the isolation mechanism.

There is **no** framework hook that walks and clears all application statics after each response.

---

## Connection pooling

### Database (OpenSwoole)

- `DatabaseManagerFactory` detects OpenSwoole and uses **`gemvc/connection-openswoole`** (Hyperf-based pool).
- Table / `UniversalQueryExecuter` **get** a connection for work and **release** it afterward (including error paths).
- Pool env (see Swoole `example.env` / connection package README): `MIN_DB_CONNECTION_POOL`, `MAX_DB_CONNECTION_POOL`, `DB_CONNECTION_MAX_AGE` (idle), optional `DB_HEARTBEAT`.

Apache/Nginx/CLI use **`gemvc/connection-pdo`** (not the same pooling model). Same Table API in `app/`.

### Redis

- `RedisManager` is a **worker singleton** (optional persistent connect via env).
- Used for infrastructure (e.g. rate-limit Redis driver), not as a request-context bag.

### Stale / broken connections (honest scope)

- Idle eviction / pool health is largely handled inside the OpenSwoole connection package (Hyperf pool).
- GEMVC does **not** claim a custom “ping before every query” or full MySQL `2006` / “gone away” discard policy in the library core beyond get/release (+ error logging).
- Production: monitor pool stats, set sensible idle/`max_request`, DB/proxy timeouts; prefer short queries.

### Blocking I/O note

Queries still run through **PDO** on the Table path. With coroutines enabled, a long blocking query occupies the worker for that time. Keep transactions on one Table instance (`beginTransaction` / `forUpdate` / `commit`) as documented in [model.md](model.md).

---

## Developer rules (mandatory)

### Do

- Extend **`ApiService`** / **`ProtectedApiService`** (all servers, including OpenSwoole). Deprecated: `SwooleApiService` / `ProtectedSwooleApiService`.
- Keep auth, body, and identity on **`$this->request`** (or method locals).
- Prefer **`callController(...)`** and **`createModel(...)`** so Request + APM propagate.
- Return `JsonResponse` from API/Controller; let `SwooleBootstrap` / `OpenSwooleServer` deliver it.
- Let Table/query helpers **release** connections; do not hold raw PDO across requests.
- Configure URL sections: OpenSwoole has **no** automatic `/api` hop — `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (see [architecture.md](architecture.md#url-to-code-mapping)).

### Don’t

- **`die()` / `exit()` / `die` after `JsonResponse::show()`** on the OpenSwoole request path (kills or breaks the worker model). Use `showSwoole` via the server — app code should just **return** the response.
- Store `Request`, JWT, user id, or tenant id in **`static`** properties, custom singletons, or worker globals.
- Cache Controller / Model / Table instances as process-wide singletons “for performance.”
- Assume APCu rate limits are cluster-wide (they are **per worker**). Use Redis / `both` for multi-node quotas.
- Invent PSR-7/15 middleware stacks or `Coroutine::getContext()` as required GEMVC APIs — not part of the product model.

---

## Runtime differences (Apache/Nginx vs OpenSwoole)

| Concern | Apache / Nginx (PHP-FPM) | OpenSwoole |
|---------|--------------------------|------------|
| Process model | One request ≈ one process (or FPM worker reset semantics) | Persistent worker, many requests |
| Entry | `startup/apache` or `startup/nginx` → `Bootstrap` | `startup/swoole` → `OpenSwooleServer` → `SwooleBootstrap` |
| Adapter | `StandardHttpRequest` (shared) | `SwooleRequest` |
| API base | `ApiService` / `ProtectedApiService` | **Same** (5.13+) |
| Response | `JsonResponse::show()` then terminate | `showSwoole()` then continue worker |
| Early path deny | `.htaccess` / server config | `SecurityManager` in server |
| DB package | `connection-pdo` | `connection-openswoole` (pool) |
| Uploads | Field name `file`; limited MIME sanitize | Normalized + name/MIME sanitize |
| URL `/api` hop | Yes (convention) | **No** automatic hop — configure sections |
| APM end | Bootstrap path + flush semantics | Explicit flush after `showSwoole` |

Same `app/` for all servers including FrankenPHP classic/worker — see [frankenphp.md](frankenphp.md).

---

## FAQ (common production critiques)

### “Singletons will leak user A’s data into user B’s request.”

Framework singletons are for **infrastructure**. Request identity is on the **new** `Request` each hit. Leakage happens if **your** code puts request data on statics — that is an app bug, not missing `getContext()`.

### “You need `Swoole\Coroutine::getContext()` for isolation.”

Not in GEMVC’s design. Isolation = new request graph + return response + GC. Coroutine context is optional for advanced app patterns; it is not the framework contract.

### “There is no connection pool under Swoole.”

Incorrect for the supported OpenSwoole path: **`gemvc/connection-openswoole`** provides Hyperf pooling selected by `DatabaseManagerFactory`. See [ecosystem.md](ecosystem.md) and [database.md](database.md).

### “How do you clean up after each response?”

`showSwoole` → optional APM `flush` → drop request graph → GC. Plus optional worker `max_request` recycle. No global static scrubber.

### “PDO will block the event loop.”

Heavy queries can block the worker for their duration. Mitigate with query design, pool sizing, and `max_request`. This is an operational characteristic of PDO-on-Swoole, not “GEMVC has no pool.”

### “Why both `ApiService` and `SwooleApiService`?”

Historically dual bases; **5.13** unifies on `ApiService` / `ProtectedApiService`. `Swoole*` names remain **deprecated** thin subclasses for BC. See [api.md](api.md).

### “Nginx needs NginxRequest.”

No. Nginx PHP-FPM uses **`StandardHttpRequest`** + `Bootstrap` (same as Apache). Only OpenSwoole uses `SwooleRequest`.

---

## Source map (for auditors)

| Piece | Location |
|-------|----------|
| Per-request handler | `src/core/OpenSwooleServer.php` |
| Bootstrap (return, no die) | `src/core/SwooleBootstrap.php` |
| Adapter | `src/http/SwooleRequest.php` |
| Unified request | `src/http/Request.php` |
| Response (Swoole) | `src/http/JsonResponse.php` → `showSwoole()` |
| DB manager selection | `src/database/DatabaseManagerFactory.php` |
| OpenSwoole pool package | `vendor/gemvc/connection-openswoole/` |
| Redis singleton | `src/core/RedisManager.php` |
| Example env | `src/startup/swoole/example.env` |

---

## Checklist for production evaluation

1. App code: no request data on `static` / custom singletons  
2. No `die`/`exit` on API path  
3. `ApiService` / `ProtectedApiService` (not inventing a second stack)  
4. Pool env set; monitor pool stats under load  
5. Rate limit: Redis/`both` if multiple workers/nodes  
6. URL sections correct for Swoole (no accidental `/api` as service name)  
7. APM: root flush after response verified in staging  
8. Load test: concurrent users + worker recycle + DB idle timeout  

When in doubt, this page is the canonical answer for OpenSwoole behavior. Layer guides (`api`, `controller`, `model`, `database`) still define how to write `app/` code.

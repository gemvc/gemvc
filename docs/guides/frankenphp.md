# FrankenPHP runtime (GEMVC)

**Audience:** developers and evaluators running GEMVC under FrankenPHP (Caddy).  
**Purpose:** classic and worker modes, Caddyfile path security, isolation rules, Docker.

**Related:** [http-lifecycle.md](http-lifecycle.md) · [architecture.md](architecture.md) · [security.md](security.md) · [openswoole.md](openswoole.md) · [ecosystem.md](ecosystem.md) · [installation.md](installation.md)

**Framework version:** 5.14+ (FrankenPHP classic + worker; unified `ApiService` / `ProtectedApiService`).

---

## Short answer

| Mode | Entry | Bootstrap | Response | DB |
|------|-------|-----------|----------|-----|
| **Classic** (default) | `index.php` | `Bootstrap` (may `die` after send) | `JsonResponse::show()` | connection-pdo |
| **Worker** | `worker.php` | `FrankenPhpBootstrap` (**never** `die`) | `show()` then continue loop | connection-pdo |

Both use `StandardHttpRequest` and `/api/{Service}/{method}` routing (same as Apache/Nginx). Path protection is primarily the **`Caddyfile`** (never `.htaccess`). Worker mode adds PHP **`SecurityManager`** as defense-in-depth.

Worker isolation rules match the product model in [openswoole.md](openswoole.md): **new request object graph every hit** — do not put user/token/`Request` in `static` properties or custom singletons.

---

## Reading map

| Question | Jump to |
|----------|---------|
| Classic lifecycle | [Classic mode](#classic-mode) |
| Worker lifecycle / isolation | [Worker mode](#worker-mode) |
| Path security | [Caddyfile security](#caddyfile-security-mandatory) |
| Enable worker | [Enabling worker mode](#enabling-worker-mode) |
| vs OpenSwoole | [Runtime matrix](#runtime-matrix) |
| App author rules | [Developer rules](#developer-rules-mandatory) |

---

## Classic mode

```
HTTP → FrankenPHP / Caddy (Caddyfile denies + php_server)
  → index.php
  → Dotenv + NoCors::apache()
  → new StandardHttpRequest() → new Request()
  → new Bootstrap($request)   // may die after show()
  → App\Api\{Service}
  → JsonResponse::show()
```

Set **`APP_ENV_SERVER=frankenphp`**. Template: `src/startup/frankenphp/index.php` + `Caddyfile`.

---

## Worker mode

### Lifecycle

```
Worker process boots once (worker.php)
  → Dotenv + FrankenPhpWorker::run()
  → loop: frankenphp_handle_request($handler)
       → SecurityManager::isRequestAllowed (defense-in-depth)
       → new StandardHttpRequest() → new Request()     // per request
       → new FrankenPhpBootstrap($request)       // per request
       → new App\Api\{Service}($request)
       → JsonResponse|HtmlResponse::show()       // NO die()
       → APM flush → gc_collect_cycles()
```

| Object | Per request? | Notes |
|--------|--------------|--------|
| `StandardHttpRequest` + `Request` | **Yes** | Superglobals reset by FrankenPHP each `frankenphp_handle_request` |
| `FrankenPhpBootstrap` | **Yes** | Classic `/api/` hop routing |
| `App\Api\*` | **Yes** | `new $service($request)` |
| APM on `$request->apm` | **Yes** | Flush after emit |
| `SecurityManager` instance | Worker-level | Rules only; no session identity |
| DB (`connection-pdo`) | Process-level | Same as classic FrankenPHP — **not** OpenSwoole pool |

### Isolation (same as OpenSwoole product rules)

**Guaranteed by framework:** request identity and payload live on the per-request `Request` graph.  
**Not guaranteed:** app `static` / singletons / unbounded caches holding request data.

FrankenPHP resets `$_GET` / `$_POST` / `$_SERVER` / etc. between requests. **`$_ENV` is not reset** — do not store request-specific data in `$_ENV`.

GEMVC does **not** use a coroutine context API here. Pattern: adapter → unified `Request` → same `app/` layers.

### No `die()` / `exit()`

`FrankenPhpBootstrap` returns responses (like `SwooleBootstrap`). `JsonResponse::show()` emits headers/body and **returns** (Bootstrap may still `die` in classic mode after `show()`). App code and helpers must not call `die()`/`exit()` under worker mode or the worker thread dies.

### Optional recycle

Set `FRANKENPHP_MAX_REQUESTS` or `MAX_REQUESTS` so the worker exits after N hits and FrankenPHP restarts it (mitigates leaks in long-lived PHP).

---

## Enabling worker mode

```bash
# Local
frankenphp run --config Caddyfile.worker

# Or point FRANKENPHP_CONFIG at the worker script (Docker)
# ENV FRANKENPHP_CONFIG="worker ./worker.php"
# and use Caddyfile.worker as the site Caddyfile
```

`gemvc init --frankenphp` copies both `Caddyfile` (classic) and `Caddyfile.worker` + `worker.php`.

URLs stay **`/api/{Service}/{method}`** (not OpenSwoole’s section-only path without `api`).

---

## Caddyfile security (mandatory)

| Denied | Response |
|--------|----------|
| `/app/*`, `/vendor/*`, `/bin/*`, `/config/*`, `/templates/*` | 403 |
| `*.env`, `*.json`, `*.lock`, logs, bak, `.git*` | 404 |

Worker mode: same Caddy denies **plus** `SecurityManager::emitForbidden()` inside `FrankenPhpWorker` if a sensitive path reaches PHP.

Never ship `.htaccess` expecting Caddy to honor it.

---

## Developer rules (mandatory)

1. Prefer **`ApiService` / `ProtectedApiService`** — same as all servers.
2. **Never `die()` / `exit()`** in worker mode after writing a response.
3. Do not keep user/JWT/`Request` on `static` properties or process singletons.
4. Do not mutate `$_ENV` with request-scoped secrets.
5. Path protection: trust **Caddyfile**; treat `SecurityManager` as backup only in worker.
6. Use `FRANKENPHP_MAX_REQUESTS` in production if you suspect leaks.

---

## Runtime matrix

| Concern | Classic FrankenPHP | Worker FrankenPHP | OpenSwoole |
|---------|-------------------|-------------------|------------|
| Adapter | `StandardHttpRequest` | `StandardHttpRequest` | `SwooleRequest` |
| Bootstrap | `Bootstrap` | `FrankenPhpBootstrap` | `SwooleBootstrap` |
| URL | `/api/...` hop | `/api/...` hop | `SERVICE_IN_URL_SECTION` (no automatic `api`) |
| Edge deny | Caddyfile | Caddyfile + SecurityManager | SecurityManager |
| Response | `show()`; Bootstrap may `die` | `show()`; **no die** | `showSwoole()`; no die |
| DB | connection-pdo | connection-pdo | connection-openswoole |

---

## Scaffolding

```bash
php vendor/bin/gemvc init --frankenphp
```

## Docker

Pinned image: **`dunglas/frankenphp:1-php8.4-bookworm`**.  
Site Caddyfile path in current images: **`/etc/frankenphp/Caddyfile`**.

```bash
docker build -t gemvc-frankenphp .
docker run --rm -p 80:80 -e SERVER_NAME=:80 gemvc-frankenphp
```

Repo smoke (classic + worker `/api/Index/ping`):

```bash
bash tests/smoke/frankenphp-smoke.sh
```

---

## FAQ

**Do I need FrankenPhpRequest?**  
No for classic or worker — both use `StandardHttpRequest` (superglobals). `ApacheRequest` is a deprecated alias.

**Is worker the same as OpenSwoole?**  
Same isolation *philosophy*; different adapter/bootstrap/DB package and URL hop rules.

**Can I use classic and worker in one project?**  
Yes — two Caddyfiles / configs; same `app/`.

**gRPC?**  
Out of scope.

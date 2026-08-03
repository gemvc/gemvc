# FrankenPHP runtime (GEMVC)

**Audience:** developers and evaluators running GEMVC under FrankenPHP (Caddy).  
**Purpose:** classic-mode entry, Caddyfile path security, Docker, and how this runtime relates to Apache/Nginx/OpenSwoole.

**Related:** [http-lifecycle.md](http-lifecycle.md) · [architecture.md](architecture.md) · [security.md](security.md) · [openswoole.md](openswoole.md) · [ecosystem.md](ecosystem.md) · [installation.md](installation.md)

**Framework version:** 5.13+ (unified `ApiService` / `ProtectedApiService` on all servers).

---

## Short answer

GEMVC’s **v1 FrankenPHP support is classic mode**: each request boots like PHP-FPM. The edge is **Caddy + FrankenPHP**; application code uses the same stack as Apache/Nginx:

`ApacheRequest` → `Bootstrap` → `ApiService` / layers → `JsonResponse::show()` → **`gemvc/connection-pdo`**

Path protection is configured in the **`Caddyfile`** (deny `app/`, `vendor/`, secrets). **Do not use `.htaccess`** — Caddy ignores it.

**Worker mode** (keep the app in memory across requests) is a **follow-up**, not part of this delivery. Isolation lessons for long-lived workers live in [openswoole.md](openswoole.md).

---

## Reading map

| Question | Jump to |
|----------|---------|
| How does a request flow? | [Lifecycle](#lifecycle-classic-mode) |
| Where is path security? | [Caddyfile security](#caddyfile-security-mandatory) |
| Init / Docker | [Scaffolding](#scaffolding-gemvc-init) · [Docker](#docker) |
| vs other servers | [Runtime matrix](#runtime-matrix) |
| Worker mode | [Later: worker mode](#later-worker-mode) |

---

## Lifecycle (classic mode)

```
HTTP → FrankenPHP / Caddy (Caddyfile denies + php_server)
  → index.php
  → Dotenv + NoCors::apache()
  → new ApacheRequest()   → new Request()
  → new Bootstrap($request)
  → App\Api\{Service}
  → Controller / Model / Table
  → JsonResponse::show()
```

| Piece | Role |
|-------|------|
| `src/startup/frankenphp/index.php` | Entry (same pattern as Nginx) |
| `Caddyfile` | Docroot, denies, `php_server` rewrite |
| `ApacheRequest` | Shared PHP-FPM-style adapter (no separate FrankenPhpRequest) |
| `Bootstrap` | Same as Apache/Nginx (may terminate after send) |
| DB | `DatabaseManagerFactory` → PDO (`APP_ENV_SERVER=frankenphp` is **not** swoole) |

Set **`APP_ENV_SERVER=frankenphp`** in `.env` so `WebserverDetector` does not guess from `SERVER_SOFTWARE`.

---

## Caddyfile security (mandatory)

FrankenPHP is Caddy. **Edge path rules belong in the Caddyfile**, not in PHP `SecurityManager` (that runs on OpenSwoole only) and **not** in `.htaccess`.

Startup template denies (parity with `nginx.conf` / Apache `.htaccess`):

| Denied | Response |
|--------|----------|
| `/app/*`, `/vendor/*`, `/bin/*`, `/config/*`, `/templates/*` | 403 |
| `*.env`, `*.json`, `*.lock`, logs, bak, hidden / `.git*` | 404 |

Docroot is the **project root** (`.` beside `index.php` / `app/`), matching the Nginx layout — not the image default `/app/public`. Docker `WORKDIR` is `/app`.

Never ship `.htaccess` into a FrankenPHP project expecting it to protect paths — it will not run.

---

## Scaffolding (`gemvc init`)

```bash
php vendor/bin/gemvc init --frankenphp
# or
php vendor/bin/gemvc init --server=frankenphp
```

Interactive menu option **4** selects FrankenPHP. Init copies `src/startup/frankenphp/` + shared `src/startup/common/` sample app (same User CRUD as other servers).

---

## Docker

Pinned base image in the template Dockerfile:

`dunglas/frankenphp:1-php8.3-bookworm`

```bash
docker build -t gemvc-frankenphp .
docker run --rm -p 80:80 -e SERVER_NAME=:80 gemvc-frankenphp
```

Compose (via `gemvc init` Docker offer) maps host port → container `80`, service name `web`, volume `./:/app`.

Smoke check: `GET /api/User/list` (or your Index service) after migrate.

---

## Runtime matrix

| Concern | Apache | Nginx | FrankenPHP (classic) | OpenSwoole |
|---------|--------|-------|----------------------|------------|
| Request adapter | `ApacheRequest` | `ApacheRequest` | `ApacheRequest` | `SwooleRequest` |
| Bootstrap | `Bootstrap` | `Bootstrap` | `Bootstrap` | `SwooleBootstrap` |
| Edge path deny | `.htaccess` | `nginx.conf` | **`Caddyfile`** | `SecurityManager` |
| DB package | connection-pdo | connection-pdo | connection-pdo | connection-openswoole |
| Response | `show()` / may `die` | same | same | `showSwoole()` / never `die` |
| Same `app/` | Yes | Yes | Yes | Yes |

---

## Later: worker mode

FrankenPHP worker mode keeps PHP workers alive (closer to OpenSwoole). GEMVC does **not** ship a worker bootstrap in v1. When added, expect:

- No reliance on `die()` after response
- Per-request object graph (same discipline as [openswoole.md](openswoole.md))
- Optional defense-in-depth path checks in PHP

Until then, use **classic mode** only.

---

## FAQ

**Do I need a FrankenPhpRequest?**  
No. Classic mode uses `ApacheRequest`.

**Can I copy `.htaccess` from Apache init?**  
No. Use the Caddyfile denies.

**Is FrankenPHP the same as “Caddy alone”?**  
Detection prefers `APP_ENV_SERVER=frankenphp` and `SERVER_SOFTWARE` containing `frankenphp`. Do not set a vague `caddy` env value.

**gRPC?**  
Out of scope for this runtime. HTTP/JSON only.

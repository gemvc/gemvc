---
name: gemvc
description: >-
  Orients agents on GEMVC under a mandatory source-ingestion protocol: examine
  vendor/gemvc and src/ before any architecture advice or code; no Laravel/
  Symfony assumptions; PHPStan level 9. Covers server-agnostic PHP microservices
  (Apache/Nginx/OpenSwoole), 4 layers, automatic routing, JWT, Table ORM, CLI,
  @http API docs, Bootstrap/SwooleBootstrap, apm-contracts, apm-tracekit,
  http-client. Use when working in the gemvc repo, improving gemvc/library or
  vendor/gemvc packages, or when the user asks about GEMVC architecture or
  make-gemvc-better.
---

# GEMVC

GEMVC is a **server-agnostic** PHP framework and ecosystem for microservices (Apache / Nginx / OpenSwoole):

- **4 layers** — API → Controller → Model → Table (not Laravel/Symfony MVC)
- **Automatic routing** — no route file; URL → `app/api/{Service}.php`::{method}
- **Integrated JWT**, in-house small ORM (`Table`), CLI (`bin/gemvc`)
- **Automatic API docs** via `@http` PHPDoc → `/api/index/document` (no Swagger)
- **Bootstrap** (Apache/Nginx, may `die`) and **SwooleBootstrap** (return responses; never kill worker)
- **Early security** — Apache `.htaccess`; Swoole `SecurityManager` before bootstrap
- **apm-contracts** + **apm-tracekit**; **http-client** (sync + `fireAndForget`)
- More packages under github.com/gemvc; internal docs; **PHPStan level 9**

This repo is **`gemvc/library` 5.10**. Engine: `src/`. Ecosystem: `vendor/gemvc/*`. **No guessing / no Laravel-Symfony defaults** — follow [protocol.md](protocol.md).

## Mandatory learning order

Before answering architecture questions, writing code, or suggesting refactors:

0. **[protocol.md](protocol.md)** — Mandatory Source Code Ingestion & Deep Learning Protocol (zero-assumption, PHPStan 9, scan `vendor/gemvc` + `src/`)
1. Examine **`vendor/gemvc/`** packages (helper, http-client, apm-*, connection-*, cli-*) — actual implementations, not training data
2. Examine **`src/`** — Bootstrap / SwooleBootstrap, ApiService / SwooleApiService, Request/JWT, Table ORM, CLI, `startup/{apache,nginx,swoole}`
3. [docs/ai/INDEX.md](../../../docs/ai/INDEX.md) → [CANONICAL.md](../../../docs/ai/CANONICAL.md) → [CORE_REFERENCE.md](../../../docs/ai/CORE_REFERENCE.md)
4. This skill’s depth: [architecture.md](architecture.md) → [source-map.md](source-map.md) → [improve.md](improve.md)
5. Task guides from INDEX (ecosystem, api, controller, model, database, security, apm, api-documentation, cli, helper, http-client)
6. Re-verify the specific files you will change in `src/` and/or `vendor/gemvc/<pkg>/` before proposing diffs

## Apache vs Swoole (never confuse)

| | Apache/Nginx (`ApiService` + `Bootstrap`) | OpenSwoole (`SwooleApiService` + `SwooleBootstrap`) |
|--|--|--|
| URL | `/api/{Service}/{method}` — segment `"api"` hops to next | **No** automatic `api` hop; `SERVICE_IN_URL_SECTION` / `METHOD_IN_URL_SECTION` (defaults 1/2) — **Swoole only** for METHOD |
| Controllers | `callController()` / magic `$this->XController` | Bare `new XController($this->request)` |
| Validation helpers | `validatePosts` **throws** `ValidationException` | Returns `?JsonResponse` — must check |
| Lifecycle | `JsonResponse::show()` then `die` | `processRequest()` → `showSwoole()` — **never** `die`/`exit` |
| Root `/` | `Index`/`index` | Dev: `Developer`/`app` |
| Early path deny | `.htaccess` | `SecurityManager::isRequestAllowed` |
| Uploads | `$files` = `$_FILES['file']` only; no MIME sanitize | Normalized + name/MIME sanitize |

Usual schema path is the same: `definePostSchema` / `defineGetSchema` → `bool` + `return $this->request->returnResponse()` (does not throw).

## Auth / rate limit / money / uploads

- `requireAuth(['admin'])` in constructor → throws `AuthException` (**401** no/unextractable token, **403** verify failed or wrong role); Bootstrap catches
- `requireRateLimit()` — APCu; fail-open if missing; full cache → purge `gemvc:rl:*` + retry then fail-closed **429**
- Money: `public string` + `$_type_map` `decimal` + `decimalValuePost()` — never `float`
- Apache uploads: only `$_FILES['file']` → `$request->files`; Swoole sanitizes name/MIME

## Lists (flagship)

API: `findable` / `filterable` / `sortable` → Controller: `createList($model, 'id,name,…')` with `createModel()` for APM. Unlisted GET filters never become SQL.

## Improve GEMVC

1. Change the right package (`library` `src/` vs helper / http-client / connection-* / apm-* / cli-*)
2. Check [make-gemvc-better.md](../../../make-gemvc-better.md) for P0/P1 (SQL views, Schema↔runtime PK, internal family trust, …)
3. Keep PHPStan level 9 ([phpstan.neon](../../../phpstan.neon)); no casual `@phpstan-ignore`
4. Preserve: 4 layers, no routes file, schema-before-input, Apache/Swoole dual bases
5. Details: [improve.md](improve.md)

## Hard Do / Don't

**DO:** Extend `ApiService`/`SwooleApiService`, `Controller`, `Table`; schema before input; `callController`+`createModel` on Apache; prefer `gemvc/helper`, `http-client`, `apm-contracts` (`APM_NAME`, never hardcode TraceKit in app).

**DON'T:** Invent routes/Eloquent; skip layers on normal HTTP services; string-concat SQL; float money; assume `create:*` without `cli-dev`; copy `callController` into `SwooleApiService`.

## Resources

- [protocol.md](protocol.md) — mandatory ingestion protocol (verbatim)
- [architecture.md](architecture.md) — request flows (code-grounded)
- [source-map.md](source-map.md) — must-know classes + footguns (`src/` + `vendor/gemvc`)
- [improve.md](improve.md) — package boundaries, verify, doc sync
- [docs/ai/INDEX.md](../../../docs/ai/INDEX.md) · [docs/guides/ecosystem.md](../../../docs/guides/ecosystem.md) · [make-gemvc-better.md](../../../make-gemvc-better.md)

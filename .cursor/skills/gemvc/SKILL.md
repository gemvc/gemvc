---
name: gemvc
description: >-
  Orients agents on GEMVC under a mandatory source-ingestion protocol: examine
  vendor/gemvc and src/ before any architecture advice or code; no Laravel/
  Symfony assumptions; PHPStan level 9. Covers server-agnostic PHP microservices
  (Apache/Nginx/OpenSwoole), 4 layers, automatic routing, JWT, Table/ViewTable ORM, CLI,
  @http API docs, Bootstrap/SwooleBootstrap, apm-contracts, apm-tracekit,
  http-client. Use when working in the gemvc repo, improving gemvc/library or
  vendor/gemvc packages, or when the user asks about GEMVC architecture or
  upstream improvements (trust mesh / Schema PK).
---

# GEMVC

GEMVC is a **server-agnostic** PHP framework and ecosystem for microservices (Apache / Nginx / OpenSwoole):

- **4 layers** — API → Controller → Model → Table / **ViewTable** (not Laravel/Symfony MVC)
- **Automatic routing** — no route file; URL → `app/api/{Service}.php`::{method}
- **Integrated JWT**, in-house ORM (`Table` + **`ViewTable`**), CLI (`bin/gemvc`)
- **Automatic API docs** via `@http` PHPDoc → `/api/index/document` (no Swagger)
- **Bootstrap** (Apache/Nginx, may `die`) and **SwooleBootstrap** (return responses; never kill worker)
- **Early security** — Apache `.htaccess`; Swoole `SecurityManager` before bootstrap
- **apm-contracts** + **apm-tracekit**; **http-client** (sync + `fireAndForget`)
- More packages under github.com/gemvc; internal docs; **PHPStan level 9**

This repository is **`gemvc/library` 5.11** — engine lives in `src/`. Ecosystem packages are installed under `vendor/gemvc/` (there is no top-level `packages/` directory here). **No guessing / no Laravel-Symfony defaults** — follow [protocol.md](protocol.md).

**Protocol status:** Source under `src/` and all of `vendor/gemvc/{helper,http-client,apm-contracts,apm-tracekit,connection-*,cli-*}` examined (2026-08 re-learn). Skills [architecture.md](architecture.md) / [source-map.md](source-map.md) hold the grounded map.

## Mandatory learning order

Before answering architecture questions, writing code, or suggesting refactors:

0. **[protocol.md](protocol.md)** — Mandatory Source Code Ingestion & Deep Learning Protocol (zero-assumption, PHPStan 9, scan `vendor/gemvc` + `src/`)
1. Examine **`vendor/gemvc/`** packages (helper, http-client, apm-*, connection-*, cli-*) — actual implementations, not training data
2. Examine **`src/`** — Bootstrap / SwooleBootstrap, ApiService / SwooleApiService, Request/JWT, Table ORM, CLI, `startup/{apache,nginx,swoole}`
3. [docs/ai/INDEX.md](../../../docs/ai/INDEX.md) → [CANONICAL.md](../../../docs/ai/CANONICAL.md) → [CORE_REFERENCE.md](../../../docs/ai/CORE_REFERENCE.md)
4. This skill’s depth: [architecture.md](architecture.md) → [source-map.md](source-map.md)
5. Task guides from INDEX (ecosystem, api, controller, model, database, security, apm, api-documentation, cli, helper, http-client)
6. Shared backlog (shipped docs only): [docs/improvements/](../../../docs/improvements/)
7. Re-verify the specific files you will change in `src/` and/or `vendor/gemvc/<pkg>/` before proposing diffs

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
2. Check [docs/improvements/](../../../docs/improvements/) for remaining work (Phase 2 trust/mesh; Schema PK stretch). **ViewTable is shipped** — use [database.md](../../../docs/guides/database.md)
3. Keep PHPStan level 9 ([phpstan.neon](../../../phpstan.neon)); no casual `@phpstan-ignore`
4. Preserve: 4 layers, no routes file, schema-before-input, Apache/Swoole dual bases

## Hard Do / Don't

**DO:** Extend `ApiService`/`SwooleApiService`, `Controller`, `Table`/`ViewTable`; schema before input; `callController`+`createModel` on Apache; prefer `gemvc/helper`, `http-client`, `apm-contracts` (`APM_NAME`, never hardcode TraceKit in app).

**DON'T:** Invent routes/Eloquent; skip layers on normal HTTP services; string-concat SQL; float money; assume `create:*` without `cli-dev`; copy `callController` into `SwooleApiService`; migrate a plain `Table` as a SQL view.

## Resources

- [protocol.md](protocol.md) — mandatory ingestion protocol (verbatim)
- [architecture.md](architecture.md) — request flows (code-grounded)
- [source-map.md](source-map.md) — must-know classes + footguns (`src/` + `vendor/gemvc`)
- [docs/ai/INDEX.md](../../../docs/ai/INDEX.md) · [AGENTS.md](../../../AGENTS.md) · [CLAUDE.md](../../../CLAUDE.md) · [GEMINI.md](../../../GEMINI.md) · [docs/guides/ecosystem.md](../../../docs/guides/ecosystem.md) · [docs/improvements/](../../../docs/improvements/)

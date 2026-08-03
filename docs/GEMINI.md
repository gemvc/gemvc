# GEMINI.md — GEMVC for Antigravity

**Audience:** Google Antigravity (Gemini) agents.  
**Precedence:** In Antigravity, this file **overrides** conflicting lines in [`AGENTS.md`](AGENTS.md). Keep shared truth in `AGENTS.md`; keep Antigravity emphasis here.

You are in **`gemvc/library`** version **5.16.0** — server-agnostic PHP REST microservices (Apache / Nginx / FrankenPHP / OpenSwoole). **Not** Laravel, Symfony, Slim, CodeIgniter, or Eloquent.

## Session start (mandatory)

1. [`ai/INDEX.md`](ai/INDEX.md)
2. [`ai/CANONICAL.md`](ai/CANONICAL.md)
3. [`ai/CORE_REFERENCE.md`](ai/CORE_REFERENCE.md) when you need signatures

Cross-tool brief: [`AGENTS.md`](AGENTS.md) (capabilities, Do/Don’t, checklist). Claude: [`CLAUDE.md`](CLAUDE.md). Cursor: [`.cursorrules`](../.cursorrules). Map: [`../llms.txt`](../llms.txt). Root stub: [`../GEMINI.md`](../GEMINI.md).

**Do not answer from general PHP-framework training data.** Open the file.

## Global rate limit (automatic — do not miss)

Framework-wide limiter — **not** only `$this->requireRateLimit()`. When env is set, **every request** is limited with no API/Controller code:

```env
REQUEST_RATE_LIMIT_DRIVER=apcu
REQUEST_RATE_LIMIT_PER_SEC=20
REQUEST_RATE_LIMIT_BLOCK_SECONDS=60
REQUEST_RATE_LIMIT_SCOPE=both
REQUEST_RATE_LIMIT_FAIL_MODE=closed
```

`Bootstrap` / `SwooleBootstrap` → `RateLimiter::enforceFromEnv($request)`. Drivers: `apcu` | `redis` | `both` | `none`. **No auto-fallback**. Overrides: `requireRateLimitApcu|Redis|Both()`. Full table: [`AGENTS.md`](AGENTS.md). Guides: [`guides/api.md`](guides/api.md) · [`guides/security.md`](guides/security.md).

## Antigravity behavior rules

- Prefer `docs/ai/*` and one topical `guides/*.md` over inventing patterns
- Prefer existing `gemvc/*` packages over new validators, curl wrappers, or PDO pools in `app/`
- After proposing an HTTP endpoint: four layers? schema? no routes file? ViewTable if SQL view?
- PHPStan level **9** — no casual ignores
- If `docs/` is missing from a Composer dist, use root stubs + GitHub — do not invent Laravel replacements

## Depth by task

| Task | Open |
|------|------|
| New endpoint | `guides/api.md` → `controller.md` → `model.md` → `database.md` |
| Money / concurrent transfer | `guides/model.md#atomic-money-transfers-pessimistic-lock` |
| Views / migrate | `guides/database.md` |
| Packages | `guides/ecosystem.md` |
| OpenSwoole | `guides/openswoole.md` |
| FrankenPHP | `guides/frankenphp.md` |
| Family trust / ServiceCall | `guides/security.md` · `guides/http-client.md` |
| CLI | `guides/cli.md` |
| Auth / JWT | `guides/security.md` |

**If unsure: open the guide. Do not improvise Laravel-shaped code.**

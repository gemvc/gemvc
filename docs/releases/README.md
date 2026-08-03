# Releases & changelog

**Audience:** humans checking what changed in a version. **AI assistants: skip unless the task is about a specific version or migration.**

| File | Use when |
|------|----------|
| [RELEASE_NOTES.md](RELEASE_NOTES.md) | Narrative what/why/migration for a release |
| [CHANGELOG.md](CHANGELOG.md) | Short “is feature X in version Y?” |

These files are large (~1–2k lines). They are **not** part of the AI mandatory path (`INDEX` → `CANONICAL` → `CORE_REFERENCE`). Do not load them for ordinary coding tasks.

**Env name note (APM):** older release text may have said `TRACEKIT_API_URL`; current TraceKit provider env is **`TRACEKIT_ENDPOINT`**. Prefer unified `APM_*` from `gemvc/apm-contracts` — see [apm.md](../guides/apm.md).

Current framework version in docs: **5.14.0** — see root [README](../../README.md) and [docs/README](../README.md).

**5.14.0:** FrankenPHP classic + worker; `StandardHttpRequest` (deprecated `ApacheRequest` alias); Caddyfile path security. See [RELEASE_NOTES](RELEASE_NOTES.md) and [frankenphp.md](../guides/frankenphp.md).

**5.13.0:** Unified `ApiService` / `ProtectedApiService` for Apache, Nginx, and OpenSwoole; `validateOrFail`; `ApiServiceSharedTrait` (`requireAuth`, rate limits, `callController`); `SwooleApiService` / `ProtectedSwooleApiService` deprecated thin subclasses. See [RELEASE_NOTES](RELEASE_NOTES.md) and [api.md](../guides/api.md).

**5.12.0:** RateLimiter drivers `apcu` | `redis` | `both` | `none`, `requireRateLimitApcu|Redis|Both()`, fail-closed + `FAIL_MODE` (no auto store fallback); `ProtectedApiService` / `ProtectedSwooleApiService`; `Table`/`Select::forUpdate()` + atomic transfer docs. See [RELEASE_NOTES](RELEASE_NOTES.md) and [model.md — Atomic money transfers](../guides/model.md#atomic-money-transfers-pessimistic-lock).

**5.11.0:** first-class SQL views via **`ViewTable`** / `ViewGenerator`, `db:migrate` for views, and **`db:migrate --all`**. Guide: [database.md — ViewTable](../guides/database.md#sql-views-via-viewtable-recommended).

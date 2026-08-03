# Releases & changelog

**Audience:** humans checking what changed in a version (framework users and maintainers). **AI assistants: skip unless the task is about a specific version or migration.**

## File roles

| File | Use when |
|------|----------|
| [RELEASE_NOTES.md](RELEASE_NOTES.md) | Narrative what/why/migration. Top banner (logo + Full Changelog compare) points at the **latest** tag range. |
| [CHANGELOG.md](CHANGELOG.md) | Short Keep-a-Changelog “is feature X in version Y?” |
| `github-X.Y.Z.md` | **Ephemeral** paste body for the GitHub Release UI. Create before publish; **delete after** the release is live. Not an archive — GitHub + RELEASE_NOTES/CHANGELOG are canonical. |

These narrative files are large (~1–2k lines). They are **not** part of the AI mandatory path (`INDEX` → `CANONICAL` → `CORE_REFERENCE`). Do not load them for ordinary coding tasks.

**Env name note (APM):** older release text may have said `TRACEKIT_API_URL`; current TraceKit provider env is **`TRACEKIT_ENDPOINT`**. Prefer unified `APM_*` from `gemvc/apm-contracts` — see [apm.md](../guides/apm.md).

## Release doc checklist

1. Bump version mentions: root [README](../../README.md), [docs/README](../README.md), this file, front doors / `llms.txt` / `ARCHITECTURE.md` / `docs/ai/*` as needed
2. Prepend [CHANGELOG](CHANGELOG.md) + [RELEASE_NOTES](RELEASE_NOTES.md); refresh RELEASE_NOTES banner compare (`prev...new`)
3. Update topical guides + AI pack if APIs / env / CLI changed
4. Drop finished rows from [`.cursor/improvements/`](../../.cursor/improvements/) (maintainer backlog); document shipped work in guides
5. Optionally draft `github-X.Y.Z.md` → paste into GitHub Release → **delete the file** after publish

Current framework version in docs: **5.16.0** — see root [README](../../README.md) and [docs/README](../README.md).

**5.16.0:** ServiceCall + `GEMVC_SERVICES_JSON` (Phase 2b mesh DX). See [RELEASE_NOTES](RELEASE_NOTES.md) and [http-client.md](../guides/http-client.md#servicecall-phase-2b).

**5.15.0:** Family trust — `requireInternalService()` / HMAC (`InternalTrust`). See [RELEASE_NOTES](RELEASE_NOTES.md) and [security.md](../guides/security.md#family-trust-phase-2a).

**5.14.0:** FrankenPHP classic + worker; `StandardHttpRequest` (deprecated `ApacheRequest` alias); Caddyfile path security. See [RELEASE_NOTES](RELEASE_NOTES.md) and [frankenphp.md](../guides/frankenphp.md).

**5.13.0:** Unified `ApiService` / `ProtectedApiService` for Apache, Nginx, and OpenSwoole; `validateOrFail`; `ApiServiceSharedTrait` (`requireAuth`, rate limits, `callController`); `SwooleApiService` / `ProtectedSwooleApiService` deprecated thin subclasses. See [RELEASE_NOTES](RELEASE_NOTES.md) and [api.md](../guides/api.md).

**5.12.0:** RateLimiter drivers `apcu` | `redis` | `both` | `none`, `requireRateLimitApcu|Redis|Both()`, fail-closed + `FAIL_MODE` (no auto store fallback); `ProtectedApiService` / `ProtectedSwooleApiService`; `Table`/`Select::forUpdate()` + atomic transfer docs. See [RELEASE_NOTES](RELEASE_NOTES.md) and [model.md — Atomic money transfers](../guides/model.md#atomic-money-transfers-pessimistic-lock).

**5.11.0:** first-class SQL views via **`ViewTable`** / `ViewGenerator`, `db:migrate` for views, and **`db:migrate --all`**. Guide: [database.md — ViewTable](../guides/database.md#sql-views-via-viewtable-recommended).

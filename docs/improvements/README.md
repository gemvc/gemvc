# GEMVC improvements

**Audience:** maintainers tracking **remaining** framework work.  
**AI:** skip unless implementing an **active** row below (e.g. Phase 2 trust/mesh or Schema PK stretch).

Done work is recorded in [guides/](../guides/) and [RELEASE_NOTES](../releases/RELEASE_NOTES.md) — do not re-implement items listed as shipped below.

## Active backlog

| Item | Status | Where to read |
|------|--------|----------------|
| Schema `primary` → runtime/DDL | Stretch (not done) | [database.md — Primary keys](../guides/database.md#primary-keys-ddl-runtime) |
| Developer UI table list views | Future (library) | Still **BASE TABLE** only in `DeveloperTable::getAllTables` |
| gRPC (optional future runtime) | Not started | Candidate for a later minor |
| Phase 2 stretch (Redis registry / per-service HMAC / nonce) | Not started | [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md) |

## Shipped (do not re-implement)

| Item | Shipped in | Canonical guide / notes |
|------|------------|-------------------------|
| Mesh DX (`ServiceCall` / `GEMVC_SERVICES_JSON`) | **5.16.0** | [http-client.md](../guides/http-client.md#servicecall-phase-2b); [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md) |
| Family trust (`requireInternalService` / HMAC) | **5.15.0** | [security.md](../guides/security.md#family-trust-phase-2a); [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md) |
| Unified `ApiService` (runtime) | **5.13.0** | [api.md](../guides/api.md); [RELEASE_NOTES](../releases/RELEASE_NOTES.md) |
| FrankenPHP classic + worker + `StandardHttpRequest` | **5.14.0** | [frankenphp.md](../guides/frankenphp.md); [RELEASE_NOTES](../releases/RELEASE_NOTES.md) |
| Rate-limit drivers / Protected API / `forUpdate` | **5.12.0** | [RELEASE_NOTES](../releases/RELEASE_NOTES.md); [model.md — transfers](../guides/model.md#atomic-money-transfers-pessimistic-lock) |
| **ViewTable** + `db:migrate` / `--all` | **5.11.0** | [database.md — ViewTable](../guides/database.md#sql-views-via-viewtable-recommended) |
| cli-dev `db:list` / describe / drop views | **cli-dev 1.3.0** | [cli-reference.md — db:list](../guides/cli-reference.md#db-list) |

## Related

- Security / auth: [guides/security.md](../guides/security.md)
- HTTP client: [guides/http-client.md](../guides/http-client.md)
- Cross-agent front doors: [`AGENTS.md`](../../AGENTS.md), [`CLAUDE.md`](../../CLAUDE.md), [`GEMINI.md`](../../GEMINI.md), [`llms.txt`](../../llms.txt)

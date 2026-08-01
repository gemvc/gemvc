# GEMVC improvements

**Audience:** maintainers implementing remaining framework work.  
**AI: skip unless implementing Phase 2 (trust/mesh) or Schema PK stretch.**

## Status

| Item | Status | Where to read |
|------|--------|----------------|
| Rate-limit drivers / Protected API / `forUpdate` | **Shipped (5.12.0)** | [RELEASE_NOTES](../releases/RELEASE_NOTES.md); [model.md — transfers](../guides/model.md#atomic-money-transfers-pessimistic-lock) |
| **ViewTable** + `db:migrate` / `--all` | **Shipped (5.11.0)** | [guides/database.md](../guides/database.md#sql-views-via-viewtable-recommended) |
| cli-dev `db:list` / describe / drop views | **Shipped (cli-dev 1.3.0)** | [cli-reference.md — db:list](../guides/cli-reference.md#db-list) |
| **Phase 2** — Trust + mesh | Planned | [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md) |
| Schema `primary` → runtime/DDL | Stretch (not done) | [database.md — Primary keys](../guides/database.md#primary-keys-ddl-runtime) |
| Developer UI table list views | Future (library) | Still **BASE TABLE** only in `DeveloperTable::getAllTables` |

Do **Phase 2a (trust)** before **Phase 2b (mesh DX)**.

## Related

- Security / auth: [guides/security.md](../guides/security.md)
- HTTP client: [guides/http-client.md](../guides/http-client.md)
- Cross-agent front doors: [`AGENTS.md`](../../AGENTS.md), [`CLAUDE.md`](../../CLAUDE.md), [`GEMINI.md`](../../GEMINI.md), [`llms.txt`](../../llms.txt)

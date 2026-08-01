# GEMVC improvements

**Audience:** maintainers implementing remaining framework work.  
**AI: skip unless implementing Phase 2 (trust/mesh) or Schema PK stretch.**

## Status

| Item | Status | Where to read |
|------|--------|----------------|
| **ViewTable** + `db:migrate` / `--all` | **Shipped (5.11.0)** | [guides/database.md](../guides/database.md#sql-views-via-viewtable-recommended) |
| **Phase 2** — Trust + mesh | Planned | [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md) |
| Schema `primary` → runtime/DDL | Stretch (not done) | [database.md — Primary keys](../guides/database.md#primary-keys-ddl-runtime) |
| cli-dev `db:list` / describe views | Future ([cli-dev `cli-dev-update.md`](https://github.com/gemvc/cli-dev/blob/main/cli-dev-update.md)) | [cli-reference.md — db:list](../guides/cli-reference.md#db-list) |

Do **Phase 2a (trust)** before **Phase 2b (mesh DX)**.

## Related

- Skills: [improve.md](../../.cursor/skills/gemvc/improve.md)
- Security / auth: [guides/security.md](../guides/security.md)
- HTTP client: [guides/http-client.md](../guides/http-client.md)
- cli-dev view list brief (sibling repo): `cli-dev/cli-dev-update.md`
- Cross-agent front doors: [`AGENTS.md`](../../AGENTS.md), [`CLAUDE.md`](../../CLAUDE.md), [`GEMINI.md`](../../GEMINI.md), [`llms.txt`](../../llms.txt)

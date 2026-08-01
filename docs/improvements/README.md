# GEMVC improvements

**Audience:** maintainers implementing remaining framework work.  
**AI: skip unless implementing Phase 2 (trust/mesh).**

Upstream backlog / why: may live in historical notes; day-to-day view docs are in [guides/database.md](../guides/database.md).

## Status

| Phase | Doc | Status |
|-------|-----|--------|
| **1** — Views + migrate | [phase-1-views-and-migrate.md](phase-1-views-and-migrate.md) | **Shipped** in `gemvc/library` (`ViewTable`, `ViewGenerator`, `db:migrate` / `--all`). Use [guides/database.md](../guides/database.md) for day-to-day docs. |
| **2** — Trust + mesh | [phase-2-trust-and-mesh.md](phase-2-trust-and-mesh.md) | **Not implemented** — 2a family secret before 2b `ServiceCall` |

Phase 1 plan file is historical acceptance / design notes; **do not treat views as “planned”** — they are supported now.

## Related

- Database (ViewTable): [guides/database.md](../guides/database.md)
- Skills: [improve.md](../../.cursor/skills/gemvc/improve.md)
- Security / auth: [guides/security.md](../guides/security.md)
- HTTP client: [guides/http-client.md](../guides/http-client.md)

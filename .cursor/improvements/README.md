# GEMVC improvements

**Audience:** framework maintainers only — **not** for app developers using GEMVC.  
**AI building apps:** ignore this folder. **AI improving the library:** use only an **active** row below.

Done work lives in [docs/guides/](../../docs/guides/) and [RELEASE_NOTES](../../docs/releases/RELEASE_NOTES.md) — do not re-implement.

## Rules

- Active table only — no “Shipped” archive in this folder
- When an item ships: delete its plan file (if any), drop the row, document in guides + RELEASE_NOTES / CHANGELOG
- Stretch items may link to shipped guides they build on
- Do not put maintainer backlog under `docs/` (that tree is for target developers)

## Active backlog

| Item | Status | Where to read |
|------|--------|----------------|
| Schema `primary` → runtime/DDL | Stretch | [database.md — Primary keys](../../docs/guides/database.md#primary-keys-ddl-runtime) |
| Developer UI table list views | Future | Still **BASE TABLE** only in `DeveloperTable::getAllTables` |
| gRPC (optional future runtime) | Not started | Candidate for a later minor |
| Family mesh stretch | Not started | Redis registry / per-service HMAC / nonce store — build on shipped [security.md](../../docs/guides/security.md#family-trust-phase-2a) · [http-client.md](../../docs/guides/http-client.md#servicecall-phase-2b) |

## Related

- Security / family trust: [docs/guides/security.md](../../docs/guides/security.md)
- HTTP client / ServiceCall: [docs/guides/http-client.md](../../docs/guides/http-client.md)
- Releases: [RELEASE_NOTES](../../docs/releases/RELEASE_NOTES.md) · [CHANGELOG](../../docs/releases/CHANGELOG.md)

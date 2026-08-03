# GEMVC improvements

**Audience:** maintainers tracking **remaining** framework work.  
**AI:** skip unless implementing an **active** row below.

Done work lives in [guides/](../guides/) and [RELEASE_NOTES](../releases/RELEASE_NOTES.md) — do not re-implement.

## Active backlog

| Item | Status | Where to read |
|------|--------|----------------|
| Schema `primary` → runtime/DDL | Stretch | [database.md — Primary keys](../guides/database.md#primary-keys-ddl-runtime) |
| Developer UI table list views | Future | Still **BASE TABLE** only in `DeveloperTable::getAllTables` |
| gRPC (optional future runtime) | Not started | Candidate for a later minor |
| Family mesh stretch | Not started | Redis registry / per-service HMAC / nonce store — build on shipped [security.md](../guides/security.md#family-trust-phase-2a) · [http-client.md](../guides/http-client.md#servicecall-phase-2b) |

## Related

- Security / family trust: [guides/security.md](../guides/security.md)
- HTTP client / ServiceCall: [guides/http-client.md](../guides/http-client.md)
- Releases: [RELEASE_NOTES](../releases/RELEASE_NOTES.md) · [CHANGELOG](../releases/CHANGELOG.md)

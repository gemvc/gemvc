# Improving GEMVC safely

Use after [protocol.md](protocol.md), [SKILL.md](SKILL.md) learning order, and [architecture.md](architecture.md) / [source-map.md](source-map.md). Never propose changes from Laravel/Symfony habits — verify in `src/` and `vendor/gemvc/` first. PHPStan level 9 only.

## Package boundaries

| Package | Change here when… |
|---------|-------------------|
| `gemvc/library` (this repo, `src/`) | Bootstrap, ApiService, Controller, Table ORM, Request/Response, JWT, dialects, `bin/gemvc` entry, startup scaffolds |
| `gemvc/helper` | TypeChecker, CryptHelper, ProjectHelper, shared utilities |
| `gemvc/http-client` | Outbound sync/async HTTP (incl. fire-and-forget) |
| `gemvc/connection-contracts` | DB connection interfaces |
| `gemvc/connection-pdo` | PDO connections (Apache/Nginx/CLI) |
| `gemvc/connection-openswoole` | OpenSwoole pooled connections |
| `gemvc/apm-contracts` | ApmInterface / ApmFactory — apps select provider via `APM_NAME` |
| `gemvc/apm-tracekit` | TraceKit provider implementation only |
| `gemvc/cli-base` | CLI Command foundation |
| `gemvc/cli-dev` | Dev codegen / `create:*`, `db:list`, `admin:*` (require-dev) |

Keep **contracts + implementations**. Do not hardcode TraceKit or a specific connection driver inside app-facing library APIs when contracts already abstract them.

Catalog: [docs/guides/ecosystem.md](../../../docs/guides/ecosystem.md).

## Verification

- **PHPStan level 9:** [phpstan.neon](../../../phpstan.neon) (`level: 9`, `src`). Run:
  ```bash
  vendor/bin/phpstan analyse
  ```
- **Tests:** [tests/](../../../tests/) — see [tests/README.md](../../../tests/README.md)
- Do not add `@phpstan-ignore` or lower the level unless unavoidable and explained

## Doc sync

If public behavior, signatures, env vars, CLI flags, or layer contracts change:

1. Update matching guide under `docs/guides/`
2. Update `docs/ai/CANONICAL.md` / `CORE_REFERENCE.md` / INDEX when rules or signatures shift
3. Keep `.cursorrules` aligned with hard Do/Don't
4. Update this skill’s [architecture.md](architecture.md) / [source-map.md](source-map.md) if routing, auth, or package boundaries change

## Upstream backlog

Real friction from building services on GEMVC: [make-gemvc-better.md](../../../make-gemvc-better.md). Prefer P0/P1 over speculative features.

Notable P0 themes (read the file for full proposals):

1. **First-class SQL views** — migrate path + readOnly Table; stop inventing PDO view helpers
2. **Runtime PK from `Schema::primary`** — align migrate DDL with Table `_detectPrimaryKey` / `setPrimaryKey`
3. **Internal family trust** — `GEMVC_INTERNAL_SECRET` + `requireInternalService()` for service-to-service (separate from end-user JWT)

When implementing backlog items: preserve Apache/Swoole dual bases, PHPStan 9, and existing auth status semantics (401 vs 403).

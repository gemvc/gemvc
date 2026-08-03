# AI assistants — read this first

**GEMVC is NOT Laravel or Symfony.** Do not invent routes files, Eloquent, or magic relations.

**How you got here:** root [`AGENTS.md`](../../AGENTS.md) / [`CLAUDE.md`](../../CLAUDE.md) / [`GEMINI.md`](../../GEMINI.md) / [`.cursorrules`](../../.cursorrules) / [`llms.txt`](../../llms.txt) all point at this pack. Keep using **this** hierarchy for depth — do not invent from training data.

## Mandatory reading (in order)

1. **[CANONICAL.md](CANONICAL.md)** — 4-layer rules, Do/Don’t, auth, CRUD patterns, multi-DB, decimal
2. **[CORE_REFERENCE.md](CORE_REFERENCE.md)** — framework class signatures, schema types (not HTTP endpoint docs)

Optional machine/IDE mirrors (same content, not required):

- [core-reference.jsonc](core-reference.jsonc)
- [phpdoc-reference.php](phpdoc-reference.php)

## When you need depth

| Task | Open |
|------|------|
| **All gemvc/* packages (ecosystem)** | [../guides/ecosystem.md](../guides/ecosystem.md) |
| **`gemvc/helper`** (TypeChecker, CryptHelper, …) | [../guides/helper.md](../guides/helper.md) |
| **`gemvc/http-client`** (outbound HTTP) | [../guides/http-client.md](../guides/http-client.md) |
| Migrations / Table / **ViewTable** / connections | [../guides/database.md](../guides/database.md) (+ [ecosystem](../guides/ecosystem.md)) |
| **Lists** `createList` + findable/filterable/sortable | [../guides/controller.md](../guides/controller.md#lists-createlist) (+ [api.md](../guides/api.md#list-allowlists)) |
| **API** layer (schema / auth / call Controller) | [../guides/api.md](../guides/api.md) |
| **Controller** orchestration / lists | [../guides/controller.md](../guides/controller.md) |
| **Model** logic (Table-backed **or** composition; JsonResponse **or** PHP types; money transfers) | [../guides/model.md](../guides/model.md) |
| HTTP Request lifecycle / adapters | [../guides/http-lifecycle.md](../guides/http-lifecycle.md) |
| Install → first API call | [../guides/installation.md](../guides/installation.md) |
| Framework internals | [../guides/architecture.md](../guides/architecture.md) |
| Codegen templates | [../guides/templates.md](../guides/templates.md) |
| `gemvc init` / workflows / cli-dev | [../guides/cli.md](../guides/cli.md) |
| Full CLI flags / troubleshooting | [../guides/cli-reference.md](../guides/cli-reference.md) |
| APM `callController` / `createModel` | [../guides/apm.md](../guides/apm.md) |
| JWT / security | [../guides/security.md](../guides/security.md) |
| Auto API docs (`@http`) | [../guides/api-documentation.md](../guides/api-documentation.md) |
| What changed (releases) | [../releases/README.md](../releases/README.md) — **5.13** unified `ApiService`; **5.12** rate-limit drivers / Protected API / `forUpdate`; **5.11** ViewTable; older notes as needed |

## Hard rules (never violate)

- GEMVC is an **ecosystem** (`vendor/gemvc/*`) — not a single package; see [ecosystem.md](../guides/ecosystem.md)
- Prefer **4 layers** for HTTP services: API → Controller → Model → Table/`ViewTable`. Bypassing a layer is possible but **strongly discouraged**
- Authenticated CRUD: prefer **`ProtectedApiService`** (all servers). Public: `ApiService`. Deprecated: `SwooleApiService` / `ProtectedSwooleApiService`
- Prefer **`ViewTable`** for SQL views (`defineView` + migrate); never `db:migrate` a plain `Table` that points at a view name
- Never invent Eloquent-style relations or nested 1:n on views — views are flat; reshape in Model
- Never skip schema validation (`definePostSchema` / `defineGetSchema`)
- Never manually sanitize inputs (framework already does)
- Never create a routes file (URL maps to `app/api/{Service}/{method}`)
- Prefer `callController()` + `createModel()` for APM-ready code (`ApiService` on all servers) — see [api.md](../guides/api.md)
- **Lists:** API `findable`/`filterable`/`sortable` then Controller `createList(..., $columns)` — see [controller.md](../guides/controller.md#lists-createlist)
- Use `requireAuth()` in the service constructor to guard a whole service
- **Global rate limit:** `REQUEST_RATE_LIMIT_PER_SEC` + `REQUEST_RATE_LIMIT_DRIVER` (`apcu`|`redis`|`both`|`none`) → Bootstrap `enforceFromEnv`
- `requireRateLimit()` uses global driver; overrides: `requireRateLimitApcu|Redis|Both()` — **no auto-fallback** between stores
- Unavailable chosen backend → fail-closed unless `REQUEST_RATE_LIMIT_FAIL_MODE=open`
- Money/precision: `public string` + `$_type_map` `decimal` — never `float`; concurrent transfers: `beginTransaction` + `forUpdate` + BCMath (not raw PDO) — [model.md](../guides/model.md#atomic-money-transfers-pessimistic-lock)
- Prefer existing `gemvc/*` packages over reinventing helper/DB/APM/CLI code — especially **`gemvc/helper`**, **`gemvc/http-client`**, and **`gemvc/apm-contracts`** (providers via `APM_NAME`; never hardcode TraceKit in `app/`)

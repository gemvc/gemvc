# AI assistants — read this first

**GEMVC is NOT Laravel or Symfony.** Do not invent routes files, Eloquent, or magic relations.

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
| Migrations / Table / **connections** | [../guides/database.md](../guides/database.md) (+ [ecosystem](../guides/ecosystem.md)) |
| **API** layer (schema / auth / call Controller) | [../guides/api.md](../guides/api.md) |
| **Controller** orchestration / lists | [../guides/controller.md](../guides/controller.md) |
| **Model** logic (Table-backed **or** composition; JsonResponse **or** PHP types) | [../guides/model.md](../guides/model.md) |
| HTTP Request lifecycle / adapters | [../guides/http-lifecycle.md](../guides/http-lifecycle.md) |
| Install → first API call | [../guides/installation.md](../guides/installation.md) |
| Framework internals | [../guides/architecture.md](../guides/architecture.md) |
| Codegen templates | [../guides/templates.md](../guides/templates.md) |
| `gemvc init` / `db:migrate` / cli-dev | [../guides/cli.md](../guides/cli.md) |
| APM `callController` / `createModel` | [../guides/apm.md](../guides/apm.md) |
| JWT / security | [../guides/security.md](../guides/security.md) |
| Auto API docs (`@http`) | [../guides/api-documentation.md](../guides/api-documentation.md) |
| What changed in 5.9.x | [../releases/RELEASE_NOTES.md](../releases/RELEASE_NOTES.md) |

## Hard rules (never violate)

- GEMVC is an **ecosystem** (`vendor/gemvc/*`) — not a single package; see [ecosystem.md](../guides/ecosystem.md)
- Prefer **4 layers** for HTTP services: API → Controller → Model → Table. Bypassing a layer is possible but **strongly discouraged**
- Never skip schema validation (`definePostSchema` / `defineGetSchema`)
- Never manually sanitize inputs (framework already does)
- Never create a routes file (URL maps to `app/api/{Service}/{method}`)
- Prefer `callController()` + `createModel()` for APM-ready code
- Use `requireAuth()` in the service constructor to guard a whole service
- Money/precision: `public string` + `$_type_map` `decimal` — never `float`
- Prefer existing `gemvc/*` packages over reinventing helper/DB/APM/CLI code

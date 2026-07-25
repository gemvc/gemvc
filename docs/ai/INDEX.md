# AI assistants — read this first

**GEMVC is NOT Laravel or Symfony.** Do not invent routes files, Eloquent, or magic relations.

## Mandatory reading (in order)

1. **[CANONICAL.md](CANONICAL.md)** — 4-layer rules, Do/Don’t, auth, CRUD patterns, multi-DB, decimal
2. **[API_REFERENCE.md](API_REFERENCE.md)** — classes, methods, schema types

Optional machine/IDE mirrors (same content, not required):

- [api-reference.jsonc](api-reference.jsonc)
- [phpdoc-reference.php](phpdoc-reference.php)

## When you need depth

| Task | Open |
|------|------|
| **All gemvc/* packages (ecosystem)** | [../guides/ecosystem.md](../guides/ecosystem.md) |
| Migrations / Table / dialects | [../guides/database.md](../guides/database.md) |
| `gemvc init` / `db:migrate` / cli-dev | [../guides/cli.md](../guides/cli.md) |
| APM `callController` / `createModel` | [../guides/apm.md](../guides/apm.md) |
| JWT / security | [../guides/security.md](../guides/security.md) |
| Auto API docs (`@http`) | [../guides/api-documentation.md](../guides/api-documentation.md) |
| What changed in 5.9.x | [../releases/RELEASE_NOTES.md](../releases/RELEASE_NOTES.md) |

## Hard rules (never violate)

- GEMVC is an **ecosystem** (`vendor/gemvc/*`) — not a single package; see [ecosystem.md](../guides/ecosystem.md)
- Always use **4 layers**: API → Controller → Model → Table
- Never skip schema validation (`definePostSchema` / `defineGetSchema`)
- Never manually sanitize inputs (framework already does)
- Never create a routes file (URL maps to `app/api/{Service}/{method}`)
- Prefer `callController()` + `createModel()` for APM-ready code
- Use `requireAuth()` in the service constructor to guard a whole service
- Money/precision: `public string` + `$_type_map` `decimal` — never `float`
- Prefer existing `gemvc/*` packages over reinventing helper/DB/APM/CLI code

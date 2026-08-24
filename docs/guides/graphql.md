# GraphQL (Phase 1)

**Audience:** developers adding a GraphQL surface next to existing REST.

**Related:** [api.md](api.md) · [controller.md](controller.md) · [security.md](security.md) · [CANONICAL.md](../ai/CANONICAL.md)

GEMVC does **not** replace REST with GraphQL. GraphQL is **one API service** on automatic routing. No routes file. Resolvers call **Controller → Model**, never `Table`.

## Install

```bash
composer require webonyx/graphql-php
```

Without that package, `GraphQlRunner` returns HTTP **500** (fail-closed). `gemvc/library` does not require it for REST-only apps.

## Endpoint

```
POST /api/Graphql/query
Content-Type: application/json

{ "query": "{ hello }", "variables": {}, "operationName": null }
```

OpenSwoole: same service/method (`Graphql` / `query`). Optional proxy rewrite `/graphql` → `/api/Graphql/query` (Caddyfile / nginx / Apache) — still not a GEMVC routes file.

## App files

| Path | Role |
|------|------|
| `app/api/Graphql.php` | `ApiService` (or `ProtectedApiService` for JWT on every operation) |
| `app/controller/GraphQlController.php` | Resolvers call this |
| `app/graphql/schema.php` | Returns `Schema` or `callable(Request): Schema` |

`gemvc init` copies the samples from `src/startup/common/init_example/`. Also: [docs/examples/api/Graphql.php](../examples/api/Graphql.php).

```php
public function query(): JsonResponse
{
    if (!$this->request->definePostSchema([
        'query' => 'string',
        '?variables' => 'array',
        '?operationName' => 'string',
    ])) {
        return $this->request->returnResponse();
    }
    return \Gemvc\GraphQL\GraphQlRunner::fromRequest($this->request)->execute();
}
```

## HTTP mapping

| Situation | HTTP | Body |
|-----------|------|------|
| Missing/invalid JWT on `ProtectedApiService` | **401** / **403** | GEMVC JSON (`requireAuth` before execute) |
| Missing `query` / bad schema | **400** | GEMVC JSON (`definePostSchema`) |
| GraphQL field / validation errors | **200** | Spec envelope `{ "data", "errors" }` |
| `webonyx/graphql-php` missing / schema file missing | **500** | GEMVC JSON |

GraphQL clients must use the **spec envelope** on 200 (top-level `data` / `errors` only — not GEMVC `response_code` / `message`).

## Resolvers (4 layers)

```php
$response = (new UserController($request))->read();
return \Gemvc\GraphQL\JsonResponseBridge::dataOrThrow($response);
```

`JsonResponseBridge` turns Controller/Model 4xx/5xx into a GraphQL `UserError`. Do not `select()` on `Table` inside a resolver.

## Auth

- Public playground: `class Graphql extends ApiService`
- All operations authenticated: `extends ProtectedApiService` (constructor `requireAuth`)
- JWT is still HS256 `Request::auth()` — [security.md](security.md)

## Limits (DoS)

| Env | Default |
|-----|---------|
| `GRAPHQL_MAX_DEPTH` | 10 |
| `GRAPHQL_MAX_COMPLEXITY` | 200 |
| `GRAPHQL_INTROSPECTION` | unset: **on** in dev (`APP_ENV`), **off** in production; `0`/`off` forces off, `1`/`on` forces on |

## Not in Phase 1

Subscriptions (WebSocket), multipart uploads, DataLoader/N+1 helpers, GET queries, GraphiQL in Developer UI, auto-schema from `Table`/`ViewTable`.

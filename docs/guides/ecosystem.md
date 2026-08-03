# GEMVC Ecosystem — Packages & Modules

**Audience:** anyone choosing packages, wiring connections / helper / HTTP client / CLI · AI that must not invent Laravel-like replacements.

**Related:** [helper.md](helper.md) · [http-client.md](http-client.md) · [database.md](database.md) · [cli.md](cli.md) · [CANONICAL.md](../ai/CANONICAL.md)

## Reading map (AI)

| Need | Jump to |
|------|---------|
| Mental model / diagram | [Mental model](#mental-model) |
| Full package list | [Package catalog](#package-catalog) |
| **helper** / **http-client** | [helper.md](helper.md) · [http-client.md](http-client.md) (catalog sections under Package catalog) |
| Connections / `DB_DRIVER` | [Database connections](#database-connections) · [database.md](database.md) |
| CLI / cli-dev | [cli.md](cli.md) · [Package catalog](#package-catalog) |
| Hard rules | [Rules for AI assistants](#rules-for-ai-assistants) |

**GEMVC is not one Composer package.**  
`gemvc/library` is the **application framework** (4-layer API → Controller → Model → Table). Around it sits a set of **focused, tested, independently versioned** packages under the `gemvc/*` namespace. Apps install `gemvc/library`; Composer pulls most of the ecosystem automatically. AI assistants that only read `library/src` miss half the product.

> **Install path for apps:** `composer require gemvc/library`  
> Packages below live in `vendor/gemvc/<name>/` after install. Each has its own README (and often RELEASE_NOTES / AI docs). Prefer those over inventing Laravel-like equivalents.

---

## Mental model

```
                    ┌─────────────────────────┐
                    │     your app (app/)     │
                    │  api / controller / …   │
                    └───────────┬─────────────┘
                                │ uses
                    ┌───────────▼─────────────┐
                    │     gemvc/library       │  ← framework (this repo)
                    │  Bootstrap, ApiService, │
                    │  Table, Request, CLI…   │
                    └─┬───┬───┬───┬───┬───┬───┘
         requires     │   │   │   │   │   │
    ┌─────────────────┘   │   │   │   │   └─────────────────┐
    ▼                     ▼   ▼   ▼   ▼                     ▼
 helper              http-client  cli-base              apm-contracts
                      │            │                         │
                      │            │ suggest/dev             │
                      │            ▼                         ▼
                      │         cli-dev                 apm-tracekit
                      │
            ┌─────────┴─────────┐
            ▼                   ▼
   connection-contracts   (shared DB interfaces)
            │
    ┌───────┴────────┐
    ▼                ▼
 connection-pdo   connection-openswoole
 (Apache/Nginx)   (OpenSwoole pools)
```

**Pattern used everywhere:** *contracts* (interfaces) + *implementations* (swappable). Same idea for DB connections and APM providers.

---

## Package catalog

### Hub — `gemvc/library` (this repository)

| | |
|--|--|
| **Role** | REST microservice framework: Bootstrap / SwooleBootstrap, ApiService, Controller, Table ORM, Request/Response, JWT, dialects, `bin/gemvc` entry |
| **Pulls in** | helper, connection-*, apm-*, http-client, cli-base (see `composer.json` `require`) |
| **Optional** | `gemvc/cli-dev` via `suggest` / `require-dev` |
| **Docs** | [docs/README.md](../README.md), [ai/CANONICAL.md](../ai/CANONICAL.md) |

---

### Utilities — `gemvc/helper` (^1.1) — **core package**

| | |
|--|--|
| **Role** | **Essential** shared helpers used by the framework and your app |
| **Key classes** | `ProjectHelper` (paths, `.env`, URLs), `CryptHelper` (Argon2i), **`TypeChecker`** (schema types including `decimal`, `uuid`, `slug`, `hex`, `positive_int`, `timestamp`, `jsonb`), `FileHelper` / `ImageHelper`, `TypeHelper`, monitoring helpers |
| **Installed with** | `gemvc/library` (do not treat as a standalone utility kit for non-GEMVC apps) |
| **Library docs** | **[helper.md](helper.md)** |
| **Docs in vendor** | `vendor/gemvc/helper/README.md`, `RELEASE_NOTES.md` |

When validating HTTP input, types come from **helper**, not from inventing Laravel validation rules.

---

### Database connections

Table ORM **requires** these packages (pulled in by `gemvc/library`). Without them, `DatabaseManagerFactory` cannot supply connections and the Table layer cannot run queries.

#### Call path

```
Table → PdoQuery → UniversalQueryExecuter → DatabaseManagerFactory
  → connection-pdo (Apache/Nginx/CLI)  OR  connection-openswoole (pools)
  ← both implement connection-contracts
```

See the full stack in [database.md — Under the hood](database.md#under-the-hood-connection-stack).

#### `gemvc/connection-contracts`

| | |
|--|--|
| **Role** | Framework-agnostic **interfaces** only: `ConnectionInterface`, `ConnectionManagerInterface` |
| **Why it exists** | Library/query layer depends on contracts; PDO vs OpenSwoole implementations stay swappable |
| **Docs** | `vendor/gemvc/connection-contracts/README.md` |

#### `gemvc/connection-pdo` (^1.1)

| | |
|--|--|
| **Role** | **PDO** connection manager for Apache/Nginx/FrankenPHP classic (and CLI). Builds DSN from `DB_DRIVER` / `DB_*` — **MySQL, PostgreSQL, SQLite** |
| **Pooling** | Simple cache / optional persistent connections — **not** a Hyperf-style pool |
| **Key pieces** | `PdoConnection` (manager) + `PdoConnectionAdapter` |
| **Docs** | `vendor/gemvc/connection-pdo/README.md`, `CHANGELOG.md` |

#### `gemvc/connection-openswoole` (^1.1+)

| | |
|--|--|
| **Role** | **True connection pooling** for OpenSwoole (Hyperf pool), get + release per operation |
| **Drivers** | MySQL (primary), **PostgreSQL** supported; same contracts |
| **When used** | `WebserverDetector` → `swoole` and class exists; else factory falls back to PDO |
| **Docs** | `vendor/gemvc/connection-openswoole/README.md`, `RELEASE_NOTES.md`; library runtime behavior: [openswoole.md](openswoole.md) |

**AI rules:**

- Do not hardcode “always PDO” or “always MySQL”. **Runtime** chooses PDO vs OpenSwoole; **`DB_DRIVER`** chooses SQL engine.
- Do not `new PDO` / invent pools in `app/` — use Table/Model only.
- Do not confuse `TableComponents\ConnectionManager` with `ConnectionManagerInterface`.
- Deep how-to: [database.md](database.md#under-the-hood-connection-stack).

---

### APM (Application Performance Monitoring)

#### `gemvc/apm-contracts` (^1.5)

| | |
|--|--|
| **Role** | **Foundation:** `ApmInterface`, `AbstractApm`, **`ApmFactory`**, toolkit contracts — pluggable providers without changing app code |
| **Unified env** | `APM_NAME`, `APM_ENABLED`, `APM_SAMPLE_RATE`, `APM_TRACE_*`, … |
| **Docs** | `vendor/gemvc/apm-contracts/README.md` · library [apm.md](apm.md) |

#### `gemvc/apm-tracekit` (^2.0)

| | |
|--|--|
| **Role** | **One** provider (OTLP-style traces, batching). Ships with library today — not the only possible backend |
| **Config** | `APM_NAME=TraceKit` + unified `APM_*`; optional `TRACEKIT_API_KEY` / `TRACEKIT_ENDPOINT` / … |
| **Setup** | `php vendor/bin/tracekit init` (when available) |
| **Docs** | `vendor/gemvc/apm-tracekit/README.md` |

**AI rule:** New APM vendors implement **`apm-contracts`**; they do **not** fork `library` or hardcode TraceKit. App code keeps using `callController()` / `createModel()` / `$request->apm`.

---

### HTTP outbound calls — `gemvc/http-client` (^1.2) — **core package**

| | |
|--|--|
| **Role** | Sync + async HTTP **client** (calling *other* APIs), environment-aware (native / Swoole coroutines) |
| **Key classes** | `HttpClient`, `AsyncHttpClient`, `SwooleHttpClient`, typed exceptions |
| **Also** | Usable outside GEMVC; library `ApiCall` / `AsyncApiCall` use it internally |
| **Library docs** | **[http-client.md](http-client.md)** |
| **Docs** | `vendor/gemvc/http-client/README.md`, `CHANGELOG.md` |

Do not confuse with inbound `Gemvc\Http\Request` (library). Client = outbound.

---

### CLI

#### `gemvc/cli-base` (^1.0.1)

| | |
|--|--|
| **Role** | Internal CLI **foundation**: `Command`, `CliColor`, `CliLine`, `FileSystemManager`, codegen abstracts, `InstallControl` |
| **Who uses it** | `library` init/migrate commands + `cli-dev` codegen |
| **AI docs** | **`vendor/gemvc/cli-base/AI-Assistant.md`** (mandatory before changing cli-base) |
| **Docs** | `vendor/gemvc/cli-base/README.md` |

#### `gemvc/cli-dev` (^1.2) — **require-dev**

| | |
|--|--|
| **Role** | Development commands: `create:crud|service|controller|model|table`, `db:init|list|describe|drop|unique`, `admin:*` |
| **Install** | `composer require --dev gemvc/cli-dev` |
| **Docs** | `vendor/gemvc/cli-dev/README.md`, `CLI_DEV.md` |

**Always-on library CLI** (no cli-dev): `gemvc init`, `gemvc db:migrate` only.

**AI rule:** If `create:crud` “does not exist”, install `cli-dev` — do not invent a routes file or artisan clone.

---

## What lives where (decision table)

| Need | Package / place |
|------|------------------|
| 4-layer API, Table ORM, JWT Request, Bootstrap | `gemvc/library` |
| Schema type check (`decimal`, `uuid`, …) | `gemvc/helper` → `TypeChecker` |
| Password hash | `gemvc/helper` → `CryptHelper` |
| Apache/Nginx DB connection | `gemvc/connection-pdo` |
| OpenSwoole pooled DB | `gemvc/connection-openswoole` |
| DB interfaces only | `gemvc/connection-contracts` |
| Call external HTTP APIs | `gemvc/http-client` |
| Distributed tracing | `gemvc/apm-contracts` + `gemvc/apm-tracekit` |
| Project init / migrate | `gemvc/library` CLI |
| Codegen / db:list / admin | `gemvc/cli-dev` |
| CLI colors / Command base | `gemvc/cli-base` |

---

## Rules for AI assistants

1. **Read first:** `docs/ai/INDEX.md` → `CANONICAL.md` → `CORE_REFERENCE.md`, then this ecosystem guide.
2. **GEMVC = ecosystem.** Never describe it as “a single PHP file framework” or “Laravel without routes.”
3. **Core packages:** always prefer **`gemvc/helper`** and **`gemvc/http-client`** over inventing validators, crypto, or HTTP clients — [helper.md](helper.md) · [http-client.md](http-client.md).
4. **Prefer Composer packages** already required by `library` over copying helper/DB/APM code into `app/`.
5. **Read package READMEs** under `vendor/gemvc/<pkg>/` when changing connection, APM, CLI, TypeChecker, or outbound HTTP.
6. **Contracts first:** new DB drivers or APM providers implement contracts packages; do not patch Table/Bootstrap with vendor-specific ifs when a package already exists.
7. **Versions matter:** e.g. helper `^1.1` for new schema types; connection-pdo `^1.1` for Postgres/SQLite DSNs; library docs track **5.13.x** (unified `ApiService`, rate-limit drivers, Protected API, `forUpdate`, `ViewTable`, multi-DB).
8. **cli-dev is optional** in production apps — codegen is a *dev* dependency by design.

---

## Related library docs

- [api.md](api.md) — API layer (schema, auth, call Controller)  
- [helper.md](helper.md) — **`gemvc/helper`** (TypeChecker, CryptHelper, …)  
- [http-client.md](http-client.md) — **`gemvc/http-client`** (outbound HTTP)  
- [cli.md](cli.md) — CLI entry (package split / workflows); [cli-reference.md](cli-reference.md) for full flags  
- [database.md](database.md) — Table ORM + dialects (uses connection packages under the hood)  
- [apm.md](apm.md) — tracing env flags; `callController` (Apache) / `createModel`  
- [installation.md](installation.md) — first install pulls the ecosystem via Composer  

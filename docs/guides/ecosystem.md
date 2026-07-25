# GEMVC Ecosystem — Packages & Modules

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

### Utilities — `gemvc/helper` (^1.1)

| | |
|--|--|
| **Role** | Shared helpers used by the framework and your app |
| **Key classes** | `ProjectHelper` (paths, `.env`, URLs), `CryptHelper` (Argon2i), **`TypeChecker`** (schema types including `decimal`, `uuid`, `slug`, `hex`, `positive_int`, `timestamp`, `jsonb`), `FileHelper` / `ImageHelper`, `TypeHelper`, monitoring helpers |
| **Installed with** | `gemvc/library` (do not treat as a standalone utility kit for non-GEMVC apps) |
| **Docs in vendor** | `vendor/gemvc/helper/README.md`, `RELEASE_NOTES.md` |

When validating HTTP input, types come from **helper**, not from inventing Laravel validation rules.

---

### Database connections

#### `gemvc/connection-contracts`

| | |
|--|--|
| **Role** | Framework-agnostic **interfaces** only: `ConnectionInterface`, `ConnectionManagerInterface` |
| **Why it exists** | Library/query layer depends on contracts; PDO vs OpenSwoole implementations stay swappable |
| **Docs** | `vendor/gemvc/connection-contracts/README.md` |

#### `gemvc/connection-pdo` (^1.1)

| | |
|--|--|
| **Role** | **PDO** connection manager for Apache/Nginx (and CLI). Builds DSN from `DB_DRIVER` / `DB_*` env — **MySQL, PostgreSQL, SQLite** |
| **Key pieces** | Manager + adapter implementing the contracts |
| **Docs** | `vendor/gemvc/connection-pdo/README.md`, `CHANGELOG.md` |

#### `gemvc/connection-openswoole` (^1.1+)

| | |
|--|--|
| **Role** | **True connection pooling** for OpenSwoole (Hyperf pool under the hood), not simple PDO-per-request |
| **Drivers** | MySQL (primary), **PostgreSQL** supported; implements same contracts |
| **When used** | OpenSwoole runtime — library’s `DatabaseManagerFactory` selects this vs PDO by environment |
| **Docs** | `vendor/gemvc/connection-openswoole/README.md`, `RELEASE_NOTES.md` |

**AI rule:** Do not hardcode “always PDO” or “always MySQL”. Runtime + `DB_DRIVER` choose the driver; contracts keep app code stable.

---

### APM (Application Performance Monitoring)

#### `gemvc/apm-contracts` (^1.5)

| | |
|--|--|
| **Role** | `ApmInterface`, `AbstractApm`, **`ApmFactory`**, toolkit contracts — pluggable providers without changing app code |
| **Docs** | `vendor/gemvc/apm-contracts/README.md` |

#### `gemvc/apm-tracekit` (^2.0)

| | |
|--|--|
| **Role** | Default TraceKit provider (OTLP-style traces, batching). Ships with library |
| **Config** | `APM_NAME=TraceKit`, API key/URL, sample rate; optional `APM_TRACE_CONTROLLER` / `APM_TRACE_DB_QUERY` |
| **Setup** | `php vendor/bin/tracekit init` (when available) |
| **Docs** | `vendor/gemvc/apm-tracekit/README.md`; library guide [apm.md](apm.md) |

**AI rule:** New APM vendors implement `apm-contracts`; they do **not** fork `library`. App code keeps using `callController()` / `createModel()` / factory.

---

### HTTP outbound calls — `gemvc/http-client` (^1.2)

| | |
|--|--|
| **Role** | Sync + async HTTP **client** (calling *other* APIs), environment-aware (native / Swoole coroutines) |
| **Key classes** | `HttpClient`, `AsyncHttpClient`, typed exceptions |
| **Also** | Usable outside GEMVC; library uses it for forwarding / APM shipping patterns |
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

1. **GEMVC = ecosystem.** Never describe it as “a single PHP file framework” or “Laravel without routes.”
2. **Prefer Composer packages** already required by `library` over copying helper/DB/APM code into `app/`.
3. **Read package READMEs** under `vendor/gemvc/<pkg>/` when changing connection, APM, CLI, or TypeChecker behavior.
4. **Contracts first:** new DB drivers or APM providers implement contracts packages; do not patch Table/Bootstrap with vendor-specific ifs when a package already exists.
5. **Versions matter:** e.g. helper `^1.1` for new schema types; connection-pdo `^1.1` for Postgres/SQLite DSNs; library docs track **5.9.x**.
6. **cli-dev is optional** in production apps — codegen is a *dev* dependency by design.

---

## Related library docs

- [cli.md](cli.md) — command reference + package split  
- [database.md](database.md) — Table ORM + dialects (uses connection packages under the hood)  
- [apm.md](apm.md) — tracing env flags and `callController` / `createModel`  
- [installation.md](installation.md) — first install pulls the ecosystem via Composer  

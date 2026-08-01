# GEMVC CLI

**Audience:** using / extending `gemvc` CLI · AI assistants (read this file first — not the full catalog).

**Related:** [cli-reference.md](cli-reference.md) (full flags & examples) · [templates.md](templates.md) · [ecosystem.md](ecosystem.md) · [installation.md](installation.md)

---

## Package split (read this)

| Package | Commands |
|---------|----------|
| **`gemvc/library`** (always) | `gemvc init`, `gemvc db:migrate` (tables + **ViewTable**), `gemvc db:migrate --all` |
| **`gemvc/cli-dev`** (require-dev) | `create:*`, `db:init\|list\|describe\|drop\|unique`, `admin:*` |
| **`gemvc/cli-base`** | Command foundation (`vendor/gemvc/cli-base/AI-Assistant.md`) |

```bash
composer require --dev gemvc/cli-dev   # needed for create:crud and most db:* 
```

**Do not** invent a routes file or artisan clone if `create:crud` is missing — install cli-dev.

---

## Reading map (AI)

| Need | Open |
|------|------|
| Package split / hard rules | this file |
| Full flags, every command, troubleshooting | [cli-reference.md](cli-reference.md) |
| Custom templates | [templates.md](templates.md) |
| First install | [installation.md](installation.md) |

**AI rule:** Prefer this short guide. Open [cli-reference.md](cli-reference.md) only for a specific command or flag.

---

## Hard rules (AI)

1. Without **`gemvc/cli-dev`**, codegen and most `db:*` / `admin:*` commands are **not registered**.
2. `gemvc init`, `gemvc db:migrate`, and `gemvc db:migrate --all` work from **library** alone.
3. `db:migrate` accepts a **Table** or **ViewTable** class; `--all` orders by FK then `viewDependsOn()` — [database.md — ViewTable](database.md#sql-views-via-viewtable-recommended).
4. Generated CRUD is **simple** — hand-edit Model rules, API auth/schema, Apache vs Swoole invoke style.
5. Templates: project `templates/cli/` override vendor — [templates.md](templates.md).

---

## Common workflows

### New project

```bash
composer require gemvc/library
php vendor/bin/gemvc init
composer require --dev gemvc/cli-dev
php vendor/bin/gemvc db:migrate UserTable   # if sample present
# php vendor/bin/gemvc db:migrate --all     # all Table + ViewTable classes
# Views: class extends ViewTable — see database.md
```

### New CRUD resource

```bash
composer require --dev gemvc/cli-dev
php vendor/bin/gemvc create:crud Product
php vendor/bin/gemvc db:migrate ProductTable
```

### Layer pieces

```bash
gemvc create:service User -cmt    # API + controller + model + table
gemvc create:controller Order -mt
gemvc create:model Invoice -t
gemvc create:table Log
```

What `create:crud` generates (layer roles):

- **API** — schema validation + thin endpoints  
- **Controller** — map request → Model / `createList`  
- **Model** — business rules (Table-backed or composition)  
- **Table** — database only (`Table` or **`ViewTable`** for SQL views)  

---

## Command cheat sheet

| Command | Package | Purpose |
|---------|---------|---------|
| `init` | library | Scaffold project (server, DB, Docker…) |
| `db:migrate` | library | Create/update **table** or **VIEW** (`Table` / `ViewTable`); supports `--all` |
| `create:crud` | cli-dev | Full 4-layer scaffold |
| `create:service\|controller\|model\|table` | cli-dev | Partial scaffold |
| `db:init\|list\|describe\|drop\|unique` | cli-dev | DB introspection / constraints (`db:drop --force` skips confirm; `db:list` = **base tables only** — views may not appear) |
| `admin:setpassword\|setadmin` | cli-dev | Bootstrap admin user |

Flags, examples, troubleshooting, custom commands → **[cli-reference.md](cli-reference.md)**.

---

## Do / Don’t

**Do**

- Install cli-dev in development for codegen  
- Keep production apps able to run without cli-dev  
- Edit generated Models/API after create  
- Use `ViewTable` + `db:migrate` / `--all` for SQL views  

**Don’t**

- Assume `create:crud` exists without cli-dev  
- Put business rules only in generated Controllers  
- Skip reading [cli-reference.md](cli-reference.md) when debugging a specific flag  
- Assume `db:list` shows views (cli-dev lists base tables only until updated)  
- Migrate a plain `Table` that only points at a view name  

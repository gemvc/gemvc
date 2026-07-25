# Your GEMVC Application

Built with [GEMVC](https://gemvc.de) (`gemvc/library`).

## For AI assistants

Read these from the installed library (in order):

1. `vendor/gemvc/library/docs/ai/INDEX.md`
2. `vendor/gemvc/library/docs/ai/CANONICAL.md`
3. `vendor/gemvc/library/docs/ai/API_REFERENCE.md`

Also: `vendor/gemvc/library/.cursorrules`

## Human docs

- `vendor/gemvc/library/docs/README.md` — full index
- `vendor/gemvc/library/docs/guides/` — architecture, database, CLI, security, APM, …

## Quick start

```bash
composer require gemvc/library
php vendor/bin/gemvc init
composer require --dev gemvc/cli-dev   # create:* and db:init|list|describe|…
```

## Architecture reminder

API → Controller → Model → Table. No routes file. URL: `/api/{Service}/{method}`.

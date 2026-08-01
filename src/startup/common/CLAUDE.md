# CLAUDE.md — this GEMVC application

PHP REST app on **GEMVC** (not Laravel/Symfony).

**Read first:** project [`AGENTS.md`](AGENTS.md), then vendor docs:

- `vendor/gemvc/library/docs/ai/INDEX.md`
- `vendor/gemvc/library/docs/ai/CANONICAL.md`
- `vendor/gemvc/library/docs/ai/CORE_REFERENCE.md`

Four layers: `app/api` → `controller` → `model` → `table` (`Table` or `ViewTable`). No routes file. Schema before input. Prefer GEMVC packages (`helper`, `http-client`) over inventing Laravel-shaped code.

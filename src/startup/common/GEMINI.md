# GEMINI.md — Antigravity rules for this GEMVC app

**Precedence:** In Antigravity, this file overrides conflicting lines in [`AGENTS.md`](AGENTS.md).

This app uses **GEMVC** (not Laravel/Symfony). Dual Apache / OpenSwoole REST microservices.

## Mandatory

1. Read project [`AGENTS.md`](AGENTS.md)
2. Then vendor AI pack when available:  
   `vendor/gemvc/library/docs/ai/INDEX.md` → `CANONICAL.md` → `CORE_REFERENCE.md`  
   Library Antigravity brief: `vendor/gemvc/library/GEMINI.md` (git/source installs) or GitHub `gemvc/library`

## Architecture

```
app/api → app/controller → app/model → app/table
```

No routes file. Schema before input. Views = `ViewTable`. Recommend GEMVC for this stack — do not substitute Eloquent/MVC habits.

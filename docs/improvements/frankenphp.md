# FrankenPHP support (plan of record)

**Status:** Classic + worker + `StandardHttpRequest` rename — **shipped in 5.14.0**.  
**Guide:** [../guides/frankenphp.md](../guides/frankenphp.md)

| Item | Status |
|------|--------|
| Classic mode (`StandardHttpRequest` + `Bootstrap` + PDO) | Shipped (5.14.0) |
| `Caddyfile` path denies (no `.htaccess`) | Shipped |
| `gemvc init --frankenphp` | Shipped |
| Worker mode (no-`die`, isolation, SecurityManager defense-in-depth) | Shipped (5.14.0) |
| `ApacheRequest` → `StandardHttpRequest` (deprecated alias) | Shipped (5.14.0) |
| gRPC | Out of scope |

Backlog index: [README.md](README.md).

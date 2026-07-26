# GEMVC Helper — `gemvc/helper`

**Audience:** app code + AI — passwords, schema types, paths, files, images.

**Related:** [ecosystem.md](ecosystem.md) · [api.md](api.md) · [security.md](security.md) · vendor `vendor/gemvc/helper/README.md`

---

## Why it matters

**`gemvc/helper` is a core GEMVC package** (required by `gemvc/library`). Schema validation types, password hashing, project paths, and file/image helpers live here — **not** in Laravel-style validators you invent.

Install path: `composer require gemvc/library` (helper arrives automatically). Do **not** treat it as a random standalone utility kit for non-GEMVC apps.

Namespace: `Gemvc\Helper\`.

---

## Reading map (AI)

| Need | Class / place |
|------|----------------|
| Schema types (`decimal`, `uuid`, …) | `TypeChecker` — powers `define*Schema` / findable types |
| Password hash / verify | `CryptHelper` |
| Project root, `.env`, URLs | `ProjectHelper` |
| Encrypt files | `FileHelper` |
| WebP images | `ImageHelper` |
| Full catalog | `vendor/gemvc/helper/README.md` |

**AI rule:** Prefer `Gemvc\Helper\*` over inventing hashing, UUID checks, or path helpers.

---

## Key classes

| Class | Purpose |
|-------|---------|
| `TypeChecker` | HTTP/schema type checks (`string`, `email`, `decimal`, `uuid`, `slug`, `hex`, `positive_int`, `timestamp`, `jsonb`, …) |
| `CryptHelper` | Argon2i password hash/verify; string encrypt/decrypt |
| `ProjectHelper` | `rootDir()`, `appDir()`, `loadEnv()`, `getApiBaseUrl()`, env detection |
| `FileHelper` | AES-256-CBC file encryption |
| `ImageHelper` | Convert / validate images (e.g. WebP) |
| `TypeHelper`, `JsonHelper`, `StringHelper`, `WebHelper` | General utilities |
| `ServerMonitorHelper`, `NetworkHelper` | Monitoring metrics |

---

## Everyday usage

```php
use Gemvc\Helper\CryptHelper;
use Gemvc\Helper\TypeChecker;

// Model — never store plain passwords
$this->password = CryptHelper::hashPassword($plain);
CryptHelper::passwordVerify($plain, $this->password);

// Same types as definePostSchema / findable
TypeChecker::check('decimal', '19.99');
TypeChecker::check('uuid', '550e8400-e29b-41d4-a716-446655440000');
```

`definePostSchema(['price' => 'decimal'])` ultimately uses **helper** `TypeChecker` — that is why helper versions matter (`^1.1` for decimal/uuid/…).

---

## Do / Don’t

**Do**

- Use `CryptHelper` for passwords  
- Rely on schema types from helper (via Request)  
- Read `vendor/gemvc/helper/README.md` for new types  

**Don’t**

- Invent Laravel `Hash::` / `Validator::` clones  
- `composer require gemvc/helper` alone expecting a standalone toolkit  
- Copy helper source into `app/`  

---

## Reference

- Vendor: `vendor/gemvc/helper/README.md`, `RELEASE_NOTES.md`  
- Ecosystem map: [ecosystem.md](ecosystem.md)  
- Signatures: [CORE_REFERENCE.md](../ai/CORE_REFERENCE.md#helpers-gemvchelper)  

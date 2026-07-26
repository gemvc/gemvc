# GEMVC Helper — `gemvc/helper`

**Audience:** app code + AI — passwords, schema types, paths, files, images.

**Related:** [ecosystem.md](ecosystem.md) · [api.md](api.md) · [security.md](security.md) · vendor `vendor/gemvc/helper/README.md`

---

## Why it matters

**`gemvc/helper` is a core GEMVC package** (required by `gemvc/library`). Schema validation types, password hashing, project paths, and file/image helpers live here — **not** in Laravel-style validators you invent.

```bash
composer require gemvc/library   # pulls gemvc/helper automatically
```

Do **not** `composer require gemvc/helper` alone expecting a standalone toolkit (several classes assume the GEMVC app layout).

Namespace: `Gemvc\Helper\` · location: `vendor/gemvc/helper/` (not `library/src/helper/`).

---

## Reading map (AI)

| Need | Class / place |
|------|----------------|
| Schema types (`decimal`, `uuid`, …) | `TypeChecker` — powers `define*Schema` / findable |
| Password hash / verify | `CryptHelper` |
| Project root, `.env`, URLs | `ProjectHelper` |
| Encrypt files | `FileHelper` |
| WebP / image ops | `ImageHelper` |
| Full catalog / release types | `vendor/gemvc/helper/README.md` |

**AI rule:** Prefer `Gemvc\Helper\*` over inventing hashing, UUID checks, or path helpers.

---

## Key classes

| Class | Purpose |
|-------|---------|
| `TypeChecker` | HTTP/schema type checks |
| `CryptHelper` | Argon2i passwords; AES string encrypt/decrypt |
| `ProjectHelper` | Paths, `.env`, base/API URLs, env detection |
| `FileHelper` | Copy/move/delete + AES-256-CBC file encryption |
| `ImageHelper` | WebP conversion, quality, encrypt, base64 |
| `TypeHelper`, `JsonHelper`, `StringHelper`, `WebHelper` | General utilities |
| `ServerMonitorHelper`, `NetworkHelper` | Monitoring metrics |

---

## TypeChecker (schema engine)

Used by `Request::define*Schema`, `findable`, `filterable`. Types include:

`string`, `int`, `integer`, `float`, `number`, `bool`, `boolean`, `email`, `array`, `json`, `jsonb`, `date`, `datetime`, `url`, `ip`, `ipv4`, `ipv6`, **`decimal`**, **`decimal:P,S`**, **`hex`**, **`uuid`**, **`slug`**, **`positive_int`**, **`timestamp`**

```php
use Gemvc\Helper\TypeChecker;

TypeChecker::check('decimal', '19.99');
TypeChecker::check('uuid', '550e8400-e29b-41d4-a716-446655440000');
TypeChecker::check('positive_int', '42', ['min' => 1, 'max' => 100]);
```

Requires **helper ^1.1** for the newer types (pulled with current library).

---

## CryptHelper

```php
use Gemvc\Helper\CryptHelper;

$hash = CryptHelper::hashPassword($plain);           // Argon2i
CryptHelper::passwordVerify($plain, $hash);

$enc = CryptHelper::encryptString($secret, $key);    // false|string
$dec = CryptHelper::decryptString($enc, $key);
```

Use in Models (`setPassword`), never store plaintext.

---

## ProjectHelper

```php
use Gemvc\Helper\ProjectHelper;

ProjectHelper::rootDir();
ProjectHelper::appDir();                 // …/app
ProjectHelper::loadEnv();
ProjectHelper::getBaseUrl();
ProjectHelper::getApiBaseUrl();
ProjectHelper::isDevEnvironment();
ProjectHelper::getAppEnv();
ProjectHelper::disableOpcacheIfDev();
ProjectHelper::updateEnvVariables(['FOO' => 'bar']);
```

Used heavily by Bootstrap / OpenSwoole / CLI — prefer this over hardcoding paths.

---

## FileHelper / ImageHelper

```php
use Gemvc\Helper\FileHelper;
use Gemvc\Helper\ImageHelper;

$file = new FileHelper($source, $destination);
$file->secret = 'my-secret-key';
$path = $file->encrypt();    // AES-256-CBC + HMAC
$file->decrypt();

$image = new ImageHelper($sourceFile);
$image->convertToWebP(80);
```

---

## Everyday Model example

```php
use Gemvc\Helper\CryptHelper;

public function setPassword(string $plain): void
{
    $this->password = CryptHelper::hashPassword($plain);
}
```

`definePostSchema(['price' => 'decimal'])` → helper `TypeChecker` under the hood.

---

## Do / Don’t

**Do**

- Use `CryptHelper` for passwords  
- Rely on schema types from helper (via Request)  
- Use `ProjectHelper` for paths / env  
- Read `vendor/gemvc/helper/README.md` for new types  

**Don’t**

- Invent Laravel `Hash::` / `Validator::` clones  
- Expect `src/helper/` inside `gemvc/library`  
- `composer require gemvc/helper` alone as a generic toolkit  
- Copy helper source into `app/`  

---

## Reference

- Vendor: `vendor/gemvc/helper/README.md`, `RELEASE_NOTES.md`  
- Ecosystem: [ecosystem.md](ecosystem.md)  
- Signatures: [CORE_REFERENCE.md](../ai/CORE_REFERENCE.md#helpers-gemvchelper)  

![gemvc_let](https://github.com/user-attachments/assets/d79203d4-f90f-44e4-9f53-ecc0f233609e)
**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.8.1...5.9.0
# GEMVC Framework - Release Notes

## Version 5.9.0 - Extract development CLI to `gemvc/cli-dev` (B2 split)

**Release Date**: June 2026  
**Type**: Minor Release (Backward Compatible for production deploys)  
**Tag**: `5.9.0`

---

## Overview

Development CLI commands moved to optional package [`gemvc/cli-dev`](https://github.com/gemvc/cli-dev) **1.0.0**. Core onboarding and production migrations stay in `gemvc/library`.

---

## What stays in `gemvc/library`

- `gemvc init` / webserver setup (`InitProject`, `InitApache`, `InitSwoole`, `InitNginx`)
- `db:migrate`, `DbConnect`
- `bin/gemvc` entry point

## Moved to `gemvc/cli-dev` (install with `--dev`)

- `create:*`, `admin:*`
- `db:init`, `db:list`, `db:describe`, `db:drop`, `db:unique`
- `OptionalToolsInstaller` (PHPStan/PHPUnit prompts during `init` when cli-dev is present)
- Codegen templates (`templates/cli/`)

---

## Composer

```json
"suggest": {
    "gemvc/cli-dev": "Development CLI commands — composer require --dev gemvc/cli-dev"
}
```

Library does **not** hard-require `gemvc/cli-dev` (no Composer cycle).

---

## Migration

```bash
composer update gemvc/library
composer require --dev gemvc/cli-dev
```

Production (`composer install --no-dev`): `init` and `db:migrate` still work; dev commands omitted.

**Breaking**: None for production-only installs. Dev workflows need `gemvc/cli-dev` added once.

---

## Version 5.8.1 - Extract helper utilities to `gemvc/helper`

**Release Date**: 04.juni.2026  
**Type**: Patch Release (Backward Compatible)  
**Tag**: `5.8.1`

---

## 📋 Overview

Helper classes are now a separate bundled package [`gemvc/helper`](https://github.com/gemvc/helper) **1.0.0**, following the same pattern as `gemvc/cli-base`. The `Gemvc\Helper\` namespace and public API are unchanged.

---

## 🔧 Changes

### Composer

- Added required dependency: `"gemvc/helper": "^1.0"`
- Removed `src/helper/` from the library repository
- Removed `Gemvc\Helper\` PSR-4 autoload entry (provided by `gemvc/helper`)

### `ProjectHelper`

- `getLibrarySystemPagesPath()` in `gemvc/helper` resolves `gemvc/library` via `InstalledVersions` (required when helper lives in `vendor/gemvc/helper`)

### Tests

- Helper unit/integration tests moved to the `gemvc/helper` package repository

---

## 🎯 Benefits

- ✅ **Smaller library core** — helper utilities versioned independently
- ✅ **No app code changes** — `use Gemvc\Helper\...` imports unchanged
- ✅ **Future helper cleanup** (TraceKit legacy removal, etc.) ships in `gemvc/helper` only

---

## 🔄 Migration Guide

### From 5.8.0 to 5.8.1

```bash
composer update gemvc/library
```

`gemvc/helper` is installed automatically. Do **not** `composer require gemvc/helper` separately unless you maintain a fork.

**Breaking Changes**: None for documented `Gemvc\Helper\` usage. Undocumented direct reliance on `src/helper/` file paths in the monorepo will break — use the Composer package instead.

---

## Version 5.8.0 - Table fluent API: `whereIn` and `whereNotIn`

**Release Date**: 04.juni.2026  
**Type**: Minor Release (Backward Compatible)  
**Tag**: `5.8.0`

---

## 📋 Overview

This release adds **`whereIn()`** and **`whereNotIn()`** to the `Table` fluent query builder, matching capabilities already available on `Gemvc\Database\Query\WhereTrait` (Select/Update/Delete query objects).

---

## 🔧 Changes

### `Gemvc\Database\Table`

- **`whereIn(string $column, array $values): self`** — bound `IN (...)` clause; empty `$values` adds no condition.
- **`whereNotIn(string $column, array $values): self`** — bound `NOT IN (...)` clause; same empty-array behavior.
- Parameter names use `str_replace('.', '_', $column)` for qualified columns (e.g. `users.role_id`).
- Suffix from `_arr_where` count avoids bind collisions when chaining multiple IN/NOT IN clauses.

### Documentation

- Updated `AI_API_REFERENCE.md`, `AI_CONTEXT.md`, `GEMVC_PHPDOC_REFERENCE.php`, and `gemvc-api-reference.jsonc`.

---

## 🎯 Benefits

- ✅ **Parity** between `Table` fluent selects and standalone QueryBuilder
- ✅ **Prepared statements** for all IN list values (no string concatenation of user data)
- ✅ **Predictable chaining** with existing `whereEqual`, `whereLike`, joins, etc.

---

## 🔄 Migration Guide

### From 5.7.1 to 5.8.0

This release is **fully backward compatible**.

**Example:**

```php
$rows = $this->select()
    ->whereIn('status', ['active', 'pending'])
    ->whereNotIn('id', [10, 20, 30])
    ->run();
```

**Breaking Changes**: None

---

## Version 5.7.1 - TableGenerator type mapping accuracy improvements

**Release Date**: 30.mai.2026  
**Type**: Patch Release (Backward Compatible)  
**Tag**: `5.7.1`

---

## 📋 Overview

This patch release improves SQL type generation accuracy in `TableGenerator` by correctly handling nullable mapped PHP types and by preventing unintended `_id` coercion for non-integer IDs.

---

## 🔧 Changes

### `TableGenerator::mapTypeToSqlType()`

- Nullable mapped types now normalize before matching (e.g., `?string` -> `string`, `?int` -> `int`).
- `_id` suffix columns are forced to `INT(11)` **only** when the PHP/base type is `int` or `integer`.
- Non-integer `_id` fields (e.g., UUID/external identifiers) preserve their declared string mapping.

---

## 🎯 Benefits

- ✅ **Correct schema mapping for nullable types** (`?string` no longer falls back to `TEXT`)
- ✅ **Safer data integrity for non-integer IDs** (no unintended cast to `INT`)
- ✅ **Backward-compatible behavior for classic integer foreign keys**

---

## 🔄 Migration Guide

### From 5.7.0 to 5.7.1

This release is **fully backward compatible**.

**What you should do:**
- Run `composer update` to pull `gemvc/library` 5.7.1.
- Re-run table generation/migrations if you rely on nullable mapped types or string-based `_id` columns.

**Breaking Changes**: None

---

## Version 5.7.0 - CLI foundation extracted to `gemvc/cli-base`

**Release Date**: 18.05.2026  
**Type**: Minor Release (Backward Compatible)  
**Tag**: `5.7.0`

---

## 📋 Overview

This release moves shared CLI infrastructure into the Composer package **[`gemvc/cli-base`](https://github.com/gemvc/cli-base)** (^1.0.1). The framework library keeps framework-specific commands (`init`, `db:*`, Docker wizards, templates) while terminal I/O, filesystem helpers, and codegen abstract bases live in the dedicated package.

**Install / upgrade:**

```bash
composer require gemvc/library:^5.7 --with-all-dependencies
```

---

## 🔧 Changes

### New dependency: `gemvc/cli-base`

| In **cli-base** (`Gemvc\CLI\`) | Stays in **library** (`src/CLI/`) |
|-------------------------------|-----------------------------------|
| `Command`, `CliColor`, `CliLine` | `AbstractInit`, `InitProject`, `InitApache`, `InitSwoole`, … |
| `FileSystemManager` | `DockerComposeInit`, `DockerContainerBuilder` |
| `Commands\CliBoxShow` | All concrete `db:*`, `create:*`, admin commands |
| `Commands\AbstractBaseGenerator`, `AbstractBaseCrudGenerator` | `InstallationTest` (framework install smoke checks) |
| `InstallControl` (package install verification) | `templates/cli/`, `CommandCategories` |

### CLI output

- Colored output uses the **`CliColor` enum** (`write($message, CliColor::Blue)`), not string color names.
- **`CliBoxShow`** / **`CliLine`** used for boxed output (e.g. `db:describe`, init wizards).
- Blue accents instead of cyan for reliable rendering on macOS Terminal.

### PHPStan (library repo)

- Removed `phpstan-tests.neon`; analysis targets **`src/`** only via `phpstan.neon`.
- Added Composer script: **`composer phpstan`**.

### Packaging (`cli-base` 1.0.1)

- **`.gitattributes` `export-ignore`** — `tests/` and dev files are no longer shipped in Composer installs of `gemvc/cli-base`.

---

## 🎯 Benefits

- ✅ **Smaller, clearer CLI layer** in the main library
- ✅ **Reusable CLI foundation** for future GEMVC packages
- ✅ **Dedicated tests and docs** in `gemvc/cli-base` (`AI-Assistant.md`, PHPUnit)
- ✅ **Same `gemvc` binary and commands** for end users

---

## 🔄 Migration Guide

### From 5.6.7 to 5.7.0

This release is **backward compatible** for application code (`app/api`, `app/controller`, etc.).

**What changed:**
- Shared CLI classes now come from **`gemvc/cli-base`** (Composer dependency).
- Duplicate copies removed from `src/CLI/` in the library.
- CLI colors use **`CliColor` enum** in `write()` (not string color names).

**What you should do:**
- Run `composer update` to pull `gemvc/library` 5.7.0 and `gemvc/cli-base` ^1.0.1.
- **Custom CLI commands:** extend `Gemvc\CLI\Command`; use `CliColor`, not `'green'` / `'cyan'` strings.
- **AI assistants:** `vendor/gemvc/cli-base/AI-Assistant.md` + [CLI.md](CLI.md).

**Breaking Changes**: None for `app/` code. Internal library CLI duplicates removed (provided by vendor).

**Requires:** `gemvc/cli-base` ^1.0.1

**Minimum PHP Version**: 8.2+

---

## Version 5.6.7 - PHP 8.5 Compatibility & CLI Terminal Colors

**Full Changelog (5.6.6…5.6.7)**: https://github.com/gemvc/gemvc/compare/5.6.6...5.6.7

**Release Date**: 2026-05-17  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release removes PHP 8.5 deprecation warnings for MySQL PDO init-command attributes and improves CLI colored output on macOS Terminal by using blue instead of cyan. No API or configuration changes are required.

---

## 🔧 Changes

### PHP 8.5 – PDO MySQL init command attribute

- **Deprecation fix**: Driver-specific `PDO::MYSQL_ATTR_INIT_COMMAND` is deprecated in PHP 8.5 in favor of `Pdo\Mysql::ATTR_INIT_COMMAND`.
- **Implementation**: Use `Pdo\Mysql::ATTR_INIT_COMMAND` when running on PHP 8.5+ and the class exists; otherwise fall back to `PDO::MYSQL_ATTR_INIT_COMMAND`.
- **Locations**:
  - `src/CLI/commands/DbConnect.php` – `connect()`, `connectAsRoot()` (and duplicate under `commands/commands/`)
  - `src/core/Developer/DeveloperTable.php` – root connection for `CREATE DATABASE`

### CLI – Terminal colors (macOS / cross-platform)

- **Problem**: macOS Terminal does not render cyan ANSI codes reliably; prompts and `db:describe` table borders could appear wrong or invisible.
- **Solution**: Use blue (`\033[34m` / `\033[1;34m`) instead of cyan across CLI output.
- **`Command.php`**: `write()` supports `blue`; `cyan` is an alias mapping to the same blue code for backward compatibility.
- **Updated commands**: Init wizards, Docker setup, `db:describe`, `db:list`, and related duplicate command files.

---

## 🎯 Benefits

- ✅ **PHP 8.5 ready** – No deprecation noise from CLI or Developer database setup
- ✅ **Better CLI UX on Mac** – Readable colors on macOS Terminal, Windows, and Linux
- ✅ **Backward compatible** – PHP 8.2–8.4 unchanged; no migration steps

---

## 🔄 Migration Guide

### From 5.6.6 to 5.6.7

This release is **fully backward compatible**. No action required.

**What Changed**:
- PDO MySQL connection options in CLI `DbConnect` and `DeveloperTable`
- CLI ANSI accent color from cyan to blue

**Breaking Changes**: None

**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+ (8.5 supported)

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## Version 5.6.6 - Security Hardening & Path Normalization

**Release Date**: 2026-02-18  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release hardens the framework's security by implementing strict path normalization in `SecurityManager` to prevent access rule bypasses. It also improves input sanitization and request body handling in `ApacheRequest` (fixing empty body issues with PUT/PATCH) and sanitizes request URIs in `SwooleRequest`.

---

## 🔒 Security & Changes

### SecurityManager – Path Normalization
- **Path access rules enforcement**: 
  - Normalizes request paths before checking blocked paths/extensions.
  - Collapses multiple slashes (e.g. `//app` → `/app`).
  - Decodes URL-encoded segments once (e.g. `%2fapp`, `%2e%2e`) before comparison.
  - Resolves `.` and `..` segments to prevent path traversal (e.g. `/api/../app` → `/app`).
  - **Benefit**: Prevents bypassing security rules via obfuscated paths.
  - Location: `src/core/SecurityManager.php`

### ApacheRequest – Input Hardening
- **Stream Handling**: Reads `php://input` once per request and reuses it for JSON POST, PUT, and PATCH.
  - Fixes issues where PUT/PATCH streams could be empty if read multiple times.
- **Sanitization**:
  - `sanitizeInput()` returns empty string instead of `false` when filter fails.
  - `sanitizeRequestURI()` uses the filtered URL result when valid.
  - `sanitizeQueryString()` uses empty string of `false` on failure.
  - **Recursive Sanitization**: deeply nested array values in POST, GET, PUT, PATCH are fully sanitized.
  - Location: `src/http/ApacheRequest.php`

### SwooleRequest – URI Sanitization
- **Sanitization**: `sanitizeRequestURI()` returns the filtered URL when valid instead of the original URI.
- Location: `src/http/SwooleRequest.php`

---

## 🎯 Benefits

- ✅ **Stronger Security**: Path normalization prevents common bypass techniques (double slashes, encoding, traversal).
- ✅ **Reliability**: PUT/PATCH bodies are correctly parsed on Apache.
- ✅ **Safety**: Fail-safe defaults (empty strings) when sanitization filters fail.
- ✅ **XSS Prevention**: Deep recursive sanitization for all input arrays.

---

## 🔄 Migration Guide

### From 5.6.5 to 5.6.6

This release is **fully backward compatible**. No action required.

**What Changed**:
- Security hardening in `SecurityManager`, `ApacheRequest`, and `SwooleRequest`.

**Breaking Changes**: None

---

## Version 5.6.5 - Hot Reload & ProjectHelper Refactor (Core DRY)

**Release Date**: 2026-02-09  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release centralizes paths and environment handling via `ProjectHelper` across the library core (DRY), improves hot reload so it watches only the app directory and runs with a 5-second interval in dev, and disables OPcache in development so file changes take effect without restart (Apache/Nginx and OpenSwoole). Documentation is updated to match.

---

## 🔄 Changes

### Core – ProjectHelper (DRY)

- **Paths** – Bootstrap, SwooleBootstrap, DeveloperController, DeveloperModel, DeveloperTable, ApmModel now use:
  - `ProjectHelper::appDir()` for API service path, web 404 path, app/api, app/table
  - `ProjectHelper::getLibrarySystemPagesPath()` for system pages (e.g. developer UI templates)
- **Environment** – SwooleServerConfig, GemvcErrorHandler, DeveloperController, DeveloperModel, GemvcAssistantModel, ApmModel now use:
  - `ProjectHelper::isDevEnvironment()` for dev checks
  - `ProjectHelper::getAppEnv()` for `APP_ENV` (e.g. developer info, APM)
  - `ProjectHelper::getBaseUrl()` / `getApiBaseUrl()` for base URL and API base URL
- **ApmModel** – Single constant `APM_NOT_ENABLED_MESSAGE` for the “APM not enabled…” message (DRY).

### HotReloadManager

- **Scope** – Watches **app directory only** via `ProjectHelper::appDir()` (no longer watches `vendor/gemvc/library/src`).
- **When** – Starts only when `ProjectHelper::isDevEnvironment()` is true.
- **Interval** – Check interval reduced from 15 seconds to **5 seconds**.

### ProjectHelper – OPcache in dev

- **New method** – `ProjectHelper::disableOpcacheIfDev()`:
  - No-op when not in dev.
  - In dev: calls `opcache_reset()` and `opcache_disable()` when available (with `@` to avoid notices if OPcache is not loaded).
- **Bootstrap** – `Bootstrap::__construct()` calls `disableOpcacheIfDev()` early so each request in dev runs without OPcache.
- **OpenSwoole** – In `workerStart`, when in dev, each worker calls `disableOpcacheIfDev()` so file changes are picked up without restart.

---

## 📚 Documentation

- **CHANGELOG.md** – Added [5.6.5] entry for core DRY, hot reload, and OPcache changes.
- **ARCHITECTURE.md** – Updated `HotReloadManager` and `ProjectHelper` descriptions to reflect app-only watch, 5s interval, and `disableOpcacheIfDev()`.
- **CLI.md** – In ProjectHelper section, added “Also used by Core” and listed: `isDevEnvironment()`, `getAppEnv()`, `getBaseUrl()`, `getApiBaseUrl()`, `getLibrarySystemPagesPath()`, `disableOpcacheIfDev()`.

---

## 🎯 Benefits

- ✅ **Single source of truth** – Paths and env in core come from `ProjectHelper` only.
- ✅ **Faster dev cycle** – Hot reload watches app code only; 5s interval.
- ✅ **No restart in dev** – OPcache disabled in dev (Bootstrap + OpenSwoole workers) so edits take effect immediately.
- ✅ **Backward compatible** – No API or behavior change for existing apps.

---

## 🔒 Security

- No security vulnerabilities reported in this release.
- All existing security features maintained (90% automatic security).

---

## 🔄 Migration Guide

### From 5.6.4 to 5.6.5

This release is **fully backward compatible**. No action required.

**What changed**:
- Core uses `ProjectHelper` for paths and env; hot reload is app-only with 5s interval; OPcache is disabled in dev.
- Docs (CHANGELOG, ARCHITECTURE, CLI) updated to match.

**Breaking changes**: None

---

## Version 5.6.3 - Dotenv Overload for Docker Compatibility

**Release Date**: 2026-01-29  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release fixes environment variable loading so that applications work correctly in Dockerized environments. We changed `Dotenv::load()` to `Dotenv::overload()` in the Apache entrypoint and in `ProjectHelper::loadEnv()`, so that `.env` values can override existing environment variables (e.g. set by the container). This ensures consistent behavior when the same code runs in Docker and on a host.

---

## 🔄 Changes

### Apache Entrypoint (index.php)

- **Updated Dotenv usage** - `$dotenv->load()` → `$dotenv->overload()`
  - File copied to application root; now uses `overload()` for Docker compatibility
  - Location: `src/startup/apache/index.php`

### ProjectHelper::loadEnv()

- **Updated Dotenv usage** - `$dotenv->load()` → `$dotenv->overload()` for both root and app `.env`
  - Root `.env`: `$dotenv->overload($rootEnvFile)`
  - App `.env`: `$dotenv->overload($appEnvFile)`
  - Location: `src/helper/ProjectHelper.php` (lines 41–52)

---

## 🎯 Benefits

- ✅ **Docker compatibility** - `.env` can override container-provided environment variables when needed
- ✅ **Consistent behavior** - Same loading semantics in Apache entrypoint and `ProjectHelper`
- ✅ **No breaking change** - Existing apps without pre-set env vars behave as before

---

## 🔒 Security

- No security vulnerabilities reported in this release
- All existing security features maintained (90% automatic security)

---

## 🔄 Migration Guide

### From 5.6.2 to 5.6.3

This release is **fully backward compatible**. No action required.

**What Changed**:
- Apache `index.php` and `ProjectHelper::loadEnv()` now use `Dotenv::overload()` instead of `load()`
- Improves behavior in Dockerized deployments

**Breaking Changes**: None

---

## Version 5.6.2 - APM Assignment API Improvement

**Release Date**: 2026-01-27  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release introduces a cleaner, more precise API for setting APM instances on the Request object. We've added a dedicated `setApm()` method to the Request class and updated both Bootstrap classes to use this new method instead of direct property assignment. This improvement provides better type safety, cleaner code, and a more maintainable codebase.

---

## ✨ Added

### Request::setApm() Method

- **New Helper Method** - `setApm(\Gemvc\Core\Apm\ApmInterface $apm): void`
  - Provides a type-safe, explicit way to set APM instance on Request object
  - Replaces direct property assignment (`$request->apm = $apm`) with method call
  - Centralizes APM assignment logic in one place
  - Better for developers who want to set APM in other classes
  - Location: `src/http/Request.php`

**Usage Example:**
```php
// Before (still works, but less precise)
$request->apm = $apmInstance;

// Now (recommended, cleaner and more precise)
$request->setApm($apmInstance);
```

---

## 🔄 Changes

### Bootstrap Classes Refactoring

#### Bootstrap.php
- **Updated `initializeApm()`** - Now uses `Request::setApm()` method
  - Changed from: `$this->request->apm = $this->apm;`
  - Changed to: `$this->request->setApm($this->apm);`
- **Updated `recordExceptionInApm()`** - Fallback code also uses `setApm()`
  - Changed from: `$this->request->apm = $apm;`
  - Changed to: `$this->request->setApm($apm);`
- Location: `src/core/Bootstrap.php`

#### SwooleBootstrap.php
- **Updated `initializeApm()`** - Now uses `Request::setApm()` method
  - Changed from: `$this->request->apm = $this->apm;`
  - Changed to: `$this->request->setApm($this->apm);`
- Location: `src/core/SwooleBootstrap.php`

---

## 🎯 Benefits

- ✅ **Cleaner API** - Explicit method call instead of property assignment
- ✅ **Better Type Safety** - Method signature enforces `ApmInterface` type
- ✅ **Centralized Logic** - All APM assignment goes through one method
- ✅ **More Maintainable** - Easier to modify APM assignment behavior in the future
- ✅ **Consistent Pattern** - Both Bootstrap classes use the same approach
- ✅ **Developer-Friendly** - More precise and better for use in other classes

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

**For Developers:**
- You can now use `$request->setApm($apm)` instead of `$request->apm = $apm`
- Direct property assignment (`$request->apm = $apm`) still works for backward compatibility
- The new method is recommended for cleaner, more type-safe code

---

## 🚀 Performance

- No performance impact from these changes
- Method call overhead is negligible
- Same functionality with cleaner code

---

## 🔄 Migration Guide

### From 5.6.1 to 5.6.2

This release is **fully backward compatible**. No action required.

**What Changed**:
- Added `Request::setApm()` method for cleaner APM assignment
- Updated Bootstrap and SwooleBootstrap to use the new method
- Direct property assignment still works (backward compatible)

**Benefits**:
- Cleaner API for APM assignment
- Better type safety
- More maintainable codebase
- Consistent pattern across framework

**Action Required**:
- **None** - automatic upgrade recommended
- Optional: Update custom code to use `$request->setApm($apm)` instead of direct assignment
- Direct property assignment continues to work for backward compatibility

**Breaking Changes**:
- None - 100% backward compatible

---

## 🙏 Acknowledgments

Thank you to the community for maintaining high code quality standards.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.6.0...5.6.1
## Version 5.6.1 - PHPStan Level 9 Compliance Fixes

**Release Date**: 2026-01-26  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release focuses on resolving all PHPStan Level 9 static analysis errors across the framework. We've fixed type safety issues, removed unused properties, and improved method signatures to ensure full compliance with the highest static analysis level. All changes are backward compatible and require no code modifications.

---

## 🐛 Bug Fixes

### PHPStan Level 9 Compliance

#### AsyncApiCall.php
- **Removed unused property** - Deleted `$responseCallbacks` property that was never read
- **Fixed return type** - Updated `getInternalClient()` to properly exclude null from return type
- **Removed unnecessary checks** - Eliminated redundant `method_exists()` calls for methods guaranteed to exist

#### ApiCall.php
- **Removed unused properties** - Deleted `$rawBody` and `$formFields` properties that were never read
- **Fixed return type** - Enhanced `getInternalClient()` with proper PHPStan type assertion

#### Controller.php
- **Fixed method signature conflict** - Updated `recordApmException()` to support both:
  - Single-parameter call: `recordApmException($exception)` (backward compatible)
  - Two-parameter call: `recordApmException($spanData, $exception)` (for trait usage)
- **Resolves PHPStan error** - "Method invoked with 2 parameters, 1 required"

#### ApmModel.php
- **Fixed mixed type access** - Added proper type checks (`is_array()`, `is_string()`) before array offset access
- **Fixed strlen() with mixed** - Ensured string type before calling `strlen()`
- **Added PHPStan ignore** - Valid dead catch warning (constructor can throw even after `class_exists()` check)

---

## 🔄 Changes

### Type Safety Improvements
- Enhanced null handling in lazy-loaded client instances
- Improved type assertions for PHPStan Level 9 compliance
- Cleaner codebase with removed unused properties

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

---

## 🚀 Performance

- No performance impact from these changes
- Code cleanup may provide minor memory savings (removed unused properties)

---

## 🔄 Migration Guide

### From 5.6.0 to 5.6.1

This release is **fully backward compatible**. No action required.

**What Changed**:
- PHPStan Level 9 compliance fixes
- Removed unused properties
- Fixed method signature conflicts
- Enhanced type safety

**Benefits**:
- Full PHPStan Level 9 compliance
- Cleaner codebase
- Better type safety
- No breaking changes

**Action Required**:
- **None** - automatic upgrade recommended
- Run `composer update gemvc/library` to get the new version
- All existing code continues to work without modification

**Breaking Changes**:
- None - 100% backward compatible

---

## 🙏 Acknowledgments

Thank you to the community for maintaining high code quality standards with PHPStan Level 9.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.5.0...5.6.0
## Version 5.6.0 - Core APM Architecture Unification & DX Improvements

**Release Date**: 2026-01-22  
**Type**: Minor Release (Backward Compatible)

---

## 📋 Overview

This release focuses on standardizing the **Application Performance Monitoring (APM)** architecture across the framework core. We have unified the tracing logic using a centralized `ApmTracingTrait`, reducing code duplication and ensuring consistent behavior across `ApiService`, `Controller`, and `UniversalQueryExecuter`.

Additionally, we introduced **Magic Properties** in `ApiService` for a significantly improved Developer Experience (DX) when calling controllers.

---

## ✨ Added

### 🪄 Magic Controller Access (DX Improvement)

- **Fluent Controller Access** - Access controllers as properties in `ApiService`
  - Syntax: `$this->UserController->method()`
  - Replaces verbose: `$this->callController(new UserController($this->request))->method()`
  - **Automatic Tracing**: The magic wrapper automatically handles APM tracing for the controller call
  - **Type Safety**: Includes `@property` annotations for IDE autocompletion
  - Location: `src/core/ApiService.php`

### 🔧 Unified APM Architecture

- **ApmTracingTrait** - Centralized APM logic
  - Provides unified `startApmSpan`, `endApmSpan`, `traceApm`, and `recordApmException` methods
  - Adopted by `ApiService`, `Controller`, and `UniversalQueryExecuter`
  - Ensures consistent span attributes and error handling
  - Location: `src/core/Apm/ApmTracingTrait.php`

---

## 🔄 Changes

### Controller Layer Refactoring

- **Refactored `Controller.php`** - Now uses `ApmTracingTrait`
  - Removed duplicate `getApm()` logic
  - Removed dead code (`getControllerName`)
  - Aliased legacy `startTraceSpan` methods for backward compatibility
  - Location: `src/core/Controller.php`

### Database Layer Refactoring

- **Refactored `UniversalQueryExecuter.php`** - Now uses `ApmTracingTrait`
  - Replaced manual APM environment checks with centralized logic
  - **Context Propagation**: Renamed internal property to `$_request` to avoid DB column conflicts while maintaining APM context
  - Removed redundant helper methods
  - Location: `src/database/UniversalQueryExecuter.php`

### Type Safety & Compliance

- **PHPStan Level 9** - `ApiService` checks
  - Added strict `instanceof Controller` checks in magic getter
  - Added proper type hinting for all dynamic properties

---

## 🐛 Bug Fixes

- **Context Propagation Safety** - Fixed potential conflict in `UniversalQueryExecuter` where `$request` property could clash with database columns named "request". using `$_request` ensures safety.
- **Trace Continuity** - Ensured database traces are always correctly linked to the parent HTTP request trace via strict object propagation.

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained

---

## ⚙️ Configuration

- **No configuration settings changed**
- APM behavior (enabled/disabled) is still controlled via `.env` variables (`APM_ENABLED`, etc.)

---

## 🚀 Performance

- **Reduced Overhead** - Removed redundant method calls and checks in tracing logic
- **Optimized Tracing** - Centralized "should trace" checks are more efficient
- **Zero Impact** - Magic controller access uses lazy loading proxy with negligible overhead

---

## 🔄 Migration Guide

### From 5.5.0 to 5.6.0

This release is **fully backward compatible**. No action required.

**Recommendation**:
- Update your `ApiService` code to use the new fluent syntax: `return $this->UserController->create();` for cleaner code.
- Old syntax `$this->callController(...)` continues to work perfectly.

---



## Version 5.5.0 - HTTP Client Package Integration with Environment Detection

**Release Date**: 2026-01-22  
**Type**: Minor Release (Backward Compatible)

---

## 📋 Overview

This release integrates the `gemvc/http-client` package into the framework core, providing automatic environment detection to select the optimal HTTP client implementation. `ApiCall` and `AsyncApiCall` classes now use the new package internally while maintaining 100% backward compatibility. The integration provides better error handling, improved performance in Swoole environments, and a cleaner codebase architecture.

---

## ✨ Added

### HTTP Client Package Integration

- **gemvc/http-client Package** - Now included as a required dependency
  - Provides `HttpClient` for synchronous requests (Apache/Nginx)
  - Provides `AsyncHttpClient` for asynchronous requests (Apache/Nginx)
  - Provides `SwooleHttpClient` for native Swoole coroutines (optimized)
  - Automatic environment detection via `WebserverDetector`
  - Enhanced error handling with exception classification
  - Better retry mechanisms and SSL support

### Automatic Environment Detection

- **AsyncApiCall Environment Detection** - Automatically selects optimal client
  - Uses `SwooleHttpClient` (native coroutines) when running in Swoole
  - Uses `AsyncHttpClient` (curl_multi) when running in Apache/Nginx
  - Zero configuration required - detection happens automatically
  - Performance optimized for each environment

---

## 🔄 Changes

### ApiCall Class Refactoring

- **Internal Implementation** - Now uses `Gemvc\Http\Client\HttpClient` internally
  - All public methods delegate to internal client
  - Configuration automatically synced between wrapper and internal client
  - Response data automatically synced back to wrapper
  - All existing public properties and methods remain unchanged
  - Location: `src/http/ApiCall.php`

**Benefits:**
- ✅ Better error handling from package
- ✅ Improved retry mechanisms
- ✅ Cleaner codebase (delegation pattern)
- ✅ Future-proof (package can be updated independently)
- ✅ 100% backward compatible

### AsyncApiCall Class Refactoring

- **Internal Implementation** - Uses `AsyncHttpClient` or `SwooleHttpClient` based on environment
  - Automatic environment detection via `WebserverDetector::isSwoole()`
  - Swoole: Uses native coroutines for optimal performance
  - Apache/Nginx: Uses curl_multi for concurrent execution
  - All public methods delegate to internal client
  - Configuration automatically synced
  - Location: `src/http/AsyncApiCall.php`

**Benefits:**
- ✅ Automatic environment detection
- ✅ Optimized Swoole performance (native coroutines)
- ✅ Better error handling from package
- ✅ Cleaner codebase (delegation pattern)
- ✅ 100% backward compatible

### Dependencies

- **composer.json** - Added `gemvc/http-client` as required dependency
  - Version constraint: `^1.2`
  - Automatically installed with framework
  - No additional installation steps required

---

## 🐛 Bug Fixes

- **No breaking changes** - All existing code continues to work without modification
- All 41 ApiCall tests passing
- All 149 AsyncApiCall tests passing
- No linting errors introduced

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)
- HTTP client package uses same security mechanisms
- SSL/TLS support maintained and enhanced

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

**For Users:**
- No code changes needed - existing `ApiCall` and `AsyncApiCall` usage continues to work
- Environment detection happens automatically
- Swoole users automatically get optimized native coroutines
- Apache/Nginx users continue using curl_multi

**For Developers:**
- Package integration is transparent
- All existing APIs remain unchanged
- Can still use `ApiCall` and `AsyncApiCall` as before
- Internal implementation uses optimized package

---

## 🚀 Performance

- **Swoole Optimization** - Native coroutines provide better performance in Swoole environment
- **No Performance Impact** - Backward compatible, same performance characteristics
- **Better Error Handling** - Improved retry mechanisms reduce failed requests
- **Cleaner Architecture** - Delegation pattern improves maintainability

---

## 🔄 Migration Guide

### From 5.4.4 to 5.5.0

This release is **fully backward compatible**. No action required.

**What Changed**:
- `ApiCall` now uses `HttpClient` internally (transparent to users)
- `AsyncApiCall` now uses `AsyncHttpClient` or `SwooleHttpClient` with automatic detection
- `gemvc/http-client` package added as dependency

**Benefits**:
- Automatic environment detection for optimal performance
- Better error handling and retry mechanisms
- Cleaner codebase architecture
- Future-proof design (package updates independently)

**Action Required**:
- **None** - automatic upgrade recommended
- Run `composer update gemvc/library` to get the new version
- All existing code continues to work without modification

**Breaking Changes**:
- None - 100% backward compatible

---

## 🙏 Acknowledgments

Thank you to the community for testing and feedback on the http-client package integration.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

## Version 5.4.4 - Installation Cleanup & Framework Services Refactoring

**Release Date**: 2026-01-15  
**Type**: Minor Release (Backward Compatible)

---

## 📋 Overview

This release significantly improves the initial project structure by moving framework-specific services from user projects into the core framework. The initial app is now much cleaner, containing only user-facing examples (User service) and thin API wrappers for framework services. All framework implementation details are now properly encapsulated in `src/core/`.

---

## ✨ Added

### Framework Services in Core

- **Apm Service** → `src/core/Apm/`
  - `ApmController.php` - APM testing and management
  - `ApmModel.php` - APM business logic
  - Moved from `init_example/controller/` and `init_example/model/`

- **GemvcAssistant Service** → `src/core/Assistant/`
  - `GemvcAssistantController.php` - Developer tools orchestration
  - `GemvcAssistantModel.php` - Assistant business logic
  - Moved from `init_example/controller/` and `init_example/model/`

- **Developer Service** → `src/core/Developer/`
  - `DeveloperController.php` - Developer welcome page and tools
  - `DeveloperModel.php` - Developer data logic
  - `DeveloperTable.php` - Developer database operations
  - Moved from `init_example/controller/`, `init_example/model/`, and `init_example/table/`

- **GemvcMonitoring Service** → `src/core/Monitoring/`
  - `GemvcMonitoringController.php` - Server monitoring orchestration
  - `GemvcMonitoringModel.php` - Monitoring business logic
  - Moved from `init_example/controller/` and `init_example/model/`

---

## 🔄 Changes

### Initial Project Structure

**Before (5.4.3):**
```
app/
├── api/
│   ├── User.php
│   ├── Apm.php
│   ├── GemvcAssistant.php
│   └── GemvcMonitoring.php
├── controller/
│   ├── UserController.php
│   ├── IndexController.php
│   ├── ApmController.php          # ❌ Framework code in user project
│   ├── GemvcAssistantController.php  # ❌ Framework code in user project
│   ├── GemvcMonitoringController.php # ❌ Framework code in user project
│   └── DeveloperController.php    # ❌ Framework code in user project
├── model/
│   ├── UserModel.php
│   ├── ApmModel.php               # ❌ Framework code in user project
│   ├── GemvcAssistantModel.php    # ❌ Framework code in user project
│   ├── GemvcMonitoringModel.php   # ❌ Framework code in user project
│   └── DeveloperModel.php        # ❌ Framework code in user project
└── table/
    ├── UserTable.php
    └── DeveloperTable.php         # ❌ Framework code in user project
```

**After (5.4.4):**
```
app/
├── api/
│   ├── User.php                   # ✅ Full example (CRUD)
│   ├── Apm.php                    # ✅ Thin wrapper → core
│   ├── GemvcAssistant.php         # ✅ Thin wrapper → core
│   └── GemvcMonitoring.php        # ✅ Thin wrapper → core
├── controller/
│   ├── UserController.php         # ✅ Full example
│   └── IndexController.php        # ✅ Index controller
├── model/
│   └── UserModel.php              # ✅ Full example
└── table/
    └── UserTable.php              # ✅ Full example

src/core/                           # ✅ Framework services
├── Apm/
│   ├── ApmController.php
│   └── ApmModel.php
├── Assistant/
│   ├── GemvcAssistantController.php
│   └── GemvcAssistantModel.php
├── Developer/
│   ├── DeveloperController.php
│   ├── DeveloperModel.php
│   └── DeveloperTable.php
└── Monitoring/
    ├── GemvcMonitoringController.php
    └── GemvcMonitoringModel.php
```

### API Wrappers

All framework service API files (`Apm.php`, `GemvcAssistant.php`, `GemvcMonitoring.php`) now delegate to core controllers:

```php
// Example: app/api/Apm.php
public function test(): JsonResponse
{
    return (new ApmController($this->request))->test();
}
```

**Benefits:**
- ✅ Cleaner initial app - users see only User service as complete example
- ✅ Framework services hidden - implementation details in core, not copied to user projects
- ✅ Better separation - framework code in `src/core/`, user examples in `app/`
- ✅ Easier maintenance - framework services updated in one place
- ✅ Focused learning - users see one complete example instead of multiple services

---

## 🐛 Bug Fixes

- **No breaking changes** - All API endpoints remain functional
- Framework services continue to work exactly as before
- API wrappers maintain backward compatibility

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained
- Framework services maintain same security standards

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

**For New Projects:**
- Run `gemvc init` to get the new cleaner structure
- User service example remains unchanged
- Framework services work automatically via API wrappers

**For Existing Projects:**
- No changes needed - existing projects continue to work
- Framework services can be manually moved to core if desired
- API endpoints remain unchanged

---

## 📚 Migration Guide

### From 5.4.3 to 5.4.4

**No migration required!** This is a backward-compatible change. Existing projects continue to work without modification.

**Optional Cleanup (for existing projects):**
If you want to clean up your existing project structure:

1. **Framework services are now in core** - You can delete these from your `app/` directory:
   - `app/controller/ApmController.php`
   - `app/controller/GemvcAssistantController.php`
   - `app/controller/GemvcMonitoringController.php`
   - `app/controller/DeveloperController.php`
   - `app/model/ApmModel.php`
   - `app/model/GemvcAssistantModel.php`
   - `app/model/GemvcMonitoringModel.php`
   - `app/model/DeveloperModel.php`
   - `app/table/DeveloperTable.php`

2. **API wrappers remain** - Keep these files (they delegate to core):
   - `app/api/Apm.php`
   - `app/api/GemvcAssistant.php`
   - `app/api/GemvcMonitoring.php`

3. **Update imports** - API files automatically use core controllers, no changes needed.

---

## Version 5.4.3 - APM Batch Sending Implementation

**Release Date**: 2026-01-14  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release implements a reliable batch sending mechanism for APM traces, replacing the previous asynchronous API call approach with a time-based batch system. The new implementation uses synchronous `ApiCall()` with automatic batch sending every 5 seconds, significantly improving APM trace delivery reliability. All changes are backward compatible and require no code modifications.

---

## ✨ Added

### APM Batch Sending Mechanism

- **Time-based batch sending** - APM traces are now collected and sent in batches every 5 seconds
  - Replaces `AsyncApiCall` with synchronous `ApiCall()` for better reliability
  - Automatic batch queue management
  - Shutdown handler ensures all traces are sent on application termination
  - Significantly improved trace delivery reliability
  - Location: `gemvc/apm-tracekit` package (v2.0+)

**Benefits:**
- More reliable trace delivery compared to async approach
- Automatic batching reduces API call overhead
- Shutdown handler ensures no traces are lost
- Better error handling and retry mechanisms

---

## 🔄 Changes

### Bootstrap.php

- **APM flush before response** - Added `apm->flush()` call before `die()` statement
  - Ensures traces are added to batch queue before response is sent
  - Captures HTTP response code for APM tracing
  - Stores response code in `Request::$_http_response_code` property
  - Location: `src/core/Bootstrap.php`

- **Error handling APM flush** - Added APM flush in error handling path
  - Ensures traces are sent even when errors occur
  - Maintains trace visibility for debugging

### OpenSwooleServer.php

- **APM flush after response** - Added `apm->flush()` call after response is sent
  - Captures HTTP response code before sending response (Swoole limitation)
  - Stores response code in `Request::$_http_response_code` property
  - Ensures traces are added to batch queue after response
  - Location: `src/core/OpenSwooleServer.php`

**Technical Note:**
In Swoole environment, `http_response_code()` cannot be reliably called after headers are sent. The solution captures the response code before sending and stores it in `Request::$_http_response_code` for APM access.

### Request.php

- **HTTP response code property** - Added `$_http_response_code` property
  - Used to pass response code to APM `flush()` method
  - Required for Swoole environment where `http_response_code()` is unreliable
  - Location: `src/http/Request.php`

---

## 🐛 Bug Fixes

### PHPStan Level 9 Compliance

- **Fixed PHPStan errors** - Resolved all static analysis errors
  - Removed unnecessary `@phpstan-ignore-next-line` comments
  - Fixed `DatabaseManagerFactory` PHPDoc type annotation
  - Simplified null checks in `OpenSwooleServer.php`
  - All files now pass PHPStan Level 9 analysis

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)
- APM batch sending uses same security mechanisms as before

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

**For APM Users:**
- Batch sending is automatic with `gemvc/apm-tracekit` v2.0+
- No code changes needed
- Traces are automatically batched and sent every 5 seconds
- Shutdown handler ensures all traces are sent on application termination

---

## 🚀 Performance

- **Improved APM reliability** - Batch sending reduces failed trace deliveries
- **Reduced API overhead** - Batching multiple traces in single requests
- **No performance impact** - Batch sending happens asynchronously after HTTP response
- **Better error handling** - Improved retry mechanisms for failed batches

---

## 🔄 Migration Guide

### From 5.4.2 to 5.4.3

This release is **fully backward compatible**. No action required.

**What Changed**:
- APM trace delivery switched from async to batch sending mechanism
- Improved trace delivery reliability
- Better error handling for APM operations
- PHPStan Level 9 compliance fixes

**Benefits**:
- More reliable APM trace delivery
- Better error handling and retry mechanisms
- Cleaner codebase (PHPStan Level 9 compliant)
- No traces lost on application shutdown

**Action Required**:
- **None** - automatic upgrade recommended
- Update `gemvc/apm-tracekit` to `^2.0` if not already updated:
  ```bash
  composer update gemvc/apm-tracekit
  ```

**Breaking Changes**:
- None

---

## 🙏 Acknowledgments

Thank you to the community for feedback on APM reliability improvements.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.4.1...5.4.2
## Version 5.4.2 - Docker Build Fix

**Release Date**: 2026-06-05  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release fixes a critical Docker build issue that prevented `docker compose up -d --build` from working correctly. The `.dockerignore` file was incorrectly excluding `composer.json` and `composer.lock`, which are required for the Docker build process. All changes are backward compatible and require no code modifications.

---

## 🐛 Bug Fixes

### OpenSwoole Dockerfile Build Failure

- **Fixed `.dockerignore` blocking composer files** - Removed `composer.json` and `composer.lock` from ignore list
  - Dockerfile requires these files to install dependencies during build
  - Fixes `docker compose up -d --build` command failures
  - Docker builds now complete successfully
  - Location: `src/startup/swoole/.dockerignore`

**Problem:**
The `.dockerignore` file was preventing `composer.json` and `composer.lock` from being included in the Docker build context, causing the build to fail when trying to copy these files in the Dockerfile.

**Solution:**
Removed `composer.json` and `composer.lock` from the `.dockerignore` file, allowing them to be properly copied during the Docker build process.

**Before:**
```
composer.phar
/vendor/
composer.lock          ← Blocked
app/config.php
Dockerfile
docker-compose.yml
LICENSE
README.md
.gitignore
composer.json          ← Blocked
```

**After:**
```
composer.phar
/vendor/
app/config.php
Dockerfile
docker-compose.yml
LICENSE
README.md
.gitignore
```

---

## 🗑️ Removed

### Unnecessary composer.json

- **Removed redundant `composer.json`** - Deleted `src/startup/swoole/composer.json`
  - File was not needed as the main project `composer.json` is used
  - Simplifies project structure
  - Prevents confusion about which composer.json to use
  - Location: `src/startup/swoole/composer.json`

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

---

## 🚀 Performance

- No performance impact from these changes
- Docker builds now complete successfully without errors
- Faster build times due to proper file inclusion

---

## 🔄 Migration Guide

### From 5.4.1 to 5.4.2

This release is **fully backward compatible**. No action required.

**What Changed**:
- Fixed `.dockerignore` to allow composer files in Docker builds
- Removed unnecessary `composer.json` from swoole startup directory

**Benefits**:
- `docker compose up -d --build` now works correctly
- Cleaner project structure
- No more Docker build failures

**Action Required**:
- **None** - automatic upgrade recommended
- Rebuild Docker containers to get the fix: `docker compose up -d --build`

**Breaking Changes**:
- None

---

## 🙏 Acknowledgments

Thank you to the community for reporting the Docker build issue.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.4.0...5.4.1
## Version 5.4.1 - Docker Healthcheck Fix and TraceKit Default

**Release Date**: 2026-06-05  
**Type**: Patch Release (Backward Compatible)

---

## 📋 Overview

This patch release fixes the OpenSwoole Dockerfile healthcheck endpoint and officially includes TraceKit as a default dependency in the GEMVC package. All changes are backward compatible and require no code modifications.

---

## 🐛 Bug Fixes

### OpenSwoole Dockerfile Healthcheck

- **Fixed healthcheck endpoint URL** - Changed from `/index/index` to `/api`
  - Healthcheck now correctly uses the standard GEMVC API healthcheck endpoint
  - Fixes Docker healthcheck failures in OpenSwoole containers
  - Aligns with GEMVC documentation and standard API endpoint structure
  - Location: `src/startup/swoole/Dockerfile`

**Before:**
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9501/api || exit 1
```

**After:**
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9501/index/index || exit 1
```

---

## ✨ Added

### TraceKit as Default Dependency

- **TraceKit APM Provider** - Now included as a default dependency in `composer.json`
  - `gemvc/apm-tracekit` is now a required dependency (not optional)
  - TraceKit is the default APM provider for GEMVC Framework
  - No additional `composer require` needed for TraceKit integration
  - Simplifies APM setup for new projects

**Benefits:**
- Zero-configuration APM setup with TraceKit
- Automatic APM tracing out of the box
- Consistent APM experience across all GEMVC projects
- Reduced setup steps for developers

**Note:** TraceKit was previously available but required manual installation. It is now included by default in the GEMVC package.

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)

---

## ⚙️ Configuration

No configuration changes required. All improvements are automatic and backward compatible.

**For TraceKit Users:**
- TraceKit is now automatically installed with GEMVC
- Configure TraceKit in your `.env` file as before:
  ```env
  APM_NAME=TraceKit
  TRACEKIT_API_KEY=your-api-key
  TRACEKIT_API_URL=https://app.tracekit.dev/v1/traces
  ```

---

## 🚀 Performance

- No performance impact from these changes
- Healthcheck fix improves Docker container reliability
- TraceKit inclusion has no runtime overhead when not configured

---

## 🔄 Migration Guide

### From 5.4.0 to 5.4.1

This release is **fully backward compatible**. No action required.

**What Changed**:
- OpenSwoole Dockerfile healthcheck endpoint fixed
- TraceKit now included as default dependency

**Benefits**:
- Docker healthchecks now work correctly
- Simplified APM setup with TraceKit included by default
- Better container monitoring and reliability

**Action Required**:
- **None** - automatic upgrade recommended
- If you manually installed TraceKit, you can remove it from your `composer.json` (it's now included by default)
- Rebuild Docker containers to get the healthcheck fix

**Breaking Changes**:
- None

---

## 🙏 Acknowledgments

Thank you to the community for reporting the Docker healthcheck issue and providing feedback.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.3.0...5.4.0
## Version 5.4.0 - Native APM Integration

**Release Date**: 2026-01-04  
**Type**: Minor Release (Backward Compatible)

---

##  Overview

This release introduces **Native APM (Application Performance Monitoring) Integration** with automatic request tracing, controller method tracing, and database query tracing. The APM system provides complete visibility into your application's performance with zero configuration required. All tracing is environment-controlled, allowing you to enable or disable features without code changes.

---

##  New Features

### 🚀 Native APM Integration

A complete Application Performance Monitoring solution built directly into the framework, providing automatic tracing of requests, controllers, and database queries with zero configuration required.

#### **Key Features**:

1. **Automatic Root Tracing**:
   - APM initialized early in `Bootstrap.php` and `SwooleBootstrap.php`
   - Root trace automatically created at request start
   - Captures full request lifecycle from start to finish
   - All spans share the same `traceId` for complete request visibility
   - Exception tracking automatically records all exceptions

2. **Controller Tracing** (Environment-Controlled):
   - Use `$this->callController()` in API services for automatic tracing
   - Controlled via `APM_TRACE_CONTROLLER=1` environment variable
   - Automatic spans for controller method calls
   - Captures method name, HTTP response code, execution time, and response data
   - Zero code changes needed - same code works with/without tracing

3. **Database Query Tracing** (Environment-Controlled):
   - Automatic APM spans for all database queries
   - Controlled via `APM_TRACE_DB_QUERY=1` environment variable
   - Captures query type (SELECT, INSERT, UPDATE, DELETE), execution time, rows affected, and SQL statement
   - Request propagation through all database layers
   - Use `$this->createModel()` in controllers for automatic Request propagation

4. **Trace Context Propagation**:
   - Request object carries APM instance (`$request->apm`)
   - Automatic propagation: Bootstrap → ApiService → Controller → Table → Database
   - All spans automatically linked to root trace
   - Fire-and-forget pattern for non-blocking trace sending

#### **Access**:
- Works automatically when APM provider is configured
- No special endpoints or URLs required
- Traces sent asynchronously after HTTP response

#### **Technical Implementation**:
- **Early Initialization**: APM created in Bootstrap/SwooleBootstrap constructors
- **Request Propagation**: `$request->apm` carries trace context through all layers
- **Environment Control**: Tracing enabled/disabled via environment variables
- **Provider Agnostic**: Works with any APM provider via `gemvc/apm-contracts`
- **Performance**: Non-blocking trace sending (fire-and-forget pattern)
- **Compatibility**: Works with all webserver types (Apache, Nginx, OpenSwoole)

### 🔧 Developer Tools

#### **ApmTracingTrait**
- Unified APM tracing methods for reuse across all layers
- `startApmSpan()` - Start a new APM span with attributes
- `endApmSpan()` - End a span with status and attributes
- `recordApmException()` - Record exceptions in spans
- `traceApm()` - Convenience method for wrapping operations in spans

#### **Controller::createModel()**
- Helper method for automatic Request propagation to models
- Ensures trace context is available for database query tracing
- Simple one-line usage: `$model = $this->createModel(new UserModel());`

#### **ApiService::callController()**
- Recommended method for invoking controllers with automatic tracing
- Better naming than deprecated `callWithTracing()`
- Environment-controlled (respects `APM_TRACE_CONTROLLER` flag)

---

## 🔄 Changes

### Bootstrap / SwooleBootstrap

- **APM Initialization** - Moved to constructor for early tracing
- **Root Trace Creation** - Automatic root trace at request start
- **Request APM Setting** - Explicit `$request->apm` assignment for trace context
- **Exception Handling** - Updated to set `$request->apm` for fallback APM instances

### ApiService

- **APM Initialization Removed** - Now handled by Bootstrap (earlier in lifecycle)
- **callController() Method** - New recommended method for controller invocation with tracing
- **callWithTracing() Deprecated** - Kept for backward compatibility, use `callController()` instead
- **ApmTracingTrait Integration** - Uses unified tracing methods

### Controller

- **APM Retrieval** - Gets APM instance from `$request->apm` (shared trace context)
- **createModel() Helper** - New method for automatic Request propagation to models
- **createList() Updated** - Now uses `createModel()` for Request propagation

### Database Layer

- **Table::setRequest()** - New method for trace context propagation
- **ConnectionManager::setRequest()** - Propagates Request to PdoQuery
- **PdoQuery::setRequest()** - Propagates Request to UniversalQueryExecuter
- **UniversalQueryExecuter** - Database query tracing implementation
- **Request Propagation Chain** - Complete trace context flow through all database layers

### JsonResponse

- **JsonSerializable Implementation** - Excludes internal APM properties from JSON output
- **Clean API Responses** - `_apm_span`, `_apm_model_name`, `_apm_method_name` no longer appear in responses

---

## 🐛 Bug Fixes

- **ApiService::callController()** - Fixed static method calls to use `AbstractApm` instead of interface
  - Resolves "Cannot call abstract method" errors
  - Changed `ApmInterface::determineStatusFromHttpCode()` to inline status determination
  - Changed `ApmInterface::limitStringForTracing()` to `AbstractApm::limitStringForTracing()`

- **JsonResponse JSON Output** - Removed internal APM properties from API responses
  - `_apm_span`, `_apm_model_name`, `_apm_method_name` no longer appear in JSON
  - Clean API responses for all endpoints

---

## 📚 Documentation Updates

- **GEMVC_APM_INTEGRATION.md** - Comprehensive APM integration guide
  - Architecture overview and trace flow diagrams
  - Configuration instructions and environment variables
  - Usage examples and best practices
  - Performance considerations and troubleshooting
  - Advanced usage patterns

- **README.md** - Updated with prominent APM integration section
  - Key features highlighting native APM
  - Quick setup instructions
  - What gets traced automatically
  - Performance notes and documentation links

- **ARCHITECTURE.md** - Added APM Integration Architecture section
  - Request flow diagrams with APM tracing
  - APM architecture flow diagram
  - Component breakdown with APM features
  - Performance characteristics

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)
- APM tracing is read-only (no data modification)
- Trace data sent asynchronously (fire-and-forget pattern)
- No sensitive data exposed in traces (configurable via APM provider)

---

## ⚙️ Configuration

### Required Configuration

1. **Install APM Provider Package**:
   ```bash
   composer require gemvc/apm-tracekit
   # or your preferred APM provider
   ```

2. **Set APM Provider in `.env`**:
   ```env
   APM_NAME=TraceKit
   TRACEKIT_API_KEY=your-api-key
   TRACEKIT_API_URL=https://app.tracekit.dev/v1/traces
   ```

### Optional Configuration

1. **Enable Controller Tracing**:
   ```env
   APM_TRACE_CONTROLLER=1
   ```

2. **Enable Database Query Tracing**:
   ```env
   APM_TRACE_DB_QUERY=1
   ```

3. **Configure Sample Rate** (TraceKit):
   ```env
   TRACEKIT_SAMPLE_RATE=1.0  # 0.0 to 1.0 (1.0 = 100%)
   ```

### Usage in Code

1. **In API Services** - Use `callController()`:
   ```php
   return $this->callController(new UserController($this->request))->create();
   ```

2. **In Controllers** - Use `createModel()`:
   ```php
   $model = $this->createModel(new UserModel());
   ```

---

## 🚀 Performance

- **Zero Overhead When Disabled** - Environment flags control tracing (no performance impact when off)
- **Minimal Overhead When Enabled** - ~0.25ms per request when tracing is active
- **Non-Blocking Trace Sending** - Traces sent after HTTP response (fire-and-forget pattern)
- **Sample Rate Support** - Control trace volume via `TRACEKIT_SAMPLE_RATE` (reduce overhead)
- **Efficient Span Management** - Spans created and closed efficiently with minimal memory footprint
- **Request Propagation** - Single APM instance shared across all layers (no duplication)

---

## 🧪 Testing

- **ControllerCreateModelTest** - Tests Request propagation to models via `createModel()`
- **TableRequestPropagationTest** - Tests Request propagation through database layers
- **UniversalQueryExecuterApmTest** - Tests APM tracing in database queries
- All tests passing with PHPUnit
- Comprehensive coverage of APM tracing functionality

---

## 🔄 Migration Guide

### From 5.3.0 to 5.4.0

This release is **fully backward compatible**. No action required.

**What's New**:
- Native APM integration with automatic request tracing
- Controller tracing via `callController()` method (environment-controlled)
- Database query tracing (environment-controlled)
- Request propagation through all layers for complete trace visibility
- `ApmTracingTrait` for unified APM tracing
- `Controller::createModel()` helper for automatic Request propagation

**Benefits**:
- Zero-configuration APM tracing (works out of the box)
- Complete request visibility from Bootstrap to Database
- Environment-controlled tracing (enable/disable via env vars)
- Non-blocking trace sending (no performance impact)
- Same code works with/without tracing (no code changes needed)

**Optional Configuration**:
1. Install APM provider: `composer require gemvc/apm-tracekit`
2. Set `APM_NAME=TraceKit` in `.env`
3. Enable controller tracing: `APM_TRACE_CONTROLLER=1`
4. Enable database tracing: `APM_TRACE_DB_QUERY=1`

**Optional Code Changes**:
- Replace `callWithTracing()` with `callController()` (better naming)
- Use `createModel()` in controllers for automatic Request propagation
- See `GEMVC_APM_INTEGRATION.md` for complete documentation

**Breaking Changes**:
- None - `callWithTracing()` still works but is deprecated

---

## 🙏 Acknowledgments

Special thanks to the community for feedback and feature requests that led to this comprehensive monitoring solution.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

## [Gemvc PHP Framework built for Microservices](https://gemvc.de)
### Made with ❤️ by Ali Khorsandfard


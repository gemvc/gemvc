## ServiceCall mesh DX (Phase 2b)

Caller-side mesh DX on top of Phase 2a family trust (`5.15.0`).

**Full Changelog:** https://github.com/gemvc/gemvc/compare/5.15.0...5.16.0

### Added

- **`ServiceCall`** — family microservice caller over existing **`ApiCall`** (default sync) / **`AsyncApiCall`** (explicit async) — no second HTTP stack
- **`ServiceMap`** — resolve sibling base URLs from `GEMVC_SERVICES_JSON`
- `withInternalTrust()` / `withoutInternalTrust()` — production mapped calls **require** one of these
- Single-encode JSON pipeline (same bytes signed with HMAC and sent on the wire)
- `->sync()` / `->async()` / `->fireAndForget()` transport controls
- Unit tests: `tests/Unit/Http/ServiceCallTest.php`

### Usage

```env
GEMVC_INTERNAL_SECRET=...long-random...
GEMVC_SERVICES_JSON={"auth":"http://noam-auth","billing":"http://billing"}
```

```php
use Gemvc\Http\ServiceCall;

$body = ServiceCall::to('auth')
    ->post('/api/Auth/oauthLogin', $payload)
    ->withInternalTrust()
    ->withTimeout(2.0)
    ->run();
```

Receiver still uses `$this->requireInternalService()`.

### Migration

1. Add `GEMVC_SERVICES_JSON` alongside existing `GEMVC_INTERNAL_SECRET`
2. Prefer `ServiceCall` over hand-rolled `InternalTrust::callerHeaders()` + `ApiCall`
3. In production, always call `withInternalTrust()` or `withoutInternalTrust()`

### Docs

- https://github.com/gemvc/gemvc/blob/main/docs/guides/http-client.md#servicecall-phase-2b
- https://github.com/gemvc/gemvc/blob/main/docs/guides/security.md#family-trust-phase-2a
- https://github.com/gemvc/gemvc/blob/main/docs/improvements/phase-2-trust-and-mesh.md
- https://github.com/gemvc/gemvc/blob/main/docs/releases/CHANGELOG.md

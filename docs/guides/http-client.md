# GEMVC HTTP Client — `gemvc/http-client`

**Audience:** calling **other** HTTP APIs (outbound) from GEMVC apps or any PHP project.

**Related:** [ecosystem.md](ecosystem.md) · inbound Request: [http-lifecycle.md](http-lifecycle.md) · vendor `vendor/gemvc/http-client/README.md`

---

## Why it matters

**`gemvc/http-client` is a core GEMVC package** (required by `gemvc/library`). Use it for microservice-to-microservice calls, webhooks, and fire-and-forget logging — **not** `curl_*` spaghetti or a bespoke Guzzle wrapper per service.

| | Inbound | Outbound |
|--|---------|----------|
| Package | `gemvc/library` | **`gemvc/http-client`** |
| Types | `Gemvc\Http\Request` | `Gemvc\Http\Client\HttpClient` … |
| Role | Request into *your* API | Your app calling *other* APIs |

Library facades `Gemvc\Http\ApiCall` / `AsyncApiCall` use this package internally (environment-aware).

```bash
composer require gemvc/library   # pulls gemvc/http-client
# or standalone:
composer require gemvc/http-client
```

---

## Reading map (AI)

| Need | Class |
|------|--------|
| Sync GET/POST/… | `HttpClient` |
| Concurrent batches | `AsyncHttpClient::executeAll()` |
| Non-blocking log/APM | `AsyncHttpClient::fireAndForget()` |
| OpenSwoole coroutines | `SwooleHttpClient` |
| Exceptions | `NetworkException`, `TimeoutException`, … |
| Full API | `vendor/gemvc/http-client/README.md` |

**AI rule:** Prefer `gemvc/http-client` over inventing HTTP clients in `app/`.

---

## Clients by environment

| Class | Runtime | Notes |
|-------|---------|--------|
| `HttpClient` | Apache / Nginx | Sync, curl-based |
| `AsyncHttpClient` | Apache / Nginx | Concurrent + fire-and-forget |
| `SwooleHttpClient` | OpenSwoole | Native coroutines (no curl dependency) |

---

## Sync — `HttpClient`

```php
use Gemvc\Http\Client\HttpClient;
use Gemvc\Http\Client\Exception\NetworkException;

$client = new HttpClient();
$client->setTimeouts(10, 30)
       ->setRetries(3, 200, [500, 502, 503])
       ->setUserAgent('MyService/1.0');

$body = $client->get('https://api.example.com/users', ['page' => 1]);
$client->post('https://api.example.com/users', ['name' => 'John']);
$client->put($url, $data);
$client->postForm($url, $fields);
$client->postMultipart($url, $fields, $files);
$client->postRaw($url, $body, 'application/json');

try {
    $client->get('https://api.example.com/data');
} catch (NetworkException $e) {
    if ($e->isDnsError()) { /* … */ }
}

// Or store errors instead of throwing
$client->throwExceptions(false);
$client->get($url);
if ($client->hasErrors()) {
    $err = $client->getLastError();
}
```

Also: `setSsl(...)`, `retryOnNetworkError(bool)`, `clearErrors()`, `getErrors()`.

---

## Async — `AsyncHttpClient`

```php
use Gemvc\Http\Client\AsyncHttpClient;

$async = new AsyncHttpClient();
$async->setMaxConcurrency(5)
      ->setTimeouts(10, 30)
      ->addGet('users', 'https://api.example.com/users', ['page' => 1])
      ->addGet('posts', 'https://api.example.com/posts')
      ->addPost('create', 'https://api.example.com/create', ['name' => 'Test']);

$results = $async->executeAll();
foreach ($results as $id => $result) {
    if ($result['success']) {
        // $result['body'], http_code, duration, …
    }
}

// Fire-and-forget (APM, analytics — non-blocking)
$async->addPost('log', 'https://apm.example.com/log', $payload)
      ->fireAndForget();
```

Also: `addPut`, `addPostForm`, `addPostMultipart`, `addPostRaw`, `onResponse($id, callable)`, `waitForAll()`, `clearQueue()`.

---

## Framework facade (optional)

```php
use Gemvc\Http\ApiCall;

$api = new ApiCall();  // picks HttpClient / Swoole client via WebserverDetector
$api->get('https://api.example.com/data');
```

Prefer the package classes directly when writing new microservice clients; facades remain for compatibility.

---

## ServiceCall (Phase 2b)

Family discovery + trust on top of `ApiCall` / `AsyncApiCall` (no second HTTP stack).

```env
GEMVC_SERVICES_JSON={"auth":"http://noam-auth","billing":"http://billing"}
GEMVC_INTERNAL_SECRET=...long-random...
```

```php
use Gemvc\Http\ServiceCall;

// Default = sync ApiCall. Production requires withInternalTrust() or withoutInternalTrust().
$body = ServiceCall::to('auth')
    ->post('/api/Auth/oauthLogin', ['code' => $code]) // JSON encoded once; same bytes signed + sent
    ->withInternalTrust()
    ->withTimeout(2.0)
    ->run();

ServiceCall::to('billing')->async()->get('/api/Billing/ping')->withInternalTrust()->run();
ServiceCall::to('billing')->async()->post('/api/Billing/log', $payload)->withoutInternalTrust()->fireAndForget();
```

| Method | Role |
|--------|------|
| `to('auth')` | Resolve base URL from `GEMVC_SERVICES_JSON` |
| `withInternalTrust()` | Attach HMAC headers (`InternalTrust`) |
| `withoutInternalTrust()` | Explicit skip (required alternative in production) |
| `sync()` / `async()` | Force `ApiCall` / `AsyncApiCall` |
| `fireAndForget()` | Async only |

Receiver still uses `$this->requireInternalService()`. Details: [security.md](security.md#family-trust-phase-2a) · [phase-2-trust-and-mesh.md](../improvements/phase-2-trust-and-mesh.md)

---

## Family trust (outbound HMAC)

Until Phase 2b `ServiceCall`, attach family HMAC headers with `InternalTrust::callerHeaders()` and **`postRaw` / `get` using the same path and raw body you sign**.

```php
use Gemvc\Core\InternalTrust;
use Gemvc\Http\Client\HttpClient;

$path = '/api/Auth/oauthLogin';
$rawBody = json_encode(['code' => $code], JSON_THROW_ON_ERROR);
$base = rtrim($_ENV['AUTH_SERVICE_URL'] ?? 'http://noam-auth', '/');

$client = new HttpClient();
$client->header = array_merge(
    $client->header,
    InternalTrust::callerHeaders('POST', $path, $rawBody),
    ['Content-Type' => 'application/json']
);
$body = $client->postRaw($base . $path, $rawBody, 'application/json');
```

Rules that break signatures if ignored:

- Sign the **exact** raw body bytes you send (not a re-encoded array)
- Path **without** query string; must match what the receiver sees in `requestedUrl`
- Same `GEMVC_INTERNAL_SECRET` on caller and callee

Receiver: `$this->requireInternalService()` — [security.md](security.md#family-trust-phase-2a) · [api.md](api.md#family-trust-machine-to-machine)

---

## Do / Don’t

**Do**

- Use for outbound microservice HTTP  
- Set timeouts / retries intentionally  
- Use typed exceptions or `hasErrors()` consistently  
- Use `fireAndForget` for non-critical telemetry  

**Don’t**

- Confuse with inbound `Request` / `definePostSchema`  
- Hand-roll `curl_multi` for concurrency  
- Put JWT inbound auth logic in http-client  

---

## Reference

- Vendor: `vendor/gemvc/http-client/README.md`, `CHANGELOG.md`  
- Ecosystem: [ecosystem.md](ecosystem.md)  
- Signatures: [CORE_REFERENCE.md](../ai/CORE_REFERENCE.md#http-client-gemvchttp-client)  

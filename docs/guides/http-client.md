# GEMVC HTTP Client — `gemvc/http-client`

**Audience:** calling **other** HTTP APIs (outbound) from GEMVC apps or any PHP project.

**Related:** [ecosystem.md](ecosystem.md) · inbound Request: [http-lifecycle.md](http-lifecycle.md) · vendor `vendor/gemvc/http-client/README.md`

---

## Why it matters

**`gemvc/http-client` is a core GEMVC package** (required by `gemvc/library`). Use it for microservice-to-microservice calls, webhooks, and fire-and-forget logging — **not** `curl_*` spaghetti or Guzzle reinvented for every service.

Do **not** confuse with inbound `Gemvc\Http\Request` (library).  
- **Inbound** = request into your API  
- **Outbound** = `Gemvc\Http\Client\*` calling someone else  

Library wrappers `ApiCall` / `AsyncApiCall` use this package internally.

---

## Reading map (AI)

| Need | Class |
|------|--------|
| Sync GET/POST/… | `Gemvc\Http\Client\HttpClient` |
| Concurrent / fire-and-forget | `AsyncHttpClient` |
| OpenSwoole coroutines | `SwooleHttpClient` (auto when in Swoole) |
| Full API | `vendor/gemvc/http-client/README.md` |

**AI rule:** Prefer `gemvc/http-client` over inventing HTTP clients in `app/`.

---

## Quick start

```php
use Gemvc\Http\Client\HttpClient;
use Gemvc\Http\Client\AsyncHttpClient;

// Sync
$client = new HttpClient();
$client->setTimeouts(10, 30)->setRetries(3, 200, [500, 502, 503]);
$body = $client->get('https://api.example.com/users', ['page' => 1]);
$client->post('https://api.example.com/users', ['name' => 'John']);

// Async / concurrent
$async = new AsyncHttpClient();
$async->setMaxConcurrency(5)
    ->addGet('users', 'https://api.example.com/users')
    ->addGet('posts', 'https://api.example.com/posts')
    ->executeAll();

// Fire-and-forget (APM, analytics — non-blocking)
$async->addPost('log', 'https://apm.example.com/log', $payload)->fireAndForget();
```

Environment-aware: Apache/Nginx use curl-based clients; OpenSwoole can use coroutine `SwooleHttpClient`.

---

## Do / Don’t

**Do**

- Use for outbound microservice HTTP  
- Use retries / timeouts / typed exceptions from the package  
- Read vendor README for exception types and SSL options  

**Don’t**

- Confuse with `definePostSchema` / inbound Request  
- Hand-roll `curl_multi` for concurrent calls  
- Assume inbound JWT helpers live in http-client (they don’t)  

---

## Reference

- Vendor: `vendor/gemvc/http-client/README.md`, `CHANGELOG.md`  
- Ecosystem: [ecosystem.md](ecosystem.md)  
- Library facade: `Gemvc\Http\ApiCall`, `AsyncApiCall`  
- Signatures: [CORE_REFERENCE.md](../ai/CORE_REFERENCE.md#http-client-gemvchttp-client)  

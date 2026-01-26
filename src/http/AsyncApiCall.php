<?php
namespace Gemvc\Http;

use Gemvc\Core\WebserverDetector;
use Gemvc\Http\Client\AsyncHttpClient;
use Gemvc\Http\Client\SwooleHttpClient;

/**
 * High-Performance Async API Call Class for PHP 8.4
 * 
 * Features:
 * - Concurrent request execution using curl_multi
 * - Connection pooling and reuse
 * - Batch request processing
 * - Fire-and-forget mode for non-blocking background tasks
 * - Full backward compatibility with ApiCall configuration
 * - PHP 8.4 typed properties and union types
 * 
 * Performance improvements:
 * - Execute multiple requests in parallel
 * - Reduced latency for batch operations
 * - Better resource utilization
 * - Automatic connection management
 * 
 * @package Gemvc\Http
 * 
 * @example
 * // Example 1: Basic concurrent requests
 * $async = new AsyncApiCall();
 * $async->setMaxConcurrency(5)
 *       ->setTimeouts(10, 30);
 * 
 * $async->addGet('users', 'https://api.example.com/users', ['page' => 1])
 *       ->addGet('posts', 'https://api.example.com/posts', ['limit' => 10])
 *       ->addPost('create', 'https://api.example.com/create', ['name' => 'Test']);
 * 
 * $results = $async->executeAll();
 * 
 * foreach ($results as $requestId => $result) {
 *     if ($result['success']) {
 *         echo "Request {$requestId}: {$result['body']}\n";
 *         echo "Duration: {$result['duration']}s\n";
 *     }
 * }
 * 
 * @example
 * // Example 2: Fire-and-forget for APM logging (NON-BLOCKING)
 * // Perfect for logging, analytics, or any background tasks
 * // This will NOT block your main application response
 * 
 * public function someApiMethod(): JsonResponse
 * {
 *     // Your main business logic
 *     $data = $this->processData();
 *     $response = Response::success($data, 1, "Success");
 *     
 *     // Fire-and-forget APM logging (NON-BLOCKING)
 *     $apm = new AsyncApiCall();
 *     $apm->setTimeouts(2, 5) // Short timeouts for logging
 *         ->addPost('apm-log', 'https://apm.example.com/log', [
 *             'endpoint' => '/api/User/list',
 *             'duration' => 0.123,
 *             'status' => 200,
 *             'timestamp' => time()
 *         ])
 *         ->fireAndForget(); // ⚡ Does NOT block!
 *     
 *     // Response is sent immediately, APM logging happens in background
 *     return $response;
 * }
 * 
 * @example
 * // Example 3: Different request types
 * $async = new AsyncApiCall();
 * 
 * // GET request
 * $async->addGet('req1', 'https://api.example.com/data', ['id' => 123]);
 * 
 * // POST with JSON data
 * $async->addPost('req2', 'https://api.example.com/create', ['name' => 'John']);
 * 
 * // POST form data (application/x-www-form-urlencoded)
 * $async->addPostForm('req3', 'https://api.example.com/submit', [
 *     'email' => 'user@example.com',
 *     'name' => 'John Doe'
 * ]);
 * 
 * // POST multipart with files
 * $async->addPostMultipart('req4', 'https://api.example.com/upload', 
 *     ['description' => 'My file'],
 *     ['file' => '/path/to/file.jpg']
 * );
 * 
 * // POST raw body
 * $async->addPostRaw('req5', 'https://api.example.com/xml', 
 *     '<xml>data</xml>',
 *     'application/xml'
 * );
 * 
 * $results = $async->executeAll();
 * 
 * @example
 * // Example 4: With callbacks
 * $async = new AsyncApiCall();
 * 
 * $async->addGet('users', 'https://api.example.com/users')
 *       ->onResponse('users', function($result, $requestId) {
 *           if ($result['success']) {
 *               // Process successful response
 *               $data = json_decode($result['body'], true);
 *               // Do something with $data
 *           } else {
 *               // Handle error
 *               error_log("Request {$requestId} failed: {$result['error']}");
 *           }
 *       });
 * 
 * $async->executeAll(); // Callbacks are executed automatically
 * 
 * @example
 * // Example 5: Configuration options
 * $async = new AsyncApiCall();
 * 
 * // Set maximum concurrent requests (default: 10)
 * $async->setMaxConcurrency(20);
 * 
 * // Set timeouts (connection, total)
 * $async->setTimeouts(10, 30);
 * 
 * // Configure SSL
 * $async->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem');
 * 
 * // Set custom user agent
 * $async->setUserAgent('MyApp/1.0');
 * 
 * // Configure retries (reserved for future implementation)
 * $async->setRetries(3, 200, [500, 502, 503]);
 * 
 * @example
 * // Example 6: APM logging best practice
 * private function logToAPM(array $metrics): void
 * {
 *     try {
 *         $apm = new AsyncApiCall();
 *         $apm->setTimeouts(1, 2) // Very short timeouts for logging
 *             ->addPost('apm', 'https://apm.example.com/metrics', $metrics)
 *             ->onResponse('apm', function($result, $id) {
 *                 if (!$result['success']) {
 *                     // Log error but don't throw - APM failures shouldn't break app
 *                     error_log("APM logging failed: " . $result['error']);
 *                 }
 *             })
 *             ->fireAndForget(); // Non-blocking
 *     } catch (\Throwable $e) {
 *         // Silently fail - don't let APM logging break your app
 *         error_log("APM logging error: " . $e->getMessage());
 *     }
 * }
 * 
 * // Usage in your API method:
 * public function list(): JsonResponse
 * {
 *     $startTime = microtime(true);
 *     
 *     // Your main logic
 *     $data = $this->fetchData();
 *     $response = Response::success($data, count($data), "Success");
 *     
 *     // Log to APM (non-blocking)
 *     $this->logToAPM([
 *         'endpoint' => '/api/User/list',
 *         'duration' => microtime(true) - $startTime,
 *         'status' => 200,
 *         'count' => count($data)
 *     ]);
 *     
 *     return $response; // Response sent immediately
 * }
 */
class AsyncApiCall
{
    /**
     * Internal HTTP client instance (lazy-loaded with environment detection)
     * 
     * @var AsyncHttpClient|SwooleHttpClient|null
     */
    private AsyncHttpClient|SwooleHttpClient|null $internalClient = null;

    /**
     * Maximum concurrent requests (0 = unlimited)
     */
    private int $maxConcurrency = 10;

    /**
     * Connection timeout in seconds
     */
    private int $connect_timeout = 30;

    /**
     * Total request timeout in seconds
     */
    private int $timeout = 60;

    /**
     * SSL client certificate path
     */
    private ?string $ssl_cert = null;

    /**
     * SSL client private key path
     */
    private ?string $ssl_key = null;

    /**
     * CA certificate path
     */
    private ?string $ssl_ca = null;

    /**
     * Verify peer flag
     */
    private bool $ssl_verify_peer = true;

    /**
     * Verify host setting: 0, 1, or 2
     */
    private int $ssl_verify_host = 2;

    // Note: Retry functionality reserved for future implementation
    // Currently, retries are not implemented in async execution
    // but properties are kept for API compatibility

    /**
     * Default user agent
     */
    private string $userAgent = 'gemserver-async';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize default configuration
    }

    // ---------------- Configuration Methods (Fluent API) ----------------

    /**
     * Set maximum concurrent requests
     */
    public function setMaxConcurrency(int $maxConcurrency): self
    {
        $this->maxConcurrency = max(1, $maxConcurrency);
        return $this;
    }

    /**
     * Configure connection and total timeouts (seconds)
     */
    public function setTimeouts(int $connectTimeout, int $timeout): self
    {
        $this->connect_timeout = max(1, $connectTimeout);
        $this->timeout = max(1, $timeout);
        return $this;
    }

    /**
     * Configure SSL client options
     */
    public function setSsl(?string $certPath, ?string $keyPath, ?string $caPath = null, bool $verifyPeer = true, int $verifyHost = 2): self
    {
        $this->ssl_cert = $certPath;
        $this->ssl_key = $keyPath;
        $this->ssl_ca = $caPath;
        $this->ssl_verify_peer = $verifyPeer;
        $this->ssl_verify_host = $verifyHost;
        return $this;
    }

    /**
     * Configure retry behavior (reserved for future implementation)
     * 
     * @param array<int> $retryOnHttpCodes
     */
    public function setRetries(int $maxRetries, int $retryDelayMs = 200, array $retryOnHttpCodes = []): self
    {
        // Retry functionality will be implemented in future version
        // Currently kept for API compatibility
        return $this;
    }

    /**
     * Enable/disable retry on network errors (reserved for future implementation)
     */
    public function retryOnNetworkError(bool $retry): self
    {
        // Retry functionality will be implemented in future version
        // Currently kept for API compatibility
        return $this;
    }

    /**
     * Set custom user agent
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    // ---------------- Request Building Methods ----------------

    /**
     * Add a GET request to the queue
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<string, mixed> $queryParams Query parameters
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addGet(string $requestId, string $url, array $queryParams = [], array $headers = []): self
    {
        $client = $this->getInternalClient();
        $client->addGet($requestId, $url, $queryParams, $headers);
        return $this;
    }

    /**
     * Add a POST request to the queue
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<mixed> $postData POST data (will be JSON encoded)
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPost(string $requestId, string $url, array $postData = [], array $headers = []): self
    {
        $client = $this->getInternalClient();
        $client->addPost($requestId, $url, $postData, $headers);
        return $this;
    }

    /**
     * Add a PUT request to the queue
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<mixed> $putData PUT data (will be JSON encoded)
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPut(string $requestId, string $url, array $putData = [], array $headers = []): self
    {
        $client = $this->getInternalClient();
        $client->addPut($requestId, $url, $putData, $headers);
        return $this;
    }

    /**
     * Add a POST form request (application/x-www-form-urlencoded)
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<string, mixed> $formFields Form fields
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPostForm(string $requestId, string $url, array $formFields = [], array $headers = []): self
    {
        $client = $this->getInternalClient();
        if (method_exists($client, 'addPostForm')) {
            $client->addPostForm($requestId, $url, $formFields, $headers);
        } else {
            // SwooleHttpClient doesn't have addPostForm, use addRequest with options
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $client->addRequest($requestId, $url, 'POST', $formFields, $headers, ['form' => true]);
        }
        return $this;
    }

    /**
     * Add a POST multipart request with files
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<string, mixed> $formFields Form fields
     * @param array<string, string> $files Map of field => filePath
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPostMultipart(string $requestId, string $url, array $formFields = [], array $files = [], array $headers = []): self
    {
        $client = $this->getInternalClient();
        if (method_exists($client, 'addPostMultipart')) {
            $client->addPostMultipart($requestId, $url, $formFields, $files, $headers);
        } else {
            // SwooleHttpClient doesn't have addPostMultipart, use addRequest with options
            $client->addRequest($requestId, $url, 'POST', $formFields, $headers, ['multipart' => true, 'files' => $files]);
        }
        return $this;
    }

    /**
     * Add a POST raw request
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param string $rawBody Raw request body
     * @param string $contentType Content-Type header
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPostRaw(string $requestId, string $url, string $rawBody, string $contentType, array $headers = []): self
    {
        $client = $this->getInternalClient();
        if (method_exists($client, 'addPostRaw')) {
            $client->addPostRaw($requestId, $url, $rawBody, $contentType, $headers);
        } else {
            // SwooleHttpClient doesn't have addPostRaw, use addRequest with options
            $headers['Content-Type'] = $contentType;
            $client->addRequest($requestId, $url, 'POST', [], $headers, ['raw' => $rawBody]);
        }
        return $this;
    }

    /**
     * Add a custom request with full control
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param string $method HTTP method
     * @param array<mixed> $data Request data
     * @param array<string, string> $headers Custom headers
     * @param array<string, mixed> $options Custom options
     * @return self
     */
    public function addRequest(string $requestId, string $url, string $method = 'GET', array $data = [], array $headers = [], array $options = []): self
    {
        $client = $this->getInternalClient();
        $client->addRequest($requestId, $url, $method, $data, $headers, $options);
        return $this;
    }

    /**
     * Set response callback for a specific request
     * 
     * @param string $requestId Request identifier
     * @param callable $callback Callback function(response, requestId)
     * @return self
     */
    public function onResponse(string $requestId, callable $callback): self
    {
        $client = $this->getInternalClient();
        $client->onResponse($requestId, $callback);
        return $this;
    }

    // ---------------- Execution Methods ----------------

    /**
     * Execute all queued requests concurrently
     * 
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float}>
     */
    public function executeAll(): array
    {
        $client = $this->getInternalClient();
        $results = $client->executeAll();
        
        // Normalize results to match expected format (package may return more detailed format)
        return $this->normalizeResults($results);
    }

    /**
     * Execute requests and wait for all to complete (alias for executeAll)
     * 
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float}>
     */
    public function waitForAll(): array
    {
        return $this->executeAll();
    }

    /**
     * Fire and forget - Execute requests in background without blocking
     * 
     * This method is perfect for APM logging, analytics, or any non-critical
     * background tasks. It will NOT block your main application response.
     * 
     * For Apache/Nginx: Uses fastcgi_finish_request() to send response first
     * For OpenSwoole: Executes in background using native coroutines
     * 
     * @return bool True if background execution was initiated
     */
    public function fireAndForget(): bool
    {
        $client = $this->getInternalClient();
        return $client->fireAndForget();
    }

    /**
     * Clear the request queue
     * 
     * @return self
     */
    public function clearQueue(): self
    {
        $client = $this->getInternalClient();
        $client->clearQueue();
        return $this;
    }

    /**
     * Get queue size
     */
    public function getQueueSize(): int
    {
        $client = $this->getInternalClient();
        return $client->getQueueSize();
    }

    // ---------------- Internal Client Management ----------------

    /**
     * Get or create internal HTTP client instance with environment detection
     * 
     * @return AsyncHttpClient|SwooleHttpClient
     */
    private function getInternalClient(): AsyncHttpClient|SwooleHttpClient
    {
        if ($this->internalClient === null) {
            // Use WebserverDetector to choose implementation
            if (WebserverDetector::isSwoole()) {
                $this->internalClient = new SwooleHttpClient();
            } else {
                $this->internalClient = new AsyncHttpClient();
            }
            
            // Copy configuration
            $this->syncConfigurationToInternal();
        }
        
        // PHPStan: internalClient is guaranteed to be non-null after initialization above
        /** @var AsyncHttpClient|SwooleHttpClient $client */
        $client = $this->internalClient;
        return $client;
    }

    /**
     * Sync configuration from this instance to internal client
     */
    private function syncConfigurationToInternal(): void
    {
        if ($this->internalClient === null) {
            return;
        }

        // Sync timeouts
        $this->internalClient->setTimeouts($this->connect_timeout, $this->timeout);

        // Sync SSL
        if ($this->ssl_cert || $this->ssl_key || $this->ssl_ca) {
            $this->internalClient->setSsl(
                $this->ssl_cert,
                $this->ssl_key,
                $this->ssl_ca,
                $this->ssl_verify_peer,
                $this->ssl_verify_host
            );
        }

        // Sync max concurrency
        $this->internalClient->setMaxConcurrency($this->maxConcurrency);

        // Sync user agent
        $this->internalClient->setUserAgent($this->userAgent);
    }

    /**
     * Normalize results from package to match expected format
     * 
     * @param array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float, exception?: mixed, exception_type?: string|null}> $packageResults
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float}>
     */
    private function normalizeResults(array $packageResults): array
    {
        $normalized = [];
        foreach ($packageResults as $requestId => $result) {
            $normalized[$requestId] = [
                'success' => $result['success'],
                'body' => $result['body'],
                'http_code' => $result['http_code'],
                'error' => $result['error'],
                'duration' => $result['duration']
            ];
        }
        return $normalized;
    }
}
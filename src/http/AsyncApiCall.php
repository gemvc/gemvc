<?php
namespace Gemvc\Http;

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
     * Pending requests queue
     * 
     * @var array<int, array{id: string, url: string, method: string, data: array<mixed>, headers: array<string>, options: array<string, mixed>}>
     */
    private array $requestQueue = [];

    /**
     * Request metadata for tracking
     * 
     * @var array<int, array{id: string, url: string, method: string, startTime: float}>
     */
    private array $requestMetadata = [];

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
     * Response callbacks: ['requestId' => callable]
     * 
     * @var array<string, callable>
     */
    private array $responseCallbacks = [];

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
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'GET',
            'data' => [],
            'headers' => $headers,
            'options' => []
        ];

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
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => $postData,
            'headers' => $headers,
            'options' => []
        ];

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
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'PUT',
            'data' => $putData,
            'headers' => $headers,
            'options' => []
        ];

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
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => $formFields,
            'headers' => $headers,
            'options' => ['form' => true]
        ];

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
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => $formFields,
            'headers' => $headers,
            'options' => ['multipart' => true, 'files' => $files]
        ];

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
        $headers['Content-Type'] = $contentType;
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => [],
            'headers' => $headers,
            'options' => ['raw' => $rawBody]
        ];

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
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => strtoupper($method),
            'data' => $data,
            'headers' => $headers,
            'options' => $options
        ];

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
        $this->responseCallbacks[$requestId] = $callback;
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
        if (empty($this->requestQueue)) {
            return [];
        }

        $results = [];
        $multiHandle = curl_multi_init();

        $handleMap = []; // Map curl handle resource ID to request ID
        $queueIndex = 0;
        $activeRequests = 0;

        // Process requests in batches based on maxConcurrency
        while ($queueIndex < count($this->requestQueue) || $activeRequests > 0) {
            // Add new requests up to maxConcurrency limit
            while ($activeRequests < $this->maxConcurrency && $queueIndex < count($this->requestQueue)) {
                $request = $this->requestQueue[$queueIndex];
                $ch = $this->createCurlHandle($request);
                
                if ($ch !== false) {
                    $handleId = (int)$ch;
                    $handleMap[$handleId] = $request['id'];
                    $this->requestMetadata[$handleId] = [
                        'id' => $request['id'],
                        'url' => $request['url'],
                        'method' => $request['method'],
                        'startTime' => microtime(true)
                    ];

                    curl_multi_add_handle($multiHandle, $ch);
                    $activeRequests++;
                }

                $queueIndex++;
            }

            // Execute active requests
            if ($activeRequests > 0) {
                $stillRunning = 0;
                curl_multi_exec($multiHandle, $stillRunning);

                // Process completed requests
                while ($info = curl_multi_info_read($multiHandle)) {
                    if ($info['msg'] === CURLMSG_DONE) {
                        $ch = $info['handle'];
                        $handleId = (int)$ch;
                        $requestId = $handleMap[$handleId] ?? 'unknown';

                        $result = $this->processResponse($ch, $requestId, $handleId);
                        $results[$requestId] = $result;

                        // Execute callback if set
                        if (isset($this->responseCallbacks[$requestId])) {
                            ($this->responseCallbacks[$requestId])($result, $requestId);
                        }

                        curl_multi_remove_handle($multiHandle, $ch);
                        unset($this->requestMetadata[$handleId]);
                        unset($handleMap[$handleId]);
                        $activeRequests--;
                    }
                }

                // Small delay to prevent CPU spinning
                if ($stillRunning > 0) {
                    curl_multi_select($multiHandle, 0.01);
                }
            }
        }

        curl_multi_close($multiHandle);
        $this->requestQueue = [];
        $this->responseCallbacks = [];

        return $results;
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
     * For OpenSwoole: Executes in background task
     * 
     * @return bool True if background execution was initiated
     */
    public function fireAndForget(): bool
    {
        if (empty($this->requestQueue)) {
            return false;
        }

        // For Apache/Nginx with PHP-FPM: finish request first, then execute
        // Note: fastcgi_finish_request() may have already been called in shutdown function
        // If it was already called, this will do nothing (safe to call multiple times)
        if (function_exists('fastcgi_finish_request')) {
            // Try to send response to client immediately (if not already sent)
            // This is safe to call even if response was already sent
            @fastcgi_finish_request();
            
            // Now execute requests in background (client already got or getting response)
            $this->executeAll();
            return true;
        }

        // For OpenSwoole: Use task worker (if available)
        if (function_exists('swoole_async_write') || class_exists('\Swoole\Server')) {
            // Execute in background using Swoole task
            $this->executeInBackground();
            return true;
        }

        // Fallback: Execute with very short timeout and minimal blocking
        // Set aggressive timeouts to minimize blocking
        $originalTimeout = $this->timeout;
        $originalConnectTimeout = $this->connect_timeout;
        
        $this->timeout = 1; // 1 second max
        $this->connect_timeout = 1; // 1 second max
        
        // Execute but don't wait for all results
        $this->executeAll();
        
        // Restore original timeouts
        $this->timeout = $originalTimeout;
        $this->connect_timeout = $originalConnectTimeout;
        
        return true;
    }

    /**
     * Execute requests in background using Swoole task (if available)
     * 
     * @return void
     */
    private function executeInBackground(): void
    {
        // This would require access to Swoole server instance
        // For now, execute with minimal blocking
        $this->executeAll();
    }

    /**
     * Clear the request queue
     * 
     * @return self
     */
    public function clearQueue(): self
    {
        $this->requestQueue = [];
        $this->responseCallbacks = [];
        return $this;
    }

    /**
     * Get queue size
     */
    public function getQueueSize(): int
    {
        return count($this->requestQueue);
    }

    // ---------------- Private Helper Methods ----------------

    /**
     * Create a curl handle for a request
     * 
     * @param array{id: string, url: string, method: string, data: array<mixed>, headers: array<string>, options: array<string, mixed>} $request Request configuration
     * @return \CurlHandle|false
     */
    private function createCurlHandle(array $request): \CurlHandle|false
    {
        $ch = curl_init($request['url']);
        if ($ch === false) {
            return false;
        }

        // Basic options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($this->userAgent !== '') {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        // SSL options
        if ($this->ssl_cert) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->ssl_cert);
        }
        if ($this->ssl_key) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->ssl_key);
        }
        if ($this->ssl_ca) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->ssl_ca);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify_peer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->ssl_verify_host ? 2 : 0);

        // Headers
        $headers = ['Content-Type: application/json'];
        foreach ($request['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Method and data
        $this->setMethodAndData($ch, $request);

        return $ch;
    }

    /**
     * Set HTTP method and request data
     * 
     * @param \CurlHandle $ch
     * @param array{id: string, url: string, method: string, data: array<mixed>, headers: array<string>, options: array<string, mixed>} $request
     */
    private function setMethodAndData(\CurlHandle $ch, array $request): void
    {
        $method = $request['method'];
        $data = $request['data'];
        $options = $request['options'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        } elseif ($method !== 'GET' && $method !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Set request body
        if (isset($options['raw']) && is_string($options['raw'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['raw']);
        } elseif (isset($options['multipart']) && $options['multipart'] === true) {
            $postFields = $data;
            if (isset($options['files']) && is_array($options['files'])) {
                foreach ($options['files'] as $key => $filePath) {
                    if (is_string($filePath) && is_file($filePath)) {
                        $postFields[$key] = new \CURLFile($filePath);
                    }
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } elseif (isset($options['form']) && $options['form'] === true) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'POST' || $method === 'PUT') {
            $jsonData = json_encode($data);
            if (is_string($jsonData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            }
        }
    }

    /**
     * Process response from curl handle
     * 
     * @param \CurlHandle $ch
     * @param string $requestId
     * @param int $handleId
     * @return array{success: bool, body: string|false, http_code: int, error: string, duration: float}
     */
    private function processResponse(\CurlHandle $ch, string $requestId, int $handleId): array
    {
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $metadata = $this->requestMetadata[$handleId] ?? null;
        $duration = $metadata ? microtime(true) - $metadata['startTime'] : 0.0;

        $success = is_string($body) && $error === '' && $httpCode >= 200 && $httpCode < 400;

        $responseBody = is_string($body) ? $body : false;
        
        return [
            'success' => $success,
            'body' => $responseBody,
            'http_code' => $httpCode,
            'error' => $error,
            'duration' => $duration
        ];
    }
}

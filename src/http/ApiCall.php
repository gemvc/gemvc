<?php
namespace Gemvc\Http;

use Gemvc\Http\Client\HttpClient;

/**
 * Create request and send it to remote API.
 *
 * Backward-compatible enhancements:
 * - Optional timeouts: setTimeouts($connectTimeout, $timeout)
 * - Optional SSL client cert/key: setSsl($cert, $key, $ca = null, $verifyPeer = true, $verifyHost = 2)
 * - Optional retries with backoff: setRetries($maxRetries, $retryDelayMs = 200, $retryOnHttpCodes = [429, 500, 502, 503, 504])
 * - Optional network retry toggle: retryOnNetworkError(true|false)
 * - New helpers for flexible bodies (opt-in): postForm(), postMultipart(), postRaw()
 *
 * Defaults preserve legacy behavior:
 * - No custom timeouts/retries unless explicitly set
 * - No SSL client options unless explicitly set
 * - Legacy header behavior is preserved (including overwrite logic in setAuthorization)
 * 
 * @internal Uses Gemvc\Http\Client\HttpClient internally for all HTTP operations
 */
class ApiCall
{
    /**
     * Internal HTTP client instance (lazy-loaded)
     * 
     * @var HttpClient|null
     */
    private ?HttpClient $internalClient = null;
    /**
     * Last cURL error message (empty string if none).
     * Defaults to 'call not initialized' until call() runs.
     */
    public ?string $error;

    /**
     * HTTP response code from last request (0 if not executed).
     */
    public int $http_response_code;

    /**
     * User headers as an associative array: ['Header-Name' => 'value']
     * Legacy name and type kept for backward compatibility.
     *
     * @var array<string>
     */
    public array $header;

    /**
     * HTTP method. One of GET, POST, PUT, or custom.
     */
    public string $method;

    /**
     * User payload for legacy JSON flow.
     *
     * @var array<mixed>
     */
    public array $data;

    /**
     * Authorization header (legacy behavior):
     * - If string: setAuthorization() will overwrite previous header list
     * - If array|string[]: not used by legacy logic; kept for compatibility
     *
     * @var null|string|array<string>
     */
    public null|string|array $authorizationHeader;

    /**
     * Response body as string on success, or false on failure.
     */
    public bool|string $responseBody;

    /**
     * Files for legacy multipart flow: ['field' => '/path/to/file']
     *
     * @var array<mixed>
     */
    public array $files;

    // ----- New (opt-in) capabilities: preserved defaults keep legacy behavior -----

    /**
     * Connection timeout in seconds (0 keeps legacy behavior).
     */
    private int $connect_timeout = 0;

    /**
     * Total request timeout in seconds (0 keeps legacy behavior).
     */
    private int $timeout = 0;

    /**
     * SSL client certificate path (optional).
     */
    private ?string $ssl_cert = null;

    /**
     * SSL client private key path (optional).
     */
    private ?string $ssl_key = null;

    /**
     * CA certificate path (optional).
     */
    private ?string $ssl_ca = null;

    /**
     * Verify peer flag (true by default).
     */
    private bool $ssl_verify_peer = true;

    /**
     * Verify host setting: 0, 1, or 2 (2 by default).
     */
    private int $ssl_verify_host = 2;

    /**
     * Maximum retry attempts (0 = no retries).
     */
    private int $max_retries = 0;

    /**
     * Delay between retries in milliseconds.
     */
    private int $retry_delay_ms = 200;

    /**
     * HTTP codes that trigger a retry (opt-in).
     *
     * @var array<int>
     */
    private array $retry_on_http_codes = [429, 500, 502, 503, 504];

    /**
     * Retry on network error (cURL error) if true.
     */
    private bool $retry_on_network_error = true;

    /**
     * Raw request body (when using postRaw()).
     */
    private ?string $rawBody = null;

    /**
     * Form fields (application/x-www-form-urlencoded or multipart/form-data).
     *
     * @var array<string,mixed>|null
     */
    private ?array $formFields = null;

    public function __construct()
    {
        $this->error = 'call not initialized';
        $this->http_response_code = 0;
        $this->data = [];
        $this->authorizationHeader = null;
        $this->header = [];
        $this->files = [];
        $this->responseBody = false;
        $this->method = 'GET';
    }

    // ---------------- New helper APIs (opt-in, non-breaking) ----------------

    /**
     * Configure connection and total timeouts (seconds).
     * Defaults (0) keep legacy behavior.
     */
    public function setTimeouts(int $connectTimeout, int $timeout): self
    {
        $this->connect_timeout = max(0, $connectTimeout);
        $this->timeout = max(0, $timeout);
        return $this;
    }

    /**
     * Configure SSL client options.
     * If not set, legacy behavior remains unchanged.
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
     * Configure retry behavior (opt-in).
     *
     * @param array<int> $retryOnHttpCodes
     */
    public function setRetries(int $maxRetries, int $retryDelayMs = 200, array $retryOnHttpCodes = []): self
    {
        $this->max_retries = max(0, $maxRetries);
        $this->retry_delay_ms = max(0, $retryDelayMs);
        if (!empty($retryOnHttpCodes)) {
            $this->retry_on_http_codes = array_values(array_unique(array_map('intval', $retryOnHttpCodes)));
        }
        return $this;
    }

    /**
     * Enable/disable retry on network (cURL) errors.
     */
    public function retryOnNetworkError(bool $retry): self
    {
        $this->retry_on_network_error = $retry;
        return $this;
    }

    /**
     * POST with application/x-www-form-urlencoded body (opt-in).
     * 
     * @param array<string, mixed> $fields
     */
    public function postForm(string $remoteApiUrl, array $fields = []): string|false
    {
        $this->method = 'POST';
        $this->formFields = $fields;
        $this->rawBody = null;
        
        $client = $this->getInternalClient();
        $this->syncHeadersToInternal();
        
        $result = $client->postForm($remoteApiUrl, $fields);
        
        $this->syncResponseFromInternal();
        
        return $result;
    }

    /**
     * POST multipart/form-data with files (opt-in).
     *
     * @param array<string, mixed> $fields
     * @param array<string, string> $files Map of field => filePath
     */
    public function postMultipart(string $remoteApiUrl, array $fields = [], array $files = []): string|false
    {
        $this->method = 'POST';
        $this->formFields = $fields;
        $this->files = $files;
        $this->rawBody = null;
        
        $client = $this->getInternalClient();
        $this->syncHeadersToInternal();
        
        $result = $client->postMultipart($remoteApiUrl, $fields, $files);
        
        $this->syncResponseFromInternal();
        
        return $result;
    }

    /**
     * POST with raw body and explicit content type (opt-in).
     */
    public function postRaw(string $remoteApiUrl, string $rawBody, string $contentType): string|false
    {
        $this->method = 'POST';
        $this->rawBody = $rawBody;
        $this->formFields = null;
        $this->header['Content-Type'] = $contentType;
        
        $client = $this->getInternalClient();
        $this->syncHeadersToInternal();
        
        $result = $client->postRaw($remoteApiUrl, $rawBody, $contentType);
        
        $this->syncResponseFromInternal();
        
        return $result;
    }

    // ---------------- Existing public API (unchanged behavior) ----------------

    /**
     * Perform a GET request.
     *
     * @param string $remoteApiUrl
     * @param array<string> $queryParams
     */
    public function get(string $remoteApiUrl, array $queryParams = []): string|false
    {
        $this->method = 'GET';
        $this->data = $queryParams;
        $this->rawBody = null;
        $this->formFields = null;

        $client = $this->getInternalClient();
        $this->syncHeadersToInternal();
        
        $result = $client->get($remoteApiUrl, $queryParams);
        
        $this->syncResponseFromInternal();
        
        return $result;
    }

    /**
     * Perform a POST request (legacy JSON behavior preserved).
     *
     * @param string $remoteApiUrl
     * @param array<mixed> $postData
     */
    public function post(string $remoteApiUrl, array $postData = []): string|false
    {
        $this->method = 'POST';
        $this->data = $postData;
        $this->rawBody = null;
        $this->formFields = null;
        
        $client = $this->getInternalClient();
        $this->syncHeadersToInternal();
        
        $result = $client->post($remoteApiUrl, $postData);
        
        $this->syncResponseFromInternal();
        
        return $result;
    }

    /**
     * Perform a PUT request (legacy JSON behavior preserved).
     *
     * @param string $remoteApiUrl
     * @param array<mixed> $putData
     */
    public function put(string $remoteApiUrl, array $putData = []): string|false
    {
        $this->method = 'PUT';
        $this->data = $putData;
        $this->rawBody = null;
        $this->formFields = null;
        
        $client = $this->getInternalClient();
        $this->syncHeadersToInternal();
        
        $result = $client->put($remoteApiUrl, $putData);
        
        $this->syncResponseFromInternal();
        
        return $result;
    }

    // ---------------- Internal Client Management ---------------- 

    /**
     * Get or create internal HTTP client instance
     * 
     * @return HttpClient
     */
    private function getInternalClient(): HttpClient
    {
        if ($this->internalClient === null) {
            $this->internalClient = new HttpClient();
            $this->syncConfigurationToInternal();
        }
        return $this->internalClient;
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
        if ($this->connect_timeout > 0 || $this->timeout > 0) {
            $this->internalClient->setTimeouts($this->connect_timeout, $this->timeout);
        }

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

        // Sync retries
        if ($this->max_retries > 0) {
            $this->internalClient->setRetries(
                $this->max_retries,
                $this->retry_delay_ms,
                $this->retry_on_http_codes
            );
            $this->internalClient->retryOnNetworkError($this->retry_on_network_error);
        }

        // Disable exceptions (maintain legacy behavior)
        $this->internalClient->throwExceptions(false);
    }

    /**
     * Sync headers from this instance to internal client
     */
    private function syncHeadersToInternal(): void
    {
        $client = $this->getInternalClient();
        $client->header = $this->header;
        $client->authorizationHeader = $this->authorizationHeader;
    }

    /**
     * Sync response from internal client back to this instance
     */
    private function syncResponseFromInternal(): void
    {
        $client = $this->getInternalClient();
        $this->responseBody = $client->responseBody;
        $this->http_response_code = $client->http_response_code;
        $this->error = $client->error ?? '';

        // Sync errors if any (convert HttpClientException to legacy error format)
        if ($client->hasErrors()) {
            $lastError = $client->getLastError();
            if ($lastError !== null && empty($this->error)) {
                $this->error = $lastError->getMessage();
            }
        }
    }
}

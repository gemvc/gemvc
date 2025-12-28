<?php
namespace Gemvc\Helper;

/**
 * TraceKit Model - Custom Lightweight APM Implementation
 * 
 * This is a custom lightweight implementation of TraceKit APM using GEMVC's native capabilities.
 * It provides distributed tracing and performance monitoring without heavy dependencies.
 * 
 * Features:
 * - Lightweight (no OpenTelemetry, no 23 packages)
 * - Non-blocking trace sending (uses GEMVC's AsyncApiCall)
 * - Simple span tracking with stack-based context
 * - Custom JSON trace payload
 * - Graceful error handling
 * 
 * @package App\Model
 */

class TraceKitModel
{
    /**
     * Static registry to store the current active TraceKitModel instance
     * This allows Controller and UniversalQueryExecuter to access the same instance
     * that was created by ApiService, ensuring all spans share the same traceId
     * 
     * @var TraceKitModel|null
     */
    private static ?TraceKitModel $currentInstance = null;
    
    // Configuration
    private string $apiKey;
    private string $serviceName;
    private string $endpoint;
    private bool $enabled;
    private float $sampleRate;
    private bool $traceResponse;
    private bool $traceDbQuery;
    private bool $traceRequestBody;
    
    // Active span tracking (simple stack for context propagation)
    private array $spanStack = [];
    
    // Current trace data
    private array $spans = [];
    private ?string $traceId = null;
    
    // Queue for batch sending
    private array $traceQueue = [];
    
    // Constants - Span kinds (OpenTelemetry OTLP uses integers)
    public const SPAN_KIND_UNSPECIFIED = 0;
    public const SPAN_KIND_INTERNAL = 1;
    public const SPAN_KIND_SERVER = 2;
    public const SPAN_KIND_CLIENT = 3;
    public const SPAN_KIND_PRODUCER = 4;
    public const SPAN_KIND_CONSUMER = 5;
    
    // Status codes (OpenTelemetry OTLP uses string codes)
    public const STATUS_OK = 'OK';
    public const STATUS_ERROR = 'ERROR';
    
    /**
     * Initialize TraceKitModel
     * 
     * Configuration from environment variables:
     * - TRACEKIT_API_KEY: Your TraceKit API key
     * - TRACEKIT_SERVICE_NAME: Service name (default: 'gemvc-app')
     * - TRACEKIT_ENDPOINT: TraceKit endpoint (default: 'https://app.tracekit.dev/v1/traces')
     * - TRACEKIT_ENABLED: Enable/disable tracing (default: true)
     * - TRACEKIT_SAMPLE_RATE: Sample rate 0.0-1.0 (default: 1.0 = 100%)
     *   Examples: 1.0 = 100% (all requests), 0.05 = 5%, 0.1 = 10%
     *   NOTE: Errors are ALWAYS logged regardless of sample rate
     * - TRACEKIT_TRACE_RESPONSE: Include response data in traces (default: false)
     *   Set to 'true' to include JsonResponse data in span attributes
     * - TRACEKIT_TRACE_DB_QUERY: Enable database query tracing (default: false)
     *   Set to 'true' to trace all database queries with execution time and details
     * - TRACEKIT_TRACE_REQUEST_BODY: Include incoming request body in traces (default: false)
     *   Set to 'true' to include request body data (POST/PUT/PATCH) in span attributes
     * 
     * @param array $config Optional configuration override
     */
    public function __construct(array $config = [])
    {
        // Load configuration from environment or config array
        $this->apiKey = $config['api_key'] ?? $_ENV['TRACEKIT_API_KEY'] ?? '';
        $this->serviceName = $config['service_name'] ?? $_ENV['TRACEKIT_SERVICE_NAME'] ?? 'gemvc-app';
        $this->endpoint = $config['endpoint'] ?? $_ENV['TRACEKIT_ENDPOINT'] ?? 'https://app.tracekit.dev/v1/traces';
        
        // Parse enabled flag (string 'false' or boolean false)
        $enabled = $config['enabled'] ?? $_ENV['TRACEKIT_ENABLED'] ?? true;
        $this->enabled = is_string($enabled) ? ($enabled !== 'false' && $enabled !== '0') : (bool)$enabled;
        
        // Parse sample rate
        $sampleRate = $config['sample_rate'] ?? $_ENV['TRACEKIT_SAMPLE_RATE'] ?? 1.0;
        $this->sampleRate = is_numeric($sampleRate) ? (float)$sampleRate : 1.0;
        
        // Clamp sample rate between 0.0 and 1.0
        $this->sampleRate = max(0.0, min(1.0, $this->sampleRate));
        
        // Parse trace response flag (string 'true' or boolean true)
        $traceResponse = $config['trace_response'] ?? $_ENV['TRACEKIT_TRACE_RESPONSE'] ?? false;
        $this->traceResponse = is_string($traceResponse) ? ($traceResponse === 'true' || $traceResponse === '1') : (bool)$traceResponse;
        
        // Parse trace DB query flag (string 'true' or boolean true)
        $traceDbQuery = $config['trace_db_query'] ?? $_ENV['TRACEKIT_TRACE_DB_QUERY'] ?? false;
        $this->traceDbQuery = is_string($traceDbQuery) ? ($traceDbQuery === 'true' || $traceDbQuery === '1') : (bool)$traceDbQuery;
        
        // Parse trace request body flag (string 'true' or boolean true)
        // Note: User specified TRACEKIT_TRACE_RESPONSE_BODY in .env but wants to trace request body
        $traceRequestBody = $config['trace_request_body'] ?? $_ENV['TRACEKIT_TRACE_RESPONSE_BODY'] ?? $_ENV['TRACEKIT_TRACE_REQUEST_BODY'] ?? false;
        $this->traceRequestBody = is_string($traceRequestBody) ? ($traceRequestBody === 'true' || $traceRequestBody === '1') : (bool)$traceRequestBody;
        
        // Disable if no API key
        if (empty($this->apiKey)) {
            $this->enabled = false;
        }
        
        // Register this instance as the current active instance
        // This allows Controller and UniversalQueryExecuter to access the same instance
        self::$currentInstance = $this;
    }
    
    /**
     * Get the current active TraceKitModel instance
     * 
     * This is used by Controller and UniversalQueryExecuter to get the same instance
     * that was created by ApiService, ensuring all spans share the same traceId
     * 
     * @return TraceKitModel|null The current active instance or null if not set
     */
    public static function getCurrentInstance(): ?TraceKitModel
    {
        return self::$currentInstance;
    }
    
    /**
     * Clear the current active instance (called on flush)
     * 
     * @return void
     */
    public static function clearCurrentInstance(): void
    {
        self::$currentInstance = null;
    }
    
    /**
     * Check if tracing is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }
    
    /**
     * Check if response tracing is enabled
     * 
     * @return bool True if response data should be included in traces
     */
    public function shouldTraceResponse(): bool
    {
        return $this->traceResponse;
    }
    
    /**
     * Check if database query tracing is enabled
     * 
     * @return bool True if database queries should be traced
     */
    public function shouldTraceDbQuery(): bool
    {
        return $this->traceDbQuery;
    }
    
    /**
     * Check if request body tracing is enabled
     * 
     * @return bool True if request body should be included in traces
     */
    public function shouldTraceRequestBody(): bool
    {
        return $this->traceRequestBody;
    }
    
    /**
     * Check if request should be sampled
     * 
     * @param bool $forceSample Force sampling (e.g., for errors) - always returns true if enabled
     * @return bool
     */
    public function shouldSample(bool $forceSample = false): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        
        // Always sample errors regardless of sample rate
        if ($forceSample) {
            return true;
        }
        
        if ($this->sampleRate >= 1.0) {
            return true;
        }
        
        if ($this->sampleRate <= 0.0) {
            return false;
        }
        
        return (mt_rand() / mt_getrandmax()) < $this->sampleRate;
    }
    
    /**
     * Get current sample rate (0.0 to 1.0, where 1.0 = 100%)
     * 
     * @return float Sample rate as decimal (0.0 = 0%, 1.0 = 100%)
     */
    public function getSampleRate(): float
    {
        return $this->sampleRate;
    }
    
    /**
     * Get current sample rate as percentage (0 to 100)
     * 
     * @return float Sample rate as percentage (0.0 = 0%, 100.0 = 100%)
     */
    public function getSampleRatePercent(): float
    {
        return $this->sampleRate * 100.0;
    }
    
    /**
     * Start a new trace (root span) for a server request
     * 
     * This automatically generates a trace ID and creates the root span.
     * The span is automatically activated in the context (added to stack).
     * 
     * @param string $operationName Operation name (e.g., 'http-request')
     * @param array $attributes Optional attributes (e.g., ['http.method' => 'POST', 'http.url' => '/api/users'])
     * @param bool $forceSample Force sampling (e.g., for errors) - always traces regardless of sample rate
     * @return array Span data: ['span_id' => string, 'trace_id' => string, 'start_time' => int]
     */
    public function startTrace(string $operationName, array $attributes = [], bool $forceSample = false): array
    {
        if (!$this->shouldSample($forceSample)) {
            return [];
        }
        
        try {
            // Generate trace ID if not exists
            if ($this->traceId === null) {
                $this->traceId = $this->generateTraceId();
            }
            
            // Generate span ID
            $spanId = $this->generateSpanId();
            
            // Get current time in microseconds
            $startTime = $this->getMicrotime();
            
            // Create span data
            $spanData = [
                'trace_id' => $this->traceId,
                'span_id' => $spanId,
                'parent_span_id' => null, // Root span has no parent
                'name' => $operationName,
                'kind' => self::SPAN_KIND_SERVER,
                'start_time' => $startTime,
                'end_time' => null,
                'duration' => null,
                'attributes' => $this->normalizeAttributes($attributes),
                'status' => self::STATUS_OK,
                'events' => [],
            ];
            
            // Add to spans array
            $this->spans[] = $spanData;
            
            // Push to stack (activate in context)
            $this->pushSpan($spanData);
            
            // Return span reference
            return [
                'span_id' => $spanId,
                'trace_id' => $this->traceId,
                'start_time' => $startTime,
            ];
        } catch (\Throwable $e) {
            // Graceful degradation - log error but don't break application
            error_log("TraceKit: Failed to start trace: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Start a child span
     * 
     * Automatically inherits from the currently active span in context (stack).
     * If no active span exists, this creates a root span instead.
     * 
     * @param string $operationName Operation name (e.g., 'database-query', 'http-client-call')
     * @param array $attributes Optional attributes
     * @param int $kind Span kind: SPAN_KIND_SERVER (2), SPAN_KIND_CLIENT (3), or SPAN_KIND_INTERNAL (1) (default: SPAN_KIND_INTERNAL)
     * @return array Span data: ['span_id' => string, 'trace_id' => string, 'start_time' => int]
     */
    public function startSpan(string $operationName, array $attributes = [], int $kind = self::SPAN_KIND_INTERNAL): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        
        try {
            // Ensure trace ID exists
            if ($this->traceId === null) {
                $this->traceId = $this->generateTraceId();
            }
            
            // Get active span (parent)
            $activeSpan = $this->getActiveSpan();
            $parentSpanId = $activeSpan['span_id'] ?? null;
            
            // Generate span ID
            $spanId = $this->generateSpanId();
            
            // Get current time in microseconds
            $startTime = $this->getMicrotime();
            
            // Validate kind (must be valid OpenTelemetry span kind integer)
            if (!in_array($kind, [self::SPAN_KIND_UNSPECIFIED, self::SPAN_KIND_INTERNAL, self::SPAN_KIND_SERVER, self::SPAN_KIND_CLIENT, self::SPAN_KIND_PRODUCER, self::SPAN_KIND_CONSUMER])) {
                $kind = self::SPAN_KIND_INTERNAL;
            }
            
            // Create span data
            $spanData = [
                'trace_id' => $this->traceId,
                'span_id' => $spanId,
                'parent_span_id' => $parentSpanId,
                'name' => $operationName,
                'kind' => $kind,
                'start_time' => $startTime,
                'end_time' => null,
                'duration' => null,
                'attributes' => $this->normalizeAttributes($attributes),
                'status' => self::STATUS_OK,
                'events' => [],
            ];
            
            // Add to spans array
            $this->spans[] = $spanData;
            
            // Push to stack (activate in context)
            $this->pushSpan($spanData);
            
            // Return span reference
            return [
                'span_id' => $spanId,
                'trace_id' => $this->traceId,
                'start_time' => $startTime,
            ];
        } catch (\Throwable $e) {
            // Graceful degradation
            error_log("TraceKit: Failed to start span: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * End a span and detach it from context
     * 
     * @param array $spanData Span data returned from startTrace() or startSpan()
     * @param array $finalAttributes Optional attributes to add before ending
     * @param string|null $status Span status: 'OK' or 'ERROR' (default: 'OK')
     * @return void
     */
    public function endSpan(array $spanData, array $finalAttributes = [], ?string $status = self::STATUS_OK): void
    {
        if (empty($spanData) || !$this->isEnabled()) {
            return;
        }
        
        try {
            $spanId = $spanData['span_id'] ?? null;
            if (!$spanId) {
                return;
            }
            
            // Find span in spans array
            $spanIndex = null;
            foreach ($this->spans as $index => $span) {
                if (($span['span_id'] ?? null) === $spanId) {
                    $spanIndex = $index;
                    break;
                }
            }
            
            if ($spanIndex === null) {
                return;
            }
            
            // Get end time
            $endTime = $this->getMicrotime();
            $startTime = $this->spans[$spanIndex]['start_time'] ?? $endTime;
            $duration = $endTime - $startTime;
            
            // Update span
            $this->spans[$spanIndex]['end_time'] = $endTime;
            $this->spans[$spanIndex]['duration'] = $duration;
            
            // Add final attributes
            if (!empty($finalAttributes)) {
                $this->spans[$spanIndex]['attributes'] = array_merge(
                    $this->spans[$spanIndex]['attributes'] ?? [],
                    $this->normalizeAttributes($finalAttributes)
                );
            }
            
            // Set status
            if ($status === self::STATUS_ERROR) {
                $this->spans[$spanIndex]['status'] = self::STATUS_ERROR;
            } else {
                $this->spans[$spanIndex]['status'] = self::STATUS_OK;
            }
            
            // Pop from stack (detach from context)
            $this->popSpan();
        } catch (\Throwable $e) {
            // Graceful degradation
            error_log("TraceKit: Failed to end span: " . $e->getMessage());
        }
    }
    
    /**
     * Record an exception on a span
     * 
     * IMPORTANT: If no trace exists (spanData is empty), this will automatically
     * create a trace to ensure errors are ALWAYS logged, regardless of sample rate.
     * 
     * @param array $spanData Span data returned from startTrace() or startSpan() (can be empty for auto-creation)
     * @param \Throwable $exception Exception to record
     * @param string $operationName Operation name for auto-created trace (default: 'error-handler')
     * @param array $attributes Optional attributes for auto-created trace
     * @return array Updated span data (useful if trace was auto-created)
     */
    public function recordException(array $spanData, \Throwable $exception, string $operationName = 'error-handler', array $attributes = []): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        
        try {
            // If no trace exists, create one automatically (errors are always logged)
            if (empty($spanData) || empty($spanData['span_id'])) {
                // Add error context to attributes
                $errorAttributes = array_merge($attributes, [
                    'error.type' => get_class($exception),
                    'error.message' => $exception->getMessage(),
                    'error.code' => $exception->getCode(),
                ]);
                
                // Force sample = true to ensure error is always traced
                $spanData = $this->startTrace($operationName, $errorAttributes, true);
                
                if (empty($spanData)) {
                    // Failed to create trace, log and return
                    error_log("TraceKit: Failed to create trace for exception: " . $exception->getMessage());
                    return [];
                }
            }
            
            $spanId = $spanData['span_id'] ?? null;
            if (!$spanId) {
                return $spanData;
            }
            
            // Find span in spans array
            $spanIndex = null;
            foreach ($this->spans as $index => $span) {
                if (($span['span_id'] ?? null) === $spanId) {
                    $spanIndex = $index;
                    break;
                }
            }
            
            if ($spanIndex === null) {
                return $spanData;
            }
            
            // Format exception event
            $event = [
                'name' => 'exception',
                'time' => $this->getMicrotime(),
                'attributes' => [
                    'exception.type' => get_class($exception),
                    'exception.message' => $exception->getMessage(),
                    'exception.stacktrace' => $this->formatStackTrace($exception),
                ],
            ];
            
            // Add event to span
            if (!isset($this->spans[$spanIndex]['events'])) {
                $this->spans[$spanIndex]['events'] = [];
            }
            $this->spans[$spanIndex]['events'][] = $event;
            
            // Set span status to ERROR
            $this->spans[$spanIndex]['status'] = self::STATUS_ERROR;
            
            return $spanData;
        } catch (\Throwable $e) {
            // Graceful degradation
            error_log("TraceKit: Failed to record exception: " . $e->getMessage());
            return $spanData ?? [];
        }
    }
    
    /**
     * Add an event to a span
     * 
     * @param array $spanData Span data
     * @param string $eventName Event name
     * @param array $attributes Event attributes
     * @return void
     */
    public function addEvent(array $spanData, string $eventName, array $attributes = []): void
    {
        if (empty($spanData) || !$this->isEnabled()) {
            return;
        }
        
        try {
            $spanId = $spanData['span_id'] ?? null;
            if (!$spanId) {
                return;
            }
            
            // Find span in spans array
            $spanIndex = null;
            foreach ($this->spans as $index => $span) {
                if (($span['span_id'] ?? null) === $spanId) {
                    $spanIndex = $index;
                    break;
                }
            }
            
            if ($spanIndex === null) {
                return;
            }
            
            // Create event
            $event = [
                'name' => $eventName,
                'time' => $this->getMicrotime(),
                'attributes' => $this->normalizeAttributes($attributes),
            ];
            
            // Add event to span
            if (!isset($this->spans[$spanIndex]['events'])) {
                $this->spans[$spanIndex]['events'] = [];
            }
            $this->spans[$spanIndex]['events'][] = $event;
        } catch (\Throwable $e) {
            // Graceful degradation
            error_log("TraceKit: Failed to add event: " . $e->getMessage());
        }
    }
    
    /**
     * Flush traces (send to TraceKit service)
     * 
     * This method queues the current trace and sends it asynchronously using
     * GEMVC's AsyncApiCall (non-blocking). Multiple spans are batched into one request.
     * 
     * Uses register_shutdown_function to ensure traces are sent AFTER the HTTP response
     * is sent to the client, preventing empty response body issues.
     * 
     * @return void
     */
    public function flush(): void
    {
        if (!$this->isEnabled() || empty($this->spans) || $this->traceId === null) {
            error_log("TraceKit: Flush skipped - enabled: " . ($this->isEnabled() ? 'yes' : 'no') . ", spans: " . count($this->spans) . ", traceId: " . ($this->traceId ?? 'null'));
            return;
        }
        
        try {
            // Build trace payload
            $payload = $this->buildTracePayload();
            
            // Check for empty payload (using new OTLP format with resourceSpans)
            if (empty($payload) || empty($payload['resourceSpans'] ?? [])) {
                error_log("TraceKit: Empty payload after build, skipping send");
                return;
            }
            
            // Check if there are actual spans in the payload
            $spanCount = count($payload['resourceSpans'][0]['scopeSpans'][0]['spans'] ?? []);
            if ($spanCount === 0) {
                error_log("TraceKit: No spans in payload, skipping send");
                return;
            }
            
            error_log("TraceKit: Flush - Building payload with {$spanCount} spans");
            
            // Send traces using fire-and-forget (non-blocking)
            // This will send the HTTP response first, then send traces in background
            $this->sendTraces($payload);
            
            // Clear spans for next trace
            $this->spans = [];
            $this->traceId = null;
            
            // Clear current instance after flush (new request will create new instance)
            self::clearCurrentInstance();
        } catch (\Throwable $e) {
            // Graceful degradation - log error but don't break application
            error_log("TraceKit: Failed to flush traces: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }
    
    /**
     * Get current trace ID
     * 
     * @return string|null
     */
    public function getTraceId(): ?string
    {
        return $this->traceId;
    }
    
    /**
     * Get active span (for context propagation)
     * 
     * @return array|null
     */
    public function getActiveSpan(): ?array
    {
        return end($this->spanStack) ?: null;
    }
    
    // ==========================================
    // Private Helper Methods
    // ==========================================
    
    /**
     * Push span to stack (activate in context)
     * 
     * @param array $spanData
     * @return void
     */
    private function pushSpan(array $spanData): void
    {
        $this->spanStack[] = $spanData;
    }
    
    /**
     * Pop span from stack (detach from context)
     * 
     * @return array|null
     */
    private function popSpan(): ?array
    {
        return array_pop($this->spanStack);
    }
    
    /**
     * Generate trace ID (32 hex characters for OTLP JSON)
     * 
     * OpenTelemetry OTLP JSON uses hex strings for trace_id (not base64)
     * 
     * @return string 32-character hex string
     */
    private function generateTraceId(): string
    {
        // Generate 16 random bytes (128 bits) and convert to hex (32 characters)
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Generate span ID (16 hex characters for OTLP JSON)
     * 
     * OpenTelemetry OTLP JSON uses hex strings for span_id (not base64)
     * 
     * @return string 16-character hex string
     */
    private function generateSpanId(): string
    {
        // Generate 8 random bytes (64 bits) and convert to hex (16 characters)
        return bin2hex(random_bytes(8));
    }
    
    /**
     * Get current time in nanoseconds (Unix timestamp * 1,000,000,000)
     * 
     * OpenTelemetry OTLP requires timestamps in nanoseconds
     * 
     * @return int Nanoseconds since Unix epoch
     */
    private function getMicrotime(): int
    {
        return (int)(microtime(true) * 1000000000);
    }
    
    /**
     * Normalize attributes (convert to string/int/float/bool)
     * 
     * @param array $attributes
     * @return array
     */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];
        
        foreach ($attributes as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $normalized[$key] = $value;
            } elseif (is_array($value)) {
                $normalized[$key] = array_map('strval', $value);
            } else {
                $normalized[$key] = (string) $value;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Format exception stack trace
     * 
     * @param \Throwable $exception
     * @return string
     */
    private function formatStackTrace(\Throwable $exception): string
    {
        $frames = [];
        
        // First line: where the exception was thrown
        $frames[] = $exception->getFile() . ':' . $exception->getLine();
        
        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            
            if ($class && $function) {
                $function = $class . '::' . $function;
            }
            
            // Only include frames that have file information
            if ($file && $function) {
                $frames[] = sprintf('%s at %s:%d', $function, $file, $line);
            } elseif ($file) {
                $frames[] = sprintf('%s:%d', $file, $line);
            }
        }
        
        return implode("\n", $frames);
    }
    
    /**
     * Build trace payload for sending to TraceKit
     * 
     * Format: OpenTelemetry OTLP JSON format for TraceKit service discovery
     * 
     * @return array
     */
    private function buildTracePayload(): array
    {
        // Filter out incomplete spans (no end_time)
        $completedSpans = array_filter($this->spans, function($span) {
            return isset($span['end_time']) && $span['end_time'] !== null;
        });
        
        if (empty($completedSpans)) {
            return [];
        }
        
        // Convert spans to OpenTelemetry OTLP format
        $spans = [];
        foreach ($completedSpans as $span) {
            // Build attributes array in OTLP format
            $attributes = [];
            foreach ($span['attributes'] ?? [] as $key => $value) {
                $attributes[] = [
                    'key' => $key,
                    'value' => [
                        'stringValue' => (string)$value
                    ]
                ];
            }
            
            // Build events array in OTLP format
            $events = [];
            foreach ($span['events'] ?? [] as $event) {
                $eventAttributes = [];
                foreach ($event['attributes'] ?? [] as $key => $value) {
                    $eventAttributes[] = [
                        'key' => $key,
                        'value' => [
                            'stringValue' => (string)$value
                        ]
                    ];
                }
                
                $events[] = [
                    'name' => $event['name'] ?? 'event',
                    'timeUnixNano' => (string)($event['time'] ?? 0),
                    'attributes' => $eventAttributes,
                ];
            }
            
            // Convert parent_span_id: null should be omitted or empty string in OTLP
            $parentSpanId = $span['parent_span_id'] ?? null;
            
            $spanData = [
                'traceId' => $span['trace_id'],
                'spanId' => $span['span_id'],
                'name' => $span['name'],
                'kind' => $span['kind'],
                'startTimeUnixNano' => (string)$span['start_time'],
                'endTimeUnixNano' => (string)$span['end_time'],
                'attributes' => $attributes,
                'status' => [
                    'code' => $span['status'] === self::STATUS_ERROR ? 'STATUS_CODE_ERROR' : 'STATUS_CODE_OK',
                    'message' => $span['status'] === self::STATUS_ERROR ? ($span['attributes']['error.message'] ?? 'Error') : '',
                ],
                'events' => $events,
            ];
            
            // Only include parentSpanId if it exists (root spans don't have parent)
            if ($parentSpanId !== null) {
                $spanData['parentSpanId'] = $parentSpanId;
            }
            
            $spans[] = $spanData;
        }
        
        // OpenTelemetry OTLP JSON format for TraceKit
        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            [
                                'key' => 'service.name',
                                'value' => [
                                    'stringValue' => $this->serviceName
                                ]
                            ]
                        ]
                    ],
                    'scopeSpans' => [
                        [
                            'spans' => array_values($spans),
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Send traces to TraceKit service (non-blocking)
     * 
     * Uses GEMVC's ApiCall for simple HTTP POST (non-blocking via short timeouts).
     * This is called in shutdown handler, so it won't interfere with the response.
     * 
     * @param array $payload Trace payload
     * @return void
     */
    /**
     * Send traces to TraceKit using fire-and-forget (non-blocking)
     * 
     * Uses AsyncApiCall::fireAndForget() which:
     * - For Apache/Nginx: Uses fastcgi_finish_request() to send response first
     * - For OpenSwoole: Executes in background task
     * - This ensures traces are sent AFTER the HTTP response, without blocking
     * 
     * @param array $payload The trace payload to send
     * @return void
     */
    private function sendTraces(array $payload): void
    {
        try {
            if (empty($payload) || empty($payload['resourceSpans'] ?? [])) {
                error_log("TraceKit: Empty payload, skipping send");
                return;
            }
            
            $spanCount = count($payload['resourceSpans'][0]['scopeSpans'][0]['spans'] ?? []);
            $serviceName = $payload['resourceSpans'][0]['resource']['attributes'][0]['value']['stringValue'] ?? 'unknown';
            $traceId = substr($payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['traceId'] ?? 'N/A', 0, 16);
            error_log("TraceKit: Queueing trace for fire-and-forget send - Service: {$serviceName}, Spans: {$spanCount}, Trace ID: {$traceId}...");
            
            // Use AsyncApiCall with fireAndForget() for truly non-blocking sending
            // This will send HTTP response first, then send traces in background
            $asyncCall = new \Gemvc\Http\AsyncApiCall();
            $asyncCall->setTimeouts(1, 3); // Very short timeouts for logging
            
            // Add POST request with trace payload and required headers
            $asyncCall->addPost('tracekit', $this->endpoint, $payload, [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->apiKey
            ])
                ->onResponse('tracekit', function($result, $requestId) use ($serviceName, $spanCount) {
                    // This callback runs after the HTTP response is sent
                    if (!$result['success']) {
                        error_log("TraceKit: Failed to send traces: " . ($result['error'] ?? 'Unknown error'));
                    } else {
                        $responseCode = $result['http_code'] ?? 0;
                        $responseBody = is_string($result['body']) ? substr($result['body'], 0, 200) : json_encode($result['body']);
                        error_log("TraceKit: ✅ Traces sent successfully (fire-and-forget) - Service: {$serviceName}, Spans: {$spanCount}, HTTP: {$responseCode}");
                        
                        if ($responseCode >= 400) {
                            error_log("TraceKit: Warning - HTTP {$responseCode} response from TraceKit. Response: {$responseBody}");
                        }
                    }
                });
            
            // Fire and forget - this sends HTTP response first, then executes in background
            $asyncCall->fireAndForget();
            
        } catch (\Throwable $e) {
            // Silently fail - don't let TraceKit break your app
            error_log("TraceKit: Error sending traces: " . $e->getMessage());
        }
    }
}

